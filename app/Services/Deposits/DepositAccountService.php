<?php

declare(strict_types=1);

namespace App\Services\Deposits;

use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\DepositAccount;
use App\Models\DepositProduct;
use App\Models\DepositTransaction;
use App\Models\Loans;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Accounting\VoucherService;
use App\Services\Loans\Repayment\PaymentProcessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DepositAccountService
{
    public function __construct(
        private readonly JournalPostingEngine $journalPostingEngine,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly VoucherService $voucherService,
    ) {
    }

    public function createAccount(int $customerId, int $depositProductId, ?string $accountNumber = null): DepositAccount
    {
        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active subshop context is required to create a deposit account.');
        }

        return DB::transaction(function () use ($subshopId, $customerId, $depositProductId, $accountNumber) {
            $product = DepositProduct::query()
                ->whereKey($depositProductId)
                ->where('subshop_id', $subshopId)
                ->firstOrFail();

            if (!(bool) $product->is_active) {
                throw new InvalidArgumentException('Deposit product is not active.');
            }

            $accountNumber = $accountNumber ?: $this->generateNextAccountNumber('SAV');

            $account = DepositAccount::query()->create([
                'subshop_id' => $subshopId,
                'customer_id' => $customerId,
                'deposit_product_id' => (int) $product->id,
                'account_number' => $accountNumber,
                'balance' => 0,
                'status' => 'active',
                'opened_at' => Carbon::now(),
            ]);

            Log::info('Deposit account created', [
                'deposit_account_id' => (int) $account->id,
                'customer_id' => (int) $customerId,
                'deposit_product_id' => (int) $product->id,
                'account_number' => (string) $accountNumber,
                'subshop_id' => (int) $subshopId,
                'created_by' => auth()->id(),
            ]);

            return $account;
        });
    }

    public function deposit(DepositAccount $account, float $amount, string $paymentMethod, ?int $bankAccountId, int $liabilityAccountId, ?string $reference = null, ?string $notes = null): DepositTransaction
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than 0.');
        }

        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active subshop context is required to deposit.');
        }

        return DB::transaction(function () use ($subshopId, $account, $amount, $paymentMethod, $bankAccountId, $liabilityAccountId, $reference, $notes) {
            $account = DepositAccount::query()->whereKey((int) $account->id)->lockForUpdate()->firstOrFail();

            if ((int) $account->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $account->status === 'closed') {
                throw new InvalidArgumentException('Cannot transact on a closed account.');
            }

            $newBalance = round((float) $account->balance + $amount, 2);

            $account->balance = $newBalance;
            $account->save();

            $tx = $this->createTransaction($account, 'deposit', $amount, $newBalance, $reference, $notes, $paymentMethod, $bankAccountId);

            $creditAccountId = 1;
            if ($bankAccountId) {
                $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
                $linked = (int) ($bank?->chart_of_account_id ?? 0);
                if ($linked > 0) {
                    $creditAccountId = $linked;
                }
            }

            $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
                ->reset()
                ->addDebit($creditAccountId, $amount, 'Customer deposit received – cash/bank in')
                ->addCredit($liabilityAccountId, $amount, 'Customer deposits liability – credit')
                ->getLines();

            $journal = $this->journalPostingEngine->postJournalEntry(
                $lines,
                'deposit_received',
                (int) $tx->id,
                'Customer deposit received – ' . $account->account_number
            );

            $this->voucherService->createVoucherFromJournalEntry(
                $journal,
                'receipt',
                [
                    'payment_method' => $paymentMethod,
                    'bank_account_id' => $bankAccountId,
                    'description' => 'Customer deposit received – ' . $account->account_number,
                ]
            );

            Log::info('Deposit made', [
                'deposit_account_id' => (int) $account->id,
                'amount' => (float) $amount,
                'balance_after' => (float) $newBalance,
                'payment_method' => (string) $paymentMethod,
                'bank_account_id' => $bankAccountId,
                'reference' => $reference,
                'created_by' => auth()->id(),
            ]);

            return $tx;
        });
    }

    public function withdraw(DepositAccount $account, float $amount, string $paymentMethod, ?int $bankAccountId, int $liabilityAccountId, ?string $reference = null, ?string $notes = null): DepositTransaction
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be greater than 0.');
        }

        $subshopId = (int) session('subshop_id');

        return DB::transaction(function () use ($subshopId, $account, $amount, $paymentMethod, $bankAccountId, $liabilityAccountId, $reference, $notes) {
            $account = DepositAccount::query()
                ->with('depositProduct')
                ->whereKey((int) $account->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $account->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $account->status !== 'active') {
                throw new InvalidArgumentException('Account must be active to withdraw.');
            }

            $minimumBalance = (float) ($account->depositProduct?->minimum_balance ?? 0);

            $currentBalance = (float) $account->balance;
            if ($amount > $currentBalance) {
                throw new InvalidArgumentException('Insufficient balance.');
            }

            $newBalance = round($currentBalance - $amount, 2);
            if ($newBalance < $minimumBalance) {
                throw new InvalidArgumentException('Withdrawal would reduce balance below minimum balance.');
            }

            $account->balance = $newBalance;
            $account->save();

            $tx = $this->createTransaction($account, 'withdrawal', $amount, $newBalance, $reference, $notes, $paymentMethod, $bankAccountId);

            $creditAccountId = 1;
            if ($bankAccountId) {
                $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
                $linked = (int) ($bank?->chart_of_account_id ?? 0);
                if ($linked > 0) {
                    $creditAccountId = $linked;
                }
            }

            $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
                ->reset()
                ->addDebit($liabilityAccountId, $amount, 'Customer deposits liability – withdrawal')
                ->addCredit($creditAccountId, $amount, 'Customer deposit withdrawal – cash/bank out')
                ->getLines();

            $journal = $this->journalPostingEngine->postJournalEntry(
                $lines,
                'deposit_withdrawal',
                (int) $tx->id,
                'Customer deposit withdrawal – ' . $account->account_number
            );

            $this->voucherService->createVoucherFromJournalEntry(
                $journal,
                'payment',
                [
                    'payment_method' => $paymentMethod,
                    'bank_account_id' => $bankAccountId,
                    'description' => 'Customer deposit withdrawal – ' . $account->account_number,
                ]
            );

            Log::info('Withdrawal made', [
                'deposit_account_id' => (int) $account->id,
                'amount' => (float) $amount,
                'balance_after' => (float) $newBalance,
                'payment_method' => (string) $paymentMethod,
                'bank_account_id' => $bankAccountId,
                'reference' => $reference,
                'created_by' => auth()->id(),
            ]);

            return $tx;
        });
    }

    public function transfer(DepositAccount $fromAccount, DepositAccount $toAccount, float $amount, ?string $reference = null, ?string $notes = null): array
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Transfer amount must be greater than 0.');
        }

        $subshopId = (int) session('subshop_id');

        return DB::transaction(function () use ($subshopId, $fromAccount, $toAccount, $amount, $reference, $notes) {
            $from = DepositAccount::query()->whereKey((int) $fromAccount->id)->lockForUpdate()->firstOrFail();
            $to = DepositAccount::query()->whereKey((int) $toAccount->id)->lockForUpdate()->firstOrFail();

            if ((int) $from->subshop_id !== $subshopId || (int) $to->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $from->status !== 'active') {
                throw new InvalidArgumentException('Source account must be active to transfer.');
            }

            if ((string) $to->status === 'closed') {
                throw new InvalidArgumentException('Cannot transfer into a closed account.');
            }

            if ((float) $from->balance < $amount) {
                throw new InvalidArgumentException('Insufficient balance.');
            }

            $fromNew = round((float) $from->balance - $amount, 2);
            $toNew = round((float) $to->balance + $amount, 2);

            $from->balance = $fromNew;
            $from->save();

            $to->balance = $toNew;
            $to->save();

            $ref = $reference ?: ('TRF-' . (string) Carbon::now()->format('YmdHis'));

            $fromTx = $this->createTransaction($from, 'transfer', $amount, $fromNew, $ref, $notes);
            $toTx = $this->createTransaction($to, 'transfer', $amount, $toNew, $ref, $notes);

            Log::info('Deposit transfer completed', [
                'from_deposit_account_id' => (int) $from->id,
                'to_deposit_account_id' => (int) $to->id,
                'amount' => (float) $amount,
                'reference' => (string) $ref,
                'created_by' => auth()->id(),
            ]);

            return [$fromTx, $toTx];
        });
    }

    public function payLoanInstallment(DepositAccount $account, Loans $loan, float $amount, ?string $reference = null, ?string $notes = null): DepositTransaction
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than 0.');
        }

        $subshopId = (int) session('subshop_id');

        return DB::transaction(function () use ($subshopId, $account, $loan, $amount, $reference, $notes) {
            $account = DepositAccount::query()
                ->with('depositProduct')
                ->whereKey((int) $account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $loan = Loans::query()->whereKey((int) $loan->id)->lockForUpdate()->firstOrFail();

            if ((int) $account->subshop_id !== $subshopId || (int) $loan->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $account->status !== 'active') {
                throw new InvalidArgumentException('Account must be active to pay a loan.');
            }

            if ((int) $account->customer_id !== (int) $loan->customer_id) {
                throw new InvalidArgumentException('Deposit account borrower must match the loan borrower.');
            }

            $minimumBalance = (float) ($account->depositProduct?->minimum_balance ?? 0);

            $currentBalance = (float) $account->balance;
            if ($amount > $currentBalance) {
                throw new InvalidArgumentException('Insufficient balance.');
            }

            $newBalance = round($currentBalance - $amount, 2);
            if ($newBalance < $minimumBalance) {
                throw new InvalidArgumentException('Payment would reduce balance below minimum balance.');
            }

            $account->balance = $newBalance;
            $account->save();

            $tx = $this->createTransaction($account, 'loan_payment', $amount, $newBalance, $reference, $notes);

            $payment = $this->paymentProcessor->processPayment(
                $loan,
                (int) $account->customer_id,
                $amount,
                'savings',
                null,
                $reference,
                Carbon::now()->startOfDay(),
                $notes ? ('Paid from savings account ' . $account->account_number . "\n" . $notes) : ('Paid from savings account ' . $account->account_number),
            );

            $liabilityAccountId = $this->resolveCustomerDepositsLiabilityAccountId($subshopId);

            $lines = app(\App\Services\Accounting\LoanAccountingMapper::class)
                ->buildDepositLoanPaymentEntry($loan, $amount, $liabilityAccountId);

            $this->journalPostingEngine->postJournalEntry(
                $lines,
                'deposit_loan_payment',
                (int) $tx->id,
                'Loan installment paid from savings – ' . $account->account_number . ' – ' . $loan->loan_code
            );

            Log::info('Loan installment paid from savings', [
                'deposit_account_id' => (int) $account->id,
                'loan_id' => (int) $loan->id,
                'payment_id' => (int) $payment->id,
                'amount' => (float) $amount,
                'balance_after' => (float) $newBalance,
                'reference' => $reference,
                'created_by' => auth()->id(),
            ]);

            return $tx;
        });
    }

    public function getCustomerAccounts(int $customerId)
    {
        $subshopId = (int) session('subshop_id');

        return DepositAccount::query()
            ->with(['depositProduct', 'depositTransactions' => fn($q) => $q->latest()->limit(5)])
            ->where('subshop_id', $subshopId)
            ->where('customer_id', $customerId)
            ->orderBy('opened_at', 'desc');
    }

    private function createTransaction(DepositAccount $account, string $type, float $amount, float $balanceAfter, ?string $reference, ?string $notes, ?string $paymentMethod = null, ?int $bankAccountId = null): DepositTransaction
    {
        return DepositTransaction::query()->create([
            'deposit_account_id' => (int) $account->id,
            'transaction_type' => $type,
            'payment_method' => $paymentMethod,
            'bank_account_id' => $bankAccountId,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reference' => $reference,
            'notes' => $notes,
            'created_by' => auth()->id(),
            'created_at' => Carbon::now(),
        ]);
    }

    private function generateNextAccountNumber(string $prefix): string
    {
        do {
            $candidate = $prefix . (string) Carbon::now()->format('Y') . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (DepositAccount::query()->where('account_number', $candidate)->exists());

        return $candidate;
    }

    private function resolveCustomerDepositsLiabilityAccountId(int $subshopId): int
    {
        $account = ChartsOfAccount::query()
            ->where('subshop_id', $subshopId)
            ->where('account_name', 'like', '%Customer Deposits%')
            ->where('is_active', true)
            ->first();

        if (!$account) {
            throw new \RuntimeException('Customer Deposits liability account not found or inactive for subshop ' . $subshopId);
        }

        return (int) $account->id;
    }
}
