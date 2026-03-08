<?php

declare(strict_types=1);

namespace App\Services\Loans\Account;

use App\Models\LoanInstallments;
use App\Models\Loans;

class LoanNextInstallmentResolver
{
    /**
     * Determine the next installment that the borrower must pay.
     *
     * Financial meaning:
     * The "next installment" is the earliest scheduled installment that is not fully settled.
     * This is typically what should be presented to staff/borrower as the next payment target.
     */
    public function getNextInstallment(Loans $loan): ?array
    {
        $loanId = (int) $loan->id;

        $next = LoanInstallments::query()
            ->where('loan_id', $loanId)
            ->where('is_active', true)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->first();

        if (!$next) {
            return null;
        }

        return [
            'installment_number' => (int) $next->installment_number,
            'due_date' => $next->due_date?->format('Y-m-d'),
            'principal_due' => (float) $next->principal_due,
            'interest_due' => (float) $next->interest_due,
            'fee_due' => (float) $next->fees_due,
            'penalty_due' => (float) $next->penalty_due,
            'total_due' => (float) $next->total_due,
        ];
    }
}
