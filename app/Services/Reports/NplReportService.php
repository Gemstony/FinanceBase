<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Loans\Risk\LoanDelinquencyEngine;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NplReportService
{
    public function __construct(
        private readonly PortfolioRiskCalculator $portfolioRiskCalculator,
        private readonly LoanDelinquencyEngine $delinquencyEngine,
    ) {}

    /**
     * @param  array{as_of:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,dpd_min?:int|null,dpd_max?:int|null,dpd_threshold?:int|null,page?:int|null,per_page?:int|null}  $filters
     * @param  array<int>  $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $asOf = $filters['as_of'];
        $threshold = isset($filters['dpd_threshold']) ? max(0, (int) $filters['dpd_threshold']) : 90;

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $loanIds = $this->filteredLoanIds($filters, $subshopIds);

        $portfolioOutstanding = $this->portfolioOutstanding($subshopIds, $loanIds);

        $summary = $this->summaryKpis($subshopIds, $loanIds, $asOf, $threshold, $portfolioOutstanding);
        $aging = $this->nplAgingBreakdown($subshopIds, $loanIds, $asOf, $threshold, (float) ($summary['total_npl_amount'] ?? 0));

        $nplList = $this->nplLoanList($filters, $subshopIds, $loanIds, $asOf, $threshold);
        $byProduct = $this->nplByProduct($subshopIds, $loanIds, $asOf, $threshold);
        $byBranch = $this->nplByBranch($subshopIds, $loanIds, $asOf, $threshold);
        $byOfficer = $this->nplByOfficer($subshopIds, $loanIds, $asOf, $threshold);
        $topCustomers = $this->topNplCustomers($subshopIds, $loanIds, $asOf, $threshold);

        $trends = $this->nplTrends($subshopIds, $loanIds, $asOf, $threshold);
        $recovery = $this->recoveryTracking($subshopIds, $loanIds, $asOf, $threshold, (float) ($summary['total_npl_amount'] ?? 0));
        $writeoff = $this->writeoffCandidates($subshopIds, $loanIds, $asOf, max($threshold, 120));

        return [
            'filters' => [
                'as_of' => $asOf->toDateString(),
                'subshop_ids' => $subshopIds,
                'dpd_min' => $filters['dpd_min'] ?? null,
                'dpd_max' => $filters['dpd_max'] ?? null,
                'dpd_threshold' => $threshold,
            ],
            'portfolio_outstanding' => round($portfolioOutstanding, 2),
            'summary' => $summary,
            'aging' => $aging,
            'npl_loans' => $nplList,
            'by_product' => $byProduct,
            'by_branch' => $byBranch,
            'by_officer' => $byOfficer,
            'top_customers' => $topCustomers,
            'trends' => $trends,
            'recovery' => $recovery,
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
     * @param  array{loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null}  $filters
     */
    private function filteredLoanIds(array $filters, array $subshopIds)
    {
        $q = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->when(! empty($filters['loan_product_id']), fn ($qq) => $qq->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when(! empty($filters['loan_status']), fn ($qq) => $qq->where('loans.status', (string) $filters['loan_status']));

        if (! empty($filters['loan_officer_id'])) {
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
        // Use delinquencyEngine to respect loanIds filters (product, officer, status)
        return $this->delinquencyEngine->calculatePortfolioOutstandingFromInstallments($subshopIds, $loanIds);
    }

    /**
     * Use LoanDelinquencyEngine as single source of truth for delinquency data.
     * Returns: loan_id, max_dpd, overdue_amount, outstanding_balance
     */
    private function delinquentLoansBase(array $subshopIds, $loanIds, Carbon $asOf): QueryBuilder
    {
        // Use delinquencyEngine for consistent DPD calculation
        return $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, $asOf);
    }

    private function summaryKpis(array $subshopIds, $loanIds, Carbon $asOf, int $threshold, float $portfolioOutstanding): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds, $asOf);

        $nplAmount = (float) DB::query()->fromSub($delinq, 'd')->where('d.max_dpd', '>=', $threshold)->sum('d.outstanding_balance');
        $nplLoans = (int) DB::query()->fromSub($delinq, 'd')->where('d.max_dpd', '>=', $threshold)->count('d.loan_id');

        $nplRatio = $portfolioOutstanding > 0 ? round(($nplAmount / $portfolioOutstanding) * 100, 2) : 0.0;

        return [
            'total_npl_loans' => $nplLoans,
            'total_npl_amount' => round($nplAmount, 2),
            'npl_ratio_pct' => $nplRatio,
        ];
    }

    private function nplAgingBreakdown(array $subshopIds, $loanIds, Carbon $asOf, int $threshold, float $nplAmountTotal): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds, $asOf);

        $rows = DB::query()
            ->fromSub($delinq, 'd')
            ->where('d.max_dpd', '>=', $threshold)
            ->selectRaw("CASE
                WHEN d.max_dpd <= 120 THEN '90-120'
                WHEN d.max_dpd <= 180 THEN '121-180'
                ELSE '180+'
            END as bucket")
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(d.outstanding_balance) as outstanding')
            ->groupBy('bucket')
            ->get();

        $order = ['90-120' => 0, '121-180' => 1, '180+' => 2];

        $mapped = $rows->map(function ($r) use ($nplAmountTotal) {
            $out = (float) ($r->outstanding ?? 0);
            $pct = $nplAmountTotal > 0 ? round(($out / $nplAmountTotal) * 100, 2) : 0.0;

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

    private function nplLoanList(array $filters, array $subshopIds, $loanIds, Carbon $asOf, int $threshold): LengthAwarePaginator
    {
        $asOfDate = $asOf->toDateString();

        $dpdMin = array_key_exists('dpd_min', $filters) && $filters['dpd_min'] !== null ? (int) $filters['dpd_min'] : ($threshold + 1);
        $dpdMax = array_key_exists('dpd_max', $filters) && $filters['dpd_max'] !== null ? (int) $filters['dpd_max'] : null;

        $perPage = ! empty($filters['per_page']) ? max(5, min(200, (int) $filters['per_page'])) : 25;
        $page = ! empty($filters['page']) ? max(1, (int) $filters['page']) : null;

        $overdueAgg = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('li.is_active', true)
            ->where('li.status', 'overdue')
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<=', $asOfDate)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('MAX(DATEDIFF(?, li.due_date)) as dpd', [$asOfDate])
            ->selectRaw('SUM(li.outstanding_amount) as overdue_amount')
            ->groupBy('li.loan_id');

        $allOutstanding = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('li.is_active', true)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.outstanding_amount) as outstanding_balance')
            ->groupBy('li.loan_id');

        $lastPayment = DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('lp.status', 'confirmed')
            ->whereDate('lp.payment_date', '<=', $asOfDate)
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
            ->joinSub($allOutstanding, 'ao', fn ($j) => $j->on('ao.loan_id', '=', 'loans.id'))
            ->leftJoinSub($lastPayment, 'lp', fn ($j) => $j->on('lp.loan_id', '=', 'loans.id'))
            ->leftJoinSub($officerMap, 'om', fn ($j) => $j->on('om.loan_id', '=', 'loans.id'))
            ->leftJoin('users as u', 'u.id', '=', 'om.officer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as p', 'p.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->when($dpdMin !== null, fn ($qq) => $qq->where('od.dpd', '>=', $dpdMin))
            ->when($dpdMax !== null, fn ($qq) => $qq->where('od.dpd', '<=', $dpdMax))
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'c.name as customer',
                'p.name as product',
                'ss.name as branch',
                'u.name as officer',
                'loans.status as loan_status',
                DB::raw('COALESCE(ao.outstanding_balance,0) as outstanding_balance'),
                DB::raw('COALESCE(od.dpd,0) as dpd'),
                'lp.last_payment_date as last_payment_date',
                DB::raw('DATEDIFF(?, lp.last_payment_date) as days_since_last_payment'),
            ])
            ->addBinding($asOfDate, 'select')
            ->orderByDesc('od.dpd');

        return $q->paginate($perPage, ['*'], 'page', $page);
    }

    private function nplByProduct(array $subshopIds, $loanIds, Carbon $asOf, int $threshold): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds, $asOf);

        // Get all products for these loans
        $products = DB::table('loan_products')
            ->whereIn('subshop_id', $subshopIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        // NPL aggregation per product using SQL (replaces N+1 loop)
        $nplByProduct = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->where('d.max_dpd', '>=', $threshold)
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw('COUNT(*) as npl_loans')
            ->selectRaw('SUM(d.outstanding_balance) as npl_amount')
            ->groupBy('loans.loan_product_id')
            ->get()->keyBy('product_id');

        // Portfolio outstanding per product using SQL (replaces N+1 loop)
        $portfolioByProduct = DB::table('loans')
            ->joinSub($delinq, 'd', fn ($j) => $j->on('d.loan_id', '=', 'loans.id'))
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw('SUM(d.outstanding_balance) as outstanding')
            ->groupBy('loans.loan_product_id')
            ->pluck('outstanding', 'product_id');

        $mapped = [];
        foreach ($products as $pid => $p) {
            $nplAmt = (float) ($nplByProduct[$pid]?->npl_amount ?? 0);
            $totalOut = (float) ($portfolioByProduct[$pid] ?? 0);

            $mapped[] = [
                'loan_product_id' => (int) $pid,
                'product' => (string) ($p->name ?? 'Unknown'),
                'npl_loans' => (int) ($nplByProduct[$pid]?->npl_loans ?? 0),
                'npl_amount' => round($nplAmt, 2),
                'portfolio_outstanding' => round($totalOut, 2),
                'npl_ratio_pct' => $totalOut > 0 ? round(($nplAmt / $totalOut) * 100, 2) : 0.0,
            ];
        }

        usort($mapped, fn ($a, $b) => ($b['npl_amount'] <=> $a['npl_amount']));

        return $mapped;
    }

    private function nplByBranch(array $subshopIds, $loanIds, Carbon $asOf, int $threshold): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds, $asOf);

        $subshops = \App\Models\SubShop::query()
            ->whereIn('id', $subshopIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        // NPL aggregation per branch using SQL (replaces N+1 loop)
        $nplByBranch = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->where('d.max_dpd', '>=', $threshold)
            ->selectRaw('loans.subshop_id as branch_id')
            ->selectRaw('COUNT(*) as npl_loans')
            ->selectRaw('SUM(d.outstanding_balance) as npl_amount')
            ->groupBy('loans.subshop_id')
            ->get()->keyBy('branch_id');

        // Portfolio outstanding per branch using SQL (replaces N+1 loop)
        $portfolioByBranch = DB::table('loans')
            ->joinSub($delinq, 'd', fn ($j) => $j->on('d.loan_id', '=', 'loans.id'))
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->selectRaw('loans.subshop_id as branch_id')
            ->selectRaw('SUM(d.outstanding_balance) as outstanding')
            ->groupBy('loans.subshop_id')
            ->pluck('outstanding', 'branch_id');

        $mapped = [];
        foreach ($subshopIds as $sid) {
            $nplData = $nplByBranch->get($sid);
            $totalOut = (float) ($portfolioByBranch[$sid] ?? 0);

            $mapped[] = [
                'subshop_id' => (int) $sid,
                'branch' => (string) ($subshops[$sid]->name ?? 'Unknown'),
                'npl_loans' => (int) ($nplData?->npl_loans ?? 0),
                'npl_amount' => round((float) ($nplData?->npl_amount ?? 0), 2),
                'portfolio_outstanding' => round($totalOut, 2),
                'npl_ratio_pct' => $totalOut > 0 ? round(((float) ($nplData?->npl_amount ?? 0) / $totalOut) * 100, 2) : 0.0,
            ];
        }

        usort($mapped, fn ($a, $b) => ($b['npl_amount'] <=> $a['npl_amount']));

        return $mapped;
    }

    private function nplByOfficer(array $subshopIds, $loanIds, Carbon $asOf, int $threshold): array
    {
        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds, $asOf);

        // Portfolio outstanding per officer using SQL (replaces N+1 loop)
        $portfolioByOfficer = DB::table('loans')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->joinSub($delinq, 'd', fn ($j) => $j->on('d.loan_id', '=', 'loans.id'))
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('ld.processed_by as officer_id')
            ->selectRaw('SUM(d.outstanding_balance) as outstanding')
            ->groupBy('ld.processed_by')
            ->pluck('outstanding', 'officer_id');

        // NPL aggregation per officer using SQL (replaces N+1 loop)
        $nplByOfficer = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->where('d.max_dpd', '>=', $threshold)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('ld.processed_by as officer_id')
            ->selectRaw('COUNT(*) as npl_loans')
            ->selectRaw('SUM(d.outstanding_balance) as npl_amount')
            ->groupBy('ld.processed_by')
            ->get()->keyBy('officer_id');

        $users = User::query()->whereIn('id', array_merge(
            $portfolioByOfficer->keys()->all(),
            $nplByOfficer->keys()->all()
        ))->get(['id', 'name'])->keyBy('id');

        $mapped = [];
        foreach ($portfolioByOfficer as $oid => $totalOut) {
            $nplData = $nplByOfficer->get($oid);

            $mapped[] = [
                'officer_id' => (int) $oid,
                'officer' => (string) ($users[(int) $oid]->name ?? 'Unknown'),
                'npl_loans' => (int) ($nplData?->npl_loans ?? 0),
                'npl_amount' => round((float) ($nplData?->npl_amount ?? 0), 2),
                'portfolio_outstanding' => round((float) $totalOut, 2),
                'npl_ratio_pct' => $totalOut > 0 ? round(((float) ($nplData?->npl_amount ?? 0) / (float) $totalOut) * 100, 2) : 0.0,
            ];
        }

        usort($mapped, fn ($a, $b) => ($b['npl_amount'] <=> $a['npl_amount']));

        return $mapped;
    }

    private function topNplCustomers(array $subshopIds, $loanIds, Carbon $asOf, int $threshold): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds, $asOf);

        $rows = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->join('customers as c', 'c.id', '=', 'loans.customer_id')
            ->where('d.max_dpd', '>=', $threshold)
            ->selectRaw('loans.customer_id as customer_id')
            ->selectRaw('c.name as customer')
            ->selectRaw('COUNT(*) as npl_loans')
            ->selectRaw('SUM(d.outstanding_balance) as npl_amount')
            ->groupBy('loans.customer_id', 'c.name')
            ->orderByDesc('npl_amount')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'customer_id' => (int) ($r->customer_id ?? 0),
            'customer' => (string) ($r->customer ?? ''),
            'npl_loans' => (int) ($r->npl_loans ?? 0),
            'npl_amount' => round((float) ($r->npl_amount ?? 0), 2),
        ])->all();
    }

    private function nplTrends(array $subshopIds, $loanIds, Carbon $asOf, int $threshold): array
    {
        $end = $asOf->copy()->endOfMonth();
        $start = $asOf->copy()->subMonths(11)->startOfMonth();
        $months = $this->monthsBetween($start, $end);

        $portfolio = $this->portfolioOutstanding($subshopIds, $loanIds);

        $labels = [];
        $nplAmounts = [];
        $portfolioAmounts = [];
        $ratios = [];

        foreach ($months as $m) {
            $monthEnd = Carbon::parse($m.'-01')->endOfMonth();
            if ($monthEnd->greaterThan($asOf)) {
                $monthEnd = $asOf->copy();
            }

            $nplAmount = (float) DB::query()
                ->fromSub($this->delinquentLoansBase($subshopIds, $loanIds, $monthEnd), 'd')
                ->where('d.max_dpd', '>=', $threshold)
                ->sum('d.outstanding_balance');

            $labels[] = $monthEnd->format('Y-m');
            $nplAmounts[] = round($nplAmount, 2);
            $portfolioAmounts[] = (float) $portfolio;
            $ratios[] = $portfolio > 0 ? round(($nplAmount / (float) $portfolio) * 100, 2) : 0.0;
        }

        return [
            'chart' => [
                'labels' => $labels,
                'npl_amount' => $nplAmounts,
                'npl_ratio' => $ratios,
                'portfolio_outstanding' => $portfolioAmounts,
            ],
        ];
    }

    /**
     * Recovery = payments allocations posted within the last 30 days up to as-of date, for loans currently NPL.
     */
    private function recoveryTracking(array $subshopIds, $loanIds, Carbon $asOf, int $threshold, float $totalNplAmount): array
    {
        $asOfDate = $asOf->toDateString();
        $from = $asOf->copy()->subDays(29)->startOfDay()->toDateString();

        $nplLoanIds = DB::query()
            ->fromSub($this->delinquentLoansBase($subshopIds, $loanIds, $asOf), 'd')
            ->where('d.max_dpd', '>=', $threshold)
            ->pluck('d.loan_id');

        if ($nplLoanIds->isEmpty()) {
            return [
                'recovered_amount' => 0.0,
                'recovery_rate_pct' => 0.0,
            ];
        }

        $sumExpr = 'SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0))';

        $recovered = (float) DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('lp.loan_id', $nplLoanIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$from, $asOfDate])
            ->selectRaw($sumExpr.' as recovered_amount')
            ->value('recovered_amount');

        $rate = $totalNplAmount > 0 ? round(($recovered / $totalNplAmount) * 100, 2) : 0.0;

        return [
            'recovered_amount' => round($recovered, 2),
            'recovery_rate_pct' => $rate,
        ];
    }

    private function writeoffCandidates(array $subshopIds, $loanIds, Carbon $asOf, int $writeoffThreshold): array
    {
        $asOfDate = $asOf->toDateString();
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds, $asOf);

        $lastPayment = DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('lp.status', 'confirmed')
            ->whereDate('lp.payment_date', '<=', $asOfDate)
            ->selectRaw('lp.loan_id as loan_id, MAX(lp.payment_date) as last_payment_date')
            ->groupBy('lp.loan_id');

        $rows = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->leftJoinSub($lastPayment, 'lp', fn ($j) => $j->on('lp.loan_id', '=', 'loans.id'))
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->where('d.max_dpd', '>', $writeoffThreshold)
            ->select([
                'loans.loan_code as loan_code',
                'c.name as customer',
                DB::raw('COALESCE(d.max_dpd,0) as dpd'),
                DB::raw('COALESCE(d.outstanding_balance,0) as outstanding'),
                'lp.last_payment_date as last_payment_date',
            ])
            ->orderByDesc('d.max_dpd')
            ->limit(25)
            ->get();

        return $rows->map(fn ($r) => [
            'loan_code' => (string) ($r->loan_code ?? ''),
            'customer' => (string) ($r->customer ?? ''),
            'dpd' => (int) ($r->dpd ?? 0),
            'outstanding' => round((float) ($r->outstanding ?? 0), 2),
            'last_payment_date' => $r->last_payment_date,
            'recommendation' => 'Consider Write-Off',
        ])->all();
    }

    /** @return array<int, string> */
    private function monthsBetween(Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();

        $months = [];
        while ($start->lte($end)) {
            $months[] = $start->format('Y-m');
            $start->addMonth();
        }

        return $months;
    }
}
