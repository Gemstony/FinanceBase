<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LoanDisbursements;
use App\Models\LoanInstallments;
use App\Models\LoanPayments;
use App\Models\LoanProducts;
use App\Models\Loans;
use App\Models\SubShop;
use App\Models\User;
use App\Services\Loans\Risk\LoanDelinquencyEngine;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class LoanPortfolioReportService
{
    public function __construct(
        private readonly PortfolioRiskCalculator $portfolioRiskCalculator,
        private readonly LoanDelinquencyEngine $delinquencyEngine,
    ) {}

    /**
     * Build the full loan portfolio report dataset.
     *
     * @param  array{date_from:Carbon,date_to:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null}  $filters
     * @param  array<int>  $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $loanIds = $this->filteredLoanIds($filters, $subshopIds);

        // Use date_to as the "as-of" date for portfolio snapshot (consistent with delinquency report)
        $asOf = $dateTo;

        // Portfolio outstanding from installments (respects all applied filters)
        $portfolioOutstanding = $this->delinquencyEngine->calculatePortfolioOutstandingFromInstallments($subshopIds, $loanIds);

        // Get active loan and borrower counts from base query
        $loanBase = $this->filteredLoansQuery($filters, $subshopIds);
        $activeStatuses = ['disbursed', 'partially_paid', 'defaulted'];
        $activeLoansCount = (clone $loanBase)->whereIn('status', $activeStatuses)->count('id');

        $activeBorrowersCount = (clone $loanBase)
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('customer_id')
            ->distinct()
            ->count('customer_id');

        $disbursed = $this->disbursementMetrics($filters, $subshopIds, $dateFrom, $dateTo);
        $repayments = $this->repaymentMetrics($filters, $subshopIds, $dateFrom, $dateTo);

        $avgLoanSize = $activeLoansCount > 0
            ? round(($portfolioOutstanding / $activeLoansCount), 2)
            : 0.0;

        $summary = [
            'total_outstanding' => $portfolioOutstanding,
            'active_loans' => $activeLoansCount,
            'active_borrowers' => $activeBorrowersCount,
            'total_disbursed_period' => (float) ($disbursed['total_amount'] ?? 0),
            'total_repayments_period' => (float) ($repayments['total_collected'] ?? 0),
            'avg_loan_size' => $avgLoanSize,
        ];

        // Use parBaseQuery for consistent installment-based aggregations
        $loanAgg = $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, $asOf);

        $composition = [
            'by_product' => $this->compositionByProduct($loanAgg, $subshopIds, $loanIds, $portfolioOutstanding),
            'by_branch' => $this->compositionByBranch($loanAgg, $subshopIds, $portfolioOutstanding, $asOf),
            'by_officer' => $this->compositionByOfficer($loanAgg, $filters, $subshopIds, $loanIds, $portfolioOutstanding),
        ];

        // PAR uses as-of date for consistent snapshot
        $par = $this->portfolioAtRisk($loanAgg, $portfolioOutstanding);

        // Aging uses as-of date
        $aging = $this->portfolioAging($subshopIds, $loanIds, $asOf);

        $disbursementAnalysis = $this->disbursementAnalysis($filters, $subshopIds, $dateFrom, $dateTo);

        $repaymentPerformance = $this->repaymentPerformance($filters, $subshopIds, $dateFrom, $dateTo);

        $topBorrowers = $this->topBorrowers($loanAgg, $subshopIds);

        $trends = $this->portfolioTrends($filters, $subshopIds, $loanIds, $dateFrom, $dateTo);

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_ids' => $subshopIds,
            ],
            'summary' => $summary,
            'composition' => $composition,
            'par' => $par,
            'aging' => $aging,
            'disbursement_analysis' => $disbursementAnalysis,
            'repayment_performance' => $repaymentPerformance,
            'top_borrowers' => $topBorrowers,
            'trends' => $trends,
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
     * Get filtered loan IDs for use with delinquency engine.
     *
     * @return \Illuminate\Support\Collection<int>
     */
    private function filteredLoanIds(array $filters, array $subshopIds): \Illuminate\Support\Collection
    {
        $q = Loans::query()->whereIn('subshop_id', $subshopIds);

        if (! empty($filters['loan_product_id'])) {
            $q->where('loan_product_id', (int) $filters['loan_product_id']);
        }

        if (! empty($filters['loan_status'])) {
            $q->where('status', (string) $filters['loan_status']);
        }

        // Officer filter via latest disbursement processor.
        if (! empty($filters['loan_officer_id'])) {
            $officerId = (int) $filters['loan_officer_id'];
            $latestDisbursement = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisbursement, 'ld_latest', function ($j) {
                $j->on('ld_latest.loan_id', '=', 'loans.id');
            })->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', $officerId)
                ->select('loans.id');
        }

        return $q->pluck('id');
    }

    /**
     * Loans base query for the report (subshop-scoped, read-only).
     *
     * IMPORTANT: Loan Officer filtering uses the disbursement processor (`loan_disbursements.processed_by`).
     */
    private function filteredLoansQuery(array $filters, array $subshopIds): Builder
    {
        $q = Loans::query()->whereIn('subshop_id', $subshopIds);

        if (! empty($filters['loan_product_id'])) {
            $q->where('loan_product_id', (int) $filters['loan_product_id']);
        }

        if (! empty($filters['loan_status'])) {
            $q->where('status', (string) $filters['loan_status']);
        }

        // Officer filter via latest disbursement processor.
        if (! empty($filters['loan_officer_id'])) {
            $officerId = (int) $filters['loan_officer_id'];
            $latestDisbursement = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisbursement, 'ld_latest', function ($j) {
                $j->on('ld_latest.loan_id', '=', 'loans.id');
            })->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', $officerId)
                ->select('loans.*');
        }

        return $q;
    }

    /**
     * Composition by product using installment-based aggregation (no N+1 loops).
     */
    private function compositionByProduct(QueryBuilder $loanAgg, array $subshopIds, $loanIds, float $portfolioOutstanding): array
    {
        $products = LoanProducts::query()->whereIn('subshop_id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        // Aggregate by product using SQL (no per-loan PHP loops)
        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->groupBy('loans.loan_product_id')
            ->get();

        return $rows->map(function ($r) use ($products, $portfolioOutstanding) {
            $pid = (int) ($r->product_id ?? 0);
            $outstanding = (float) ($r->outstanding ?? 0);

            return [
                'product_id' => $pid,
                'product_name' => (string) ($products[$pid]->name ?? 'Unknown'),
                'loans_count' => (int) ($r->loans_count ?? 0),
                'outstanding' => round($outstanding, 2),
                'pct' => $portfolioOutstanding > 0 ? round(($outstanding / $portfolioOutstanding) * 100, 2) : 0.0,
            ];
        })->sortByDesc('outstanding')->values()->all();
    }

    /**
     * Composition by branch using installment-based aggregation (no N+1 loops).
     */
    private function compositionByBranch(QueryBuilder $loanAgg, array $subshopIds, float $portfolioOutstanding, Carbon $asOf): array
    {
        $subshops = SubShop::query()->whereIn('id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        // Aggregate by branch using SQL (no per-loan PHP loops)
        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->selectRaw('loans.subshop_id as subshop_id')
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->selectRaw('SUM(CASE WHEN la.max_dpd >= 30 THEN la.outstanding_balance ELSE 0 END) as par30_outstanding')
            ->groupBy('loans.subshop_id')
            ->get();

        return $rows->map(function ($r) use ($subshops, $portfolioOutstanding) {
            $sid = (int) ($r->subshop_id ?? 0);
            $outstanding = (float) ($r->outstanding ?? 0);
            $par30 = (float) ($r->par30_outstanding ?? 0);

            return [
                'subshop_id' => $sid,
                'branch' => (string) ($subshops[$sid]->name ?? 'Unknown'),
                'active_loans' => (int) ($r->loans_count ?? 0),
                'outstanding' => round($outstanding, 2),
                'par30' => round($par30, 2),
            ];
        })->sortByDesc('outstanding')->values()->all();
    }

    /**
     * Officer performance using installment-based aggregation (no N+1 loops).
     */
    private function compositionByOfficer(QueryBuilder $loanAgg, array $filters, array $subshopIds, $loanIds, float $portfolioOutstanding): array
    {
        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $loanOfficer = DB::table('loans')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('loans.id as loan_id, ld.processed_by as officer_id');

        // Aggregate by officer using SQL (no per-loan PHP loops)
        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->joinSub($loanOfficer, 'lo', fn ($j) => $j->on('lo.loan_id', '=', 'la.loan_id'))
            ->selectRaw('lo.officer_id as officer_id')
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->groupBy('lo.officer_id')
            ->get();

        $users = User::query()->whereIn('id', $rows->pluck('officer_id')->values())->get(['id', 'name'])->keyBy('id');

        // Repayments collected in period by officer's portfolio
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $repaymentsByLoan = DB::table('loan_payments as lp')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('lp.status', 'confirmed')
            ->selectRaw('lp.loan_id, SUM(lp.amount) as collected')
            ->groupBy('lp.loan_id');

        $collectedByOfficer = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->joinSub($latestDisb, 'ld_latest3', fn ($j) => $j->on('ld_latest3.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld3', 'ld3.id', '=', 'ld_latest3.id')
            ->leftJoinSub($repaymentsByLoan, 'rp', fn ($j) => $j->on('rp.loan_id', '=', 'loans.id'))
            ->selectRaw('ld3.processed_by as officer_id, SUM(COALESCE(rp.collected,0)) as collected')
            ->groupBy('officer_id')
            ->pluck('collected', 'officer_id');

        return $rows->map(function ($r) use ($users, $collectedByOfficer) {
            $oid = (int) ($r->officer_id ?? 0);
            $outstanding = (float) ($r->outstanding ?? 0);

            return [
                'officer_id' => $oid,
                'officer' => (string) ($users[$oid]->name ?? 'Unknown'),
                'loans_managed' => (int) ($r->loans_count ?? 0),
                'outstanding' => round($outstanding, 2),
                'repayments_collected' => round((float) ($collectedByOfficer[$oid] ?? 0.0), 2),
            ];
        })->sortByDesc('outstanding')->values()->all();
    }

    private function disbursementMetrics(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $q = LoanDisbursements::query()
            ->join('loans', 'loans.id', '=', 'loan_disbursements.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (! empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (! empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (! empty($filters['loan_officer_id'])) {
            $q->where('loan_disbursements.processed_by', (int) $filters['loan_officer_id']);
        }

        return [
            'loans_disbursed' => (int) (clone $q)->distinct()->count('loan_disbursements.loan_id'),
            'total_amount' => (float) (clone $q)->sum('loan_disbursements.amount'),
        ];
    }

    private function repaymentMetrics(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $q = LoanPayments::query()
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('loan_payments.status', 'confirmed')
            ->whereBetween('loan_payments.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (! empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (! empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (! empty($filters['loan_officer_id'])) {
            // filter to loans belonging to officer portfolio
            $latestDisbursement = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisbursement, 'ld_latest', function ($j) {
                $j->on('ld_latest.loan_id', '=', 'loans.id');
            })
                ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', (int) $filters['loan_officer_id']);
        }

        return [
            'total_collected' => (float) (clone $q)->sum('loan_payments.amount'),
        ];
    }

    /**
     * Portfolio at Risk using parBaseQuery with inclusive >= thresholds.
     */
    private function portfolioAtRisk(QueryBuilder $loanAgg, float $portfolioOutstanding): array
    {
        // Use inclusive thresholds (>= 30/60/90) for PAR calculations
        $par1_30 = (float) DB::query()->fromSub($loanAgg, 'la')
            ->where('la.max_dpd', '>=', 1)
            ->where('la.max_dpd', '<', 30)
            ->sum('la.outstanding_balance');

        $par30 = (float) DB::query()->fromSub($loanAgg, 'la')
            ->where('la.max_dpd', '>=', 30)
            ->where('la.max_dpd', '<', 60)
            ->sum('la.outstanding_balance');

        $par60 = (float) DB::query()->fromSub($loanAgg, 'la')
            ->where('la.max_dpd', '>=', 60)
            ->where('la.max_dpd', '<', 90)
            ->sum('la.outstanding_balance');

        $par90 = (float) DB::query()->fromSub($loanAgg, 'la')
            ->where('la.max_dpd', '>=', 90)
            ->sum('la.outstanding_balance');

        // Current = total portfolio minus delinquent amounts
        $delinquentTotal = $par1_30 + $par30 + $par60 + $par90;
        $current = max(0.0, $portfolioOutstanding - $delinquentTotal);

        $rows = [
            ['bucket' => 'Current', 'outstanding' => round($current, 2)],
            ['bucket' => 'PAR 1–30', 'outstanding' => round($par1_30, 2)],
            ['bucket' => 'PAR 31–60', 'outstanding' => round($par30, 2)],
            ['bucket' => 'PAR 61–90', 'outstanding' => round($par60, 2)],
            ['bucket' => 'PAR > 90', 'outstanding' => round($par90, 2)],
        ];

        return array_map(function ($r) use ($portfolioOutstanding) {
            $out = (float) $r['outstanding'];
            $r['pct'] = $portfolioOutstanding > 0 ? round(($out / $portfolioOutstanding) * 100, 2) : 0.0;

            return $r;
        }, $rows);
    }

    /**
     * Portfolio aging using parBaseQuery with as-of date.
     */
    private function portfolioAging(array $subshopIds, $loanIds, Carbon $asOf): array
    {
        // Use parBaseQuery for consistent installment-based aging
        $loanAgg = $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, $asOf);

        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->selectRaw("CASE 
                WHEN la.max_dpd = 0 THEN 'Current'
                WHEN la.max_dpd BETWEEN 1 AND 30 THEN '1–30 days'
                WHEN la.max_dpd BETWEEN 31 AND 60 THEN '31–60 days'
                WHEN la.max_dpd BETWEEN 61 AND 90 THEN '61–90 days'
                ELSE '90+ days' END as bucket")
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->groupBy('bucket')
            ->get();

        $order = ['Current', '1–30 days', '31–60 days', '61–90 days', '90+ days'];

        $map = $rows->mapWithKeys(fn ($r) => [(string) $r->bucket => (float) $r->outstanding]);

        return collect($order)->map(function ($b) use ($map) {
            return [
                'bucket' => $b,
                'outstanding' => round((float) ($map[$b] ?? 0.0), 2),
            ];
        })->values()->all();
    }

    private function disbursementAnalysis(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $q = DB::table('loan_disbursements as ld')
            ->join('loans', 'loans.id', '=', 'ld.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('ld.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (! empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (! empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (! empty($filters['loan_officer_id'])) {
            $q->where('ld.processed_by', (int) $filters['loan_officer_id']);
        }

        $rows = $q
            ->selectRaw("DATE_FORMAT(ld.disbursement_date, '%Y-%m') as ym, COUNT(DISTINCT ld.loan_id) as loans_disbursed, SUM(ld.amount) as amount")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        return $rows->map(function ($r) {
            $loans = (int) $r->loans_disbursed;
            $amount = (float) $r->amount;

            return [
                'month' => (string) $r->ym,
                'loans_disbursed' => $loans,
                'amount' => round($amount, 2),
                'avg_amount' => $loans > 0 ? round($amount / $loans, 2) : 0.0,
            ];
        })->values()->all();
    }

    private function repaymentPerformance(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $expectedQ = LoanInstallments::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereBetween('due_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $expected = (float) $expectedQ->sum('total_due');

        $collected = (float) DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('lp.amount');

        $eff = $expected > 0 ? round($collected / $expected, 4) : 0.0;

        return [
            'expected' => round($expected, 2),
            'collected' => round($collected, 2),
            'efficiency' => $eff,
            'efficiency_pct' => round($eff * 100, 2),
        ];
    }

    /**
     * Top borrowers using installment-based aggregation (no N+1 loops).
     */
    private function topBorrowers(QueryBuilder $loanAgg, array $subshopIds): array
    {
        // Aggregate by customer using SQL (no per-loan PHP loops)
        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->whereNotNull('loans.customer_id')
            ->selectRaw('loans.customer_id as customer_id')
            ->selectRaw('COUNT(*) as loan_count')
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->groupBy('loans.customer_id')
            ->orderByDesc('outstanding')
            ->limit(10)
            ->get();

        $customers = DB::table('customers')
            ->whereIn('id', $rows->pluck('customer_id')->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        return $rows->map(function ($r) use ($customers) {
            $cid = (int) ($r->customer_id ?? 0);

            return [
                'customer_id' => $cid,
                'customer' => (string) ($customers[$cid]->name ?? 'Unknown'),
                'loan_count' => (int) ($r->loan_count ?? 0),
                'outstanding' => round((float) ($r->outstanding ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * Portfolio trends using parBaseQuery for consistent PAR30 calculation per month.
     */
    private function portfolioTrends(array $filters, array $subshopIds, $loanIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        // Month list
        $start = (clone $dateFrom)->startOfMonth();
        $end = (clone $dateTo)->startOfMonth();
        $months = [];
        for ($d = $start->copy(); $d->lte($end); $d->addMonth()) {
            $months[] = $d->format('Y-m');
        }

        $disb = DB::table('loan_disbursements as ld')
            ->join('loans', 'loans.id', '=', 'ld.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('ld.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("DATE_FORMAT(ld.disbursement_date, '%Y-%m') as ym, SUM(ld.amount) as amount")
            ->groupBy('ym')
            ->pluck('amount', 'ym');

        $rep = DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("DATE_FORMAT(lp.payment_date, '%Y-%m') as ym, SUM(lp.amount) as amount")
            ->groupBy('ym')
            ->pluck('amount', 'ym');

        // PAR30 trend using parBaseQuery per month (correct per-loan max_dpd >= 30)
        $par30Trend = [];
        foreach ($months as $ym) {
            $asOf = Carbon::createFromFormat('Y-m', $ym)->endOfMonth();
            $monthlyAgg = $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, $asOf);
            $par30 = (float) DB::query()->fromSub($monthlyAgg, 'la')
                ->where('la.max_dpd', '>=', 30)
                ->sum('la.outstanding_balance');
            $par30Trend[$ym] = $par30;
        }

        // Outstanding trend using delinquencyEngine per month
        $outTrend = [];
        foreach ($months as $ym) {
            $asOf = Carbon::createFromFormat('Y-m', $ym)->endOfMonth();
            $out = $this->delinquencyEngine->calculatePortfolioOutstandingFromInstallments($subshopIds, $loanIds, $asOf);
            $outTrend[$ym] = $out;
        }

        return [
            'labels' => $months,
            'portfolio_outstanding' => array_map(fn ($m) => round((float) ($outTrend[$m] ?? 0.0), 2), $months),
            'disbursements' => array_map(fn ($m) => round((float) ($disb[$m] ?? 0.0), 2), $months),
            'repayments' => array_map(fn ($m) => round((float) ($rep[$m] ?? 0.0), 2), $months),
            'par30' => array_map(fn ($m) => round((float) ($par30Trend[$m] ?? 0.0), 2), $months),
        ];
    }
}
