<?php

namespace App\Exports\Reports\Accounting;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BalanceSheetExport implements WithMultipleSheets
{
    public function __construct(private readonly array $report)
    {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->assetsSheet(),
            $this->liabilitiesSheet(),
            $this->equitySheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $filters = $this->report['filters'] ?? [];
        $totals = $this->report['totals'] ?? [];
        $validation = $this->report['validation'] ?? [];

        $rows = [
            ['SECTION' => 'FILTERS', 'VALUE' => ''],
            ['As-of Date', $filters['as_of'] ?? ''],
            ['Compare As-of', $filters['compare_as_of'] ?? ''],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['Account Class Filter', (string) ($filters['account_class_id'] ?? '')],
            ['', ''],
            ['SECTION' => 'TOTALS', 'VALUE' => ''],
            ['Total Assets', number_format((float) ($totals['assets_total'] ?? 0), 2)],
            ['Total Liabilities', number_format((float) ($totals['liabilities_total'] ?? 0), 2)],
            ['Total Equity', number_format((float) ($totals['equity_total'] ?? 0), 2)],
            ['', ''],
            ['SECTION' => 'VALIDATION', 'VALUE' => ''],
            ['Balanced', !empty($validation['balanced']) ? 'YES' : 'NO'],
            ['Difference (Assets - (Liabilities + Equity))', number_format((float) ($validation['difference'] ?? 0), 2)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function assetsSheet(): GenericArrayExport
    {
        return new GenericArrayExport($this->flattenSection('Assets', $this->report['tree']['assets'] ?? []), 'Assets');
    }

    private function liabilitiesSheet(): GenericArrayExport
    {
        return new GenericArrayExport($this->flattenSection('Liabilities', $this->report['tree']['liabilities'] ?? []), 'Liabilities');
    }

    private function equitySheet(): GenericArrayExport
    {
        $rows = $this->flattenTree('Equity', $this->report['tree']['equity']['items'] ?? []);
        foreach (($this->report['tree']['equity']['computed'] ?? []) as $line) {
            $rows[] = [
                'Section' => 'Equity',
                'Class' => 'Computed',
                'Group' => 'Computed',
                'Account' => (string) ($line['label'] ?? ''),
                'Balance' => number_format((float) ($line['balance'] ?? 0), 2),
                'Previous Balance' => ($line['prev_balance'] ?? null) !== null ? number_format((float) $line['prev_balance'], 2) : '',
            ];
        }
        return new GenericArrayExport($rows, 'Equity');
    }

    /**
     * @param array{current?:array,non_current?:array} $section
     */
    private function flattenSection(string $name, array $section): array
    {
        $rows = [];
        $rows = array_merge($rows, $this->flattenTree($name . ' (Current)', $section['current'] ?? []));
        $rows = array_merge($rows, $this->flattenTree($name . ' (Non-Current)', $section['non_current'] ?? []));
        return $rows;
    }

    /**
     * @param array<string, mixed> $tree
     */
    private function flattenTree(string $sectionName, array $tree): array
    {
        $rows = [];
        foreach ($tree as $classNode) {
            $className = (string) ($classNode['class_name'] ?? '');
            foreach (($classNode['groups'] ?? []) as $groupNode) {
                $groupName = (string) ($groupNode['group_name'] ?? '');
                foreach (($groupNode['accounts'] ?? []) as $acc) {
                    $rows[] = [
                        'Section' => $sectionName,
                        'Class' => $className,
                        'Group' => $groupName,
                        'Account' => (string) ($acc['account_code'] ?? '') . ' - ' . (string) ($acc['account_name'] ?? ''),
                        'Balance' => number_format((float) ($acc['balance'] ?? 0), 2),
                        'Previous Balance' => ($acc['prev_balance'] ?? null) !== null ? number_format((float) $acc['prev_balance'], 2) : '',
                    ];
                }
            }
        }

        return empty($rows) ? [['No data']] : $rows;
    }
}
