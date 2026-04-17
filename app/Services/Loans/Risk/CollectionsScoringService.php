<?php

declare(strict_types=1);

namespace App\Services\Loans\Risk;

use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Collections Scoring Service
 *
 * Calculates priority scores for collections worklist based on multiple factors:
 * - Days overdue (DPD)
 * - Outstanding balance
 * - Historical payment behavior
 * - Last payment date
 * - Customer segment
 *
 * Higher score = higher priority for collection action.
 */
class CollectionsScoringService
{
    /**
     * Weight factors for scoring algorithm.
     * These can be adjusted based on business priorities.
     */
    protected const WEIGHT_DPD = 0.35;
    protected const WEIGHT_OUTSTANDING = 0.25;
    protected const WEIGHT_LAST_PAYMENT = 0.20;
    protected const WEIGHT_CUSTOMER_HISTORY = 0.20;

    /**
     * Maximum score for normalization.
     */
    protected const MAX_SCORE = 100;

    public function __construct(
        protected DpdCalculator $dpdCalculator,
        protected PortfolioRiskCalculator $portfolioRisk
    ) {
    }

    /**
     * Calculate collection scores for a collection of loans.
     *
     * @param Collection<Loans> $loans
     * @return array<int, float> Array of [loan_id => score]
     */
    public function calculateScores(Collection $loans): array
    {
        if ($loans->isEmpty()) {
            return [];
        }

        $loanIds = $loans->pluck('id')->toArray();

        // Get payment history for all loans in bulk
        $paymentHistory = $this->getPaymentHistoryBulk($loanIds);

        $scores = [];

        foreach ($loans as $loan) {
            $scores[$loan->id] = $this->calculateSingleScore(
                $loan,
                $paymentHistory[$loan->id] ?? null
            );
        }

        return $scores;
    }

    /**
     * Calculate score for a single loan.
     *
     * @param Loans $loan
     * @param object|null $paymentHistory
     * @return float Score between 0 and 100
     */
    public function calculateSingleScore(Loans $loan, ?object $paymentHistory = null): float
    {
        $dpdScore = $this->calculateDpdScore($loan->max_days_overdue ?? 0);
        $outstandingScore = $this->calculateOutstandingScore((float) ($loan->outstanding_balance ?? 0));
        $lastPaymentScore = $this->calculateLastPaymentScore($paymentHistory);
        $historyScore = $this->calculateCustomerHistoryScore($paymentHistory);

        $totalScore = (
            $dpdScore * self::WEIGHT_DPD +
            $outstandingScore * self::WEIGHT_OUTSTANDING +
            $lastPaymentScore * self::WEIGHT_LAST_PAYMENT +
            $historyScore * self::WEIGHT_CUSTOMER_HISTORY
        );

        return round(min(self::MAX_SCORE, max(0, $totalScore)), 2);
    }

    /**
     * Calculate DPD component score.
     *
     * Higher DPD = higher score (more urgent)
     *
     * @param int $maxDaysOverdue
     * @return float Score 0-100
     */
    protected function calculateDpdScore(int $maxDaysOverdue): float
    {
        if ($maxDaysOverdue <= 0) {
            return 0;
        }

        if ($maxDaysOverdue <= 30) {
            // PAR30: 20-40 points
            return 20 + ($maxDaysOverdue / 30) * 20;
        }

        if ($maxDaysOverdue <= 60) {
            // PAR60: 40-60 points
            return 40 + (($maxDaysOverdue - 30) / 30) * 20;
        }

        if ($maxDaysOverdue <= 90) {
            // PAR90: 60-80 points
            return 60 + (($maxDaysOverdue - 60) / 30) * 20;
        }

        if ($maxDaysOverdue <= 120) {
            // PAR120: 80-95 points
            return 80 + (($maxDaysOverdue - 90) / 30) * 15;
        }

        // 120+ days: 95-100 points (capped)
        return min(100, 95 + (($maxDaysOverdue - 120) / 30) * 5);
    }

    /**
     * Calculate outstanding balance component score.
     *
     * Higher outstanding = higher score (more at stake)
     *
     * @param float $outstanding
     * @return float Score 0-100
     */
    protected function calculateOutstandingScore(float $outstanding): float
    {
        if ($outstanding <= 0) {
            return 0;
        }

        // Logarithmic scale for outstanding amount
        // Small loans (< 1000): 0-20 points
        // Medium loans (1000-10000): 20-60 points
        // Large loans (10000-50000): 60-90 points
        // Very large loans (> 50000): 90-100 points

        $logOutstanding = log10($outstanding + 1);

        // Base 1000 = 3 log10, base 100000 = 5 log10
        $normalized = ($logOutstanding - 3) / 2; // normalize to 0-1 range roughly

        return min(100, max(0, $normalized * 100));
    }

    /**
     * Calculate last payment recency score.
     *
     * Longer since last payment = higher score (more concerning)
     *
     * @param object|null $paymentHistory
     * @return float Score 0-100
     */
    protected function calculateLastPaymentScore(?object $paymentHistory): float
    {
        if (!$paymentHistory || !$paymentHistory->last_payment_date) {
            // No payment history - highest concern
            return 100;
        }

        $lastPayment = Carbon::parse($paymentHistory->last_payment_date);
        $daysSincePayment = Carbon::today()->diffInDays($lastPayment);

        if ($daysSincePayment <= 7) {
            // Paid within last week: 0-10 points
            return ($daysSincePayment / 7) * 10;
        }

        if ($daysSincePayment <= 30) {
            // 1-4 weeks: 10-40 points
            return 10 + (($daysSincePayment - 7) / 23) * 30;
        }

        if ($daysSincePayment <= 90) {
            // 1-3 months: 40-70 points
            return 40 + (($daysSincePayment - 30) / 60) * 30;
        }

        // Over 3 months: 70-100 points
        return min(100, 70 + (($daysSincePayment - 90) / 30) * 30);
    }

    /**
     * Calculate customer payment history score.
     *
     * Poor history = higher score (riskier customer)
     *
     * @param object|null $paymentHistory
     * @return float Score 0-100
     */
    protected function calculateCustomerHistoryScore(?object $paymentHistory): float
    {
        if (!$paymentHistory) {
            return 50; // Unknown history - neutral score
        }

        $totalPayments = $paymentHistory->total_payments ?? 0;
        $onTimePayments = $paymentHistory->on_time_payments ?? 0;

        if ($totalPayments === 0) {
            return 75; // No payment history - high concern
        }

        $onTimeRate = $onTimePayments / $totalPayments;

        // Invert: lower on-time rate = higher score
        return (1 - $onTimeRate) * 100;
    }

    /**
     * Get payment history for multiple loans in bulk.
     *
     * @param array<int> $loanIds
     * @return array<int, object>
     */
    protected function getPaymentHistoryBulk(array $loanIds): array
    {
        if (empty($loanIds)) {
            return [];
        }

        $results = DB::table('loan_payments as lp')
            ->join('loan_payment_allocations as lpa', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->whereIn('li.loan_id', $loanIds)
            ->where('lp.status', 'confirmed')
            ->select('li.loan_id')
            ->selectRaw('MAX(lp.payment_date) as last_payment_date')
            ->selectRaw('COUNT(DISTINCT lp.id) as total_payments')
            ->selectRaw(
                'SUM(CASE WHEN lp.payment_date <= li.due_date THEN 1 ELSE 0 END) as on_time_payments'
            )
            ->groupBy('li.loan_id')
            ->get()
            ->keyBy('loan_id')
            ->toArray();

        return $results;
    }

    /**
     * Get priority label based on score.
     *
     * @param float $score
     * @return string Priority label
     */
    public function getPriorityLabel(float $score): string
    {
        if ($score >= 80) {
            return 'critical';
        }

        if ($score >= 60) {
            return 'high';
        }

        if ($score >= 40) {
            return 'medium';
        }

        if ($score >= 20) {
            return 'low';
        }

        return 'minimal';
    }

    /**
     * Get recommended action based on score and loan details.
     *
     * @param Loans $loan
     * @param float $score
     * @return string Recommended action
     */
    public function getRecommendedAction(Loans $loan, float $score): string
    {
        $maxDpd = $loan->max_days_overdue ?? 0;

        if ($score >= 80 || $maxDpd >= 90) {
            return 'immediate_legal_action';
        }

        if ($score >= 60 || $maxDpd >= 60) {
            return 'field_visit_required';
        }

        if ($score >= 40 || $maxDpd >= 30) {
            return 'phone_follow_up';
        }

        if ($score >= 20) {
            return 'sms_reminder';
        }

        return 'monitor';
    }
}
