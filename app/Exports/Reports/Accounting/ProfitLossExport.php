<?php

namespace App\Exports\Reports\Accounting;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProfitLossExport implements WithMultipleSheets
{
    public function __construct(private readonly array $report)
    {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->detailsSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $filters = $this->report['filters'] ?? [];
        $totals = $this->report['totals'] ?? [];
        $prev = $this->report['previous_period'] ?? null;

        $rows = [
            ['SECTION' => 'FILTERS', 'VALUE' => ''],
            ['From Date', (string) ($filters['from_date'] ?? '')],
            ['To Date', (string) ($filters['to_date'] ?? '')],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['Account Group', (string) ($filters['account_group_id'] ?? '')],
            ['Compare', (string) ($filters['compare'] ?? 'none')],
            ['Show %', !empty($filters['show_pct']) ? 'Yes' : 'No'],
        ];

        if ($prev) {
            $rows[] = ['', ''];
            $rows[] = ['SECTION' => 'PREVIOUS PERIOD', 'VALUE' => ''];
            $rows[] = ['Mode', (string) ($prev['mode'] ?? '')];
            $rows[] = ['From Date', (string) ($prev['from_date'] ?? '')];
            $rows[] = ['To Date', (string) ($prev['to_date'] ?? '')];
        }

        $rows[] = ['', ''];
        $rows[] = ['SECTION' => 'TOTALS', 'VALUE' => ''];
        $rows[] = ['Total Income', number_format((float) ($totals['total_income'] ?? 0), 2)];
        $rows[] = ['Total Expenses', number_format((float) ($totals['total_expenses'] ?? 0), 2)];
        $rows[] = [(string) ($totals['net_label'] ?? 'Net Profit'), number_format((float) ($totals['net_profit'] ?? 0), 2)];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function detailsSheet(): GenericArrayExport
    {
        $tree = $this->report['tree'] ?? [];
        $showPct = (bool) (($this->report['filters']['show_pct'] ?? false));
        $compare = (string) (($this->report['filters']['compare'] ?? 'none'));
        $hasCompare = $compare !== '' && strtolower($compare) !== 'none';

        $rows = [];
        foreach (['income' => 'Income', 'expense' => 'Expenses'] as $sectionKey => $sectionLabel) {
            $section = (array) ($tree[$sectionKey] ?? []);
            $groups = (array) ($section['groups'] ?? []);

            foreach ($groups as $g) {
                $groupName = (string) ($g['group_name'] ?? '');
                $accounts = (array) ($g['accounts'] ?? []);

                foreach ($accounts as $a) {
                    $row = [
                        'Section' => $sectionLabel,
                        'Group' => $groupName,
                        'Account' => trim((string) ($a['account_code'] ?? '') . ' - ' . (string) ($a['account_name'] ?? '')),
                        'Current' => number_format((float) ($a['amount'] ?? 0), 2),
                    ];

                    if ($hasCompare) {
                        $row['Previous'] = number_format((float) ($a['previous_amount'] ?? 0), 2);
                        $row['Difference'] = number_format((float) ($a['difference'] ?? 0), 2);
                    }

                    if ($showPct) {
                        $row['%'] = number_format((float) ($a['pct'] ?? 0), 2);
                    }

                    $rows[] = $row;
                }
            }
        }

        return new GenericArrayExport(empty($rows) ? [['No data']] : $rows, 'Details');
    }
}
