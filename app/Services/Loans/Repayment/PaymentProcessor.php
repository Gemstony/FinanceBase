<?php

declare(strict_types=1);

namespace App\Services\Loans\Repayment;

use App\Jobs\InitiateAzamPayPaymentJob;
use App\Models\BankAccounts;
use App\Models\LoanInstallmentPayments;
use App\Models\LoanInstallments;
use App\Models\LoanPaymentAllocations;
use App\Models\LoanPayments;
use App\Models\Loans;
use App\Models\PaymentLog;
use App\Models\PaymentTransaction;
use App\Models\SubShop;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Accounting\VoucherService;
use App\Services\Loans\Account\LoanAccountEngine;
use App\Services\Loans\Credits\CustomerCreditService;
use App\Services\Loans\Ledger\LoanTransactionLedger;
use App\Services\Sms\SmsManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentProcessor
{
    public function __construct(
        private readonly LoanRepaymentValidator $validator,
        private readonly LoanAccountEngine $loanAccountEngine,
        private readonly LoanTransactionLedger $ledger,
        private readonly JournalPostingEngine $accounting,
        private readonly CustomerCreditService $customerCreditService,
        private readonly VoucherService $voucherService,
    ) {}

    public function processMobilePayment(
        Loans $loan,
        ?int $payerCustomerId,
        float $paymentAmount,
        Carbon $paymentDate,
        string $phoneNumber,
        string $provider,
        ?string $notes = null,
    ): array {
        $resolvedCustomerId = (int) ($loan->customer_id ?: $payerCustomerId);
        if ($resolvedCustomerId <= 0) {
            throw new InvalidArgumentException('Payer is required for this loan.');
        }

        $subshopId = (int) $loan->subshop_id;
        $shopId = (int) SubShop::where('id', $subshopId)->value('shop_id') ?? 0;
        if ($shopId <= 0) {
            throw new InvalidArgumentException('Shop configuration not found for this loan.');
        }

        $externalId = 'LR-'.uniqid().'-'.time();

        $payment = LoanPayments::create([
            'loan_id' => (int) $loan->id,
            'customer_id' => $resolvedCustomerId,
            'user_id' => auth()->id(),
            'amount' => $paymentAmount,
            'payment_date' => $paymentDate->toDateString(),
            'payment_method' => 'azampay',
            'reference_number' => $externalId,
            'notes' => $notes,
            'status' => 'pending',
            'external_id' => $externalId,
            'phone' => $phoneNumber,
            'provider' => $provider,
            'transaction_reference' => $externalId,
        ]);

        $transaction = PaymentTransaction::create([
            'shop_id' => $shopId,
            'subshop_id' => $subshopId,
            'customer_id' => $resolvedCustomerId,
            'loan_id' => (int) $loan->id,
            'reference' => $externalId,
            'provider' => 'azampay',
            'channel' => 'mobile',
            'amount' => $paymentAmount,
            'phone' => $phoneNumber,
            'status' => 'pending',
            'external_id' => $externalId,
            'meta' => [
                'loan_payment_id' => $payment->id,
                'provider' => $provider,
            ],
        ]);

        PaymentLog::log(
            (int) $transaction->id,
            'azampay',
            [
                'loan_id' => (int) $loan->id,
                'payment_id' => $payment->id,
                'amount' => $paymentAmount,
                'phone' => $phoneNumber,
                'provider' => $provider,
                'external_id' => $externalId,
            ],
            null,
            'initiated'
        );

        Log::info('Payment created (pending), dispatching AzamPay job', [
            'payment_id' => $payment->id,
            'loan_id' => $loan->id,
            'external_id' => $externalId,
            'amount' => $paymentAmount,
            'transaction_id' => $transaction->id,
        ]);

        InitiateAzamPayPaymentJob::dispatch(
            $shopId,
            (int) $payment->id,
            $phoneNumber,
            $paymentAmount,
            $provider,
            $externalId
        );

        return [
            'payment' => $payment,
            'status' => 'pending',
            'message' => 'Payment request sent. Please complete on your phone.',
        ];
    }

    public function processPayment(
        Loans $loan,
        ?int $payerCustomerId,
        float $paymentAmount,
        string $paymentMethod,
        ?int $bankAccountId,
        ?string $transactionReference,
        Carbon $paymentDate,
        ?string $notes = null,
        ?object $strategy = null,
    ): LoanPayments {
        $strategy = $strategy ?: new PenaltyFirstStrategy;

        $this->validator->validate($loan, $payerCustomerId, $paymentAmount, $transactionReference, $paymentDate);

        $bankAccountId = $bankAccountId ? (int) $bankAccountId : null;
        $requiresBank = ! in_array($paymentMethod, ['cash', 'customer_credit', 'savings'], true);
        if ($requiresBank && ! $bankAccountId) {
            throw new InvalidArgumentException('Bank account is required for this payment method.');
        }

        return DB::transaction(function () use (
            $loan,
            $payerCustomerId,
            $paymentAmount,
            $paymentMethod,
            $bankAccountId,
            $transactionReference,
            $paymentDate,
            $notes,
            $strategy
        ) {
            $loan = Loans::query()->whereKey((int) $loan->id)->lockForUpdate()->firstOrFail();

            if ($bankAccountId) {
                $bankAccount = BankAccounts::query()
                    ->whereKey($bankAccountId)
                    ->firstOrFail();

                if ((int) $bankAccount->subshop_id !== (int) $loan->subshop_id) {
                    throw new InvalidArgumentException('Selected bank account does not belong to this branch.');
                }
            }

            $resolvedCustomerId = (int) ($loan->customer_id ?: $payerCustomerId);
            if ($resolvedCustomerId <= 0) {
                throw new InvalidArgumentException('Payer is required for this loan.');
            }

            $allocator = new PaymentAllocator($strategy);
            $allocations = $allocator->allocatePayment($loan, $paymentAmount);

            if (empty($allocations)) {
                throw new InvalidArgumentException('Unable to allocate payment amount.');
            }

            $payment = LoanPayments::create([
                'loan_id' => (int) $loan->id,
                'customer_id' => $resolvedCustomerId,
                'user_id' => auth()->id(),
                'amount' => $paymentAmount,
                'payment_date' => $paymentDate->toDateString(),
                'payment_method' => $paymentMethod,
                'reference_number' => $transactionReference,
                'notes' => $notes,
                'status' => 'confirmed',
            ]);

            $principalTotal = 0.0;
            $interestTotal = 0.0;
            $feeTotal = 0.0;
            $penaltyTotal = 0.0;
            $allocatedTotal = 0.0;

            foreach ($allocations as $row) {
                $installment = LoanInstallments::query()
                    ->whereKey((int) $row['installment_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $principal = (float) ($row['principal_paid'] ?? 0.0);
                $interest = (float) ($row['interest_paid'] ?? 0.0);
                $fee = (float) ($row['fee_paid'] ?? 0.0);
                $penalty = (float) ($row['penalty_paid'] ?? 0.0);
                $total = (float) ($row['total'] ?? 0.0);

                LoanPaymentAllocations::create([
                    'loan_payment_id' => (int) $payment->id,
                    'loan_installment_id' => (int) $installment->id,
                    'principal_amount' => $principal,
                    'interest_amount' => $interest,
                    'fee_amount' => $fee,
                    'penalty_amount' => $penalty,
                ]);

                $installment->principal_paid = round((float) $installment->principal_paid + $principal, 2);
                $installment->interest_paid = round((float) $installment->interest_paid + $interest, 2);
                $installment->fees_paid = round((float) $installment->fees_paid + $fee, 2);
                $installment->penalty_paid = round((float) $installment->penalty_paid + $penalty, 2);

                $installment->amount_paid = round((float) $installment->amount_paid + $total, 2);
                $installment->outstanding_amount = round(max(0.0, (float) $installment->total_due - (float) $installment->amount_paid), 2);

                if ((float) $installment->outstanding_amount <= 0.0) {
                    $installment->status = 'paid';
                    $installment->paid_date = $paymentDate->toDateString();
                } elseif ((float) $installment->amount_paid > 0.0) {
                    $installment->status = 'partial';
                }

                $installment->save();

                LoanInstallmentPayments::create([
                    'installment_id' => (int) $installment->id,
                    'loan_id' => (int) $loan->id,
                    'subshop_id' => (int) $loan->subshop_id,
                    'customer_id' => $resolvedCustomerId,
                    'total_paid' => $total,
                    'payment_method' => $paymentMethod,
                    'bank_account_id' => $bankAccountId,
                    'payment_date' => $paymentDate->toDateString(),
                    'reference_number' => $transactionReference,
                    'is_successful' => true,
                    'is_active' => true,
                ]);

                $principalTotal += $principal;
                $interestTotal += $interest;
                $feeTotal += $fee;
                $penaltyTotal += $penalty;
                $allocatedTotal += $total;
            }

            $summary = $this->loanAccountEngine->getLoanAccountSummary($loan);
            $loan->outstanding_balance = (float) ($summary['total_balance'] ?? null);
            $loan->next_installment_amount = (float) ($summary['next_installment']['total_due'] ?? null);

            $hasOutstanding = LoanInstallments::query()
                ->where('loan_id', (int) $loan->id)
                ->where('is_active', true)
                ->where('outstanding_amount', '>', 0)
                ->exists();

            $loan->status = $hasOutstanding ? 'partially_paid' : 'paid_off';
            $loan->save();

            $remainingPayment = round(max(0.0, (float) $paymentAmount - (float) $allocatedTotal), 2);
            if ($remainingPayment > 0 && ! $hasOutstanding) {
                $this->customerCreditService->createCreditFromOverpayment(
                    (int) $loan->subshop_id,
                    $resolvedCustomerId,
                    (int) $loan->id,
                    (int) $payment->id,
                    $remainingPayment,
                    'Overpayment credit created automatically from repayment.'
                );

                Log::info('Overpayment stored as customer credit', [
                    'loan_id' => (int) $loan->id,
                    'payment_id' => (int) $payment->id,
                    'customer_id' => $resolvedCustomerId,
                    'remaining_payment' => $remainingPayment,
                ]);
            }

            $this->ledger->recordRepayment(
                $loan,
                (float) $paymentAmount,
                round($principalTotal, 2),
                round($interestTotal, 2),
                round($penaltyTotal, 2),
                round($feeTotal, 2),
                (int) $payment->id
            );

            $journal = $this->accounting->postLoanRepayment([
                'payment_id' => (int) $payment->id,
                'loan_id' => (int) $loan->id,
                'subshop_id' => (int) $loan->subshop_id,
                'principal_amount' => round($principalTotal, 2),
                'interest_amount' => round($interestTotal, 2),
                'penalty_amount' => round($penaltyTotal, 2),
                'fee_amount' => round($feeTotal, 2),
                'payment_method' => (string) $paymentMethod,
                'bank_account_id' => $bankAccountId,
            ]);

            $this->voucherService->createVoucherFromJournalEntry(
                $journal,
                'receipt',
                [
                    'payment_method' => (string) $paymentMethod,
                    'bank_account_id' => $bankAccountId,
                    'description' => 'Loan repayment receipt voucher #'.(int) $payment->id,
                ]
            );

            try {
                $customer = $loan->customer;
                if ($customer && $customer->phone) {
                    $shopId = (int) ($loan->subshop?->shop_id ?? 0);
                    app(SmsManager::class)->sendEvent('payment.received', [
                        'shop_id' => $shopId,
                        'subshop_id' => (int) $loan->subshop_id,
                        'user_id' => auth()->id(),
                        'phone' => $customer->phone,
                        'data' => [
                            'amount' => $paymentAmount,
                            'date' => $paymentDate->format('Y-m-d'),
                            'loan_code' => $loan->loan_code ?? 'N/A',
                        ],
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to send payment received SMS: '.$e->getMessage());
            }

            return $payment;
        });
    }

    public function confirmPendingPayment(
        int $paymentId,
        float $amount,
        ?string $phone = null,
        ?string $provider = null,
    ): LoanPayments {
        Log::info('PaymentProcessor::confirmPendingPayment started', [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'phone' => $phone,
            'provider' => $provider,
        ]);

        $payment = LoanPayments::query()->findOrFail($paymentId);

        if ($payment->status !== 'pending') {
            throw new InvalidArgumentException("Payment #{$paymentId} is not in pending status.");
        }

        $loan = Loans::query()->whereKey((int) $payment->loan_id)->firstOrFail();

        try {
            return DB::transaction(function () use ($payment, $loan, $phone, $provider, $amount) {
                $payment->update([
                    'status' => 'confirmed',
                    'phone' => $phone ?? $payment->phone,
                    'provider' => $provider ?? $payment->provider,
                ]);

                Log::info('Payment status updated to confirmed, calling processLoanRepayment', [
                    'payment_id' => $payment->id,
                    'loan_id' => $loan->id,
                ]);

                return $this->processLoanRepayment($loan, (int) $payment->customer_id, $amount, $payment);
            });
        } catch (\Exception $e) {
            Log::error('confirmPendingPayment transaction failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    protected function processLoanRepayment(
        Loans $loan,
        int $customerId,
        float $amount,
        LoanPayments $payment,
    ): LoanPayments {
        Log::info('PaymentProcessor::processLoanRepayment started', [
            'loan_id' => $loan->id,
            'customer_id' => $customerId,
            'amount' => $amount,
            'payment_id' => $payment->id,
        ]);

        $strategy = new PenaltyFirstStrategy;
        $allocator = new PaymentAllocator($strategy);

        Log::info('PaymentProcessor: calling allocatePayment', ['loan_id' => $loan->id, 'amount' => $amount]);
        $allocations = $allocator->allocatePayment($loan, $amount);
        Log::info('PaymentProcessor: allocatePayment returned', ['count' => count($allocations)]);
        $allocator = new PaymentAllocator($strategy);
        $allocations = $allocator->allocatePayment($loan, $amount);

        if (empty($allocations)) {
            throw new InvalidArgumentException('Unable to allocate payment amount.');
        }

        $paymentDate = Carbon::now()->toDateString();
        $principalTotal = 0.0;
        $interestTotal = 0.0;
        $feeTotal = 0.0;
        $penaltyTotal = 0.0;
        $allocatedTotal = 0.0;

        foreach ($allocations as $row) {
            $installment = LoanInstallments::query()
                ->whereKey((int) $row['installment_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $principal = (float) ($row['principal_paid'] ?? 0.0);
            $interest = (float) ($row['interest_paid'] ?? 0.0);
            $fee = (float) ($row['fee_paid'] ?? 0.0);
            $penalty = (float) ($row['penalty_paid'] ?? 0.0);
            $total = (float) ($row['total'] ?? 0.0);

            LoanPaymentAllocations::create([
                'loan_payment_id' => (int) $payment->id,
                'loan_installment_id' => (int) $installment->id,
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'fee_amount' => $fee,
                'penalty_amount' => $penalty,
            ]);

            $installment->principal_paid = round((float) $installment->principal_paid + $principal, 2);
            $installment->interest_paid = round((float) $installment->interest_paid + $interest, 2);
            $installment->fees_paid = round((float) $installment->fees_paid + $fee, 2);
            $installment->penalty_paid = round((float) $installment->penalty_paid + $penalty, 2);
            $installment->amount_paid = round((float) $installment->amount_paid + $total, 2);
            $installment->outstanding_amount = round(max(0.0, (float) $installment->total_due - (float) $installment->amount_paid), 2);

            if ((float) $installment->outstanding_amount <= 0.0) {
                $installment->status = 'paid';
                $installment->paid_date = $paymentDate;
            } elseif ((float) $installment->amount_paid > 0.0) {
                $installment->status = 'partial';
            }

            $installment->save();

            LoanInstallmentPayments::create([
                'installment_id' => (int) $installment->id,
                'loan_id' => (int) $loan->id,
                'subshop_id' => (int) $loan->subshop_id,
                'customer_id' => $customerId,
                'total_paid' => $total,
                'payment_method' => $payment->payment_method ?? 'azampay',
                'payment_date' => $paymentDate,
                'reference_number' => $payment->external_id ?? $payment->transaction_reference,
                'is_successful' => true,
                'is_active' => true,
            ]);

            $principalTotal += $principal;
            $interestTotal += $interest;
            $feeTotal += $fee;
            $penaltyTotal += $penalty;
            $allocatedTotal += $total;
        }

        $summary = $this->loanAccountEngine->getLoanAccountSummary($loan);
        $loan->outstanding_balance = (float) ($summary['total_balance'] ?? null);
        $loan->next_installment_amount = (float) ($summary['next_installment']['total_due'] ?? null);

        $hasOutstanding = LoanInstallments::query()
            ->where('loan_id', (int) $loan->id)
            ->where('is_active', true)
            ->where('outstanding_amount', '>', 0)
            ->exists();

        $loan->status = $hasOutstanding ? 'partially_paid' : 'paid_off';
        $loan->save();

        $remainingPayment = round(max(0.0, (float) $amount - (float) $allocatedTotal), 2);
        if ($remainingPayment > 0 && ! $hasOutstanding) {
            $this->customerCreditService->createCreditFromOverpayment(
                (int) $loan->subshop_id,
                $customerId,
                (int) $loan->id,
                (int) $payment->id,
                $remainingPayment,
                'Overpayment credit created automatically from repayment.'
            );

            Log::info('Overpayment stored as customer credit', [
                'loan_id' => (int) $loan->id,
                'payment_id' => (int) $payment->id,
                'customer_id' => $customerId,
                'remaining_payment' => $remainingPayment,
            ]);
        }

        $this->ledger->recordRepayment(
            $loan,
            (float) $amount,
            round($principalTotal, 2),
            round($interestTotal, 2),
            round($penaltyTotal, 2),
            round($feeTotal, 2),
            (int) $payment->id
        );

        $journal = $this->accounting->postLoanRepayment([
            'payment_id' => (int) $payment->id,
            'loan_id' => (int) $loan->id,
            'subshop_id' => (int) $loan->subshop_id,
            'principal_amount' => round($principalTotal, 2),
            'interest_amount' => round($interestTotal, 2),
            'penalty_amount' => round($penaltyTotal, 2),
            'fee_amount' => round($feeTotal, 2),
            'payment_method' => (string) ($payment->payment_method ?? 'azampay'),
            'bank_account_id' => null,
        ]);

        $this->voucherService->createVoucherFromJournalEntry(
            $journal,
            'receipt',
            [
                'payment_method' => (string) ($payment->payment_method ?? 'azampay'),
                'bank_account_id' => null,
                'description' => 'Loan repayment receipt voucher #'.(int) $payment->id,
            ]
        );

        try {
            $customer = $loan->customer;
            if ($customer && $customer->phone) {
                $shopId = (int) ($loan->subshop?->shop_id ?? 0);
                app(SmsManager::class)->sendEvent('loan.repayment', [
                    'shop_id' => $shopId,
                    'subshop_id' => (int) $loan->subshop_id,
                    'user_id' => $payment->user_id ?? null,
                    'phone' => $customer->phone,
                    'data' => [
                        'name' => $customer->name,
                        'amount' => $amount,
                        'date' => $paymentDate,
                        'loan_code' => $loan->loan_code ?? 'N/A',
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send payment received SMS: '.$e->getMessage());
        }

        Log::info('Loan repayment processed via webhook', [
            'payment_id' => $payment->id,
            'loan_id' => $loan->id,
            'amount' => $amount,
        ]);

        return $payment;
    }
}
