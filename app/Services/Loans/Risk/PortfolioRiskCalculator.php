<?php

namespace App\Services\Loans\Risk;

use App\Models\LoanInstallments;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class PortfolioRiskCalculator
{
    /**
     * Cache TTL in seconds for individual loan calculations (5 minutes).
     * Shorter TTL because loan payments can change outstanding balance frequently.
     */
    protected const LOAN_CACHE_TTL = 300;

    /**
     * Cache TTL in seconds for portfolio-level calculations (10 minutes).
     */
    protected const PORTFOLIO_CACHE_TTL = 600;

    /**
     * Cache TTL in seconds for delinquent calculations (5 minutes).
     */
    protected const DELINQUENT_CACHE_TTL = 300;

    /**
     * Cache key prefix for loan outstanding calculations.
     */
    protected const LOAN_CACHE_PREFIX = 'loan_outstanding:';

    /**
     * Generate cache key for a loan's outstanding balance.
     */
    protected function getLoanCacheKey(int $loanId): string
    {
        return self::LOAN_CACHE_PREFIX . $loanId;
    }

    /**
     * Clear the cache for a specific loan.
     */
    public function clearLoanCache(int $loanId): void
    {
        Cache::forget($this->getLoanCacheKey($loanId));
    }

    /**
     * Clear all loan outstanding caches.
     */
    public function clearAllLoanCaches(): void
    {
        // Note: This requires cache driver that supports pattern deletion
        // For Redis, you would use: Cache::store('redis')->getRedis()->del(...)
        // For file/database drivers, use with caution on large portfolios
    }

    /**
     * Calculate outstanding balance with caching.
     *
     * This is the primary method that should be used by external callers.
     * It caches results to avoid repeated expensive calculations.
     */
    public function calculateLoanOutstandingCached(Loans $loan): float
    {
        $loanId = (int) $loan->id;
        $cacheKey = $this->getLoanCacheKey($loanId);

        return Cache::remember($cacheKey, self::LOAN_CACHE_TTL, function () use ($loan) {
            return $this->calculateLoanOutstanding($loan);
        });
    }

    /**
     * Bulk calculate outstanding balances for multiple loans with caching.
     *
     * @param array<int> $loanIds
     * @return array<int, float> Array of [loan_id => outstanding_balance]
     */
    public function bulkCalculateOutstanding(array $loanIds): array
    {
        if (empty($loanIds)) {
            return [];
        }

        $results = [];
        $uncachedIds = [];

        // Check cache first
        foreach ($loanIds as $loanId) {
            $cacheKey = $this->getLoanCacheKey($loanId);
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$loanId] = (float) $cached;
            } else {
                $uncachedIds[] = $loanId;
            }
        }

        // Calculate uncached loans in bulk
        if (!empty($uncachedIds)) {
            $uncachedLoans = Loans::whereIn('id', $uncachedIds)->get();

            foreach ($uncachedLoans as $loan) {
                $outstanding = $this->calculateLoanOutstanding($loan);
                $results[$loan->id] = $outstanding;

                // Store in cache
                Cache::put(
                    $this->getLoanCacheKey($loan->id),
                    $outstanding,
                    self::LOAN_CACHE_TTL
                );
            }
        }

        return $results;
    }

    /**
     * Calculate total portfolio outstanding with caching.
     */
    public function calculateTotalPortfolioOutstandingCached(?int $subshopId = null): float
    {
        $cacheKey = $subshopId
            ? "portfolio_outstanding:subshop:{$subshopId}"
            : 'portfolio_outstanding:total';

        return Cache::remember($cacheKey, self::PORTFOLIO_CACHE_TTL, function () use ($subshopId) {
            if ($subshopId) {
                return $this->calculateTotalPortfolioOutstandingForSubshops([$subshopId]);
            }
            return $this->calculateTotalPortfolioOutstanding();
        });
    }
    /**
     * Calculate the outstanding balance for a single loan.
     *
     * Financial logic (microfinance / banking standard):
     * Outstanding balance is the sum of unpaid components across all installments.
     * Excludes penalties - they are tracked separately as "accrued penalties"
     * and should not be included in core outstanding/receivable calculations.
     *
     * Handles restructured loans correctly:
     * - Uses latest active schedule version only
     * - Old restructured installments are marked is_active=false
     * - Payment allocations from old installments still count (they're linked via loan_id)
     *
     * For each installment:
     * (principal_due - principal_paid)
     * + (interest_due - interest_paid)
     * + (fees_due - fees_paid)
     *
     * We never allow negative outstanding values (overpayments or data issues).
     */
    public function calculateLoanOutstanding(Loans $loan): float
    {
        $loanId = (int) $loan->id;

        $latestVersion = (int) LoanInstallments::query()
            ->where('loan_id', $loanId)
            ->where('is_active', true)
            ->max('schedule_version');

        if ($latestVersion <= 0) {
            return 0.0;
        }

        $expected = \Illuminate\Support\Facades\DB::table('loan_installments as li')
            ->where('li.loan_id', $loanId)
            ->where('li.is_active', true)
            ->where('li.schedule_version', $latestVersion)
            ->selectRaw('
                SUM(COALESCE(li.principal_due,0)) as principal_expected,
                SUM(COALESCE(li.interest_due,0)) as interest_expected,
                SUM(COALESCE(li.fees_due,0)) as fees_expected
            ')
            ->groupBy('li.loan_id')
            ->first();

        // Get all payments allocated to this loan (from any schedule version)
        // This handles restructured loans correctly - pre-restructure payments count
        $paid = \Illuminate\Support\Facades\DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->where('li.loan_id', $loanId)
            ->where('lp.status', 'confirmed')
            ->selectRaw('
                SUM(COALESCE(lpa.principal_amount,0)) as principal_paid,
                SUM(COALESCE(lpa.interest_amount,0)) as interest_paid,
                SUM(COALESCE(lpa.fee_amount,0)) as fees_paid
            ')
            ->groupBy('li.loan_id')
            ->first();

        $principalExpected = (float) ($expected->principal_expected ?? 0);
        $interestExpected = (float) ($expected->interest_expected ?? 0);
        $feesExpected = (float) ($expected->fees_expected ?? 0);

        $principalPaid = (float) ($paid->principal_paid ?? 0);
        $interestPaid = (float) ($paid->interest_paid ?? 0);
        $feesPaid = (float) ($paid->fees_paid ?? 0);

        $principalOutstanding = max(0.0, $principalExpected - $principalPaid);
        $interestOutstanding = max(0.0, $interestExpected - $interestPaid);
        $feesOutstanding = max(0.0, $feesExpected - $feesPaid);

        return round($principalOutstanding + $interestOutstanding + $feesOutstanding, 2);
    }

    /**
     * Calculate total outstanding for the entire loan portfolio.
     *
     * Portfolio outstanding is computed as the sum of outstanding for all active loans.
     * This is a key denominator in PAR calculations.
     */
    public function calculateTotalPortfolioOutstanding(): float
    {
        $total = 0.0;

        $this->activeLoansQuery()
            ->select(['id'])
            ->chunkById(200, function ($loans) use (&$total) {
                foreach ($loans as $loan) {
                    $outstanding = $this->calculateLoanOutstanding($loan);
                    if ($outstanding > 0) {
                        $total += $outstanding;
                    }
                }
            });

        return round($total, 2);
    }

    /**
     * Calculate the outstanding balance of delinquent loans for the given threshold.
     *
     * A loan is considered delinquent for a given PAR bucket when it has at least one
     * installment that is overdue by more than $days.
     *
     * This method returns the sum of outstanding balances of those delinquent loans.
     */
    public function calculateDelinquentOutstanding(int $days, ?int $subshopId = null): float
    {
        $days = max(0, (int) $days);
        $cutoffDate = Carbon::today()->subDays($days);

        $delinquentLoanIds = LoanInstallments::query()
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoffDate)
            ->distinct()
            ->pluck('loan_id');

        if ($delinquentLoanIds->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;

        $query = $this->activeLoansQuery()
            ->whereIn('id', $delinquentLoanIds)
            ->select(['id']);

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        $query->chunkById(200, function ($loans) use (&$total) {
            foreach ($loans as $loan) {
                $outstanding = $this->calculateLoanOutstanding($loan);
                if ($outstanding > 0) {
                    $total += $outstanding;
                }
            }
        });

        return round($total, 2);
    }

    /**
     * Calculate delinquent outstanding with caching.
     */
    public function calculateDelinquentOutstandingCached(int $days, ?int $subshopId = null): float
    {
        $cacheKey = $subshopId
            ? "delinquent_outstanding:days:{$days}:subshop:{$subshopId}"
            : "delinquent_outstanding:days:{$days}";

        return Cache::remember($cacheKey, self::DELINQUENT_CACHE_TTL, function () use ($days, $subshopId) {
            return $this->calculateDelinquentOutstanding($days, $subshopId);
        });
    }

    /**
     * Calculate total outstanding for loans within specific subshops.
     *
     * Uses the same active loan definition and per-loan outstanding calculation.
     */
    public function calculateTotalPortfolioOutstandingForSubshops($subshopIds): float
    {
        $total = 0.0;

        $this->activeLoansQuery()
            ->whereIn('subshop_id', $subshopIds)
            ->select(['id'])
            ->chunkById(200, function ($loans) use (&$total) {
                foreach ($loans as $loan) {
                    $outstanding = $this->calculateLoanOutstanding($loan);
                    if ($outstanding > 0) {
                        $total += $outstanding;
                    }
                }
            });

        return round($total, 2);
    }

    /**
     * Calculate delinquent outstanding for loans within specific subshops.
     *
     * Uses the same active loan definition and per-loan outstanding calculation.
     */
    public function calculateDelinquentOutstandingForSubshops(int $days, array $subshopIds): float
    {
        $days = max(0, (int) $days);
        $cutoffDate = Carbon::today()->subDays($days);

        $delinquentLoanIds = LoanInstallments::query()
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoffDate)
            ->distinct()
            ->pluck('loan_id');

        if ($delinquentLoanIds->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;

        $this->activeLoansQuery()
            ->whereIn('id', $delinquentLoanIds)
            ->whereIn('subshop_id', $subshopIds)
            ->select(['id'])
            ->chunkById(200, function ($loans) use (&$total) {
                foreach ($loans as $loan) {
                    $outstanding = $this->calculateLoanOutstanding($loan);
                    if ($outstanding > 0) {
                        $total += $outstanding;
                    }
                }
            });

        return round($total, 2);
    }

    /**
     * Active loans query used across calculations.
     *
     * In most microfinance systems, PAR is measured on the active portfolio.
     * Outstanding should ONLY include disbursed loans:
     * - disbursed: Loan has been disbursed, currently being repaid
     * - partially_paid: Partially repaid, still active
     * - defaulted: Overdue/written off
     *
     * Excludes:
     * - pending/approved: Not yet disbursed, no outstanding yet
     * - written_off: Already written off (tracked separately)
     * - paid_off: Fully repaid
     *
     * We exclude soft-deleted rows automatically (SoftDeletes).
     */
    public function activeLoansQuery(): Builder
    {
        return Loans::query()
            ->where('is_active', true)
            ->whereIn('status', [
                'disbursed',
                'partially_paid',
                'defaulted',
            ])
            ->where('is_written_off', false);
    }
}
