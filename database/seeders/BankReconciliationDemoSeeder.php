<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BankAccounts;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\JournalEntries;
use App\Models\JournalEntryLines;
use App\Models\SubShop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankReconciliationDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $subshop = SubShop::query()->first();
            $user = User::query()->first();

            if (!$subshop || !$user) {
                $this->command?->warn('No SubShop/User found. Seed shop/subshop and users first.');
                return;
            }

            $bankAccount = BankAccounts::query()->firstOrCreate(
                ['subshop_id' => (int) $subshop->id, 'account_name' => 'CRDB Operating Account'],
                [
                    'account_type' => 'bank',
                    'bank_name' => 'CRDB',
                    'account_number' => '000111222',
                    'opening_balance' => 0,
                    'currency_code' => 'TZS',
                    'chart_of_account_id' => 1,
                    'is_active' => true,
                    'created_by' => (int) $user->id,
                    'updated_by' => (int) $user->id,
                ]
            );

            $statementDate = Carbon::today()->toDateString();

            $statement = BankStatement::query()->create([
                'bank_account_id' => (int) $bankAccount->id,
                'statement_date' => $statementDate,
                'opening_balance' => 1000000,
                'closing_balance' => 900000,
                'reference_number' => 'STMT-DEMO-001',
                'status' => 'in_progress',
                'notes' => 'Demo statement for reconciliation testing',
            ]);

            // Create a journal entry that should match a statement credit of 100,000 (e.g. loan disbursement)
            $journal = JournalEntries::query()->create([
                'subshop_id' => (int) $subshop->id,
                'reference_type' => 'loan_disbursement',
                'reference_id' => 999,
                'transaction_date' => $statementDate,
                'description' => 'Demo disbursement journal',
                'created_by' => (int) $user->id,
            ]);

            JournalEntryLines::query()->insert([
                [
                    'journal_entry_id' => (int) $journal->id,
                    'account_id' => 1,
                    'debit' => 100000,
                    'credit' => 0,
                    'description' => 'Loan receivable',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'journal_entry_id' => (int) $journal->id,
                    'account_id' => (int) ($bankAccount->chart_of_account_id ?? 1),
                    'debit' => 0,
                    'credit' => 100000,
                    'description' => 'Bank credit',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            BankStatementLine::query()->insert([
                [
                    'bank_statement_id' => (int) $statement->id,
                    'transaction_date' => $statementDate,
                    'reference' => 'DISB-0001',
                    'description' => 'Loan disbursement',
                    'debit' => 0,
                    'credit' => 100000,
                    'amount' => 100000, // Credit (money in) should be positive
                    'is_matched' => false,
                    'matched_journal_entry_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'bank_statement_id' => (int) $statement->id,
                    'transaction_date' => Carbon::parse($statementDate)->subDay()->toDateString(),
                    'reference' => 'FEE-CHG',
                    'description' => 'Bank charges',
                    'debit' => 5000,
                    'credit' => 0,
                    'amount' => -5000, // Debit (money out) should be negative
                    'is_matched' => false,
                    'matched_journal_entry_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $this->command?->info('Bank reconciliation demo data seeded successfully.');
        });
    }
}
