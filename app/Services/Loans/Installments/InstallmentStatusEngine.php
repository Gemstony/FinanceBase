<?php

namespace App\Services\Loans\Installments;

use App\Models\Loans;
use App\Models\LoanInstallments;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * InstallmentStatusEngine
 *
 * Determines the correct status of an installment based on balances and due date.
 * Uses InstallmentBalanceCalculator to compute outstanding amounts.
 *
 * Possible statuses:
 * - pending: No payment yet and due_date is in the future
 * - partial: Some amount is paid but installment is not fully settled
 * - paid: All balances are zero
 * - overdue: due_date has passed and installment still has outstanding balance
 */
class InstallmentStatusEngine
{
    public function __construct(
        private InstallmentBalanceCalculator $balanceCalculator
    ) {}

    /**
     * Determine the status of an installment.
     *
     * Business logic:
     * 1. If total outstanding is zero => paid
     * 2. If any payment made but not fully settled => partial
     * 3. If due date passed and still outstanding => overdue
     * 4. Otherwise => pending
     *
     * @param LoanInstallments $installment
     * @return string
     */
    public function determineStatus(LoanInstallments $installment): string
    {
        $totalOutstanding = $this->balanceCalculator->calculateTotalOutstanding($installment);

        // Fully paid
        if ($totalOutstanding === 0.0) {
            return 'paid';
        }

        // Partial payment made
        if ($this->hasAnyPayment($installment)) {
            return 'partial';
        }

        // Overdue (due date passed and still outstanding)
        if (Carbon::today()->gt($installment->due_date)) {
            return 'overdue';
        }

        // Default to pending (no payments yet and due date is future)
        return 'pending';
    }

    /**
     * Check if an installment has any payment recorded.
     *
     * @param LoanInstallments $installment
     * @return bool
     */
    public function hasAnyPayment(LoanInstallments $installment): bool
    {
        return (float) $installment->principal_paid > 0
            || (float) $installment->interest_paid > 0
            || (float) $installment->fees_paid > 0
            || (float) $installment->penalty_paid > 0;
    }

    /**
     * Check if an installment is fully paid.
     *
     * @param LoanInstallments $installment
     * @return bool
     */
    public function isFullyPaid(LoanInstallments $installment): bool
    {
        return $this->balanceCalculator->calculateTotalOutstanding($installment) === 0.0;
    }

    /**
     * Update the status of a single installment and persist it.
     *
     * @param LoanInstallments $installment
     * @return void
     */
    public function updateInstallmentStatus(LoanInstallments $installment): void
    {
        $newStatus = $this->determineStatus($installment);

        // Only update if status actually changed to avoid unnecessary DB writes
        if ($installment->status !== $newStatus) {
            $installment->status = $newStatus;
            $installment->save();
        }
    }

    /**
     * Update statuses for all installments of a loan.
     *
     * Processes each installment individually to ensure accurate status calculation.
     * Wrapped in a transaction for data consistency.
     *
     * @param Loans $loan
     * @return void
     */
    public function updateLoanInstallmentsStatuses(Loans $loan): void
    {
        DB::transaction(function () use ($loan) {
            $loan->installments()->each(function (LoanInstallments $installment) {
                $this->updateInstallmentStatus($installment);
            });
        });
    }
}
