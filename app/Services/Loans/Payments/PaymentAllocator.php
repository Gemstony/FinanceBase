<?php

namespace App\Services\Loans\Payments;

use App\Models\LoanInstallments;
use InvalidArgumentException;

class PaymentAllocator
{
    /**
     * Allocate a payment amount across installments using a priority order.
     *
     * Important: this allocator does NOT mutate DB fields. It works on remaining
     * component balances you provide (usually: charged - already allocated).
     *
     * @param array<int, array{installment:LoanInstallments, remaining:array{principal:float,interest:float,fee:float,penalty:float}}> $installments
     * @param array<int, string> $priorityOrder Must be subset/order of: penalty, fee, interest, principal
     *
     * @return array{remaining_amount:float, allocations:array<int, array{installment:LoanInstallments, principal:float, interest:float, fee:float, penalty:float, total:float}>}
     */
    public function allocate(array $installments, float $paymentAmount, array $priorityOrder): array
    {
        if ($paymentAmount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than 0.');
        }

        $valid = ['penalty', 'fee', 'interest', 'principal'];
        foreach ($priorityOrder as $p) {
            if (!in_array($p, $valid, true)) {
                throw new InvalidArgumentException('Invalid allocation component: ' . $p);
            }
        }

        $remaining = (float) $paymentAmount;
        $result = [];

        foreach ($installments as $item) {
            if ($remaining <= 0) {
                break;
            }

            $installment = $item['installment'];
            $componentRemaining = $item['remaining'];

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

                $due = (float) ($componentRemaining[$component] ?? 0);
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

            $totalAllocated = round(
                $allocated['principal'] + $allocated['interest'] + $allocated['fee'] + $allocated['penalty'],
                2
            );
            if ($totalAllocated <= 0) {
                continue;
            }

            $result[] = [
                'installment' => $installment,
                'principal' => $allocated['principal'],
                'interest' => $allocated['interest'],
                'fee' => $allocated['fee'],
                'penalty' => $allocated['penalty'],
                'total' => $totalAllocated,
            ];
        }

        return [
            'remaining_amount' => $remaining,
            'allocations' => $result,
        ];
    }
}
