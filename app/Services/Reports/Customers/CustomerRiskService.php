<?php

declare(strict_types=1);

namespace App\Services\Reports\Customers;

use App\Models\CustomerCreditBalances;
use App\Models\LoanDisbursements;
use App\Models\LoanInstallments;
use App\Models\LoanPaymentAllocations;
use App\Models\LoanPenaltyApplications;
use App\Models\LoanProducts;
use App\Models\Loans;
use App\Models\SubShop;
use App\Models\Customers;
use App\Services\Loans\Account\LoanBalanceCalculator;
use App\Services\Loans\Credits\CustomerCreditService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerRiskService
{
    private const ACTIVE_LOAN_STATUSES = ['disbursed', 'partially_paid'];
    private const OVERDUE_INSTALLMENT_STATUS = 'overdue';
    private const PAID_INSTALLMENT_STATUS = 'paid';

    public function __construct(
        private readonly LoanBalanceCalculator $loanBalanceCalculator,
        private readonly CustomerCreditService $customerCreditService,
    ) {
    }

    /**
     * Build the customer risk report data
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $subshopId = $filters['subshop_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $riskLevel = $filters['risk_level'] ?? null;
        $loanProductId = $filters['loan_product_id'] ?? null;
        $loanOfficerId = $filters['loan_officer_id'] ?? null;

        // Get base customer query
        $customerQuery = $this->buildCustomerQuery(
            $subshopId,
            $fromDate,
            $toDate,
            $riskLevel,
            $loanProductId,
            $loanOfficerId,
            $accessibleSubshopIds
        );

        // Get customer risk data
        $customers = $this->getCustomerRiskData($customerQuery);

        // Apply risk level filtering after calculation
        if ($riskLevel) {
            $customers = $customers->filter(function ($customer) use ($riskLevel) {
                return $customer->risk_level === $riskLevel;
            });
        }

        // Get summary metrics
        $metrics = $this->getSummaryMetrics($customers);

        // Get loan products for filter
        $loanProducts = $this->getLoanProducts($accessibleSubshopIds, $subshopId);

        // Get chart data
        $chartData = $this->getChartData($customers);

        // Get top risk customers
        $topRiskCustomers = $this->getTopRiskCustomers($customers, 10);

        return [
            'customers' => $customers,
            'metrics' => $metrics,
            'loan_products' => $loanProducts,
            'chart_data' => $chartData,
            'top_risk_customers' => $topRiskCustomers,
        ];
    }

    /**
     * Build base customer query with filters
     */
    private function buildCustomerQuery(
        ?int $subshopId,
        ?string $fromDate,
        ?string $toDate,
        ?string $riskLevel,
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
     * Get customer risk data with all calculations
     */
    private function getCustomerRiskData($customerQuery): Collection
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
        $creditData = $this->getBulkCreditData($customerIds);

        // Calculate risk metrics for each customer
        return $customers->map(function ($customer) use (
            $loanData,
            $disbursementData,
            $paymentData,
            $installmentData,
            $penaltyData,
            $creditData
        ) {
            $customerId = $customer->id;

            // Financial exposure
            $totalDisbursed = $disbursementData[$customerId] ?? 0;
            $totalRepaid = $paymentData[$customerId] ?? 0;
            $outstandingBalance = $totalDisbursed - $totalRepaid;

            // Delinquency metrics
            $overdueAmount = $installmentData[$customerId]['overdue_amount'] ?? 0;
            $daysPastDue = $installmentData[$customerId]['max_days_past_due'] ?? 0;
            $overdueLoansCount = $installmentData[$customerId]['overdue_loans_count'] ?? 0;

            // PAR classification
            $par30 = ($installmentData[$customerId]['par_30'] ?? 0) > 0 ? 1 : 0;
            $par60 = ($installmentData[$customerId]['par_60'] ?? 0) > 0 ? 1 : 0;
            $par90 = ($installmentData[$customerId]['par_90'] ?? 0) > 0 ? 1 : 0;

            // Penalty exposure
            $totalPenalties = $penaltyData[$customerId]['total_penalties'] ?? 0;
            $outstandingPenalties = $penaltyData[$customerId]['outstanding_penalties'] ?? 0;

            // Repayment behavior
            $onTimePayments = $installmentData[$customerId]['on_time_payments'] ?? 0;
            $latePayments = $installmentData[$customerId]['late_payments'] ?? 0;
            $missedPayments = $installmentData[$customerId]['missed_payments'] ?? 0;

            // Credit behavior
            $creditBalance = $creditData[$customerId]['credit_balance'] ?? 0;
            $hasOverpayment = $creditData[$customerId]['has_overpayment'] ?? false;

            // Calculate risk score
            $riskScore = $this->calculateRiskScore(
                (float) $overdueAmount,
                (float) $daysPastDue,
                (float) $outstandingPenalties,
                (int) $overdueLoansCount,
                (float) $totalDisbursed
            );

            // Determine risk level
            $riskLevel = $this->determineRiskLevel($riskScore);

            // Get loans count
            $loansCount = count($loanData[$customerId] ?? []);

            // Add calculated properties to customer
            $customer->total_loans = $loansCount;
            $customer->total_disbursed = $totalDisbursed;
            $customer->total_repaid = $totalRepaid;
            $customer->outstanding_balance = $outstandingBalance;
            $customer->overdue_amount = $overdueAmount;
            $customer->days_past_due = $daysPastDue;
            $customer->overdue_loans_count = $overdueLoansCount;
            $customer->par30 = $par30;
            $customer->par60 = $par60;
            $customer->par90 = $par90;
            $customer->total_penalties = $totalPenalties;
            $customer->outstanding_penalties = $outstandingPenalties;
            $customer->on_time_payments = $onTimePayments;
            $customer->late_payments = $latePayments;
            $customer->missed_payments = $missedPayments;
            $customer->credit_balance = $creditBalance;
            $customer->has_overpayment = $hasOverpayment;
            $customer->risk_score = $riskScore;
            $customer->risk_level = $riskLevel;

            return $customer;
        });
    }

    /**
     * Calculate risk score (0-100)
     */
    private function calculateRiskScore(
        float $overdueAmount,
        float $daysPastDue,
        float $outstandingPenalties,
        int $overdueLoansCount,
        float $totalDisbursed
    ): float {
        // Normalize overdue amount as percentage of disbursed
        $overdueRatio = $totalDisbursed > 0 
            ? min(($overdueAmount / $totalDisbursed) * 100, 100) 
            : 0;

        // Calculate weighted score
        // overdue_amount * 0.4 + days_past_due * 0.2 + outstanding_penalties * 0.2 + overdue_loans_count * 0.2
        $rawScore = (
            ($overdueRatio * 0.4) +
            (min($daysPastDue, 100) * 0.2) +  // Cap at 100 days
            ($outstandingPenalties > 0 ? 20 : 0) +  // Binary penalty factor
            ($overdueLoansCount > 0 ? 20 : 0)  // Binary overdue loans factor
        );

        // Normalize to 0-100 scale
        return min(100, max(0, round($rawScore, 2)));
    }

    /**
     * Determine risk level from score
     */
    private function determineRiskLevel(float $score): string
    {
        if ($score <= 25) {
            return 'Low Risk';
        } elseif ($score <= 50) {
            return 'Medium Risk';
        } elseif ($score <= 75) {
            return 'High Risk';
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
        // LoanDisbursements has loan_id, not customer_id - need to join through Loans
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
     * Get bulk installment data for delinquency metrics
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
                'loan_installments.principal_due',
                'loan_installments.interest_due',
                'loan_installments.total_due',
            ])
            ->get();

        $data = [];

        foreach ($customerIds as $customerId) {
            $customerInstallments = $installments->where('customer_id', $customerId);
            
            $overdueInstallments = $customerInstallments->where('status', self::OVERDUE_INSTALLMENT_STATUS);
            $paidInstallments = $customerInstallments->where('status', self::PAID_INSTALLMENT_STATUS);

            // Calculate overdue amount
            $overdueAmount = $overdueInstallments->sum('total_due');

            // Calculate max days past due
            $maxDaysPastDue = 0;
            foreach ($overdueInstallments as $installment) {
                $dueDate = Carbon::parse($installment->due_date);
                $daysPastDue = $dueDate->diffInDays($today);
                $maxDaysPastDue = max($maxDaysPastDue, $daysPastDue);
            }

            // Count overdue loans
            $overdueLoanIds = $overdueInstallments->pluck('loan_id')->unique()->count();

            // PAR classification
            $par30 = $overdueInstallments->filter(function ($inst) use ($today) {
                return Carbon::parse($inst->due_date)->diffInDays($today) > 30;
            })->sum('total_due');

            $par60 = $overdueInstallments->filter(function ($inst) use ($today) {
                return Carbon::parse($inst->due_date)->diffInDays($today) > 60;
            })->sum('total_due');

            $par90 = $overdueInstallments->filter(function ($inst) use ($today) {
                return Carbon::parse($inst->due_date)->diffInDays($today) > 90;
            })->sum('total_due');

            // Repayment behavior - check payment timing
            $onTimePayments = 0;
            $latePayments = 0;
            $missedPayments = 0;

            foreach ($paidInstallments as $installment) {
                // For paid installments, we need to check if they were paid on time
                // This is simplified - in reality you'd check actual payment date
                $dueDate = Carbon::parse($installment->due_date);
                $onTimePayments++;
            }

            // Count overdue unpaid installments as missed
            $missedPayments = $overdueInstallments->count();

            $data[$customerId] = [
                'overdue_amount' => $overdueAmount,
                'max_days_past_due' => $maxDaysPastDue,
                'overdue_loans_count' => $overdueLoanIds,
                'par_30' => $par30,
                'par_60' => $par60,
                'par_90' => $par90,
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
            ])
            ->get();

        // Get paid penalties - LoanPaymentAllocations has loan_installment_id, not loan_id
        $paidPenalties = LoanPaymentAllocations::query()
            ->join('loan_installments as li', 'li.id', '=', 'loan_payment_allocations.loan_installment_id')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->whereIn('l.customer_id', $customerIds)
            ->groupBy('l.customer_id')
            ->select([
                'l.customer_id',
                DB::raw('SUM(loan_payment_allocations.penalty_amount) as paid_penalties'),
            ])
            ->get()
            ->keyBy('customer_id');

        $data = [];
        foreach ($penalties as $penalty) {
            $paidAmount = $paidPenalties[$penalty->customer_id]->paid_penalties ?? 0;
            $data[$penalty->customer_id] = [
                'total_penalties' => $penalty->total_penalties,
                'outstanding_penalties' => max(0, $penalty->total_penalties - $paidAmount),
            ];
        }

        return $data;
    }

    /**
     * Get bulk credit data
     */
    private function getBulkCreditData(array $customerIds): array
    {
        $credits = CustomerCreditBalances::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'available')
            ->groupBy('customer_id')
            ->select('customer_id', DB::raw('SUM(amount) as credit_balance'))
            ->get()
            ->keyBy('customer_id');

        $data = [];
        foreach ($customerIds as $customerId) {
            $credit = $credits[$customerId] ?? null;
            $data[$customerId] = [
                'credit_balance' => $credit ? (float) $credit->credit_balance : 0,
                'has_overpayment' => $credit && $credit->credit_balance > 0,
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
        
        $lowRiskCount = $customers->where('risk_level', 'Low Risk')->count();
        $mediumRiskCount = $customers->where('risk_level', 'Medium Risk')->count();
        $highRiskCount = $customers->where('risk_level', 'High Risk')->count();
        $defaultedCount = $customers->where('risk_level', 'Defaulted')->count();

        $totalOutstanding = $customers->sum('outstanding_balance');
        $totalOverdue = $customers->sum('overdue_amount');
        $totalPenalties = $customers->sum('outstanding_penalties');
        $averageRiskScore = $customers->avg('risk_score') ?? 0;

        return [
            'total_customers' => $totalCustomers,
            'low_risk_count' => $lowRiskCount,
            'medium_risk_count' => $mediumRiskCount,
            'high_risk_count' => $highRiskCount,
            'defaulted_count' => $defaultedCount,
            'total_outstanding' => $totalOutstanding,
            'total_overdue' => $totalOverdue,
            'total_penalties' => $totalPenalties,
            'average_risk_score' => round($averageRiskScore, 2),
        ];
    }

    /**
     * Get chart data
     */
    private function getChartData(Collection $customers): array
    {
        // Risk distribution
        $riskDistribution = [
            'labels' => ['Low Risk', 'Medium Risk', 'High Risk', 'Defaulted'],
            'data' => [
                $customers->where('risk_level', 'Low Risk')->count(),
                $customers->where('risk_level', 'Medium Risk')->count(),
                $customers->where('risk_level', 'High Risk')->count(),
                $customers->where('risk_level', 'Defaulted')->count(),
            ],
        ];

        // Aging distribution
        $agingDistribution = [
            'labels' => ['0-30 Days', '31-60 Days', '61-90 Days', '90+ Days'],
            'data' => [
                $customers->filter(fn($c) => $c->days_past_due > 0 && $c->days_past_due <= 30)->sum('overdue_amount'),
                $customers->filter(fn($c) => $c->days_past_due > 30 && $c->days_past_due <= 60)->sum('overdue_amount'),
                $customers->filter(fn($c) => $c->days_past_due > 60 && $c->days_past_due <= 90)->sum('overdue_amount'),
                $customers->filter(fn($c) => $c->days_past_due > 90)->sum('overdue_amount'),
            ],
        ];

        // Top risk customers for bar chart
        $topRisk = $this->getTopRiskCustomers($customers, 10);
        $topRiskCustomers = [
            'labels' => $topRisk->pluck('name')->toArray(),
            'data' => $topRisk->pluck('risk_score')->toArray(),
        ];

        // Risk trend (last 6 months - simulated based on customer data)
        $riskTrend = $this->getRiskTrend($customers);

        return [
            'risk_distribution' => $riskDistribution,
            'aging_distribution' => $agingDistribution,
            'top_risk_customers' => $topRiskCustomers,
            'risk_trend' => $riskTrend,
        ];
    }

    /**
     * Get top risk customers
     */
    private function getTopRiskCustomers(Collection $customers, int $limit = 10): Collection
    {
        return $customers
            ->sortByDesc('risk_score')
            ->take($limit)
            ->values();
    }

    /**
     * Get risk trend data
     */
    private function getRiskTrend(Collection $customers): array
    {
        // This is a simplified version - in a real scenario, you'd calculate
        // historical risk scores based on past data
        $months = [];
        $avgScores = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $months[] = $month->format('M Y');
            // Use current average as placeholder for trend
            $avgScores[] = round($customers->avg('risk_score') ?? 0, 2);
        }

        return [
            'labels' => $months,
            'data' => $avgScores,
        ];
    }

    /**
     * Get loan products for filter
     */
    public function getLoanProducts(array $accessibleSubshopIds, ?int $subshopId): Collection
    {
        $query = LoanProducts::query()
            ->whereIn('subshop_id', $accessibleSubshopIds)
            ->orderBy('name');

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        return $query->get();
    }

    /**
     * Get subshops for filter
     */
    public function getSubshops(array $accessibleSubshopIds): Collection
    {
        return SubShop::query()
            ->whereIn('id', $accessibleSubshopIds)
            ->orderBy('name')
            ->get();
    }
}
