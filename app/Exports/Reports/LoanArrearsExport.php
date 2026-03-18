<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LoanArrearsExport implements WithMultipleSheets
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
            $this->installmentLevelSheet(),
            $this->agingBucketsSheet(),
            $this->byProductSheet(),
            $this->byBranchSheet(),
            $this->byOfficerSheet(),
            $this->topDefaultersSheet(),
            $this->missedInstallmentsSheet(),
            $this->trendSheet(),
            $this->ratioSheet(),
            $this->partialOverdueSheet(),
            $this->highRiskSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $s = $this->report['summary'] ?? [];
        $filters = $this->report['filters'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT FILTERS', 'VALUE' => ''],
            ['As At Date', $filters['as_at_date'] ?? ''],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['DPD Min', $filters['dpd_min'] ?? ''],
            ['DPD Max', $filters['dpd_max'] ?? ''],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Portfolio Outstanding', number_format($this->report['portfolio_outstanding'] ?? 0, 2)],
            ['Total Arrears', number_format($s['total_arrears'] ?? 0, 2)],
            ['Loans In Arrears', number_format($s['loans_in_arrears'] ?? 0)],
            ['Overdue Installments', number_format($s['overdue_installments'] ?? 0)],
            ['Average Arrears Per Loan', number_format($s['avg_arrears_per_loan'] ?? 0, 2)],
            ['Maximum Arrears', number_format($s['max_arrears'] ?? 0, 2)],
            ['Arrears Ratio (%)', ($this->report['arrears_ratio_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function loanLevelSheet(): GenericArrayExport
    {
        $p = $this->report['loan_level'] ?? null;
        $items = $this->paginatorItems($p);

        if (empty($items)) {
            return new GenericArrayExport([['No data']], 'Loan Level');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Product' => $r['product'] ?? '',
            'Branch' => $r['branch'] ?? '',
            'Officer' => $r['officer'] ?? '',
            'Loan Status' => $r['loan_status'] ?? '',
            'Arrears Amount' => number_format((float) ($r['arrears_amount'] ?? 0), 2),
            'Overdue Installments' => (int) ($r['overdue_installments'] ?? 0),
            'Oldest Due Date' => $r['oldest_due_date'] ?? '',
            'DPD' => (int) ($r['dpd'] ?? 0),
            'Last Payment Date' => $r['last_payment_date'] ?? '',
        ], $items);

        return new GenericArrayExport($mapped, 'Loan Level');
    }

    private function installmentLevelSheet(): GenericArrayExport
    {
        $p = $this->report['installment_level'] ?? null;
        $items = $this->paginatorItems($p);

        if (empty($items)) {
            return new GenericArrayExport([['No data']], 'Installment Level');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Installment #' => $r['installment_number'] ?? 0,
            'Due Date' => $r['due_date'] ?? '',
            'Installment Amount' => number_format((float) ($r['installment_amount'] ?? 0), 2),
            'Paid Amount' => number_format((float) ($r['paid_amount'] ?? 0), 2),
            'Arrears (Outstanding)' => number_format((float) ($r['arrears_amount'] ?? 0), 2),
            'DPD' => (int) ($r['dpd'] ?? 0),
        ], $items);

        return new GenericArrayExport($mapped, 'Installment Level');
    }

    private function agingBucketsSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['aging_buckets'] ?? []);
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Aging Buckets');
        }

        $mapped = array_map(fn ($r) => [
            'Bucket' => $r['bucket'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Total Arrears' => number_format($r['arrears'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Aging Buckets');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['by_product'] ?? []);
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Total Arrears' => number_format($r['arrears'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Product');
    }

    private function byBranchSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['by_branch'] ?? []);
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Branch');
        }

        $mapped = array_map(fn ($r) => [
            'Branch' => $r['branch'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Total Arrears' => number_format($r['arrears'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Branch');
    }

    private function byOfficerSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['by_officer'] ?? []);
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Officer');
        }

        $mapped = array_map(fn ($r) => [
            'Officer' => $r['officer'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Total Arrears' => number_format($r['arrears'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'By Officer');
    }

    private function topDefaultersSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['top_defaulters'] ?? []);
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Top Defaulters');
        }

        $mapped = array_map(fn ($r) => [
            'Customer' => $r['customer'] ?? '',
            'Loans' => $r['loans'] ?? 0,
            'Total Arrears' => number_format($r['arrears'] ?? 0, 2),
            'DPD' => $r['dpd'] ?? 0,
        ], $rows);

        return new GenericArrayExport($mapped, 'Top Defaulters');
    }

    private function missedInstallmentsSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['missed_installments'] ?? []);
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Missed Installments');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Missed Installments' => $r['missed_installments'] ?? 0,
            'Total Arrears' => number_format($r['arrears'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Missed Installments');
    }

    private function trendSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['trend'] ?? []);
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Trend');
        }

        $mapped = array_map(fn ($r) => [
            'Date' => $r['date'] ?? '',
            'Total Arrears' => number_format($r['total_arrears'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Trend');
    }

    private function ratioSheet(): GenericArrayExport
    {
        $rows = [
            ['Portfolio Outstanding', number_format($this->report['portfolio_outstanding'] ?? 0, 2)],
            ['Total Arrears', number_format(($this->report['summary']['total_arrears'] ?? 0), 2)],
            ['Arrears Ratio (%)', ($this->report['arrears_ratio_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Arrears Ratio');
    }

    private function partialOverdueSheet(): GenericArrayExport
    {
        $r = $this->report['partial_overdue'] ?? [];
        $rows = [
            ['Partial Overdue Installments', number_format($r['partial_overdue_installments'] ?? 0)],
            ['Total Partial Arrears', number_format($r['partial_arrears'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Partial Overdue');
    }

    private function highRiskSheet(): GenericArrayExport
    {
        $rows = $this->normalizeRows($this->report['high_risk'] ?? []);
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'High Risk');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Arrears' => number_format($r['arrears'] ?? 0, 2),
            'Missed Installments' => $r['missed_installments'] ?? 0,
            'DPD' => $r['dpd'] ?? 0,
        ], $rows);

        return new GenericArrayExport($mapped, 'High Risk');
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
