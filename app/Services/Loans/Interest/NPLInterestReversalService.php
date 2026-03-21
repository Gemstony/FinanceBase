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
 * Service for reversing interest when loans become non-performing (NPL).
 *
 * In microfinance, when a loan exceeds 90 days overdue:
 * - Regulatory requirements typically mandate stopping interest accrual
 * - Previously accrued interest may need to be reversed or suspended
 * - This ensures financial statements comply with accounting standards
 *
 * Journal Entry for reversal:
 * - Debit: Interest Income (reverse the income)
 * - Credit: Accrued Interest Receivable (reverse the asset)
 */
class NPLInterestReversalService
{
    public const NPL_THRESHOLD_DAYS = 90;

    public function __construct(
        protected ?JournalPostingEngine $journalPostingEngine = null,
    ) {
    }

    /**
     * Process NPL interest reversals for all loans that exceeded 90 days overdue.
     *
     * This should be run AFTER the daily accrual to catch any loans that just crossed
     * the NPL threshold.
     */
    public function processNPLReversals(?Carbon $asOfDate = null): array
    {
        $results = [
            'processed' => 0,
            'loans_became_npl' => 0,
            'reversals' => 0,
            'total_reversed' => 0.0,
            'errors' => 0,
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
                        $result = $this->processLoanNPLReversal($loan, $today);
                        if ($result['reversed']) {
                            $results['reversals']++;
                            $results['total_reversed'] += $result['amount'];
                        }
                        if ($result['became_npl']) {
                            $results['loans_became_npl']++;
                        }
                        $results['processed']++;
                    } catch (\Throwable $e) {
                        Log::error('NPL interest reversal failed for loan', [
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
     * Process NPL reversal for a specific loan.
     *
     * Returns array with:
     * - became_npl: true if loan just crossed NPL threshold
     * - reversed: true if reversal was performed
     * - amount: amount reversed
     */
    public function processLoanNPLReversal(Loans $loan, Carbon $asOfDate): array
    {
        $result = [
            'became_npl' => false,
            'reversed' => false,
            'amount' => 0.0,
        ];

        $maxOverdueDays = $this->calculateMaxOverdueDays($loan, $asOfDate);

        // Check if loan is now NPL (>90 days overdue)
        if ($maxOverdueDays <= self::NPL_THRESHOLD_DAYS) {
            return $result;
        }

        $result['became_npl'] = true;

        // Check if there's a pending reversal already (to avoid duplicate reversals)
        $hasExistingReversal = LoanInterestAccruals::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->where('is_npl_reversal', true)
            ->whereDate('accrual_date', $asOfDate->toDateString())
            ->exists();

        if ($hasExistingReversal) {
            return $result;
        }

        // Get total unposted accruals that should be reversed
        $unpostedAccruals = LoanInterestAccruals::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->where('is_posted', false)
            ->where('is_npl_reversal', false)
            ->get();

        if ($unpostedAccruals->isEmpty()) {
            return $result;
        }

        $totalReversal = round((float) $unpostedAccruals->sum('daily_interest') * -1, 2);

        if ($totalReversal >= 0) {
            return $result;
        }

        return DB::transaction(function () use ($loan, $asOfDate, $unpostedAccruals, $totalReversal, &$result) {
            // Create reversal record (negative interest)
            $reversal = LoanInterestAccruals::create([
                'loan_id' => $loan->id,
                'installment_id' => null,
                'accrual_date' => $asOfDate->toDateString(),
                'principal_balance' => 0,
                'interest_rate' => 0,
                'daily_interest' => $totalReversal, // Negative value
                'is_posted' => false,
                'posting_id' => null,
                'is_active' => true,
                'is_npl_reversal' => true,
                'npl_reversal_reason' => 'Loan exceeded ' . self::NPL_THRESHOLD_DAYS . ' days overdue',
            ]);

            // Mark original accruals as reversed
            LoanInterestAccruals::query()
                ->whereIn('id', $unpostedAccruals->pluck('id'))
                ->update([
                    'is_posted' => true,
                    'posting_id' => $reversal->id,
                ]);

            // Post reversal to General Ledger
            $this->postReversalToGeneralLedger($loan, $reversal, abs($totalReversal));

            $result['reversed'] = true;
            $result['amount'] = abs($totalReversal);

            Log::info('NPL interest reversal completed', [
                'loan_id' => $loan->id,
                'reversal_id' => $reversal->id,
                'amount' => $totalReversal,
            ]);

            return $result;
        });
    }

    /**
     * Calculate maximum days overdue among overdue installments.
     */
    protected function calculateMaxOverdueDays(Loans $loan, Carbon $today): int
    {
        $max = (int) LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->get(['due_date'])
            ->map(function ($i) use ($today) {
                $due = $i->due_date instanceof Carbon
                    ? $i->due_date->copy()->startOfDay()
                    : Carbon::parse($i->due_date)->startOfDay();
                return max(0, (int) $due->diffInDays($today, false));
            })
            ->max();

        return max(0, $max);
    }

    /**
     * Post NPL reversal to General Ledger.
     *
     * Journal Entry:
     * - Debit: Interest Income (reverse the income recognition)
     * - Credit: Accrued Interest Receivable (reverse the asset)
     */
    protected function postReversalToGeneralLedger(
        Loans $loan,
        LoanInterestAccruals $reversal,
        float $amount
    ): void {
        if (!$this->journalPostingEngine) {
            return;
        }

        $accruedInterestAccountId = (int) ($loan->interest_receivable_account_id ?? 0);
        $interestIncomeAccountId = (int) ($loan->interest_income_account_id ?? 0);

        if ($accruedInterestAccountId <= 0 || $interestIncomeAccountId <= 0) {
            Log::warning('NPL reversal GL posting skipped - accounts not configured', [
                'loan_id' => $loan->id,
                'reversal_id' => $reversal->id,
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
                        'description' => "NPL interest reversal - {$loan->loan_code}",
                    ],
                    [
                        'account_id' => $accruedInterestAccountId,
                        'debit' => 0,
                        'credit' => round($amount, 2),
                        'description' => "NPL accrued interest reversal - {$loan->loan_code}",
                    ],
                ],
                'npl_interest_reversal',
                (int) $reversal->id,
                "NPL Interest Reversal - Loan {$loan->loan_code}",
                $reversal->accrual_date?->toDateString()
            );

            Log::info('NPL interest reversal posted to General Ledger', [
                'loan_id' => $loan->id,
                'reversal_id' => $reversal->id,
                'amount' => $amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to post NPL reversal to General Ledger', [
                'loan_id' => $loan->id,
                'reversal_id' => $reversal->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get summary of loans at NPL risk.
     */
    public function getNPLSummary(?Carbon $asOfDate = null): array
    {
        $today = ($asOfDate ?? Carbon::today())->startOfDay();
        $activeStatuses = ['active', 'disbursed', 'partially_paid'];

        $loansAtRisk = Loans::query()
            ->where('is_active', true)
            ->whereIn('status', $activeStatuses)
            ->get()
            ->map(function ($loan) use ($today) {
                $maxOverdueDays = $this->calculateMaxOverdueDays($loan, $today);
                return [
                    'loan_id' => $loan->id,
                    'loan_code' => $loan->loan_code,
                    'max_overdue_days' => $maxOverdueDays,
                    'is_npl' => $maxOverdueDays > self::NPL_THRESHOLD_DAYS,
                ];
            })
            ->filter(fn($l) => $l['is_npl']);

        return [
            'total_at_risk' => $loansAtRisk->count(),
            'npl_threshold_days' => self::NPL_THRESHOLD_DAYS,
            'loans' => $loansAtRisk->values(),
        ];
    }
}
