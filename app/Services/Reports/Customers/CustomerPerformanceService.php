<?php

declare(strict_types=1);

namespace App\Services\Reports\Customers;

use App\Models\Customers;
use App\Models\LoanDisbursements;
use App\Models\LoanInstallments;
use App\Models\LoanPaymentAllocations;
use App\Models\LoanPayments;
use App\Models\LoanPenaltyApplications;
use App\Models\LoanProducts;
use App\Models\Loans;
use App\Models\SubShop;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerPerformanceService
{
    private const ACTIVE_LOAN_STATUSES = ['disbursed', 'partially_paid'];
    private const CLOSED_LOAN_STATUS = 'paid_off';
    private const OVERDUE_INSTALLMENT_STATUS = 'overdue';
    private const PAID_INSTALLMENT_STATUS = 'paid';

    /**
     * Build the customer performance report data
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $subshopId = $filters['subshop_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $performanceLevel = $filters['performance_level'] ?? null;
        $loanProductId = $filters['loan_product_id'] ?? null;
        $loanOfficerId = $filters['loan_officer_id'] ?? null;

        // Get base customer query
        $customerQuery = $this->buildCustomerQuery(
            $subshopId,
            $fromDate,
            $toDate,
            $performanceLevel,
            $loanProductId,
            $loanOfficerId,
            $accessibleSubshopIds
        );

        // Get customer performance data
        $customers = $this->getCustomerPerformanceData($customerQuery);

        // Apply performance level filtering after calculation
        if ($performanceLevel) {
            $customers = $customers->filter(function ($customer) use ($performanceLevel) {
                return $customer->performance_level === $performanceLevel;
            });
        }

        // Sort by score descending (ranking)
        $customers = $customers->sortByDesc('performance_score')->values();

        // Get summary metrics
        $metrics = $this->getSummaryMetrics($customers);

        // Get top and worst performers
        $topPerformers = $this->getTopPerformers($customers, 10);
        $worstPerformers = $this->getWorstPerformers($customers, 10);

        // Get trend analysis
        $trendData = $this->getTrendAnalysis($customers, $fromDate, $toDate);

        // Get chart data
        $chartData = $this->getChartData($customers, $topPerformers, $trendData);

        // Get loan products for filter
        $loanProducts = $this->getLoanProducts($accessibleSubshopIds, $subshopId);

        return [
            'customers' => $customers,
            'metrics' => $metrics,
            'top_performers' => $topPerformers,
            'worst_performers' => $worstPerformers,
            'trend_data' => $trendData,
            'chart_data' => $chartData,
            'loan_products' => $loanProducts,
        ];
    }

    /**
     * Build base customer query with filters
     */
    private function buildCustomerQuery(
        ?int $subshopId,
        ?string $fromDate,
        ?string $toDate,
        ?string $performanceLevel,
        ?int $loanProductId,
        ?int $loanOfficerId,
        array $accessibleSubshopIds
    ) {
        $query = Customers::query()
            ->with(['subshop'])
            ->whereIn('subshop_id', $accessibleSubshopIds);

        // Apply subshop filter
        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        // Apply loan product filter
        if ($loanProductId) {
            $query->whereHas('loans', function ($q) use ($loanProductId) {
                $q->where('loan_product_id', $loanProductId);
            });
        }

        // Apply loan officer filter
        if ($loanOfficerId) {
            $query->whereHas('loans', function ($q) use ($loanOfficerId) {
                $q->where('approved_by', $loanOfficerId);
            });
        }

        return $query;
    }

    /**
     * Get customer performance data with all calculations
     */
    private function getCustomerPerformanceData($customerQuery): Collection
    {
        $customers = $customerQuery->get();

        // Get customer IDs for bulk data loading
        $customerIds = $customers->pluck('id')->toArray();

        if (empty($customerIds)) {
            return $customers;
        }

        // Bulk load related data
        $loanData = $this->getBulkLoanData($customerIds);
        $disbursementData = $this->getBulkDisbursementData($customerIds);
        $paymentData = $this->getBulkPaymentData($customerIds);
        $installmentData = $this->getBulkInstallmentData($customerIds);
        $penaltyData = $this->getBulkPenaltyData($customerIds);

        // Calculate performance metrics for each customer
        return $customers->map(function ($customer) use (
            $loanData,
            $disbursementData,
            $paymentData,
            $installmentData,
            $penaltyData
        ) {
            $customerId = $customer->id;

            // Loan metrics
            $customerLoans = $loanData[$customerId] ?? [];
            $totalLoans = count($customerLoans);
            $activeLoans = collect($customerLoans)->whereIn('status', self::ACTIVE_LOAN_STATUSES)->count();
            $closedLoans = collect($customerLoans)->where('status', self::CLOSED_LOAN_STATUS)->count();

            // Financial metrics
            $totalDisbursed = $disbursementData[$customerId] ?? 0;
            $totalPaid = $paymentData[$customerId] ?? 0;
            $totalDue = $installmentData[$customerId]['total_due'] ?? 0;
            $outstanding = max(0, $totalDue - $totalPaid);

            // Repayment metrics
            $onTimePayments = $installmentData[$customerId]['on_time_payments'] ?? 0;
            $latePayments = $installmentData[$customerId]['late_payments'] ?? 0;
            $missedPayments = $installmentData[$customerId]['missed_payments'] ?? 0;
            $totalPayments = $onTimePayments + $latePayments;

            // Repayment rate
            $repaymentRate = $totalDue > 0 ? min(1, $totalPaid / $totalDue) : 0;

            // Timeliness ratio
            $timelinessRatio = $totalPayments > 0 ? $onTimePayments / $totalPayments : 0;

            // Completion rate
            $completionRate = $totalLoans > 0 ? $closedLoans / $totalLoans : 0;

            // Penalty metrics
            $totalPenalties = $penaltyData[$customerId]['total_penalties'] ?? 0;
            $penaltyFrequency = $penaltyData[$customerId]['penalty_count'] ?? 0;

            // Delinquency metrics
            $overdueAmount = $installmentData[$customerId]['overdue_amount'] ?? 0;
            $daysPastDue = $installmentData[$customerId]['max_days_past_due'] ?? 0;

            // Calculate performance score
            $performanceScore = $this->calculatePerformanceScore(
                $repaymentRate,
                $timelinessRatio,
                $completionRate,
                $penaltyFrequency
            );

            // Determine performance level
            $performanceLevel = $this->determinePerformanceLevel($performanceScore);

            // Add calculated properties to customer
            $customer->total_loans = $totalLoans;
            $customer->active_loans = $activeLoans;
            $customer->closed_loans = $closedLoans;
            $customer->total_disbursed = $totalDisbursed;
            $customer->total_paid = $totalPaid;
            $customer->total_due = $totalDue;
            $customer->outstanding = $outstanding;
            $customer->on_time_payments = $onTimePayments;
            $customer->late_payments = $latePayments;
            $customer->missed_payments = $missedPayments;
            $customer->repayment_rate = $repaymentRate;
            $customer->timeliness_ratio = $timelinessRatio;
            $customer->completion_rate = $completionRate;
            $customer->total_penalties = $totalPenalties;
            $customer->penalty_frequency = $penaltyFrequency;
            $customer->overdue_amount = $overdueAmount;
            $customer->days_past_due = $daysPastDue;
            $customer->performance_score = $performanceScore;
            $customer->performance_level = $performanceLevel;

            return $customer;
        });
    }

    /**
     * Calculate performance score (0-100)
     */
    private function calculatePerformanceScore(
        float $repaymentRate,
        float $timelinessRatio,
        float $completionRate,
        int $penaltyFrequency
    ): float {
        // Normalize penalty frequency (cap at 10 for scoring)
        $normalizedPenalty = min($penaltyFrequency, 10) / 10;

        // Calculate weighted score
        $rawScore = (
            ($repaymentRate * 0.4) +
            ($timelinessRatio * 0.3) +
            ($completionRate * 0.2) -
            ($normalizedPenalty * 0.1)
        ) * 100;

        // Normalize to 0-100 range
        return min(100, max(0, round($rawScore, 2)));
    }

    /**
     * Determine performance level from score
     */
    private function determinePerformanceLevel(float $score): string
    {
        if ($score >= 80) {
            return 'Excellent';
        } elseif ($score >= 60) {
            return 'Good';
        } elseif ($score >= 40) {
            return 'Average';
        } elseif ($score >= 20) {
            return 'Poor';
        } else {
            return 'Defaulted';
        }
    }

    /**
     * Get bulk loan data for customers
     */
    private function getBulkLoanData(array $customerIds): array
    {
        $loans = Loans::query()
            ->whereIn('customer_id', $customerIds)
            ->get()
            ->groupBy('customer_id');

        $data = [];
        foreach ($loans as $customerId => $customerLoans) {
            $data[$customerId] = $customerLoans->toArray();
        }

        return $data;
    }

    /**
     * Get bulk disbursement data
     */
    private function getBulkDisbursementData(array $customerIds): array
    {
        $disbursements = LoanDisbursements::query()
            ->join('loans as l', 'l.id', '=', 'loan_disbursements.loan_id')
            ->whereIn('l.customer_id', $customerIds)
            ->groupBy('l.customer_id')
            ->select('l.customer_id', DB::raw('SUM(loan_disbursements.amount) as total'))
            ->pluck('total', 'l.customer_id')
            ->toArray();

        return $disbursements;
    }

    /**
     * Get bulk payment data
     */
    private function getBulkPaymentData(array $customerIds): array
    {
        $payments = LoanPaymentAllocations::query()
            ->join('loan_installments as li', 'li.id', '=', 'loan_payment_allocations.loan_installment_id')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->whereIn('l.customer_id', $customerIds)
            ->groupBy('l.customer_id')
            ->select(
                'l.customer_id',
                DB::raw('SUM(loan_payment_allocations.principal_amount + loan_payment_allocations.interest_amount) as total')
            )
            ->pluck('total', 'l.customer_id')
            ->toArray();

        return $payments;
    }

    /**
     * Get bulk installment data for repayment metrics
     */
    private function getBulkInstallmentData(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $today = Carbon::today();

        // Get all relevant installments in one query
        $installments = LoanInstallments::query()
            ->join('loans as l', 'l.id', '=', 'loan_installments.loan_id')
            ->whereIn('l.customer_id', $customerIds)
            ->where('loan_installments.is_active', true)
            ->select([
                'l.customer_id',
                'loan_installments.id',
                'loan_installments.status',
                'loan_installments.due_date',
                'loan_installments.paid_date',
                'loan_installments.total_due',
            ])
            ->get();

        $data = [];

        foreach ($customerIds as $customerId) {
            $customerInstallments = $installments->where('customer_id', $customerId);
            
            $paidInstallments = $customerInstallments->where('status', self::PAID_INSTALLMENT_STATUS);
            $overdueInstallments = $customerInstallments->where('status', self::OVERDUE_INSTALLMENT_STATUS);

            // Calculate total due
            $totalDue = $customerInstallments->sum('total_due');

            // Calculate overdue amount
            $overdueAmount = $overdueInstallments->sum('total_due');

            // Calculate max days past due
            $maxDaysPastDue = 0;
            foreach ($overdueInstallments as $installment) {
                $dueDate = Carbon::parse($installment->due_date);
                $daysPastDue = $dueDate->diffInDays($today);
                $maxDaysPastDue = max($maxDaysPastDue, $daysPastDue);
            }

            // Repayment behavior - check payment timing
            $onTimePayments = 0;
            $latePayments = 0;

            foreach ($paidInstallments as $installment) {
                $dueDate = Carbon::parse($installment->due_date);
                $paidDate = $installment->paid_date ? Carbon::parse($installment->paid_date) : null;
                
                if ($paidDate && $paidDate->lte($dueDate)) {
                    $onTimePayments++;
                } else {
                    $latePayments++;
                }
            }

            // Count overdue unpaid installments as missed
            $missedPayments = $overdueInstallments->count();

            $data[$customerId] = [
                'total_due' => $totalDue,
                'overdue_amount' => $overdueAmount,
                'max_days_past_due' => $maxDaysPastDue,
                'on_time_payments' => $onTimePayments,
                'late_payments' => $latePayments,
                'missed_payments' => $missedPayments,
            ];
        }

        return $data;
    }

    /**
     * Get bulk penalty data
     */
    private function getBulkPenaltyData(array $customerIds): array
    {
        $penalties = LoanPenaltyApplications::query()
            ->join('loans as l', 'l.id', '=', 'loan_penalty_applications.loan_id')
            ->whereIn('l.customer_id', $customerIds)
            ->groupBy('l.customer_id')
            ->select([
                'l.customer_id',
                DB::raw('SUM(loan_penalty_applications.amount) as total_penalties'),
                DB::raw('COUNT(loan_penalty_applications.id) as penalty_count'),
            ])
            ->get();

        $data = [];
        foreach ($penalties as $penalty) {
            $data[$penalty->customer_id] = [
                'total_penalties' => (float) $penalty->total_penalties,
                'penalty_count' => (int) $penalty->penalty_count,
            ];
        }

        return $data;
    }

    /**
     * Get summary metrics
     */
    private function getSummaryMetrics(Collection $customers): array
    {
        $totalCustomers = $customers->count();
        
        $excellentCount = $customers->where('performance_level', 'Excellent')->count();
        $goodCount = $customers->where('performance_level', 'Good')->count();
        $averageCount = $customers->where('performance_level', 'Average')->count();
        $poorCount = $customers->where('performance_level', 'Poor')->count();
        $defaultedCount = $customers->where('performance_level', 'Defaulted')->count();

        $averageScore = $customers->avg('performance_score') ?? 0;
        $totalDisbursed = $customers->sum('total_disbursed');
        $totalPaid = $customers->sum('total_paid');
        $totalOutstanding = $customers->sum('outstanding');

        return [
            'total_customers' => $totalCustomers,
            'excellent_count' => $excellentCount,
            'good_count' => $goodCount,
            'average_count' => $averageCount,
            'poor_count' => $poorCount,
            'defaulted_count' => $defaultedCount,
            'average_score' => round($averageScore, 2),
            'total_disbursed' => $totalDisbursed,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,
        ];
    }

    /**
     * Get top performers
     */
    private function getTopPerformers(Collection $customers, int $limit = 10): Collection
    {
        return $customers->sortByDesc('performance_score')->take($limit)->values();
    }

    /**
     * Get worst performers
     */
    private function getWorstPerformers(Collection $customers, int $limit = 10): Collection
    {
        return $customers->sortBy('performance_score')->take($limit)->values();
    }

    /**
     * Get trend analysis (monthly average scores)
     */
    private function getTrendAnalysis(Collection $customers, ?string $fromDate, ?string $toDate): array
    {
        // For trend analysis, we need to group by month
        // This is a simplified version - in production, you'd query historical data
        $months = [];
        $start = $fromDate ? Carbon::parse($fromDate) : Carbon::now()->subMonths(6);
        $end = $toDate ? Carbon::parse($toDate) : Carbon::now();

        $current = $start->copy()->startOfMonth();
        while ($current->lte($end)) {
            $months[] = [
                'month' => $current->format('M Y'),
                'year' => $current->year,
                'month_num' => $current->month,
            ];
            $current->addMonth();
        }

        // For each month, calculate average performance score
        // This is a simplified calculation - in reality you'd need historical data
        $trendData = [];
        foreach ($months as $monthData) {
            // For now, use current average score for all months
            // In a real implementation, you'd query historical performance data
            $avgScore = $customers->avg('performance_score') ?? 0;
            
            $trendData[] = [
                'month' => $monthData['month'],
                'average_score' => round($avgScore, 2),
            ];
        }

        return $trendData;
    }

    /**
     * Get chart data
     */
    private function getChartData(Collection $customers, Collection $topPerformers, array $trendData): array
    {
        // Performance distribution (Pie chart)
        $performanceDistribution = [
            'Excellent' => $customers->where('performance_level', 'Excellent')->count(),
            'Good' => $customers->where('performance_level', 'Good')->count(),
            'Average' => $customers->where('performance_level', 'Average')->count(),
            'Poor' => $customers->where('performance_level', 'Poor')->count(),
            'Defaulted' => $customers->where('performance_level', 'Defaulted')->count(),
        ];

        $performanceChart = [
            'labels' => array_keys($performanceDistribution),
            'data' => array_values($performanceDistribution),
        ];

        // Top performers (Bar chart)
        $topPerformersChart = [
            'labels' => $topPerformers->pluck('name')->toArray(),
            'data' => $topPerformers->pluck('performance_score')->toArray(),
        ];

        // Trend (Line chart)
        $trendChart = [
            'labels' => array_column($trendData, 'month'),
            'data' => array_column($trendData, 'average_score'),
        ];

        return [
            'performance_distribution' => $performanceChart,
            'top_performers' => $topPerformersChart,
            'trend' => $trendChart,
        ];
    }

    /**
     * Get loan products for filter dropdown
     */
    public function getLoanProducts(array $accessibleSubshopIds, ?int $subshopId = null): Collection
    {
        $query = LoanProducts::query()
            ->whereIn('subshop_id', $accessibleSubshopIds)
            ->where('is_active', true);

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Get subshops for filter dropdown
     */
    public function getSubshops(array $accessibleSubshopIds): Collection
    {
        return SubShop::whereIn('id', $accessibleSubshopIds)
            ->orderBy('name')
            ->get(['id', 'name', 'shop_id']);
    }
}
