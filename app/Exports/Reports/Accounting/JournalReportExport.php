<?php

namespace App\Exports\Reports\Accounting;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class JournalReportExport implements WithMultipleSheets
{
    public function __construct(private readonly array $report)
    {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->entriesSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $filters = $this->report['filters'] ?? [];
        $totals = $this->report['totals'] ?? [];

        $rows = [
            ['SECTION' => 'FILTERS', 'VALUE' => ''],
            ['From Date', (string) ($filters['from_date'] ?? '')],
            ['To Date', (string) ($filters['to_date'] ?? '')],
            ['Branch', (string) ($filters['subshop_id'] ?? '') ?: 'All Accessible'],
            ['Reference Search', (string) ($filters['reference'] ?? '')],
            ['Transaction Type', (string) ($filters['reference_type'] ?? '')],
            ['Created By', (string) ($filters['created_by'] ?? '')],
            ['', ''],
            ['SECTION' => 'TOTALS', 'VALUE' => ''],
            ['Entries', (string) ($totals['entries_count'] ?? 0)],
            ['Total Debits', number_format((float) ($totals['total_debit'] ?? 0), 2)],
            ['Total Credits', number_format((float) ($totals['total_credit'] ?? 0), 2)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function entriesSheet(): GenericArrayExport
    {
        $rows = [];

        foreach (($this->report['entries_all'] ?? []) as $e) {
            $header = [
                'Entry ID' => (string) ($e['id'] ?? ''),
                'Date' => (string) ($e['transaction_date'] ?? ''),
                'Type' => (string) ($e['reference_type'] ?? ''),
                'Reference' => (string) ($e['reference_id'] ?? ''),
                'Description' => (string) ($e['description'] ?? ''),
                'Created By' => (string) ($e['created_by_name'] ?? ''),
                'Total Debit' => number_format((float) ($e['total_debit'] ?? 0), 2),
                'Total Credit' => number_format((float) ($e['total_credit'] ?? 0), 2),
                'Balanced' => !empty($e['is_balanced']) ? 'YES' : 'NO',
            ];

            $lines = $e['lines'] ?? [];
            if (empty($lines)) {
                $rows[] = array_merge($header, [
                    'Account' => '',
                    'Debit' => number_format(0, 2),
                    'Credit' => number_format(0, 2),
                    'Line Description' => '',
                ]);
                continue;
            }

            foreach ($lines as $l) {
                $rows[] = array_merge($header, [
                    'Account' => trim((string) ($l['account_code'] ?? '') . ' ' . (string) ($l['account_name'] ?? '')),
                    'Debit' => number_format((float) ($l['debit'] ?? 0), 2),
                    'Credit' => number_format((float) ($l['credit'] ?? 0), 2),
                    'Line Description' => (string) ($l['description'] ?? ''),
                ]);
            }
        }

        return new GenericArrayExport(empty($rows) ? [['No data']] : $rows, 'Entries');
    }
}
