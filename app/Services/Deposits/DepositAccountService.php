<?php

declare(strict_types=1);

namespace App\Services\Deposits;

use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\CustomerDepositLiabilityAccount;
use App\Models\DepositAccount;
use App\Models\DepositProduct;
use App\Models\DepositTransaction;
use App\Models\Loans;
use App\Models\PaymentMethodAccount;
use App\Models\SubShop;
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
        // Get shop-level deposit products (accessible by all subshops under the same shop)
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active subshop context is required to create a deposit account.');
        }

        return DB::transaction(function () use ($subshopId, $customerId, $depositProductId, $accountNumber, $shopSubshopIds) {
            $product = DepositProduct::query()
                ->whereKey($depositProductId)
                ->whereIn('subshop_id', $shopSubshopIds)
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

    public function deposit(DepositAccount $account, float $amount, string $paymentMethod, ?int $bankAccountId, ?string $reference = null, ?string $notes = null): DepositTransaction
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than 0.');
        }

        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active subshop context is required to deposit.');
        }

        // Get shop-level scope (accessible by all subshops under the same shop)
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        // Get and validate liability account from configuration
        $liabilityAccountId = $this->getCustomerDepositsLiabilityAccount($subshopId);

        // Resolve cash/bank account with proper validation (Asset Class 1)
        $cashAccountId = $this->resolvePaymentSourceAccountId($paymentMethod, $bankAccountId, $subshopId);

        Log::info('Processing deposit', [
            'subshop_id' => $subshopId,
            'shop_id' => $shopId,
            'deposit_account_id' => $account->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'bank_account_id' => $bankAccountId,
            'cash_account_id' => $cashAccountId,
            'liability_account_id' => $liabilityAccountId,
        ]);

        return DB::transaction(function () use ($subshopId, $shopId, $shopSubshopIds, $account, $amount, $paymentMethod, $bankAccountId, $liabilityAccountId, $cashAccountId, $reference, $notes) {
            $account = DepositAccount::query()->whereKey((int) $account->id)->lockForUpdate()->firstOrFail();

            if (!$shopSubshopIds->contains($account->subshop_id)) {
                abort(403);
            }

            if ((string) $account->status === 'closed') {
                throw new InvalidArgumentException('Cannot transact on a closed account.');
            }

            $newBalance = round((float) $account->balance + $amount, 2);

            $account->balance = $newBalance;
            $account->save();

            $tx = $this->createTransaction($account, 'deposit', $amount, $newBalance, $reference, $notes, $paymentMethod, $bankAccountId);

            $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
                ->reset()
                ->addDebit($cashAccountId, $amount, 'Customer deposit received – cash/bank in')
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

            Log::info('Deposit completed successfully', [
                'deposit_account_id' => (int) $account->id,
                'transaction_id' => (int) $tx->id,
                'amount' => (float) $amount,
                'balance_after' => (float) $newBalance,
                'cash_account_id' => $cashAccountId,
                'liability_account_id' => $liabilityAccountId,
            ]);

            return $tx;
        });
    }

    public function withdraw(DepositAccount $account, float $amount, string $paymentMethod, ?int $bankAccountId, ?string $reference = null, ?string $notes = null): DepositTransaction
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be greater than 0.');
        }

        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active subshop context is required to withdraw.');
        }

        // Get shop-level scope (accessible by all subshops under the same shop)
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        // Get and validate liability account from configuration
        $liabilityAccountId = $this->getCustomerDepositsLiabilityAccount($subshopId);

        // Resolve cash/bank account with proper validation (Asset Class 1)
        $cashAccountId = $this->resolvePaymentSourceAccountId($paymentMethod, $bankAccountId, $subshopId);

        Log::info('Processing withdrawal', [
            'subshop_id' => $subshopId,
            'shop_id' => $shopId,
            'deposit_account_id' => $account->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'bank_account_id' => $bankAccountId,
            'cash_account_id' => $cashAccountId,
            'liability_account_id' => $liabilityAccountId,
        ]);

        return DB::transaction(function () use ($subshopId, $shopId, $shopSubshopIds, $account, $amount, $paymentMethod, $bankAccountId, $liabilityAccountId, $cashAccountId, $reference, $notes) {
            $account = DepositAccount::query()
                ->with('depositProduct')
                ->whereKey((int) $account->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$shopSubshopIds->contains($account->subshop_id)) {
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

            $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
                ->reset()
                ->addDebit($liabilityAccountId, $amount, 'Customer deposits liability – withdrawal')
                ->addCredit($cashAccountId, $amount, 'Customer deposit withdrawal – cash/bank out')
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

            Log::info('Withdrawal completed successfully', [
                'deposit_account_id' => (int) $account->id,
                'transaction_id' => (int) $tx->id,
                'amount' => (float) $amount,
                'balance_after' => (float) $newBalance,
                'cash_account_id' => $cashAccountId,
                'liability_account_id' => $liabilityAccountId,
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

        // Get shop-level scope (accessible by all subshops under the same shop)
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        return DB::transaction(function () use ($subshopId, $shopId, $shopSubshopIds, $fromAccount, $toAccount, $amount, $reference, $notes) {
            $from = DepositAccount::query()->whereKey((int) $fromAccount->id)->lockForUpdate()->firstOrFail();
            $to = DepositAccount::query()->whereKey((int) $toAccount->id)->lockForUpdate()->firstOrFail();

            if (!$shopSubshopIds->contains($from->subshop_id) || !$shopSubshopIds->contains($to->subshop_id)) {
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

        // Get shop-level scope (accessible by all subshops under the same shop)
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        return DB::transaction(function () use ($subshopId, $shopId, $shopSubshopIds, $account, $loan, $amount, $reference, $notes) {
            $account = DepositAccount::query()
                ->with('depositProduct')
                ->whereKey((int) $account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $loan = Loans::query()->whereKey((int) $loan->id)->lockForUpdate()->firstOrFail();

            if (!$shopSubshopIds->contains($account->subshop_id) || !$shopSubshopIds->contains($loan->subshop_id)) {
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

            // Get liability account for passing to payment processor as source account
            $liabilityAccountId = $this->getCustomerDepositsLiabilityAccount($subshopId);

            Log::info('Processing loan payment from deposit account', [
                'deposit_account_id' => (int) $account->id,
                'loan_id' => (int) $loan->id,
                'amount' => (float) $amount,
                'liability_account_id' => $liabilityAccountId,
            ]);

            // PaymentProcessor handles the journal entry posting with proper allocation
            // (principal/interest/penalty) debiting the deposit liability account
            $payment = $this->paymentProcessor->processPayment(
                $loan,
                (int) $account->customer_id,
                $amount,
                'savings',
                null,
                $reference,
                Carbon::now()->startOfDay(),
                $notes ? ('Paid from savings account ' . $account->account_number . "\n" . $notes) : ('Paid from savings account ' . $account->account_number),
                null, // Use default strategy
                $liabilityAccountId, // Source account override for deposit liability
            );

            Log::info('Loan installment paid from savings successfully', [
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

        // Get shop-level scope (accessible by all subshops under the same shop)
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        return DepositAccount::query()
            ->with(['depositProduct', 'depositTransactions' => fn($q) => $q->latest()->limit(5)])
            ->whereIn('subshop_id', $shopSubshopIds)
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
        return $this->getCustomerDepositsLiabilityAccount($subshopId);
    }

    /**
     * Get customer deposits liability account for a subshop (uses shop-level configuration)
     */
    public function getCustomerDepositsLiabilityAccount(int $subshopId): int
    {
        Log::debug('Getting customer deposits liability account', ['subshop_id' => $subshopId]);

        // Get the shop ID from the subshop for shop-level configuration
        $subshop = SubShop::find($subshopId);
        if (!$subshop) {
            Log::error('Subshop not found', ['subshop_id' => $subshopId]);
            throw new InvalidArgumentException('Invalid branch selected.');
        }

        $shopId = $subshop->shop_id;
        Log::debug('Resolved shop ID for liability account', ['shop_id' => $shopId]);

        $liabilityAccount = CustomerDepositLiabilityAccount::forShop($shopId);

        if (!$liabilityAccount) {
            Log::error('Customer deposits liability account not configured', ['shop_id' => $shopId, 'subshop_id' => $subshopId]);
            throw new InvalidArgumentException(
                'Customer deposits liability account is not configured for this shop. ' .
                'Please configure it first before processing deposits or withdrawals.'
            );
        }

        Log::debug('Liability account found', [
            'liability_account_id' => $liabilityAccount->id,
            'chart_of_account_id' => $liabilityAccount->chart_of_account_id,
        ]);

        // Validate that account is still a liability account and active
        $chartAccount = ChartsOfAccount::query()->whereKey($liabilityAccount->chart_of_account_id)->first();

        if (!$chartAccount) {
            Log::error('Configured liability account no longer exists', [
                'liability_account_id' => $liabilityAccount->chart_of_account_id,
            ]);
            throw new InvalidArgumentException('Configured liability account no longer exists.');
        }

        if ((int) $chartAccount->accountClass->code !== 2) {
            Log::error('Configured liability account not liability class', [
                'account_class_code' => $chartAccount->accountClass->code,
            ]);
            throw new InvalidArgumentException('Configured liability account is not a liability account (Account Class 2).');
        }

        if (!$chartAccount->is_active) {
            Log::error('Configured liability account not active', [
                'liability_account_id' => $liabilityAccount->chart_of_account_id,
            ]);
            throw new InvalidArgumentException('Configured liability account is not active.');
        }

        // Validate account belongs to any subshop under the same shop (shop-level scope)
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');
        if (!$shopSubshopIds->contains($chartAccount->subshop_id)) {
            Log::error('Configured liability account does not belong to this shop', [
                'account_subshop_id' => $chartAccount->subshop_id,
                'shop_id' => $shopId,
            ]);
            throw new InvalidArgumentException('Configured liability account does not belong to this shop.');
        }

        Log::debug('Liability account validated', [
            'liability_account_id' => $liabilityAccount->chart_of_account_id,
            'account_name' => $chartAccount->account_name,
        ]);

        return (int) $liabilityAccount->chart_of_account_id;
    }

    /**
     * Resolve payment source account (cash/bank) with proper validation
     * Returns Asset Class 1 account ID
     */
    private function resolvePaymentSourceAccountId(string $paymentMethod, ?int $bankAccountId, int $subshopId): int
    {
        Log::debug('Resolving payment source account', [
            'payment_method' => $paymentMethod,
            'bank_account_id' => $bankAccountId,
            'subshop_id' => $subshopId,
        ]);

        // If bank account provided, use its chart of account
        if ($bankAccountId) {
            $bank = BankAccounts::query()->whereKey($bankAccountId)->first();

            if (!$bank) {
                Log::error('Bank account not found', ['bank_account_id' => $bankAccountId]);
                throw new InvalidArgumentException('Selected bank account not found.');
            }

            if ((int) $bank->subshop_id !== $subshopId) {
                Log::error('Bank account wrong subshop', [
                    'bank_subshop_id' => $bank->subshop_id,
                    'session_subshop_id' => $subshopId,
                ]);
                throw new InvalidArgumentException('Selected bank account does not belong to this branch.');
            }

            if (!$bank->is_active) {
                Log::error('Bank account not active', ['bank_account_id' => $bankAccountId]);
                throw new InvalidArgumentException('Selected bank account is not active.');
            }

            $accountId = (int) $bank->chart_of_account_id;
            if ($accountId <= 0) {
                Log::error('Bank account missing chart_of_account_id', ['bank_account_id' => $bankAccountId]);
                throw new InvalidArgumentException('Bank account is not linked to a chart of account.');
            }

            // Validate that bank account's COA is Asset class (Class 1)
            $chartAccount = ChartsOfAccount::query()->whereKey($accountId)->first();
            if (!$chartAccount) {
                Log::error('Bank linked chart account not found', ['chart_account_id' => $accountId]);
                throw new InvalidArgumentException('Bank account linked chart of account not found.');
            }

            if ((int) $chartAccount->accountClass->code !== 1) {
                Log::error('Bank linked chart account not Asset class', [
                    'chart_account_id' => $accountId,
                    'account_class_code' => $chartAccount->accountClass->code,
                ]);
                throw new InvalidArgumentException('Bank account must be linked to an Asset account (Class 1).');
            }

            Log::debug('Using bank account mapping', [
                'bank_account_id' => $bankAccountId,
                'chart_account_id' => $accountId,
            ]);

            return $accountId;
        }

        // Look up payment method to GL account mapping for cash/mobile_money/etc.
        $method = trim(strtolower($paymentMethod));
        if ($method === '') {
            Log::error('Empty payment method');
            throw new InvalidArgumentException('Payment method is required to resolve payment account.');
        }

        $mapping = PaymentMethodAccount::query()
            ->where('subshop_id', $subshopId)
            ->where('payment_method', $method)
            ->first();

        if (!$mapping) {
            Log::error('Payment method account mapping not found', [
                'subshop_id' => $subshopId,
                'payment_method' => $method,
            ]);
            throw new InvalidArgumentException("Payment method '{$paymentMethod}' is not mapped to a GL account. Please configure it in Payment Method Accounts.");
        }

        $accountId = (int) $mapping->chart_of_account_id;
        if ($accountId <= 0) {
            Log::error('Invalid chart_of_account_id in payment method mapping', [
                'payment_method' => $method,
                'mapping_id' => $mapping->id,
            ]);
            throw new InvalidArgumentException("Invalid chart of account for payment method '{$paymentMethod}'.");
        }

        // Validate that mapped COA is Asset class (Class 1)
        $chartAccount = ChartsOfAccount::query()->whereKey($accountId)->first();
        if (!$chartAccount) {
            Log::error('Payment method linked chart account not found', [
                'chart_account_id' => $accountId,
                'payment_method' => $method,
            ]);
            throw new InvalidArgumentException('Payment method linked chart of account not found.');
        }

        if ((int) $chartAccount->accountClass->code !== 1) {
            Log::error('Payment method linked chart account not Asset class', [
                'chart_account_id' => $accountId,
                'payment_method' => $method,
                'account_class_code' => $chartAccount->accountClass->code,
            ]);
            throw new InvalidArgumentException('Payment method must be mapped to an Asset account (Class 1).');
        }

        if (!$chartAccount->is_active) {
            Log::error('Payment method linked chart account not active', [
                'chart_account_id' => $accountId,
                'payment_method' => $method,
            ]);
            throw new InvalidArgumentException('Payment method linked chart of account is not active.');
        }

        if ((int) $chartAccount->subshop_id !== $subshopId) {
            Log::error('Payment method linked chart account wrong subshop', [
                'chart_account_id' => $accountId,
                'account_subshop_id' => $chartAccount->subshop_id,
                'session_subshop_id' => $subshopId,
            ]);
            throw new InvalidArgumentException('Payment method linked chart of account does not belong to this branch.');
        }

        Log::debug('Using payment method mapping', [
            'payment_method' => $method,
            'chart_account_id' => $accountId,
        ]);

        return $accountId;
    }
}
