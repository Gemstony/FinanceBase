<?php

declare(strict_types=1);

namespace App\Services\Loans\WriteOff;

use App\Models\Loans;

class LoanWriteOffCalculator
{
    /**
     * Calculate remaining balances to be written off.
     *
     * Financial logic:
     * We write off the unpaid portion of each component based on the current schedule:
     * - remaining_principal  = SUM(max(0, principal_due - principal_paid))
     * - remaining_interest   = SUM(max(0, interest_due - interest_paid))
     * - remaining_penalties  = SUM(max(0, penalty_due - penalty_paid))
     *
     * Note: Fees are tracked independently in loan_fee_applications table
     * and are NOT included in installment amounts, so they are excluded from write-off.
     *
     * @return array{principal_written_off:float,interest_written_off:float,penalties_written_off:float,total_written_off:float}
     */
    public function calculateBalances(Loans $loan): array
    {
        $installments = $loan->installments()
            ->where('is_active', true)
            ->where('status', '!=', 'paid')
            ->get([
                'principal_due', 'principal_paid',
                'interest_due', 'interest_paid',
                'penalty_due', 'penalty_paid',
            ]);

        $principal = (float) $installments->sum(fn ($i) => max(0.0, (float) $i->principal_due - (float) $i->principal_paid));
        $interest = (float) $installments->sum(fn ($i) => max(0.0, (float) $i->interest_due - (float) $i->interest_paid));
        $penalties = (float) $installments->sum(fn ($i) => max(0.0, (float) $i->penalty_due - (float) $i->penalty_paid));

        $principal = round($principal, 2);
        $interest = round($interest, 2);
        $penalties = round($penalties, 2);

        $total = round($principal + $interest + $penalties, 2);

        return [
            'principal_written_off' => $principal,
            'interest_written_off' => $interest,
            'penalties_written_off' => $penalties,
            'total_written_off' => $total,
        ];
    }
}
