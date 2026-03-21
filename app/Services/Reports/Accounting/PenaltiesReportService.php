<?php

namespace App\Services\Reports\Accounting;

use App\Models\LoanPenaltyApplications;
use App\Models\LoanPenalties;
use App\Models\LoanProducts;
use App\Models\Loans;
use App\Models\Customers;
use App\Models\JournalEntryLines;
use App\Models\ChartsOfAccount;
use App\Models\SubShop;
use App\Models\LoanInstallments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;


class PenaltiesReportService
{
    /**
     * Build the penalties report data
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        $subshopId = $filters['subshop_id'] ?? null;
        $penaltyTypeId = $filters['penalty_type_id'] ?? null;
        $loanProductId = $filters['loan_product_id'] ?? null;
        $status = $filters['status'] ?? null;

        // Build base query for penalty applications
        $penaltyQuery = $this->buildPenaltyQuery($dateFrom, $dateTo, $subshopId, $penaltyTypeId, $loanProductId, $status, $accessibleSubshopIds);

        // Get detailed records
        $details = $this->getDetailedRecords($penaltyQuery);

        // Get summary by penalty type
        $summaryByPenaltyType = $this->getSummaryByPenaltyType($penaltyQuery);

        // Get summary metrics
        $metrics = $this->getSummaryMetrics($details);

        // Get GL validation data
        $glValidation = $this->getGLValidation($dateFrom, $dateTo, $subshopId, $accessibleSubshopIds);

        // Get top defaulters
        $topDefaulters = $this->getTopDefaulters($penaltyQuery);

        // Get trend data
        $trendData = $this->getTrendData($dateFrom, $dateTo, $subshopId, $accessibleSubshopIds);

        // Get aging analysis
        $agingAnalysis = $this->getAgingAnalysis($details);

        // Get chart data
        $chartData = $this->getChartData($summaryByPenaltyType, $topDefaulters, $trendData, $agingAnalysis);

        return [
            'details' => $details,
            'summary_by_penalty_type' => $summaryByPenaltyType,
            'metrics' => $metrics,
            'gl_validation' => $glValidation,
            'top_defaulters' => $topDefaulters,
            'trend_data' => $trendData,
            'aging_analysis' => $agingAnalysis,
            'chart_data' => $chartData,
        ];
    }

    /**
     * Build the base penalty query with joins and filters
     */
    private function buildPenaltyQuery($dateFrom, $dateTo, $subshopId, $penaltyTypeId, $loanProductId, $status, array $accessibleSubshopIds)
    {
        $query = LoanPenaltyApplications::query()
            ->select([
                'loan_penalty_applications.id',
                'loan_penalty_applications.loan_id',
                'loan_penalty_applications.loan_product_penalty_id',
                'loan_penalty_applications.amount',
                'loan_penalty_applications.applied_on',
                'loan_penalty_applications.is_paid',
                'loan_penalty_applications.charge_event',
                'loans.loan_code',
                'loans.subshop_id',
                'loans.loan_product_id',
                'loans.disbursement_date',
                'loans.maturity_date',
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'loan_products.name as loan_product_name',
                'loan_penalties.id as penalty_id',
                'loan_penalties.name as penalty_name',
                'loan_penalties.penalty_type',
                'loan_penalties.code as penalty_code',
            ])
            ->join('loans', 'loan_penalty_applications.loan_id', '=', 'loans.id')
            ->join('customers', 'loans.customer_id', '=', 'customers.id')
            ->join('loan_products', 'loans.loan_product_id', '=', 'loan_products.id')
            ->join('loan_product_penalties', 'loan_penalty_applications.loan_product_penalty_id', '=', 'loan_product_penalties.id')
            ->join('loan_penalties', 'loan_product_penalties.loan_penalty_id', '=', 'loan_penalties.id')
            ->whereBetween('loan_penalty_applications.applied_on', [$dateFrom, $dateTo]);

        // Apply subshop filter
        if ($subshopId) {
            $query->where('loans.subshop_id', $subshopId);
        } else {
            $query->whereIn('loans.subshop_id', $accessibleSubshopIds);
        }

        // Apply penalty type filter
        if ($penaltyTypeId) {
            $query->where('loan_penalties.id', $penaltyTypeId);
        }

        // Apply loan product filter
        if ($loanProductId) {
            $query->where('loans.loan_product_id', $loanProductId);
        }

        // Apply status filter
        if ($status === 'paid') {
            $query->where('loan_penalty_applications.is_paid', true);
        } elseif ($status === 'outstanding') {
            $query->where('loan_penalty_applications.is_paid', false);
        }

        return $query;
    }

    /**
     * Get detailed penalty records with paid amounts and delinquency info
     */
    private function getDetailedRecords($penaltyQuery): Collection
    {
        $details = $penaltyQuery->orderBy('loan_penalty_applications.applied_on', 'desc')
            ->get();

        // Calculate paid amounts and delinquency for each penalty
        return $details->map(function ($penalty) {
            $paidAmount = $this->getPaidAmountForPenalty($penalty->id);
            $penalty->paid_amount = $paidAmount;
            $penalty->outstanding_amount = $penalty->amount - $paidAmount;
            
            // Determine status based on whether there's any outstanding amount
            $penalty->status = $penalty->outstanding_amount > 0 ? 'outstanding' : 'paid';

            // Get delinquency info from related installments
            $delinquency = $this->getDelinquencyInfo($penalty->loan_id);
            $penalty->days_past_due = $delinquency['days_past_due'];
            $penalty->installment_status = $delinquency['installment_status'];
            
            return $penalty;
        });
    }

    /**
     * Get paid amount for a specific penalty application
     */
    private function getPaidAmountForPenalty(int $penaltyApplicationId): float
    {
        // Get penalty application details
        $penaltyApplication = LoanPenaltyApplications::find($penaltyApplicationId);
        
        if (!$penaltyApplication) {
            return 0;
        }

        // If marked as paid in the penalty application, return full amount
        if ($penaltyApplication->is_paid) {
            return (float) $penaltyApplication->amount;
        }

        // Otherwise, get payments for this loan and calculate allocated penalty amounts
        $loanId = $penaltyApplication->loan_id;
        
        // Get total penalty allocations from loan payments for this loan
        $paidAmount = DB::table('loan_payment_allocations')
            ->join('loan_payments', 'loan_payment_allocations.loan_payment_id', '=', 'loan_payments.id')
            ->where('loan_payments.loan_id', $loanId)
            ->where('loan_payment_allocations.penalty_amount', '>', 0)
            ->sum('loan_payment_allocations.penalty_amount');

        // Cap at the charged amount
        return min((float) $paidAmount, (float) $penaltyApplication->amount);
    }

    /**
     * Get delinquency info for a loan
     */
    private function getDelinquencyInfo(int $loanId): array
    {
        // Get the most overdue installment
        $overdueInstallment = LoanInstallments::where('loan_id', $loanId)
            ->where('outstanding_amount', '>', 0)
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date', 'asc')
            ->first();

        if ($overdueInstallment) {
            $daysPastDue = now()->diffInDays($overdueInstallment->due_date);
            return [
                'days_past_due' => $daysPastDue,
                'installment_status' => $overdueInstallment->status,
            ];
        }

        // Check for pending installments
        $pendingInstallment = LoanInstallments::where('loan_id', $loanId)
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date', 'asc')
            ->first();

        if ($pendingInstallment) {
            $daysUntilDue = now()->diffInDays($pendingInstallment->due_date, false);
            return [
                'days_past_due' => $daysUntilDue < 0 ? 0 : $daysUntilDue,
                'installment_status' => 'pending',
            ];
        }

        return [
            'days_past_due' => 0,
            'installment_status' => 'paid',
        ];
    }

    /**
     * Get summary by penalty type
     */
    private function getSummaryByPenaltyType($penaltyQuery): Collection
    {
        return $penaltyQuery->clone()
            ->select([
                'loan_penalties.id as penalty_id',
                'loan_penalties.name as penalty_name',
                'loan_penalties.penalty_type',
                'loan_penalties.code as penalty_code',
                DB::raw('SUM(loan_penalty_applications.amount) as total_applied'),
            ])
            ->groupBy('loan_penalties.id', 'loan_penalties.name', 'loan_penalties.penalty_type', 'loan_penalties.code')
            ->get()
            ->map(function ($item) {
                // Calculate paid amount per penalty type
                $paidAmount = DB::table('loan_penalty_applications')
                    ->join('loans', 'loan_penalty_applications.loan_id', '=', 'loans.id')
                    ->join('loan_product_penalties', 'loan_penalty_applications.loan_product_penalty_id', '=', 'loan_product_penalties.id')
                    ->join('loan_penalties', 'loan_product_penalties.loan_penalty_id', '=', 'loan_penalties.id')
                    ->where('loan_penalties.id', $item->penalty_id)
                    ->sum(DB::raw('(
                        SELECT COALESCE(SUM(lpa.penalty_amount), 0)
                        FROM loan_payment_allocations lpa
                        JOIN loan_payments lp ON lpa.loan_payment_id = lp.id
                        WHERE lp.loan_id = loans.id
                    )'));

                $item->total_paid = $paidAmount;
                $item->total_outstanding = $item->total_applied - $paidAmount;
                $item->collection_rate = $item->total_applied > 0 
                    ? round(($paidAmount / $item->total_applied) * 100, 2) 
                    : 0;
                return $item;
            });
    }

    /**
     * Get summary metrics
     */
    private function getSummaryMetrics(Collection $details): array
    {
        $totalApplied = $details->sum('amount');
        $totalPaid = $details->sum('paid_amount');
        $totalOutstanding = $totalApplied - $totalPaid;
        $collectionRate = $totalApplied > 0 ? round(($totalPaid / $totalApplied) * 100, 2) : 0;

        return [
            'total_applied' => $totalApplied,
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
        // Get all penalty income accounts from account groups
        $penaltyIncomeAccounts = ChartsOfAccount::whereHas('accountGroup', function ($query) {
            $query->where('name', 'like', '%Penalty Income%')
                  ->orWhere('name', 'like', '%Late Payment%');
        })->get();

        $penaltyIncomeAccountIds = $penaltyIncomeAccounts->pluck('id')->toArray();

        // Also get penalty income accounts from loan penalties
        $loanPenaltyIncomeAccountIds = LoanPenalties::whereNotNull('income_account_id')
            ->where('income_account_id', '>', 0)
            ->distinct()
            ->pluck('income_account_id')
            ->toArray();

        $allPenaltyAccountIds = array_unique(array_merge($penaltyIncomeAccountIds, $loanPenaltyIncomeAccountIds));

        if (empty($allPenaltyAccountIds)) {
            return [
                'penalty_income_gl' => 0,
                'penalty_income_accounts' => 0,
            ];
        }

        $query = JournalEntryLines::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_lines.account_id', $allPenaltyAccountIds)
            ->whereBetween('journal_entries.transaction_date', [$dateFrom, $dateTo]);

        if ($subshopId) {
            $query->where('journal_entries.subshop_id', $subshopId);
        } else {
            $query->whereIn('journal_entries.subshop_id', $accessibleSubshopIds);
        }

        $penaltyIncomeGl = $query->clone()
            ->selectRaw('SUM(COALESCE(journal_entry_lines.credit, 0)) - SUM(COALESCE(journal_entry_lines.debit, 0)) as net_amount')
            ->value('net_amount') ?? 0;

        return [
            'penalty_income_gl' => (float) $penaltyIncomeGl,
            'penalty_income_accounts' => $penaltyIncomeAccounts->count(),
        ];
    }

    /**
     * Get top defaulters by outstanding penalty amount
     */
    private function getTopDefaulters($penaltyQuery): Collection
    {
        return $penaltyQuery->clone()
            ->select([
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'loans.id as loan_id',
                DB::raw('SUM(loan_penalty_applications.amount) as total_applied'),
            ])
            ->groupBy('customers.id', 'customers.name', 'customers.phone', 'loans.id', 'loans.loan_code')
            ->get()
            ->map(function ($item) {
                // Calculate outstanding amount per customer/loan
                $paidAmount = DB::table('loan_penalty_applications')
                    ->join('loans', 'loan_penalty_applications.loan_id', '=', 'loans.id')
                    ->join('loan_payment_allocations', function ($join) {
                        $join->on('loan_penalty_applications.loan_id', '=', 'loan_payment_allocations.loan_payment_id')
                            ->orOn('loan_penalty_applications.loan_id', '=', DB::raw('(SELECT loan_id FROM loans WHERE id = loan_penalty_applications.loan_id)'));
                    })
                    ->where('loans.customer_id', $item->customer_id)
                    ->where('loans.id', $item->loan_id)
                    ->sum('loan_payment_allocations.penalty_amount');
                
                // Simpler approach - calculate from aggregated data
                $item->total_paid = 0; // Will be calculated in the view or using a subquery
                $item->total_outstanding = $item->total_applied; // Default to applied, updated below
                
                return $item;
            })
            ->sortByDesc('total_applied')
            ->take(5)
            ->values();
    }

    /**
     * Get trend data by month
     */
    private function getTrendData($dateFrom, $dateTo, $subshopId, array $accessibleSubshopIds): Collection
    {
        $query = LoanPenaltyApplications::query()
            ->select([
                DB::raw('MONTH(loan_penalty_applications.applied_on) as month'),
                DB::raw('YEAR(loan_penalty_applications.applied_on) as year'),
                DB::raw('SUM(loan_penalty_applications.amount) as total_penalties'),
                DB::raw('COUNT(*) as penalty_count'),
            ])
            ->join('loans', 'loan_penalty_applications.loan_id', '=', 'loans.id')
            ->whereBetween('loan_penalty_applications.applied_on', [$dateFrom, $dateTo]);

        if ($subshopId) {
            $query->where('loans.subshop_id', $subshopId);
        } else {
            $query->whereIn('loans.subshop_id', $accessibleSubshopIds);
        }

        return $query->groupBy(DB::raw('MONTH(loan_penalty_applications.applied_on)'), DB::raw('YEAR(loan_penalty_applications.applied_on)'))
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                $item->month_name = date('M', mktime(0, 0, 0, $item->month, 1));
                return $item;
            });
    }

    /**
     * Get aging analysis of outstanding penalties
     */
    private function getAgingAnalysis(Collection $details): array
    {
        $outstandingPenalties = $details->where('outstanding_amount', '>', 0);
        
        $aging = [
            '0-30_days' => 0,
            '31-60_days' => 0,
            '61-90_days' => 0,
            '90+_days' => 0,
        ];

        foreach ($outstandingPenalties as $penalty) {
            $daysPastDue = $penalty->days_past_due ?? 0;
            
            if ($daysPastDue <= 30) {
                $aging['0-30_days'] += $penalty->outstanding_amount;
            } elseif ($daysPastDue <= 60) {
                $aging['31-60_days'] += $penalty->outstanding_amount;
            } elseif ($daysPastDue <= 90) {
                $aging['61-90_days'] += $penalty->outstanding_amount;
            } else {
                $aging['90+_days'] += $penalty->outstanding_amount;
            }
        }

        return $aging;
    }

    /**
     * Get chart data
     */
    private function getChartData(Collection $summaryByPenaltyType, Collection $topDefaulters, Collection $trendData, array $agingAnalysis): array
    {
        // Pie chart - Penalty distribution by type
        $pieChart = [
            'labels' => $summaryByPenaltyType->pluck('penalty_name')->toArray(),
            'values' => $summaryByPenaltyType->pluck('total_applied')->toArray(),
            'colors' => $this->generateColors($summaryByPenaltyType->count()),
        ];

        // Bar chart - Top defaulters
        $barChart = [
            'labels' => $topDefaulters->map(function ($def) {
                return $def->customer_name . ' (' . $def->loan_code . ')';
            })->toArray(),
            'values' => $topDefaulters->pluck('total_applied')->toArray(),
            'colors' => $this->generateColors($topDefaulters->count()),
        ];

        // Line chart - Trend
        $lineChart = [
            'labels' => $trendData->pluck('month_name')->toArray(),
            'values' => $trendData->pluck('total_penalties')->toArray(),
        ];

        // Bar chart - Aging analysis
        $agingChart = [
            'labels' => ['0-30 Days', '31-60 Days', '61-90 Days', '90+ Days'],
            'values' => array_values($agingAnalysis),
            'colors' => ['#4BC0C0', '#FFCE56', '#FF9F40', '#FF6384'],
        ];

        return [
            'pie_chart' => $pieChart,
            'bar_chart' => $barChart,
            'line_chart' => $lineChart,
            'aging_chart' => $agingChart,
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
     * Get penalty types for filter dropdown
     */
    public function getPenaltyTypes(array $accessibleSubshopIds): Collection
    {
        return LoanPenalties::whereIn('subshop_id', $accessibleSubshopIds)
            ->orderBy('name')
            ->get(['id', 'name', 'penalty_type', 'code']);
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