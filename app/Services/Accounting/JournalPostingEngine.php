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
    ) {}

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
     * @param  array  $lines  Array of lines with account_id, debit, credit, description
     * @param  string  $referenceType  Reference type (e.g., loan_disbursement, loan_payment)
     * @param  int  $referenceId  Reference ID (e.g., loan_id, payment_id)
     * @param  string|null  $description  Optional transaction description
     */
    public function postJournalEntry(
        array $lines,
        string $referenceType,
        int $referenceId,
        ?string $description = null,
        ?string $transactionDate = null,
        ?int $subshopId = null
    ): JournalEntries {
        $this->validator->validate($lines);

        return DB::transaction(function () use (
            $lines,
            $referenceType,
            $referenceId,
            $description,
            $transactionDate,
            $subshopId
        ) {
            $journal = JournalEntries::create([
                'subshop_id' => $subshopId ?? (int) session('subshop_id') ?? 0,
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
     * @param  Loans  $loan  The loan being disbursed
     * @param  float  $amount  Disbursement amount
     * @param  int  $creditAccountId  Chart of Accounts ID for the bank/cash account to credit
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
            'Loan disbursement for loan #'.(int) $loan->id,
            $loanDisbursement->disbursement_date ? $loanDisbursement->disbursement_date->toDateString() : null
        );
    }

    /**
     * Convenience method: post a loan repayment journal entry.
     *
     * @param  array  $allocation  Payment allocation data
     */
    public function postLoanRepayment(array $allocation): JournalEntries
    {
        $lines = $this->mapper->buildLoanRepaymentEntry($allocation);

        $subshopId = (int) ($allocation['subshop_id'] ?? session('subshop_id') ?? 0);

        return $this->postJournalEntry(
            $lines,
            'loan_payment',
            (int) ($allocation['payment_id'] ?? 0),
            'Loan repayment – cash received',
            null,
            $subshopId
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

        if (! $original) {
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
     * @param  Loans  $loan  The loan being written off
     * @param  array  $balances  Array with keys: principal_written_off, interest_written_off, fees_written_off, penalties_written_off
     * @param  int    $writeOffExpenseAccountId  The configured expense account for write-offs
     */
    public function postLoanWriteOff(Loans $loan, array $balances, int $writeOffExpenseAccountId): JournalEntries
    {
        $lines = $this->mapper->buildLoanWriteOffEntry($loan, $balances, $writeOffExpenseAccountId);

        return $this->postJournalEntry(
            $lines,
            'loan_write_off',
            (int) $loan->id,
            "Loan write-off – {$loan->loan_code}",
            null,
            (int) ($loan->subshop_id ?? 0)
        );
    }

    /**
     * Convenience method: post a loan recovery journal entry.
     *
     * @param  array  $recoveryData  Array with keys: principal, interest, fees, penalties, total, bank_account_id, payment_method, subshop_id
     * @param  int    $recoveryIncomeAccountId  The configured recovery income account ID
     * @param  int    $referenceId  Reference ID for the recovery record
     */
    public function postLoanRecovery(array $recoveryData, int $recoveryIncomeAccountId, int $referenceId): JournalEntries
    {
        $lines = $this->mapper->buildLoanRecoveryEntry($recoveryData, $recoveryIncomeAccountId);

        return $this->postJournalEntry(
            $lines,
            'loan_writeoff_recovery',
            $referenceId,
            'Loan write-off recovery – cash received',
            null,
            (int) ($recoveryData['subshop_id'] ?? 0)
        );
    }
}
