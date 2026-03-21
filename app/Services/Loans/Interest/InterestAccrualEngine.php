<?php

namespace App\Services\Loans\Interest;

use App\Models\LoanInterestAccruals;
use App\Models\LoanInstallments;
use App\Models\Loans;
use App\Services\Accounting\JournalPostingEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterestAccrualEngine
{
    public function __construct(
        protected LoanOutstandingCalculator $loanOutstandingCalculator,
        protected DailyInterestCalculator $dailyInterestCalculator,
        protected ?JournalPostingEngine $journalPostingEngine = null,
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
            ->select([
                'id', 
                'loan_code',
                'subshop_id',
                'principal_amount', 
                'interest_rate',
                'interest_receivable_account_id',
                'interest_income_account_id',
            ])
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

            $accrual = LoanInterestAccruals::create([
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

            // Post to General Ledger if JournalPostingEngine is available
            $this->postAccrualToGeneralLedger($loan, $accrual);
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

    /**
     * Post daily interest accrual to General Ledger.
     *
     * Journal Entry:
     * - Debit: Accrued Interest Receivable (interest_receivable_account_id)
     * - Credit: Interest Income (interest_income_account_id)
     *
     * This ensures interest accruals are properly reflected in financial statements.
     */
    protected function postAccrualToGeneralLedger(Loans $loan, LoanInterestAccruals $accrual): void
    {
        // Skip if JournalPostingEngine is not available (for backwards compatibility)
        if (!$this->journalPostingEngine) {
            return;
        }

        // Get the account IDs from the loan
        $accruedInterestAccountId = (int) ($loan->interest_receivable_account_id ?? 0);
        $interestIncomeAccountId = (int) ($loan->interest_income_account_id ?? 0);

        // Skip if accounts are not configured
        if ($accruedInterestAccountId <= 0 || $interestIncomeAccountId <= 0) {
            Log::warning('Interest accrual GL posting skipped - accounts not configured', [
                'loan_id' => $loan->id,
                'accrued_interest_account_id' => $accruedInterestAccountId,
                'interest_income_account_id' => $interestIncomeAccountId,
            ]);
            return;
        }

        // Get subshop_id from the loan (needed for journal entry)
        $subshopId = (int) ($loan->subshop_id ?? 0);
        if ($subshopId <= 0) {
            Log::warning('Interest accrual GL posting skipped - subshop_id not found', [
                'loan_id' => $loan->id,
            ]);
            return;
        }

        try {
            // Create journal entry directly to avoid session dependency in CLI
            \Illuminate\Support\Facades\DB::transaction(function () use ($loan, $accrual, $accruedInterestAccountId, $interestIncomeAccountId, $subshopId) {
                $journal = \App\Models\JournalEntries::create([
                    'subshop_id' => $subshopId,
                    'reference_type' => 'interest_accrual',
                    'reference_id' => (int) $accrual->id,
                    'transaction_date' => $accrual->accrual_date?->toDateString() ?? now()->toDateString(),
                    'description' => "Daily interest accrual - Loan {$loan->loan_code}",
                    'created_by' => 1, // System user
                ]);

                // Debit entry - Accrued Interest Receivable
                \App\Models\JournalEntryLines::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $accruedInterestAccountId,
                    'debit' => round($accrual->daily_interest, 2),
                    'credit' => 0,
                    'description' => "Interest accrual - {$loan->loan_code}",
                ]);

                // Credit entry - Interest Income
                \App\Models\JournalEntryLines::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $interestIncomeAccountId,
                    'debit' => 0,
                    'credit' => round($accrual->daily_interest, 2),
                    'description' => "Interest income accrual - {$loan->loan_code}",
                ]);

                Log::info('Interest accrual posted to General Ledger', [
                    'loan_id' => $loan->id,
                    'accrual_id' => $accrual->id,
                    'journal_entry_id' => $journal->id,
                    'amount' => $accrual->daily_interest,
                    'accrued_interest_account_id' => $accruedInterestAccountId,
                    'interest_income_account_id' => $interestIncomeAccountId,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to post interest accrual to General Ledger', [
                'loan_id' => $loan->id,
                'accrual_id' => $accrual->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
