<?php

namespace App\Exports\Reports\Accounting;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CashFlowExport implements WithMultipleSheets
{
    public function __construct(private readonly array $report)
    {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->transactionsSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $filters = $this->report['filters'] ?? [];
        $cash = $this->report['cash_account'] ?? [];
        $sections = $this->report['sections'] ?? [];
        $totals = $this->report['totals'] ?? [];

        $rows = [
            ['SECTION' => 'CASH ACCOUNT', 'VALUE' => ''],
            ['Account', (string) ($cash['account_code'] ?? '') . ' - ' . (string) ($cash['account_name'] ?? '')],
            ['', ''],
            ['SECTION' => 'FILTERS', 'VALUE' => ''],
            ['From Date', (string) ($filters['from_date'] ?? '')],
            ['To Date', (string) ($filters['to_date'] ?? '')],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['Transaction Type', (string) ($filters['reference_type'] ?? '')],
            ['', ''],
            ['SECTION' => 'BALANCES', 'VALUE' => ''],
            ['Opening Balance', number_format((float) ($this->report['opening_balance'] ?? 0), 2)],
            ['Closing Balance', number_format((float) ($totals['closing_balance'] ?? 0), 2)],
            ['', ''],
            ['SECTION' => 'OPERATING ACTIVITIES', 'VALUE' => ''],
            ['Inflow', number_format((float) (($sections['OPERATING']['inflow'] ?? 0)), 2)],
            ['Outflow', number_format((float) (($sections['OPERATING']['outflow'] ?? 0)), 2)],
            ['Net', number_format((float) (($sections['OPERATING']['net'] ?? 0)), 2)],
            ['', ''],
            ['SECTION' => 'INVESTING ACTIVITIES', 'VALUE' => ''],
            ['Inflow', number_format((float) (($sections['INVESTING']['inflow'] ?? 0)), 2)],
            ['Outflow', number_format((float) (($sections['INVESTING']['outflow'] ?? 0)), 2)],
            ['Net', number_format((float) (($sections['INVESTING']['net'] ?? 0)), 2)],
            ['', ''],
            ['SECTION' => 'FINANCING ACTIVITIES', 'VALUE' => ''],
            ['Inflow', number_format((float) (($sections['FINANCING']['inflow'] ?? 0)), 2)],
            ['Outflow', number_format((float) (($sections['FINANCING']['outflow'] ?? 0)), 2)],
            ['Net', number_format((float) (($sections['FINANCING']['net'] ?? 0)), 2)],
            ['', ''],
            ['SECTION' => 'TOTALS', 'VALUE' => ''],
            ['Total Inflow', number_format((float) ($totals['total_inflow'] ?? 0), 2)],
            ['Total Outflow', number_format((float) ($totals['total_outflow'] ?? 0), 2)],
            ['Net Cash Flow', number_format((float) ($totals['net_cash_flow'] ?? 0), 2)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function transactionsSheet(): GenericArrayExport
    {
        $rows = [];
        foreach (($this->report['transactions_all'] ?? []) as $t) {
            $rows[] = [
                'Date' => (string) ($t['transaction_date'] ?? ''),
                'Journal Entry' => (string) ($t['journal_entry_id'] ?? ''),
                'Type' => (string) ($t['reference_type'] ?? ''),
                'Ref ID' => (string) ($t['reference_id'] ?? ''),
                'Description' => (string) ($t['journal_description'] ?? ''),
                'Line Description' => (string) ($t['line_description'] ?? ''),
                'Counter Account' => trim((string) ($t['counter_account_code'] ?? '') . ' - ' . (string) ($t['counter_account_name'] ?? '')),
                'Activity Type' => (string) ($t['activity_type'] ?? ''),
                'Inflow (Debit)' => number_format((float) ($t['debit'] ?? 0), 2),
                'Outflow (Credit)' => number_format((float) ($t['credit'] ?? 0), 2),
                'Running Balance' => number_format((float) ($t['running_balance'] ?? 0), 2),
            ];
        }

        return new GenericArrayExport(empty($rows) ? [['No data']] : $rows, 'Transactions');
    }
}
