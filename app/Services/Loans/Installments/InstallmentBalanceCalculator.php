<?php

namespace App\Services\Loans\Installments;

use App\Models\LoanInstallments;

/**
 * InstallmentBalanceCalculator
 *
 * Responsible for calculating outstanding balances of an installment.
 * This class computes component-level and total outstanding amounts
 * without determining installment status.
 */
class InstallmentBalanceCalculator
{
    /**
     * Calculate principal outstanding for an installment.
     *
     * Formula: principal_due - principal_paid
     * Never returns a negative value.
     *
     * @param LoanInstallments $installment
     * @return float
     */
    public function calculatePrincipalOutstanding(LoanInstallments $installment): float
    {
        $outstanding = (float) $installment->principal_due - (float) $installment->principal_paid;
        return max(0.0, $outstanding);
    }

    /**
     * Calculate interest outstanding for an installment.
     *
     * Formula: interest_due - interest_paid
     * Never returns a negative value.
     *
     * @param LoanInstallments $installment
     * @return float
     */
    public function calculateInterestOutstanding(LoanInstallments $installment): float
    {
        $outstanding = (float) $installment->interest_due - (float) $installment->interest_paid;
        return max(0.0, $outstanding);
    }

    /**
     * Calculate fees outstanding for an installment.
     *
     * Formula: fees_due - fees_paid
     * Never returns a negative value.
     *
     * @param LoanInstallments $installment
     * @return float
     */
    public function calculateFeesOutstanding(LoanInstallments $installment): float
    {
        $outstanding = (float) $installment->fees_due - (float) $installment->fees_paid;
        return max(0.0, $outstanding);
    }

    /**
     * Calculate penalty outstanding for an installment.
     *
     * Formula: penalty_due - penalty_paid
     * Never returns a negative value.
     *
     * @param LoanInstallments $installment
     * @return float
     */
    public function calculatePenaltyOutstanding(LoanInstallments $installment): float
    {
        $outstanding = (float) $installment->penalty_due - (float) $installment->penalty_paid;
        return max(0.0, $outstanding);
    }

    /**
     * Calculate total outstanding for an installment.
     *
     * Formula: sum of all outstanding components
     * Rounded to 2 decimal places for monetary precision.
     *
     * @param LoanInstallments $installment
     * @return float
     */
    public function calculateTotalOutstanding(LoanInstallments $installment): float
    {
        $total = $this->calculatePrincipalOutstanding($installment)
                + $this->calculateInterestOutstanding($installment)
                + $this->calculateFeesOutstanding($installment)
                + $this->calculatePenaltyOutstanding($installment);

        return round($total, 2);
    }
}
