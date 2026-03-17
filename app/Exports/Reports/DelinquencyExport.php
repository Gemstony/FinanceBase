<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DelinquencyExport implements WithMultipleSheets
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
            $this->delinquentLoansSheet(),
            $this->byOfficerSheet(),
            $this->byBranchSheet(),
            $this->byProductSheet(),
            $this->highRiskSheet(),
            $this->recoverySheet(),
            $this->trendsSheet(),
            $this->dpdAnalysisSheet(),
            $this->missedInstallmentsSheet(),
            $this->writeoffCandidatesSheet(),
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
            ['DPD Min', $filters['dpd_min'] ?? ''],
            ['DPD Max', $filters['dpd_max'] ?? ''],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Portfolio Outstanding', number_format($this->report['portfolio_outstanding'] ?? 0, 2)],
            ['Total Overdue Loans', number_format($s['total_overdue_loans'] ?? 0)],
            ['Total Overdue Amount', number_format($s['total_overdue_amount'] ?? 0, 2)],
            ['PAR30 (%)', ($s['par30_pct'] ?? 0) . '%'],
            ['PAR60 (%)', ($s['par60_pct'] ?? 0) . '%'],
            ['PAR90 (%)', ($s['par90_pct'] ?? 0) . '%'],
            ['NPL Loans', number_format($s['npl_loans'] ?? 0)],
            ['Average DPD', number_format($s['avg_dpd'] ?? 0, 2)],
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
            'Portfolio %' => ($r['pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'Aging');
    }

    private function delinquentLoansSheet(): GenericArrayExport
    {
        $p = $this->report['delinquent_loans'] ?? null;
        $items = $p && method_exists($p, 'items') ? $p->items() : [];

        if (empty($items)) {
            return new GenericArrayExport([['No data']], 'Delinquent Loans');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r->loan_code ?? ($r['loan_code'] ?? ''),
            'Customer' => $r->customer ?? ($r['customer'] ?? ''),
            'Product' => $r->product ?? ($r['product'] ?? ''),
            'Branch' => $r->branch ?? ($r['branch'] ?? ''),
            'Officer' => $r->officer ?? ($r['officer'] ?? ''),
            'Loan Status' => $r->loan_status ?? ($r['loan_status'] ?? ''),
            'Overdue Amount' => number_format((float) ($r->overdue_amount ?? ($r['overdue_amount'] ?? 0)), 2),
            'DPD' => (int) ($r->dpd ?? ($r['dpd'] ?? 0)),
            'Last Payment Date' => $r->last_payment_date ?? ($r['last_payment_date'] ?? ''),
        ], $items);

        return new GenericArrayExport($mapped, 'Delinquent Loans');
    }

    private function byOfficerSheet(): GenericArrayExport
    {
        $rows = $this->report['by_officer'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Officer');
        }

        $mapped = array_map(fn ($r) => [
            'Officer' => $r['officer'] ?? '',
            'Total Loans' => $r['total_loans'] ?? 0,
            'Overdue Loans' => $r['overdue_loans'] ?? 0,
            'Overdue Amount' => number_format($r['overdue_amount'] ?? 0, 2),
            'PAR30 %' => ($r['par30_pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'By Officer');
    }

    private function byBranchSheet(): GenericArrayExport
    {
        $rows = $this->report['by_branch'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Branch');
        }

        $mapped = array_map(fn ($r) => [
            'Branch' => $r['branch'] ?? '',
            'Total Loans' => $r['total_loans'] ?? 0,
            'Overdue Loans' => $r['overdue_loans'] ?? 0,
            'Overdue Amount' => number_format($r['overdue_amount'] ?? 0, 2),
            'PAR30 %' => ($r['par30_pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'By Branch');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->report['by_product'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product'] ?? '',
            'Total Loans' => $r['total_loans'] ?? 0,
            'Overdue Loans' => $r['overdue_loans'] ?? 0,
            'Overdue Amount' => number_format($r['overdue_amount'] ?? 0, 2),
            'PAR30 %' => ($r['par30_pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'By Product');
    }

    private function highRiskSheet(): GenericArrayExport
    {
        $rows = $this->report['high_risk'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'High Risk');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Product' => $r['product'] ?? '',
            'DPD' => $r['dpd'] ?? 0,
            'Overdue Amount' => number_format($r['overdue_amount'] ?? 0, 2),
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'High Risk');
    }

    private function recoverySheet(): GenericArrayExport
    {
        $r = $this->report['recovery'] ?? [];

        $rows = [
            ['METRIC' => 'Recovered Amount', 'VALUE' => number_format($r['recovered_amount'] ?? 0, 2)],
            ['METRIC' => 'Recovery Rate', 'VALUE' => ($r['recovery_rate_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Recovery');
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
                'PAR30 %' => ($c['par30'][$i] ?? 0) . '%',
                'PAR60 %' => ($c['par60'][$i] ?? 0) . '%',
                'PAR90 %' => ($c['par90'][$i] ?? 0) . '%',
                'Overdue Amount' => number_format($c['overdue_amount'][$i] ?? 0, 2),
                'Delinquent Loans' => $c['delinquent_loans'][$i] ?? 0,
            ];
        }

        return new GenericArrayExport($rows, 'Trends');
    }

    private function dpdAnalysisSheet(): GenericArrayExport
    {
        $d = $this->report['dpd_analysis'] ?? [];
        $dist = $d['distribution'] ?? [];

        $rows = [
            ['METRIC' => 'Average DPD', 'VALUE' => number_format($d['avg_dpd'] ?? 0, 2)],
            ['METRIC' => 'Max DPD', 'VALUE' => number_format($d['max_dpd'] ?? 0)],
            ['', ''],
            ['BUCKET' => 'DPD Distribution', 'VALUE' => ''],
        ];

        if (!empty($dist)) {
            foreach ($dist as $r) {
                $rows[] = [$r['bucket'] ?? '', $r['loans'] ?? 0];
            }
        }

        return new GenericArrayExport($rows, 'DPD Analysis');
    }

    private function missedInstallmentsSheet(): GenericArrayExport
    {
        $rows = $this->report['missed_installments'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Missed Installments');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Missed Installments' => $r['missed_installments'] ?? 0,
        ], $rows);

        return new GenericArrayExport($mapped, 'Missed Installments');
    }

    private function writeoffCandidatesSheet(): GenericArrayExport
    {
        $rows = $this->report['writeoff_candidates'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Write-Off Candidates');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'DPD' => $r['dpd'] ?? 0,
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
            'Last Payment Date' => $r['last_payment_date'] ?? '',
            'Recommendation' => $r['recommendation'] ?? '',
        ], $rows);

        return new GenericArrayExport($mapped, 'Write-Off Candidates');
    }
}
