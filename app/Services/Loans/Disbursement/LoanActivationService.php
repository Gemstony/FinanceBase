<?php

declare(strict_types=1);

namespace App\Services\Loans\Disbursement;

use App\Models\Loans;
use Carbon\Carbon;

class LoanActivationService
{
    /**
     * Activate a loan after successful disbursement.
     *
     * Financial meaning:
     * Activation marks the beginning of the loan lifecycle where:
     * - repayments can be accepted
     * - interest can start accruing
     * - penalties can accrue when installments become overdue
     * - delinquency classification (PAR) becomes relevant
     *
     * Important integrity rule:
     * - Installments must remain unchanged (they were generated earlier).
     */
    public function activateLoan(Loans $loan, float $amount): void
    {
        // In this schema, the lifecycle status after disbursement is `disbursed`.
        // (The enum in `loans.status` does not include `active`.)
        $loan->status = 'disbursed';

        // Persist the actual disbursement date for reporting and lifecycle tracking.
        $loan->disbursement_date = Carbon::now()->toDateString();

        // Do not regenerate schedules or modify existing installments here.
        // The repayment schedule is already created at loan origination.

        $loan->save();
    }
}
