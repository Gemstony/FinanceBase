<?php

namespace App\Services\Reports\Accounting;

use App\Models\LoanFeeApplications;
use App\Models\LoanFees;
use App\Models\LoanProducts;
use App\Models\Loans;
use App\Models\Customers;
use App\Models\JournalEntryLines;
use App\Models\ChartsOfAccount;
use App\Models\SubShop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;


class FeesReportService
{
    /**
     * Build the fees report data
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        $subshopId = $filters['subshop_id'] ?? null;
        $feeTypeId = $filters['fee_type_id'] ?? null;
        $loanProductId = $filters['loan_product_id'] ?? null;
        $status = $filters['status'] ?? null;

        // Build base query for fee applications
        $feeQuery = $this->buildFeeQuery($dateFrom, $dateTo, $subshopId, $feeTypeId, $loanProductId, $status, $accessibleSubshopIds);

        // Get detailed records
        $details = $this->getDetailedRecords($feeQuery);

        // Get summary by fee type
        $summaryByFeeType = $this->getSummaryByFeeType($feeQuery);

        // Get summary metrics
        $metrics = $this->getSummaryMetrics($details);

        // Get GL validation data
        $glValidation = $this->getGLValidation($dateFrom, $dateTo, $subshopId, $accessibleSubshopIds);

        // Get top fees
        $topFees = $this->getTopFees($feeQuery);

        // Get trend data
        $trendData = $this->getTrendData($dateFrom, $dateTo, $subshopId, $accessibleSubshopIds);

        // Get chart data
        $chartData = $this->getChartData($summaryByFeeType, $topFees, $trendData);

        return [
            'details' => $details,
            'summary_by_fee_type' => $summaryByFeeType,
            'metrics' => $metrics,
            'gl_validation' => $glValidation,
            'top_fees' => $topFees,
            'trend_data' => $trendData,
            'chart_data' => $chartData,
        ];
    }

    /**
     * Build the base fee query with joins and filters
     */
    private function buildFeeQuery($dateFrom, $dateTo, $subshopId, $feeTypeId, $loanProductId, $status, array $accessibleSubshopIds)
    {
        $query = LoanFeeApplications::query()
            ->select([
                'loan_fee_applications.id',
                'loan_fee_applications.loan_id',
                'loan_fee_applications.loan_product_fee_id',
                'loan_fee_applications.amount',
                'loan_fee_applications.applied_on',
                'loan_fee_applications.is_paid',
                'loan_fee_applications.charge_event',
                'loans.loan_code',
                'loans.subshop_id',
                'loans.loan_product_id',
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'loan_products.name as loan_product_name',
                'loan_fees.id as fee_id',
                'loan_fees.name as fee_name',
                'loan_fees.fee_type',
                'loan_fees.code as fee_code',
            ])
            ->join('loans', 'loan_fee_applications.loan_id', '=', 'loans.id')
            ->join('customers', 'loans.customer_id', '=', 'customers.id')
            ->join('loan_products', 'loans.loan_product_id', '=', 'loan_products.id')
            ->join('loan_product_fees', 'loan_fee_applications.loan_product_fee_id', '=', 'loan_product_fees.id')
            ->join('loan_fees', 'loan_product_fees.loan_fee_id', '=', 'loan_fees.id')
            ->whereBetween('loan_fee_applications.applied_on', [$dateFrom, $dateTo]);

        // Apply subshop filter
        if ($subshopId) {
            $query->where('loans.subshop_id', $subshopId);
        } else {
            $query->whereIn('loans.subshop_id', $accessibleSubshopIds);
        }

        // Apply fee type filter
        if ($feeTypeId) {
            $query->where('loan_fees.id', $feeTypeId);
        }

        // Apply loan product filter
        if ($loanProductId) {
            $query->where('loans.loan_product_id', $loanProductId);
        }

        // Apply status filter
        if ($status === 'paid') {
            $query->where('loan_fee_applications.is_paid', true);
        } elseif ($status === 'charged') {
            // All fees are charged by default
        } elseif ($status === 'outstanding') {
            $query->where('loan_fee_applications.is_paid', false);
        }

        return $query;
    }

    /**
     * Get detailed fee records with paid amounts
     */
    private function getDetailedRecords($feeQuery): Collection
    {
        $details = $feeQuery->orderBy('loan_fee_applications.applied_on', 'desc')
            ->get();

        // Calculate paid amounts for each fee
        return $details->map(function ($fee) {
            $paidAmount = $this->getPaidAmountForFee($fee->id);
            $fee->paid_amount = $paidAmount;
            $fee->outstanding_amount = $fee->amount - $paidAmount;
            // Determine status based on whether there's any outstanding amount
            $fee->status = $fee->outstanding_amount > 0 ? 'outstanding' : 'paid';
            return $fee;
        });
    }

    /**
     * Get paid amount for a specific fee application
     */
    private function getPaidAmountForFee(int $feeApplicationId): float
    {
        // Get fee application details to find related payments
        $feeApplication = LoanFeeApplications::find($feeApplicationId);
        
        if (!$feeApplication) {
            return 0;
        }

        // If marked as paid in the fee application, return full amount
        if ($feeApplication->is_paid) {
            return (float) $feeApplication->amount;
        }

        // Otherwise, get payments for this loan and calculate allocated fee amounts
        $loanId = $feeApplication->loan_id;
        
        // Get total fee allocations from loan payments for this loan
        $paidAmount = DB::table('loan_payment_allocations')
            ->join('loan_payments', 'loan_payment_allocations.loan_payment_id', '=', 'loan_payments.id')
            ->where('loan_payments.loan_id', $loanId)
            ->where('loan_payment_allocations.fee_amount', '>', 0)
            ->sum('loan_payment_allocations.fee_amount');

        // Cap at the charged amount
        return min((float) $paidAmount, (float) $feeApplication->amount);
    }

    /**
     * Get summary by fee type
     */
    private function getSummaryByFeeType($feeQuery): Collection
    {
        return $feeQuery->clone()
            ->select([
                'loan_fees.id as fee_id',
                'loan_fees.name as fee_name',
                'loan_fees.fee_type',
                'loan_fees.code as fee_code',
                DB::raw('SUM(loan_fee_applications.amount) as total_charged'),
            ])
            ->groupBy('loan_fees.id', 'loan_fees.name', 'loan_fees.fee_type', 'loan_fees.code')
            ->get()
            ->map(function ($item) {
                // Calculate paid amount per fee type
                $paidAmount = DB::table('loan_fee_applications')
                    ->join('loans', 'loan_fee_applications.loan_id', '=', 'loans.id')
                    ->join('loan_product_fees', 'loan_fee_applications.loan_product_fee_id', '=', 'loan_product_fees.id')
                    ->join('loan_fees', 'loan_product_fees.loan_fee_id', '=', 'loan_fees.id')
                    ->where('loan_fees.id', $item->fee_id)
                    ->sum(DB::raw('(
                        SELECT COALESCE(SUM(lpa.fee_amount), 0)
                        FROM loan_payment_allocations lpa
                        JOIN loan_payments lp ON lpa.loan_payment_id = lp.id
                        WHERE lp.loan_id = loans.id
                    )'));

                $item->total_paid = $paidAmount;
                $item->total_outstanding = $item->total_charged - $paidAmount;
                $item->collection_rate = $item->total_charged > 0 
                    ? round(($paidAmount / $item->total_charged) * 100, 2) 
                    : 0;
                return $item;
            });
    }

    /**
     * Get summary metrics
     */
    private function getSummaryMetrics(Collection $details): array
    {
        $totalCharged = $details->sum('amount');
        $totalPaid = $details->sum('paid_amount');
        $totalOutstanding = $totalCharged - $totalPaid;
        $collectionRate = $totalCharged > 0 ? round(($totalPaid / $totalCharged) * 100, 2) : 0;

        return [
            'total_charged' => $totalCharged,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,
            'collection_rate' => $collectionRate,
            'total_transactions' => $details->count(),
            'paid_count' => $details->where('is_paid', true)->count(),
            'outstanding_count' => $details->where('is_paid', false)->count(),
        ];
    }

    /**
     * Get GL validation data
     */
    private function getGLValidation($dateFrom, $dateTo, $subshopId, array $accessibleSubshopIds): array
    {
        // Get all fee income accounts from account groups
        $feeIncomeAccounts = ChartsOfAccount::whereHas('accountGroup', function ($query) {
            $query->where('name', 'like', '%Fee Income%')
                  ->orWhere('name', 'like', '%Service Charge%');
        })->get();

        $feeIncomeAccountIds = $feeIncomeAccounts->pluck('id')->toArray();

        // Also get fee income accounts from loan fees
        $loanFeeIncomeAccountIds = LoanFees::whereNotNull('income_account_id')
            ->where('income_account_id', '>', 0)
            ->distinct()
            ->pluck('income_account_id')
            ->toArray();

        $allFeeAccountIds = array_unique(array_merge($feeIncomeAccountIds, $loanFeeIncomeAccountIds));

        $query = JournalEntryLines::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_lines.account_id', $allFeeAccountIds)
            ->whereBetween('journal_entries.transaction_date', [$dateFrom, $dateTo]);

        if ($subshopId) {
            $query->where('journal_entries.subshop_id', $subshopId);
        } else {
            $query->whereIn('journal_entries.subshop_id', $accessibleSubshopIds);
        }

        $feeIncomeGl = $query->clone()
            ->selectRaw('SUM(COALESCE(journal_entry_lines.credit, 0)) - SUM(COALESCE(journal_entry_lines.debit, 0)) as net_amount')
            ->value('net_amount') ?? 0;

        return [
            'fee_income_gl' => (float) $feeIncomeGl,
            'fee_income_accounts' => $feeIncomeAccounts->count(),
        ];
    }

    /**
     * Get top fees by charged amount
     */
    private function getTopFees($feeQuery): Collection
    {
        return $feeQuery->clone()
            ->select([
                'loan_fees.id as fee_id',
                'loan_fees.name as fee_name',
                'loan_fees.code as fee_code',
                DB::raw('SUM(loan_fee_applications.amount) as total_charged'),
                DB::raw('COUNT(*) as application_count'),
            ])
            ->groupBy('loan_fees.id', 'loan_fees.name', 'loan_fees.code')
            ->orderByDesc('total_charged')
            ->limit(5)
            ->get();
    }

    /**
     * Get trend data by month
     */
    private function getTrendData($dateFrom, $dateTo, $subshopId, array $accessibleSubshopIds): Collection
    {
        $query = LoanFeeApplications::query()
            ->select([
                DB::raw('MONTH(loan_fee_applications.applied_on) as month'),
                DB::raw('YEAR(loan_fee_applications.applied_on) as year'),
                DB::raw('SUM(loan_fee_applications.amount) as total_fees'),
                DB::raw('COUNT(*) as fee_count'),
            ])
            ->join('loans', 'loan_fee_applications.loan_id', '=', 'loans.id')
            ->whereBetween('loan_fee_applications.applied_on', [$dateFrom, $dateTo]);

        if ($subshopId) {
            $query->where('loans.subshop_id', $subshopId);
        } else {
            $query->whereIn('loans.subshop_id', $accessibleSubshopIds);
        }

        return $query->groupBy(DB::raw('MONTH(loan_fee_applications.applied_on)'), DB::raw('YEAR(loan_fee_applications.applied_on)'))
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                $item->month_name = date('M', mktime(0, 0, 0, $item->month, 1));
                return $item;
            });
    }

    /**
     * Get chart data
     */
    private function getChartData(Collection $summaryByFeeType, Collection $topFees, Collection $trendData): array
    {
        // Pie chart - Fee distribution by type
        $pieChart = [
            'labels' => $summaryByFeeType->pluck('fee_name')->toArray(),
            'values' => $summaryByFeeType->pluck('total_charged')->toArray(),
            'colors' => $this->generateColors($summaryByFeeType->count()),
        ];

        // Bar chart - Top fees
        $barChart = [
            'labels' => $topFees->pluck('fee_name')->toArray(),
            'values' => $topFees->pluck('total_charged')->toArray(),
            'colors' => $this->generateColors($topFees->count()),
        ];

        // Line chart - Trend
        $lineChart = [
            'labels' => $trendData->pluck('month_name')->toArray(),
            'values' => $trendData->pluck('total_fees')->toArray(),
        ];

        return [
            'pie_chart' => $pieChart,
            'bar_chart' => $barChart,
            'line_chart' => $lineChart,
        ];
    }

    /**
     * Generate random colors for charts
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

    /**
     * Get fee types for filter dropdown
     */
    public function getFeeTypes(array $accessibleSubshopIds): Collection
    {
        return LoanFees::whereIn('subshop_id', $accessibleSubshopIds)
            ->orderBy('name')
            ->get(['id', 'name', 'fee_type', 'code']);
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
}
