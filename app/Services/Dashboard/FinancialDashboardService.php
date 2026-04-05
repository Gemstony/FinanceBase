<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\ChartsOfAccount;
use App\Models\Customers;
use App\Models\DepositAccount;
use App\Models\JournalEntries;
use App\Models\LoanPayments;
use App\Models\LoanRestructures;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Loans\Account\LoanBalanceCalculator;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use App\Services\Reports\Accounting\ExpensesSummaryService;
use App\Services\Reports\Accounting\IncomeSummaryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialDashboardService
{
    public function __construct(
        private readonly LoanBalanceCalculator $loanBalanceCalculator,
        private readonly PortfolioRiskCalculator $portfolioRiskCalculator,
        private readonly IncomeSummaryService $incomeSummaryService,
        private readonly ExpensesSummaryService $expensesSummaryService
    ) {}

    /**
     * Build the financial dashboard data.
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        if (empty($subshopIds)) {
            return $this->emptyResponse();
        }

        // KPI Summary Cards
        $kpis = $this->calculateKPIs($subshopIds, $fromDate, $toDate);

        // Profitability Data
        $profitability = $this->calculateProfitability($subshopIds, $fromDate, $toDate);

        // Cash Flow Data
        $cashFlow = $this->calculateCashFlow($subshopIds, $fromDate, $toDate);

        // Loan Portfolio Data
        $loanPortfolio = $this->calculateLoanPortfolio($subshopIds);

        // Customer Stats
        $customerStats = $this->calculateCustomerStats($subshopIds);

        // PAR Data
        $parData = $this->calculatePAR($subshopIds);

        // Income Distribution
        $incomeDistribution = $this->calculateIncomeDistribution($subshopIds, $fromDate, $toDate);

        // Expense Distribution
        $expenseDistribution = $this->calculateExpenseDistribution($subshopIds, $fromDate, $toDate);

        // Monthly Trends
        $monthlyTrends = $this->calculateMonthlyTrends($subshopIds, $fromDate, $toDate);

        // Branch Performance
        $branchPerformance = $this->calculateBranchPerformance($subshopIds, $fromDate, $toDate);

        // Recent Transactions
        $recentTransactions = $this->getRecentTransactions($subshopIds);

        // Alerts
        $alerts = $this->generateAlerts($kpis, $parData);

        // Loan Charts Data
        $loanCharts = $this->calculateLoanCharts($subshopIds, $fromDate, $toDate);

        return [
            'filters' => [
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'subshop_ids' => $subshopIds,
            ],
            'kpis' => $kpis,
            'profitability' => $profitability,
            'cash_flow' => $cashFlow,
            'loan_portfolio' => $loanPortfolio,
            'customer_stats' => $customerStats,
            'par_data' => $parData,
            'income_distribution' => $incomeDistribution,
            'expense_distribution' => $expenseDistribution,
            'monthly_trends' => $monthlyTrends,
            'branch_performance' => $branchPerformance,
            'recent_transactions' => $recentTransactions,
            'alerts' => $alerts,
            'loan_charts' => $loanCharts,
            'charts' => $this->buildChartData($profitability, $cashFlow, $incomeDistribution, $expenseDistribution, $monthlyTrends, $branchPerformance),
        ];
    }

    private function resolveSubshopFilter(?int $subshopId, array $accessibleSubshopIds): array
    {
        if ($subshopId) {
            return in_array($subshopId, $accessibleSubshopIds, true) ? [$subshopId] : [];
        }

        return $accessibleSubshopIds;
    }

    private function emptyResponse(): array
    {
        return [
            'filters' => [],
            'kpis' => $this->emptyKPIs(),
            'profitability' => ['labels' => [], 'income' => [], 'expenses' => [], 'profit' => []],
            'cash_flow' => ['labels' => [], 'inflows' => [], 'outflows' => []],
            'loan_portfolio' => [
                'total_disbursed' => 0.0,
                'total_outstanding' => 0.0,
                'active_loans_count' => 0,
                'paid_off_count' => 0,
                'total_released_loans' => 0,
                'total_active_loans' => 0,
                'total_completed_loans' => 0,
                'total_restructured_loans' => 0,
            ],
            'customer_stats' => [
                'total_registered_customers' => 0,
                'total_customer_deposits' => 0.0,
            ],
            'par_data' => ['par30' => 0.0, 'par60' => 0.0, 'par90' => 0.0],
            'income_distribution' => ['labels' => [], 'values' => []],
            'expense_distribution' => ['labels' => [], 'values' => []],
            'monthly_trends' => ['labels' => [], 'income' => [], 'expenses' => [], 'profit' => []],
            'branch_performance' => ['labels' => [], 'income' => [], 'expenses' => [], 'profit' => []],
            'recent_transactions' => [],
            'alerts' => [],
            'loan_charts' => [
                'loans_released' => ['labels' => [], 'values' => []],
                'loans_collections' => ['labels' => [], 'values' => []],
                'customer_status' => ['active' => 0, 'inactive' => 0],
                'loan_amount_status' => ['paid' => 0.0, 'due' => 0.0],
            ],
            'charts' => [],
        ];
    }

    private function emptyKPIs(): array
    {
        return [
            'loan_portfolio' => 0.0,
            'total_outstanding' => 0.0,
            'total_income' => 0.0,
            'total_expenses' => 0.0,
            'net_profit' => 0.0,
            'cash_balance' => 0.0,
            'par30' => 0.0,
        ];
    }

    /**
     * Calculate KPI Summary Cards.
     */
    private function calculateKPIs(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        // Total Loan Portfolio (using PortfolioRiskCalculator)
        $totalOutstanding = $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops($subshopIds);

        // Total Disbursed (sum of principal_amount for disbursed loans)
        $totalDisbursed = (float) Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->sum('principal_amount');

        // Total Income (from GL income accounts)
        $totalIncome = $this->getTotalIncome($subshopIds, $fromDate, $toDate);

        // Total Expenses (from GL expense accounts)
        $totalExpenses = $this->getTotalExpenses($subshopIds, $fromDate, $toDate);

        // Net Profit
        $netProfit = $totalIncome - $totalExpenses;

        // Cash Balance (from cash/bank accounts)
        $cashBalance = $this->getCashBalance($subshopIds);

        // PAR 30
        $par30 = $this->calculatePAR30($subshopIds);

        return [
            'loan_portfolio' => round($totalDisbursed, 2),
            'total_outstanding' => round($totalOutstanding, 2),
            'total_income' => round($totalIncome, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
            'cash_balance' => round($cashBalance, 2),
            'par30' => round($par30, 2),
        ];
    }

    /**
     * Calculate Profitability (Income vs Expenses by month).
     */
    private function calculateProfitability(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        if (! $fromDate || ! $toDate) {
            // Default to last 12 months
            $fromDate = Carbon::now()->subMonths(11)->startOfMonth();
            $toDate = Carbon::now()->endOfMonth();
        }

        $months = $this->generateMonthLabels($fromDate, $toDate);
        $labels = $months['labels'];

        $incomeData = [];
        $expensesData = [];
        $profitData = [];

        foreach ($labels as $month) {
            $monthStart = Carbon::parse($month.'-01')->startOfMonth();
            $monthEnd = Carbon::parse($month.'-01')->endOfMonth();

            $income = $this->getTotalIncome($subshopIds, $monthStart, $monthEnd);
            $expenses = $this->getTotalExpenses($subshopIds, $monthStart, $monthEnd);
            $profit = $income - $expenses;

            $incomeData[] = round($income, 2);
            $expensesData[] = round($expenses, 2);
            $profitData[] = round($profit, 2);
        }

        return [
            'labels' => $labels,
            'income' => $incomeData,
            'expenses' => $expensesData,
            'profit' => $profitData,
        ];
    }

    /**
     * Calculate Cash Flow (inflows vs outflows).
     */
    private function calculateCashFlow(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        if (! $fromDate || ! $toDate) {
            $fromDate = Carbon::now()->subMonths(11)->startOfMonth();
            $toDate = Carbon::now()->endOfMonth();
        }

        $months = $this->generateMonthLabels($fromDate, $toDate);
        $labels = $months['labels'];

        $inflows = [];
        $outflows = [];

        foreach ($labels as $month) {
            $monthStart = Carbon::parse($month.'-01')->startOfMonth();
            $monthEnd = Carbon::parse($month.'-01')->endOfMonth();

            $inflow = $this->getCashInflows($subshopIds, $monthStart, $monthEnd);
            $outflow = $this->getCashOutflows($subshopIds, $monthStart, $monthEnd);

            $inflows[] = round($inflow, 2);
            $outflows[] = round($outflow, 2);
        }

        return [
            'labels' => $labels,
            'inflows' => $inflows,
            'outflows' => $outflows,
        ];
    }

    /**
     * Calculate Loan Portfolio metrics.
     */
    private function calculateLoanPortfolio(array $subshopIds): array
    {
        // Total Disbursed
        $totalDisbursed = (float) Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->sum('principal_amount');

        // Total Outstanding
        $totalOutstanding = $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops($subshopIds);

        // Active Loans Count
        $activeLoansCount = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereIn('status', ['disbursed', 'partially_paid'])
            ->count();

        // Paid Off Loans Count
        $paidOffCount = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->where('status', 'paid_off')
            ->count();

        // Total Released Loans
        $totalReleasedLoans = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted', 'paid_off', 'written_off'])
            ->count();

        // Total Active Loans (status: disbursed, partially_paid)
        $activeLoanCount = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereIn('status', ['disbursed', 'partially_paid'])
            ->count();

        // Total Completed Loans (status: paid_off)
        $completedLoansCount = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->where('status', 'paid_off')
            ->count();

        // Total Restructured Loans (status: executed)
        $restructuredLoansCount = LoanRestructures::query()
            ->join('loans as l', 'l.id', '=', 'loan_restructures.loan_id')
            ->whereIn('l.subshop_id', $subshopIds)
            ->where('loan_restructures.status', 'executed')
            ->count();

        return [
            'total_disbursed' => round($totalDisbursed, 2),
            'total_outstanding' => round($totalOutstanding, 2),
            'active_loans_count' => $activeLoansCount,
            'paid_off_count' => $paidOffCount,
            'total_released_loans' => $totalReleasedLoans,
            'total_active_loans' => $activeLoanCount,
            'total_completed_loans' => $completedLoansCount,
            'total_restructured_loans' => $restructuredLoansCount,
        ];
    }

    /**
     * Calculate Customer statistics.
     */
    private function calculateCustomerStats(array $subshopIds): array
    {
        // Total Registered Customers
        $totalCustomers = Customers::query()
            ->whereIn('subshop_id', $subshopIds)
            ->count();

        // Total Customer Deposits
        $totalDeposits = DepositAccount::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('status', 'active')
            ->sum('balance');

        return [
            'total_registered_customers' => $totalCustomers,
            'total_customer_deposits' => round((float) $totalDeposits, 2),
        ];
    }

    /**
     * Calculate PAR (Portfolio at Risk).
     */
    private function calculatePAR(array $subshopIds): array
    {
        $totalOutstanding = $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops($subshopIds);

        if ($totalOutstanding <= 0) {
            return [
                'par30' => 0.0,
                'par60' => 0.0,
                'par90' => 0.0,
            ];
        }

        $par30 = $this->portfolioRiskCalculator->calculateDelinquentOutstanding(30);
        $par60 = $this->portfolioRiskCalculator->calculateDelinquentOutstanding(60);
        $par90 = $this->portfolioRiskCalculator->calculateDelinquentOutstanding(90);

        return [
            'par30' => round(($par30 / $totalOutstanding) * 100, 2),
            'par60' => round(($par60 / $totalOutstanding) * 100, 2),
            'par90' => round(($par90 / $totalOutstanding) * 100, 2),
        ];
    }

    private function calculatePAR30(array $subshopIds): float
    {
        $totalOutstanding = $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops($subshopIds);

        if ($totalOutstanding <= 0) {
            return 0.0;
        }

        $par30 = $this->portfolioRiskCalculator->calculateDelinquentOutstanding(30);

        return round(($par30 / $totalOutstanding) * 100, 2);
    }

    /**
     * Calculate Income Distribution by account.
     */
    private function calculateIncomeDistribution(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        if (! $fromDate || ! $toDate) {
            $fromDate = Carbon::now()->subMonths(11)->startOfMonth();
            $toDate = Carbon::now()->endOfMonth();
        }

        $filters = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'subshop_id' => null,
        ];

        $report = $this->incomeSummaryService->build($filters, $subshopIds);
        $topIncome = $report['top_income'] ?? [];

        $labels = [];
        $values = [];
        $totalIncome = array_sum(array_map(fn ($i) => $i['amount'] ?? 0, $topIncome));

        foreach ($topIncome as $item) {
            $labels[] = trim(($item['account_code'] ?? '').' '.($item['account_name'] ?? ''));
            $values[] = round((float) ($item['amount'] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => round($totalIncome, 2),
        ];
    }

    /**
     * Calculate Expense Distribution by account.
     */
    private function calculateExpenseDistribution(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        if (! $fromDate || ! $toDate) {
            $fromDate = Carbon::now()->subMonths(11)->startOfMonth();
            $toDate = Carbon::now()->endOfMonth();
        }

        $filters = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'subshop_id' => null,
        ];

        $report = $this->expensesSummaryService->build($filters, $subshopIds);
        $topExpenses = $report['top_expenses'] ?? [];

        $labels = [];
        $values = [];
        $totalExpenses = array_sum(array_map(fn ($i) => $i['amount'] ?? 0, $topExpenses));

        foreach ($topExpenses as $item) {
            $labels[] = trim(($item['account_code'] ?? '').' '.($item['account_name'] ?? ''));
            $values[] = round((float) ($item['amount'] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => round($totalExpenses, 2),
        ];
    }

    /**
     * Calculate Monthly Trends.
     */
    private function calculateMonthlyTrends(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        $profitability = $this->calculateProfitability($subshopIds, $fromDate, $toDate);

        return [
            'labels' => $profitability['labels'],
            'income' => $profitability['income'],
            'expenses' => $profitability['expenses'],
            'profit' => $profitability['profit'],
        ];
    }

    /**
     * Calculate Loan Charts Data.
     */
    private function calculateLoanCharts(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        if (! $fromDate || ! $toDate) {
            $fromDate = Carbon::now()->subMonths(11)->startOfMonth();
            $toDate = Carbon::now()->endOfMonth();
        }

        $months = $this->generateMonthLabels($fromDate, $toDate);
        $labels = $months['labels'];

        $loansReleased = [];
        $loansCollections = [];

        foreach ($labels as $month) {
            $monthStart = Carbon::parse($month.'-01')->startOfMonth();
            $monthEnd = Carbon::parse($month.'-01')->endOfMonth();

            $released = Loans::query()
                ->whereIn('subshop_id', $subshopIds)
                ->where('is_active', true)
                ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted', 'paid_off', 'written_off'])
                ->whereBetween('disbursement_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count();
            $loansReleased[] = $released;

            $collected = LoanPayments::query()
                ->join('loans as l', 'l.id', '=', 'loan_payments.loan_id')
                ->whereIn('l.subshop_id', $subshopIds)
                ->whereIn('loan_payments.status', ['confirmed', 'completed'])
                ->whereBetween('loan_payments.payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('loan_payments.amount');
            $loansCollections[] = round((float) $collected, 2);
        }

        $activeCustomers = Customers::query()
            ->whereIn('subshop_id', $subshopIds)
            ->whereExists(function ($query) use ($subshopIds, $fromDate, $toDate) {
                $query->select(DB::raw(1))
                    ->from('loans')
                    ->whereColumn('loans.customer_id', 'customers.id')
                    ->whereIn('loans.subshop_id', $subshopIds);
                if ($fromDate && $toDate) {
                    $query->whereBetween('loans.disbursement_date', [$fromDate->toDateString(), $toDate->toDateString()]);
                }
            })
            ->count();

        $inactiveCustomers = Customers::query()
            ->whereIn('subshop_id', $subshopIds)
            ->whereNotExists(function ($query) use ($subshopIds, $fromDate, $toDate) {
                $query->select(DB::raw(1))
                    ->from('loans')
                    ->whereColumn('loans.customer_id', 'customers.id')
                    ->whereIn('loans.subshop_id', $subshopIds);
                if ($fromDate && $toDate) {
                    $query->whereBetween('loans.disbursement_date', [$fromDate->toDateString(), $toDate->toDateString()]);
                }
            })
            ->count();

        $totalPaidAmount = LoanPayments::query()
            ->join('loans as l', 'l.id', '=', 'loan_payments.loan_id')
            ->whereIn('l.subshop_id', $subshopIds)
            ->whereIn('loan_payments.status', ['confirmed', 'completed'])
            ->sum('loan_payments.amount');

        $totalDueAmount = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->sum('outstanding_balance');

        return [
            'loans_released' => [
                'labels' => $labels,
                'values' => $loansReleased,
            ],
            'loans_collections' => [
                'labels' => $labels,
                'values' => $loansCollections,
            ],
            'customer_status' => [
                'active' => $activeCustomers,
                'inactive' => $inactiveCustomers,
            ],
            'loan_amount_status' => [
                'paid' => round((float) $totalPaidAmount, 2),
                'due' => round((float) $totalDueAmount, 2),
            ],
        ];
    }

    /**
     * Calculate Branch Performance.
     */
    private function calculateBranchPerformance(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        if (! $fromDate || ! $toDate) {
            $fromDate = Carbon::now()->subMonths(11)->startOfMonth();
            $toDate = Carbon::now()->endOfMonth();
        }

        $subshops = SubShop::query()
            ->whereIn('id', $subshopIds)
            ->where('is_active', true)
            ->get(['id', 'name']);

        $labels = [];
        $income = [];
        $expenses = [];
        $profit = [];

        foreach ($subshops as $subshop) {
            $labels[] = $subshop->name;

            $subshopIncome = $this->getTotalIncome([$subshop->id], $fromDate, $toDate);
            $subshopExpenses = $this->getTotalExpenses([$subshop->id], $fromDate, $toDate);

            $income[] = round($subshopIncome, 2);
            $expenses[] = round($subshopExpenses, 2);
            $profit[] = round($subshopIncome - $subshopExpenses, 2);
        }

        return [
            'labels' => $labels,
            'income' => $income,
            'expenses' => $expenses,
            'profit' => $profit,
        ];
    }

    /**
     * Get Recent Transactions.
     */
    private function getRecentTransactions(array $subshopIds): array
    {
        $transactions = JournalEntries::query()
            ->whereIn('subshop_id', $subshopIds)
            ->with(['lines', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        return $transactions->map(function ($entry) {
            $debit = $entry->lines->sum('debit');
            $credit = $entry->lines->sum('credit');

            return [
                'id' => $entry->id,
                'date' => $entry->transaction_date?->toDateString(),
                'reference' => $entry->reference_type ? ($entry->reference_type.' #'.$entry->reference_id) : 'JE #'.$entry->id,
                'description' => $entry->description,
                'amount' => round($debit, 2),
                'type' => $debit > 0 ? 'debit' : 'credit',
                'created_by' => $entry->creator?->name,
            ];
        })->toArray();
    }

    /**
     * Generate Alerts based on KPIs and PAR.
     */
    private function generateAlerts(array $kpis, array $parData): array
    {
        $alerts = [];

        // PAR Alert
        $par30 = $parData['par30'] ?? 0;
        if ($par30 > 10) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'High Portfolio at Risk',
                'message' => "PAR30 is at {$par30}%, which exceeds the 10% threshold.",
                'action_url' => route('reports.par.index'),
            ];
        } elseif ($par30 > 5) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Elevated Portfolio Risk',
                'message' => "PAR30 is at {$par30}%, approaching the 10% threshold.",
                'action_url' => route('reports.par.index'),
            ];
        }

        // Cash Balance Alert
        $cashBalance = $kpis['cash_balance'] ?? 0;
        $totalExpenses = $kpis['total_expenses'] ?? 0;
        if ($totalExpenses > 0 && $cashBalance < ($totalExpenses * 0.1)) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low Cash Balance',
                'message' => 'Cash balance is below 10% of monthly expenses.',
                'action_url' => route('reports.accounting.cash_flow.index'),
            ];
        }

        // Net Profit Alert
        $netProfit = $kpis['net_profit'] ?? 0;
        $totalIncome = $kpis['total_income'] ?? 0;
        if ($totalIncome > 0 && $netProfit < 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Net Loss',
                'message' => 'The organization is currently operating at a loss.',
                'action_url' => route('reports.accounting.profit_loss.index'),
            ];
        }

        return $alerts;
    }

    /**
     * Build chart data for the frontend.
     */
    private function buildChartData(
        array $profitability,
        array $cashFlow,
        array $incomeDistribution,
        array $expenseDistribution,
        array $monthlyTrends,
        array $branchPerformance
    ): array {
        return [
            'profit_trend' => [
                'labels' => $profitability['labels'],
                'datasets' => [
                    [
                        'label' => 'Net Profit',
                        'data' => $profitability['profit'],
                        'borderColor' => '#10b981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                ],
            ],
            'income_vs_expenses' => [
                'labels' => $profitability['labels'],
                'datasets' => [
                    [
                        'label' => 'Income',
                        'data' => $profitability['income'],
                        'backgroundColor' => '#10b981',
                    ],
                    [
                        'label' => 'Expenses',
                        'data' => $profitability['expenses'],
                        'backgroundColor' => '#ef4444',
                    ],
                ],
            ],
            'cash_flow' => [
                'labels' => $cashFlow['labels'],
                'datasets' => [
                    [
                        'label' => 'Cash Inflows',
                        'data' => $cashFlow['inflows'],
                        'borderColor' => '#10b981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Cash Outflows',
                        'data' => $cashFlow['outflows'],
                        'borderColor' => '#ef4444',
                        'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                ],
            ],
            'income_distribution' => [
                'labels' => $incomeDistribution['labels'],
                'datasets' => [
                    [
                        'data' => $incomeDistribution['values'],
                        'backgroundColor' => [
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                            '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
                        ],
                    ],
                ],
            ],
            'expense_distribution' => [
                'labels' => $expenseDistribution['labels'],
                'datasets' => [
                    [
                        'data' => $expenseDistribution['values'],
                        'backgroundColor' => [
                            '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#06b6d4',
                            '#3b82f6', '#8b5cf6', '#ec4899', '#6366f1', '#14b8a6',
                        ],
                    ],
                ],
            ],
            'monthly_trends' => [
                'labels' => $monthlyTrends['labels'],
                'datasets' => [
                    [
                        'label' => 'Income',
                        'data' => $monthlyTrends['income'],
                        'borderColor' => '#10b981',
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Expenses',
                        'data' => $monthlyTrends['expenses'],
                        'borderColor' => '#ef4444',
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Profit',
                        'data' => $monthlyTrends['profit'],
                        'borderColor' => '#3b82f6',
                        'tension' => 0.4,
                    ],
                ],
            ],
            'branch_performance' => [
                'labels' => $branchPerformance['labels'],
                'datasets' => [
                    [
                        'label' => 'Income',
                        'data' => $branchPerformance['income'],
                        'backgroundColor' => '#10b981',
                    ],
                    [
                        'label' => 'Expenses',
                        'data' => $branchPerformance['expenses'],
                        'backgroundColor' => '#ef4444',
                    ],
                    [
                        'label' => 'Profit',
                        'data' => $branchPerformance['profit'],
                        'backgroundColor' => '#3b82f6',
                    ],
                ],
            ],
        ];
    }

    // Helper methods

    private function getTotalIncome(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): float
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->where(function ($w) {
                $w->whereRaw("UPPER(ac.code) like '4%'")
                    ->orWhereRaw("UPPER(ac.code) like '5%'")
                    ->orWhereRaw("UPPER(ac.code) like '%INCOME%'")
                    ->orWhereRaw("UPPER(ac.code) like '%REVENUE%'")
                    ->orWhereRaw("UPPER(ac.name) like '%INCOME%'")
                    ->orWhereRaw("UPPER(ac.name) like '%REVENUE%'");
            });

        if ($fromDate) {
            $query->whereDate('je.transaction_date', '>=', $fromDate->toDateString());
        }
        if ($toDate) {
            $query->whereDate('je.transaction_date', '<=', $toDate->toDateString());
        }

        $result = $query->selectRaw('SUM(jel.credit - jel.debit) as amount')->first();

        return max(0.0, round((float) ($result->amount ?? 0), 2));
    }

    private function getTotalExpenses(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): float
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->where(function ($w) {
                $w->whereRaw("UPPER(ac.code) like '6%'")
                    ->orWhereRaw("UPPER(ac.code) like '%EXPENSE%'")
                    ->orWhereRaw("UPPER(ac.name) like '%EXPENSE%'")
                    ->orWhereRaw("UPPER(ac.name) like '%COST%'")
                    ->orWhereRaw("UPPER(ac.code) like '%COST%'");
            });

        if ($fromDate) {
            $query->whereDate('je.transaction_date', '>=', $fromDate->toDateString());
        }
        if ($toDate) {
            $query->whereDate('je.transaction_date', '<=', $toDate->toDateString());
        }

        $result = $query->selectRaw('SUM(jel.debit - jel.credit) as amount')->first();

        return max(0.0, round((float) ($result->amount ?? 0), 2));
    }

    private function getCashBalance(array $subshopIds): float
    {
        // Get cash and bank accounts (account class code 1 = Assets)
        $cashAccountIds = ChartsOfAccount::query()
            ->join('account_classes as ac', 'ac.id', '=', 'charts_of_accounts.account_class_id')
            ->join('account_groups as ag', 'ag.id', '=', 'charts_of_accounts.account_group_id')
            ->whereIn('charts_of_accounts.subshop_id', $subshopIds)
            ->where('charts_of_accounts.is_active', 1)
            ->where(function ($w) {
                $w->whereRaw("UPPER(ag.name) like '%CASH%'")
                    ->orWhereRaw("UPPER(ag.name) like '%BANK%'")
                    ->orWhereRaw("UPPER(ag.name) like '%LIQUID%'");
            })
            ->pluck('charts_of_accounts.id')
            ->toArray();

        if (empty($cashAccountIds)) {
            return 0.0;
        }

        $result = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $cashAccountIds)
            ->selectRaw('SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->first();

        $debit = round((float) ($result->total_debit ?? 0), 2);
        $credit = round((float) ($result->total_credit ?? 0), 2);

        return round($debit - $credit, 2);
    }

    private function getCashInflows(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): float
    {
        $cashAccountIds = $this->getCashAccountIds($subshopIds);
        if (empty($cashAccountIds)) {
            return 0.0;
        }

        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $cashAccountIds)
            ->where('jel.debit', '>', 0);

        if ($fromDate) {
            $query->whereDate('je.transaction_date', '>=', $fromDate->toDateString());
        }
        if ($toDate) {
            $query->whereDate('je.transaction_date', '<=', $toDate->toDateString());
        }

        $result = $query->selectRaw('SUM(jel.debit) as total')->first();

        return round((float) ($result->total ?? 0), 2);
    }

    private function getCashOutflows(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): float
    {
        $cashAccountIds = $this->getCashAccountIds($subshopIds);
        if (empty($cashAccountIds)) {
            return 0.0;
        }

        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $cashAccountIds)
            ->where('jel.credit', '>', 0);

        if ($fromDate) {
            $query->whereDate('je.transaction_date', '>=', $fromDate->toDateString());
        }
        if ($toDate) {
            $query->whereDate('je.transaction_date', '<=', $toDate->toDateString());
        }

        $result = $query->selectRaw('SUM(jel.credit) as total')->first();

        return round((float) ($result->total ?? 0), 2);
    }

    private function getCashAccountIds(array $subshopIds): array
    {
        return ChartsOfAccount::query()
            ->join('account_groups as ag', 'ag.id', '=', 'charts_of_accounts.account_group_id')
            ->whereIn('charts_of_accounts.subshop_id', $subshopIds)
            ->where('charts_of_accounts.is_active', 1)
            ->where(function ($w) {
                $w->whereRaw("UPPER(ag.name) like '%CASH%'")
                    ->orWhereRaw("UPPER(ag.name) like '%BANK%'")
                    ->orWhereRaw("UPPER(ag.name) like '%LIQUID%'");
            })
            ->pluck('charts_of_accounts.id')
            ->toArray();
    }

    private function generateMonthLabels(Carbon $fromDate, Carbon $toDate): array
    {
        $labels = [];
        $current = $fromDate->copy()->startOfMonth();
        $end = $toDate->copy()->startOfMonth();

        while ($current->lte($end)) {
            $labels[] = $current->format('Y-m');
            $current->addMonth();
        }

        return ['labels' => $labels];
    }
}
