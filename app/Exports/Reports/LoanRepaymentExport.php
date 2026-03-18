<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LoanRepaymentExport implements WithMultipleSheets
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
            $this->paymentMethodsSheet(),
            $this->onTimeVsLateSheet(),
            $this->agingSheet(),
            $this->scheduledVsActualSheet(),
            $this->partialVsFullSheet(),
            $this->recoverySheet(),
            $this->topCustomersSheet(),
            $this->officerPerformanceSheet(),
            $this->loanLevelSheet(),
            $this->installmentLevelSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $summary = $this->report['summary'] ?? [];
        $filters = $this->report['filters'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT FILTERS', 'VALUE' => ''],
            ['Report Period', ($filters['date_from'] ?? '') . ' to ' . ($filters['date_to'] ?? '')],
            ['Branch(es)', implode(', ', $filters['subshop_ids'] ?? ['All Accessible'])],
            ['Loan Product', (string) ($filters['loan_product_id'] ?? '')],
            ['Loan Officer', (string) ($filters['loan_officer_id'] ?? '')],
            ['Payment Method', (string) ($filters['payment_method'] ?? '')],
            ['Loan Status', (string) ($filters['loan_status'] ?? '')],
            ['Customer', (string) ($filters['customer_id'] ?? '')],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Total Repayments Collected', number_format($summary['total_repayments_collected'] ?? 0, 2)],
            ['Repayment Transactions', number_format($summary['repayment_transactions'] ?? 0)],
            ['Average Payment Amount', number_format($summary['average_payment_amount'] ?? 0, 2)],
            ['On-Time Repayment Rate', number_format($summary['on_time_repayment_rate_pct'] ?? 0, 2) . '%'],
            ['Late Payment Rate', number_format($summary['late_payment_rate_pct'] ?? 0, 2) . '%'],
            ['Collection Efficiency', number_format($summary['collection_efficiency_pct'] ?? 0, 2) . '%'],
            ['Recovery Rate', number_format($summary['recovery_rate_pct'] ?? 0, 2) . '%'],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function trendsSheet(): GenericArrayExport
    {
        $trends = $this->report['trends'] ?? [];

        $datasets = [
            'Auto' => $trends['auto']['rows'] ?? [],
            'Daily' => $trends['daily']['rows'] ?? [],
            'Weekly' => $trends['weekly']['rows'] ?? [],
            'Monthly' => $trends['monthly']['rows'] ?? [],
        ];

        $out = [];
        foreach ($datasets as $label => $rows) {
            $out[] = ['SECTION' => $label . ' TRENDS', 'Period' => '', 'Payments' => '', 'Amount' => ''];
            $out[] = ['SECTION' => '', 'Period' => 'Period', 'Payments' => 'Payments', 'Amount' => 'Amount'];

            foreach ($rows as $r) {
                $out[] = [
                    'SECTION' => '',
                    'Period' => $r['period'] ?? '',
                    'Payments' => $r['payments'] ?? 0,
                    'Amount' => number_format($r['amount'] ?? 0, 2),
                ];
            }

            if (empty($rows)) {
                $out[] = ['SECTION' => '', 'Period' => 'No data', 'Payments' => '', 'Amount' => ''];
            }

            $out[] = ['SECTION' => '', 'Period' => '', 'Payments' => '', 'Amount' => ''];
        }

        return new GenericArrayExport($out ?: [['No trend data']], 'Trends');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['by_product'] ?? []);
        if (count($rows) === 0) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product'] ?? '',
            'Payments' => $r['payments'] ?? 0,
            'Amount' => number_format($r['amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Product');
    }

    private function byBranchSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['by_branch'] ?? []);
        if (count($rows) === 0) {
            return new GenericArrayExport([['No data']], 'By Branch');
        }

        $mapped = array_map(fn ($r) => [
            'Branch' => $r['branch'] ?? '',
            'Payments' => $r['payments'] ?? 0,
            'Amount' => number_format($r['amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Branch');
    }

    private function byOfficerSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['by_officer'] ?? []);
        if (count($rows) === 0) {
            return new GenericArrayExport([['No data']], 'By Officer');
        }

        $mapped = array_map(fn ($r) => [
            'Officer' => $r['officer'] ?? '',
            'Payments' => $r['payments'] ?? 0,
            'Amount' => number_format($r['amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Officer');
    }

    private function paymentMethodsSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['payment_methods'] ?? []);
        if (count($rows) === 0) {
            return new GenericArrayExport([['No data']], 'Payment Methods');
        }

        $mapped = array_map(fn ($r) => [
            'Payment Method' => $r['payment_method'] ?? '',
            'Payments' => $r['payments'] ?? 0,
            'Amount' => number_format($r['amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Payment Methods');
    }

    private function onTimeVsLateSheet(): GenericArrayExport
    {
        $otl = $this->report['on_time_vs_late'] ?? [];

        $rows = [
            ['Metric' => 'On-Time Payments', 'Value' => $otl['on_time_payments'] ?? 0],
            ['Metric' => 'Late Payments', 'Value' => $otl['late_payments'] ?? 0],
            ['Metric' => 'On-Time Amount', 'Value' => number_format($otl['on_time_amount'] ?? 0, 2)],
            ['Metric' => 'Late Amount', 'Value' => number_format($otl['late_amount'] ?? 0, 2)],
            ['Metric' => 'On-Time Rate', 'Value' => ($otl['on_time_rate'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'On-Time vs Late');
    }

    private function agingSheet(): GenericArrayExport
    {
        $aging = $this->report['aging'] ?? [];
        $buckets = $aging['buckets'] ?? [];

        $rows = [];
        foreach ($buckets as $b) {
            $rows[] = [
                'Bucket' => $b['bucket'] ?? '',
                'Payments' => $b['payments'] ?? 0,
                'Late Amount' => number_format($b['amount'] ?? 0, 2),
            ];
        }

        if (empty($rows)) {
            $rows = [['No data']];
        }

        return new GenericArrayExport($rows, 'Aging');
    }

    private function scheduledVsActualSheet(): GenericArrayExport
    {
        $sva = $this->report['scheduled_vs_actual'] ?? [];

        $rows = [
            ['Metric' => 'Scheduled Amount', 'Value' => number_format($sva['scheduled_amount'] ?? 0, 2)],
            ['Metric' => 'Actual Collected', 'Value' => number_format($sva['actual_collected'] ?? 0, 2)],
            ['Metric' => 'Variance (Scheduled - Actual)', 'Value' => number_format($sva['variance'] ?? 0, 2)],
            ['Metric' => 'Collection Efficiency', 'Value' => ($sva['collection_efficiency'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Scheduled vs Actual');
    }

    private function partialVsFullSheet(): GenericArrayExport
    {
        $p = $this->report['partial_vs_full'] ?? [];

        $rows = [
            ['Metric' => 'Full Payments (Count)', 'Value' => number_format($p['full_payments_count'] ?? 0)],
            ['Metric' => 'Partial Payments (Count)', 'Value' => number_format($p['partial_payments_count'] ?? 0)],
            ['Metric' => 'Full Payments (Amount)', 'Value' => number_format($p['full_payments_amount'] ?? 0, 2)],
            ['Metric' => 'Partial Payments (Amount)', 'Value' => number_format($p['partial_payments_amount'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Partial vs Full');
    }

    private function recoverySheet(): GenericArrayExport
    {
        $r = $this->report['recovery'] ?? [];

        $rows = [
            ['Metric' => 'Total Overdue Amount', 'Value' => number_format($r['total_overdue_amount'] ?? 0, 2)],
            ['Metric' => 'Recovery Collected', 'Value' => number_format($r['recovery_collected'] ?? 0, 2)],
            ['Metric' => 'Recovery Transactions', 'Value' => number_format($r['recovery_transactions'] ?? 0)],
            ['Metric' => 'Recovery Rate', 'Value' => ($r['recovery_rate_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Recovery');
    }

    private function topCustomersSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['top_customers'] ?? []);
        if (count($rows) === 0) {
            return new GenericArrayExport([['No data']], 'Top Customers');
        }

        $mapped = array_map(fn ($r) => [
            'Customer' => $r['customer'] ?? '',
            'Payments' => $r['payments'] ?? 0,
            'Amount' => number_format($r['amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Top Customers');
    }

    private function officerPerformanceSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['officer_performance'] ?? []);
        if (count($rows) === 0) {
            return new GenericArrayExport([['No data']], 'Officer Performance');
        }

        $mapped = array_map(fn ($r) => [
            'Officer' => $r['officer'] ?? '',
            'Payments' => $r['payments'] ?? 0,
            'Amount' => number_format($r['amount'] ?? 0, 2),
            'On-Time Rate' => ($r['on_time_rate'] ?? 0) . '%',
            'Recovery Rate' => ($r['recovery_rate_pct'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'Officer Performance');
    }

    private function loanLevelSheet(): GenericArrayExport
    {
        $paginator = $this->report['loan_level'] ?? null;
        $items = $this->paginatorItems($paginator);

        if (empty($items)) {
            return new GenericArrayExport([['No data']], 'Loan Level');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Product' => $r['product'] ?? '',
            'Branch' => $r['branch'] ?? '',
            'Status' => $r['status'] ?? '',
            'Total Due' => number_format($r['total_due'] ?? 0, 2),
            'Paid (Period)' => number_format($r['period_paid'] ?? 0, 2),
            'Total Paid' => number_format($r['total_paid'] ?? 0, 2),
            'Outstanding' => number_format($r['outstanding'] ?? 0, 2),
            'Last Payment Date' => $r['last_payment_date'] ?? '',
        ], $items);

        return new GenericArrayExport($mapped, 'Loan Level');
    }

    private function installmentLevelSheet(): GenericArrayExport
    {
        $paginator = $this->report['installment_level'] ?? null;
        $items = $this->paginatorItems($paginator);

        if (empty($items)) {
            return new GenericArrayExport([['No data']], 'Installment Level');
        }

        $mapped = array_map(fn ($r) => [
            'Customer' => $r['customer'] ?? '',
            'Installment #' => $r['installment_number'] ?? 0,
            'Due Date' => $r['due_date'] ?? '',
            'Paid Date' => $r['paid_date'] ?? '',
            'Status' => $r['status'] ?? '',
            'Total Due' => number_format($r['total_due'] ?? 0, 2),
            'Amount Paid' => number_format($r['amount_paid'] ?? 0, 2),
            'Outstanding' => number_format($r['outstanding_amount'] ?? 0, 2),
            'Paid (Period)' => number_format($r['paid_in_period'] ?? 0, 2),
        ], $items);

        return new GenericArrayExport($mapped, 'Installment Level');
    }

    private function paginatorItems($maybePaginator): array
    {
        if ($maybePaginator instanceof LengthAwarePaginator) {
            return array_map(function ($item) {
                if (is_object($item) && method_exists($item, 'toArray')) {
                    return (array) $item->toArray();
                }

                return is_object($item) ? (array) $item : (array) $item;
            }, $maybePaginator->items());
        }

        if ($maybePaginator instanceof Collection) {
            return $maybePaginator->values()->all();
        }

        if (is_array($maybePaginator)) {
            return $maybePaginator;
        }

        return [];
    }

    private function normalizeRows($maybeRows): array
    {
        if ($maybeRows instanceof Collection) {
            return $maybeRows->values()->all();
        }

        if (is_array($maybeRows)) {
            return $maybeRows;
        }

        return [];
    }
}
