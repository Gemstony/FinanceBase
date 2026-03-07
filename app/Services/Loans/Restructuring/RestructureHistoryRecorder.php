<?php

declare(strict_types=1);

namespace App\Services\Loans\Restructuring;

use App\Models\LoanInstallments;
use App\Models\LoanRestructureInstallments;
use App\Models\LoanRestructures;
use App\Models\Loans;

class RestructureHistoryRecorder
{
    /**
     * Record an audit snapshot of the original (remaining) installment schedule.
     *
     * Audit requirement:
     * - Restructuring must never destroy historical schedule information.
     * - Even if we later cancel/delete future unpaid installments, we must be able to reconstruct
     *   what the borrower originally owed on each remaining installment at the time of restructure.
     *
     * We therefore snapshot ALL installments that are not fully paid.
     */
    public function recordInstallmentSnapshot(Loans $loan, LoanRestructures $restructure): void
    {
        $installments = $loan->installments()
            ->where('status', '!=', 'paid')
            ->orderBy('installment_number')
            ->get();

        /** @var LoanInstallments $installment */
        foreach ($installments as $installment) {
            LoanRestructureInstallments::create([
                'restructure_id' => $restructure->id,
                'installment_id' => $installment->id,
                'installment_number' => (int) $installment->installment_number,
                'old_due_date' => $installment->due_date,
                'old_principal_due' => (float) $installment->principal_due,
                'old_interest_due' => (float) $installment->interest_due,
                'old_fees_due' => (float) $installment->fees_due,
                'old_penalty_due' => (float) $installment->penalty_due,
                // Payment snapshot (what had already been paid at the moment of restructure)
                'principal_paid' => (float) $installment->principal_paid,
                'interest_paid' => (float) $installment->interest_paid,
                'fees_paid' => (float) $installment->fees_paid,
                'penalty_paid' => (float) $installment->penalty_paid,
            ]);
        }
    }
}
