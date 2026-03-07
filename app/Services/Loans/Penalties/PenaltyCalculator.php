<?php

namespace App\Services\Loans\Penalties;

use App\Models\LoanInstallments;
use Carbon\Carbon;

class PenaltyCalculator
{
    /**
     * Calculate daily penalty amount for an overdue installment.
     *
     * Financial logic (banking / microfinance):
     * Many institutions accrue penalties daily as a percentage of outstanding principal.
     * This method returns the penalty that should be accrued for *today's run*.
     *
     * Formula (daily accrual):
     * penalty_today = outstanding_principal × penalty_rate_per_day
     *
     * Where:
     * outstanding_principal = max(0, principal_due - principal_paid)
     *
     * Notes:
     * - `daysOverdue` is computed for eligibility checks (e.g. grace period), but we do NOT
     *   multiply by days overdue here because this engine is intended to run once per day.
     * - Amount is always >= 0.
     */
    public function calculatePenalty(LoanInstallments $installment, float $penaltyRatePerDay): float
    {
        $rate = max(0.0, $penaltyRatePerDay);

        $outstandingPrincipal = max(0.0, (float) $installment->principal_due - (float) $installment->principal_paid);

        $penalty = $outstandingPrincipal * $rate;

        return round(max(0.0, $penalty), 2);
    }

    /**
     * Calculate days overdue = today - due_date.
     * Returns 0 if not overdue.
     */
    public function calculateDaysOverdue(LoanInstallments $installment, ?Carbon $today = null): int
    {
        $asOf = ($today ?? Carbon::today())->startOfDay();
        $dueDate = ($installment->due_date instanceof Carbon)
            ? $installment->due_date
            : Carbon::parse($installment->due_date);

        $days = $dueDate->startOfDay()->diffInDays($asOf, false);

        return max(0, (int) $days);
    }
}
