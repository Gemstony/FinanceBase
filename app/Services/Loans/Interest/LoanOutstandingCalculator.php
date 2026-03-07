<?php

namespace App\Services\Loans\Interest;

use App\Models\LoanPaymentAllocations;
use App\Models\LoanInstallments;
use App\Models\Loans;

class LoanOutstandingCalculator
{
    /**
     * Calculate the current outstanding principal balance for a loan.
     *
     * Financial logic:
     * - The principal balance is reduced only when repayments are allocated to principal.
     * - We sum principal allocations from `loan_payment_allocations.principal_amount`.
     * - We compare against the originally disbursed principal (snapshot) stored on the loan.
     *
     * Assumptions:
     * - `Loans::principal_amount` represents the principal disbursed/approved for the loan.
     * - Allocations represent posted/accepted payments (this system does not currently
     *   enforce a payment status filter at the allocation level).
     *
     * Returned value is never negative.
     */
    public function calculateOutstandingPrincipal(Loans $loan): float
    {
        $principalDisbursed = (float) ($loan->principal_amount ?? 0);

        $principalPaid = (float) LoanPaymentAllocations::query()
            ->whereHas('loanInstallment', function ($q) use ($loan) {
                $q->where('loan_id', $loan->id);
            })
            ->sum('principal_amount');

        $outstanding = $principalDisbursed - $principalPaid;

        // Never allow negative outstanding principal.
        return round(max(0.0, $outstanding), 2);
    }
}
