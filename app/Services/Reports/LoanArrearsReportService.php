<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Models\LoanProducts;
use App\Models\SubShop;
use App\Models\User;

class LoanArrearsReportService
{
    /**
     * @param array{as_at_date:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,dpd_min?:int|null,dpd_max?:int|null,customer_id?:int|null,loan_id?:int|null,per_page?:int|null,page?:int|null,installments_page?:int|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $asAt = $filters['as_at_date'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $loanIds = $this->filteredLoanIds($filters, $subshopIds);

        $portfolioOutstanding = $this->portfolioOutstanding($subshopIds, $loanIds);

        $loanAgg = $this->arrearsLoansBase($subshopIds, $loanIds, $asAt);

        $summary = $this->summaryKpis($loanAgg);

        $loanLevel = $this->loanLevelTable($filters, $subshopIds, $loanIds, $asAt);
        $installmentLevel = $this->installmentLevelTable($filters, $subshopIds, $loanIds, $asAt);

        $aging = $this->agingBuckets($loanAgg);
        $byProduct = $this->arrearsByProduct($loanAgg, $subshopIds);
        $byBranch = $this->arrearsByBranch($loanAgg, $subshopIds);
        $byOfficer = $this->arrearsByOfficer($loanAgg);

        $topDefaulters = $this->topDefaulters($loanAgg);
        $missed = $this->missedInstallmentsAnalysis($loanAgg);
        $trend = $this->arrearsTrend($filters, $subshopIds, $loanIds, $asAt);

        $ratio = $portfolioOutstanding > 0
            ? round(((float) ($summary['total_arrears'] ?? 0) / $portfolioOutstanding) * 100, 2)
            : 0.0;

        $partials = $this->partialPaymentDetection($subshopIds, $loanIds, $asAt);
        $highRisk = $this->highRiskLoans($loanAgg);

        return [
            'filters' => [
                'as_at_date' => $asAt->toDateString(),
                'subshop_ids' => $subshopIds,
                'dpd_min' => $filters['dpd_min'] ?? null,
                'dpd_max' => $filters['dpd_max'] ?? null,
            ],
            'portfolio_outstanding' => round($portfolioOutstanding, 2),
            'summary' => $summary,
            'arrears_ratio_pct' => $ratio,
            'loan_level' => $loanLevel,
            'installment_level' => $installmentLevel,
            'aging_buckets' => $aging,
            'by_product' => $byProduct,
            'by_branch' => $byBranch,
            'by_officer' => $byOfficer,
            'top_defaulters' => $topDefaulters,
            'missed_installments' => $missed,
            'trend' => $trend,
            'partial_overdue' => $partials,
            'high_risk' => $highRisk,
        ];
    }

    /** @return array<int> */
    private function resolveSubshopFilter(?int $subshopId, array $accessibleSubshopIds): array
    {
        if ($subshopId) {
            return in_array($subshopId, $accessibleSubshopIds, true) ? [$subshopId] : [-1];
        }

        return $accessibleSubshopIds ?: [-1];
    }

    /**
     * @param array{loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,customer_id?:int|null,loan_id?:int|null} $filters
     */
    private function filteredLoanIds(array $filters, array $subshopIds)
    {
        $q = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when(!empty($filters['loan_id']), fn ($qq) => $qq->where('loans.id', (int) $filters['loan_id']))
            ->when(!empty($filters['loan_product_id']), fn ($qq) => $qq->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when(!empty($filters['loan_status']), fn ($qq) => $qq->where('loans.status', (string) $filters['loan_status']))
            ->when(!empty($filters['customer_id']), fn ($qq) => $qq->where('loans.customer_id', (int) $filters['customer_id']));

        if (!empty($filters['loan_officer_id'])) {
            $latestDisb = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
                ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', (int) $filters['loan_officer_id']);
        }

        return $q->pluck('loans.id');
    }

    private function portfolioOutstanding(array $subshopIds, $loanIds): float
    {
        return (float) DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->sum('li.outstanding_amount');
    }

    private function arrearsLoansBase(array $subshopIds, $loanIds, Carbon $asAt): QueryBuilder
    {
        $asAtDate = $asAt->toDateString();

        $overdue = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<', $asAtDate)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('COUNT(*) as overdue_installments')
            ->selectRaw('MIN(li.due_date) as oldest_due_date')
            ->selectRaw('MAX(DATEDIFF(?, li.due_date)) as dpd', [$asAtDate])
            ->selectRaw('SUM(li.outstanding_amount) as arrears_amount')
            ->groupBy('li.loan_id');

        return DB::query()->fromSub($overdue, 'o')
            ->where('o.arrears_amount', '>', 0);
    }

    private function summaryKpis(QueryBuilder $loanAgg): array
    {
        $totalArrears = (float) DB::query()->fromSub($loanAgg, 'x')->sum('x.arrears_amount');
        $loans = (int) DB::query()->fromSub($loanAgg, 'x')->count('x.loan_id');
        $installments = (int) DB::query()->fromSub($loanAgg, 'x')->sum('x.overdue_installments');
        $avg = $loans > 0 ? round($totalArrears / $loans, 2) : 0.0;
        $max = (float) DB::query()->fromSub($loanAgg, 'x')->max('x.arrears_amount');

        return [
            'total_arrears' => round($totalArrears, 2),
            'loans_in_arrears' => $loans,
            'overdue_installments' => $installments,
            'avg_arrears_per_loan' => $avg,
            'max_arrears' => round($max, 2),
        ];
    }

    private function loanLevelTable(array $filters, array $subshopIds, $loanIds, Carbon $asAt): LengthAwarePaginator
    {
        $dpdMin = array_key_exists('dpd_min', $filters) && $filters['dpd_min'] !== null ? (int) $filters['dpd_min'] : null;
        $dpdMax = array_key_exists('dpd_max', $filters) && $filters['dpd_max'] !== null ? (int) $filters['dpd_max'] : null;

        $perPage = !empty($filters['per_page']) ? max(5, min(200, (int) $filters['per_page'])) : 25;
        $page = !empty($filters['page']) ? max(1, (int) $filters['page']) : 1;

        $asAtDate = $asAt->toDateString();

        $overdueAgg = DB::table('loan_installments as li')
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<', $asAtDate)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('COUNT(*) as overdue_installments')
            ->selectRaw('MIN(li.due_date) as oldest_due_date')
            ->selectRaw('MAX(DATEDIFF(?, li.due_date)) as dpd', [$asAtDate])
            ->selectRaw('SUM(li.outstanding_amount) as arrears_amount')
            ->groupBy('li.loan_id');

        $lastPayment = DB::table('loan_payments as lp')
            ->where('lp.status', 'confirmed')
            ->whereDate('lp.payment_date', '<=', $asAtDate)
            ->selectRaw('lp.loan_id as loan_id, MAX(lp.payment_date) as last_payment_date')
            ->groupBy('lp.loan_id');

        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $officerMap = DB::table('loan_disbursements as ld')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.id', '=', 'ld.id'))
            ->selectRaw('ld.loan_id as loan_id, ld.processed_by as officer_id');

        $q = DB::table('loans')
            ->joinSub($overdueAgg, 'od', fn ($j) => $j->on('od.loan_id', '=', 'loans.id'))
            ->leftJoinSub($lastPayment, 'lp', fn ($j) => $j->on('lp.loan_id', '=', 'loans.id'))
            ->leftJoinSub($officerMap, 'om', fn ($j) => $j->on('om.loan_id', '=', 'loans.id'))
            ->leftJoin('users as u', 'u.id', '=', 'om.officer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as p', 'p.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->when($dpdMin !== null, fn ($qq) => $qq->where('od.dpd', '>=', $dpdMin))
            ->when($dpdMax !== null, fn ($qq) => $qq->where('od.dpd', '<=', $dpdMax))
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'c.id as customer_id',
                'c.name as customer',
                'p.name as product',
                'ss.name as branch',
                'u.name as officer',
                'loans.status as loan_status',
                DB::raw('COALESCE(od.arrears_amount,0) as arrears_amount'),
                DB::raw('COALESCE(od.overdue_installments,0) as overdue_installments'),
                'od.oldest_due_date as oldest_due_date',
                DB::raw('COALESCE(od.dpd,0) as dpd'),
                'lp.last_payment_date as last_payment_date',
            ])
            ->orderByDesc('od.dpd')
            ->orderByDesc('od.arrears_amount');

        return $q->paginate($perPage, ['*'], 'page', $page);
    }

    private function installmentLevelTable(array $filters, array $subshopIds, $loanIds, Carbon $asAt): LengthAwarePaginator
    {
        $perPage = !empty($filters['per_page']) ? max(5, min(200, (int) $filters['per_page'])) : 25;
        $page = !empty($filters['installments_page']) ? max(1, (int) $filters['installments_page']) : 1;

        $asAtDate = $asAt->toDateString();

        $paidAgg = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->where('lp.status', 'confirmed')
            ->whereDate('lp.payment_date', '<=', $asAtDate)
            ->selectRaw('lpa.loan_installment_id as installment_id')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0) + COALESCE(lpa.interest_amount,0) + COALESCE(lpa.fee_amount,0) + COALESCE(lpa.penalty_amount,0)) as paid_amount')
            ->groupBy('installment_id');

        $q = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->leftJoinSub($paidAgg, 'pd', fn ($j) => $j->on('pd.installment_id', '=', 'li.id'))
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('li.loan_id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<', $asAtDate)
            ->select([
                'li.loan_id as loan_id',
                'loans.loan_code as loan_code',
                'c.id as customer_id',
                'c.name as customer',
                'li.installment_number as installment_number',
                'li.due_date as due_date',
            ])
            ->selectRaw('li.total_due as installment_amount')
            ->selectRaw('COALESCE(pd.paid_amount,0) as paid_amount')
            ->selectRaw('li.outstanding_amount as arrears_amount')
            ->selectRaw('DATEDIFF(?, li.due_date) as dpd', [$asAtDate])
            ->orderByDesc('dpd')
            ->orderByDesc('arrears_amount');

        return $q->paginate($perPage, ['*'], 'installments_page', $page);
    }

    private function agingBuckets(QueryBuilder $loanAgg): array
    {
        $rows = DB::query()
            ->fromSub($loanAgg, 'a')
            ->selectRaw("CASE
                WHEN a.dpd <= 30 THEN '1-30'
                WHEN a.dpd <= 60 THEN '31-60'
                WHEN a.dpd <= 90 THEN '61-90'
                ELSE '90+'
            END as bucket")
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(a.arrears_amount) as arrears_amount')
            ->groupBy('bucket')
            ->get();

        $order = ['1-30' => 1, '31-60' => 2, '61-90' => 3, '90+' => 4];

        $mapped = $rows->map(fn ($r) => [
            'bucket' => (string) ($r->bucket ?? ''),
            'loans' => (int) ($r->loans_count ?? 0),
            'arrears' => round((float) ($r->arrears_amount ?? 0), 2),
        ])->all();

        usort($mapped, fn ($a, $b) => ($order[$a['bucket']] ?? 99) <=> ($order[$b['bucket']] ?? 99));

        return $mapped;
    }

    private function arrearsByProduct(QueryBuilder $loanAgg, array $subshopIds): array
    {
        $products = LoanProducts::query()->whereIn('subshop_id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $rows = DB::query()
            ->fromSub($loanAgg, 'a')
            ->join('loans', 'loans.id', '=', 'a.loan_id')
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(a.arrears_amount) as arrears_amount')
            ->groupBy('product_id')
            ->get();

        $mapped = $rows->map(function ($r) use ($products) {
            $pid = (int) $r->product_id;
            return [
                'product_id' => $pid,
                'product' => (string) ($products[$pid]->name ?? 'Unknown'),
                'loans' => (int) ($r->loans_count ?? 0),
                'arrears' => round((float) ($r->arrears_amount ?? 0), 2),
            ];
        })->values()->all();

        usort($mapped, fn ($a, $b) => ($b['arrears'] <=> $a['arrears']));

        return $mapped;
    }

    private function arrearsByBranch(QueryBuilder $loanAgg, array $subshopIds): array
    {
        $subshops = SubShop::query()->whereIn('id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $rows = DB::query()
            ->fromSub($loanAgg, 'a')
            ->join('loans', 'loans.id', '=', 'a.loan_id')
            ->selectRaw('loans.subshop_id as subshop_id')
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(a.arrears_amount) as arrears_amount')
            ->groupBy('subshop_id')
            ->get();

        $mapped = $rows->map(function ($r) use ($subshops) {
            $sid = (int) $r->subshop_id;
            return [
                'subshop_id' => $sid,
                'branch' => (string) ($subshops[$sid]->name ?? 'Unknown'),
                'loans' => (int) ($r->loans_count ?? 0),
                'arrears' => round((float) ($r->arrears_amount ?? 0), 2),
            ];
        })->values()->all();

        usort($mapped, fn ($a, $b) => ($b['arrears'] <=> $a['arrears']));

        return $mapped;
    }

    private function arrearsByOfficer(QueryBuilder $loanAgg): array
    {
        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $loanOfficer = DB::table('loans')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->select(['loans.id as loan_id', 'ld.processed_by as officer_id']);

        $rows = DB::query()
            ->fromSub($loanAgg, 'a')
            ->joinSub($loanOfficer, 'lo', fn ($j) => $j->on('lo.loan_id', '=', 'a.loan_id'))
            ->selectRaw('lo.officer_id as officer_id')
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(a.arrears_amount) as arrears_amount')
            ->groupBy('lo.officer_id')
            ->get();

        $officerIds = $rows->pluck('officer_id')->filter()->unique()->values();
        $users = User::query()->whereIn('id', $officerIds)->get(['id', 'name'])->keyBy('id');

        $mapped = $rows->map(function ($r) use ($users) {
            $oid = (int) $r->officer_id;
            return [
                'officer_id' => $oid,
                'officer' => (string) ($users[$oid]->name ?? 'Unknown'),
                'loans' => (int) ($r->loans_count ?? 0),
                'arrears' => round((float) ($r->arrears_amount ?? 0), 2),
            ];
        })->values()->all();

        usort($mapped, fn ($a, $b) => ($b['arrears'] <=> $a['arrears']));

        return $mapped;
    }

    private function topDefaulters(QueryBuilder $loanAgg): array
    {
        $rows = DB::query()
            ->fromSub($loanAgg, 'a')
            ->join('loans', 'loans.id', '=', 'a.loan_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->selectRaw('loans.customer_id as customer_id')
            ->selectRaw('COALESCE(c.name, "Unknown") as customer')
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(a.arrears_amount) as arrears_amount')
            ->selectRaw('MAX(a.dpd) as max_dpd')
            ->groupBy('customer_id', 'customer')
            ->orderByDesc('arrears_amount')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'customer_id' => (int) ($r->customer_id ?? 0),
            'customer' => (string) ($r->customer ?? 'Unknown'),
            'loans' => (int) ($r->loans_count ?? 0),
            'arrears' => round((float) ($r->arrears_amount ?? 0), 2),
            'dpd' => (int) ($r->max_dpd ?? 0),
        ])->all();
    }

    private function missedInstallmentsAnalysis(QueryBuilder $loanAgg): array
    {
        $rows = DB::query()
            ->fromSub($loanAgg, 'a')
            ->join('loans', 'loans.id', '=', 'a.loan_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->select([
                'a.loan_id',
                'loans.loan_code as loan_code',
                'c.name as customer',
                'a.overdue_installments as missed_installments',
                'a.arrears_amount as arrears_amount',
            ])
            ->orderByDesc('a.overdue_installments')
            ->orderByDesc('a.arrears_amount')
            ->limit(50)
            ->get();

        return $rows->map(fn ($r) => [
            'loan_id' => (int) ($r->loan_id ?? 0),
            'loan_code' => (string) ($r->loan_code ?? ''),
            'customer' => (string) ($r->customer ?? 'Unknown'),
            'missed_installments' => (int) ($r->missed_installments ?? 0),
            'arrears' => round((float) ($r->arrears_amount ?? 0), 2),
        ])->all();
    }

    private function arrearsTrend(array $filters, array $subshopIds, $loanIds, Carbon $asAt): array
    {
        $months = [];
        $start = (clone $asAt)->startOfMonth()->subMonths(11);
        for ($d = $start->copy(); $d->lte($asAt); $d->addMonth()) {
            $months[] = $d->format('Y-m');
        }

        $rows = [];
        foreach ($months as $ym) {
            $dt = Carbon::createFromFormat('Y-m', $ym)->endOfMonth();
            if ($dt->gt($asAt)) {
                $dt = $asAt->copy();
            }

            $asAtDate = $dt->toDateString();

            $total = (float) DB::table('loan_installments as li')
                ->join('loans', 'loans.id', '=', 'li.loan_id')
                ->whereIn('loans.subshop_id', $subshopIds)
                ->where('li.is_active', true)
                ->where('li.outstanding_amount', '>', 0)
                ->whereDate('li.due_date', '<', $asAtDate)
                ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
                ->sum('li.outstanding_amount');

            $rows[] = [
                'date' => $dt->toDateString(),
                'total_arrears' => round($total, 2),
            ];
        }

        return $rows;
    }

    private function partialPaymentDetection(array $subshopIds, $loanIds, Carbon $asAt): array
    {
        $asAtDate = $asAt->toDateString();

        $q = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->where('li.amount_paid', '>', 0)
            ->whereDate('li.due_date', '<', $asAtDate)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('li.loan_id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'));

        $count = (int) (clone $q)->count('li.id');
        $total = (float) (clone $q)->sum('li.outstanding_amount');

        return [
            'partial_overdue_installments' => $count,
            'partial_arrears' => round($total, 2),
        ];
    }

    private function highRiskLoans(QueryBuilder $loanAgg): array
    {
        $rows = DB::query()
            ->fromSub($loanAgg, 'a')
            ->join('loans', 'loans.id', '=', 'a.loan_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->where('a.dpd', '>=', 60)
            ->select([
                'a.loan_id',
                'loans.loan_code as loan_code',
                'c.name as customer',
                'a.arrears_amount as arrears_amount',
                'a.overdue_installments as overdue_installments',
                'a.dpd as dpd',
            ])
            ->orderByDesc('a.dpd')
            ->orderByDesc('a.arrears_amount')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'loan_id' => (int) ($r->loan_id ?? 0),
            'loan_code' => (string) ($r->loan_code ?? ''),
            'customer' => (string) ($r->customer ?? 'Unknown'),
            'arrears' => round((float) ($r->arrears_amount ?? 0), 2),
            'missed_installments' => (int) ($r->overdue_installments ?? 0),
            'dpd' => (int) ($r->dpd ?? 0),
        ])->all();
    }
}
