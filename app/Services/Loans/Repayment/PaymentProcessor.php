<?php

declare(strict_types=1);

namespace App\Services\Loans\Repayment;

use App\Models\LoanInstallmentPayments;
use App\Models\LoanInstallments;
use App\Models\LoanPaymentAllocations;
use App\Models\LoanPayments;
use App\Models\Loans;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Loans\Account\LoanAccountEngine;
use App\Services\Loans\Ledger\LoanTransactionLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentProcessor
{
    public function __construct(
        private readonly LoanRepaymentValidator $validator,
        private readonly LoanAccountEngine $loanAccountEngine,
        private readonly LoanTransactionLedger $ledger,
        private readonly JournalPostingEngine $accounting,
    ) {
    }

    public function processPayment(
        Loans $loan,
        ?int $payerCustomerId,
        float $paymentAmount,
        string $paymentMethod,
        ?string $transactionReference,
        Carbon $paymentDate,
        ?string $notes = null,
        ?object $strategy = null,
    ): LoanPayments {
        $strategy = $strategy ?: new PenaltyFirstStrategy();

        $this->validator->validate($loan, $payerCustomerId, $paymentAmount, $transactionReference, $paymentDate);

        return DB::transaction(function () use (
            $loan,
            $payerCustomerId,
            $paymentAmount,
            $paymentMethod,
            $transactionReference,
            $paymentDate,
            $notes,
            $strategy
        ) {
            $loan = Loans::query()->whereKey((int) $loan->id)->lockForUpdate()->firstOrFail();

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
                    'payment_date' => $paymentDate->toDateString(),
                    'reference_number' => $transactionReference,
                    'is_successful' => true,
                    'is_active' => true,
                ]);

                $principalTotal += $principal;
                $interestTotal += $interest;
                $feeTotal += $fee;
                $penaltyTotal += $penalty;
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

            $this->ledger->recordRepayment(
                $loan,
                (float) $paymentAmount,
                round($principalTotal, 2),
                round($interestTotal, 2),
                round($penaltyTotal, 2),
                round($feeTotal, 2),
                (int) $payment->id
            );

            $this->accounting->postLoanRepayment([
                'payment_id' => (int) $payment->id,
                'loan_id' => (int) $loan->id,
                'principal_amount' => round($principalTotal, 2),
                'interest_amount' => round($interestTotal, 2),
                'penalty_amount' => round($penaltyTotal, 2),
                'fee_amount' => round($feeTotal, 2),
            ]);

            return $payment;
        });
    }
}
