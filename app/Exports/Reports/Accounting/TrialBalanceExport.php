<?php

namespace App\Exports\Reports\Accounting;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TrialBalanceExport implements WithMultipleSheets
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
        $validation = $this->report['validation'] ?? [];

        $rows = [
            ['SECTION' => 'FILTERS', 'VALUE' => ''],
            ['As-of Date', $filters['as_of'] ?? ''],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['Account Class Filter', (string) ($filters['account_class_id'] ?? '')],
            ['Hide Zero Balances', !empty($filters['hide_zero']) ? 'YES' : 'NO'],
            ['', ''],
            ['SECTION' => 'TOTALS', 'VALUE' => ''],
            ['Total Debit', number_format((float) ($totals['total_debit'] ?? 0), 2)],
            ['Total Credit', number_format((float) ($totals['total_credit'] ?? 0), 2)],
            ['', ''],
            ['SECTION' => 'VALIDATION', 'VALUE' => ''],
            ['Balanced', !empty($validation['balanced']) ? 'YES' : 'NO'],
            ['Difference (Total Debit - Total Credit)', number_format((float) ($validation['difference'] ?? 0), 2)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function detailsSheet(): GenericArrayExport
    {
        $tree = $this->report['tree'] ?? [];
        $rows = [];

        foreach ($tree as $classNode) {
            $className = (string) ($classNode['class_name'] ?? '');
            foreach (($classNode['groups'] ?? []) as $groupNode) {
                $groupName = (string) ($groupNode['group_name'] ?? '');
                foreach (($groupNode['accounts'] ?? []) as $acc) {
                    $rows[] = [
                        'Class' => $className,
                        'Group' => $groupName,
                        'Account' => (string) ($acc['account_code'] ?? '') . ' - ' . (string) ($acc['account_name'] ?? ''),
                        'Debit' => number_format((float) ($acc['debit'] ?? 0), 2),
                        'Credit' => number_format((float) ($acc['credit'] ?? 0), 2),
                    ];
                }
            }
        }

        return new GenericArrayExport(empty($rows) ? [['No data']] : $rows, 'Trial Balance');
    }
}
