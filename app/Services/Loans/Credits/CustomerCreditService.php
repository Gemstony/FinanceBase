<?php

declare(strict_types=1);

namespace App\Services\Loans\Credits;

use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\CustomerCreditBalances;
use App\Models\CustomerCreditLiabilityAccount;
use App\Models\Loans;
use App\Models\PaymentMethodAccount;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Accounting\VoucherService;
use App\Services\Loans\Account\LoanAccountEngine;
use App\Services\Loans\Repayment\PaymentProcessor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CustomerCreditService
{
    public function __construct(
        private readonly LoanAccountEngine $loanAccountEngine,
        private readonly JournalPostingEngine $journalPostingEngine,
        private readonly VoucherService $voucherService,
    ) {
    }

    public function createCreditFromOverpayment(int $subshopId, int $customerId, ?int $loanId, ?int $paymentId, float $amount, ?string $notes = null): CustomerCreditBalances
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be greater than 0.');
        }

        $credit = CustomerCreditBalances::query()->create([
            'subshop_id' => $subshopId,
            'customer_id' => $customerId,
            'loan_id' => $loanId,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'status' => 'available',
            'notes' => $notes,
        ]);

        Log::info('Customer credit created from overpayment', [
            'credit_id' => (int) $credit->id,
            'subshop_id' => $subshopId,
            'customer_id' => $customerId,
            'loan_id' => $loanId,
            'payment_id' => $paymentId,
            'amount' => $amount,
        ]);

        return $credit;
    }

    public function getBorrowerAvailableCredits(int $subshopId, int $customerId): Builder
    {
        return CustomerCreditBalances::query()
            ->where('subshop_id', $subshopId)
            ->where('customer_id', $customerId)
            ->where('status', 'available');
    }

    public function getBorrowerAvailableCreditBalance(int $subshopId, int $customerId): float
    {
        return (float) $this->getBorrowerAvailableCredits($subshopId, $customerId)->sum('amount');
    }

    public function applyCreditToLoan(int $creditId, int $loanId): CustomerCreditBalances
    {
        $subshopId = (int) session('subshop_id');

        $credit = CustomerCreditBalances::query()
            ->whereKey($creditId)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $credit->subshop_id !== $subshopId) {
            throw new InvalidArgumentException('Invalid subshop for this credit.');
        }

        if ((string) $credit->status !== 'available') {
            throw new InvalidArgumentException('This credit is not available.');
        }

        $loan = Loans::query()->whereKey($loanId)->firstOrFail();
        if ((int) $loan->subshop_id !== $subshopId) {
            throw new InvalidArgumentException('Invalid subshop for target loan.');
        }

        $summary = $this->loanAccountEngine->getLoanAccountSummary($loan);
        $outstanding = (float) ($summary['total_balance'] ?? 0.0);
        $amount = (float) $credit->amount;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be greater than 0.');
        }

        if ($amount > $outstanding) {
            throw new InvalidArgumentException('Credit amount must not exceed loan outstanding balance.');
        }

        $payment = app(PaymentProcessor::class)->processPayment(
            $loan,
            (int) $credit->customer_id,
            $amount,
            'customer_credit',
            null,
            null,
            Carbon::now()->startOfDay(),
            'Applied customer credit #' . (int) $credit->id,
        );

        $credit->status = 'applied';
        $credit->applied_to_loan_id = (int) $loan->id;
        $credit->applied_at = Carbon::now();
        $credit->notes = trim((string) ($credit->notes ?? '') . "\nApplied via payment #" . (int) $payment->id);
        $credit->save();

        Log::info('Customer credit applied to loan', [
            'credit_id' => (int) $credit->id,
            'customer_id' => (int) $credit->customer_id,
            'loan_id' => (int) $loan->id,
            'payment_id' => (int) $payment->id,
            'amount' => (float) $amount,
        ]);

        return $credit;
    }

    /**
     * @param array{refund_method:string, bank_account_id?:int|null, notes?:string|null} $data
     */
    public function refundCredit(int $creditId, int $userId, float $refundAmount, array $data): CustomerCreditBalances
    {
        try {
            Log::info('CustomerCreditService::refundCredit started', [
                'credit_id' => $creditId,
                'user_id' => $userId,
                'refund_amount' => $refundAmount,
                'data' => $data,
            ]);

            $subshopId = (int) session('subshop_id');
            Log::debug('Subshop ID resolved', ['subshop_id' => $subshopId]);

            $refundAmount = round((float) $refundAmount, 2);
            Log::debug('Refund amount validated', ['refund_amount' => $refundAmount]);

            if ($refundAmount <= 0) {
                Log::error('Invalid refund amount', ['refund_amount' => $refundAmount]);
                throw new InvalidArgumentException('Refund amount must be greater than 0.');
            }

            $refundMethod = (string) ($data['refund_method'] ?? '');
            Log::debug('Refund method resolved', ['refund_method' => $refundMethod]);

            if ($refundMethod === '') {
                Log::error('Empty refund method');
                throw new InvalidArgumentException('Refund method is required.');
            }

            $bankAccountId = isset($data['bank_account_id']) && $data['bank_account_id'] ? (int) $data['bank_account_id'] : null;
            $requiresBank = in_array($refundMethod, ['bank_transfer', 'mobile_money'], true);
            Log::debug('Bank account validation', [
                'bank_account_id' => $bankAccountId,
                'requires_bank' => $requiresBank,
            ]);

            if ($requiresBank && !$bankAccountId) {
                Log::error('Missing required bank account', ['refund_method' => $refundMethod]);
                throw new InvalidArgumentException('Bank account is required for this refund method.');
            }

            // Get fixed liability account for this subshop
            Log::debug('Getting liability account', ['subshop_id' => $subshopId]);
            $liabilityAccountId = $this->getCustomerCreditLiabilityAccount($subshopId);
            Log::debug('Liability account resolved', ['liability_account_id' => $liabilityAccountId]);

            $creditCashAccountId = $this->resolveRefundCashAccountId($refundMethod, $bankAccountId, $subshopId);
            Log::debug('Cash account resolved', ['cash_account_id' => $creditCashAccountId]);

            $now = Carbon::now();

            $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
                ->reset()
                ->addDebit($liabilityAccountId, $refundAmount, 'Customer credit refund - liability reduction')
                ->addCredit($creditCashAccountId, $refundAmount, 'Customer credit refund - cash/bank outflow')
                ->getLines();

            Log::debug('Journal lines created', [
                'debit_account_id' => $liabilityAccountId,
                'credit_account_id' => $creditCashAccountId,
                'amount' => $refundAmount,
            ]);

            $credit = CustomerCreditBalances::query()
                ->whereKey($creditId)
                ->lockForUpdate()
                ->firstOrFail();

            Log::debug('Credit loaded', [
                'credit_id' => $credit->id,
                'credit_amount' => $credit->amount,
                'credit_status' => $credit->status,
                'credit_subshop_id' => $credit->subshop_id,
            ]);

            if ((int) $credit->subshop_id !== $subshopId) {
                Log::error('Subshop mismatch', [
                    'credit_subshop_id' => $credit->subshop_id,
                    'session_subshop_id' => $subshopId,
                ]);
                throw new InvalidArgumentException('Invalid subshop for this credit.');
            }

            if ((string) $credit->status !== 'available') {
                Log::error('Invalid credit status', ['credit_status' => $credit->status]);
                throw new InvalidArgumentException('Only available credits can be refunded.');
            }

            $creditAmount = round((float) $credit->amount, 2);
            if ($refundAmount > $creditAmount) {
                Log::error('Refund amount exceeds available', [
                    'refund_amount' => $refundAmount,
                    'available_amount' => $creditAmount,
                ]);
                throw new InvalidArgumentException('Refund amount must not exceed available credit amount.');
            }

            // Partial refund: keep remaining credit available and record refunded portion as its own row.
            if ($refundAmount < $creditAmount) {
                Log::debug('Processing partial refund', [
                    'refund_amount' => $refundAmount,
                    'credit_amount' => $creditAmount,
                ]);

                $remaining = round($creditAmount - $refundAmount, 2);
                if ($remaining <= 0) {
                    throw new InvalidArgumentException('Invalid remaining credit after refund.');
                }

                $credit->amount = $remaining;
                $credit->save();

                $refundedCredit = CustomerCreditBalances::query()->create([
                    'subshop_id' => (int) $credit->subshop_id,
                    'customer_id' => (int) $credit->customer_id,
                    'loan_id' => $credit->loan_id ? (int) $credit->loan_id : null,
                    'payment_id' => $credit->payment_id ? (int) $credit->payment_id : null,
                    'amount' => $refundAmount,
                    'status' => 'refunded',
                    'refunded_at' => $now,
                    'refunded_by' => $userId,
                    'refund_method' => $refundMethod,
                    'bank_account_id' => $bankAccountId,
                    'notes' => $data['notes'] ?? null,
                ]);

                $journal = $this->journalPostingEngine->postJournalEntry(
                    $lines,
                    'customer_credit_refund',
                    (int) $refundedCredit->id,
                    'Customer credit refund #' . (int) $refundedCredit->id
                );

                $this->voucherService->createVoucherFromJournalEntry(
                    $journal,
                    'payment',
                    [
                        'payment_method' => $refundMethod,
                        'bank_account_id' => $bankAccountId,
                        'description' => 'Customer credit refund payment voucher #' . (int) $refundedCredit->id,
                    ]
                );

                Log::info('Customer credit partially refunded', [
                    'original_credit_id' => (int) $credit->id,
                    'refunded_credit_id' => (int) $refundedCredit->id,
                    'customer_id' => (int) $credit->customer_id,
                    'refund_amount' => (float) $refundAmount,
                    'remaining_amount' => (float) $remaining,
                    'refund_method' => $refundMethod,
                    'bank_account_id' => $bankAccountId,
                    'refunded_by' => $userId,
                ]);

                return $refundedCredit;
            }

            // Full refund
            $credit->status = 'refunded';
            $credit->refunded_at = $now;
            $credit->refunded_by = $userId;
            $credit->refund_method = $refundMethod;
            $credit->bank_account_id = $bankAccountId;
            $credit->notes = $data['notes'] ?? null;
            $credit->save();

            $journal = $this->journalPostingEngine->postJournalEntry(
                $lines,
                'customer_credit_refund',
                (int) $credit->id,
                'Customer credit refund #' . (int) $credit->id
            );

            $this->voucherService->createVoucherFromJournalEntry(
                $journal,
                'payment',
                [
                    'payment_method' => $refundMethod,
                    'bank_account_id' => $bankAccountId,
                    'description' => 'Customer credit refund payment voucher #' . (int) $credit->id,
                ]
            );

            Log::info('Customer credit refunded', [
                'credit_id' => (int) $credit->id,
                'customer_id' => (int) $credit->customer_id,
                'amount' => (float) $refundAmount,
                'refund_method' => $refundMethod,
                'bank_account_id' => $bankAccountId,
                'refunded_by' => $userId,
            ]);

            return $credit;
        } catch (\Exception $e) {
            // Log the error and re-throw for controller to handle
            Log::error('CustomerCreditService::refundCredit failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'credit_id' => $creditId,
                'user_id' => $userId,
                'refund_amount' => $refundAmount,
            ]);
            
            // Re-throw to let controller handle with user-friendly message
            throw $e;
        }
    }

    private function resolveRefundCashAccountId(string $refundMethod, ?int $bankAccountId, int $subshopId): int
    {
        if ($bankAccountId) {
            $bank = BankAccounts::query()->whereKey($bankAccountId)->firstOrFail();
            if ((int) $bank->subshop_id !== $subshopId) {
                throw new InvalidArgumentException('Bank account does not belong to this branch.');
            }
            
            $accountId = (int) $bank->chart_of_account_id;
            if ($accountId <= 0) {
                throw new InvalidArgumentException('Bank account is not linked to a chart of account.');
            }
            
            return $accountId;
        }

        // For cash refunds, use payment method mapping to get the correct cash account
        $mapping = PaymentMethodAccount::query()
            ->where('subshop_id', $subshopId)
            ->where('payment_method', $refundMethod)
            ->first();

        if (!$mapping) {
            throw new InvalidArgumentException("Payment method '{$refundMethod}' is not mapped to a GL account.");
        }

        $accountId = (int) $mapping->chart_of_account_id;
        if ($accountId <= 0) {
            throw new InvalidArgumentException("Invalid chart_of_account_id for payment method '{$refundMethod}'.");
        }

        Log::debug('Using payment method mapping', ['account_id' => $accountId]);
        return $accountId;
    }

    /**
     * Get customer credit liability account for a subshop
     */
    public function getCustomerCreditLiabilityAccount(int $subshopId): int
    {
        Log::debug('Getting customer credit liability account', ['subshop_id' => $subshopId]);
        
        $liabilityAccount = CustomerCreditLiabilityAccount::forSubshop($subshopId);
        
        if (!$liabilityAccount) {
            Log::error('Liability account not configured', ['subshop_id' => $subshopId]);
            throw new InvalidArgumentException(
                'Customer credit liability account is not configured for this branch. ' .
                'Please configure it first before processing refunds.'
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
        
        if ((int) $chartAccount->subshop_id !== $subshopId) {
            Log::error('Configured liability account wrong subshop', [
                'account_subshop_id' => $chartAccount->subshop_id,
                'session_subshop_id' => $subshopId,
            ]);
            throw new InvalidArgumentException('Configured liability account does not belong to this branch.');
        }

        Log::debug('Liability account validated', [
            'liability_account_id' => $liabilityAccount->chart_of_account_id,
            'account_name' => $chartAccount->account_name,
        ]);

        return (int) $liabilityAccount->chart_of_account_id;
    }
}
