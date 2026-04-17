<?php

declare(strict_types=1);

namespace App\Services\Loans\Risk;

use App\Models\RiskSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Risk Snapshot Service
 *
 * Creates and manages daily risk snapshots for historical tracking
 * and trend analysis. This provides efficient access to historical
 * risk metrics without recalculating from raw data.
 */
class RiskSnapshotService
{
    public function __construct(
        protected PortfolioRiskCalculator $portfolioRisk,
        protected LoanDelinquencyEngine $delinquencyEngine,
        protected DpdCalculator $dpdCalculator
    ) {
    }

    /**
     * Create a risk snapshot for a given date and subshop.
     *
     * @param Carbon|null $date Date for snapshot (defaults to today)
     * @param int|null $subshopId Subshop ID (null for portfolio-wide)
     * @param string|null $createdBy User creating the snapshot
     * @return RiskSnapshot
     */
    public function createSnapshot(?Carbon $date = null, ?int $subshopId = null, ?string $createdBy = null): RiskSnapshot
    {
        $date = $date ?? Carbon::today();
        $asOfDate = $date->copy()->endOfDay();

        // Check if snapshot already exists
        $existing = RiskSnapshot::where('snapshot_date', $date->toDateString())
            ->where('subshop_id', $subshopId)
            ->first();

        if ($existing) {
            Log::info("Risk snapshot already exists for {$date->toDateString()}, subshop: {$subshopId}");
            return $existing;
        }

        // Calculate all metrics
        $metrics = $this->calculateMetrics($asOfDate, $subshopId);

        // Create snapshot
        $snapshot = RiskSnapshot::create([
            'snapshot_date' => $date->toDateString(),
            'subshop_id' => $subshopId,
            'portfolio_outstanding' => $metrics['portfolio_outstanding'],
            'total_active_loans' => $metrics['total_active_loans'],
            'performing_loans' => $metrics['performing_loans'],
            'delinquent_loans' => $metrics['delinquent_loans'],
            'par30_rate' => $metrics['par30_rate'],
            'par60_rate' => $metrics['par60_rate'],
            'par90_rate' => $metrics['par90_rate'],
            'par180_rate' => $metrics['par180_rate'],
            'par30_amount' => $metrics['par30_amount'],
            'par60_amount' => $metrics['par60_amount'],
            'par90_amount' => $metrics['par90_amount'],
            'par180_amount' => $metrics['par180_amount'],
            'npl_rate' => $metrics['par90_rate'],
            'npl_amount' => $metrics['par90_amount'],
            'current_count' => $metrics['current_count'],
            'par30_count' => $metrics['par30_count'],
            'par60_count' => $metrics['par60_count'],
            'par90_count' => $metrics['par90_count'],
            'default_count' => $metrics['default_count'],
            'created_by' => $createdBy,
            'notes' => "Auto-generated snapshot for {$date->toDateString()}",
        ]);

        Log::info("Created risk snapshot for {$date->toDateString()}, subshop: {$subshopId}");

        return $snapshot;
    }

    /**
     * Create a portfolio-wide snapshot for a shop.
     *
     * @param Carbon|null $date Date for snapshot (defaults to today)
     * @param int $shopId Shop ID for portfolio-wide snapshot
     * @param string|null $createdBy User creating the snapshot
     * @return RiskSnapshot
     */
    public function createPortfolioSnapshot(?Carbon $date = null, int $shopId, ?string $createdBy = null): RiskSnapshot
    {
        $date = $date ?? Carbon::today();
        $asOfDate = $date->copy()->endOfDay();

        // Check if snapshot already exists
        $existing = RiskSnapshot::where('snapshot_date', $date->toDateString())
            ->where('shop_id', $shopId)
            ->whereNull('subshop_id')
            ->first();

        if ($existing) {
            Log::info("Portfolio risk snapshot already exists for {$date->toDateString()}, shop: {$shopId}");
            return $existing;
        }

        // Calculate all metrics for the shop
        $metrics = $this->calculateMetricsForShop($asOfDate, $shopId);

        // Create snapshot
        $snapshot = RiskSnapshot::create([
            'snapshot_date' => $date->toDateString(),
            'subshop_id' => null, // NULL indicates portfolio-wide
            'shop_id' => $shopId,
            'portfolio_outstanding' => $metrics['portfolio_outstanding'],
            'total_active_loans' => $metrics['total_active_loans'],
            'performing_loans' => $metrics['performing_loans'],
            'delinquent_loans' => $metrics['delinquent_loans'],
            'par30_rate' => $metrics['par30_rate'],
            'par60_rate' => $metrics['par60_rate'],
            'par90_rate' => $metrics['par90_rate'],
            'par180_rate' => $metrics['par180_rate'],
            'par30_amount' => $metrics['par30_amount'],
            'par60_amount' => $metrics['par60_amount'],
            'par90_amount' => $metrics['par90_amount'],
            'par180_amount' => $metrics['par180_amount'],
            'npl_rate' => $metrics['par90_rate'],
            'npl_amount' => $metrics['par90_amount'],
            'current_count' => $metrics['current_count'],
            'par30_count' => $metrics['par30_count'],
            'par60_count' => $metrics['par60_count'],
            'par90_count' => $metrics['par90_count'],
            'default_count' => $metrics['default_count'],
            'created_by' => $createdBy,
            'notes' => "Portfolio snapshot for shop {$shopId} on {$date->toDateString()}",
        ]);

        Log::info("Created portfolio risk snapshot for {$date->toDateString()}, shop: {$shopId}");

        return $snapshot;
    }

    /**
     * Calculate all risk metrics for a snapshot.
     *
     * @param Carbon $asOfDate
     * @param int|null $subshopId
     * @return array
     */
    protected function calculateMetrics(Carbon $asOfDate, ?int $subshopId): array
    {
        // Get active loans query
        $activeLoansQuery = $this->portfolioRisk->activeLoansQuery();

        if ($subshopId !== null) {
            // Specific subshop
            $activeLoansQuery->where('subshop_id', $subshopId);
        }
        // If subshopId is null, get all loans (but caller should use calculateMetricsForShop for shop-specific)

        $loanIds = $activeLoansQuery->pluck('id')->toArray();

        if (empty($loanIds)) {
            return $this->getEmptyMetrics();
        }

        // Calculate portfolio outstanding
        $outstandingMap = $this->portfolioRisk->bulkCalculateOutstanding($loanIds);
        $portfolioOutstanding = array_sum($outstandingMap);

        // Count loans with outstanding balance
        $activeLoanIds = array_filter($loanIds, fn($id) => ($outstandingMap[$id] ?? 0) > 0);
        $totalActiveLoans = count($activeLoanIds);

        // Get DPD for all loans
        $dpdMap = $this->dpdCalculator->bulkCalculateMaxDpd($activeLoanIds, $asOfDate);

        // Classify loans by risk
        $riskDistribution = $this->classifyLoansByRisk($activeLoanIds, $dpdMap, $outstandingMap);

        // Calculate PAR rates
        $par30Amount = $this->calculateParAmount($activeLoanIds, $dpdMap, $outstandingMap, 1, 30);
        $par60Amount = $this->calculateParAmount($activeLoanIds, $dpdMap, $outstandingMap, 31, 60);
        $par90Amount = $this->calculateParAmount($activeLoanIds, $dpdMap, $outstandingMap, 61, 90);
        $par180Amount = $this->calculateParAmount($activeLoanIds, $dpdMap, $outstandingMap, 91, null);

        $par30Rate = $portfolioOutstanding > 0 ? round(($par30Amount / $portfolioOutstanding) * 100, 2) : 0;
        $par60Rate = $portfolioOutstanding > 0 ? round(($par60Amount / $portfolioOutstanding) * 100, 2) : 0;
        $par90Rate = $portfolioOutstanding > 0 ? round(($par90Amount / $portfolioOutstanding) * 100, 2) : 0;
        $par180Rate = $portfolioOutstanding > 0 ? round(($par180Amount / $portfolioOutstanding) * 100, 2) : 0;

        return [
            'portfolio_outstanding' => $portfolioOutstanding,
            'total_active_loans' => $totalActiveLoans,
            'performing_loans' => $riskDistribution['current'],
            'delinquent_loans' => $totalActiveLoans - $riskDistribution['current'],
            'par30_rate' => $par30Rate,
            'par60_rate' => $par60Rate,
            'par90_rate' => $par90Rate,
            'par180_rate' => $par180Rate,
            'par30_amount' => $par30Amount,
            'par60_amount' => $par60Amount,
            'par90_amount' => $par90Amount,
            'par180_amount' => $par180Amount,
            'current_count' => $riskDistribution['current'],
            'par30_count' => $riskDistribution['par30'],
            'par60_count' => $riskDistribution['par60'],
            'par90_count' => $riskDistribution['par90'],
            'default_count' => $riskDistribution['default'],
        ];
    }

    /**
     * Classify loans by risk category.
     */
    protected function classifyLoansByRisk(array $loanIds, array $dpdMap, array $outstandingMap): array
    {
        $distribution = [
            'current' => 0,
            'par30' => 0,
            'par60' => 0,
            'par90' => 0,
            'default' => 0,
        ];

        foreach ($loanIds as $loanId) {
            $outstanding = $outstandingMap[$loanId] ?? 0;
            if ($outstanding <= 0) {
                continue;
            }

            $dpd = $dpdMap[$loanId] ?? 0;
            $category = $this->dpdCalculator->classifyByDpd($dpd);
            $distribution[$category]++;
        }

        return $distribution;
    }

    /**
     * Calculate PAR amount for a DPD range.
     */
    protected function calculateParAmount(
        array $loanIds,
        array $dpdMap,
        array $outstandingMap,
        int $minDpd,
        ?int $maxDpd
    ): float {
        $total = 0.0;

        foreach ($loanIds as $loanId) {
            $dpd = $dpdMap[$loanId] ?? 0;

            if ($dpd < $minDpd) {
                continue;
            }

            if ($maxDpd !== null && $dpd > $maxDpd) {
                continue;
            }

            $total += $outstandingMap[$loanId] ?? 0;
        }

        return round($total, 2);
    }

    /**
     * Get empty metrics structure.
     */
    protected function getEmptyMetrics(): array
    {
        return [
            'portfolio_outstanding' => 0,
            'total_active_loans' => 0,
            'performing_loans' => 0,
            'delinquent_loans' => 0,
            'par30_rate' => 0,
            'par60_rate' => 0,
            'par90_rate' => 0,
            'par180_rate' => 0,
            'par30_amount' => 0,
            'par60_amount' => 0,
            'par90_amount' => 0,
            'par180_amount' => 0,
            'current_count' => 0,
            'par30_count' => 0,
            'par60_count' => 0,
            'par90_count' => 0,
            'default_count' => 0,
        ];
    }

    /**
     * Calculate all risk metrics for a shop (portfolio-wide).
     *
     * @param Carbon $asOfDate
     * @param int $shopId
     * @return array
     */
    protected function calculateMetricsForShop(Carbon $asOfDate, int $shopId): array
    {
        // Get all subshop IDs for this shop
        $shopSubshopIds = \App\Models\SubShop::where('shop_id', $shopId)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        // Get active loans query scoped to this shop's subshops
        $activeLoansQuery = $this->portfolioRisk->activeLoansQuery();

        if (!empty($shopSubshopIds)) {
            $activeLoansQuery->whereIn('subshop_id', $shopSubshopIds);
        }

        $loanIds = $activeLoansQuery->pluck('id')->toArray();

        if (empty($loanIds)) {
            return $this->getEmptyMetrics();
        }

        // Calculate portfolio outstanding
        $outstandingMap = $this->portfolioRisk->bulkCalculateOutstanding($loanIds);
        $portfolioOutstanding = array_sum($outstandingMap);

        // Count loans with outstanding balance
        $activeLoanIds = array_filter($loanIds, fn($id) => ($outstandingMap[$id] ?? 0) > 0);
        $totalActiveLoans = count($activeLoanIds);

        // Get DPD for all loans
        $dpdMap = $this->dpdCalculator->bulkCalculateMaxDpd($activeLoanIds, $asOfDate);

        // Classify loans by risk
        $riskDistribution = $this->classifyLoansByRisk($activeLoanIds, $dpdMap, $outstandingMap);

        // Calculate PAR rates
        $par30Amount = $this->calculateParAmount($activeLoanIds, $dpdMap, $outstandingMap, 1, 30);
        $par60Amount = $this->calculateParAmount($activeLoanIds, $dpdMap, $outstandingMap, 31, 60);
        $par90Amount = $this->calculateParAmount($activeLoanIds, $dpdMap, $outstandingMap, 61, 90);
        $par180Amount = $this->calculateParAmount($activeLoanIds, $dpdMap, $outstandingMap, 91, null);

        $par30Rate = $portfolioOutstanding > 0 ? round(($par30Amount / $portfolioOutstanding) * 100, 2) : 0;
        $par60Rate = $portfolioOutstanding > 0 ? round(($par60Amount / $portfolioOutstanding) * 100, 2) : 0;
        $par90Rate = $portfolioOutstanding > 0 ? round(($par90Amount / $portfolioOutstanding) * 100, 2) : 0;
        $par180Rate = $portfolioOutstanding > 0 ? round(($par180Amount / $portfolioOutstanding) * 100, 2) : 0;

        return [
            'portfolio_outstanding' => $portfolioOutstanding,
            'total_active_loans' => $totalActiveLoans,
            'performing_loans' => $riskDistribution['current'],
            'delinquent_loans' => $totalActiveLoans - $riskDistribution['current'],
            'par30_rate' => $par30Rate,
            'par60_rate' => $par60Rate,
            'par90_rate' => $par90Rate,
            'par180_rate' => $par180Rate,
            'par30_amount' => $par30Amount,
            'par60_amount' => $par60Amount,
            'par90_amount' => $par90Amount,
            'par180_amount' => $par180Amount,
            'current_count' => $riskDistribution['current'],
            'par30_count' => $riskDistribution['par30'],
            'par60_count' => $riskDistribution['par60'],
            'par90_count' => $riskDistribution['par90'],
            'default_count' => $riskDistribution['default'],
        ];
    }

    /**
     * Get trend analysis between two dates.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array<int>|int|null $subshopIds
     * @return array
     */
    public function getTrendAnalysis(Carbon $startDate, Carbon $endDate, array|int|null $subshopIds = null): array
    {
        $query = RiskSnapshot::forDateRange($startDate, $endDate)
            ->orderBy('snapshot_date');

        if (is_array($subshopIds) && !empty($subshopIds)) {
            $query->whereIn('subshop_id', $subshopIds);
        } elseif ($subshopIds) {
            $query->where('subshop_id', $subshopIds);
        } else {
            $query->whereNull('subshop_id'); // Portfolio-wide snapshots
        }

        $snapshots = $query->get();

        if ($snapshots->isEmpty()) {
            return [];
        }

        $first = $snapshots->first();
        $last = $snapshots->last();

        return [
            'period' => [
                'start' => $first->snapshot_date->toDateString(),
                'end' => $last->snapshot_date->toDateString(),
            ],
            'portfolio_change' => [
                'amount' => $last->portfolio_outstanding - $first->portfolio_outstanding,
                'percentage' => $first->portfolio_outstanding > 0
                    ? round((($last->portfolio_outstanding - $first->portfolio_outstanding) / $first->portfolio_outstanding) * 100, 2)
                    : 0,
            ],
            'par30_change' => [
                'rate_change' => round($last->par30_rate - $first->par30_rate, 2),
                'amount_change' => $last->par30_amount - $first->par30_amount,
            ],
            'par90_change' => [
                'rate_change' => round($last->par90_rate - $first->par90_rate, 2),
                'amount_change' => $last->par90_amount - $first->par90_amount,
            ],
            'npl_change' => [
                'rate_change' => round($last->npl_rate - $first->npl_rate, 2),
                'amount_change' => $last->npl_amount - $first->npl_amount,
            ],
            'snapshots' => $snapshots,
        ];
    }

    /**
     * Get the latest snapshot.
     *
     * @param int|null $subshopId
     * @return RiskSnapshot|null
     */
    public function getLatestSnapshot(?int $subshopId = null): ?RiskSnapshot
    {
        return RiskSnapshot::forSubshop($subshopId)
            ->latest()
            ->first();
    }
}
