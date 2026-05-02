<?php

declare(strict_types=1);

namespace App\Services\Loans\WriteOff;

use App\Models\ChartsOfAccount;
use App\Models\LoanInstallments;
use App\Models\LoanWriteOffAccount;
use App\Models\LoanWriteoffs;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Loans\Ledger\LoanTransactionLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LoanWriteOffEngine
{
    /** @var array<int, array<string, mixed>> Cache of validated configurations */
    private array $validatedConfigs = [];

    /** @var array<int, string> Cache of unconfigured subshop IDs with reason */
    private array $unconfiguredSubshops = [];

    public function __construct(
        private readonly LoanWriteOffValidator $validator,
        private readonly LoanWriteOffCalculator $calculator,
        private readonly LoanTransactionLedger $ledger,
        private readonly JournalPostingEngine $journalPostingEngine,
    ) {}

    /**
     * Get write-off account configuration for a subshop with validation.
     *
     * Validates:
     * - Configuration exists for subshop
     * - Write-off expense account exists, is active, belongs to subshop, is Class 5 (Expense)
     * - Recovery income account exists, is active, belongs to subshop, is Class 4 (Revenue)
     *
     * @param int $subshopId The subshop ID to get configuration for
     * @return array{write_off_expense_account_id:int,recovery_income_account_id:int}
     * @throws InvalidArgumentException If configuration is invalid or missing
     */
    public function getWriteOffAccounts(int $subshopId): array
    {
        // Return cached result if available
        if (isset($this->validatedConfigs[$subshopId])) {
            return $this->validatedConfigs[$subshopId];
        }

        // Check if already marked as unconfigured
        if (isset($this->unconfiguredSubshops[$subshopId])) {
            throw new InvalidArgumentException($this->unconfiguredSubshops[$subshopId]);
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        // Get all subshop IDs under this shop for validation
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $config = LoanWriteOffAccount::with(['writeOffExpenseAccount.accountClass', 'recoveryIncomeAccount.accountClass'])
            ->whereIn('subshop_id', $shopSubshopIds)
            ->first();

        if (! $config) {
            $message = "Loan write-off accounts not configured for subshop {$subshopId}";
            $this->unconfiguredSubshops[$subshopId] = $message;
            Log::warning($message, ['subshop_id' => $subshopId]);
            throw new InvalidArgumentException($message);
        }

        // Validate write-off expense account
        $expenseAccount = $config->writeOffExpenseAccount;
        if (! $expenseAccount) {
            $message = "Write-off expense account not found (ID: {$config->write_off_expense_account_id})";
            $this->unconfiguredSubshops[$subshopId] = $message;
            Log::warning($message, ['subshop_id' => $subshopId, 'account_id' => $config->write_off_expense_account_id]);
            throw new InvalidArgumentException($message);
        }

        if (! $expenseAccount->is_active) {
            $message = "Write-off expense account is inactive (ID: {$config->write_off_expense_account_id})";
            $this->unconfiguredSubshops[$subshopId] = $message;
            Log::warning($message, ['subshop_id' => $subshopId, 'account_id' => $config->write_off_expense_account_id]);
            throw new InvalidArgumentException($message);
        }

        // Validate account belongs to the same shop (shop-level scope)
        $currentSubshop = SubShop::findOrFail($subshopId);
        if ((int) $expenseAccount->shop_id !== (int) $currentSubshop->shop_id) {
            $message = "Write-off expense account does not belong to this shop (ID: {$config->write_off_expense_account_id})";
            $this->unconfiguredSubshops[$subshopId] = $message;
            Log::warning($message, [
                'subshop_id' => $subshopId,
                'shop_id' => $currentSubshop->shop_id,
                'account_id' => $config->write_off_expense_account_id,
                'account_shop_id' => $expenseAccount->shop_id,
            ]);
            throw new InvalidArgumentException($message);
        }

        // Validate it's an Expense account (Class 5)
        $expenseClassId = $expenseAccount->accountClass->code;
        if ($expenseClassId != 5) {
            $message = "Write-off expense account must be Class 5 (Expense), got Class {$expenseClassId}";
            $this->unconfiguredSubshops[$subshopId] = $message;
            Log::warning($message, [
                'subshop_id' => $subshopId,
                'account_id' => $config->write_off_expense_account_id,
                'account_class_id' => $expenseClassId,
            ]);
            throw new InvalidArgumentException($message);
        }

        // Validate recovery income account
        $incomeAccount = $config->recoveryIncomeAccount;
        if (! $incomeAccount) {
            $message = "Recovery income account not found (ID: {$config->recovery_income_account_id})";
            $this->unconfiguredSubshops[$subshopId] = $message;
            Log::warning($message, ['subshop_id' => $subshopId, 'account_id' => $config->recovery_income_account_id]);
            throw new InvalidArgumentException($message);
        }

        if (! $incomeAccount->is_active) {
            $message = "Recovery income account is inactive (ID: {$config->recovery_income_account_id})";
            $this->unconfiguredSubshops[$subshopId] = $message;
            Log::warning($message, ['subshop_id' => $subshopId, 'account_id' => $config->recovery_income_account_id]);
            throw new InvalidArgumentException($message);
        }

        // Validate account belongs to the same shop (shop-level scope)
        if ((int) $incomeAccount->shop_id !== (int) $currentSubshop->shop_id) {
            $message = "Recovery income account does not belong to this shop (ID: {$config->recovery_income_account_id})";
            $this->unconfiguredSubshops[$subshopId] = $message;
            Log::warning($message, [
                'subshop_id' => $subshopId,
                'shop_id' => $currentSubshop->shop_id,
                'account_id' => $config->recovery_income_account_id,
                'account_shop_id' => $incomeAccount->shop_id,
            ]);
            throw new InvalidArgumentException($message);
        }

        // Validate it's a Revenue account (Class 4)
        $incomeClassId = $incomeAccount->accountClass->code;
        if ($incomeClassId != 4) {
            $message = "Recovery income account must be Class 4 (Income), got Class {$incomeClassId}";
            $this->unconfiguredSubshops[$subshopId] = $message;
            Log::warning($message, [
                'subshop_id' => $subshopId,
                'account_id' => $config->recovery_income_account_id,
                'account_class_id' => $incomeClassId,
            ]);
            throw new InvalidArgumentException($message);
        }

        $result = [
            'write_off_expense_account_id' => (int) $config->write_off_expense_account_id,
            'recovery_income_account_id' => (int) $config->recovery_income_account_id,
        ];

        // Cache the validated configuration
        $this->validatedConfigs[$subshopId] = $result;

        Log::info('Loan write-off accounts validated and cached', [
            'subshop_id' => $subshopId,
            'write_off_expense_account_id' => $result['write_off_expense_account_id'],
            'recovery_income_account_id' => $result['recovery_income_account_id'],
        ]);

        return $result;
    }

    /**
     * Write off a severely delinquent loan.
     *
     * Accounting/portfolio behavior:
     * - Remaining receivables are recognized as loss (recorded in loan_writeoffs)
     * - The loan is moved to status = written_off, which prevents future accrual engines from processing it
     * - Remaining installments are frozen (deactivated) so they no longer participate in delinquency/collections engines
     * - The system still allows recovery tracking via loan_writeoff_recoveries
     *
     * @throws InvalidArgumentException If write-off accounts are not configured
     */
    public function writeOffLoan(Loans $loan, string $writeoffDate, string $reason, int $approvedBy): LoanWriteoffs
    {
        $this->validator->validate($loan);

        $subshopId = (int) ($loan->subshop_id ?? 0);
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Loan must have a valid subshop_id to perform write-off');
        }

        // Get validated write-off accounts - will throw if not configured
        $accounts = $this->getWriteOffAccounts($subshopId);

        return DB::transaction(function () use ($loan, $writeoffDate, $reason, $approvedBy, $accounts) {
            $balances = $this->calculator->calculateBalances($loan);

            // Validate that loan has required accounts for component write-off
            if (! $loan->principal_account_id) {
                throw new InvalidArgumentException('Loan missing principal_account_id for write-off');
            }
            if ($balances['interest_written_off'] > 0 && ! $loan->interest_receivable_account_id) {
                throw new InvalidArgumentException('Loan missing interest_receivable_account_id for write-off');
            }
            if ($balances['penalties_written_off'] > 0 && ! $loan->penalty_receivable_account_id) {
                throw new InvalidArgumentException('Loan missing penalty_receivable_account_id for write-off');
            }

            $date = Carbon::parse($writeoffDate)->toDateString();

            $writeoff = LoanWriteoffs::create([
                'loan_id' => $loan->id,
                'writeoff_date' => $date,
                'principal_written_off' => $balances['principal_written_off'],
                'interest_written_off' => $balances['interest_written_off'],
                'fees_written_off' => 0,
                'penalties_written_off' => $balances['penalties_written_off'],
                'total_written_off' => $balances['total_written_off'],
                'reason' => $reason,
                'approved_by' => $approvedBy,
                'approved_at' => Carbon::now(),
            ]);

            // Set loan status to stop accrual engines.
            $loan->status = 'written_off';
            $loan->is_written_off = true;
            $loan->save();

            // Close remaining schedule so it no longer participates in delinquency calculations and accrual engines.
            LoanInstallments::query()
                ->where('loan_id', $loan->id)
                ->where('is_active', true)
                ->where('status', '!=', 'paid')
                ->update([
                    'is_active' => false,
                    'status' => 'written_off',
                ]);

            $this->ledger->recordWriteOff(
                loan: $loan,
                amount: (float) $balances['total_written_off'],
                referenceId: (int) $writeoff->id
            );

            // Post component-based journal entry
            $this->journalPostingEngine->postLoanWriteOff(
                loan: $loan,
                balances: $balances,
                writeOffExpenseAccountId: $accounts['write_off_expense_account_id']
            );

            Log::info('Loan write-off completed successfully', [
                'loan_id' => $loan->id,
                'writeoff_id' => $writeoff->id,
                'subshop_id' => $loan->subshop_id,
                'total_written_off' => $balances['total_written_off'],
            ]);

            return $writeoff;
        });
    }
}
