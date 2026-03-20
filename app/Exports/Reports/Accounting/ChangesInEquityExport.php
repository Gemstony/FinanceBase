<?php

namespace App\Exports\Reports\Accounting;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ChangesInEquityExport implements WithMultipleSheets
{
    public function __construct(private readonly array $report)
    {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->equityBreakdownSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $filters = $this->report['filters'] ?? [];
        $hasData = $this->report['has_data'] ?? false;

        $rows = [
            ['SECTION' => 'FILTERS', 'VALUE' => ''],
            ['From Date', (string) ($filters['from_date'] ?? '')],
            ['To Date', (string) ($filters['to_date'] ?? '')],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
        ];

        if (!$hasData) {
            $rows[] = ['', ''];
            $rows[] = ['No data found for selected period', ''];
            return new GenericArrayExport($rows, 'Summary');
        }

        $rows[] = ['', ''];
        $rows[] = ['SECTION' => 'CHANGES IN EQUITY', 'VALUE' => ''];
        $rows[] = ['Opening Equity', number_format((float) ($this->report['opening_equity'] ?? 0), 2)];
        $rows[] = ['+ Capital Contributions', number_format((float) ($this->report['capital_contributions'] ?? 0), 2)];
        $rows[] = ['+ Net Profit', number_format((float) ($this->report['net_profit'] ?? 0), 2)];
        $rows[] = ['- Withdrawals', number_format((float) ($this->report['withdrawals'] ?? 0), 2)];
        $rows[] = ['= Closing Equity', number_format((float) ($this->report['closing_equity'] ?? 0), 2)];

        $rows[] = ['', ''];
        $rows[] = ['SECTION' => 'BALANCE SHEET RECONCILIATION', 'VALUE' => ''];
        $rows[] = ['Equity from Changes in Equity', number_format((float) ($this->report['closing_equity'] ?? 0), 2)];
        $rows[] = ['Equity from Balance Sheet', number_format((float) ($this->report['balance_sheet_equity'] ?? 0), 2)];
        
        $balanced = $this->report['validation']['balanced'] ?? true;
        $diff = $this->report['validation']['difference'] ?? 0;
        $rows[] = ['Difference', number_format((float) $diff, 2)];
        $rows[] = ['Status', $balanced ? 'BALANCED' : 'NOT BALANCED'];

        $rows[] = ['', ''];
        $rows[] = ['SECTION' => 'RETAINED EARNINGS', 'VALUE' => ''];
        $rows[] = ['Cumulative Retained Earnings', number_format((float) ($this->report['retained_earnings'] ?? 0), 2)];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function equityBreakdownSheet(): GenericArrayExport
    {
        $equityBreakdown = $this->report['equity_breakdown'] ?? [];

        if (empty($equityBreakdown)) {
            return new GenericArrayExport([['No equity accounts found']], 'Equity Breakdown');
        }

        $rows = [
            ['Account Code', 'Account Name', 'Balance']
        ];

        foreach ($equityBreakdown as $equity) {
            $rows[] = [
                (string) ($equity['account_code'] ?? ''),
                (string) ($equity['account_name'] ?? ''),
                number_format((float) ($equity['balance'] ?? 0), 2),
            ];
        }

        return new GenericArrayExport($rows, 'Equity Breakdown');
    }
}
