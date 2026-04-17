<?php

declare(strict_types=1);

namespace App\Services\Loans\Risk;

use App\Models\Loans;
use App\Models\RiskThreshold;
use Illuminate\Support\Collection;

/**
 * Provision Calculation Service
 *
 * Calculates loan loss provisions based on risk classifications
 * using configurable provision rates from risk thresholds.
 */
class ProvisionCalculationService
{
    public function __construct(
        protected PortfolioRiskCalculator $portfolioRisk,
        protected DpdCalculator $dpdCalculator,
        protected LoanDelinquencyEngine $delinquencyEngine
    ) {
    }

    /**
     * Calculate provision for a single loan.
     *
     * @param Loans $loan
     * @param RiskThreshold|null $thresholds
     * @return array{outstanding: float, provision_rate: float, provision_amount: float}
     */
    public function calculateLoanProvision(Loans $loan, ?RiskThreshold $thresholds = null): array
    {
        $thresholds = $thresholds ?? RiskThreshold::global()->active()->first();

        $outstanding = $this->portfolioRisk->calculateLoanOutstandingCached($loan);

        if ($outstanding <= 0) {
            return [
                'outstanding' => 0,
                'provision_rate' => 0,
                'provision_amount' => 0,
            ];
        }

        $maxDpd = $this->dpdCalculator->calculateMaxDaysOverdueForLoan($loan->id);
        $riskStatus = $this->dpdCalculator->classifyByDpd($maxDpd);

        $provisionRate = $thresholds ? $thresholds->getProvisionRate($riskStatus) : $this->getDefaultProvisionRate($riskStatus);
        $provisionAmount = $outstanding * ($provisionRate / 100);

        return [
            'outstanding' => round($outstanding, 2),
            'provision_rate' => $provisionRate,
            'provision_amount' => round($provisionAmount, 2),
            'risk_status' => $riskStatus,
            'max_dpd' => $maxDpd,
        ];
    }

    /**
     * Calculate total provisions for the portfolio.
     *
     * @param array<int>|int|null $subshopIds
     * @param RiskThreshold|null $thresholds
     * @return array{total_outstanding: float, total_provision: float, breakdown: array}
     */
    public function calculatePortfolioProvision(array|int|null $subshopIds = null, ?RiskThreshold $thresholds = null): array
    {
        $subshopId = is_array($subshopIds) ? null : $subshopIds;
        $thresholds = $thresholds ?? RiskThreshold::forSubshop($subshopId);

        $activeLoansQuery = $this->portfolioRisk->activeLoansQuery();
        if (is_array($subshopIds) && !empty($subshopIds)) {
            $activeLoansQuery->whereIn('subshop_id', $subshopIds);
        } elseif ($subshopIds) {
            $activeLoansQuery->where('subshop_id', $subshopIds);
        }

        $loanIds = $activeLoansQuery->pluck('id')->toArray();

        if (empty($loanIds)) {
            return [
                'total_outstanding' => 0,
                'total_provision' => 0,
                'breakdown' => [],
            ];
        }

        $outstandingMap = $this->portfolioRisk->bulkCalculateOutstanding($loanIds);
        $maxDpdMap = $this->dpdCalculator->bulkCalculateMaxDpd($loanIds);

        $breakdown = [
            'current' => ['count' => 0, 'outstanding' => 0, 'provision' => 0, 'rate' => 0],
            'par30' => ['count' => 0, 'outstanding' => 0, 'provision' => 0, 'rate' => 0],
            'par60' => ['count' => 0, 'outstanding' => 0, 'provision' => 0, 'rate' => 0],
            'par90' => ['count' => 0, 'outstanding' => 0, 'provision' => 0, 'rate' => 0],
            'default' => ['count' => 0, 'outstanding' => 0, 'provision' => 0, 'rate' => 0],
        ];

        $totalOutstanding = 0;
        $totalProvision = 0;

        foreach ($loanIds as $loanId) {
            $outstanding = $outstandingMap[$loanId] ?? 0;
            if ($outstanding <= 0) {
                continue;
            }

            $maxDpd = $maxDpdMap[$loanId] ?? 0;
            $riskStatus = $this->dpdCalculator->classifyByDpd($maxDpd);
            $provisionRate = $thresholds ? $thresholds->getProvisionRate($riskStatus) : $this->getDefaultProvisionRate($riskStatus);
            $provisionAmount = $outstanding * ($provisionRate / 100);

            $breakdown[$riskStatus]['count']++;
            $breakdown[$riskStatus]['outstanding'] += $outstanding;
            $breakdown[$riskStatus]['provision'] += $provisionAmount;
            $breakdown[$riskStatus]['rate'] = $provisionRate;

            $totalOutstanding += $outstanding;
            $totalProvision += $provisionAmount;
        }

        // Round all values
        foreach ($breakdown as $status => $data) {
            $breakdown[$status]['outstanding'] = round($data['outstanding'], 2);
            $breakdown[$status]['provision'] = round($data['provision'], 2);
        }

        return [
            'total_outstanding' => round($totalOutstanding, 2),
            'total_provision' => round($totalProvision, 2),
            'provision_percentage' => $totalOutstanding > 0 ? round(($totalProvision / $totalOutstanding) * 100, 2) : 0,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate provisions for a collection of loans.
     *
     * @param Collection<Loans> $loans
     * @param RiskThreshold|null $thresholds
     * @return array<int, array>
     */
    public function calculateLoansProvision(Collection $loans, ?RiskThreshold $thresholds = null): array
    {
        $thresholds = $thresholds ?? RiskThreshold::global()->active()->first();

        $results = [];

        foreach ($loans as $loan) {
            $results[$loan->id] = $this->calculateLoanProvision($loan, $thresholds);
        }

        return $results;
    }

    /**
     * Get default provision rate when no thresholds configured.
     */
    protected function getDefaultProvisionRate(string $riskStatus): float
    {
        return match ($riskStatus) {
            'current' => 0,
            'par30' => 5,
            'par60' => 20,
            'par90' => 50,
            'default' => 100,
            default => 0,
        };
    }

    /**
     * Generate provision report.
     *
     * @param array<int>|int|null $subshopIds
     * @return array
     */
    public function generateProvisionReport(array|int|null $subshopIds = null): array
    {
        $subshopId = is_array($subshopIds) ? null : $subshopIds;
        $thresholds = RiskThreshold::forSubshop($subshopId);
        $calculation = $this->calculatePortfolioProvision($subshopIds, $thresholds);

        $report = [
            'generated_at' => now()->toDateTimeString(),
            'subshop_id' => $subshopIds,
            'thresholds_used' => $thresholds ? [
                'par30_rate' => $thresholds->provision_rate_par30,
                'par60_rate' => $thresholds->provision_rate_par60,
                'par90_rate' => $thresholds->provision_rate_par90,
                'default_rate' => $thresholds->provision_rate_default,
            ] : 'default_rates',
            'summary' => [
                'total_outstanding' => $calculation['total_outstanding'],
                'total_provision_required' => $calculation['total_provision'],
                'provision_percentage' => $calculation['provision_percentage'],
            ],
            'breakdown' => $calculation['breakdown'],
        ];

        // Calculate impact of each bucket
        foreach ($report['breakdown'] as $status => $data) {
            $report['breakdown'][$status]['percentage_of_portfolio'] =
                $calculation['total_outstanding'] > 0
                    ? round(($data['outstanding'] / $calculation['total_outstanding']) * 100, 2)
                    : 0;
        }

        return $report;
    }
}
