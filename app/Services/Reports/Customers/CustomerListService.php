<?php

namespace App\Services\Reports\Customers;

use App\Models\CustomerCreditBalances;
use App\Models\Customers;
use App\Models\LoanDisbursements;
use App\Models\LoanInstallments;
use App\Models\LoanPayments;
use App\Models\LoanProducts;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Loans\Account\LoanBalanceCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerListService
{
    private const ACTIVE_LOAN_STATUSES = ['disbursed', 'partially_paid'];
    private const CLOSED_LOAN_STATUSES = ['paid_off', 'written_off', 'rejected'];

    public function __construct(
        private readonly LoanBalanceCalculator $loanBalanceCalculator,
    ) {
    }

    /**
     * Build the customer list report data
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $subshopId = $filters['subshop_id'] ?? null;
        $search = $filters['search'] ?? null;
        $customerStatus = $filters['customer_status'] ?? null;
        $loanStatus = $filters['loan_status'] ?? null;
        $loanProductId = $filters['loan_product_id'] ?? null;

        // Get base customer query
        $customerQuery = $this->buildCustomerQuery($subshopId, $search, $customerStatus, $loanStatus, $loanProductId, $accessibleSubshopIds);

        // Get customer data with loan summaries
        $customers = $this->getCustomerData($customerQuery);

        // Get summary metrics
        $metrics = $this->getSummaryMetrics($customers);

        // Get loan products for filter
        $loanProducts = $this->getLoanProducts($accessibleSubshopIds, $subshopId);

        // Get chart data
        $chartData = $this->getChartData($customers);

        return [
            'customers' => $customers,
            'metrics' => $metrics,
            'loan_products' => $loanProducts,
            'chart_data' => $chartData,
        ];
    }

    /**
     * Build base customer query with filters
     */
    private function buildCustomerQuery(?int $subshopId, ?string $search, ?string $customerStatus, ?string $loanStatus, ?int $loanProductId, array $accessibleSubshopIds)
    {
        $query = Customers::query()
            ->with(['subshop'])
            ->whereIn('subshop_id', $accessibleSubshopIds);

        // Apply subshop filter
        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        // Apply search filter (name, phone, email)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply customer status filter
        if ($customerStatus === 'active') {
            $query->where('is_active', true);
        } elseif ($customerStatus === 'inactive') {
            $query->where('is_active', false);
        }

        // Apply loan status and product filters via subquery
        if ($loanStatus || $loanProductId) {
            $query->whereHas('loans', function ($q) use ($loanStatus, $loanProductId) {
                if ($loanStatus === 'active') {
                    $q->whereIn('status', self::ACTIVE_LOAN_STATUSES);
                } elseif ($loanStatus === 'closed') {
                    $q->whereIn('status', self::CLOSED_LOAN_STATUSES);
                }
                if ($loanProductId) {
                    $q->where('loan_product_id', $loanProductId);
                }
            });
        }

        return $query;
    }

    /**
     * Get customer data with loan summaries
     */
    private function getCustomerData($customerQuery): Collection
    {
        $customers = $customerQuery->orderBy('name')->get();

        return $customers->map(function ($customer) {
            // Get all loans for this customer
            $loans = Loans::where('customer_id', $customer->id)
                ->with(['loanProduct', 'disbursements', 'repayments', 'installments'])
                ->get();

            // Calculate loan summaries
            $loanSummary = $this->calculateLoanSummary($loans);
            $customer->total_loans = $loanSummary['total_loans'];
            $customer->active_loans = $loanSummary['active_loans'];
            $customer->closed_loans = $loanSummary['closed_loans'];

            // Financial summary
            $financialSummary = $this->calculateFinancialSummary($loans);
            $customer->total_disbursed = $financialSummary['total_disbursed'];
            $customer->total_repaid = $financialSummary['total_repaid'];
            $customer->outstanding_balance = $financialSummary['outstanding_balance'];

            // Overdue & risk indicators
            $riskIndicators = $this->calculateRiskIndicators($loans);
            $customer->overdue_amount = $riskIndicators['overdue_amount'];
            $customer->days_past_due = $riskIndicators['days_past_due'];
            $customer->par_status = $riskIndicators['par_status'];

            // Customer credit balance
            $customer->credit_balance = $this->getCustomerCreditBalance($customer->id);

            // Customer status classification
            $customer->risk_status = $this->classifyCustomerStatus($riskIndicators, $loanSummary, $customer->is_active);

            // Last activity
            $lastActivity = $this->getLastActivity($loans, $customer->id);
            $customer->last_transaction_date = $lastActivity['last_transaction_date'];
            $customer->last_loan_date = $lastActivity['last_loan_date'];

            return $customer;
        });
    }

    /**
     * Calculate loan summary per customer
     */
    private function calculateLoanSummary(Collection $loans): array
    {
        $totalLoans = $loans->count();
        $activeLoans = $loans->whereIn('status', self::ACTIVE_LOAN_STATUSES)->count();
        $closedLoans = $loans->whereIn('status', self::CLOSED_LOAN_STATUSES)->count();

        return [
            'total_loans' => $totalLoans,
            'active_loans' => $activeLoans,
            'closed_loans' => $closedLoans,
        ];
    }

    /**
     * Calculate financial summary per customer
     */
    private function calculateFinancialSummary(Collection $loans): array
    {
        $totalDisbursed = 0;
        $totalRepaid = 0;

        foreach ($loans as $loan) {
            // Get total disbursed
            $disbursed = $loan->disbursements()->sum('amount') ?? 0;
            $totalDisbursed += (float) $disbursed;

            // Get total paid from repayments
            $paid = $loan->repayments()->sum('amount') ?? 0;
            $totalRepaid += (float) $paid;
        }

        $outstandingBalance = $totalDisbursed - $totalRepaid;

        return [
            'total_disbursed' => round($totalDisbursed, 2),
            'total_repaid' => round($totalRepaid, 2),
            'outstanding_balance' => round($outstandingBalance, 2),
        ];
    }

    /**
     * Calculate overdue & risk indicators
     */
    private function calculateRiskIndicators(Collection $loans): array
    {
        $totalOverdueAmount = 0;
        $maxDaysPastDue = 0;

        foreach ($loans as $loan) {
            // Get overdue installments
            $overdueInstallments = $loan->installments()
                ->where('status', 'overdue')
                ->where('outstanding_amount', '>', 0)
                ->get();

            foreach ($overdueInstallments as $installment) {
                $totalOverdueAmount += (float) $installment->outstanding_amount;

                // Calculate days past due
                $daysPastDue = Carbon::now()->diffInDays(Carbon::parse($installment->due_date), false);
                if ($daysPastDue > $maxDaysPastDue) {
                    $maxDaysPastDue = $daysPastDue;
                }
            }
        }

        // Determine PAR status
        $parStatus = 'Good';
        if ($maxDaysPastDue >= 90) {
            $parStatus = 'PAR90';
        } elseif ($maxDaysPastDue >= 60) {
            $parStatus = 'PAR60';
        } elseif ($maxDaysPastDue >= 30) {
            $parStatus = 'PAR30';
        }

        return [
            'overdue_amount' => round($totalOverdueAmount, 2),
            'days_past_due' => $maxDaysPastDue,
            'par_status' => $parStatus,
        ];
    }

    /**
     * Get customer credit balance
     */
    private function getCustomerCreditBalance(int $customerId): float
    {
        return (float) CustomerCreditBalances::where('customer_id', $customerId)
            ->where('status', 'available')
            ->sum('amount');
    }

    /**
     * Classify customer status
     */
    private function classifyCustomerStatus(array $riskIndicators, array $loanSummary, bool $isActive): string
    {
        // If customer is inactive
        if (!$isActive) {
            return 'Inactive';
        }

        // If customer has no active loans
        if ($loanSummary['active_loans'] === 0) {
            return 'No Active Loans';
        }

        // Risk classification based on days past due
        $daysPastDue = $riskIndicators['days_past_due'];

        if ($daysPastDue >= 90) {
            return 'Defaulted';
        } elseif ($daysPastDue >= 30) {
            return 'At Risk';
        }

        return 'Good';
    }

    /**
     * Get last activity dates
     */
    private function getLastActivity(Collection $loans, int $customerId): array
    {
        $lastTransactionDate = null;
        $lastLoanDate = null;

        // Get last transaction date from payments
        $lastPayment = LoanPayments::whereHas('loan', function ($q) use ($customerId) {
            $q->where('customer_id', $customerId);
        })->orderBy('payment_date', 'desc')->first();

        if ($lastPayment) {
            $lastTransactionDate = $lastPayment->payment_date;
        }

        // Get last loan date from disbursements
        $lastDisbursement = LoanDisbursements::whereHas('loan', function ($q) use ($customerId) {
            $q->where('customer_id', $customerId);
        })->orderBy('disbursement_date', 'desc')->first();

        if ($lastDisbursement) {
            $lastLoanDate = $lastDisbursement->disbursement_date;
        }

        // If no payments, check if there's any loan activity date
        if (!$lastTransactionDate && $loans->isNotEmpty()) {
            $lastLoanDate = $loans->max('disbursement_date');
        }

        return [
            'last_transaction_date' => $lastTransactionDate,
            'last_loan_date' => $lastLoanDate,
        ];
    }

    /**
     * Get summary metrics
     */
    private function getSummaryMetrics(Collection $customers): array
    {
        $totalCustomers = $customers->count();
        $activeCustomers = $customers->where('is_active', true)->count();
        $customersWithLoans = $customers->where('total_loans', '>', 0)->count();
        $defaultedCustomers = $customers->where('risk_status', 'Defaulted')->count();

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'customers_with_loans' => $customersWithLoans,
            'defaulted_customers' => $defaultedCustomers,
        ];
    }

    /**
     * Get loan products for filter dropdown
     */
    public function getLoanProducts(array $accessibleSubshopIds, ?int $subshopId = null): Collection
    {
        $query = LoanProducts::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        } else {
            $query->whereIn('subshop_id', $accessibleSubshopIds);
        }

        return $query->get(['id', 'name', 'code']);
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

    /**
     * Get chart data
     */
    private function getChartData(Collection $customers): array
    {
        // Customer status distribution (Pie chart)
        $statusCounts = $customers->groupBy('risk_status')->map(function ($group) {
            return $group->count();
        });

        $pieChart = [
            'labels' => $statusCounts->keys()->toArray(),
            'values' => $statusCounts->values()->toArray(),
            'colors' => $this->generateColors($statusCounts->count()),
        ];

        // Top customers by loan size (Bar chart)
        $topCustomers = $customers
            ->sortByDesc('total_disbursed')
            ->take(10)
            ->map(function ($customer) {
                return [
                    'name' => $customer->name,
                    'disbursed' => $customer->total_disbursed,
                ];
            });

        $barChart = [
            'labels' => $topCustomers->pluck('name')->toArray(),
            'values' => $topCustomers->pluck('disbursed')->toArray(),
            'colors' => $this->generateColors($topCustomers->count()),
        ];

        return [
            'pie_chart' => $pieChart,
            'bar_chart' => $barChart,
        ];
    }

    /**
     * Generate colors for charts
     */
    private function generateColors(int $count): array
    {
        $colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
            '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384',
        ];

        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $colors[$i % count($colors)];
        }

        return $result;
    }
}