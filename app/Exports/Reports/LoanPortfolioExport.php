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
        $summary = $this->report['summary'] ?? [];

        $summaryRows = [
            ['Metric' => 'Total Portfolio Outstanding', 'Value' => $summary['total_outstanding'] ?? 0],
            ['Metric' => 'Number of Active Loans', 'Value' => $summary['active_loans'] ?? 0],
            ['Metric' => 'Number of Active Borrowers', 'Value' => $summary['active_borrowers'] ?? 0],
            ['Metric' => 'Total Loan Disbursed (Period)', 'Value' => $summary['total_disbursed_period'] ?? 0],
            ['Metric' => 'Total Repayments Collected (Period)', 'Value' => $summary['total_repayments_period'] ?? 0],
            ['Metric' => 'Average Loan Size', 'Value' => $summary['avg_loan_size'] ?? 0],
        ];

        $composition = $this->report['composition'] ?? [];
        $par = $this->report['par'] ?? [];
        $aging = $this->report['aging'] ?? [];
        $disbursement = $this->report['disbursement_analysis'] ?? [];
        $repayment = $this->report['repayment_performance'] ?? [];
        $topBorrowers = $this->report['top_borrowers'] ?? [];

        $repaymentRows = [[
            'Expected Repayments' => $repayment['expected'] ?? 0,
            'Collected Repayments' => $repayment['collected'] ?? 0,
            'Collection Efficiency %' => $repayment['efficiency_pct'] ?? 0,
        ]];

        return [
            new GenericArrayExport($summaryRows, 'Summary'),
            new GenericArrayExport($composition['by_product'] ?? [], 'By Product'),
            new GenericArrayExport($composition['by_branch'] ?? [], 'By Branch'),
            new GenericArrayExport($composition['by_officer'] ?? [], 'By Officer'),
            new GenericArrayExport($par, 'PAR Buckets'),
            new GenericArrayExport($aging, 'Aging'),
            new GenericArrayExport($disbursement, 'Disbursements'),
            new GenericArrayExport($repaymentRows, 'Repayment Performance'),
            new GenericArrayExport($topBorrowers, 'Top Borrowers'),
        ];
    }
}
