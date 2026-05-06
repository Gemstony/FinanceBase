<?php

namespace App\Services\Loans\Risk;

use App\Models\LoanInstallments;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class LoanDelinquencyEngine
{
    /**
     * Cache TTL for PAR calculations (5 minutes).
     */
    protected const PAR_CACHE_TTL = 300;

    /**
     * Cache TTL for risk classifications (5 minutes).
     */
    protected const RISK_CLASS_CACHE_TTL = 300;

    public function __construct(
        protected PortfolioRiskCalculator $portfolioRiskCalculator,
        protected DpdCalculator $dpdCalculator
    ) {
    }

    /**
     * Base query for loans considered part of the active portfolio for delinquency/PAR.
     *
     * Note: this mirrors PortfolioRiskCalculator::activeLoansQuery() but uses query builder
     * so we can build installment-driven aggregates efficiently.
     */
    protected function activePortfolioLoansQuery(array $subshopIds, $loanIds = null): QueryBuilder
    {
        $q = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false);

        if ($loanIds && method_exists($loanIds, 'isNotEmpty')) {
            $q->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'));
        }

        return $q;
    }

    /**
     * Build a delinquency base query (per-loan) from loan_installments.
     *
     * Returns: loan_id, max_dpd, overdue_amount, outstanding_balance
     *
     * - max_dpd is computed as-of $asOfDate
     * - overdue_amount is sum of overdue installments (status=overdue) with outstanding_amount > 0
     * - outstanding_balance is total outstanding across active installments (denominator basis)
     */
    public function delinquencyBaseQuery(array $subshopIds, $loanIds = null, ?Carbon $asOfDate = null): QueryBuilder
    {
        $asOf = ($asOfDate ?? Carbon::today())->toDateString();

        $loanFilter = $this->activePortfolioLoansQuery($subshopIds, $loanIds)
            ->select('loans.id');

        $overdue = DB::table('loan_installments as li')
            ->joinSub($loanFilter, 'pl', fn ($j) => $j->on('pl.id', '=', 'li.loan_id'))
            ->where('li.is_active', true)
            ->where('li.status', 'overdue')
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<', $asOf)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('MAX(DATEDIFF(?, li.due_date)) as max_dpd', [$asOf])
            ->selectRaw('SUM(li.outstanding_amount) as overdue_amount')
            ->groupBy('li.loan_id');

        $allOutstanding = DB::table('loan_installments as li')
            ->joinSub($loanFilter, 'pl', fn ($j) => $j->on('pl.id', '=', 'li.loan_id'))
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.outstanding_amount) as outstanding_balance')
            ->groupBy('li.loan_id');

        return DB::query()
            ->fromSub($overdue, 'o')
            ->joinSub($allOutstanding, 'a', fn ($j) => $j->on('a.loan_id', '=', 'o.loan_id'))
            ->select(['o.loan_id', 'o.max_dpd', 'o.overdue_amount', 'a.outstanding_balance']);
    }

    /**
     * Base query for PAR-style reporting including current loans.
     *
     * Returns: loan_id, max_dpd, overdue_amount, outstanding_balance
     * - max_dpd/overdue_amount are 0 when a loan has no overdue installments as-of date.
     */
    public function parBaseQuery(array $subshopIds, $loanIds = null, ?Carbon $asOfDate = null): QueryBuilder
    {
        $asOf = ($asOfDate ?? Carbon::today())->toDateString();

        $loanFilter = $this->activePortfolioLoansQuery($subshopIds, $loanIds)
            ->select('loans.id');

        $allOutstanding = DB::table('loan_installments as li')
            ->joinSub($loanFilter, 'pl', fn ($j) => $j->on('pl.id', '=', 'li.loan_id'))
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.outstanding_amount) as outstanding_balance')
            ->groupBy('li.loan_id');

        $overdue = DB::table('loan_installments as li')
            ->joinSub($loanFilter, 'pl', fn ($j) => $j->on('pl.id', '=', 'li.loan_id'))
            ->where('li.is_active', true)
            ->where('li.status', 'overdue')
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<', $asOf)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('MAX(DATEDIFF(?, li.due_date)) as max_dpd', [$asOf])
            ->selectRaw('SUM(li.outstanding_amount) as overdue_amount')
            ->groupBy('li.loan_id');

        return DB::query()
            ->fromSub($allOutstanding, 'a')
            ->leftJoinSub($overdue, 'o', fn ($j) => $j->on('o.loan_id', '=', 'a.loan_id'))
            ->selectRaw('a.loan_id as loan_id')
            ->selectRaw('COALESCE(o.max_dpd, 0) as max_dpd')
            ->selectRaw('COALESCE(o.overdue_amount, 0) as overdue_amount')
            ->selectRaw('a.outstanding_balance as outstanding_balance');
    }

    /**
     * Installment-level base query for installment aging reports.
     *
     * Returns individual installment records with:
     * - loan_id, loan_code, customer info, product, branch, officer
     * - installment_id, installment_number, due_date
     * - total_due, paid_amount, outstanding_balance
     * - dpd (Days Past Due per installment)
     * - aging_bucket (Current, 1-30, 31-60, 61-90, 90+)
     *
     * Uses the same DPD calculation logic as parBaseQuery for consistency.
     */
    public function installmentLevelBaseQuery(
        array $subshopIds,
        ?Carbon $asOfDate = null,
        ?int $loanProductId = null,
        ?int $loanOfficerId = null,
        ?string $loanStatus = null,
        ?string $customerSearch = null,
        ?int $dpdMin = null,
        ?int $dpdMax = null
    ): QueryBuilder {
        $asOf = ($asOfDate ?? Carbon::today())->toDateString();

        // Active loan filter (same logic as parBaseQuery)
        $loanFilter = $this->activePortfolioLoansQuery($subshopIds)
            ->when($loanProductId, fn ($q) => $q->where('loans.loan_product_id', $loanProductId))
            ->when($loanStatus, fn ($q) => $q->where('loans.status', $loanStatus))
            ->select('loans.id');

        // Latest disbursement for officer lookup
        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $q = DB::table('loan_installments as li')
            ->joinSub($loanFilter, 'pl', fn ($j) => $j->on('pl.id', '=', 'li.loan_id'))
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->leftJoin('customers', 'customers.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as lp', 'lp.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->leftJoinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->leftJoin('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->leftJoin('users as u', 'u.id', '=', 'ld.processed_by')
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            // Apply officer filter at join level
            ->when($loanOfficerId, fn ($q) => $q->where('ld.processed_by', $loanOfficerId))
            // Apply customer search
            ->when($customerSearch, function ($q) use ($customerSearch) {
                $search = trim($customerSearch);
                if ($search !== '') {
                    $q->where('customers.name', 'like', '%'.$search.'%');
                }
            })
            // Select all installment-level fields
            ->selectRaw('li.id as installment_id')
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('loans.loan_code as loan_code')
            ->selectRaw('customers.id as customer_id')
            ->selectRaw('customers.name as customer')
            ->selectRaw('COALESCE(lp.name, "") as product')
            ->selectRaw('COALESCE(ss.name, "") as branch')
            ->selectRaw('COALESCE(u.name, "") as officer')
            ->selectRaw('li.installment_number as installment_number')
            ->selectRaw('li.due_date as due_date')
            ->selectRaw('li.total_due as total_due')
            ->selectRaw('li.amount_paid as paid_amount')
            ->selectRaw('li.outstanding_amount as outstanding_balance')
            ->selectRaw('li.status as installment_status')
            // DPD calculation: same logic as parBaseQuery (DATEDIFF against due_date)
            ->selectRaw('CASE WHEN li.due_date < ? THEN DATEDIFF(?, li.due_date) ELSE 0 END as dpd', [$asOf, $asOf])
            // Aging bucket classification
            ->selectRaw(
                "CASE \n".
                " WHEN li.due_date >= ? THEN 'Current'\n".
                " WHEN DATEDIFF(?, li.due_date) <= 30 THEN '1-30'\n".
                " WHEN DATEDIFF(?, li.due_date) <= 60 THEN '31-60'\n".
                " WHEN DATEDIFF(?, li.due_date) <= 90 THEN '61-90'\n".
                " ELSE '90+' END as aging_bucket",
                [$asOf, $asOf, $asOf, $asOf]
            );

        // Apply DPD filters using HAVING (since dpd is computed)
        if ($dpdMin !== null) {
            $q->having('dpd', '>=', $dpdMin);
        }
        if ($dpdMax !== null) {
            $q->having('dpd', '<=', $dpdMax);
        }

        return $q;
    }

    /**
     * Calculate total outstanding (denominator) from loan_installments directly.
     */
    public function calculatePortfolioOutstandingFromInstallments(array $subshopIds, $loanIds = null): float
    {
        $loanFilter = $this->activePortfolioLoansQuery($subshopIds, $loanIds)->select('loans.id');

        $perLoan = DB::table('loan_installments as li')
            ->joinSub($loanFilter, 'pl', fn ($j) => $j->on('pl.id', '=', 'li.loan_id'))
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.outstanding_amount) as outstanding_balance')
            ->groupBy('li.loan_id');

        $total = (float) DB::query()->fromSub($perLoan, 'x')->sum('x.outstanding_balance');

        return round($total, 2);
    }

    /**
     * Calculate outstanding balance of delinquent loans (numerator) from loan_installments.
     *
     * Delinquent is defined as max_dpd >= $days (best practice boundary inclusive).
     */
    public function calculateDelinquentOutstandingFromInstallments(int $days, array $subshopIds, $loanIds = null, ?Carbon $asOfDate = null): float
    {
        $days = max(0, (int) $days);

        $base = $this->delinquencyBaseQuery($subshopIds, $loanIds, $asOfDate);
        $sum = (float) DB::query()->fromSub($base, 'd')
            ->where('d.max_dpd', '>=', $days)
            ->sum('d.outstanding_balance');

        return round($sum, 2);
    }

    /**
     * Calculate Portfolio at Risk (PAR) for a given threshold in days.
     *
     * Banking / microfinance formula:
     * PAR(days) = Outstanding balance of delinquent loans (days) / Total outstanding loan portfolio
     *
     * Returned value is a percentage (0 - 100).
     */
    public function calculatePAR(int $days, array|int|null $subshopIds = null): float
    {
        $days = max(0, (int) $days);

        $asOf = Carbon::today();

        // Handle array of subshop IDs
        if (is_array($subshopIds) && !empty($subshopIds)) {
            $totalPortfolio = $this->calculatePortfolioOutstandingFromInstallments($subshopIds);
            $delinquentOutstanding = $this->calculateDelinquentOutstandingFromInstallments($days, $subshopIds, null, $asOf);
        } else {
            $subshopId = is_array($subshopIds) ? null : $subshopIds;

            $subshopId = $subshopId ? (int) $subshopId : null;
            $ids = $subshopId ? [$subshopId] : [];
            $totalPortfolio = $subshopId
                ? $this->calculatePortfolioOutstandingFromInstallments($ids)
                : $this->calculatePortfolioOutstandingFromInstallments($this->allSubshopIdsForShopContext());

            if ($totalPortfolio <= 0) {
                return 0.0;
            }

            $delinquentOutstanding = $subshopId
                ? $this->calculateDelinquentOutstandingFromInstallments($days, $ids, null, $asOf)
                : $this->calculateDelinquentOutstandingFromInstallments($days, $this->allSubshopIdsForShopContext(), null, $asOf);
        }

        if ($totalPortfolio <= 0) {
            return 0.0;
        }

        $par = ($delinquentOutstanding / $totalPortfolio) * 100;

        return round(max(0.0, $par), 2);
    }

    /**
     * Calculate PAR with caching.
     */
    public function calculatePARCached(int $days, array|int|null $subshopIds = null): float
    {
        $asOfKey = Carbon::today()->toDateString();
        // Generate cache key based on subshop IDs
        if (is_array($subshopIds)) {
            sort($subshopIds); // Ensure consistent ordering
            $cacheKey = "par:days:{$days}:asof:{$asOfKey}:subshops:" . implode(',', $subshopIds);
        } else {
            $cacheKey = $subshopIds
                ? "par:days:{$days}:asof:{$asOfKey}:subshop:{$subshopIds}"
                : "par:days:{$days}:asof:{$asOfKey}";
        }

        return Cache::remember($cacheKey, self::PAR_CACHE_TTL, function () use ($days, $subshopIds) {
            return $this->calculatePAR($days, $subshopIds);
        });
    }

    public function calculatePAR30(array|int|null $subshopIds = null): float
    {
        return $this->calculatePAR(30, $subshopIds);
    }

    public function calculatePAR60(array|int|null $subshopIds = null): float
    {
        return $this->calculatePAR(60, $subshopIds);
    }

    public function calculatePAR90(array|int|null $subshopIds = null): float
    {
        return $this->calculatePAR(90, $subshopIds);
    }

    /**
     * Get delinquent loans for a PAR bucket.
     *
     * A loan is delinquent for a given bucket when at least one installment is:
     * - status = overdue
     * - and days overdue > $days
     */
    public function getDelinquentLoans(int $days, array|int|null $subshopIds = null): Collection
    {
        $days = max(0, (int) $days);
        $cutoffDate = Carbon::today()->subDays($days);

        $loanIds = LoanInstallments::query()
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoffDate)
            ->distinct()
            ->pluck('loan_id');

        if ($loanIds->isEmpty()) {
            return new Collection();
        }

        $query = $this->portfolioRiskCalculator
            ->activeLoansQuery()
            ->whereIn('id', $loanIds);

        if (is_array($subshopIds) && !empty($subshopIds)) {
            $query->whereIn('subshop_id', $subshopIds);
        } elseif ($subshopIds) {
            $query->where('subshop_id', $subshopIds);
        }

        $loans = $query->get();

        // Use bulk calculation for better performance
        $outstandingMap = $this->portfolioRiskCalculator->bulkCalculateOutstanding($loanIds->toArray());

        return $loans
            ->filter(function (Loans $loan) use ($outstandingMap) {
                return ($outstandingMap[$loan->id] ?? 0) > 0;
            })
            ->values();
    }

    /**
     * Get delinquent loans with enriched data (outstanding, risk category, max DPD).
     *
     * @return Collection<Loans>
     */
    public function getDelinquentLoansEnriched(int $days, array|int|null $subshopIds = null): Collection
    {
        $loans = $this->getDelinquentLoans($days, $subshopIds);

        if ($loans->isEmpty()) {
            return $loans;
        }

        $loanIds = $loans->pluck('id')->toArray();

        // Bulk calculate all required data
        $outstandingMap = $this->portfolioRiskCalculator->bulkCalculateOutstanding($loanIds);
        $maxDpdMap = $this->dpdCalculator->bulkCalculateMaxDpd($loanIds);

        foreach ($loans as $loan) {
            $loan->outstanding_balance = $outstandingMap[$loan->id] ?? 0;
            $loan->max_days_overdue = $maxDpdMap[$loan->id] ?? 0;
            $loan->risk_category = $this->dpdCalculator->classifyByDpd($loan->max_days_overdue);
        }

        return $loans;
    }

    /**
     * Helper: calculate days overdue for an installment.
     *
     * If installment is not overdue yet, returns 0.
     *
     * @deprecated Use DpdCalculator::calculateDaysOverdue() instead for consistency.
     */
    public function calculateDaysOverdue(LoanInstallments $installment): int
    {
        return $this->dpdCalculator->calculateDaysOverdue($installment);
    }

    /**
     * Calculate max days overdue for a loan.
     */
    public function calculateMaxDaysOverdueForLoan(int $loanId): int
    {
        return $this->dpdCalculator->calculateMaxDaysOverdueForLoan($loanId);
    }

    /**
     * OPTIONAL banking feature: classify a single loan into a risk bucket.
     *
     * Uses the maximum days overdue among overdue installments.
     */
    public function classifyLoanRisk(Loans $loan): string
    {
        $isPortfolioLoan = (bool) ($loan->is_active ?? false)
            && in_array((string) $loan->status, ['disbursed', 'partially_paid', 'defaulted'], true)
            && !(bool) ($loan->is_written_off ?? false);

        if (!$isPortfolioLoan) {
            return 'current';
        }

        if ($this->portfolioRiskCalculator->calculateLoanOutstandingCached($loan) <= 0) {
            return 'current';
        }

        $maxDaysOverdue = $this->dpdCalculator->calculateMaxDaysOverdueForLoan($loan->id);

        return $this->dpdCalculator->classifyByDpd($maxDaysOverdue);
    }

    /**
     * Classify loan risk with caching.
     */
    public function classifyLoanRiskCached(Loans $loan): string
    {
        $cacheKey = "loan_risk:class:{$loan->id}";

        return Cache::remember($cacheKey, self::RISK_CLASS_CACHE_TTL, function () use ($loan) {
            return $this->classifyLoanRisk($loan);
        });
    }

    /**
     * Get a summary of the portfolio risk.
     *
     * @return array{portfolio_outstanding: float, par30: float, par60: float, par90: float, par180: float}
     */
    public function getPortfolioRiskSummary(array|int|null $subshopIds = null): array
    {
        $portfolioOutstanding = 0.0;
        if (is_array($subshopIds) && !empty($subshopIds)) {
            $portfolioOutstanding = $this->calculatePortfolioOutstandingFromInstallments($subshopIds);
        } elseif ($subshopIds) {
            $portfolioOutstanding = $this->calculatePortfolioOutstandingFromInstallments([(int) $subshopIds]);
        } else {
            $portfolioOutstanding = $this->calculatePortfolioOutstandingFromInstallments($this->allSubshopIdsForShopContext());
        }

        return [
            'portfolio_outstanding' => $portfolioOutstanding,
            'par30' => $this->calculatePARCached(30, $subshopIds),
            'par60' => $this->calculatePARCached(60, $subshopIds),
            'par90' => $this->calculatePARCached(90, $subshopIds),
            'par180' => $this->calculatePARCached(180, $subshopIds),
        ];
    }

    /**
     * Get portfolio risk summary with full caching.
     */
    public function getPortfolioRiskSummaryCached(array|int|null $subshopIds = null): array
    {
        $asOfKey = Carbon::today()->toDateString();
        // Generate cache key based on subshop IDs
        if (is_array($subshopIds)) {
            sort($subshopIds); // Ensure consistent ordering
            $cacheKey = "portfolio_risk_summary:asof:{$asOfKey}:subshops:" . implode(',', $subshopIds);
        } else {
            $cacheKey = $subshopIds
                ? "portfolio_risk_summary:asof:{$asOfKey}:subshop:{$subshopIds}"
                : "portfolio_risk_summary:asof:{$asOfKey}:total";
        }

        return Cache::remember($cacheKey, self::PAR_CACHE_TTL, function () use ($subshopIds) {
            return $this->getPortfolioRiskSummary($subshopIds);
        });
    }

    /**
     * Bulk classify loans into risk buckets.
     *
     * @param array<int> $loanIds
     * @return array<int, string> Array of [loan_id => risk_category]
     */
    public function bulkClassifyLoanRisk(array $loanIds): array
    {
        if (empty($loanIds)) {
            return [];
        }

        $results = [];
        $maxDpdMap = $this->dpdCalculator->bulkCalculateMaxDpd($loanIds);
        $outstandingMap = $this->portfolioRiskCalculator->bulkCalculateOutstanding($loanIds);

        foreach ($loanIds as $loanId) {
            $outstanding = $outstandingMap[$loanId] ?? 0;

            if ($outstanding <= 0) {
                $results[$loanId] = 'current';
                continue;
            }

            $maxDpd = $maxDpdMap[$loanId] ?? 0;
            $results[$loanId] = $this->dpdCalculator->classifyByDpd($maxDpd);
        }

        return $results;
    }

    /**
     * Resolve "all subshops" for contexts that previously used global portfolio calculations.
     *
     * In this application, most consumers use explicit subshop IDs, but some call sites
     * pass null to mean "total". We keep this behavior by using all subshop IDs.
     *
     * If no subshops exist, returns [-1] to ensure queries return 0 totals.
     *
     * @return array<int>
     */
    protected function allSubshopIdsForShopContext(): array
    {
        $ids = DB::table('sub_shops')->pluck('id')->map(fn ($v) => (int) $v)->all();

        return !empty($ids) ? $ids : [-1];
    }
}
