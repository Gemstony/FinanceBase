<?php

declare(strict_types=1);

namespace App\Services\Loans\Restructuring;

use App\Models\Loans;
use App\Services\Loans\LoanScheduleEngine;
use Carbon\Carbon;
use InvalidArgumentException;

class LoanRescheduleCalculator
{
    public function __construct(
        private readonly LoanScheduleEngine $scheduleEngine
    ) {}

    /**
     * Calculate a new repayment schedule for the remaining principal.
     *
     * Financial logic:
     * 1) Remaining principal is derived from the current schedule:
     *    SUM(max(0, principal_due - principal_paid)) across non-paid installments.
     *    This ensures we only reschedule what is truly still owed.
     *
     * 2) Interest rate is taken from the loan's current interest_rate (annual %).
     *    For a plain reschedule we keep the same interest rate.
     *
     * 3) We then reuse the project's LoanScheduleEngine, by creating a temporary
     *    "virtual loan" object with:
     *    - principal_amount = remaining principal
     *    - installments = new term count (treated as number of installments)
     *    - disbursement_date = effective date (anchor)
     *
     * The LoanScheduleEngine will apply the correct repayment frequency and interest method
     * according to the loan's configured product relationships.
     *
     * @return array<int, array{installment_number:int,due_date:string,principal_due:float,interest_due:float,fees_due:float,penalty_due:float}>
     */
    public function calculateNewSchedule(Loans $loan, int $newTermMonths, ?Carbon $effectiveDate = null): array
    {
        if ($newTermMonths < 1) {
            throw new InvalidArgumentException('New term months must be at least 1.');
        }

        $remainingPrincipal = $this->calculateRemainingPrincipal($loan);
        if ($remainingPrincipal <= 0.0) {
            throw new InvalidArgumentException('Unable to reschedule: remaining principal is zero.');
        }

        $anchor = ($effectiveDate ?? Carbon::today())->startOfDay();

        // Clone the loan to avoid mutating the persisted model in this calculation.
        $virtualLoan = $loan->replicate();
        $virtualLoan->principal_amount = $remainingPrincipal;
        $virtualLoan->installments = $newTermMonths;
        $virtualLoan->disbursement_date = $anchor->toDateString();

        $schedule = $this->scheduleEngine->generate($virtualLoan);

        // Normalize schedule engine output to the structure expected by restructuring engine.
        $rows = [];
        foreach ($schedule as $row) {
            $rows[] = [
                'installment_number' => (int) $row['installment_number'],
                'due_date' => (string) $row['due_date'],
                'principal_due' => (float) $row['principal_amount'],
                'interest_due' => (float) $row['interest_amount'],
                'fees_due' => 0.0,
                'penalty_due' => 0.0,
            ];
        }

        return $rows;
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
}
