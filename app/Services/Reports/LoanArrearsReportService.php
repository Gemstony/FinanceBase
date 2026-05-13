<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Loans\Risk\LoanDelinquencyEngine;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Models\LoanProducts;
use App\Models\SubShop;
use App\Models\User;

class LoanArrearsReportService
{
    public function __construct(
        private readonly LoanDelinquencyEngine $delinquencyEngine,
    ) {
    }
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
        // Use LoanDelinquencyEngine for single source of truth on portfolio outstanding
        return $this->delinquencyEngine->calculatePortfolioOutstandingFromInstallments($subshopIds, $loanIds);
    }

    private function arrearsLoansBase(array $subshopIds, $loanIds, Carbon $asAt): QueryBuilder
    {
        // Use LoanDelinquencyEngine as single source of truth for delinquency data
        // Engine provides: loan_id, max_dpd, overdue_amount, outstanding_balance
        $base = $this->delinquencyEngine->delinquencyBaseQuery($subshopIds, $loanIds, $asAt);

        $asAtDate = $asAt->toDateString();

        // Supplement with installment-level details that engine doesn't provide
        $installmentDetails = DB::table('loan_installments as li')
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<', $asAtDate)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('COUNT(*) as overdue_installments')
            ->selectRaw('MIN(li.due_date) as oldest_due_date')
            ->groupBy('li.loan_id');

        // Wrap and map engine columns to report-compatible names
        return DB::query()
            ->fromSub($base, 'd')
            ->leftJoinSub($installmentDetails, 'idet', fn ($j) => $j->on('idet.loan_id', '=', 'd.loan_id'))
            ->selectRaw('d.loan_id as loan_id')
            ->selectRaw('d.max_dpd as dpd')
            ->selectRaw('d.overdue_amount as arrears_amount')
            ->selectRaw('d.outstanding_balance as outstanding_balance')
            ->selectRaw('COALESCE(idet.overdue_installments, 0) as overdue_installments')
            ->selectRaw('idet.oldest_due_date as oldest_due_date')
            ->where('d.overdue_amount', '>', 0);
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

        // Use LoanDelinquencyEngine's arrearsLoansBase for single source of truth on arrears data
        // This ensures DPD and arrears calculations are consistent across all report sections
        $arrearsBase = $this->arrearsLoansBase($subshopIds, $loanIds, $asAt);

        // Filter by DPD range if specified
        if ($dpdMin !== null) {
            $arrearsBase = DB::query()->fromSub($arrearsBase, 'base')->where('dpd', '>=', $dpdMin);
        }
        if ($dpdMax !== null) {
            $arrearsBase = DB::query()->fromSub($arrearsBase, 'base')->where('dpd', '<=', $dpdMax);
        }

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

        // Join arrears base with loan details
        $q = DB::table('loans')
            ->joinSub($arrearsBase, 'od', fn ($j) => $j->on('od.loan_id', '=', 'loans.id'))
            ->leftJoinSub($lastPayment, 'lp', fn ($j) => $j->on('lp.loan_id', '=', 'loans.id'))
            ->leftJoinSub($officerMap, 'om', fn ($j) => $j->on('om.loan_id', '=', 'loans.id'))
            ->leftJoin('users as u', 'u.id', '=', 'om.officer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as p', 'p.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->whereIn('loans.subshop_id', $subshopIds)
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

        // Use LoanDelinquencyEngine for installment-level query (single source of truth)
        // Engine provides: DPD, aging buckets, officer, branch, product, customer info
        $q = $this->delinquencyEngine->installmentLevelBaseQuery(
            $subshopIds,
            $asAt,
            $filters['loan_product_id'] ?? null,
            $filters['loan_officer_id'] ?? null,
            $filters['loan_status'] ?? null,
            null, // customer_search - we use customer_id filter instead
            $filters['dpd_min'] ?? null,
            $filters['dpd_max'] ?? null
        );

        // Add loanIds filter if specific loans are filtered
        if ($loanIds->isNotEmpty()) {
            $q->whereIn('li.loan_id', $loanIds->toArray());
        } else {
            $q->whereRaw('1=0');
        }

        // Filter to overdue installments only (due_date < asAt)
        // Note: Engine's query already filters outstanding_amount > 0, we add the overdue date filter
        $asAtDate = $asAt->toDateString();
        $q->whereDate('li.due_date', '<', $asAtDate);

        return $q->addSelect(DB::raw('li.outstanding_amount as arrears_amount'))
            ->addSelect(DB::raw('li.total_due as installment_amount'))
            ->orderByDesc('dpd')
            ->orderByDesc('arrears_amount')
            ->paginate($perPage, ['*'], 'installments_page', $page);
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
        // Build month-end dates for the last 12 months
        $monthEnds = [];
        $start = (clone $asAt)->startOfMonth()->subMonths(11);
        for ($d = $start->copy(); $d->lte($asAt); $d->addMonth()) {
            $monthEnd = $d->copy()->endOfMonth();
            if ($monthEnd->gt($asAt)) {
                $monthEnd = $asAt->copy();
            }
            $monthEnds[] = $monthEnd;
        }

        if ($loanIds->isEmpty()) {
            // Return empty trend if no loans match filters
            return collect($monthEnds)->map(fn ($dt) => [
                'date' => $dt->toDateString(),
                'total_arrears' => 0.0,
            ])->all();
        }

        // Batch query: Get all arrears data in one query, then calculate per-month totals
        // This avoids N+1 queries (12 separate queries)
        $startDate = $monthEnds[0]->copy()->startOfMonth();
        $endDate = $asAt->copy();

        // Get all overdue installments that could be in the date range
        $loanIdsArray = $loanIds->toArray();
        $overdueData = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('li.loan_id', $loanIdsArray)
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<', $endDate->toDateString())
            ->whereDate('li.due_date', '>=', $startDate->toDateString())
            ->select(['li.loan_id', 'li.due_date', 'li.outstanding_amount'])
            ->get();

        // Group by month and calculate totals
        $arrearsByMonth = [];
        foreach ($monthEnds as $dt) {
            $monthKey = $dt->toDateString();
            $monthCutoff = $dt->toDateString();

            $total = $overdueData
                ->filter(fn ($row) => $row->due_date < $monthCutoff)
                ->sum('outstanding_amount');

            $arrearsByMonth[] = [
                'date' => $monthKey,
                'total_arrears' => round((float) $total, 2),
            ];
        }

        return $arrearsByMonth;
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
