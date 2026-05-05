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
            default => throw new \InvalidArgumentException("Unknown scenario: {$scenario}"),
        };
    }

    /**
     * Build a baseline snapshot used by all scenarios.
     *
     * @param array<int>|int|null $subshopIds
     */
    protected function baseline(array|int|null $subshopIds = null): array
    {
        $summary = $this->delinquencyEngine->getPortfolioRiskSummaryCached($subshopIds);
        $provision = $this->provisionService->calculatePortfolioProvision($subshopIds);

        $portfolioOutstanding = (float) ($summary['portfolio_outstanding'] ?? 0);
        $par30Rate = (float) ($summary['par30'] ?? 0);
        $par90Rate = (float) ($summary['par90'] ?? 0);

        $par30Amount = ($par30Rate / 100) * $portfolioOutstanding;
        $par90Amount = ($par90Rate / 100) * $portfolioOutstanding;

        $rates = [
            'par30' => (float) ($provision['breakdown']['par30']['rate'] ?? 5),
            'par60' => (float) ($provision['breakdown']['par60']['rate'] ?? 20),
            'par90' => (float) ($provision['breakdown']['par90']['rate'] ?? 50),
            'default' => (float) ($provision['breakdown']['default']['rate'] ?? 100),
        ];

        return [
            'summary' => $summary,
            'provision' => $provision,
            'portfolio_outstanding' => $portfolioOutstanding,
            'par30_rate' => $par30Rate,
            'par90_rate' => $par90Rate,
            'par30_amount' => round($par30Amount, 2),
            'par90_amount' => round($par90Amount, 2),
            'provision_rates' => $rates,
        ];
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
        $par30Increase = (float) ($params['par30_increase'] ?? 5);
        $par90Increase = (float) ($params['par90_increase'] ?? 2);

        $base = $this->baseline($subshopIds);
        $portfolioOutstanding = $base['portfolio_outstanding'];
        $rates = $base['provision_rates'];

        $currentPar30 = (float) $base['par30_rate'];
        $currentPar90 = (float) $base['par90_rate'];

        $projectedPar30 = min(100.0, max(0.0, $currentPar30 + $par30Increase));
        $projectedPar90 = min(100.0, max(0.0, $currentPar90 + $par90Increase));

        // PAR30 includes PAR90, so avoid double-counting by splitting into:
        // - "PAR30-only" band (30+ but <90)
        // - PAR90+ band
        $currentPar30Amount = ($currentPar30 / 100) * $portfolioOutstanding;
        $currentPar90Amount = ($currentPar90 / 100) * $portfolioOutstanding;
        $currentPar30OnlyAmount = max(0.0, $currentPar30Amount - $currentPar90Amount);

        $projectedPar30Amount = ($projectedPar30 / 100) * $portfolioOutstanding;
        $projectedPar90Amount = ($projectedPar90 / 100) * $portfolioOutstanding;
        $projectedPar30OnlyAmount = max(0.0, $projectedPar30Amount - $projectedPar90Amount);

        $additionalPar30Only = max(0.0, $projectedPar30OnlyAmount - $currentPar30OnlyAmount);
        $additionalPar90 = max(0.0, $projectedPar90Amount - $currentPar90Amount);
        $additionalDelinquent = $additionalPar30Only + $additionalPar90;

        $additionalProvision = ($additionalPar30Only * ($rates['par30'] / 100))
            + ($additionalPar90 * ($rates['par90'] / 100));

        $impactAssessment = $this->generateImpactAssessment((float) $additionalProvision, (float) $portfolioOutstanding);
        $impactAssessment['risk_rating_change'] = $this->assessRiskRatingChange($currentPar90, $projectedPar90);

        return [
            'scenario_key' => 'par_increase',
            'scenario_name' => 'PAR Rate Increase',
            'description' => "PAR30 increases by {$par30Increase}pp, PAR90 increases by {$par90Increase}pp",
            'params' => [
                'par30_increase' => $par30Increase,
                'par90_increase' => $par90Increase,
            ],
            'assumptions' => [
                'PAR30 includes PAR90; calculations split PAR30-only and PAR90+ to avoid double-counting.',
                'Provision impact uses configured provision rates from Risk Thresholds (fallbacks apply if not configured).',
                'This is an aggregate simulation (no loan-by-loan transition modelling).',
            ],
            'current_state' => [
                'portfolio_outstanding' => round($portfolioOutstanding, 2),
                'par30_rate' => round($currentPar30, 2),
                'par90_rate' => round($currentPar90, 2),
                'par30_amount' => round($currentPar30Amount, 2),
                'par90_amount' => round($currentPar90Amount, 2),
                'total_provision_required' => round((float) ($base['provision']['total_provision'] ?? 0), 2),
            ],
            'projected_state' => [
                'portfolio_outstanding' => round($portfolioOutstanding, 2),
                'par30_rate' => round($projectedPar30, 2),
                'par90_rate' => round($projectedPar90, 2),
                'additional_delinquent_amount' => round($additionalDelinquent, 2),
                'additional_provision_required' => round($additionalProvision, 2),
            ],
            'impact_assessment' => $impactAssessment,
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
        $defaultPercentage = (float) ($params['default_percentage'] ?? 10);
        $recoveryRate = (float) ($params['recovery_rate'] ?? 30);

        $base = $this->baseline($subshopIds);
        $portfolioOutstanding = $base['portfolio_outstanding'];
        $rates = $base['provision_rates'];

        // Performing exposure approximation: portfolio - PAR30 exposure
        $performingAmount = max(0.0, $portfolioOutstanding - (float) $base['par30_amount']);

        $defaultedAmount = $performingAmount * ($defaultPercentage / 100);
        $recoverableAmount = $defaultedAmount * ($recoveryRate / 100);
        $lossAmount = $defaultedAmount - $recoverableAmount;

        $additionalProvision = $defaultedAmount * ($rates['default'] / 100);
        $newPar90Rate = $portfolioOutstanding > 0
            ? min(100.0, (float) $base['par90_rate'] + (($defaultedAmount / $portfolioOutstanding) * 100))
            : (float) $base['par90_rate'];

        $impactAssessment = $this->generateImpactAssessment((float) $additionalProvision, (float) $portfolioOutstanding);
        $impactAssessment['loss_percentage'] = $portfolioOutstanding > 0
            ? round(($lossAmount / $portfolioOutstanding) * 100, 2)
            : 0;

        return [
            'scenario_key' => 'mass_default',
            'scenario_name' => 'Mass Default Event',
            'description' => "{$defaultPercentage}% of performing loans default with {$recoveryRate}% recovery",
            'params' => [
                'default_percentage' => $defaultPercentage,
                'recovery_rate' => $recoveryRate,
            ],
            'assumptions' => [
                'Performing exposure is approximated as portfolio outstanding minus PAR30 exposure.',
                'All new defaults are treated as DEFAULT bucket for provisioning purposes.',
                'This does not model staggered recoveries over time.',
            ],
            'current_state' => [
                'portfolio_outstanding' => round($portfolioOutstanding, 2),
                'performing_amount' => round($performingAmount, 2),
                'par30_rate' => round((float) $base['par30_rate'], 2),
                'par90_rate' => round((float) $base['par90_rate'], 2),
                'par30_amount' => round((float) $base['par30_amount'], 2),
                'par90_amount' => round((float) $base['par90_amount'], 2),
                'total_provision_required' => round((float) ($base['provision']['total_provision'] ?? 0), 2),
            ],
            'projected_state' => [
                'new_par90_rate' => round($newPar90Rate, 2),
                'defaulted_amount' => round($defaultedAmount, 2),
                'recoverable_amount' => round($recoverableAmount, 2),
                'estimated_loss' => round($lossAmount, 2),
                'projected_par90_amount' => round(($newPar90Rate / 100) * $portfolioOutstanding, 2),
                'additional_delinquent_amount' => round($defaultedAmount, 2),
                'additional_provision_required' => round($additionalProvision, 2),
            ],
            'impact_assessment' => $impactAssessment,
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

        $base = $this->baseline($subshopIds);
        $portfolioOutstanding = $base['portfolio_outstanding'];
        $rates = $base['provision_rates'];

        // Calculate PAR impact
        $parIncrease = $factors['par_increase'];
        $projectedPar90 = min(100.0, (float) $base['par90_rate'] + (float) $parIncrease);
        $projectedPar30 = min(100.0, (float) $base['par30_rate'] + (float) ($parIncrease * 0.6));

        // Calculate default impact
        $performingAmount = max(0.0, $portfolioOutstanding - (float) $base['par30_amount']);
        $additionalDefaultRate = $factors['default_increase'] / 100;
        $additionalDefaults = $performingAmount * $additionalDefaultRate;

        // Calculate provision impact
        $currentPar90Amount = (float) $base['par90_amount'];
        $projectedPar90Amount = ($projectedPar90 / 100) * $portfolioOutstanding;
        $additionalPar90 = max(0.0, $projectedPar90Amount - $currentPar90Amount);
        $additionalProvision = ($additionalPar90 * ($rates['par90'] / 100))
            + ($additionalDefaults * ($rates['default'] / 100));

        $impactAssessment = $this->generateImpactAssessment((float) $additionalProvision, (float) $portfolioOutstanding);
        $impactAssessment['severity_score'] = match ($downturnSeverity) {
            'severe' => 90,
            'moderate' => 60,
            default => 30,
        };

        return [
            'scenario_key' => 'economic_downturn',
            'scenario_name' => 'Economic Downturn',
            'description' => "{$downturnSeverity} economic downturn scenario",
            'severity_factors' => $factors,
            'params' => [
                'severity' => $downturnSeverity,
            ],
            'assumptions' => [
                'PAR90 increases due to worsening repayment and liquidity pressure.',
                'Additional defaults are estimated from performing exposure and treated as DEFAULT for provisioning.',
                'Projected PAR30 is approximated as 60% of the PAR90 shock (to keep UI simple).',
            ],
            'current_state' => [
                'portfolio_outstanding' => round($portfolioOutstanding, 2),
                'par30_rate' => round((float) $base['par30_rate'], 2),
                'par90_rate' => round((float) $base['par90_rate'], 2),
                'par30_amount' => round((float) $base['par30_amount'], 2),
                'par90_amount' => round((float) $base['par90_amount'], 2),
                'total_provision_required' => round((float) ($base['provision']['total_provision'] ?? 0), 2),
            ],
            'projected_state' => [
                'par30_rate' => round($projectedPar30, 2),
                'par90_rate' => round($projectedPar90, 2),
                'projected_par90_amount' => round(($projectedPar90 / 100) * $portfolioOutstanding, 2),
                'additional_defaults' => round($additionalDefaults, 2),
                'additional_provision_required' => round($additionalProvision, 2),
            ],
            'impact_assessment' => $impactAssessment,
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
