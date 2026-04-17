<?php

declare(strict_types=1);

namespace App\Services\Loans\Risk;

use App\Models\RiskSnapshot;
use Carbon\Carbon;

/**
 * Stress Testing Service
 *
 * Simulates "what-if" scenarios to assess portfolio resilience
 * under various stress conditions.
 */
class StressTestingService
{
    public function __construct(
        protected PortfolioRiskCalculator $portfolioRisk,
        protected LoanDelinquencyEngine $delinquencyEngine,
        protected ProvisionCalculationService $provisionService
    ) {
    }

    /**
     * Run a stress test scenario.
     *
     * Available scenarios:
     * - 'par_increase': Simulate PAR rate increase
     * - 'mass_default': Simulate mass defaults at specific DPD
     * - 'economic_downturn': Combined stress test
     * - 'sector_crisis': Concentrated sector impact
     *
     * @param string $scenario
     * @param array $params
     * @param array<int>|int|null $subshopIds
     * @return array
     */
    public function runScenario(string $scenario, array $params = [], array|int|null $subshopIds = null): array
    {
        return match ($scenario) {
            'par_increase' => $this->runParIncreaseScenario($params, $subshopIds),
            'mass_default' => $this->runMassDefaultScenario($params, $subshopIds),
            'economic_downturn' => $this->runEconomicDownturnScenario($params, $subshopIds),
            'sector_crisis' => $this->runSectorCrisisScenario($params, $subshopIds),
            default => throw new \InvalidArgumentException("Unknown scenario: {$scenario}"),
        };
    }

    /**
     * Run PAR increase scenario.
     *
     * Params:
     * - 'par30_increase': Percentage points to add to PAR30 (default: 5)
     * - 'par90_increase': Percentage points to add to PAR90 (default: 2)
     */
    protected function runParIncreaseScenario(array $params, array|int|null $subshopIds): array
    {
        $par30Increase = $params['par30_increase'] ?? 5;
        $par90Increase = $params['par90_increase'] ?? 2;

        $currentSummary = $this->delinquencyEngine->getPortfolioRiskSummaryCached($subshopIds);
        $currentProvision = $this->provisionService->calculatePortfolioProvision($subshopIds);

        // Calculate projected PAR rates
        $projectedPar30 = min(100, $currentSummary['par30'] + $par30Increase);
        $projectedPar90 = min(100, $currentSummary['par90'] + $par90Increase);

        // Estimate impact on portfolio
        $portfolioOutstanding = $currentSummary['portfolio_outstanding'];
        $currentPar30Amount = ($currentSummary['par30'] / 100) * $portfolioOutstanding;
        $projectedPar30Amount = ($projectedPar30 / 100) * $portfolioOutstanding;

        $currentPar90Amount = ($currentSummary['par90'] / 100) * $portfolioOutstanding;
        $projectedPar90Amount = ($projectedPar90 / 100) * $portfolioOutstanding;

        $additionalPar30 = $projectedPar30Amount - $currentPar30Amount;
        $additionalPar90 = $projectedPar90Amount - $currentPar90Amount;

        // Estimate provision impact (PAR90+ typically requires higher provision)
        $additionalProvision = $additionalPar90 * 0.5; // Assume 50% provision rate for new PAR90

        return [
            'scenario_name' => 'PAR Rate Increase',
            'description' => "PAR30 increases by {$par30Increase}pp, PAR90 increases by {$par90Increase}pp",
            'current_state' => [
                'par30_rate' => $currentSummary['par30'],
                'par90_rate' => $currentSummary['par90'],
                'total_provision' => $currentProvision['total_provision'],
            ],
            'projected_state' => [
                'par30_rate' => $projectedPar30,
                'par90_rate' => $projectedPar90,
                'additional_delinquent_amount' => round($additionalPar30 + $additionalPar90, 2),
                'additional_provision_required' => round($additionalProvision, 2),
            ],
            'impact' => [
                'provision_increase_percentage' => $currentProvision['total_provision'] > 0
                    ? round(($additionalProvision / $currentProvision['total_provision']) * 100, 2)
                    : 0,
                'risk_rating_change' => $this->assessRiskRatingChange($currentSummary['par90'], $projectedPar90),
            ],
        ];
    }

    /**
     * Run mass default scenario.
     *
     * Params:
     * - 'default_percentage': % of performing loans that default (default: 10)
     * - 'recovery_rate': Expected recovery percentage (default: 30)
     */
    protected function runMassDefaultScenario(array $params, array|int|null $subshopIds): array
    {
        $defaultPercentage = $params['default_percentage'] ?? 10;
        $recoveryRate = $params['recovery_rate'] ?? 30;

        $currentSummary = $this->delinquencyEngine->getPortfolioRiskSummaryCached($subshopIds);
        $portfolioOutstanding = $currentSummary['portfolio_outstanding'];

        // Assume performing loans = portfolio - PAR30 amount
        $par30Amount = ($currentSummary['par30'] / 100) * $portfolioOutstanding;
        $performingAmount = $portfolioOutstanding - $par30Amount;

        $defaultedAmount = $performingAmount * ($defaultPercentage / 100);
        $recoverableAmount = $defaultedAmount * ($recoveryRate / 100);
        $lossAmount = $defaultedAmount - $recoverableAmount;

        return [
            'scenario_name' => 'Mass Default Event',
            'description' => "{$defaultPercentage}% of performing loans default with {$recoveryRate}% recovery",
            'current_state' => [
                'performing_amount' => round($performingAmount, 2),
                'portfolio_outstanding' => $portfolioOutstanding,
            ],
            'impact' => [
                'defaulted_amount' => round($defaultedAmount, 2),
                'recoverable_amount' => round($recoverableAmount, 2),
                'estimated_loss' => round($lossAmount, 2),
                'loss_percentage' => $portfolioOutstanding > 0
                    ? round(($lossAmount / $portfolioOutstanding) * 100, 2)
                    : 0,
            ],
            'new_par90_rate' => min(100, $currentSummary['par90'] + (($defaultedAmount / $portfolioOutstanding) * 100)),
        ];
    }

    /**
     * Run economic downturn scenario.
     *
     * Combines multiple stress factors.
     */
    protected function runEconomicDownturnScenario(array $params, array|int|null $subshopIds): array
    {
        $downturnSeverity = $params['severity'] ?? 'moderate'; // mild, moderate, severe

        $severityFactors = [
            'mild' => ['par_increase' => 3, 'default_increase' => 5, 'recovery_reduction' => 10],
            'moderate' => ['par_increase' => 7, 'default_increase' => 15, 'recovery_reduction' => 20],
            'severe' => ['par_increase' => 15, 'default_increase' => 30, 'recovery_reduction' => 40],
        ];

        $factors = $severityFactors[$downturnSeverity] ?? $severityFactors['moderate'];

        $currentSummary = $this->delinquencyEngine->getPortfolioRiskSummaryCached($subshopIds);
        $portfolioOutstanding = $currentSummary['portfolio_outstanding'];

        // Calculate PAR impact
        $parIncrease = $factors['par_increase'];
        $projectedPar90 = min(100, $currentSummary['par90'] + $parIncrease);

        // Calculate default impact
        $performingAmount = $portfolioOutstanding * (1 - ($currentSummary['par30'] / 100));
        $additionalDefaultRate = $factors['default_increase'] / 100;
        $additionalDefaults = $performingAmount * $additionalDefaultRate;

        // Calculate provision impact
        $newNplRate = $projectedPar90 / 100;
        $additionalProvision = ($additionalDefaults * 0.5) + ($portfolioOutstanding * $newNplRate * 0.25);

        return [
            'scenario_name' => 'Economic Downturn',
            'description' => "{$downturnSeverity} economic downturn scenario",
            'severity_factors' => $factors,
            'current_state' => [
                'par90_rate' => $currentSummary['par90'],
                'portfolio_outstanding' => $portfolioOutstanding,
            ],
            'projected_state' => [
                'par90_rate' => $projectedPar90,
                'additional_defaults' => round($additionalDefaults, 2),
                'additional_provision_required' => round($additionalProvision, 2),
            ],
            'impact_assessment' => $this->generateImpactAssessment($additionalProvision, $portfolioOutstanding),
        ];
    }

    /**
     * Run sector crisis scenario.
     *
     * Tests concentration risk.
     */
    protected function runSectorCrisisScenario(array $params, array|int|null $subshopIds): array
    {
        $sector = $params['sector'] ?? 'agriculture';
        $impactPercentage = $params['impact_percentage'] ?? 50;

        // This would require sector data on loans
        // For now, return a template structure

        return [
            'scenario_name' => 'Sector Crisis',
            'description' => "{$impactPercentage}% of {$sector} loans become delinquent",
            'sector' => $sector,
            'impact_percentage' => $impactPercentage,
            'note' => 'Requires sector classification data on loans to calculate precise impact',
            'assumptions' => [
                'concentration_limit' => 25, // %
                'sector_correlation' => 'high',
            ],
        ];
    }

    /**
     * Assess risk rating change.
     */
    protected function assessRiskRatingChange(float $currentPar90, float $projectedPar90): string
    {
        if ($projectedPar90 > 15) {
            return 'critical_increase';
        }

        if ($projectedPar90 > $currentPar90 + 5) {
            return 'significant_increase';
        }

        if ($projectedPar90 > $currentPar90) {
            return 'moderate_increase';
        }

        return 'stable';
    }

    /**
     * Generate impact assessment.
     */
    protected function generateImpactAssessment(float $additionalProvision, float $portfolioOutstanding): array
    {
        $provisionImpact = $portfolioOutstanding > 0
            ? ($additionalProvision / $portfolioOutstanding) * 100
            : 0;

        return [
            'provision_impact_percentage' => round($provisionImpact, 2),
            'risk_level' => match (true) {
                $provisionImpact > 10 => 'critical',
                $provisionImpact > 5 => 'high',
                $provisionImpact > 2 => 'moderate',
                default => 'low',
            },
            'recommended_actions' => match (true) {
                $provisionImpact > 10 => [
                    'Immediately increase provision reserves',
                    'Review high-risk loans for write-off',
                    'Implement emergency collection procedures',
                ],
                $provisionImpact > 5 => [
                    'Increase provision reserves',
                    'Intensify collections on PAR60+ loans',
                    'Review credit policies',
                ],
                $provisionImpact > 2 => [
                    'Monitor portfolio closely',
                    'Increase collection follow-ups',
                ],
                default => [
                    'Continue normal monitoring',
                ],
            },
        ];
    }

    /**
     * Compare current portfolio against historical stress.
     *
     * @param array<int>|int|null $subshopIds
     * @return array
     */
    public function compareAgainstHistoricalStress(array|int|null $subshopIds = null): array
    {
        $current = $this->delinquencyEngine->getPortfolioRiskSummaryCached($subshopIds);

        // Get worst historical PAR90 in last 12 months
        $query = RiskSnapshot::where('snapshot_date', '>=', now()->subMonths(12))
            ->orderBy('par90_rate', 'desc');

        if (is_array($subshopIds) && !empty($subshopIds)) {
            $query->whereIn('subshop_id', $subshopIds);
        } elseif ($subshopIds) {
            $query->where('subshop_id', $subshopIds);
        } else {
            $query->whereNull('subshop_id');
        }

        $worstHistorical = $query->first();

        return [
            'current_par90' => $current['par90'],
            'historical_worst_par90' => $worstHistorical?->par90_rate ?? 0,
            'historical_worst_date' => $worstHistorical?->snapshot_date?->toDateString(),
            'comparison' => $worstHistorical
                ? ($current['par90'] > $worstHistorical->par90_rate ? 'above_historical_worst' : 'below_historical_worst')
                : 'no_historical_data',
            'stress_buffer' => $worstHistorical
                ? round($worstHistorical->par90_rate - $current['par90'], 2)
                : null,
        ];
    }
}
