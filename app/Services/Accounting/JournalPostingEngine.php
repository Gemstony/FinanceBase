<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\JournalEntries;
use App\Models\JournalEntryLines;
use App\Models\LoanDisbursements;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JournalPostingEngine
{
    public function __construct(
        private readonly DoubleEntryValidator $validator,
        private readonly JournalEntryBuilder $builder,
        private readonly LoanAccountingMapper $mapper,
    ) {
    }

    /**
     * Post a generic journal entry to the General Ledger.
     *
     * Execution flow:
     * 1. Validate the lines for double-entry balance.
     * 2. Begin a database transaction.
     * 3. Insert the journal entry header.
     * 4. Insert all journal entry lines.
     * 5. Commit the transaction.
     *
     * @param array  $lines          Array of lines with account_id, debit, credit, description
     * @param string $referenceType  Reference type (e.g., loan_disbursement, loan_payment)
     * @param int    $referenceId    Reference ID (e.g., loan_id, payment_id)
     * @param string|null $description Optional transaction description
     *
     * @return JournalEntries
     */
    public function postJournalEntry(
        array $lines,
        string $referenceType,
        int $referenceId,
        ?string $description = null,
        ?string $transactionDate = null
    ): JournalEntries {
        $this->validator->validate($lines);

        return DB::transaction(function () use (
            $lines,
            $referenceType,
            $referenceId,
            $description,
            $transactionDate
        ) {
            $journal = JournalEntries::create([
                'subshop_id' => (int) session('subshop_id'),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'transaction_date' => $transactionDate ?: Carbon::now()->toDateString(),
                'description' => $description,
                'created_by' => auth()->id(),
            ]);

            $linesToInsert = [];
            foreach ($lines as $line) {
                $linesToInsert[] = [
                    'journal_entry_id' => $journal->id,
                    'account_id' => (int) $line['account_id'],
                    'debit' => round((float) $line['debit'], 2),
                    'credit' => round((float) $line['credit'], 2),
                    'description' => $line['description'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            JournalEntryLines::insert($linesToInsert);

            return $journal;
        });
    }

    /**
     * Convenience method: post a loan disbursement journal entry.
     *
     * @param Loans  $loan   The loan being disbursed
     * @param float  $amount Disbursement amount
     * @param int    $creditAccountId Chart of Accounts ID for the bank/cash account to credit
     *
     * @return JournalEntries
     */
    public function postLoanDisbursement(Loans $loan, float $amount, int $creditAccountId): JournalEntries
    {
        $lines = $this->mapper->buildLoanDisbursementEntry($loan, $amount, $creditAccountId);

        return $this->postJournalEntry(
            $lines,
            'loan_disbursement',
            (int) $loan->id,
            "Loan disbursement – {$loan->loan_code}"
        );
    }

    /**
     * Post a loan disbursement journal entry tied to a specific disbursement record.
     */
    public function postLoanDisbursementFromDisbursement(
        Loans $loan,
        LoanDisbursements $loanDisbursement,
        int $creditAccountId
    ): JournalEntries {
        $lines = $this->mapper->buildLoanDisbursementEntry($loan, (float) $loanDisbursement->amount, $creditAccountId);

        return $this->postJournalEntry(
            $lines,
            'loan_disbursement',
            (int) $loanDisbursement->id,
            'Loan disbursement for loan #' . (int) $loan->id,
            $loanDisbursement->disbursement_date ? $loanDisbursement->disbursement_date->toDateString() : null
        );
    }

    /**
     * Convenience method: post a loan repayment journal entry.
     *
     * @param array $allocation Payment allocation data
     *
     * @return JournalEntries
     */
    public function postLoanRepayment(array $allocation): JournalEntries
    {
        $lines = $this->mapper->buildLoanRepaymentEntry($allocation);

        return $this->postJournalEntry(
            $lines,
            'loan_payment',
            (int) ($allocation['payment_id'] ?? 0),
            'Loan repayment – cash received'
        );
    }

    public function postLoanJournalEntryReversalForPayment(int $paymentId): JournalEntries
    {
        $original = JournalEntries::query()
            ->with('lines')
            ->where('reference_type', 'loan_payment')
            ->where('reference_id', $paymentId)
            ->latest('id')
            ->first();

        if (!$original) {
            return $this->postJournalEntry(
                [],
                'loan_payment_reversal',
                $paymentId,
                'Loan repayment reversal – no original journal entry found'
            );
        }

        $reversalLines = [];
        foreach ($original->lines as $line) {
            $reversalLines[] = [
                'account_id' => (int) $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => $line->description,
            ];
        }

        return $this->postJournalEntry(
            $reversalLines,
            'loan_payment_reversal',
            $paymentId,
            'Loan repayment reversal'
        );
    }

    /**
     * Convenience method: post a loan write-off journal entry.
     *
     * @param Loans  $loan   The loan being written off
     * @param float  $amount Write-off amount
     *
     * @return JournalEntries
     */
    public function postLoanWriteOff(Loans $loan, float $amount): JournalEntries
    {
        $lines = $this->mapper->buildLoanWriteOffEntry($loan, $amount);

        return $this->postJournalEntry(
            $lines,
            'loan_write_off',
            (int) $loan->id,
            "Loan write-off – {$loan->loan_code}"
        );
    }

    /**
     * Convenience method: post a loan recovery journal entry.
     *
     * @param float $amount Recovery amount received
     *
     * @return JournalEntries
     */
    public function postLoanRecovery(float $amount): JournalEntries
    {
        $lines = $this->mapper->buildLoanRecoveryEntry($amount);

        return $this->postJournalEntry(
            $lines,
            'loan_recovery',
            0, // No specific reference ID for generic recovery
            'Loan recovery – cash received'
        );
    }
}
