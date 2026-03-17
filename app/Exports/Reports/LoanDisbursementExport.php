<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LoanDisbursementExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
    ) {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->trendsSheet(),
            $this->byProductSheet(),
            $this->byBranchSheet(),
            $this->byOfficerSheet(),
            $this->newVsRepeatSheet(),
            $this->loanSizeDistributionSheet(),
            $this->statusAnalysisSheet(),
            $this->methodAnalysisSheet(),
            $this->disbursementVsRepaymentSheet(),
            $this->topBorrowersSheet(),
            $this->efficiencySheet(),
            $this->detailedListSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $s = $this->report['summary'] ?? [];
        $filters = $this->report['filters'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT FILTERS', 'VALUE' => ''],
            ['Report Period', ($filters['date_from'] ?? '') . ' to ' . ($filters['date_to'] ?? '')],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['Loan Product', (string) ($filters['loan_product_id'] ?? '')],
            ['Loan Officer', (string) ($filters['loan_officer_id'] ?? '')],
            ['Loan Status', (string) ($filters['loan_status'] ?? '')],
            ['Disbursement Method', (string) ($filters['disbursement_method_id'] ?? '')],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Total Loans Disbursed', number_format($s['total_loans_disbursed'] ?? 0)],
            ['Total Disbursement Amount', number_format($s['total_disbursement_amount'] ?? 0, 2)],
            ['Average Loan Size', number_format($s['average_loan_size'] ?? 0, 2)],
            ['New Borrowers', number_format($s['new_borrowers'] ?? 0)],
            ['Repeat Borrowers', number_format($s['repeat_borrowers'] ?? 0)],
            ['Disbursement Growth (%)', ($s['disbursement_growth_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function trendsSheet(): GenericArrayExport
    {
        $rows = $this->report['trends']['rows'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No trend data']], 'Trends');
        }

        $mapped = array_map(fn ($r) => [
            'Period' => $r['period'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Amount' => number_format($r['amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Trends');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->report['by_product'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Total Amount' => number_format($r['amount'] ?? 0, 2),
            'Average Loan Size' => number_format($r['avg_loan_size'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Product');
    }

    private function byBranchSheet(): GenericArrayExport
    {
        $rows = $this->report['by_branch'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Branch');
        }

        $mapped = array_map(fn ($r) => [
            'Branch' => $r['branch'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Total Amount' => number_format($r['amount'] ?? 0, 2),
            'Average Loan Size' => number_format($r['avg_loan_size'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Branch');
    }

    private function byOfficerSheet(): GenericArrayExport
    {
        $rows = $this->report['by_officer'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Officer');
        }

        $mapped = array_map(fn ($r) => [
            'Officer' => $r['officer'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Total Amount' => number_format($r['amount'] ?? 0, 2),
            'Average Loan Size' => number_format($r['avg_loan_size'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Officer');
    }

    private function newVsRepeatSheet(): GenericArrayExport
    {
        $nvr = $this->report['new_vs_repeat'] ?? [];

        $rows = [
            [
                'Category' => 'New Borrowers',
                'Customers' => $nvr['new']['count'] ?? 0,
                'Amount' => number_format($nvr['new']['amount'] ?? 0, 2),
            ],
            [
                'Category' => 'Repeat Borrowers',
                'Customers' => $nvr['repeat']['count'] ?? 0,
                'Amount' => number_format($nvr['repeat']['amount'] ?? 0, 2),
            ],
        ];

        return new GenericArrayExport($rows, 'New vs Repeat');
    }

    private function loanSizeDistributionSheet(): GenericArrayExport
    {
        $rows = $this->report['loan_size_distribution'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Loan Size');
        }

        $mapped = array_map(fn ($r) => [
            'Bucket' => $r['bucket'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Amount' => number_format($r['amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Loan Size');
    }

    private function statusAnalysisSheet(): GenericArrayExport
    {
        $s = $this->report['status_analysis'] ?? [];

        $rows = [
            [
                'Status' => 'Approved Not Disbursed',
                'Count' => $s['approved_not_disbursed']['count'] ?? 0,
                'Amount' => number_format($s['approved_not_disbursed']['amount'] ?? 0, 2),
            ],
            [
                'Status' => 'Disbursed',
                'Count' => $s['disbursed']['count'] ?? 0,
                'Amount' => number_format($s['disbursed']['amount'] ?? 0, 2),
            ],
            [
                'Status' => 'Cancelled',
                'Count' => $s['cancelled']['count'] ?? 0,
                'Amount' => number_format($s['cancelled']['amount'] ?? 0, 2),
            ],
        ];

        return new GenericArrayExport($rows, 'Status');
    }

    private function methodAnalysisSheet(): GenericArrayExport
    {
        $rows = $this->report['method_analysis'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Methods');
        }

        $mapped = array_map(fn ($r) => [
            'Method' => $r['method'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Amount' => number_format($r['amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Methods');
    }

    private function disbursementVsRepaymentSheet(): GenericArrayExport
    {
        $dvr = $this->report['disbursement_vs_repayment'] ?? [];

        $rows = [
            ['Metric' => 'Total Disbursed', 'Value' => number_format($dvr['total_disbursed'] ?? 0, 2)],
            ['Metric' => 'Total Repaid', 'Value' => number_format($dvr['total_repaid'] ?? 0, 2)],
            ['Metric' => 'Net Portfolio Growth', 'Value' => number_format($dvr['net_portfolio_growth'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Disbursed vs Repaid');
    }

    private function topBorrowersSheet(): GenericArrayExport
    {
        $rows = $this->report['top_borrowers'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Top Borrowers');
        }

        $mapped = array_map(fn ($r) => [
            'Customer' => $r['customer'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Total Disbursed' => number_format($r['amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Top Borrowers');
    }

    private function efficiencySheet(): GenericArrayExport
    {
        $e = $this->report['efficiency'] ?? [];

        $rows = [
            ['Metric' => 'Average Time to Disburse (days)', 'Value' => number_format($e['avg_time_to_disburse_days'] ?? 0, 2)],
            ['Metric' => 'Approval Conversion Rate (%)', 'Value' => ($e['approval_conversion_rate_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Efficiency');
    }

    private function detailedListSheet(): GenericArrayExport
    {
        $p = $this->report['detailed_list'] ?? null;
        $items = $p && method_exists($p, 'items') ? $p->items() : [];

        if (empty($items)) {
            return new GenericArrayExport([['No data']], 'Detailed List');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r->loan_code ?? ($r['loan_code'] ?? ''),
            'Customer' => $r->customer ?? ($r['customer'] ?? ''),
            'Product' => $r->product ?? ($r['product'] ?? ''),
            'Branch' => $r->branch ?? ($r['branch'] ?? ''),
            'Officer' => $r->officer ?? ($r['officer'] ?? ''),
            'Disbursement Date' => $r->disbursement_date ?? ($r['disbursement_date'] ?? ''),
            'Amount' => number_format((float) ($r->amount ?? ($r['amount'] ?? 0)), 2),
            'Method' => $r->disbursement_method ?? ($r['disbursement_method'] ?? ''),
            'Loan Status' => $r->loan_status ?? ($r['loan_status'] ?? ''),
        ], $items);

        return new GenericArrayExport($mapped, 'Detailed List');
    }
}
