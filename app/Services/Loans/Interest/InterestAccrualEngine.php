<?php

namespace App\Services\Loans\Interest;

use App\Models\LoanInterestAccruals;
use App\Models\LoanInstallments;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterestAccrualEngine
{
    public function __construct(
        protected LoanOutstandingCalculator $loanOutstandingCalculator,
        protected DailyInterestCalculator $dailyInterestCalculator,
    ) {
    }

    /**
     * Process daily interest accrual for the active loan portfolio.
     *
     * Business rules:
     * - Accrue interest daily on outstanding principal.
     * - Only accrue for loans in an "active" lifecycle state (disbursed/partially_paid).
     * - Do NOT accrue for written-off/paid-off loans.
     * - Do NOT accrue for loans with max overdue days > 90 (non-performing).
     * - Prevent duplicate accrual (one row per loan per day).
     */
    public function processDailyAccrual(?Carbon $asOfDate = null): void
    {
        $today = ($asOfDate ?? Carbon::today())->startOfDay();

        // Treat these as "active" loans for interest accrual in this system.
        // Note: the loans.status enum does not contain "active"; in microfinance,
        // interest accrues after disbursement while the loan is running.
        $activeStatuses = ['active', 'disbursed', 'partially_paid'];

        Loans::query()
            ->where('is_active', true)
            ->whereIn('status', $activeStatuses)
            ->select(['id', 'principal_amount', 'interest_rate'])
            ->orderBy('id')
            ->chunkById(200, function ($loans) use ($today) {
                foreach ($loans as $loan) {
                    try {
                        $this->accrueForLoan($loan, $today);
                    } catch (\Throwable $e) {
                        Log::error('Interest accrual failed for loan', [
                            'loan_id' => $loan->id,
                            'date' => $today->toDateString(),
                            'message' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                    }
                }
            });
    }

    protected function accrueForLoan(Loans $loan, Carbon $today): void
    {
        // Prevent duplicate accrual record per loan per day.
        $exists = LoanInterestAccruals::query()
            ->where('loan_id', $loan->id)
            ->whereDate('accrual_date', $today->toDateString())
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return;
        }

        // Do not accrue if loan is non-performing: max overdue days > 90.
        $maxOverdueDays = $this->calculateMaxOverdueDays($loan, $today);
        if ($maxOverdueDays > 90) {
            return;
        }

        $principalBalance = $this->loanOutstandingCalculator->calculateOutstandingPrincipal($loan);
        if ($principalBalance <= 0) {
            return;
        }

        $annualRate = (float) ($loan->interest_rate ?? 0);
        if ($annualRate <= 0) {
            return;
        }

        $dailyInterest = $this->dailyInterestCalculator->calculateDailyInterest($principalBalance, $annualRate);
        if ($dailyInterest <= 0) {
            return;
        }

        DB::transaction(function () use ($loan, $today, $principalBalance, $annualRate, $dailyInterest) {
            // Double-check inside the transaction to avoid race conditions when running in parallel.
            $existsTx = LoanInterestAccruals::query()
                ->where('loan_id', $loan->id)
                ->whereDate('accrual_date', $today->toDateString())
                ->where('is_active', true)
                ->exists();

            if ($existsTx) {
                return;
            }

            LoanInterestAccruals::create([
                'loan_id' => $loan->id,
                'installment_id' => null,
                'accrual_date' => $today->toDateString(),
                'principal_balance' => round($principalBalance, 2),
                'interest_rate' => round($annualRate, 4),
                'daily_interest' => round($dailyInterest, 6),
                'is_posted' => false,
                'posting_id' => null,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Calculate maximum days overdue among overdue installments.
     *
     * This is used as a simple NPL (non-performing loan) guard:
     * if max overdue days exceeds 90, we stop accruing interest.
     */
    protected function calculateMaxOverdueDays(Loans $loan, Carbon $today): int
    {
        $max = (int) LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->get(['due_date'])
            ->map(function ($i) use ($today) {
                $due = $i->due_date instanceof Carbon ? $i->due_date->copy()->startOfDay() : Carbon::parse($i->due_date)->startOfDay();
                return max(0, (int) $due->diffInDays($today, false));
            })
            ->max();

        return max(0, $max);
    }
}
