<?php

namespace App\Services\Loans\Risk;

use App\Models\LoanInstallments;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
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

        // Handle array of subshop IDs
        if (is_array($subshopIds) && !empty($subshopIds)) {
            $totalPortfolio = $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops($subshopIds);
            $delinquentOutstanding = $this->portfolioRiskCalculator->calculateDelinquentOutstandingForSubshops($days, $subshopIds);
        } else {
            $subshopId = is_array($subshopIds) ? null : $subshopIds;
            $totalPortfolio = $subshopId
                ? $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingCached($subshopId)
                : $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingCached();

            if ($totalPortfolio <= 0) {
                return 0.0;
            }

            $delinquentOutstanding = $this->portfolioRiskCalculator->calculateDelinquentOutstandingCached($days, $subshopId);
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
        // Generate cache key based on subshop IDs
        if (is_array($subshopIds)) {
            sort($subshopIds); // Ensure consistent ordering
            $cacheKey = "par:days:{$days}:subshops:" . implode(',', $subshopIds);
        } else {
            $cacheKey = $subshopIds
                ? "par:days:{$days}:subshop:{$subshopIds}"
                : "par:days:{$days}";
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
        // Handle array of subshop IDs
        if (is_array($subshopIds) && !empty($subshopIds)) {
            $portfolioOutstanding = $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops($subshopIds);
        } else {
            $subshopId = is_array($subshopIds) ? null : $subshopIds;
            $portfolioOutstanding = $subshopId
                ? $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingCached($subshopId)
                : $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingCached();
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
        // Generate cache key based on subshop IDs
        if (is_array($subshopIds)) {
            sort($subshopIds); // Ensure consistent ordering
            $cacheKey = "portfolio_risk_summary:subshops:" . implode(',', $subshopIds);
        } else {
            $cacheKey = $subshopIds
                ? "portfolio_risk_summary:subshop:{$subshopIds}"
                : 'portfolio_risk_summary:total';
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
}
