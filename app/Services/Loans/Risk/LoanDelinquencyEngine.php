<?php

namespace App\Services\Loans\Risk;

use App\Models\LoanInstallments;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class LoanDelinquencyEngine
{
    public function __construct(
        protected PortfolioRiskCalculator $portfolioRiskCalculator
    ) {
    }

    /**
     * Calculate Portfolio at Risk (PAR) for a given threshold in days.
     *
     * Banking / microfinance formula:
     * PAR(days) = Outstanding balance of delinquent loans (days) / Total outstanding loan portfolio
     *
     * Returned value is a percentage (0 - 100).
     */
    public function calculatePAR(int $days, ?int $subshopId = null): float
    {
        $days = max(0, (int) $days);

        $totalPortfolio = $subshopId
            ? $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops([$subshopId])
            : $this->portfolioRiskCalculator->calculateTotalPortfolioOutstanding();
        
        if ($totalPortfolio <= 0) {
            return 0.0;
        }

        $delinquentOutstanding = $this->portfolioRiskCalculator->calculateDelinquentOutstanding($days, $subshopId);

        $par = ($delinquentOutstanding / $totalPortfolio) * 100;

        return round(max(0.0, $par), 2);
    }

    public function calculatePAR30(?int $subshopId = null): float
    {
        return $this->calculatePAR(30, $subshopId);
    }

    public function calculatePAR60(?int $subshopId = null): float
    {
        return $this->calculatePAR(60, $subshopId);
    }

    public function calculatePAR90(?int $subshopId = null): float
    {
        return $this->calculatePAR(90, $subshopId);
    }

    /**
     * Get delinquent loans for a PAR bucket.
     *
     * A loan is delinquent for a given bucket when at least one installment is:
     * - status = overdue
     * - and days overdue > $days
     */
    public function getDelinquentLoans(int $days, ?int $subshopId = null): Collection
    {
        $days = max(0, (int) $days);
        $cutoffDate = Carbon::today()->subDays($days);

        $loanIds = LoanInstallments::query()
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoffDate)
            ->distinct()
            ->pluck('loan_id');

        if ($loanIds->isEmpty()) {
            return new Collection();
        }

        $query = $this->portfolioRiskCalculator
            ->activeLoansQuery()
            ->whereIn('id', $loanIds);

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        $loans = $query->get();

        return $loans
            ->filter(function (Loans $loan) {
                return $this->portfolioRiskCalculator->calculateLoanOutstanding($loan) > 0;
            })
            ->values();
    }

    /**
     * Helper: calculate days overdue for an installment.
     *
     * If installment is not overdue yet, returns 0.
     */
    public function calculateDaysOverdue(LoanInstallments $installment): int
    {
        $dueDate = $installment->due_date instanceof Carbon
            ? $installment->due_date
            : Carbon::parse($installment->due_date);

        $days = Carbon::today()->diffInDays($dueDate, false) * -1;

        return max(0, (int) $days);
    }

    /**
     * OPTIONAL banking feature: classify a single loan into a risk bucket.
     *
     * Uses the maximum days overdue among overdue installments.
     */
    public function classifyLoanRisk(Loans $loan): string
    {
        $isPortfolioLoan = (bool) ($loan->is_active ?? false)
            && in_array((string) $loan->status, ['disbursed', 'partially_paid', 'defaulted'], true)
            && !(bool) ($loan->is_written_off ?? false);

        if (!$isPortfolioLoan) {
            return 'current';
        }

        if ($this->portfolioRiskCalculator->calculateLoanOutstanding($loan) <= 0) {
            return 'current';
        }

        $maxDaysOverdue = (int) LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->get(['due_date'])
            ->map(function ($i) {
                $dueDate = $i->due_date instanceof Carbon ? $i->due_date : Carbon::parse($i->due_date);
                return max(0, Carbon::today()->diffInDays($dueDate, false) * -1);
            })
            ->max();

        if ($maxDaysOverdue <= 0) {
            return 'current';
        }

        if ($maxDaysOverdue <= 30) {
            return 'par30';
        }

        if ($maxDaysOverdue <= 60) {
            return 'par60';
        }

        if ($maxDaysOverdue <= 90) {
            return 'par90';
        }

        return 'default';
    }

    /**
     * Get a summary of the portfolio risk.
     *
     * @return array{portfolio_outstanding: float, par30: float, par60: float, par90: float, par180: float}
     */
    public function getPortfolioRiskSummary(?int $subshopId = null): array
    {
        return [
            'portfolio_outstanding' => $subshopId
                ? $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops([$subshopId])
                : $this->portfolioRiskCalculator->calculateTotalPortfolioOutstanding(),
            'par30' => $this->calculatePAR(30, $subshopId),
            'par60' => $this->calculatePAR(60, $subshopId),
            'par90' => $this->calculatePAR(90, $subshopId),
            'par180' => $this->calculatePAR(180, $subshopId),
        ];
    }
}
