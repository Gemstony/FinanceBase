<?php

declare(strict_types=1);

namespace App\Services\Loans\Restructuring;

use App\Models\LoanInstallments;
use App\Models\LoanRestructures;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LoanRestructureEngine
{
    public function __construct(
        private readonly LoanRestructureValidator $validator,
        private readonly LoanRescheduleCalculator $rescheduleCalculator,
        private readonly RestructureHistoryRecorder $historyRecorder
    ) {}

    /**
     * Restructure a loan by rescheduling the remaining installments.
     *
     * This method is transactional to guarantee data integrity: either the entire restructure
     * is applied, or nothing changes.
     */
    public function restructureLoan(Loans $loan, int $newTermMonths, string $reason): LoanRestructures
    {
        $this->validator->validate($loan);

        return DB::transaction(function () use ($loan, $newTermMonths, $reason) {
            $effectiveDate = Carbon::today()->startOfDay();

            $remainingPrincipal = $this->calculateRemainingPrincipal($loan);

            $restructure = LoanRestructures::create([
                'loan_id' => $loan->id,
                'restructure_type' => 'reschedule',
                'old_term_months' => (int) $loan->installments,
                'new_term_months' => (int) $newTermMonths,
                'old_interest_rate' => (float) $loan->interest_rate,
                'new_interest_rate' => (float) $loan->interest_rate,
                'restructure_effective_date' => $effectiveDate->toDateString(),
                'remaining_principal' => $remainingPrincipal,
                'reason' => $reason,
                'approved_by' => Auth::id(),
                'approved_at' => Carbon::now(),
                'is_active' => true,
            ]);

            // Record audit snapshot BEFORE we cancel/delete any installments.
            $this->historyRecorder->recordInstallmentSnapshot($loan, $restructure);

            // Cancel/delete future installments safely.
            // Data integrity rule: never delete installments that have any payments, because payment allocations
            // reference installment_id and must remain auditable.
            $startInstallmentNumber = $this->cancelUnpaidFutureInstallments($loan);

            $newSchedule = $this->rescheduleCalculator->calculateNewSchedule($loan, $newTermMonths, $effectiveDate);

            $this->insertNewInstallments($loan, $newSchedule, $startInstallmentNumber);

            // Update loan header counts/maturity to remain consistent for downstream engines.
            $loan->installments = $this->calculateLatestInstallmentNumber($loan);
            $loan->maturity_date = $this->calculateMaturityDate($loan);
            $loan->save();

            return $restructure;
        });
    }

    /**
     * Cancel remaining installments.
     *
     * Implementation notes:
     * - We MUST NOT delete installments that have any component payments, because payment allocations refer
     *   to installment_id and must remain auditable.
     * - We also have a UNIQUE constraint on (loan_id, installment_number). To reuse installment numbers for the
     *   new schedule, we hard-delete ONLY installments that are completely unpaid (all component paid columns are 0).
     * - For partially-paid installments, we only deactivate them.
     *
     * @return int The installment_number from which the new schedule numbering should start.
     */
    private function cancelUnpaidFutureInstallments(Loans $loan): int
    {
        $installments = $loan->installments()
            ->where('status', '!=', 'paid')
            ->orderBy('installment_number')
            ->get();

        $firstCanceledNumber = null;

        /** @var LoanInstallments $installment */
        foreach ($installments as $installment) {
            $hasAnyPayment = (float) $installment->principal_paid > 0
                || (float) $installment->interest_paid > 0
                || (float) $installment->fees_paid > 0
                || (float) $installment->penalty_paid > 0;

            if ($hasAnyPayment) {
                $installment->is_active = false;
                $installment->save();
                continue;
            }

            if ($firstCanceledNumber === null) {
                $firstCanceledNumber = (int) $installment->installment_number;
            }

            // No payments exist, and we already captured an audit snapshot. We can forceDelete to free the
            // (loan_id, installment_number) uniqueness slot for the new schedule.
            $installment->forceDelete();
        }

        if ($firstCanceledNumber !== null) {
            return $firstCanceledNumber;
        }

        // If we did not cancel anything, append new schedule after the latest number.
        return $this->calculateLatestInstallmentNumber($loan) + 1;
    }

    /**
     * Insert new installment rows.
     *
     * Important: loan_installments has a unique constraint on (loan_id, installment_number).
     * We reuse installment numbers freed by force-deleting completely unpaid installments.
     */
    private function insertNewInstallments(Loans $loan, array $newSchedule, int $startInstallmentNumber): void
    {
        $principalAccountId = (int) ($loan->principal_account_id ?? 0);
        $interestIncomeAccountId = (int) ($loan->interest_income_account_id ?? 0);
        $penaltyIncomeAccountId = (int) ($loan->penalty_income_account_id ?? 0);

        if ($principalAccountId <= 0 || $interestIncomeAccountId <= 0 || $penaltyIncomeAccountId <= 0) {
            throw new RuntimeException('Unable to restructure: required loan account mappings are missing.');
        }

        foreach (array_values($newSchedule) as $idx => $row) {
            $principalDue = (float) $row['principal_due'];
            $interestDue = (float) $row['interest_due'];
            $feesDue = (float) ($row['fees_due'] ?? 0.0);
            $penaltyDue = (float) ($row['penalty_due'] ?? 0.0);

            $totalDue = $principalDue + $interestDue + $feesDue + $penaltyDue;

            LoanInstallments::create([
                'loan_id' => $loan->id,
                'subshop_id' => $loan->subshop_id,
                'installment_number' => $startInstallmentNumber + $idx,
                'principal_due' => $principalDue,
                'interest_due' => $interestDue,
                'fees_due' => $feesDue,
                'penalty_due' => $penaltyDue,
                'principal_paid' => 0,
                'interest_paid' => 0,
                'fees_paid' => 0,
                'penalty_paid' => 0,
                'total_due' => $totalDue,
                'amount_paid' => 0,
                'outstanding_amount' => $totalDue,
                'due_date' => (string) $row['due_date'],
                'paid_date' => null,
                'status' => 'pending',
                'is_active' => true,
                'principal_account_id' => $principalAccountId,
                'interest_income_account_id' => $interestIncomeAccountId,
                'penalty_income_account_id' => $penaltyIncomeAccountId,
                'fee_income_account_id' => $loan->fee_income_account_id,
            ]);
        }
    }

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

    private function calculateLatestInstallmentNumber(Loans $loan): int
    {
        $max = $loan->installments()->max('installment_number');

        return (int) ($max ?? 0);
    }

    private function calculateMaturityDate(Loans $loan): ?string
    {
        $lastDue = $loan->installments()
            ->where('is_active', true)
            ->orderByDesc('due_date')
            ->value('due_date');

        if ($lastDue === null) {
            return null;
        }

        return Carbon::parse($lastDue)->toDateString();
    }
}
