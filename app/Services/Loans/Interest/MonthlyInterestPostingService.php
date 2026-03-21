<?php

namespace App\Services\Loans\Interest;

use App\Models\LoanInterestAccruals;
use App\Models\LoanInterestPostings;
use App\Models\LoanInstallments;
use App\Models\Loans;
use App\Services\Accounting\JournalPostingEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for posting monthly interest from accruals to income.
 *
 * This handles the transfer of accrued interest (which sits on the balance sheet
 * as a receivable) to interest income (which sits on the income statement).
 *
 * In microfinance, this is typically done:
 * - Monthly at month-end
 * - Or when interest is actually received
 *
 * Journal Entry:
 * - Debit: Interest Income (contra - to zero out the income side)
 * - Credit: Accrued Interest Receivable (to reduce the asset)
 *
 * Note: This is the REVERSE of the daily accrual. The daily accrual increases
 * both the receivable AND income. This monthly posting is typically used to
 * recognize the interest as "earned" or to reconcile the GL.
 */
class MonthlyInterestPostingService
{
    public function __construct(
        protected ?JournalPostingEngine $journalPostingEngine = null,
    ) {
    }

    /**
     * Process monthly interest posting for all active loans.
     *
     * This aggregates all unposted interest accruals and posts them to GL.
     * Typically run at month-end.
     */
    public function processMonthlyPosting(?Carbon $asOfDate = null): array
    {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total_interest' => 0.0,
        ];

        $today = ($asOfDate ?? Carbon::today())->startOfDay();
        $activeStatuses = ['active', 'disbursed', 'partially_paid'];

        Loans::query()
            ->where('is_active', true)
            ->whereIn('status', $activeStatuses)
            ->select(['id', 'loan_code', 'principal_amount', 'interest_rate'])
            ->orderBy('id')
            ->chunkById(200, function ($loans) use ($today, &$results) {
                foreach ($loans as $loan) {
                    try {
                        $posting = $this->postInterestForLoan($loan, $today);
                        if ($posting) {
                            $results['processed']++;
                            $results['total_interest'] += $posting->interest_amount;
                        } else {
                            $results['skipped']++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('Monthly interest posting failed for loan', [
                            'loan_id' => $loan->id,
                            'date' => $today->toDateString(),
                            'message' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                        $results['errors']++;
                    }
                }
            });

        return $results;
    }

    /**
     * Post interest for a specific loan.
     *
     * Returns the posting record if successful, null if nothing to post.
     */
    public function postInterestForLoan(Loans $loan, Carbon $asOfDate): ?LoanInterestPostings
    {
        return DB::transaction(function () use ($loan, $asOfDate) {
            // Get all unposted accruals for this loan
            $accruals = LoanInterestAccruals::query()
                ->where('loan_id', $loan->id)
                ->where('is_active', true)
                ->where('is_posted', false)
                ->lockForUpdate()
                ->get();

            if ($accruals->isEmpty()) {
                return null;
            }

            $total = round((float) $accruals->sum('daily_interest'), 2);

            if ($total <= 0) {
                // Mark zero entries as posted to prevent reprocessing
                LoanInterestAccruals::query()
                    ->whereIn('id', $accruals->pluck('id'))
                    ->update([
                        'is_posted' => true,
                        'posting_id' => null,
                    ]);
                return null;
            }

            // Create the posting record
            $posting = LoanInterestPostings::create([
                'loan_id' => $loan->id,
                'installment_id' => null,
                'posting_date' => $asOfDate->toDateString(),
                'interest_amount' => $total,
                'reference_number' => null,
                'description' => 'Monthly interest posting',
                'is_successful' => true,
                'is_active' => true,
            ]);

            // Mark accruals as posted
            LoanInterestAccruals::query()
                ->whereIn('id', $accruals->pluck('id'))
                ->update([
                    'is_posted' => true,
                    'posting_id' => $posting->id,
                ]);

            // Allocate posted interest to installments (FIFO)
            $this->allocatePostedInterestToInstallments($loan, $total);

            // Post to General Ledger
            $this->postToGeneralLedger($loan, $posting, $total);

            Log::info('Monthly interest posting completed', [
                'loan_id' => $loan->id,
                'posting_id' => $posting->id,
                'amount' => $total,
            ]);

            return $posting;
        });
    }

    /**
     * Allocate posted interest to installments (FIFO).
     *
     * This increases the interest_due on upcoming installments.
     */
    protected function allocatePostedInterestToInstallments(Loans $loan, float $interestAmount): void
    {
        $remaining = round(max(0.0, $interestAmount), 2);
        if ($remaining <= 0) {
            return;
        }

        $installments = LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        foreach ($installments as $inst) {
            if ($remaining <= 0) {
                break;
            }

            $add = $remaining;
            $inst->interest_due = round((float) $inst->interest_due + $add, 2);
            $inst->total_due = round(
                (float) $inst->principal_due +
                (float) $inst->interest_due +
                (float) $inst->fees_due +
                (float) $inst->penalty_due,
                2
            );
            $inst->outstanding_amount = round(
                max(0.0, (float) $inst->total_due - (float) $inst->amount_paid),
                2
            );
            $inst->save();

            $remaining = round($remaining - $add, 2);
        }
    }

    /**
     * Post monthly interest to General Ledger.
     *
     * Journal Entry (month-end recognition):
     * - Debit: Interest Income (to reverse/zero the daily accruals)
     * - Credit: Accrued Interest Receivable (to reduce the asset)
     *
     * Note: This is optional and depends on accounting policy.
     * Some systems only do daily accruals and recognize income on payment.
     */
    protected function postToGeneralLedger(
        Loans $loan,
        LoanInterestPostings $posting,
        float $amount
    ): void {
        if (!$this->journalPostingEngine) {
            return;
        }

        $accruedInterestAccountId = (int) ($loan->interest_receivable_account_id ?? 0);
        $interestIncomeAccountId = (int) ($loan->interest_income_account_id ?? 0);

        if ($accruedInterestAccountId <= 0 || $interestIncomeAccountId <= 0) {
            Log::warning('Monthly interest GL posting skipped - accounts not configured', [
                'loan_id' => $loan->id,
                'posting_id' => $posting->id,
            ]);
            return;
        }

        try {
            $this->journalPostingEngine->postJournalEntry(
                [
                    [
                        'account_id' => $interestIncomeAccountId,
                        'debit' => round($amount, 2),
                        'credit' => 0,
                        'description' => "Interest income recognition - {$loan->loan_code}",
                    ],
                    [
                        'account_id' => $accruedInterestAccountId,
                        'debit' => 0,
                        'credit' => round($amount, 2),
                        'description' => "Accrued interest reduction - {$loan->loan_code}",
                    ],
                ],
                'interest_posting',
                (int) $posting->id,
                "Monthly interest posting - Loan {$loan->loan_code}",
                $posting->posting_date?->toDateString()
            );

            Log::info('Monthly interest posting posted to General Ledger', [
                'loan_id' => $loan->id,
                'posting_id' => $posting->id,
                'amount' => $amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to post monthly interest to General Ledger', [
                'loan_id' => $loan->id,
                'posting_id' => $posting->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get summary of unposted interest accruals.
     */
    public function getUnpostedAccrualsSummary(): array
    {
        $summary = LoanInterestAccruals::query()
            ->select('loan_id')
            ->selectRaw('COUNT(*) as accrual_count')
            ->selectRaw('SUM(daily_interest) as total_interest')
            ->where('is_active', true)
            ->where('is_posted', false)
            ->groupBy('loan_id')
            ->get();

        return [
            'total_loans' => $summary->count(),
            'total_accruals' => $summary->sum('accrual_count'),
            'total_unposted_interest' => round($summary->sum('total_interest'), 2),
        ];
    }
}
