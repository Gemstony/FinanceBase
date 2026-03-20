<?php

namespace App\Exports\Reports\Accounting;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CashBookExport implements WithMultipleSheets
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
        $account = $this->report['account'] ?? [];
        $opening = $this->report['opening'] ?? [];
        $totals = $this->report['totals'] ?? [];

        $rows = [
            ['SECTION' => 'ACCOUNT', 'VALUE' => ''],
            ['Account', (string) ($account['account_code'] ?? '') . ' - ' . (string) ($account['account_name'] ?? '')],
            ['', ''],
            ['SECTION' => 'FILTERS', 'VALUE' => ''],
            ['From Date', (string) ($filters['from_date'] ?? '')],
            ['To Date', (string) ($filters['to_date'] ?? '')],
            ['Branch(es)', implode(', ', array_map('strval', $filters['subshop_ids'] ?? [])) ?: 'All Accessible'],
            ['Transaction Type', (string) ($filters['reference_type'] ?? '')],
            ['Reference Search', (string) ($filters['reference_search'] ?? '')],
            ['', ''],
            ['SECTION' => 'BALANCES', 'VALUE' => ''],
            ['Opening Debit Total', number_format((float) ($opening['total_debit'] ?? 0), 2)],
            ['Opening Credit Total', number_format((float) ($opening['total_credit'] ?? 0), 2)],
            ['Opening Balance', number_format((float) ($opening['balance'] ?? 0), 2)],
            ['', ''],
            ['SECTION' => 'PERIOD TOTALS', 'VALUE' => ''],
            ['Total Receipts (Debits)', number_format((float) ($totals['period_debit'] ?? 0), 2)],
            ['Total Payments (Credits)', number_format((float) ($totals['period_credit'] ?? 0), 2)],
            ['Closing Balance', number_format((float) ($totals['closing_balance'] ?? 0), 2)],
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
                'Created By' => (string) ($t['created_by_name'] ?? ''),
                'Receipt (Debit)' => number_format((float) ($t['debit'] ?? 0), 2),
                'Payment (Credit)' => number_format((float) ($t['credit'] ?? 0), 2),
                'Running Balance' => number_format((float) ($t['running_balance'] ?? 0), 2),
            ];
        }

        return new GenericArrayExport(empty($rows) ? [['No data']] : $rows, 'Transactions');
    }
}
