<?php

namespace App\Exports\Reports\Accounting;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExpensesSummaryExport implements WithMultipleSheets
{
    public function __construct(private readonly array $report)
    {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->breakdownSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $filters = $this->report['filters'] ?? [];
        $totals = $this->report['totals'] ?? [];
        $prev = $this->report['previous_period'] ?? [];

        $rows = [
            ['SECTION' => 'FILTERS', 'VALUE' => ''],
            ['From Date', (string) ($filters['from_date'] ?? '')],
            ['To Date', (string) ($filters['to_date'] ?? '')],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['Account Group', (string) ($filters['account_group_id'] ?? '')],
            ['Expense Account', (string) ($filters['expense_account_id'] ?? '')],
            ['', ''],
            ['SECTION' => 'PREVIOUS PERIOD', 'VALUE' => ''],
            ['Previous From', (string) ($prev['from_date'] ?? '')],
            ['Previous To', (string) ($prev['to_date'] ?? '')],
            ['', ''],
            ['SECTION' => 'TOTALS', 'VALUE' => ''],
            ['Total Expenses', number_format((float) ($totals['total_expenses'] ?? 0), 2)],
            ['Previous Total', number_format((float) ($totals['previous_total_expenses'] ?? 0), 2)],
            ['Difference', number_format((float) ($totals['difference_total_expenses'] ?? 0), 2)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function breakdownSheet(): GenericArrayExport
    {
        $rows = [];

        foreach (($this->report['tree'] ?? []) as $g) {
            $groupName = (string) ($g['group_name'] ?? '');
            foreach (($g['accounts'] ?? []) as $a) {
                $rows[] = [
                    'Account Group' => $groupName,
                    'Account' => trim((string) ($a['account_code'] ?? '') . ' - ' . (string) ($a['account_name'] ?? '')),
                    'Amount' => number_format((float) ($a['amount'] ?? 0), 2),
                    '%' => number_format((float) ($a['percentage'] ?? 0), 2),
                    'Previous' => number_format((float) ($a['previous_amount'] ?? 0), 2),
                    'Difference' => number_format((float) ($a['difference'] ?? 0), 2),
                ];
            }
        }

        return new GenericArrayExport(empty($rows) ? [['No data']] : $rows, 'Breakdown');
    }
}
