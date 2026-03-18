<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LoanProducts;
use App\Models\SubShop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LoanAgingReportService
{
    /**
     * @param array{as_at_date:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,dpd_min?:int|null,dpd_max?:int|null,page?:int|null,per_page?:int|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $asAt = $filters['as_at_date'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $loanIds = $this->filteredLoanIds($filters, $subshopIds);

        $loanAgg = $this->loanAgingBase($subshopIds, $loanIds, $asAt);

        $portfolioOutstanding = (float) DB::query()->fromSub($loanAgg, 'la')->sum('la.outstanding_balance');
        $totalOverdueAmount = (float) DB::query()->fromSub($loanAgg, 'la')->sum('la.overdue_amount');

        $summary = $this->summaryKpis($loanAgg, $portfolioOutstanding, $totalOverdueAmount);

        $agingBuckets = $this->agingBuckets($loanAgg, $portfolioOutstanding);

        $loanLevel = $this->loanLevelList($filters, $subshopIds, $loanIds, $asAt);

        $byProduct = $this->agingByProduct($loanAgg, $subshopIds);
        $byBranch = $this->agingByBranch($loanAgg, $subshopIds);
        $byOfficer = $this->agingByOfficer($loanAgg, $subshopIds, $loanIds);

        $highRisk = $this->highRiskLoans($loanAgg);
        $dpdDistribution = $this->dpdDistribution($loanAgg);
        $writeoff = $this->writeoffCandidates($loanAgg, $subshopIds, $loanIds, $asAt);

        $trends = $this->agingTrends($filters, $accessibleSubshopIds);

        return [
            'filters' => [
                'as_at_date' => $asAt->toDateString(),
                'subshop_ids' => $subshopIds,
                'dpd_min' => $filters['dpd_min'] ?? null,
                'dpd_max' => $filters['dpd_max'] ?? null,
            ],
            'portfolio_outstanding' => round($portfolioOutstanding, 2),
            'summary' => $summary,
            'aging_buckets' => $agingBuckets,
            'loans' => $loanLevel,
            'by_product' => $byProduct,
            'by_branch' => $byBranch,
            'by_officer' => $byOfficer,
            'high_risk' => $highRisk,
            'dpd_distribution' => $dpdDistribution,
            'trends' => $trends,
            'writeoff_candidates' => $writeoff,
        ];
    }

    /** @return array<int> */
    private function resolveSubshopFilter(?int $subshopId, array $accessibleSubshopIds): array
    {
        if ($subshopId) {
            return in_array($subshopId, $accessibleSubshopIds, true) ? [$subshopId] : [];
        }

        return $accessibleSubshopIds;
    }

    /**
     * Officer is derived from latest disbursement processor (loan_disbursements.processed_by).
     *
     * @param array{loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null} $filters
     */
    private function filteredLoanIds(array $filters, array $subshopIds)
    {
        $q = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when(!empty($filters['loan_product_id']), fn ($qq) => $qq->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when(!empty($filters['loan_status']), fn ($qq) => $qq->where('loans.status', (string) $filters['loan_status']));

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

    private function loanAgingBase(array $subshopIds, $loanIds, Carbon $asAt): QueryBuilder
    {
        $unpaid = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.outstanding_amount) as outstanding_balance')
            ->groupBy('li.loan_id');

        $overdue = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<', $asAt->toDateString())
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('MAX(DATEDIFF(?, li.due_date)) as dpd', [$asAt->toDateString()])
            ->selectRaw('SUM(li.outstanding_amount) as overdue_amount')
            ->groupBy('li.loan_id');

        return DB::query()
            ->fromSub($unpaid, 'u')
            ->leftJoinSub($overdue, 'o', fn ($j) => $j->on('o.loan_id', '=', 'u.loan_id'))
            ->selectRaw('u.loan_id as loan_id')
            ->selectRaw('COALESCE(o.dpd, 0) as dpd')
            ->selectRaw('COALESCE(o.overdue_amount, 0) as overdue_amount')
            ->selectRaw('u.outstanding_balance as outstanding_balance');
    }

    private function summaryKpis(QueryBuilder $loanAgg, float $portfolioOutstanding, float $totalOverdueAmount): array
    {
        $performingLoans = (int) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '<=', 0)->count('la.loan_id');
        $nplLoans = (int) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 90)->count('la.loan_id');

        $avgDpd = (float) DB::query()->fromSub($loanAgg, 'la')->avg('la.dpd');
        $maxDpd = (int) DB::query()->fromSub($loanAgg, 'la')->max('la.dpd');

        $par30Outstanding = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 30)->sum('la.outstanding_balance');
        $par60Outstanding = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 60)->sum('la.outstanding_balance');
        $par90Outstanding = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 90)->sum('la.outstanding_balance');

        $par30 = $portfolioOutstanding > 0 ? round(($par30Outstanding / $portfolioOutstanding) * 100, 2) : 0.0;
        $par60 = $portfolioOutstanding > 0 ? round(($par60Outstanding / $portfolioOutstanding) * 100, 2) : 0.0;
        $par90 = $portfolioOutstanding > 0 ? round(($par90Outstanding / $portfolioOutstanding) * 100, 2) : 0.0;

        return [
            'total_outstanding' => round($portfolioOutstanding, 2),
            'total_overdue_amount' => round($totalOverdueAmount, 2),
            'performing_loans' => $performingLoans,
            'non_performing_loans' => $nplLoans,
            'avg_dpd' => round($avgDpd ?: 0.0, 2),
            'max_dpd' => $maxDpd,
            'par30_pct' => $par30,
            'par60_pct' => $par60,
            'par90_pct' => $par90,
        ];
    }

    private function agingBuckets(QueryBuilder $loanAgg, float $portfolioOutstanding): array
    {
        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->selectRaw("CASE
                WHEN la.dpd <= 0 THEN 'Current'
                WHEN la.dpd <= 30 THEN '1-30'
                WHEN la.dpd <= 60 THEN '31-60'
                WHEN la.dpd <= 90 THEN '61-90'
                ELSE '90+'
            END as bucket")
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->groupBy('bucket')
            ->get();

        $order = ['Current' => 0, '1-30' => 1, '31-60' => 2, '61-90' => 3, '90+' => 4];

        $mapped = $rows->map(function ($r) use ($portfolioOutstanding) {
            $out = (float) ($r->outstanding ?? 0);
            $pct = $portfolioOutstanding > 0 ? round(($out / $portfolioOutstanding) * 100, 2) : 0.0;

            return [
                'bucket' => (string) ($r->bucket ?? ''),
                'loans' => (int) ($r->loans_count ?? 0),
                'outstanding' => round($out, 2),
                'pct' => $pct,
            ];
        })->all();

        usort($mapped, fn ($a, $b) => ($order[$a['bucket']] ?? 99) <=> ($order[$b['bucket']] ?? 99));

        return $mapped;
    }

    private function loanLevelList(array $filters, array $subshopIds, $loanIds, Carbon $asAt): LengthAwarePaginator
    {
        $dpdMin = array_key_exists('dpd_min', $filters) && $filters['dpd_min'] !== null ? (int) $filters['dpd_min'] : null;
        $dpdMax = array_key_exists('dpd_max', $filters) && $filters['dpd_max'] !== null ? (int) $filters['dpd_max'] : null;

        $perPage = !empty($filters['per_page']) ? max(5, min(200, (int) $filters['per_page'])) : 25;
        $page = !empty($filters['page']) ? max(1, (int) $filters['page']) : null;

        $loanAgg = $this->loanAgingBase($subshopIds, $loanIds, $asAt);

        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $officerMap = DB::table('loan_disbursements as ld')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.id', '=', 'ld.id'))
            ->selectRaw('ld.loan_id as loan_id, ld.processed_by as officer_id');

        $q = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->leftJoinSub($officerMap, 'om', fn ($j) => $j->on('om.loan_id', '=', 'loans.id'))
            ->leftJoin('users as u', 'u.id', '=', 'om.officer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as p', 'p.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->when($dpdMin !== null, fn ($qq) => $qq->where('la.dpd', '>=', $dpdMin))
            ->when($dpdMax !== null, fn ($qq) => $qq->where('la.dpd', '<=', $dpdMax))
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'c.name as customer',
                'p.name as product',
                'ss.name as branch',
                'u.name as officer',
                'loans.status as loan_status',
                DB::raw('COALESCE(la.outstanding_balance,0) as outstanding_balance'),
                DB::raw('COALESCE(la.overdue_amount,0) as overdue_amount'),
                DB::raw('COALESCE(la.dpd,0) as dpd'),
            ])
            ->orderByDesc('la.dpd')
            ->orderByDesc('la.outstanding_balance');

        return $q->paginate($perPage, ['*'], 'page', $page);
    }

    private function agingByProduct(QueryBuilder $loanAgg, array $subshopIds): array
    {
        $products = LoanProducts::query()->whereIn('subshop_id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw("SUM(CASE WHEN la.dpd <= 0 THEN la.outstanding_balance ELSE 0 END) as current_amt")
            ->selectRaw("SUM(CASE WHEN la.dpd BETWEEN 1 AND 30 THEN la.outstanding_balance ELSE 0 END) as d1_30")
            ->selectRaw("SUM(CASE WHEN la.dpd BETWEEN 31 AND 60 THEN la.outstanding_balance ELSE 0 END) as d31_60")
            ->selectRaw("SUM(CASE WHEN la.dpd BETWEEN 61 AND 90 THEN la.outstanding_balance ELSE 0 END) as d61_90")
            ->selectRaw("SUM(CASE WHEN la.dpd > 90 THEN la.outstanding_balance ELSE 0 END) as d90p")
            ->groupBy('product_id')
            ->get();

        return $rows->map(function ($r) use ($products) {
            $pid = (int) $r->product_id;

            return [
                'product_id' => $pid,
                'product' => (string) ($products[$pid]->name ?? 'Unknown'),
                'current' => round((float) ($r->current_amt ?? 0), 2),
                'd1_30' => round((float) ($r->d1_30 ?? 0), 2),
                'd31_60' => round((float) ($r->d31_60 ?? 0), 2),
                'd61_90' => round((float) ($r->d61_90 ?? 0), 2),
                'd90p' => round((float) ($r->d90p ?? 0), 2),
            ];
        })->values()->all();
    }

    private function agingByBranch(QueryBuilder $loanAgg, array $subshopIds): array
    {
        $subshops = SubShop::query()->whereIn('id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->selectRaw('loans.subshop_id as subshop_id')
            ->selectRaw("SUM(CASE WHEN la.dpd <= 0 THEN la.outstanding_balance ELSE 0 END) as current_amt")
            ->selectRaw("SUM(CASE WHEN la.dpd BETWEEN 1 AND 30 THEN la.outstanding_balance ELSE 0 END) as d1_30")
            ->selectRaw("SUM(CASE WHEN la.dpd BETWEEN 31 AND 60 THEN la.outstanding_balance ELSE 0 END) as d31_60")
            ->selectRaw("SUM(CASE WHEN la.dpd BETWEEN 61 AND 90 THEN la.outstanding_balance ELSE 0 END) as d61_90")
            ->selectRaw("SUM(CASE WHEN la.dpd > 90 THEN la.outstanding_balance ELSE 0 END) as d90p")
            ->groupBy('subshop_id')
            ->get();

        return $rows->map(function ($r) use ($subshops) {
            $sid = (int) $r->subshop_id;

            return [
                'subshop_id' => $sid,
                'branch' => (string) ($subshops[$sid]->name ?? 'Unknown'),
                'current' => round((float) ($r->current_amt ?? 0), 2),
                'd1_30' => round((float) ($r->d1_30 ?? 0), 2),
                'd31_60' => round((float) ($r->d31_60 ?? 0), 2),
                'd61_90' => round((float) ($r->d61_90 ?? 0), 2),
                'd90p' => round((float) ($r->d90p ?? 0), 2),
            ];
        })->values()->all();
    }

    private function agingByOfficer(QueryBuilder $loanAgg, array $subshopIds, $loanIds): array
    {
        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $loanOfficer = DB::table('loans')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->select(['loans.id as loan_id', 'ld.processed_by as officer_id']);

        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->joinSub($loanOfficer, 'lo', fn ($j) => $j->on('lo.loan_id', '=', 'la.loan_id'))
            ->selectRaw('lo.officer_id as officer_id')
            ->selectRaw("SUM(CASE WHEN la.dpd <= 0 THEN la.outstanding_balance ELSE 0 END) as current_amt")
            ->selectRaw("SUM(CASE WHEN la.dpd BETWEEN 1 AND 30 THEN la.outstanding_balance ELSE 0 END) as d1_30")
            ->selectRaw("SUM(CASE WHEN la.dpd BETWEEN 31 AND 60 THEN la.outstanding_balance ELSE 0 END) as d31_60")
            ->selectRaw("SUM(CASE WHEN la.dpd BETWEEN 61 AND 90 THEN la.outstanding_balance ELSE 0 END) as d61_90")
            ->selectRaw("SUM(CASE WHEN la.dpd > 90 THEN la.outstanding_balance ELSE 0 END) as d90p")
            ->groupBy('lo.officer_id')
            ->get();

        $officerIds = $rows->pluck('officer_id')->filter()->unique()->values();
        $users = User::query()->whereIn('id', $officerIds)->get(['id', 'name'])->keyBy('id');

        return $rows->map(function ($r) use ($users) {
            $oid = (int) $r->officer_id;

            return [
                'officer_id' => $oid,
                'officer' => (string) ($users[$oid]->name ?? 'Unknown'),
                'current' => round((float) ($r->current_amt ?? 0), 2),
                'd1_30' => round((float) ($r->d1_30 ?? 0), 2),
                'd31_60' => round((float) ($r->d31_60 ?? 0), 2),
                'd61_90' => round((float) ($r->d61_90 ?? 0), 2),
                'd90p' => round((float) ($r->d90p ?? 0), 2),
            ];
        })->values()->all();
    }

    private function highRiskLoans(QueryBuilder $loanAgg): array
    {
        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->where('la.dpd', '>', 60)
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'c.name as customer',
                DB::raw('la.dpd as dpd'),
                DB::raw('la.outstanding_balance as outstanding'),
            ])
            ->orderByDesc('la.dpd')
            ->orderByDesc('la.outstanding_balance')
            ->limit(10)
            ->get();

        return $rows->map(function ($r) {
            $dpd = (int) ($r->dpd ?? 0);

            $risk = match (true) {
                $dpd > 120 => 'SEVERE',
                $dpd > 90 => 'CRITICAL',
                $dpd > 60 => 'HIGH',
                default => 'MEDIUM',
            };

            return [
                'loan_id' => (int) $r->loan_id,
                'loan_code' => (string) $r->loan_code,
                'customer' => (string) ($r->customer ?? 'Unknown'),
                'dpd' => $dpd,
                'outstanding' => round((float) ($r->outstanding ?? 0), 2),
                'risk_level' => $risk,
            ];
        })->values()->all();
    }

    private function dpdDistribution(QueryBuilder $loanAgg): array
    {
        $avg = (float) DB::query()->fromSub($loanAgg, 'la')->avg('la.dpd');
        $max = (int) DB::query()->fromSub($loanAgg, 'la')->max('la.dpd');

        $distRows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->selectRaw("CASE
                WHEN la.dpd <= 0 THEN '0'
                WHEN la.dpd <= 30 THEN '1-30'
                WHEN la.dpd <= 60 THEN '31-60'
                WHEN la.dpd <= 90 THEN '61-90'
                WHEN la.dpd <= 120 THEN '91-120'
                ELSE '120+'
            END as bucket")
            ->selectRaw('COUNT(*) as loans')
            ->groupBy('bucket')
            ->get();

        $order = ['0' => 0, '1-30' => 1, '31-60' => 2, '61-90' => 3, '91-120' => 4, '120+' => 5];

        $dist = $distRows->map(fn ($r) => [
            'bucket' => (string) ($r->bucket ?? ''),
            'loans' => (int) ($r->loans ?? 0),
        ])->all();

        usort($dist, fn ($a, $b) => ($order[$a['bucket']] ?? 99) <=> ($order[$b['bucket']] ?? 99));

        return [
            'avg_dpd' => round($avg ?: 0.0, 2),
            'max_dpd' => $max,
            'distribution' => $dist,
        ];
    }

    private function writeoffCandidates(QueryBuilder $loanAgg, array $subshopIds, $loanIds, Carbon $asAt): array
    {
        $lastPayment = DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereDate('lp.payment_date', '<=', $asAt->toDateString())
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('lp.loan_id as loan_id, MAX(lp.payment_date) as last_payment_date')
            ->groupBy('lp.loan_id');

        $cutoff = (clone $asAt)->subDays(30)->toDateString();

        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->leftJoinSub($lastPayment, 'lp', fn ($j) => $j->on('lp.loan_id', '=', 'loans.id'))
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->where('la.dpd', '>', 120)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('lp.last_payment_date')->orWhereDate('lp.last_payment_date', '<', $cutoff);
            })
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'c.name as customer',
                DB::raw('la.dpd as dpd'),
                DB::raw('la.outstanding_balance as outstanding'),
                'lp.last_payment_date as last_payment_date',
            ])
            ->orderByDesc('la.dpd')
            ->orderByDesc('la.outstanding_balance')
            ->limit(50)
            ->get();

        return $rows->map(fn ($r) => [
            'loan_id' => (int) $r->loan_id,
            'loan_code' => (string) $r->loan_code,
            'customer' => (string) ($r->customer ?? 'Unknown'),
            'dpd' => (int) ($r->dpd ?? 0),
            'outstanding' => round((float) ($r->outstanding ?? 0), 2),
            'last_payment_date' => $r->last_payment_date,
            'recommendation' => 'Consider Write-Off',
        ])->values()->all();
    }

    /**
     * @param array{as_at_date:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    private function agingTrends(array $filters, array $accessibleSubshopIds): array
    {
        $asAt = $filters['as_at_date'];
        $from = (clone $asAt)->subMonthsNoOverflow(11)->startOfMonth();
        $months = $this->monthsBetween($from, $asAt);

        $labels = [];
        $par30 = [];
        $par90 = [];
        $overdue = [];

        foreach ($months as $monthEnd) {
            $label = $monthEnd->format('Y-m');
            $labels[] = $label;

            $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
            $loanIds = $this->filteredLoanIds($filters, $subshopIds);
            $loanAgg = $this->loanAgingBase($subshopIds, $loanIds, $monthEnd);

            $portfolioOutstanding = (float) DB::query()->fromSub($loanAgg, 'la')->sum('la.outstanding_balance');
            $overdueAmt = (float) DB::query()->fromSub($loanAgg, 'la')->sum('la.overdue_amount');
            $par30Out = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 30)->sum('la.outstanding_balance');
            $par90Out = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 90)->sum('la.outstanding_balance');

            $par30[] = $portfolioOutstanding > 0 ? round(($par30Out / $portfolioOutstanding) * 100, 2) : 0.0;
            $par90[] = $portfolioOutstanding > 0 ? round(($par90Out / $portfolioOutstanding) * 100, 2) : 0.0;
            $overdue[] = round($overdueAmt, 2);
        }

        return [
            'labels' => $labels,
            'par30' => $par30,
            'par90' => $par90,
            'overdue_amount' => $overdue,
        ];
    }

    /** @return array<int, Carbon> */
    private function monthsBetween(Carbon $from, Carbon $to): array
    {
        $cur = (clone $from)->startOfMonth();
        $end = (clone $to)->endOfMonth();

        $months = [];
        while ($cur->lte($end)) {
            $months[] = (clone $cur)->endOfMonth();
            $cur->addMonthNoOverflow();
        }

        return $months;
    }
}
