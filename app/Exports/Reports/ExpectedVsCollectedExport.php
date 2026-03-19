<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExpectedVsCollectedExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
    ) {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->periodBreakdownSheet(),
            $this->loanLevelSheet(),
            $this->installmentLevelSheet(),
            $this->byProductSheet(),
            $this->byBranchSheet(),
            $this->byOfficerSheet(),
            $this->topUnderperformingSheet(),
            $this->missedCollectionsSheet(),
            $this->partialPaymentsSheet(),
            $this->arrearsContributionSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $summary = $this->report['summary'] ?? [];
        $filters = $this->report['filters'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT FILTERS', 'VALUE' => ''],
            ['Report Period', ($filters['start_date'] ?? '') . ' to ' . ($filters['end_date'] ?? '')],
            ['Branch(es)', implode(', ', $filters['subshop_ids'] ?? ['All Accessible'])],
            ['Group By', (string) ($filters['group_by'] ?? 'auto')],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Total Expected Amount', number_format($summary['total_expected'] ?? 0, 2)],
            ['Total Collected Amount', number_format($summary['total_collected'] ?? 0, 2)],
            ['Total Variance (Expected - Collected)', number_format($summary['total_variance'] ?? 0, 2)],
            ['Collection Rate (%)', number_format($summary['collection_rate_pct'] ?? 0, 2) . '%'],
            ['Total Due Installments', number_format($summary['total_due_installments'] ?? 0)],
            ['Total Paid Installments', number_format($summary['total_paid_installments'] ?? 0)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function periodBreakdownSheet(): GenericArrayExport
    {
        $rows = $this->report['period_breakdown']['rows'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Period Breakdown');
        }

        $mapped = array_map(fn ($r) => [
            'Period' => $r['period'] ?? '',
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Variance' => number_format($r['variance'] ?? 0, 2),
            'Collection Rate %' => number_format($r['collection_rate_pct'] ?? 0, 2) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'Period Breakdown');
    }

    private function loanLevelSheet(): GenericArrayExport
    {
        $rows = $this->report['loan_level'] ?? [];
        if ($rows instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $rows = $rows->items();
        }
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Loan Level');
        }

        $mapped = array_map(function ($r) {
            $expected = (float) ($r->expected ?? 0);
            $collected = (float) ($r->collected ?? 0);
            $rate = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0;

            return [
                'Loan Code' => $r->loan_code ?? '',
                'Customer' => $r->customer ?? '',
                'Product' => $r->product ?? '',
                'Branch' => $r->branch ?? '',
                'Officer' => $r->officer ?? '',
                'Expected' => number_format($expected, 2),
                'Collected' => number_format($collected, 2),
                'Variance' => number_format((float) ($r->variance ?? 0), 2),
                'Collection Rate %' => $rate . '%',
                'Loan Status' => $r->loan_status ?? '',
            ];
        }, $rows);

        return new GenericArrayExport($mapped, 'Loan Level');
    }

    private function installmentLevelSheet(): GenericArrayExport
    {
        $rows = $this->report['installment_level'] ?? [];
        if ($rows instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $rows = $rows->items();
        }
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Installments');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r->loan_code ?? '',
            'Customer' => $r->customer ?? '',
            'Installment #' => $r->installment_number ?? '',
            'Due Date' => $r->due_date ?? '',
            'Expected' => number_format($r->expected ?? 0, 2),
            'Collected' => number_format($r->collected ?? 0, 2),
            'Variance' => number_format($r->variance ?? 0, 2),
            'Status' => $r->status ?? '',
        ], $rows);

        return new GenericArrayExport($mapped, 'Installments');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->report['by_product'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product'] ?? '',
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Variance' => number_format($r['variance'] ?? 0, 2),
            'Collection Rate %' => number_format($r['collection_rate_pct'] ?? 0, 2) . '%',
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
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Variance' => number_format($r['variance'] ?? 0, 2),
            'Collection Rate %' => number_format($r['collection_rate_pct'] ?? 0, 2) . '%',
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
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Variance' => number_format($r['variance'] ?? 0, 2),
            'Collection Rate %' => number_format($r['collection_rate_pct'] ?? 0, 2) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'By Officer');
    }

    private function topUnderperformingSheet(): GenericArrayExport
    {
        $tu = $this->report['top_underperforming'] ?? [];

        $rows = [];
        $top = $tu['top'] ?? [];
        $under = $tu['underperforming'] ?? [];

        $rows[] = ['SECTION' => 'TOP PERFORMERS', 'Loan' => '', 'Customer' => '', 'Expected' => '', 'Collected' => '', 'Rate' => ''];
        foreach ($top as $r) {
            $rows[] = [
                'SECTION' => '',
                'Loan' => $r['loan_code'] ?? '',
                'Customer' => $r['customer'] ?? '',
                'Expected' => number_format($r['expected'] ?? 0, 2),
                'Collected' => number_format($r['collected'] ?? 0, 2),
                'Rate' => number_format($r['collection_rate_pct'] ?? 0, 2) . '%',
            ];
        }

        $rows[] = ['SECTION' => '', 'Loan' => '', 'Customer' => '', 'Expected' => '', 'Collected' => '', 'Rate' => ''];
        $rows[] = ['SECTION' => 'UNDERPERFORMING', 'Loan' => '', 'Customer' => '', 'Expected' => '', 'Collected' => '', 'Rate' => ''];
        foreach ($under as $r) {
            $rows[] = [
                'SECTION' => '',
                'Loan' => $r['loan_code'] ?? '',
                'Customer' => $r['customer'] ?? '',
                'Expected' => number_format($r['expected'] ?? 0, 2),
                'Collected' => number_format($r['collected'] ?? 0, 2),
                'Rate' => number_format($r['collection_rate_pct'] ?? 0, 2) . '%',
            ];
        }

        if (count($rows) <= 3) {
            return new GenericArrayExport([['No data']], 'Top & Under');
        }

        return new GenericArrayExport($rows, 'Top & Under');
    }

    private function missedCollectionsSheet(): GenericArrayExport
    {
        $rows = $this->report['missed_collections'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Missed Collections');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Missed Amount' => number_format($r['missed_amount'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Missed Collections');
    }

    private function partialPaymentsSheet(): GenericArrayExport
    {
        $pp = $this->report['partial_payments'] ?? [];

        $rows = [
            ['Metric' => 'Partial Installments (Count)', 'Value' => number_format($pp['partial_installments'] ?? 0)],
            ['Metric' => 'Total Remaining Amount', 'Value' => number_format($pp['remaining_amount'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Partial Payments');
    }

    private function arrearsContributionSheet(): GenericArrayExport
    {
        $ac = $this->report['arrears_contribution'] ?? [];

        $rows = [
            ['Metric' => 'Shortfall (Expected - Collected, Min 0)', 'Value' => number_format($ac['shortfall'] ?? 0, 2)],
            ['Metric' => 'Shortfall % of Expected', 'Value' => number_format($ac['shortfall_pct_of_expected'] ?? 0, 2) . '%'],
        ];

        return new GenericArrayExport($rows, 'Arrears Contribution');
    }
}
