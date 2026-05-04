<?php

namespace App\Exports\Reports\Risk;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProvisionReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
        private readonly string $shopName,
        private readonly ?string $subshopName,
    ) {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->breakdownSheet(),
            $this->journalEntrySheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $s = $this->report['summary'] ?? [];
        $thresholds = $this->report['thresholds_used'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT INFO', 'VALUE' => ''],
            ['Shop Name', $this->shopName],
            ['Branch', $this->subshopName ?? 'All Branches'],
            ['Report Generated At', $this->report['generated_at'] ?? now()->format('Y-m-d H:i:s')],
            ['', ''],
            ['SECTION' => 'PROVISION RATES', 'VALUE' => ''],
            ['PAR30 Rate', ($thresholds['par30_rate'] ?? 0) . '%'],
            ['PAR60 Rate', ($thresholds['par60_rate'] ?? 0) . '%'],
            ['PAR90 Rate', ($thresholds['par90_rate'] ?? 0) . '%'],
            ['Default Rate', ($thresholds['default_rate'] ?? 0) . '%'],
            ['', ''],
            ['SECTION' => 'PORTFOLIO SUMMARY', 'VALUE' => ''],
            ['Total Portfolio Outstanding', number_format($s['total_outstanding'] ?? 0, 2)],
            ['Total Provision Required', number_format($s['total_provision_required'] ?? 0, 2)],
            ['Provision Coverage Ratio', ($s['provision_percentage'] ?? 0) . '%'],
            ['Net Portfolio Value', number_format(($s['total_outstanding'] ?? 0) - ($s['total_provision_required'] ?? 0), 2)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function breakdownSheet(): GenericArrayExport
    {
        $breakdown = $this->report['breakdown'] ?? [];
        $totalProvision = $this->report['summary']['total_provision_required'] ?? 0;

        if (empty($breakdown)) {
            return new GenericArrayExport([['No data']], 'Breakdown');
        }

        $mapped = [];
        foreach ($breakdown as $status => $data) {
            if ($data['count'] > 0) {
                $provisionPct = $totalProvision > 0
                    ? round(($data['provision'] / $totalProvision) * 100, 1)
                    : 0;

                $mapped[] = [
                    'Risk Category' => strtoupper($status),
                    'Loan Count' => $data['count'],
                    'Outstanding Amount' => number_format($data['outstanding'], 2),
                    '% of Portfolio' => $data['percentage_of_portfolio'] . '%',
                    'Provision Rate' => $data['rate'] . '%',
                    'Provision Amount' => number_format($data['provision'], 2),
                    '% of Total Provision' => $provisionPct . '%',
                ];
            }
        }

        // Add total row
        $mapped[] = [
            'Risk Category' => 'TOTAL',
            'Loan Count' => array_sum(array_column($breakdown, 'count')),
            'Outstanding Amount' => number_format($this->report['summary']['total_outstanding'] ?? 0, 2),
            '% of Portfolio' => '100%',
            'Provision Rate' => '-',
            'Provision Amount' => number_format($this->report['summary']['total_provision_required'] ?? 0, 2),
            '% of Total Provision' => '100%',
        ];

        return new GenericArrayExport($mapped, 'Breakdown');
    }

    private function journalEntrySheet(): GenericArrayExport
    {
        $totalProvision = $this->report['summary']['total_provision_required'] ?? 0;

        $rows = [
            ['SECTION' => 'SUGGESTED JOURNAL ENTRY', 'VALUE' => ''],
            ['', ''],
            ['Account', 'Debit', 'Credit', 'Description'],
            ['Loan Loss Expense', number_format($totalProvision, 2), '-', 'Provision for loan losses'],
            ['Allowance for Loan Losses', '-', number_format($totalProvision, 2), 'Provision for loan losses'],
        ];

        return new GenericArrayExport($rows, 'Journal Entry');
    }
}
