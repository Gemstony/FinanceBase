<?php

namespace App\Services\Loans\Risk;

use App\Models\LoanInstallments;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PortfolioRiskCalculator
{
    /**
     * Calculate the outstanding balance for a single loan.
     *
     * Financial logic (microfinance / banking standard):
     * Outstanding balance is the sum of unpaid components across all installments.
     *
     * For each installment:
     * (principal_due - principal_paid)
     * + (interest_due - interest_paid)
     * + (fees_due - fees_paid)
     * + (penalty_due - penalty_paid)
     *
     * We never allow negative outstanding values (overpayments or data issues).
     */
    public function calculateLoanOutstanding(Loans $loan): float
    {
        $total = 0.0;

        LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->select([
                'principal_due',
                'principal_paid',
                'interest_due',
                'interest_paid',
                'fees_due',
                'fees_paid',
                'penalty_due',
                'penalty_paid',
            ])
            ->chunk(500, function ($installments) use (&$total) {
                foreach ($installments as $i) {
                    $principal = max(0.0, (float) $i->principal_due - (float) $i->principal_paid);
                    $interest = max(0.0, (float) $i->interest_due - (float) $i->interest_paid);
                    $fees = max(0.0, (float) $i->fees_due - (float) $i->fees_paid);
                    $penalty = max(0.0, (float) $i->penalty_due - (float) $i->penalty_paid);

                    $total += ($principal + $interest + $fees + $penalty);
                }
            });

        return round($total, 2);
    }

    /**
     * Calculate total outstanding for the entire loan portfolio.
     *
     * Portfolio outstanding is computed as the sum of outstanding for all active loans.
     * This is a key denominator in PAR calculations.
     */
    public function calculateTotalPortfolioOutstanding(): float
    {
        $total = 0.0;

        $this->activeLoansQuery()
            ->select(['id'])
            ->chunkById(200, function ($loans) use (&$total) {
                foreach ($loans as $loan) {
                    $outstanding = $this->calculateLoanOutstanding($loan);
                    if ($outstanding > 0) {
                        $total += $outstanding;
                    }
                }
            });

        return round($total, 2);
    }

    /**
     * Calculate the outstanding balance of delinquent loans for the given threshold.
     *
     * A loan is considered delinquent for a given PAR bucket when it has at least one
     * installment that is overdue by more than $days.
     *
     * This method returns the sum of outstanding balances of those delinquent loans.
     */
    public function calculateDelinquentOutstanding(int $days): float
    {
        $days = max(0, (int) $days);
        $cutoffDate = Carbon::today()->subDays($days);

        $delinquentLoanIds = LoanInstallments::query()
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoffDate)
            ->distinct()
            ->pluck('loan_id');

        if ($delinquentLoanIds->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;

        $this->activeLoansQuery()
            ->whereIn('id', $delinquentLoanIds)
            ->select(['id'])
            ->chunkById(200, function ($loans) use (&$total) {
                foreach ($loans as $loan) {
                    $outstanding = $this->calculateLoanOutstanding($loan);
                    if ($outstanding > 0) {
                        $total += $outstanding;
                    }
                }
            });

        return round($total, 2);
    }

    /**
     * Calculate total outstanding for loans within specific subshops.
     *
     * Uses the same active loan definition and per-loan outstanding calculation.
     */
    public function calculateTotalPortfolioOutstandingForSubshops($subshopIds): float
    {
        $total = 0.0;

        $this->activeLoansQuery()
            ->whereIn('subshop_id', $subshopIds)
            ->select(['id'])
            ->chunkById(200, function ($loans) use (&$total) {
                foreach ($loans as $loan) {
                    $outstanding = $this->calculateLoanOutstanding($loan);
                    if ($outstanding > 0) {
                        $total += $outstanding;
                    }
                }
            });

        return round($total, 2);
    }

    /**
     * Active loans query used across calculations.
     *
     * In most microfinance systems, PAR is measured on the active portfolio.
     * We exclude soft-deleted rows automatically (SoftDeletes).
     */
    public function activeLoansQuery(): Builder
    {
        return \App\Models\Loans::query()
            ->where('is_active', true)
            ->whereIn('status', [
                'disbursed',
                'partially_paid',
                'defaulted',
            ])
            ->where('is_written_off', false);
    }
}
