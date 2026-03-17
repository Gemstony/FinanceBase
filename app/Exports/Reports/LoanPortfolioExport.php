<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LoanPortfolioExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
    ) {
    }

    public function sheets(): array
    {
        $sheets = [];

        // 1. Summary Sheet
        $sheets[] = $this->buildSummarySheet();

        // 2. Portfolio by Product
        $sheets[] = $this->buildCompositionByProductSheet();

        // 3. Portfolio by Branch
        $sheets[] = $this->buildCompositionByBranchSheet();

        // 4. Portfolio by Officer
        $sheets[] = $this->buildCompositionByOfficerSheet();

        // 5. PAR Buckets (Portfolio at Risk)
        $sheets[] = $this->buildPARSheet();

        // 6. Portfolio Aging
        $sheets[] = $this->buildAgingSheet();

        // 7. Disbursement Analysis
        $sheets[] = $this->buildDisbursementSheet();

        // 8. Repayment Performance
        $sheets[] = $this->buildRepaymentSheet();

        // 9. Top Borrowers
        $sheets[] = $this->buildTopBorrowersSheet();

        // 10. Trends (Time Series Data)
        $sheets[] = $this->buildTrendsSheet();

        return $sheets;
    }

    private function buildSummarySheet(): GenericArrayExport
    {
        $summary = $this->report['summary'] ?? [];
        $filters = $this->report['filters'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT FILTERS', 'VALUE' => ''],
            ['Report Period', ($filters['date_from'] ?? '') . ' to ' . ($filters['date_to'] ?? '')],
            ['Branch(es)', implode(', ', $filters['subshop_ids'] ?? ['All Accessible'])],
            ['', ''],
            ['SECTION' => 'PORTFOLIO SUMMARY', 'VALUE' => ''],
            ['Total Portfolio Outstanding', number_format($summary['total_outstanding'] ?? 0, 2)],
            ['Number of Active Loans', number_format($summary['active_loans'] ?? 0)],
            ['Number of Active Borrowers', number_format($summary['active_borrowers'] ?? 0)],
            ['Total Disbursed (Period)', number_format($summary['total_disbursed_period'] ?? 0, 2)],
            ['Total Repayments Collected (Period)', number_format($summary['total_repayments_period'] ?? 0, 2)],
            ['Average Loan Size', number_format($summary['avg_loan_size'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function buildCompositionByProductSheet(): GenericArrayExport
    {
        $composition = $this->report['composition']['by_product'] ?? [];
        
        if (empty($composition)) {
            return new GenericArrayExport([['No data available']], 'By Product');
        }

        $rows = array_map(fn ($r) => [
            'Product ID' => $r['product_id'] ?? '',
            'Product Name' => $r['product_name'] ?? '',
            'Loans Count' => $r['loans_count'] ?? 0,
            'Outstanding Amount' => number_format($r['outstanding'] ?? 0, 2),
            'Percentage %' => ($r['pct'] ?? 0) . '%',
        ], $composition);

        // Add total row
        $totalOutstanding = array_sum(array_column($composition, 'outstanding'));
        $totalLoans = array_sum(array_column($composition, 'loans_count'));
        $rows[] = [
            'Product ID' => '',
            'Product Name' => 'TOTAL',
            'Loans Count' => $totalLoans,
            'Outstanding Amount' => number_format($totalOutstanding, 2),
            'Percentage %' => '100%',
        ];

        return new GenericArrayExport($rows, 'By Product');
    }

    private function buildCompositionByBranchSheet(): GenericArrayExport
    {
        $composition = $this->report['composition']['by_branch'] ?? [];
        
        if (empty($composition)) {
            return new GenericArrayExport([['No data available']], 'By Branch');
        }

        $rows = array_map(fn ($r) => [
            'Subshop ID' => $r['subshop_id'] ?? '',
            'Branch Name' => $r['branch'] ?? '',
            'Active Loans' => $r['active_loans'] ?? 0,
            'Outstanding Amount' => number_format($r['outstanding'] ?? 0, 2),
            'PAR 30' => number_format($r['par30'] ?? 0, 2),
        ], $composition);

        // Add total row
        $totalOutstanding = array_sum(array_column($composition, 'outstanding'));
        $totalLoans = array_sum(array_column($composition, 'active_loans'));
        $totalPAR30 = array_sum(array_column($composition, 'par30'));
        $rows[] = [
            'Subshop ID' => '',
            'Branch Name' => 'TOTAL',
            'Active Loans' => $totalLoans,
            'Outstanding Amount' => number_format($totalOutstanding, 2),
            'PAR 30' => number_format($totalPAR30, 2),
        ];

        return new GenericArrayExport($rows, 'By Branch');
    }

    private function buildCompositionByOfficerSheet(): GenericArrayExport
    {
        $composition = $this->report['composition']['by_officer'] ?? [];
        
        if (empty($composition)) {
            return new GenericArrayExport([['No data available']], 'By Officer');
        }

        $rows = array_map(fn ($r) => [
            'Officer ID' => $r['officer_id'] ?? '',
            'Officer Name' => $r['officer'] ?? '',
            'Loans Managed' => $r['loans_managed'] ?? 0,
            'Outstanding Amount' => number_format($r['outstanding'] ?? 0, 2),
            'Repayments Collected' => number_format($r['repayments_collected'] ?? 0, 2),
        ], $composition);

        // Add total row
        $totalOutstanding = array_sum(array_column($composition, 'outstanding'));
        $totalLoans = array_sum(array_column($composition, 'loans_managed'));
        $totalRepayments = array_sum(array_column($composition, 'repayments_collected'));
        $rows[] = [
            'Officer ID' => '',
            'Officer Name' => 'TOTAL',
            'Loans Managed' => $totalLoans,
            'Outstanding Amount' => number_format($totalOutstanding, 2),
            'Repayments Collected' => number_format($totalRepayments, 2),
        ];

        return new GenericArrayExport($rows, 'By Officer');
    }

    private function buildPARSheet(): GenericArrayExport
    {
        $par = $this->report['par'] ?? [];
        $summary = $this->report['summary'] ?? [];
        $portfolioOutstanding = $summary['total_outstanding'] ?? 0;

        if (empty($par)) {
            return new GenericArrayExport([['No data available']], 'PAR Buckets');
        }

        $rows = array_map(fn ($r) => [
            'Risk Bucket' => $r['bucket'] ?? '',
            'Outstanding Amount' => number_format($r['outstanding'] ?? 0, 2),
            'Percentage of Portfolio' => ($r['pct'] ?? 0) . '%',
            'Risk Level' => match($r['bucket'] ?? '') {
                'Current' => 'LOW',
                'PAR 1–30' => 'MEDIUM',
                'PAR 31–60' => 'HIGH',
                'PAR 61–90' => 'CRITICAL',
                'PAR > 90' => 'SEVERE',
                default => '',
            },
        ], $par);

        // Calculate PAR30+ (non-current)
        $parAtRisk = array_filter($par, fn($r) => ($r['bucket'] ?? '') !== 'Current');
        $parTotal = array_sum(array_column($parAtRisk, 'outstanding'));
        $par30PlusPct = $portfolioOutstanding > 0 ? round(($parTotal / $portfolioOutstanding) * 100, 2) : 0;

        $rows[] = ['', '', '', ''];
        $rows[] = [
            'Risk Bucket' => 'PAR 30+ (Total at Risk)',
            'Outstanding Amount' => number_format($parTotal, 2),
            'Percentage of Portfolio' => $par30PlusPct . '%',
            'Risk Level' => 'AGGREGATE',
        ];

        return new GenericArrayExport($rows, 'PAR Buckets');
    }

    private function buildAgingSheet(): GenericArrayExport
    {
        $aging = $this->report['aging'] ?? [];

        if (empty($aging)) {
            return new GenericArrayExport([['No data available']], 'Aging');
        }

        $rows = array_map(fn ($r) => [
            'Aging Bucket' => $r['bucket'] ?? '',
            'Outstanding Amount' => number_format($r['outstanding'] ?? 0, 2),
            'Days Range' => match($r['bucket'] ?? '') {
                'Current' => '0 days',
                '1–30 days' => '1-30 days',
                '31–60 days' => '31-60 days',
                '61–90 days' => '61-90 days',
                '90+ days' => '90+ days',
                default => '',
            },
        ], $aging);

        // Add total
        $totalOutstanding = array_sum(array_column($aging, 'outstanding'));
        $rows[] = [
            'Aging Bucket' => 'TOTAL',
            'Outstanding Amount' => number_format($totalOutstanding, 2),
            'Days Range' => '',
        ];

        return new GenericArrayExport($rows, 'Aging');
    }

    private function buildDisbursementSheet(): GenericArrayExport
    {
        $disbursement = $this->report['disbursement_analysis'] ?? [];

        if (empty($disbursement)) {
            return new GenericArrayExport([['No disbursement data for the selected period']], 'Disbursements');
        }

        $rows = array_map(fn ($r) => [
            'Month' => $r['month'] ?? '',
            'Loans Disbursed' => $r['loans_disbursed'] ?? 0,
            'Total Amount' => number_format($r['amount'] ?? 0, 2),
            'Average Loan Size' => number_format($r['avg_amount'] ?? 0, 2),
        ], $disbursement);

        // Add total row
        $totalLoans = array_sum(array_column($disbursement, 'loans_disbursed'));
        $totalAmount = array_sum(array_column($disbursement, 'amount'));
        $avgLoan = $totalLoans > 0 ? round($totalAmount / $totalLoans, 2) : 0;
        $rows[] = [
            'Month' => 'TOTAL',
            'Loans Disbursed' => $totalLoans,
            'Total Amount' => number_format($totalAmount, 2),
            'Average Loan Size' => number_format($avgLoan, 2),
        ];

        return new GenericArrayExport($rows, 'Disbursements');
    }

    private function buildRepaymentSheet(): GenericArrayExport
    {
        $repayment = $this->report['repayment_performance'] ?? [];

        $rows = [
            ['METRIC' => 'Expected Repayments', 'VALUE' => number_format($repayment['expected'] ?? 0, 2)],
            ['METRIC' => 'Collected Repayments', 'VALUE' => number_format($repayment['collected'] ?? 0, 2)],
            ['METRIC' => 'Collection Efficiency', 'VALUE' => ($repayment['efficiency_pct'] ?? 0) . '%'],
            ['METRIC' => 'Collection Gap', 'VALUE' => number_format(($repayment['expected'] ?? 0) - ($repayment['collected'] ?? 0), 2)],
        ];

        // Add performance rating
        $efficiency = $repayment['efficiency_pct'] ?? 0;
        $rating = match(true) {
            $efficiency >= 95 => 'EXCELLENT',
            $efficiency >= 85 => 'GOOD',
            $efficiency >= 70 => 'FAIR',
            $efficiency >= 50 => 'POOR',
            default => 'CRITICAL',
        };
        $rows[] = ['METRIC' => 'Performance Rating', 'VALUE' => $rating];

        return new GenericArrayExport($rows, 'Repayment Performance');
    }

    private function buildTopBorrowersSheet(): GenericArrayExport
    {
        $borrowers = $this->report['top_borrowers'] ?? [];

        if (empty($borrowers)) {
            return new GenericArrayExport([['No data available']], 'Top Borrowers');
        }

        $rows = array_map(fn ($r, $idx) => [
            'Rank' => $idx + 1,
            'Customer ID' => $r['customer_id'] ?? '',
            'Customer Name' => $r['customer'] ?? '',
            'Loan Count' => $r['loan_count'] ?? 0,
            'Outstanding Amount' => number_format($r['outstanding'] ?? 0, 2),
        ], $borrowers, array_keys($borrowers));

        // Add total
        $totalOutstanding = array_sum(array_column($borrowers, 'outstanding'));
        $totalLoans = array_sum(array_column($borrowers, 'loan_count'));
        $rows[] = [
            'Rank' => '',
            'Customer ID' => '',
            'Customer Name' => 'TOTAL TOP 10',
            'Loan Count' => $totalLoans,
            'Outstanding Amount' => number_format($totalOutstanding, 2),
        ];

        return new GenericArrayExport($rows, 'Top Borrowers');
    }

    private function buildTrendsSheet(): GenericArrayExport
    {
        $trends = $this->report['trends'] ?? [];

        if (empty($trends['labels'] ?? [])) {
            return new GenericArrayExport([['No trend data available for the selected period']], 'Trends');
        }

        $labels = $trends['labels'];
        $portfolio = $trends['portfolio_outstanding'] ?? [];
        $disbursements = $trends['disbursements'] ?? [];
        $repayments = $trends['repayments'] ?? [];
        $par30 = $trends['par30'] ?? [];

        $rows = [];
        $rows[] = [
            'Month' => 'MONTH',
            'Portfolio Outstanding' => 'PORTFOLIO OUTSTANDING',
            'Disbursements' => 'DISBURSEMENTS',
            'Repayments' => 'REPAYMENTS',
            'PAR 30' => 'PAR 30',
            'Net Flow' => 'NET FLOW',
        ];

        foreach ($labels as $i => $month) {
            $disb = $disbursements[$i] ?? 0;
            $rep = $repayments[$i] ?? 0;
            $rows[] = [
                'Month' => $month,
                'Portfolio Outstanding' => number_format($portfolio[$i] ?? 0, 2),
                'Disbursements' => number_format($disb, 2),
                'Repayments' => number_format($rep, 2),
                'PAR 30' => number_format($par30[$i] ?? 0, 2),
                'Net Flow' => number_format($disb - $rep, 2),
            ];
        }

        return new GenericArrayExport($rows, 'Trends');
    }
}
