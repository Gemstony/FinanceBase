<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class NplReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
    ) {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->agingSheet(),
            $this->nplLoansSheet(),
            $this->byProductSheet(),
            $this->byBranchSheet(),
            $this->byOfficerSheet(),
            $this->topCustomersSheet(),
            $this->trendsSheet(),
            $this->recoverySheet(),
            $this->writeoffCandidatesSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $s = $this->report['summary'] ?? [];
        $filters = $this->report['filters'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT FILTERS', 'VALUE' => ''],
            ['As-of Date', $filters['as_of'] ?? ''],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['DPD Threshold', $filters['dpd_threshold'] ?? 90],
            ['DPD Min', $filters['dpd_min'] ?? ''],
            ['DPD Max', $filters['dpd_max'] ?? ''],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Total Portfolio Outstanding', number_format($this->report['portfolio_outstanding'] ?? 0, 2)],
            ['Total NPL Loans', number_format($s['total_npl_loans'] ?? 0)],
            ['Total NPL Amount', number_format($s['total_npl_amount'] ?? 0, 2)],
            ['NPL Ratio (%)', ($s['npl_ratio_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function agingSheet(): GenericArrayExport
    {
        $rows = $this->report['aging'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Aging');
        }

        $mapped = array_map(fn ($r) => [
            'Bucket' => $r['bucket'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
            'NPL %' => ($r['pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'Aging');
    }

    private function nplLoansSheet(): GenericArrayExport
    {
        $p = $this->report['npl_loans'] ?? null;
        $items = $p && method_exists($p, 'items') ? $p->items() : [];

        if (empty($items)) {
            return new GenericArrayExport([['No data']], 'NPL Loans');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r->loan_code ?? ($r['loan_code'] ?? ''),
            'Customer' => $r->customer ?? ($r['customer'] ?? ''),
            'Product' => $r->product ?? ($r['product'] ?? ''),
            'Branch' => $r->branch ?? ($r['branch'] ?? ''),
            'Officer' => $r->officer ?? ($r['officer'] ?? ''),
            'Loan Status' => $r->loan_status ?? ($r['loan_status'] ?? ''),
            'Outstanding Balance' => number_format((float) ($r->outstanding_balance ?? ($r['outstanding_balance'] ?? 0)), 2),
            'DPD' => (int) ($r->dpd ?? ($r['dpd'] ?? 0)),
            'Last Payment Date' => $r->last_payment_date ?? ($r['last_payment_date'] ?? ''),
            'Days Since Last Payment' => (int) ($r->days_since_last_payment ?? ($r['days_since_last_payment'] ?? 0)),
        ], $items);

        return new GenericArrayExport($mapped, 'NPL Loans');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->report['by_product'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product'] ?? '',
            'NPL Loans' => $r['npl_loans'] ?? 0,
            'NPL Amount' => number_format($r['npl_amount'] ?? 0, 2),
            'Portfolio Outstanding' => number_format($r['portfolio_outstanding'] ?? 0, 2),
            'NPL Ratio %' => ($r['npl_ratio_pct'] ?? 0) . '%',
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
            'NPL Loans' => $r['npl_loans'] ?? 0,
            'NPL Amount' => number_format($r['npl_amount'] ?? 0, 2),
            'Portfolio Outstanding' => number_format($r['portfolio_outstanding'] ?? 0, 2),
            'NPL Ratio %' => ($r['npl_ratio_pct'] ?? 0) . '%',
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
            'NPL Loans' => $r['npl_loans'] ?? 0,
            'NPL Amount' => number_format($r['npl_amount'] ?? 0, 2),
            'Portfolio Outstanding' => number_format($r['portfolio_outstanding'] ?? 0, 2),
            'NPL Ratio %' => ($r['npl_ratio_pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'By Officer');
    }

    private function topCustomersSheet(): GenericArrayExport
    {
        $rows = $this->report['top_customers'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Top Customers');
        }

        $mapped = array_map(fn ($r) => [
            'Customer' => $r['customer'] ?? '',
            'NPL Loans' => $r['npl_loans'] ?? 0,
            'NPL Amount' => number_format($r['npl_amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Top Customers');
    }

    private function trendsSheet(): GenericArrayExport
    {
        $c = $this->report['trends']['chart'] ?? [];
        $labels = $c['labels'] ?? [];

        if (empty($labels)) {
            return new GenericArrayExport([['No data']], 'Trends');
        }

        $rows = [];
        foreach ($labels as $i => $label) {
            $rows[] = [
                'Month' => $label,
                'NPL Amount' => number_format($c['npl_amount'][$i] ?? 0, 2),
                'Portfolio Outstanding' => number_format($c['portfolio_outstanding'][$i] ?? 0, 2),
                'NPL Ratio %' => ($c['npl_ratio'][$i] ?? 0) . '%',
            ];
        }

        return new GenericArrayExport($rows, 'Trends');
    }

    private function recoverySheet(): GenericArrayExport
    {
        $r = $this->report['recovery'] ?? [];

        $rows = [
            ['METRIC' => 'Recovered Amount (last 30 days)', 'VALUE' => number_format($r['recovered_amount'] ?? 0, 2)],
            ['METRIC' => 'Recovery Rate', 'VALUE' => ($r['recovery_rate_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Recovery');
    }

    private function writeoffCandidatesSheet(): GenericArrayExport
    {
        $rows = $this->report['writeoff_candidates'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Write-Off');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'DPD' => $r['dpd'] ?? 0,
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
            'Last Payment Date' => $r['last_payment_date'] ?? '',
            'Recommendation' => $r['recommendation'] ?? '',
        ], $rows);

        return new GenericArrayExport($mapped, 'Write-Off');
    }
}
