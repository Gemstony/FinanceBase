<?php

declare(strict_types=1);

namespace App\Services\Loans\Account;

use App\Models\LoanInstallments;
use App\Models\Loans;
use Carbon\Carbon;

class LoanOverdueCalculator
{
    /**
     * Calculate overdue amounts and delinquency indicators.
     *
     * IMPORTANT:
     * - Read-only calculations only.
     * - Uses the installment schedule and its status to determine what is overdue.
     *
     * Financial meaning:
     * - Overdue amounts represent scheduled components that should have been paid by today,
     *   but remain unpaid (fully or partially).
     */
    public function calculateOverdue(Loans $loan): array
    {
        $loanId = (int) $loan->id;
        $today = Carbon::today();

        // Find installments where due_date < today AND status != paid
        $overdueInstallments = LoanInstallments::query()
            ->where('loan_id', $loanId)
            ->where('is_active', true)
            ->whereDate('due_date', '<', $today->toDateString())
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->get();

        $overduePrincipal = 0.0;
        $overdueInterest = 0.0;
        $overdueFees = 0.0;
        $overduePenalties = 0.0;

        foreach ($overdueInstallments as $ins) {
            // Use per-installment component outstanding (scheduled - paid) so partial payments are handled.
            $overduePrincipal += max(0.0, (float) $ins->principal_due - (float) $ins->principal_paid);
            $overdueInterest += max(0.0, (float) $ins->interest_due - (float) $ins->interest_paid);
            $overdueFees += max(0.0, (float) $ins->fees_due - (float) $ins->fees_paid);
            $overduePenalties += max(0.0, (float) $ins->penalty_due - (float) $ins->penalty_paid);
        }

        $totalOverdue = round($overduePrincipal + $overdueInterest + $overdueFees + $overduePenalties, 2);

        // Days overdue is based on the oldest unpaid installment due date.
        $oldestDue = $overdueInstallments->first()?->due_date;
        $daysOverdue = 0;
        if ($oldestDue) {
            $daysOverdue = Carbon::parse($oldestDue)->startOfDay()->diffInDays($today, false);
            $daysOverdue = max(0, $daysOverdue);
        }

        return [
            'overdue_principal' => round($overduePrincipal, 2),
            'overdue_interest' => round($overdueInterest, 2),
            'overdue_fees' => round($overdueFees, 2),
            'overdue_penalties' => round($overduePenalties, 2),
            'overdue_amount' => $totalOverdue,
            'days_overdue' => $daysOverdue,
        ];
    }
}
