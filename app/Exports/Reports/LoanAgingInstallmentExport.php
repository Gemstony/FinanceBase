<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LoanAgingInstallmentExport implements WithMultipleSheets
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
            $this->installmentListSheet(),
            $this->missedInstallmentsSheet(),
            $this->byProductSheet(),
            $this->byBranchSheet(),
            $this->byOfficerSheet(),
            $this->partialPaymentSheet(),
            $this->highRiskSheet(),
            $this->dpdDistributionSheet(),
            $this->recoverySegmentationSheet(),
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
            ['Customer', (string) ($filters['customer'] ?? '')],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Total Outstanding Installments', number_format($s['total_outstanding_installments'] ?? 0)],
            ['Total Outstanding Amount', number_format($s['total_outstanding_amount'] ?? 0, 2)],
            ['Total Overdue Installments', number_format($s['total_overdue_installments'] ?? 0)],
            ['Total Overdue Amount', number_format($s['total_overdue_amount'] ?? 0, 2)],
            ['Average Installment DPD', number_format($s['avg_dpd'] ?? 0, 2)],
            ['Maximum Installment DPD', number_format($s['max_dpd'] ?? 0)],
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
            'Installments' => $r['installments'] ?? 0,
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
            'Outstanding %' => ($r['pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'Aging Buckets');
    }

    private function installmentListSheet(): GenericArrayExport
    {
        $p = $this->report['installments'] ?? null;
        $items = $p && method_exists($p, 'items') ? $p->items() : [];

        if (empty($items)) {
            return new GenericArrayExport([['No data']], 'Installments');
        }

        $mapped = array_map(function ($r) {
            $dpd = (int) $this->rowValue($r, 'dpd', 0);
            $bucket = (string) $this->rowValue($r, 'aging_bucket', $this->bucketLabel($dpd));

            return [
                'Loan Code' => (string) $this->rowValue($r, 'loan_code', ''),
                'Customer' => (string) $this->rowValue($r, 'customer', ''),
                'Product' => (string) $this->rowValue($r, 'product', ''),
                'Branch' => (string) $this->rowValue($r, 'branch', ''),
                'Officer' => (string) $this->rowValue($r, 'officer', ''),
                'Installment No' => (int) $this->rowValue($r, 'installment_number', 0),
                'Due Date' => (string) $this->rowValue($r, 'due_date', ''),
                'Installment Amount' => number_format((float) $this->rowValue($r, 'installment_amount', 0), 2),
                'Paid Amount' => number_format((float) $this->rowValue($r, 'paid_amount', 0), 2),
                'Outstanding Balance' => number_format((float) $this->rowValue($r, 'outstanding_balance', 0), 2),
                'DPD' => $dpd,
                'Aging Bucket' => $bucket,
                'Installment Status' => (string) $this->rowValue($r, 'installment_status', ''),
                'Allocation Issue' => (int) $this->rowValue($r, 'allocation_issue', 0) ? 'Yes' : 'No',
            ];
        }, $items);

        return new GenericArrayExport($mapped, 'Installments');
    }

    private function missedInstallmentsSheet(): GenericArrayExport
    {
        $rows = $this->report['missed_installments'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Missed By Loan');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Missed Installments' => $r['missed_installments'] ?? 0,
            'Total Overdue Amount' => number_format($r['overdue_amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Missed By Loan');
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

    private function partialPaymentSheet(): GenericArrayExport
    {
        $p = $this->report['partial_payment'] ?? [];

        $rows = [
            ['Metric' => 'Partial Installments', 'Value' => number_format($p['partial_installments'] ?? 0)],
            ['Metric' => 'Total Partial Paid Amount', 'Value' => number_format($p['total_partial_paid_amount'] ?? 0, 2)],
            ['Metric' => 'Total Partial Outstanding Amount', 'Value' => number_format($p['total_partial_outstanding_amount'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Partial Payments');
    }

    private function highRiskSheet(): GenericArrayExport
    {
        $rows = $this->report['high_risk_installments'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'High Risk');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Installment No' => $r['installment_number'] ?? 0,
            'DPD' => $r['dpd'] ?? 0,
            'Outstanding Balance' => number_format($r['outstanding_balance'] ?? 0, 2),
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
                $rows[] = [$r['bucket'] ?? '', $r['installments'] ?? 0];
            }
        }

        return new GenericArrayExport($rows, 'DPD Distribution');
    }

    private function recoverySegmentationSheet(): GenericArrayExport
    {
        $rows = $this->report['recovery_segmentation'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Recovery Segmentation');
        }

        $mapped = array_map(fn ($r) => [
            'Risk Segment' => $r['risk'] ?? '',
            'Installments' => $r['installments'] ?? 0,
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Recovery Segmentation');
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
