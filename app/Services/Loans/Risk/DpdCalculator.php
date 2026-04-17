<?php

declare(strict_types=1);

namespace App\Services\Loans\Risk;

use App\Models\LoanInstallments;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Standardized Days Past Due (DPD) Calculator
 *
 * Provides consistent DPD calculation across all risk services.
 * Uses SQL DATEDIFF for database-level calculations and Carbon for in-memory calculations.
 */
class DpdCalculator
{
    /**
     * Calculate DPD for a single installment using consistent logic.
     *
     * Formula: DPD = max(0, Today - Due Date)
     *
     * @param LoanInstallments $installment
     * @param Carbon|null $asOfDate Date to calculate DPD from (defaults to today)
     * @return int Days past due (0 if not overdue)
     */
    public function calculateDaysOverdue(LoanInstallments $installment, ?Carbon $asOfDate = null): int
    {
        if ($installment->status !== 'overdue') {
            return 0;
        }

        $asOfDate = $asOfDate ?? Carbon::today();
        $dueDate = $installment->due_date instanceof Carbon
            ? $installment->due_date
            : Carbon::parse($installment->due_date);

        // Calculate days difference (positive = overdue)
        $days = $asOfDate->diffInDays($dueDate, false) * -1;

        return max(0, (int) $days);
    }

    /**
     * Get the maximum DPD for a loan across all overdue installments.
     *
     * @param int $loanId
     * @param Carbon|null $asOfDate
     * @return int Maximum days overdue (0 if no overdue installments)
     */
    public function calculateMaxDaysOverdueForLoan(int $loanId, ?Carbon $asOfDate = null): int
    {
        $asOfDate = $asOfDate ?? Carbon::today();

        $maxDpd = DB::table('loan_installments')
            ->where('loan_id', $loanId)
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $asOfDate)
            ->selectRaw('MAX(DATEDIFF(?, due_date)) as max_dpd', [$asOfDate->toDateString()])
            ->value('max_dpd');

        return (int) ($maxDpd ?? 0);
    }

    /**
     * Bulk calculate DPD for multiple loans using SQL.
     *
     * Returns array keyed by loan_id with max_dpd values.
     *
     * @param array $loanIds
     * @param Carbon|null $asOfDate
     * @return array<int, int>
     */
    public function bulkCalculateMaxDpd(array $loanIds, ?Carbon $asOfDate = null): array
    {
        if (empty($loanIds)) {
            return [];
        }

        $asOfDate = $asOfDate ?? Carbon::today();

        $results = DB::table('loan_installments')
            ->whereIn('loan_id', $loanIds)
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $asOfDate)
            ->select('loan_id')
            ->selectRaw('MAX(DATEDIFF(?, due_date)) as max_dpd', [$asOfDate->toDateString()])
            ->groupBy('loan_id')
            ->pluck('max_dpd', 'loan_id')
            ->toArray();

        return array_map(fn($dpd) => (int) $dpd, $results);
    }

    /**
     * Get all overdue installment IDs with their DPD values.
     *
     * @param int $minDays Minimum DPD threshold
     * @param int|null $loanId Optional loan filter
     * @param Carbon|null $asOfDate
     * @return array<int, int> Array of [installment_id => dpd_days]
     */
    public function getOverdueInstallmentsWithDpd(int $minDays = 1, ?int $loanId = null, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::today();
        $cutoffDate = $asOfDate->copy()->subDays($minDays);

        $query = DB::table('loan_installments')
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoffDate);

        if ($loanId) {
            $query->where('loan_id', $loanId);
        }

        return $query
            ->select('id', 'due_date')
            ->get()
            ->mapWithKeys(function ($row) use ($asOfDate) {
                $dueDate = Carbon::parse($row->due_date);
                $dpd = max(0, (int) ($asOfDate->diffInDays($dueDate, false) * -1));
                return [$row->id => $dpd];
            })
            ->toArray();
    }

    /**
     * Calculate weighted average DPD for a set of loans.
     * Weights are based on outstanding balance.
     *
     * @param array $loanIds
     * @param array $outstandingBalances Array of [loan_id => outstanding_balance]
     * @param Carbon|null $asOfDate
     * @return float Weighted average DPD
     */
    public function calculateWeightedAverageDpd(array $loanIds, array $outstandingBalances, ?Carbon $asOfDate = null): float
    {
        if (empty($loanIds)) {
            return 0.0;
        }

        $dpdMap = $this->bulkCalculateMaxDpd($loanIds, $asOfDate);

        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($loanIds as $loanId) {
            $dpd = $dpdMap[$loanId] ?? 0;
            $outstanding = $outstandingBalances[$loanId] ?? 0;

            $weightedSum += $dpd * $outstanding;
            $totalWeight += $outstanding;
        }

        return $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0.0;
    }

    /**
     * Check if a loan is delinquent based on DPD threshold.
     *
     * @param int $loanId
     * @param int $thresholdDays
     * @param Carbon|null $asOfDate
     * @return bool
     */
    public function isDelinquent(int $loanId, int $thresholdDays = 1, ?Carbon $asOfDate = null): bool
    {
        $maxDpd = $this->calculateMaxDaysOverdueForLoan($loanId, $asOfDate);
        return $maxDpd >= $thresholdDays;
    }

    /**
     * Classify a loan into risk bucket based on DPD.
     *
     * @param int $dpd Days past due
     * @return string Risk classification: current, par30, par60, par90, default
     */
    public function classifyByDpd(int $dpd): string
    {
        if ($dpd <= 0) {
            return 'current';
        }

        if ($dpd <= 30) {
            return 'par30';
        }

        if ($dpd <= 60) {
            return 'par60';
        }

        if ($dpd <= 90) {
            return 'par90';
        }

        return 'default';
    }

    /**
     * Get DPD distribution for a set of loans.
     *
     * @param array $loanIds
     * @param Carbon|null $asOfDate
     * @return array<string, int> Count of loans in each bucket
     */
    public function getDpdDistribution(array $loanIds, ?Carbon $asOfDate = null): array
    {
        $dpdMap = $this->bulkCalculateMaxDpd($loanIds, $asOfDate);

        $distribution = [
            'current' => 0,
            'par30' => 0,
            'par60' => 0,
            'par90' => 0,
            'default' => 0,
        ];

        foreach ($loanIds as $loanId) {
            $dpd = $dpdMap[$loanId] ?? 0;
            $bucket = $this->classifyByDpd($dpd);
            $distribution[$bucket]++;
        }

        return $distribution;
    }
}
