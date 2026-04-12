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
use App\Models\PaymentMethodAccount;
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

        $paymentAccountId = $this->resolvePaymentAccountId('azampay', (int) $subshopId);

        $payment = LoanPayments::create([
            'loan_id' => (int) $loan->id,
            'subshop_id' => (int) $subshopId,
            'customer_id' => $resolvedCustomerId,
            'user_id' => auth()->id(),
            'amount' => $paymentAmount,
            'payment_date' => $paymentDate->toDateString(),
            'payment_method' => 'azampay',
            'payment_account_id' => $paymentAccountId,
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

        // Validate payment parameters
        $this->validator->validate($loan, $payerCustomerId, $paymentAmount, $transactionReference, $paymentDate);

        // Check if bank account is required for this payment method
        $bankAccountId = $bankAccountId ? (int) $bankAccountId : null;
        $requiresBank = ! in_array($paymentMethod, ['cash', 'customer_credit', 'savings'], true);
        if ($requiresBank && ! $bankAccountId) {
            throw new InvalidArgumentException('Bank account is required for this payment method.');
        }

        // Resolve customer (payer)
        $resolvedCustomerId = (int) ($loan->customer_id ?: $payerCustomerId);
        if ($resolvedCustomerId <= 0) {
            throw new InvalidArgumentException('Payer is required for this loan.');
        }

        // Resolve payment account ID (GL account for this payment method)
        $subshopId = (int) $loan->subshop_id;
        $paymentAccountId = $this->resolvePaymentAccountId($paymentMethod, $subshopId, $bankAccountId);

        // Process payment in database transaction
        return DB::transaction(function () use (
            $loan,
            $resolvedCustomerId,
            $paymentAmount,
            $paymentMethod,
            $bankAccountId,
            $paymentAccountId,
            $transactionReference,
            $paymentDate,
            $notes,
            $strategy
        ) {
            // Lock loan for update to prevent concurrent modifications
            $loan = Loans::query()->whereKey((int) $loan->id)->lockForUpdate()->firstOrFail();

            // Create loan payment record
            $payment = LoanPayments::create([
                'loan_id' => (int) $loan->id,
                'subshop_id' => (int) $loan->subshop_id,
                'customer_id' => $resolvedCustomerId,
                'user_id' => auth()->id(),
                'amount' => $paymentAmount,
                'payment_date' => $paymentDate->toDateString(),
                'payment_method' => $paymentMethod,
                'payment_account_id' => $paymentAccountId,
                'reference_number' => $transactionReference,
                'notes' => $notes,
                'status' => 'confirmed',
            ]);

            // Process repayment allocation and accounting
            return $this->processRepaymentCore(
                $loan,
                $resolvedCustomerId,
                $paymentAmount,
                $paymentMethod,
                $bankAccountId,
                $paymentDate,
                $transactionReference,
                $notes,
                $strategy,
                $payment
            );
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

                return $this->processRepaymentCore(
                    $loan,
                    (int) $payment->customer_id,
                    $amount,
                    (string) ($payment->payment_method ?? 'azampay'),
                    null,
                    Carbon::now()->startOfDay(),
                    (string) ($payment->external_id ?? $payment->transaction_reference ?? null),
                    (string) ($payment->notes ?? null),
                    null,
                    $payment
                );
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

    protected function processRepaymentCore(
        Loans $loan,
        int $customerId,
        float $amount,
        string $paymentMethod,
        ?int $bankAccountId,
        Carbon $paymentDate,
        ?string $transactionReference,
        ?string $notes,
        ?object $strategy,
        LoanPayments $payment,
    ): LoanPayments {
        $strategy = $strategy ?: new PenaltyFirstStrategy;

        Log::info('PaymentProcessor::processRepaymentCore started', [
            'loan_id' => (int) $loan->id,
            'payment_id' => (int) $payment->id,
            'subshop_id' => (int) $loan->subshop_id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
        ]);

        $allocator = new PaymentAllocator($strategy);
        $allocations = $allocator->allocatePayment($loan, $amount);

        if (empty($allocations)) {
            throw new InvalidArgumentException('Unable to allocate payment amount.');
        }

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

            if ((int) $installment->loan_id !== (int) $loan->id) {
                throw new InvalidArgumentException('Installment does not belong to loan.');
            }

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
                'customer_id' => $customerId,
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

        if (! $payment->payment_account_id) {
            throw new InvalidArgumentException('Loan payment missing payment_account_id; cannot post journal.');
        }

        $journal = $this->accounting->postLoanRepayment([
            'payment_id' => (int) $payment->id,
            'loan_id' => (int) $loan->id,
            'subshop_id' => (int) $loan->subshop_id,
            'principal_amount' => round($principalTotal, 2),
            'interest_amount' => round($interestTotal, 2),
            'penalty_amount' => round($penaltyTotal, 2),
            'fee_amount' => round($feeTotal, 2),
            'payment_method' => (string) $paymentMethod,
            'payment_account_id' => (int) $payment->payment_account_id,
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

        return $payment;
    }

    private function resolvePaymentAccountId(string $paymentMethod, int $subshopId, ?int $bankAccountId = null): int
    {
        // Validate subshop
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('subshop_id is required to resolve payment method GL account.');
        }

        // If bank account provided, use its chart of account
        if ($bankAccountId) {
            $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
            if (! $bank) {
                throw new InvalidArgumentException('Selected bank account not found.');
            }
            
            if ((int) $bank->subshop_id !== $subshopId) {
                throw new InvalidArgumentException('Selected bank account does not belong to this branch.');
            }
            
            $accountId = (int) $bank->chart_of_account_id;
            if ($accountId <= 0) {
                throw new InvalidArgumentException('Bank account missing chart_of_account_id mapping.');
            }
            
            return $accountId;
        }

        // Look up payment method to GL account mapping
        $method = trim(strtolower($paymentMethod));
        if ($method === '') {
            throw new InvalidArgumentException('payment_method is required to resolve payment GL account.');
        }

        $mapping = PaymentMethodAccount::query()
            ->where('subshop_id', $subshopId)
            ->where('payment_method', $method)
            ->first();

        if (! $mapping) {
            Log::error('Payment method account mapping not found', [
                'subshop_id' => $subshopId,
                'payment_method' => $method,
                'available_methods' => PaymentMethodAccount::where('subshop_id', $subshopId)->pluck('payment_method')->toArray(),
            ]);
            throw new InvalidArgumentException("Payment method not mapped to GL account: {$method}.");
        }

        $accountId = (int) $mapping->chart_of_account_id;
        if ($accountId <= 0) {
            throw new InvalidArgumentException("Invalid chart_of_account_id for payment method mapping: {$method}.");
        }

        return $accountId;
    }
}
