<?php

declare(strict_types=1);

namespace App\Services\Loans\Credits;

use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\CustomerCreditBalances;
use App\Models\Loans;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Accounting\VoucherService;
use App\Services\Loans\Account\LoanAccountEngine;
use App\Services\Loans\Repayment\PaymentProcessor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
     * @param array{refund_method:string, bank_account_id?:int|null, liability_account_id?:int, notes?:string|null} $data
     */
    public function refundCredit(int $creditId, int $userId, float $refundAmount, array $data): CustomerCreditBalances
    {
        $subshopId = (int) session('subshop_id');

        $refundAmount = round((float) $refundAmount, 2);
        if ($refundAmount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than 0.');
        }

        $refundMethod = (string) ($data['refund_method'] ?? '');
        if ($refundMethod === '') {
            throw new InvalidArgumentException('Refund method is required.');
        }

        $bankAccountId = isset($data['bank_account_id']) && $data['bank_account_id'] ? (int) $data['bank_account_id'] : null;
        $requiresBank = in_array($refundMethod, ['bank_transfer', 'mobile_money'], true);
        if ($requiresBank && !$bankAccountId) {
            throw new InvalidArgumentException('Bank account is required for this refund method.');
        }

        $liabilityAccountId = !empty($data['liability_account_id']) ? (int) $data['liability_account_id'] : 0;
        if ($liabilityAccountId <= 0) {
            throw new InvalidArgumentException('Customer credit liability account is required.');
        }

        $credit = CustomerCreditBalances::query()
            ->whereKey($creditId)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $credit->subshop_id !== $subshopId) {
            throw new InvalidArgumentException('Invalid subshop for this credit.');
        }

        if ((string) $credit->status !== 'available') {
            throw new InvalidArgumentException('Only available credits can be refunded.');
        }

        $creditAmount = round((float) $credit->amount, 2);
        if ($refundAmount > $creditAmount) {
            throw new InvalidArgumentException('Refund amount must not exceed available credit amount.');
        }

        if ($bankAccountId) {
            $bankAccount = BankAccounts::query()->whereKey($bankAccountId)->firstOrFail();
            if ((int) $bankAccount->subshop_id !== $subshopId) {
                throw new InvalidArgumentException('Selected bank account does not belong to this branch.');
            }
        }

        $liabilityAccount = ChartsOfAccount::query()->whereKey($liabilityAccountId)->firstOrFail();
        if ((int) $liabilityAccount->subshop_id !== $subshopId) {
            throw new InvalidArgumentException('Selected liability account does not belong to this branch.');
        }

        $creditCashAccountId = $this->resolveRefundCashAccountId($refundMethod, $bankAccountId);

        $now = Carbon::now();

        $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
            ->reset()
            ->addDebit($liabilityAccountId, $refundAmount, 'Customer credit refund – liability reduction')
            ->addCredit($creditCashAccountId, $refundAmount, 'Customer credit refund – cash/bank outflow')
            ->getLines();

        // Partial refund: keep remaining credit available and record refunded portion as its own row.
        if ($refundAmount < $creditAmount) {
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
    }

    private function resolveRefundCashAccountId(string $refundMethod, ?int $bankAccountId): int
    {
        if ($bankAccountId) {
            $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
            $linked = (int) ($bank?->chart_of_account_id ?? 0);
            if ($linked > 0) {
                return $linked;
            }
        }

        return 1;
    }
}
