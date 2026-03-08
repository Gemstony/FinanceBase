<?php

declare(strict_types=1);

namespace App\Services\Loans\Repayment;

use App\Models\LoanInstallments;
use App\Models\Loans;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PaymentAllocator
{
    /**
     * @param object $strategy Any object with method getPriorityOrder(): array
     */
    public function __construct(private readonly object $strategy)
    {
        if (!method_exists($this->strategy, 'getPriorityOrder')) {
            throw new InvalidArgumentException('Invalid allocation strategy provided.');
        }
    }

    /**
     * @return array<int, array{installment_id:int, principal_paid:float, interest_paid:float, fee_paid:float, penalty_paid:float, total:float}>
     */
    public function allocatePayment(Loans $loan, float $amount): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than 0.');
        }

        $latestVersion = (int) (LoanInstallments::query()
            ->where('loan_id', (int) $loan->id)
            ->max('schedule_version') ?: 1);

        /** @var Collection<int, LoanInstallments> $installments */
        $installments = LoanInstallments::query()
            ->where('loan_id', (int) $loan->id)
            ->where('schedule_version', $latestVersion)
            ->where('is_active', true)
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        if ($installments->isEmpty()) {
            throw new InvalidArgumentException('This loan has no unpaid installments.');
        }

        $priorityOrder = (array) $this->strategy->getPriorityOrder();
        $valid = ['penalty', 'interest', 'fee', 'principal'];
        foreach ($priorityOrder as $p) {
            if (!in_array($p, $valid, true)) {
                throw new InvalidArgumentException('Invalid allocation component: ' . $p);
            }
        }

        $remaining = round($amount, 2);
        $allocations = [];

        foreach ($installments as $ins) {
            if ($remaining <= 0) {
                break;
            }

            $componentRemaining = [
                'principal' => max(0.0, (float) $ins->principal_due - (float) $ins->principal_paid),
                'interest' => max(0.0, (float) $ins->interest_due - (float) $ins->interest_paid),
                'fee' => max(0.0, (float) $ins->fees_due - (float) $ins->fees_paid),
                'penalty' => max(0.0, (float) $ins->penalty_due - (float) $ins->penalty_paid),
            ];

            $allocated = [
                'principal' => 0.0,
                'interest' => 0.0,
                'fee' => 0.0,
                'penalty' => 0.0,
            ];

            foreach ($priorityOrder as $component) {
                if ($remaining <= 0) {
                    break;
                }

                $due = (float) ($componentRemaining[$component] ?? 0.0);
                if ($due <= 0) {
                    continue;
                }

                $pay = min($due, $remaining);
                if ($pay <= 0) {
                    continue;
                }

                $allocated[$component] = round($allocated[$component] + $pay, 2);
                $remaining = round($remaining - $pay, 2);
                $componentRemaining[$component] = round($due - $pay, 2);
            }

            $totalAllocated = round($allocated['principal'] + $allocated['interest'] + $allocated['fee'] + $allocated['penalty'], 2);
            if ($totalAllocated <= 0) {
                continue;
            }

            $allocations[] = [
                'installment_id' => (int) $ins->id,
                'principal_paid' => (float) $allocated['principal'],
                'interest_paid' => (float) $allocated['interest'],
                'fee_paid' => (float) $allocated['fee'],
                'penalty_paid' => (float) $allocated['penalty'],
                'total' => (float) $totalAllocated,
            ];
        }

        return $allocations;
    }
}
