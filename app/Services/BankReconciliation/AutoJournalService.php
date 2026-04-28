<?php

declare(strict_types=1);

namespace App\Services\BankReconciliation;

use App\Models\BankAccounts;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\ChartsOfAccount;
use App\Models\JournalEntries;
use App\Models\SubShop;
use App\Services\Accounting\DoubleEntryValidator;
use App\Services\Accounting\JournalEntryBuilder;
use App\Services\Accounting\JournalPostingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AutoJournalService
{
    public function __construct(
        private readonly JournalEntryBuilder $builder,
        private readonly DoubleEntryValidator $validator,
        private readonly JournalPostingEngine $postingEngine,
    ) {
    }

    public function suggestAccountId(BankStatementLine $line, int $shopId): ?int
    {
        $description = strtolower(trim((string) ($line->description ?? '')));

        if ($description === '') {
            return null;
        }

        $query = ChartsOfAccount::query()
            ->where('shop_id', $shopId)
            ->where('is_active', true);

        if (str_contains($description, 'charge') || str_contains($description, 'fee')) {
            $account = (clone $query)
                ->where('account_name', 'like', '%bank%charge%')
                ->orWhere('account_name', 'like', '%bank%fee%')
                ->orderBy('account_name')
                ->first(['id']);

            return $account ? (int) $account->id : null;
        }

        if (str_contains($description, 'interest')) {
            $account = (clone $query)
                ->where('account_name', 'like', '%interest%')
                ->orderBy('account_name')
                ->first(['id']);

            return $account ? (int) $account->id : null;
        }

        return null;
    }

    public function createForStatementLine(int $statementLineId, int $selectedAccountId): JournalEntries
    {
        return DB::transaction(function () use ($statementLineId, $selectedAccountId) {
            $line = BankStatementLine::query()->lockForUpdate()->findOrFail($statementLineId);

            if ((bool) $line->is_matched || !empty($line->matched_journal_entry_id)) {
                throw new RuntimeException('This statement line is already matched.');
            }

            $statement = BankStatement::query()->lockForUpdate()->findOrFail((int) $line->bank_statement_id);
            $statement->loadMissing('bankAccount');

            if (!$statement->bankAccount) {
                throw new RuntimeException('Statement bank account not found.');
            }

            if ((int) $statement->bankAccount->subshop_id !== (int) session('subshop_id')) {
                throw new RuntimeException('You can only create adjustments for the active branch context.');
            }

            $bank = BankAccounts::query()->lockForUpdate()->findOrFail((int) $statement->bank_account_id);
            $bankGlAccountId = (int) ($bank->chart_of_account_id ?? 0);
            if ($bankGlAccountId <= 0) {
                throw new RuntimeException('Selected bank account is not mapped to a GL account.');
            }

            $selectedAccount = ChartsOfAccount::query()->whereKey($selectedAccountId)->firstOrFail(['id', 'shop_id', 'is_active']);
            if (!(bool) $selectedAccount->is_active) {
                throw new RuntimeException('Selected account is inactive.');
            }
            // Validate account belongs to the same shop as the current subshop
            $currentSubshop = SubShop::findOrFail((int) session('subshop_id'));
            if ((int) $selectedAccount->shop_id !== (int) $currentSubshop->shop_id) {
                throw new RuntimeException('Selected account is not in the active shop context.');
            }

            $isDebit = ((float) $line->debit) > 0.0;
            $isCredit = ((float) $line->credit) > 0.0;

            if (!$isDebit && !$isCredit) {
                throw new RuntimeException('Statement line has zero debit and credit.');
            }

            if ($isDebit && $isCredit) {
                throw new RuntimeException('Statement line has both debit and credit amounts.');
            }

            $amount = $isDebit ? (float) $line->debit : (float) $line->credit;
            $amount = round(abs($amount), 2);
            if ($amount <= 0) {
                throw new RuntimeException('Amount must be greater than zero.');
            }

            $this->builder->reset();

            $lineDescription = trim('Auto-generated from bank reconciliation: ' . (string) ($line->description ?? ''));
            $amount = round(max((float) $line->debit, (float) $line->credit), 2);

            if ($isDebit) {
                $this->builder->addDebit($selectedAccountId, $amount, $lineDescription);
                $this->builder->addCredit($bankGlAccountId, $amount, $lineDescription);
            } else {
                $this->builder->addDebit($bankGlAccountId, $amount, $lineDescription);
                $this->builder->addCredit($selectedAccountId, $amount, $lineDescription);
            }

            $lines = $this->builder->getLines();

            $this->validator->validate($lines);

            $journal = $this->postingEngine->postJournalEntry(
                $lines,
                'bank_reconciliation_adjustment',
                (int) $line->id,
                $lineDescription,
                $line->transaction_date ? $line->transaction_date->toDateString() : null
            );

            $line->is_matched = true;
            $line->matched_journal_entry_id = (int) $journal->id;
            $line->save();

            Log::info('Bank reconciliation auto-journal created', [
                'subshop_id' => (int) session('subshop_id'),
                'user_id' => auth()->id(),
                'bank_statement_id' => (int) $statement->id,
                'bank_statement_line_id' => (int) $line->id,
                'journal_entry_id' => (int) $journal->id,
                'created_at' => now()->toDateTimeString(),
            ]);

            return $journal;
        });
    }
}
