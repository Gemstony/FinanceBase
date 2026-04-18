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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinancialDashboardService
{
    /** @var array<string, float> Cache for portfolio outstanding by subshop hash */
    private array $cachedOutstanding = [];

    /** @var array<string, array> Cache for cash account IDs by subshop hash */
    private array $cachedCashAccountIds = [];

    /** @var bool|null Cache for disbursement table existence */
    private static ?bool $disbursementTableExists = null;

    public function __construct(
        private readonly LoanBalanceCalculator $loanBalanceCalculator,
        private readonly PortfolioRiskCalculator $portfolioRiskCalculator,
        private readonly IncomeSummaryService $incomeSummaryService,
        private readonly ExpensesSummaryService $expensesSummaryService
    ) {}

    /**
     * Build the financial dashboard data with intelligent caching.
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        // Generate cache key based on parameters (with shop prefix for cache organization)
        $shopId = $this->getShopIdFromSubshops($subshopIds);
        $cacheKey = $this->generateCacheKey($subshopIds, $fromDate, $toDate, $shopId);

        // Cache dashboard data for 5 minutes (300 seconds)
        // Note: Using key-based namespacing instead of tags for broader cache driver compatibility
        return Cache::remember($cacheKey, 300, function () use ($subshopIds, $fromDate, $toDate) {
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
                    'profitability' => [
                        'labels' => $profitability['labels'],
                        'income' => $profitability['income'],
                        'expenses' => $profitability['expenses'],
                        'profit' => $profitability['profit'],
                    ],
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
                    'cached_at' => now()->toDateTimeString(),
                ];
            });
    }

    /**
     * Generate cache key based on parameters.
     * Uses shop-prefixed keys for cache organization and easy invalidation.
     */
    private function generateCacheKey(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate, int $shopId): string
    {
        $fromStr = $fromDate?->toDateString() ?? 'default';
        $toStr = $toDate?->toDateString() ?? 'default';
        $subshopsHash = md5(implode(',', $subshopIds));

        return "dashboard:shop_{$shopId}:{$subshopsHash}:{$fromStr}:{$toStr}";
    }

    /**
     * Get shop ID from subshops for cache tagging.
     */
    private function getShopIdFromSubshops(array $subshopIds): int
    {
        if (empty($subshopIds)) {
            return 0;
        }

        // Get from first subshop
        $subshop = SubShop::query()
            ->whereIn('id', $subshopIds)
            ->first();

        return $subshop?->shop_id ?? 0;
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
        // Total Loan Portfolio (using cached PortfolioRiskCalculator)
        $totalOutstanding = $this->getCachedPortfolioOutstanding($subshopIds);

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
     * Optimized to use single bulk query instead of N+1 monthly queries.
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

        // Fetch all monthly data in single queries (reduces from 24 queries to 2)
        $incomeByMonth = $this->getMonthlyIncomeBulk($subshopIds, $fromDate, $toDate);
        $expensesByMonth = $this->getMonthlyExpensesBulk($subshopIds, $fromDate, $toDate);

        $incomeData = [];
        $expensesData = [];
        $profitData = [];

        foreach ($labels as $month) {
            $income = $incomeByMonth[$month] ?? 0.0;
            $expenses = $expensesByMonth[$month] ?? 0.0;
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
     * Optimized to use single bulk query instead of N+1 monthly queries.
     */
    private function calculateCashFlow(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        if (! $fromDate || ! $toDate) {
            $fromDate = Carbon::now()->subMonths(11)->startOfMonth();
            $toDate = Carbon::now()->endOfMonth();
        }

        $months = $this->generateMonthLabels($fromDate, $toDate);
        $labels = $months['labels'];

        // Get cached cash account IDs
        $cashAccountIds = $this->getCachedCashAccountIds($subshopIds);

        // Fetch all monthly data in single queries (reduces from 24 queries to 2)
        $inflowsByMonth = $this->getMonthlyCashInflowsBulk($subshopIds, $cashAccountIds, $fromDate, $toDate);
        $outflowsByMonth = $this->getMonthlyCashOutflowsBulk($subshopIds, $cashAccountIds, $fromDate, $toDate);

        $inflows = [];
        $outflows = [];

        foreach ($labels as $month) {
            $inflows[] = round($inflowsByMonth[$month] ?? 0.0, 2);
            $outflows[] = round($outflowsByMonth[$month] ?? 0.0, 2);
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

        // Total Outstanding (cached)
        $totalOutstanding = $this->getCachedPortfolioOutstanding($subshopIds);

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
     * Calculate PAR (Portfolio at Risk) with proper subshop scoping and caching.
     */
    private function calculatePAR(array $subshopIds): array
    {
        $totalOutstanding = $this->getCachedPortfolioOutstanding($subshopIds);

        if ($totalOutstanding <= 0) {
            return [
                'par30' => 0.0,
                'par60' => 0.0,
                'par90' => 0.0,
            ];
        }

        // Calculate delinquent outstanding for each threshold with subshop scoping and caching
        $delinquentOutstanding30 = $this->getCachedDelinquentOutstanding(30, $subshopIds);
        $delinquentOutstanding60 = $this->getCachedDelinquentOutstanding(60, $subshopIds);
        $delinquentOutstanding90 = $this->getCachedDelinquentOutstanding(90, $subshopIds);

        return [
            'par30' => round(($delinquentOutstanding30 / $totalOutstanding) * 100, 2),
            'par60' => round(($delinquentOutstanding60 / $totalOutstanding) * 100, 2),
            'par90' => round(($delinquentOutstanding90 / $totalOutstanding) * 100, 2),
        ];
    }

    private function calculatePAR30(array $subshopIds): float
    {
        $totalOutstanding = $this->getCachedPortfolioOutstanding($subshopIds);

        if ($totalOutstanding <= 0) {
            return 0.0;
        }

        // Use subshop-scoped delinquent calculation with caching
        $delinquentOutstanding30 = $this->getCachedDelinquentOutstanding(30, $subshopIds);

        return round(($delinquentOutstanding30 / $totalOutstanding) * 100, 2);
    }

    /**
     * Get cached delinquent outstanding with subshop scoping.
     * Uses array-specific calculation for proper multi-subshop support.
     */
    private function getCachedDelinquentOutstanding(int $days, array $subshopIds): float
    {
        if (empty($subshopIds)) {
            return 0.0;
        }

        $cacheKey = 'delinquent_outstanding:days:' . $days . ':subshops:' . md5(implode(',', $subshopIds));

        return Cache::remember($cacheKey, 300, function () use ($days, $subshopIds) {
            return $this->portfolioRiskCalculator->calculateDelinquentOutstandingForSubshops($days, $subshopIds);
        });
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
     * Optimized to use single bulk query instead of N+1 monthly queries.
     */
    private function calculateLoanCharts(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        if (! $fromDate || ! $toDate) {
            $fromDate = Carbon::now()->subMonths(11)->startOfMonth();
            $toDate = Carbon::now()->endOfMonth();
        }

        $months = $this->generateMonthLabels($fromDate, $toDate);
        $labels = $months['labels'];

        // Fetch all monthly data in single queries (reduces from 24 queries to 2)
        $loansReleasedByMonth = $this->getMonthlyLoansReleasedBulk($subshopIds, $fromDate, $toDate);
        $collectionsByMonth = $this->getMonthlyCollectionsBulk($subshopIds, $fromDate, $toDate);

        $loansReleased = [];
        $loansCollections = [];

        foreach ($labels as $month) {
            $loansReleased[] = (int) ($loansReleasedByMonth[$month] ?? 0);
            $loansCollections[] = round((float) ($collectionsByMonth[$month] ?? 0.0), 2);
        }

        // Optimized customer status query using single query with conditional count
        $customerStatus = $this->getCustomerStatusCounts($subshopIds, $fromDate, $toDate);

        // Total paid amount - single query for all time
        $totalPaidAmount = LoanPayments::query()
            ->join('loans as l', 'l.id', '=', 'loan_payments.loan_id')
            ->whereIn('l.subshop_id', $subshopIds)
            ->whereIn('loan_payments.status', ['confirmed', 'completed'])
            ->sum('loan_payments.amount');

        $totalDueAmount = $this->getCachedPortfolioOutstanding($subshopIds);

        return [
            'loans_released' => [
                'labels' => $labels,
                'values' => $loansReleased,
            ],
            'loans_collections' => [
                'labels' => $labels,
                'values' => $loansCollections,
            ],
            'customer_status' => $customerStatus,
            'loan_amount_status' => [
                'paid' => round((float) $totalPaidAmount, 2),
                'due' => round((float) $totalDueAmount, 2),
            ],
        ];
    }

    /**
     * Get customer status counts in optimized single query.
     */
    private function getCustomerStatusCounts(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        // Use a single query to get both active and inactive counts
        $query = Customers::query()
            ->whereIn('subshop_id', $subshopIds)
            ->selectRaw('
                COUNT(DISTINCT CASE WHEN EXISTS (
                    SELECT 1 FROM loans 
                    WHERE loans.customer_id = customers.id 
                    AND loans.subshop_id IN (' . implode(',', $subshopIds) . ')' .
                    ($fromDate && $toDate ? ' AND loans.disbursement_date BETWEEN \'' . $fromDate->toDateString() . '\' AND \'' . $toDate->toDateString() . '\'' : '') .
                ') THEN customers.id END) as active_count,
                COUNT(DISTINCT CASE WHEN NOT EXISTS (
                    SELECT 1 FROM loans 
                    WHERE loans.customer_id = customers.id 
                    AND loans.subshop_id IN (' . implode(',', $subshopIds) . ')' .
                    ($fromDate && $toDate ? ' AND loans.disbursement_date BETWEEN \'' . $fromDate->toDateString() . '\' AND \'' . $toDate->toDateString() . '\'' : '') .
                ') THEN customers.id END) as inactive_count
            ');

        $result = $query->first();

        return [
            'active' => (int) ($result->active_count ?? 0),
            'inactive' => (int) ($result->inactive_count ?? 0),
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
     * Optimized to reduce memory usage by selecting only needed columns.
     */
    private function getRecentTransactions(array $subshopIds): array
    {
        // Use a single query with joins to avoid N+1 and reduce memory
        $transactions = DB::table('journal_entries as je')
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by')
            ->leftJoin('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->select([
                'je.id',
                'je.transaction_date',
                'je.reference_type',
                'je.reference_id',
                'je.description',
                'u.name as created_by_name',
                DB::raw('SUM(jel.debit) as total_debit'),
                DB::raw('SUM(jel.credit) as total_credit'),
            ])
            ->groupBy('je.id', 'je.transaction_date', 'je.reference_type', 'je.reference_id', 'je.description', 'u.name')
            ->orderBy('je.transaction_date', 'desc')
            ->orderBy('je.id', 'desc')
            ->limit(20)
            ->get();

        return $transactions->map(function ($entry) {
            $debit = (float) ($entry->total_debit ?? 0);
            $credit = (float) ($entry->total_credit ?? 0);

            return [
                'id' => $entry->id,
                'date' => $entry->transaction_date ? Carbon::parse($entry->transaction_date)->toDateString() : null,
                'reference' => $entry->reference_type ? ($entry->reference_type.' #'.$entry->reference_id) : 'JE #'.$entry->id,
                'description' => $entry->description,
                'amount' => round($debit, 2),
                'type' => $debit > 0 ? 'debit' : 'credit',
                'created_by' => $entry->created_by_name,
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
                    ->orWhereRaw("UPPER(ac.name) like '%INCOME%'");
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
                $w->whereRaw("UPPER(ac.code) like '5%'")
                    ->orWhereRaw("UPPER(ac.name) like '%EXPENSE%'");
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
        // Get cached cash and bank accounts
        $cashAccountIds = $this->getCachedCashAccountIds($subshopIds);

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
        $cashAccountIds = $this->getCachedCashAccountIds($subshopIds);
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
        $cashAccountIds = $this->getCachedCashAccountIds($subshopIds);
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
            ->join('account_classes as ac', 'ac.id', '=', 'charts_of_accounts.account_class_id')
            ->whereIn('charts_of_accounts.subshop_id', $subshopIds)
            ->where('charts_of_accounts.is_active', 1)
            ->whereRaw("UPPER(ac.code) like '1%'")  // Assets class (1, 10, 100, etc.)
            ->pluck('charts_of_accounts.id')
            ->toArray();
    }

    /**
     * Get cached portfolio outstanding to avoid repeated calculations.
     */
    private function getCachedPortfolioOutstanding(array $subshopIds): float
    {
        $key = implode(',', $subshopIds);
        if (!isset($this->cachedOutstanding[$key])) {
            $this->cachedOutstanding[$key] = $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops($subshopIds);
        }
        return $this->cachedOutstanding[$key];
    }

    /**
     * Get cached cash account IDs to avoid repeated queries.
     */
    private function getCachedCashAccountIds(array $subshopIds): array
    {
        $key = implode(',', $subshopIds);
        if (!isset($this->cachedCashAccountIds[$key])) {
            $this->cachedCashAccountIds[$key] = $this->getCashAccountIds($subshopIds);
        }
        return $this->cachedCashAccountIds[$key];
    }

    /**
     * Check if loan_disbursements table exists (cached).
     */
    private static function disbursementTableExists(): bool
    {
        if (self::$disbursementTableExists === null) {
            self::$disbursementTableExists = \Illuminate\Support\Facades\Schema::hasTable('loan_disbursements');
        }
        return self::$disbursementTableExists;
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

    /**
     * Get monthly income data in a single query (optimized for bulk retrieval).
     */
    private function getMonthlyIncomeBulk(array $subshopIds, Carbon $fromDate, Carbon $toDate): array
    {
        $results = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereBetween('je.transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->where(function ($w) {
                $w->whereRaw("UPPER(ac.code) like '4%'")
                    ->orWhereRaw("UPPER(ac.name) like '%INCOME%'");
            })
            ->selectRaw("DATE_FORMAT(je.transaction_date, '%Y-%m') as month, SUM(jel.credit - jel.debit) as amount")
            ->groupBy('month')
            ->pluck('amount', 'month')
            ->toArray();

        return array_map(fn($v) => max(0.0, round((float) $v, 2)), $results);
    }

    /**
     * Get monthly expense data in a single query (optimized for bulk retrieval).
     */
    private function getMonthlyExpensesBulk(array $subshopIds, Carbon $fromDate, Carbon $toDate): array
    {
        $results = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereBetween('je.transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->where(function ($w) {
                $w->whereRaw("UPPER(ac.code) like '5%'")
                    ->orWhereRaw("UPPER(ac.name) like '%EXPENSE%'");
            })
            ->selectRaw("DATE_FORMAT(je.transaction_date, '%Y-%m') as month, SUM(jel.debit - jel.credit) as amount")
            ->groupBy('month')
            ->pluck('amount', 'month')
            ->toArray();

        return array_map(fn($v) => max(0.0, round((float) $v, 2)), $results);
    }

    /**
     * Get monthly cash inflows in a single query (optimized for bulk retrieval).
     */
    private function getMonthlyCashInflowsBulk(array $subshopIds, array $cashAccountIds, Carbon $fromDate, Carbon $toDate): array
    {
        if (empty($cashAccountIds)) {
            return [];
        }

        $results = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $cashAccountIds)
            ->where('jel.debit', '>', 0)
            ->whereBetween('je.transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->selectRaw("DATE_FORMAT(je.transaction_date, '%Y-%m') as month, SUM(jel.debit) as amount")
            ->groupBy('month')
            ->pluck('amount', 'month')
            ->toArray();

        return array_map(fn($v) => round((float) $v, 2), $results);
    }

    /**
     * Get monthly cash outflows in a single query (optimized for bulk retrieval).
     */
    private function getMonthlyCashOutflowsBulk(array $subshopIds, array $cashAccountIds, Carbon $fromDate, Carbon $toDate): array
    {
        if (empty($cashAccountIds)) {
            return [];
        }

        $results = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $cashAccountIds)
            ->where('jel.credit', '>', 0)
            ->whereBetween('je.transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->selectRaw("DATE_FORMAT(je.transaction_date, '%Y-%m') as month, SUM(jel.credit) as amount")
            ->groupBy('month')
            ->pluck('amount', 'month')
            ->toArray();

        return array_map(fn($v) => round((float) $v, 2), $results);
    }

    /**
     * Get monthly loans released count in a single query (optimized for bulk retrieval).
     */
    private function getMonthlyLoansReleasedBulk(array $subshopIds, Carbon $fromDate, Carbon $toDate): array
    {
        return Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted', 'paid_off', 'written_off'])
            ->whereBetween('disbursement_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->selectRaw("DATE_FORMAT(disbursement_date, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();
    }

    /**
     * Get monthly collections sum in a single query (optimized for bulk retrieval).
     */
    private function getMonthlyCollectionsBulk(array $subshopIds, Carbon $fromDate, Carbon $toDate): array
    {
        $results = LoanPayments::query()
            ->join('loans as l', 'l.id', '=', 'loan_payments.loan_id')
            ->whereIn('l.subshop_id', $subshopIds)
            ->whereIn('loan_payments.status', ['confirmed', 'completed'])
            ->whereBetween('loan_payments.payment_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->selectRaw("DATE_FORMAT(loan_payments.payment_date, '%Y-%m') as month, SUM(loan_payments.amount) as amount")
            ->groupBy('month')
            ->pluck('amount', 'month')
            ->toArray();

        // Cast string values to float to avoid TypeError with round()
        return array_map(fn($v) => (float) $v, $results);
    }
}
