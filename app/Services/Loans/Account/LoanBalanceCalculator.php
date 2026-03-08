<?php

declare(strict_types=1);

namespace App\Services\Loans\Account;

use App\Models\LoanInterestAccruals;
use App\Models\LoanInstallments;
use App\Models\LoanPaymentAllocations;
use App\Models\LoanPenaltyApplications;
use App\Models\Loans;

class LoanBalanceCalculator
{
    /**
     * Calculate current outstanding balances for a loan.
     *
     * IMPORTANT:
     * - This service performs read-only aggregation.
     * - It must never modify loan, installment, payment, or accrual records.
     *
     * Financial meaning:
     * - Outstanding principal: scheduled principal due minus principal already allocated from payments.
     * - Outstanding interest: accrued interest (daily accruals) minus interest already allocated from payments.
     * - Outstanding penalties: penalties applied minus penalty already allocated from payments.
     * - Outstanding fees: scheduled fees due minus fees already allocated from payments.
     */
    public function calculateBalances(Loans $loan): array
    {
        $loanId = (int) $loan->id;

        $latestVersion = (int) (LoanInstallments::query()
            ->where('loan_id', $loanId)
            ->max('schedule_version') ?: 1);

        // Principal outstanding:
        // SUM(principal_due from loan_installments) - SUM(principal_amount from loan_payment_allocations)
        $scheduledPrincipal = (float) LoanInstallments::query()
            ->where('loan_id', $loanId)
            ->where('schedule_version', $latestVersion)
            ->where('is_active', true)
            ->sum('principal_due');

        $paidPrincipal = (float) LoanPaymentAllocations::query()
            ->join('loan_installments as li', 'li.id', '=', 'loan_payment_allocations.loan_installment_id')
            ->where('li.loan_id', $loanId)
            ->sum('loan_payment_allocations.principal_amount');

        $principalOutstanding = max(0.0, $scheduledPrincipal - $paidPrincipal);

        // Interest outstanding:
        // SUM(daily_interest from loan_interest_accruals) - SUM(interest_amount from loan_payment_allocations)
        $accruedInterest = (float) LoanInterestAccruals::query()
            ->where('loan_id', $loanId)
            ->where('is_active', true)
            ->sum('daily_interest');

        $paidInterest = (float) LoanPaymentAllocations::query()
            ->join('loan_installments as li', 'li.id', '=', 'loan_payment_allocations.loan_installment_id')
            ->where('li.loan_id', $loanId)
            ->sum('loan_payment_allocations.interest_amount');

        $interestOutstanding = max(0.0, $accruedInterest - $paidInterest);

        // Penalties outstanding:
        // SUM(amount from loan_penalty_applications) - SUM(penalty_amount from loan_payment_allocations)
        $appliedPenalties = (float) LoanPenaltyApplications::query()
            ->where('loan_id', $loanId)
            ->sum('amount');

        $paidPenalties = (float) LoanPaymentAllocations::query()
            ->join('loan_installments as li', 'li.id', '=', 'loan_payment_allocations.loan_installment_id')
            ->where('li.loan_id', $loanId)
            ->sum('loan_payment_allocations.penalty_amount');

        $penaltiesOutstanding = max(0.0, $appliedPenalties - $paidPenalties);

        // Fees outstanding:
        // SUM(fees_due from loan_installments) - SUM(fee_amount from loan_payment_allocations)
        $scheduledFees = (float) LoanInstallments::query()
            ->where('loan_id', $loanId)
            ->where('schedule_version', $latestVersion)
            ->where('is_active', true)
            ->sum('fees_due');

        $paidFees = (float) LoanPaymentAllocations::query()
            ->join('loan_installments as li', 'li.id', '=', 'loan_payment_allocations.loan_installment_id')
            ->where('li.loan_id', $loanId)
            ->sum('loan_payment_allocations.fee_amount');

        $feesOutstanding = max(0.0, $scheduledFees - $paidFees);

        // Total balance:
        // principal_outstanding + interest_outstanding + penalties_outstanding + fees_outstanding
        $totalBalance = round(
            $principalOutstanding + $interestOutstanding + $penaltiesOutstanding + $feesOutstanding,
            2
        );

        return [
            'principal_outstanding' => round($principalOutstanding, 2),
            'interest_outstanding' => round($interestOutstanding, 2),
            'penalties_outstanding' => round($penaltiesOutstanding, 2),
            'fees_outstanding' => round($feesOutstanding, 2),
            'total_balance' => $totalBalance,
        ];
    }
}
