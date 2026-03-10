<?php

declare(strict_types=1);

namespace App\Services\Loans\Credits;

use App\Models\CustomerCreditBalances;
use App\Models\Loans;
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

    public function refundCredit(int $creditId, int $userId, ?string $notes = null): CustomerCreditBalances
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
            throw new InvalidArgumentException('Only available credits can be refunded.');
        }

        $credit->status = 'refunded';
        $credit->refunded_at = Carbon::now();
        $credit->refunded_by = $userId;
        $credit->notes = $notes;
        $credit->save();

        Log::info('Customer credit refunded', [
            'credit_id' => (int) $credit->id,
            'customer_id' => (int) $credit->customer_id,
            'amount' => (float) $credit->amount,
            'refunded_by' => $userId,
        ]);

        return $credit;
    }
}
