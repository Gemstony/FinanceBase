<?php

declare(strict_types=1);

namespace App\Services\BankReconciliation;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\JournalEntries;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ReconciliationService
{
    public function manualMatch(int $statementLineId, int $journalEntryId): BankStatementLine
    {
        return DB::transaction(function () use ($statementLineId, $journalEntryId) {
            $line = BankStatementLine::query()->lockForUpdate()->findOrFail($statementLineId);
            $statement = BankStatement::query()->lockForUpdate()->findOrFail((int) $line->bank_statement_id);

            $this->authorizeStatementContext($statement);

            if ((string) $statement->status === 'reconciled') {
                throw new RuntimeException('This statement is already reconciled.');
            }

            if ((bool) $line->is_matched || !empty($line->matched_journal_entry_id)) {
                throw new RuntimeException('This statement line is already matched.');
            }

            $journal = JournalEntries::query()
                ->with('lines')
                ->whereKey($journalEntryId)
                ->firstOrFail();

            if ((int) $journal->subshop_id !== (int) session('subshop_id')) {
                throw new RuntimeException('Journal entry is not in the active branch context.');
            }

            $line->is_matched = true;
            $line->matched_journal_entry_id = (int) $journal->id;
            $line->save();

            return $line;
        });
    }

    public function summary(BankStatement $statement): array
    {
        $statement->loadMissing(['lines', 'bankAccount']);
        $bankGlAccountId = (int) ($statement->bankAccount->chart_of_account_id ?? 0);

        $matchedLines = $statement->lines
            ->where('is_matched', true)
            ->whereNotNull('matched_journal_entry_id');

        $totalTx = (int) $statement->lines->count();
        $matchedTx = (int) $matchedLines->count();
        $unmatchedTx = $totalTx - $matchedTx;

        $statementDebit = round((float) $statement->lines->sum('debit'), 2);
        $statementCredit = round((float) $statement->lines->sum('credit'), 2);
        $statementNet = round((float) $statement->lines->sum('amount'), 2);

        $matchedJournalIds = $matchedLines
            ->pluck('matched_journal_entry_id')
            ->filter()
            ->unique()
            ->values();

        $journals = $matchedJournalIds->isEmpty()
            ? collect()
            : JournalEntries::query()->with('lines')->whereIn('id', $matchedJournalIds)->get();

        $ledgerNet = 0.0;
        foreach ($journals as $j) {
            // We only care about the lines in this journal that hit the Bank's GL account.
            // Ledger Net = (Bank Debits) - (Bank Credits).
            $bankLines = $j->lines->where('account_id', $bankGlAccountId);
            $jBankDebit = (float) $bankLines->sum('debit');
            $jBankCredit = (float) $bankLines->sum('credit');
            
            $ledgerNet += ($jBankDebit - $jBankCredit);
        }
        $ledgerNet = round($ledgerNet, 2);

        $statementBalanceMove = round((float) $statement->closing_balance - (float) $statement->opening_balance, 2);
        $difference = round($statementNet - $ledgerNet, 2);

        return [
            'total_transactions' => $totalTx,
            'matched_transactions' => $matchedTx,
            'unmatched_transactions' => $unmatchedTx,
            'statement_debit' => $statementDebit,
            'statement_credit' => $statementCredit,
            'statement_net' => $statementNet,
            'statement_balance_move' => $statementBalanceMove,
            'ledger_net' => $ledgerNet,
            'difference' => $difference,
        ];
    }

    public function validateBeforeFinalize(BankStatement $statement): void
    {
        $summary = $this->summary($statement);

        if ((int) ($summary['total_transactions'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Statement has no transactions.');
        }

        if (round((float) ($summary['difference'] ?? 0.0), 2) !== 0.0) {
            throw new InvalidArgumentException('Reconciliation is not balanced. Difference: ' . $summary['difference']);
        }

        if ((int) ($summary['unmatched_transactions'] ?? 0) > 0) {
            throw new InvalidArgumentException('All statement lines must be matched before finalizing.');
        }
    }

    public function finalize(BankStatement $statement): BankStatement
    {
        return DB::transaction(function () use ($statement) {
            $statement = BankStatement::query()->lockForUpdate()->findOrFail((int) $statement->id);

            $this->authorizeStatementContext($statement);

            if ((string) $statement->status === 'reconciled') {
                return $statement;
            }

            $this->validateBeforeFinalize($statement);

            $statement->status = 'reconciled';
            $statement->reconciled_at = Carbon::now();
            $statement->save();

            return $statement;
        });
    }

    public function resetMatches(BankStatement $statement): int
    {
        return DB::transaction(function () use ($statement): int {
            $statement = BankStatement::query()->lockForUpdate()->findOrFail((int) $statement->id);

            $this->authorizeStatementContext($statement);

            if ((string) $statement->status === 'reconciled') {
                throw new RuntimeException('This statement is already reconciled. You cannot reset matches.');
            }

            $lines = BankStatementLine::query()
                ->where('bank_statement_id', (int) $statement->id)
                ->where(function ($q) {
                    $q->where('is_matched', true)
                        ->orWhereNotNull('matched_journal_entry_id');
                })
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($lines as $line) {
                $line->is_matched = false;
                $line->matched_journal_entry_id = null;
                $line->save();
                $count++;
            }

            return $count;
        });
    }

    private function authorizeStatementContext(BankStatement $statement): void
    {
        $statement->loadMissing('bankAccount');

        if (!$statement->bankAccount) {
            throw new RuntimeException('Statement bank account not found.');
        }

        if ((int) $statement->bankAccount->subshop_id !== (int) session('subshop_id')) {
            throw new RuntimeException('You can only reconcile statements for the active branch context.');
        }
    }
}
