<?php

declare(strict_types=1);

namespace App\Services\Loans\Repayment;

use App\Models\LoanPayments;
use App\Models\Loans;
use Carbon\Carbon;
use InvalidArgumentException;

class LoanRepaymentValidator
{
    public function validate(
        Loans $loan,
        ?int $payerCustomerId,
        float $paymentAmount,
        ?string $transactionReference,
        Carbon $paymentDate
    ): void {
        if ($paymentAmount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than 0.');
        }

        if (!$loan->customer_id) {
            if (!$payerCustomerId || $payerCustomerId <= 0) {
                throw new InvalidArgumentException('Payer is required for this loan.');
            }
        }

        $status = (string) $loan->status;
        if (!in_array($status, ['disbursed', 'partially_paid'], true)) {
            throw new InvalidArgumentException('This loan is not eligible for repayment.');
        }

        if (!$loan->disbursement_date) {
            throw new InvalidArgumentException('This loan has no disbursement date.');
        }

        if ($paymentDate->startOfDay()->lt(Carbon::parse($loan->disbursement_date)->startOfDay())) {
            throw new InvalidArgumentException('Payment date cannot be before disbursement date.');
        }

        if ($transactionReference) {
            $exists = LoanPayments::query()
                ->where('loan_id', (int) $loan->id)
                ->where('reference_number', $transactionReference)
                ->where('status', 'confirmed')
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException('A confirmed payment with this transaction reference already exists for this loan.');
            }
        }
    }
}
