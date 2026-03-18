<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LoanAgingExport implements WithMultipleSheets
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
            $this->agingBucketsSheet(),
            $this->loanLevelSheet(),
            $this->byProductSheet(),
            $this->byBranchSheet(),
            $this->byOfficerSheet(),
            $this->highRiskSheet(),
            $this->dpdDistributionSheet(),
            $this->trendsSheet(),
            $this->writeoffCandidatesSheet(),
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
            ['Total Outstanding Portfolio', number_format($s['total_outstanding'] ?? 0, 2)],
            ['Total Overdue Amount', number_format($s['total_overdue_amount'] ?? 0, 2)],
            ['Performing Loans (DPD = 0)', number_format($s['performing_loans'] ?? 0)],
            ['Non-Performing Loans (DPD > 90)', number_format($s['non_performing_loans'] ?? 0)],
            ['Average DPD', number_format($s['avg_dpd'] ?? 0, 2)],
            ['Maximum DPD', number_format($s['max_dpd'] ?? 0)],
            ['PAR30 (%)', ($s['par30_pct'] ?? 0) . '%'],
            ['PAR60 (%)', ($s['par60_pct'] ?? 0) . '%'],
            ['PAR90 (%)', ($s['par90_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function agingBucketsSheet(): GenericArrayExport
    {
        $rows = $this->report['aging_buckets'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Aging Buckets');
        }

        $mapped = array_map(fn ($r) => [
            'Bucket' => $r['bucket'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
            'Portfolio %' => ($r['pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'Aging Buckets');
    }

    private function loanLevelSheet(): GenericArrayExport
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
                'Aging Bucket' => $this->bucketLabel($dpd),
            ];
        }, $items);

        return new GenericArrayExport($mapped, 'Loan List');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->report['by_product'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product'] ?? '',
            'Current' => number_format($r['current'] ?? 0, 2),
            '1-30' => number_format($r['d1_30'] ?? 0, 2),
            '31-60' => number_format($r['d31_60'] ?? 0, 2),
            '61-90' => number_format($r['d61_90'] ?? 0, 2),
            '90+' => number_format($r['d90p'] ?? 0, 2),
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
            'Current' => number_format($r['current'] ?? 0, 2),
            '1-30' => number_format($r['d1_30'] ?? 0, 2),
            '31-60' => number_format($r['d31_60'] ?? 0, 2),
            '61-90' => number_format($r['d61_90'] ?? 0, 2),
            '90+' => number_format($r['d90p'] ?? 0, 2),
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
            'Current' => number_format($r['current'] ?? 0, 2),
            '1-30' => number_format($r['d1_30'] ?? 0, 2),
            '31-60' => number_format($r['d31_60'] ?? 0, 2),
            '61-90' => number_format($r['d61_90'] ?? 0, 2),
            '90+' => number_format($r['d90p'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Officer');
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
            'DPD' => $r['dpd'] ?? 0,
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
            'Risk Level' => $r['risk_level'] ?? '',
        ], $rows);

        return new GenericArrayExport($mapped, 'High Risk');
    }

    private function dpdDistributionSheet(): GenericArrayExport
    {
        $d = $this->report['dpd_distribution'] ?? [];
        $dist = $d['distribution'] ?? [];

        $rows = [
            ['METRIC' => 'Average DPD', 'VALUE' => number_format($d['avg_dpd'] ?? 0, 2)],
            ['METRIC' => 'Maximum DPD', 'VALUE' => number_format($d['max_dpd'] ?? 0)],
            ['', ''],
            ['BUCKET' => 'DPD Distribution', 'VALUE' => ''],
        ];

        if (!empty($dist)) {
            foreach ($dist as $r) {
                $rows[] = [$r['bucket'] ?? '', $r['loans'] ?? 0];
            }
        }

        return new GenericArrayExport($rows, 'DPD Distribution');
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
                'PAR90 %' => ($t['par90'][$i] ?? 0) . '%',
                'Overdue Amount' => number_format($t['overdue_amount'][$i] ?? 0, 2),
            ];
        }

        return new GenericArrayExport($rows, 'Trends');
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

    private function bucketLabel(int $dpd): string
    {
        if ($dpd <= 0) {
            return 'Current';
        }
        if ($dpd <= 30) {
            return '1-30';
        }
        if ($dpd <= 60) {
            return '31-60';
        }
        if ($dpd <= 90) {
            return '61-90';
        }
        return '90+';
    }
}
