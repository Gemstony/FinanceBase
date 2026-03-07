<?php

declare(strict_types=1);

namespace App\Services\Loans\Restructuring;

use App\Models\Loans;
use Illuminate\Support\Str;
use RuntimeException;

class LoanRestructureValidator
{
    /**
     * Validate whether the provided loan can be restructured.
     *
     * Validation rules (business + data integrity):
     * - Loan must exist and be active (system flag)
     * - Loan must be in a running state (not closed, not written off)
     * - Loan must have remaining principal
     * - Loan must have remaining installments (i.e. not fully settled)
     *
     * These validations protect against:
     * - rewriting a closed contract (paid-off / written-off)
     * - restructuring a loan without any remaining balance
     * - generating schedules when there is nothing to reschedule
     */
    public function validate(Loans $loan): void
    {
        if (empty($loan->id)) {
            throw new RuntimeException('Unable to restructure: loan does not exist.');
        }

        if ($loan->is_active !== true) {
            throw new RuntimeException('Unable to restructure: loan is inactive.');
        }

        $status = Str::lower((string) $loan->status);

        // Treat these as "closed" states where restructuring must never occur.
        if (in_array($status, ['paid_off', 'written_off'], true)) {
            throw new RuntimeException("Unable to restructure: loan is closed (status={$loan->status}).");
        }

        // These states are also not eligible for restructuring in this implementation.
        if (in_array($status, ['defaulted', 'rejected'], true)) {
            throw new RuntimeException("Unable to restructure: loan status is not eligible (status={$loan->status}).");
        }

        // Some systems use 'active', while this codebase uses lifecycle states like 'disbursed'/'partially_paid'.
        $eligibleStatuses = ['active', 'disbursed', 'partially_paid', 'approved'];
        if (!in_array($status, $eligibleStatuses, true)) {
            throw new RuntimeException("Unable to restructure: loan must be active/running (status={$loan->status}).");
        }

        $remainingPrincipal = $this->calculateRemainingPrincipal($loan);
        if ($remainingPrincipal <= 0.0) {
            throw new RuntimeException('Unable to restructure: loan has no remaining principal.');
        }

        $remainingInstallmentsCount = $loan->installments()
            ->where('status', '!=', 'paid')
            ->where('is_active', true)
            ->count();

        if ($remainingInstallmentsCount < 1) {
            throw new RuntimeException('Unable to restructure: no remaining installments were found.');
        }
    }

    /**
     * Remaining principal is computed from the schedule (not from the loan header),
     * so that restructuring reflects what is actually still due:
     *
     * remaining_principal = SUM(max(0, principal_due - principal_paid)) for all non-paid installments
     */
    private function calculateRemainingPrincipal(Loans $loan): float
    {
        return (float) $loan->installments()
            ->where('status', '!=', 'paid')
            ->where('is_active', true)
            ->get(['principal_due', 'principal_paid'])
            ->sum(function ($i) {
                $due = (float) $i->principal_due;
                $paid = (float) $i->principal_paid;

                return max(0.0, $due - $paid);
            });
    }
}
