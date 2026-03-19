<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ParReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
    ) {
    }

    private function rowValue(mixed $row, string $key, mixed $default = ''): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? $default;
        }

        if (is_object($row)) {
            return isset($row->{$key}) ? $row->{$key} : $default;
        }

        return $default;
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->agingSheet(),
            $this->byProductSheet(),
            $this->byBranchSheet(),
            $this->byOfficerSheet(),
            $this->trendsSheet(),
            $this->highRiskSheet(),
            $this->topRiskyLoansSheet(),
            $this->concentrationSheet(),
            $this->writeoffExposureSheet(),
            $this->recoveryImpactSheet(),
            $this->segmentationSheet(),
            $this->loanListSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $s = $this->report['summary'] ?? [];
        $filters = $this->report['filters'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT FILTERS', 'VALUE' => ''],
            ['As At Date', (string) ($filters['as_at_date'] ?? '')],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['DPD Min', $filters['dpd_min'] ?? ''],
            ['DPD Max', $filters['dpd_max'] ?? ''],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Total Portfolio Outstanding', number_format($s['total_portfolio_outstanding'] ?? 0, 2)],
            ['Total Overdue Amount', number_format($s['total_overdue_amount'] ?? 0, 2)],
            ['Total At-Risk Amount', number_format($s['total_at_risk_amount'] ?? 0, 2)],
            ['PAR30 (%)', ($s['par30_pct'] ?? 0) . '%'],
            ['PAR60 (%)', ($s['par60_pct'] ?? 0) . '%'],
            ['PAR90 (%)', ($s['par90_pct'] ?? 0) . '%'],
            ['NPL Loans (DPD > 90)', number_format($s['npl_loans'] ?? 0)],
            ['NPL Outstanding', number_format($s['npl_outstanding'] ?? 0, 2)],
            ['NPL Ratio (%)', ($s['npl_ratio_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function agingSheet(): GenericArrayExport
    {
        $rows = $this->report['aging_buckets'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'PAR Aging');
        }

        $mapped = array_map(fn ($r) => [
            'Bucket' => $r['bucket'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
            'Portfolio %' => ($r['pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'PAR Aging');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->report['by_product'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product'] ?? '',
            'Total Portfolio' => number_format($r['total_portfolio'] ?? 0, 2),
            'PAR30 %' => ($r['par30_pct'] ?? 0) . '%',
            'PAR60 %' => ($r['par60_pct'] ?? 0) . '%',
            'PAR90 %' => ($r['par90_pct'] ?? 0) . '%',
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
            'Total Portfolio' => number_format($r['total_portfolio'] ?? 0, 2),
            'PAR30 %' => ($r['par30_pct'] ?? 0) . '%',
            'PAR90 %' => ($r['par90_pct'] ?? 0) . '%',
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
            'Total Portfolio' => number_format($r['total_portfolio'] ?? 0, 2),
            'PAR30 %' => ($r['par30_pct'] ?? 0) . '%',
            'PAR90 %' => ($r['par90_pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'By Officer');
    }

    private function trendsSheet(): GenericArrayExport
    {
        $t = $this->report['trends'] ?? [];
        $labels = $t['labels'] ?? [];

        if (empty($labels)) {
            return new GenericArrayExport([['No data']], 'Trends');
        }

        $rows = [];
        foreach ($labels as $i => $label) {
            $rows[] = [
                'Month' => $label,
                'PAR30 %' => ($t['par30'][$i] ?? 0) . '%',
                'PAR60 %' => ($t['par60'][$i] ?? 0) . '%',
                'PAR90 %' => ($t['par90'][$i] ?? 0) . '%',
                'At-Risk Amount' => number_format($t['at_risk_amount'][$i] ?? 0, 2),
            ];
        }

        return new GenericArrayExport($rows, 'Trends');
    }

    private function highRiskSheet(): GenericArrayExport
    {
        $hr = $this->report['high_risk_portfolio'] ?? [];

        $rows = [
            ['Segment' => 'DPD > 60', 'Loans' => $hr['over_60']['loans'] ?? 0, 'Outstanding' => number_format($hr['over_60']['outstanding'] ?? 0, 2)],
            ['Segment' => 'DPD > 90', 'Loans' => $hr['over_90']['loans'] ?? 0, 'Outstanding' => number_format($hr['over_90']['outstanding'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'High Risk');
    }

    private function topRiskyLoansSheet(): GenericArrayExport
    {
        $rows = $this->report['top_risky_loans'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Top Risky Loans');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'DPD' => $r['dpd'] ?? 0,
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Top Risky Loans');
    }

    private function concentrationSheet(): GenericArrayExport
    {
        $c = $this->report['concentration'] ?? [];

        $rows = [
            ['METRIC' => 'Risk Outstanding (PAR30+)', 'VALUE' => number_format($c['risk_outstanding'] ?? 0, 2)],
            ['METRIC' => 'Risk % of Portfolio', 'VALUE' => ($c['risk_pct_of_portfolio'] ?? 0) . '%'],
            ['', ''],
            ['TOP CUSTOMERS', ''],
        ];

        foreach (($c['top_customers'] ?? []) as $r) {
            $rows[] = [
                (string) ($r['customer'] ?? ''),
                number_format((float) ($r['outstanding'] ?? 0), 2) . ' (' . ($r['pct_of_risk'] ?? 0) . '%)',
            ];
        }

        $rows[] = ['', ''];
        $rows[] = ['TOP BRANCHES', ''];

        foreach (($c['top_branches'] ?? []) as $r) {
            $rows[] = [
                (string) ($r['branch'] ?? ''),
                number_format((float) ($r['outstanding'] ?? 0), 2) . ' (' . ($r['pct_of_risk'] ?? 0) . '%)',
            ];
        }

        $rows[] = ['', ''];
        $rows[] = ['TOP PRODUCTS', ''];

        foreach (($c['top_products'] ?? []) as $r) {
            $rows[] = [
                (string) ($r['product'] ?? ''),
                number_format((float) ($r['outstanding'] ?? 0), 2) . ' (' . ($r['pct_of_risk'] ?? 0) . '%)',
            ];
        }

        return new GenericArrayExport($rows, 'Concentration');
    }

    private function writeoffExposureSheet(): GenericArrayExport
    {
        $w = $this->report['writeoff_exposure'] ?? [];

        $rows = [
            ['Segment' => 'DPD > 90', 'Loans' => $w['dpd_over_90']['loans'] ?? 0, 'Outstanding' => number_format($w['dpd_over_90']['outstanding'] ?? 0, 2)],
            ['Segment' => 'DPD > 120', 'Loans' => $w['dpd_over_120']['loans'] ?? 0, 'Outstanding' => number_format($w['dpd_over_120']['outstanding'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Write-Off Exposure');
    }

    private function recoveryImpactSheet(): GenericArrayExport
    {
        $r = $this->report['recovery_impact'] ?? [];

        $rows = [
            ['Metric' => 'Previous As At', 'Value' => $r['previous_as_at'] ?? ''],
            ['Metric' => 'Current As At', 'Value' => $r['current_as_at'] ?? ''],
            ['Metric' => 'Previous PAR30 %', 'Value' => ($r['previous_par30_pct'] ?? 0) . '%'],
            ['Metric' => 'Current PAR30 %', 'Value' => ($r['current_par30_pct'] ?? 0) . '%'],
            ['Metric' => 'PAR30 Change (pp)', 'Value' => $r['par30_change_pct_points'] ?? 0],
            ['Metric' => 'Previous At-Risk Amount', 'Value' => number_format($r['previous_at_risk_amount'] ?? 0, 2)],
            ['Metric' => 'Current At-Risk Amount', 'Value' => number_format($r['current_at_risk_amount'] ?? 0, 2)],
            ['Metric' => 'At-Risk Change', 'Value' => number_format($r['at_risk_change_amount'] ?? 0, 2)],
            ['Metric' => 'Recovered Amount', 'Value' => number_format($r['recovered_amount'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Recovery Impact');
    }

    private function segmentationSheet(): GenericArrayExport
    {
        $rows = $this->report['segmentation'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Segmentation');
        }

        $mapped = array_map(fn ($r) => [
            'Segment' => $r['segment'] ?? '',
            'Amount' => number_format($r['amount'] ?? 0, 2),
            '%' => ($r['pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'Segmentation');
    }

    private function loanListSheet(): GenericArrayExport
    {
        $p = $this->report['loans'] ?? null;
        $items = $p && method_exists($p, 'items') ? $p->items() : [];

        if (empty($items)) {
            return new GenericArrayExport([['No data']], 'Loan List');
        }

        $mapped = array_map(function ($r) {
            $dpd = (int) $this->rowValue($r, 'dpd', 0);

            return [
                'Loan Code' => (string) $this->rowValue($r, 'loan_code', ''),
                'Customer' => (string) $this->rowValue($r, 'customer', ''),
                'Product' => (string) $this->rowValue($r, 'product', ''),
                'Branch' => (string) $this->rowValue($r, 'branch', ''),
                'Officer' => (string) $this->rowValue($r, 'officer', ''),
                'Loan Status' => (string) $this->rowValue($r, 'loan_status', ''),
                'Outstanding Balance' => number_format((float) $this->rowValue($r, 'outstanding_balance', 0), 2),
                'Overdue Amount' => number_format((float) $this->rowValue($r, 'overdue_amount', 0), 2),
                'DPD' => $dpd,
            ];
        }, $items);

        return new GenericArrayExport($mapped, 'Loan List');
    }
}
