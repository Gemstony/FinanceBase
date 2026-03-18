<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LoanOutstandingExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
    ) {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->loanLevelSheet(),
            $this->byProductSheet(),
            $this->byBranchSheet(),
            $this->byOfficerSheet(),
            $this->distributionSheet(),
            $this->topBorrowersSheet(),
            $this->statusSheet(),
            $this->vsDisbursedSheet(),
            $this->snapshotSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $summary = $this->report['summary'] ?? [];
        $filters = $this->report['filters'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT FILTERS', 'VALUE' => ''],
            ['As At Date', (string) ($filters['as_at_date'] ?? '')],
            ['Branch(es)', implode(', ', $filters['subshop_ids'] ?? ['All Accessible'])],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Total Outstanding Portfolio', number_format($summary['total_outstanding'] ?? 0, 2)],
            ['Total Principal Outstanding', number_format($summary['principal_outstanding'] ?? 0, 2)],
            ['Total Interest Outstanding', number_format($summary['interest_outstanding'] ?? 0, 2)],
            ['Total Fees Outstanding', number_format($summary['fees_outstanding'] ?? 0, 2)],
            ['Number of Active Loans', number_format($summary['active_loans'] ?? 0)],
            ['Average Outstanding per Loan', number_format($summary['avg_outstanding_per_loan'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function loanLevelSheet(): GenericArrayExport
    {
        $rows = $this->report['loans'] ?? [];

        if ($rows instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $rows = $rows->items();
        }

        if (empty($rows)) {
            return new GenericArrayExport([['No data available']], 'Loan-Level');
        }

        $mapped = array_map(fn ($r) => [
            'Loan ID' => $r->loan_id ?? '',
            'Loan Code' => $r->loan_code ?? '',
            'Customer' => $r->customer_name ?? ($r->customer_id ?? ''),
            'Loan Product' => $r->loan_product_name ?? ($r->loan_product_id ?? ''),
            'Branch' => $r->branch_name ?? ($r->subshop_id ?? ''),
            'Loan Officer' => $r->officer_name ?? ($r->officer_id ?? ''),
            'Disbursement Date' => $r->disbursement_date ?? '',
            'Principal Outstanding' => number_format($r->principal_outstanding ?? 0, 2),
            'Interest Outstanding' => number_format($r->interest_outstanding ?? 0, 2),
            'Fees Outstanding' => number_format($r->fees_outstanding ?? 0, 2),
            'Total Outstanding' => number_format($r->total_outstanding ?? 0, 2),
            'Last Payment Date' => $r->last_payment_date ?? '',
            'Loan Status' => $r->loan_status ?? '',
        ], $rows);

        return new GenericArrayExport($mapped, 'Loan-Level');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->report['by_product'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product'] ?? '',
            'Loans' => $r['loans_count'] ?? 0,
            'Principal Outstanding' => number_format($r['principal_outstanding'] ?? 0, 2),
            'Interest Outstanding' => number_format($r['interest_outstanding'] ?? 0, 2),
            'Total Outstanding' => number_format($r['total_outstanding'] ?? 0, 2),
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
            'Loans' => $r['loans_count'] ?? 0,
            'Total Outstanding' => number_format($r['total_outstanding'] ?? 0, 2),
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
            'Loans' => $r['loans_count'] ?? 0,
            'Total Outstanding' => number_format($r['total_outstanding'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Officer');
    }

    private function distributionSheet(): GenericArrayExport
    {
        $rows = $this->report['distribution'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Distribution');
        }

        $mapped = array_map(fn ($r) => [
            'Range' => $r['range'] ?? '',
            'Loans' => $r['loans_count'] ?? 0,
            'Total Outstanding' => number_format($r['total_outstanding'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Distribution');
    }

    private function topBorrowersSheet(): GenericArrayExport
    {
        $rows = $this->report['top_borrowers'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Top Borrowers');
        }

        $mapped = array_map(fn ($r) => [
            'Customer' => $r['customer'] ?? '',
            'Loans' => $r['loans_count'] ?? 0,
            'Total Outstanding' => number_format($r['total_outstanding'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Top Borrowers');
    }

    private function statusSheet(): GenericArrayExport
    {
        $rows = $this->report['status_breakdown'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Status');
        }

        $mapped = array_map(fn ($r) => [
            'Status' => $r['status'] ?? '',
            'Loans' => $r['loans_count'] ?? 0,
            'Total Outstanding' => number_format($r['total_outstanding'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Status');
    }

    private function vsDisbursedSheet(): GenericArrayExport
    {
        $rows = $this->report['vs_disbursed'] ?? [];

        $mapped = [
            ['Metric' => 'Total Disbursed Amount', 'Value' => number_format($rows['total_disbursed'] ?? 0, 2)],
            ['Metric' => 'Total Outstanding Amount', 'Value' => number_format($rows['total_outstanding'] ?? 0, 2)],
            ['Metric' => 'Total Recovered Amount', 'Value' => number_format($rows['total_recovered'] ?? 0, 2)],
            ['Metric' => 'Recovery Rate (%)', 'Value' => ($rows['recovery_rate_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($mapped, 'Vs Disbursed');
    }

    private function snapshotSheet(): GenericArrayExport
    {
        $rows = $this->report['snapshot'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Snapshot');
        }

        $mapped = array_map(fn ($r) => [
            'Date' => $r['date'] ?? '',
            'Total Outstanding' => number_format($r['total_outstanding'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Snapshot');
    }
}
