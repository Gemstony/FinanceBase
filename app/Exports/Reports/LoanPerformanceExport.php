<?php

namespace App\Exports\Reports;

use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LoanPerformanceExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
    ) {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->trendsSheet(),
            $this->collectionEfficiencySheet(),
            $this->onTimeLateSheet(),
            $this->byProductSheet(),
            $this->byOfficerSheet(),
            $this->delinquencySheet(),
            $this->behaviorSheet(),
            $this->topLoansSheet(),
            $this->worstLoansSheet(),
            $this->expectedVsActualSheet(),
            $this->writeOffSheet(),
        ];
    }

    private function summarySheet(): GenericArrayExport
    {
        $summary = $this->report['summary'] ?? [];
        $filters = $this->report['filters'] ?? [];

        $rows = [
            ['SECTION' => 'REPORT FILTERS', 'VALUE' => ''],
            ['Report Period', ($filters['date_from'] ?? '') . ' to ' . ($filters['date_to'] ?? '')],
            ['Branch(es)', implode(', ', $filters['subshop_ids'] ?? ['All Accessible'])],
            ['', ''],
            ['SECTION' => 'SUMMARY KPIs', 'VALUE' => ''],
            ['Total Expected Repayments', number_format($summary['total_expected'] ?? 0, 2)],
            ['Total Collected Amount', number_format($summary['total_collected'] ?? 0, 2)],
            ['Collection Rate (%)', ($summary['collection_rate_pct'] ?? 0) . '%'],
            ['On-Time Repayment Rate (%)', ($summary['on_time_rate_pct'] ?? 0) . '%'],
            ['Late Payment Rate (%)', ($summary['late_payment_rate_pct'] ?? 0) . '%'],
            ['Default Rate (%)', ($summary['default_rate_pct'] ?? 0) . '%'],
            ['Average Days Late', number_format($summary['avg_days_late'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Summary');
    }

    private function trendsSheet(): GenericArrayExport
    {
        $rows = $this->report['trends']['rows'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No trend data']], 'Trends');
        }

        $mapped = array_map(fn ($r) => [
            'Month' => $r['month'] ?? '',
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Efficiency %' => ($r['efficiency_pct'] ?? 0) . '%',
        ], $rows);

        $totalExpected = array_sum(array_map(fn ($r) => (float) ($r['expected'] ?? 0), $rows));
        $totalCollected = array_sum(array_map(fn ($r) => (float) ($r['collected'] ?? 0), $rows));
        $eff = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 2) : 0;

        $mapped[] = [
            'Month' => 'TOTAL',
            'Expected' => number_format($totalExpected, 2),
            'Collected' => number_format($totalCollected, 2),
            'Efficiency %' => $eff . '%',
        ];

        return new GenericArrayExport($mapped, 'Trends');
    }

    private function collectionEfficiencySheet(): GenericArrayExport
    {
        $ce = $this->report['collection_efficiency'] ?? [];

        $rows = [
            ['METRIC' => 'Expected Amount', 'VALUE' => number_format($ce['expected'] ?? 0, 2)],
            ['METRIC' => 'Collected Amount', 'VALUE' => number_format($ce['collected'] ?? 0, 2)],
            ['METRIC' => 'Collection Efficiency', 'VALUE' => ($ce['efficiency_pct'] ?? 0) . '%'],
            ['METRIC' => 'Missed Payments (Count)', 'VALUE' => number_format($ce['missed_payments_count'] ?? 0)],
            ['METRIC' => 'Missed Payments (Amount)', 'VALUE' => number_format($ce['missed_payments_amount'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Collection Efficiency');
    }

    private function onTimeLateSheet(): GenericArrayExport
    {
        $otl = $this->report['on_time_late'] ?? [];

        $rows = [
            [
                'Category' => 'On-Time',
                'Count' => $otl['on_time']['count'] ?? 0,
                'Amount' => number_format($otl['on_time']['amount'] ?? 0, 2),
            ],
            [
                'Category' => 'Late',
                'Count' => $otl['late']['count'] ?? 0,
                'Amount' => number_format($otl['late']['amount'] ?? 0, 2),
            ],
            [
                'Category' => 'Missed',
                'Count' => $otl['missed']['count'] ?? 0,
                'Amount' => number_format($otl['missed']['amount'] ?? 0, 2),
            ],
        ];

        return new GenericArrayExport($rows, 'On-Time vs Late');
    }

    private function byProductSheet(): GenericArrayExport
    {
        $rows = $this->report['by_product'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Product');
        }

        $mapped = array_map(fn ($r) => [
            'Product' => $r['product_name'] ?? '',
            'Total Loans' => $r['total_loans'] ?? 0,
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Efficiency %' => ($r['efficiency_pct'] ?? 0) . '%',
            'PAR30' => number_format($r['par30'] ?? 0, 2),
        ], $rows);

        $totalLoans = array_sum(array_map(fn ($r) => (int) ($r['total_loans'] ?? 0), $rows));
        $totalExpected = array_sum(array_map(fn ($r) => (float) ($r['expected'] ?? 0), $rows));
        $totalCollected = array_sum(array_map(fn ($r) => (float) ($r['collected'] ?? 0), $rows));
        $totalPar30 = array_sum(array_map(fn ($r) => (float) ($r['par30'] ?? 0), $rows));
        $eff = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 2) : 0;

        $mapped[] = [
            'Product' => 'TOTAL',
            'Total Loans' => $totalLoans,
            'Expected' => number_format($totalExpected, 2),
            'Collected' => number_format($totalCollected, 2),
            'Efficiency %' => $eff . '%',
            'PAR30' => number_format($totalPar30, 2),
        ];

        return new GenericArrayExport($mapped, 'By Product');
    }

    private function byOfficerSheet(): GenericArrayExport
    {
        $rows = $this->report['by_officer'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'By Officer');
        }

        $mapped = array_map(fn ($r) => [
            'Officer' => $r['officer'] ?? '',
            'Loans Managed' => $r['loans_managed'] ?? 0,
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Efficiency %' => ($r['efficiency_pct'] ?? 0) . '%',
            'PAR30' => number_format($r['par30'] ?? 0, 2),
        ], $rows);

        $totalLoans = array_sum(array_map(fn ($r) => (int) ($r['loans_managed'] ?? 0), $rows));
        $totalExpected = array_sum(array_map(fn ($r) => (float) ($r['expected'] ?? 0), $rows));
        $totalCollected = array_sum(array_map(fn ($r) => (float) ($r['collected'] ?? 0), $rows));
        $totalPar30 = array_sum(array_map(fn ($r) => (float) ($r['par30'] ?? 0), $rows));
        $eff = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 2) : 0;

        $mapped[] = [
            'Officer' => 'TOTAL',
            'Loans Managed' => $totalLoans,
            'Expected' => number_format($totalExpected, 2),
            'Collected' => number_format($totalCollected, 2),
            'Efficiency %' => $eff . '%',
            'PAR30' => number_format($totalPar30, 2),
        ];

        return new GenericArrayExport($mapped, 'By Officer');
    }

    private function delinquencySheet(): GenericArrayExport
    {
        $d = $this->report['delinquency'] ?? [];

        $rows = [
            ['METRIC' => 'Number of Overdue Loans', 'VALUE' => number_format($d['overdue_loans'] ?? 0)],
            ['METRIC' => 'Overdue Amount', 'VALUE' => number_format($d['overdue_amount'] ?? 0, 2)],
            ['METRIC' => 'Loans > 90 days', 'VALUE' => number_format($d['loans_over_90_days'] ?? 0)],
            ['METRIC' => 'Default Rate', 'VALUE' => ($d['default_rate_pct'] ?? 0) . '%'],
        ];

        return new GenericArrayExport($rows, 'Delinquency');
    }

    private function behaviorSheet(): GenericArrayExport
    {
        $b = $this->report['behavior'] ?? [];
        $repeatLate = $b['repeat_late_payers'] ?? [];

        $rows = [
            ['METRIC' => 'Average Repayment Delay (Days)', 'VALUE' => number_format($b['avg_repayment_delay_days'] ?? 0, 2)],
            ['METRIC' => 'Early Payments (Count)', 'VALUE' => number_format($b['early_payments_count'] ?? 0)],
            ['METRIC' => 'Early Payments (Amount)', 'VALUE' => number_format($b['early_payments_amount'] ?? 0, 2)],
            ['', ''],
            ['SECTION' => 'REPEAT LATE PAYERS (TOP 10)', 'VALUE' => ''],
        ];

        if (empty($repeatLate)) {
            $rows[] = ['No data', ''];
            return new GenericArrayExport($rows, 'Behavior');
        }

        $rows[] = ['Customer', 'Late Payments Count'];
        foreach ($repeatLate as $r) {
            $rows[] = [
                $r['customer'] ?? '',
                $r['late_payments_count'] ?? 0,
            ];
        }

        return new GenericArrayExport($rows, 'Behavior');
    }

    private function topLoansSheet(): GenericArrayExport
    {
        $rows = $this->report['top_worst_loans']['top'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Top Loans');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Product' => $r['product'] ?? '',
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Efficiency %' => ($r['efficiency_pct'] ?? 0) . '%',
            'Late Amount' => number_format($r['late_amount'] ?? 0, 2),
            'Late % of Collected' => ($r['late_pct_of_collected'] ?? 0) . '%',
        ], $rows);

        return new GenericArrayExport($mapped, 'Top Loans');
    }

    private function worstLoansSheet(): GenericArrayExport
    {
        $rows = $this->report['top_worst_loans']['worst'] ?? [];
        if (empty($rows)) {
            return new GenericArrayExport([['No data']], 'Worst Loans');
        }

        $mapped = array_map(fn ($r) => [
            'Loan Code' => $r['loan_code'] ?? '',
            'Customer' => $r['customer'] ?? '',
            'Product' => $r['product'] ?? '',
            'Expected' => number_format($r['expected'] ?? 0, 2),
            'Collected' => number_format($r['collected'] ?? 0, 2),
            'Efficiency %' => ($r['efficiency_pct'] ?? 0) . '%',
            'Outstanding (In Period)' => number_format($r['outstanding_in_period'] ?? 0, 2),
        ], $rows);

        return new GenericArrayExport($mapped, 'Worst Loans');
    }

    private function expectedVsActualSheet(): GenericArrayExport
    {
        $eva = $this->report['expected_vs_actual'] ?? [];

        $rows = [
            ['METRIC' => 'Expected Repayments', 'VALUE' => number_format($eva['expected'] ?? 0, 2)],
            ['METRIC' => 'Actual Repayments', 'VALUE' => number_format($eva['actual'] ?? 0, 2)],
            ['METRIC' => 'Difference', 'VALUE' => number_format($eva['difference'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Expected vs Actual');
    }

    private function writeOffSheet(): GenericArrayExport
    {
        $wo = $this->report['write_off'] ?? [];

        $rows = [
            ['METRIC' => 'Loans Written Off', 'VALUE' => number_format($wo['written_off_loans'] ?? 0)],
            ['METRIC' => 'Amount Written Off', 'VALUE' => number_format($wo['amount_written_off'] ?? 0, 2)],
            ['METRIC' => 'Recoveries After Write-Off', 'VALUE' => number_format($wo['recoveries_after_writeoff'] ?? 0, 2)],
        ];

        return new GenericArrayExport($rows, 'Write-Off & Recovery');
    }
}
