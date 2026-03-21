<?php

namespace App\Services\Loans\Interest;

use App\Models\ChartsOfAccount;
use App\Models\JournalEntries;
use App\Models\JournalEntryLines;
use App\Models\LoanInterestAccruals;
use App\Models\LoanInterestPostings;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for reconciling interest accruals between loan system and General Ledger.
 *
 * This helps identify discrepancies between:
 * - Sum of loan_interest_accruals (operational records)
 * - GL balance of Accrued Interest Receivable account
 *
 * Common reconciliation issues:
 * 1. Missing GL entries for daily accruals
 * 2. Duplicate GL entries
 * 3. Reversals not posted to GL
 * 4. Manual adjustments in GL not reflected in loans
 */
class InterestReconciliationService
{
    /**
     * Get reconciliation report for all loans.
     *
     * Returns a detailed breakdown of:
     * - Total accrued interest from loan system
     * - GL balance for each loan's accrued interest account
     * - Discrepancies
     */
    public function getReconciliationReport(?Carbon $asOfDate = null): array
    {
        $asOf = $asOfDate ?? Carbon::today();

        // Get all loans with their accrued interest accounts
        $loans = Loans::query()
            ->where('is_active', true)
            ->whereIn('status', ['active', 'disbursed', 'partially_paid'])
            ->whereNotNull('interest_receivable_account_id')
            ->where('interest_receivable_account_id', '>', 0)
            ->get();

        $results = [];
        $totalLoanAccrued = 0.0;
        $totalGLAccrued = 0.0;
        $totalDiscrepancy = 0.0;

        foreach ($loans as $loan) {
            $loanAccrued = $this->getLoanAccruedInterest($loan, $asOf);
            $glAccrued = $this->getGLAccruedInterest($loan, $asOf);
            $discrepancy = round($glAccrued - $loanAccrued, 2);

            $totalLoanAccrued += $loanAccrued;
            $totalGLAccrued += $glAccrued;
            $totalDiscrepancy += $discrepancy;

            $results[] = [
                'loan_id' => $loan->id,
                'loan_code' => $loan->loan_code,
                'customer_name' => $loan->customer?->full_name ?? 'N/A',
                'principal_balance' => $loan->outstanding_balance,
                'accrued_interest_account_id' => $loan->interest_receivable_account_id,
                'accrued_interest_account_name' => $this->getAccountName($loan->interest_receivable_account_id),
                'loan_accrued_interest' => $loanAccrued,
                'gl_accrued_interest' => $glAccrued,
                'discrepancy' => $discrepancy,
                'status' => $this->getReconciliationStatus($discrepancy),
            ];
        }

        // Sort by discrepancy descending (biggest issues first)
        usort($results, fn($a, $b) => abs($b['discrepancy']) <=> abs($a['discrepancy']));

        return [
            'as_of_date' => $asOf->toDateString(),
            'summary' => [
                'total_loans' => count($results),
                'loans_in_balance' => count(array_filter($results, fn($r) => $r['status'] === 'balanced')),
                'loans_with_discrepancy' => count(array_filter($results, fn($r) => $r['status'] !== 'balanced')),
                'total_loan_accrued_interest' => round($totalLoanAccrued, 2),
                'total_gl_accrued_interest' => round($totalGLAccrued, 2),
                'total_discrepancy' => round($totalDiscrepancy, 2),
            ],
            'details' => $results,
        ];
    }

    /**
     * Get total accrued interest for a loan from the loan system.
     */
    protected function getLoanAccruedInterest(Loans $loan, Carbon $asOf): float
    {
        // Sum of all accruals (including reversals)
        $total = LoanInterestAccruals::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->whereDate('accrual_date', '<=', $asOf->toDateString())
            ->sum('daily_interest');

        return round((float) $total, 2);
    }

    /**
     * Get accrued interest balance from General Ledger.
     */
    protected function getGLAccruedInterest(Loans $loan, Carbon $asOf): float
    {
        $accountId = (int) $loan->interest_receivable_account_id;
        if ($accountId <= 0) {
            return 0.0;
        }

        // Get sum of debits minus credits for the accrued interest account
        $balance = JournalEntryLines::query()
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($query) use ($asOf) {
                $query->whereDate('transaction_date', '<=', $asOf->toDateString());
            })
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance');

        return round((float) ($balance ?? 0), 2);
    }

    /**
     * Get account name from chart of accounts.
     */
    protected function getAccountName(int $accountId): string
    {
        $account = ChartsOfAccount::query()->find($accountId);
        return $account ? ($account->account_name ?? $account->account_code ?? 'Unknown') : 'Unknown';
    }

    /**
     * Determine reconciliation status based on discrepancy amount.
     */
    protected function getReconciliationStatus(float $discrepancy): string
    {
        $absDiscrepancy = abs($discrepancy);

        if ($absDiscrepancy < 0.01) {
            return 'balanced';
        } elseif ($absDiscrepancy < 10) {
            return 'minor_difference'; // Allow for rounding
        } elseif ($discrepancy > 0) {
            return 'gl_higher'; // GL has more - potential duplicate entries
        } else {
            return 'loan_higher'; // Loan has more - missing GL entries
        }
    }

    /**
     * Get summary statistics for interest accruals.
     */
    public function getAccrualStatistics(?Carbon $asOfDate = null): array
    {
        $asOf = $asOfDate ?? Carbon::today();

        // Total accrued interest (loan system)
        $totalAccrued = LoanInterestAccruals::query()
            ->where('is_active', true)
            ->whereDate('accrual_date', '<=', $asOf->toDateString())
            ->sum('daily_interest');

        // Total posted interest
        $totalPosted = LoanInterestPostings::query()
            ->where('is_active', true)
            ->whereDate('posting_date', '<=', $asOf->toDateString())
            ->sum('interest_amount');

        // Unposted accruals count
        $unpostedCount = LoanInterestAccruals::query()
            ->where('is_active', true)
            ->where('is_posted', false)
            ->count();

        // Total unposted amount
        $unpostedAmount = LoanInterestAccruals::query()
            ->where('is_active', true)
            ->where('is_posted', false)
            ->sum('daily_interest');

        // NPL reversals
        $nplReversals = LoanInterestAccruals::query()
            ->where('is_active', true)
            ->where('is_npl_reversal', true)
            ->whereDate('accrual_date', '<=', $asOf->toDateString())
            ->sum('daily_interest');

        return [
            'as_of_date' => $asOf->toDateString(),
            'total_accrued_interest' => round((float) $totalAccrued, 2),
            'total_posted_interest' => round((float) $totalPosted, 2),
            'unposted_accruals' => [
                'count' => $unpostedCount,
                'amount' => round((float) $unpostedAmount, 2),
            ],
            'npl_reversals' => [
                'amount' => round((float) $nplReversals, 2),
            ],
        ];
    }

    /**
     * Get loans with missing GL accounts configured.
     */
    public function getLoansMissingGLAccounts(): Collection
    {
        return Loans::query()
            ->where('is_active', true)
            ->whereIn('status', ['active', 'disbursed', 'partially_paid'])
            ->where(function ($query) {
                $query->whereNull('interest_receivable_account_id')
                    ->orWhereNull('interest_income_account_id')
                    ->orWhere('interest_receivable_account_id', 0)
                    ->orWhere('interest_income_account_id', 0);
            })
            ->with(['customer'])
            ->get()
            ->map(function ($loan) {
                return [
                    'loan_id' => $loan->id,
                    'loan_code' => $loan->loan_code,
                    'customer_name' => $loan->customer?->full_name ?? 'N/A',
                    'interest_receivable_account_id' => $loan->interest_receivable_account_id,
                    'interest_income_account_id' => $loan->interest_income_account_id,
                ];
            });
    }

    /**
     * Get detailed GL entry history for a specific loan's interest accounts.
     */
    public function getLoanGLEntryHistory(Loans $loan, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $start = $startDate ?? Carbon::now()->startOfMonth();
        $end = $endDate ?? Carbon::now()->endOfDay();

        $accountIds = array_filter([
            $loan->interest_receivable_account_id,
            $loan->interest_income_account_id,
        ]);

        if (empty($accountIds)) {
            return [];
        }

        $entries = JournalEntryLines::query()
            ->with(['journalEntry'])
            ->whereIn('account_id', $accountIds)
            ->whereHas('journalEntry', function ($query) use ($start, $end) {
                $query->whereDate('transaction_date', '>=', $start->toDateString())
                    ->whereDate('transaction_date', '<=', $end->toDateString());
            })
            ->orderByDesc('id')
            ->get()
            ->map(function ($line) {
                return [
                    'date' => $line->journalEntry?->transaction_date?->toDateString(),
                    'reference' => $line->journalEntry?->reference_type . ' #' . $line->journalEntry?->reference_id,
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ];
            });

        return $entries->toArray();
    }
}
