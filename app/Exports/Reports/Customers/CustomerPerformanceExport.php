<?php

namespace App\Exports\Reports\Customers;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerPerformanceExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected $reportData;
    protected $subshopName;

    public function __construct(array $reportData, string $subshopName = 'All Branches')
    {
        $this->reportData = $reportData;
        $this->subshopName = $subshopName;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $customers = $this->reportData['customers'] ?? collect();
        $metrics = $this->reportData['metrics'] ?? [];
        $topPerformers = $this->reportData['top_performers'] ?? collect();
        $worstPerformers = $this->reportData['worst_performers'] ?? collect();
        
        $data = [];
        
        // Add summary section
        $data[] = ['CUSTOMER PERFORMANCE REPORT'];
        $data[] = ['Branch: ' . $this->subshopName];
        $data[] = [];
        
        // Add summary metrics
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Total Customers', $metrics['total_customers'] ?? 0];
        $data[] = ['Excellent', $metrics['excellent_count'] ?? 0];
        $data[] = ['Good', $metrics['good_count'] ?? 0];
        $data[] = ['Average', $metrics['average_count'] ?? 0];
        $data[] = ['Poor', $metrics['poor_count'] ?? 0];
        $data[] = ['Defaulted', $metrics['defaulted_count'] ?? 0];
        $data[] = ['Average Score', $metrics['average_score'] ?? 0];
        $data[] = [];
        
        // Add financial summary
        $data[] = ['FINANCIAL SUMMARY'];
        $data[] = ['Total Disbursed', number_format($metrics['total_disbursed'] ?? 0, 2)];
        $data[] = ['Total Paid', number_format($metrics['total_paid'] ?? 0, 2)];
        $data[] = ['Total Outstanding', number_format($metrics['total_outstanding'] ?? 0, 2)];
        $data[] = [];
        
        // Add top performers section
        if ($topPerformers->count() > 0) {
            $data[] = ['TOP 10 PERFORMERS'];
            $data[] = ['Rank', 'Customer Name', 'Phone', 'Score', 'Performance Level'];
            
            foreach ($topPerformers as $index => $customer) {
                $data[] = [
                    $index + 1,
                    $customer->name ?? '',
                    $customer->phone ?? '',
                    $customer->performance_score ?? 0,
                    $customer->performance_level ?? '',
                ];
            }
            $data[] = [];
        }
        
        // Add worst performers section
        if ($worstPerformers->count() > 0) {
            $data[] = ['BOTTOM 10 PERFORMERS'];
            $data[] = ['Rank', 'Customer Name', 'Phone', 'Score', 'Performance Level'];
            
            $totalCustomers = $customers->count();
            foreach ($worstPerformers as $index => $customer) {
                $data[] = [
                    $totalCustomers - $worstPerformers->count() + $index + 1,
                    $customer->name ?? '',
                    $customer->phone ?? '',
                    $customer->performance_score ?? 0,
                    $customer->performance_level ?? '',
                ];
            }
            $data[] = [];
        }
        
        // Add detailed records
        $data[] = ['CUSTOMER PERFORMANCE DETAILS'];
        $data[] = [
            'Rank',
            'Customer Name',
            'Phone',
            'Total Loans',
            'Active Loans',
            'Closed Loans',
            'Total Disbursed',
            'Total Paid',
            'Total Due',
            'Outstanding',
            'Repayment Rate',
            'On-Time Payments',
            'Late Payments',
            'Missed Payments',
            'Total Penalties',
            'Penalty Frequency',
            'Overdue Amount',
            'Days Past Due',
            'Performance Score',
            'Performance Level'
        ];
        
        foreach ($customers as $index => $customer) {
            $data[] = [
                $index + 1,
                $customer->name ?? '',
                $customer->phone ?? '',
                $customer->total_loans ?? 0,
                $customer->active_loans ?? 0,
                $customer->closed_loans ?? 0,
                number_format($customer->total_disbursed ?? 0, 2),
                number_format($customer->total_paid ?? 0, 2),
                number_format($customer->total_due ?? 0, 2),
                number_format($customer->outstanding ?? 0, 2),
                number_format(($customer->repayment_rate ?? 0) * 100, 1) . '%',
                $customer->on_time_payments ?? 0,
                $customer->late_payments ?? 0,
                $customer->missed_payments ?? 0,
                number_format($customer->total_penalties ?? 0, 2),
                $customer->penalty_frequency ?? 0,
                number_format($customer->overdue_amount ?? 0, 2),
                $customer->days_past_due ?? 0,
                $customer->performance_score ?? 0,
                $customer->performance_level ?? '',
            ];
        }
        
        return collect($data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Customer Performance Report';
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        $styles = [];
        
        // Style header rows
        foreach ($sheet->getRowIterator() as $rowNum => $row) {
            $cellValue = $sheet->getCell('A' . $rowNum)->getValue();
            
            // Main report title
            if ($rowNum === 1 && strpos($cellValue, 'CUSTOMER PERFORMANCE REPORT') !== false) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => 'center'],
                ];
                $sheet->mergeCells('A' . $rowNum . ':T' . $rowNum);
            }
            
            // Section headers
            if (in_array($cellValue, ['SUMMARY METRICS', 'FINANCIAL SUMMARY', 'TOP 10 PERFORMERS', 'BOTTOM 10 PERFORMERS', 'CUSTOMER PERFORMANCE DETAILS'])) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '007BFF']],
                    'font' => ['color' => ['rgb' => 'FFFFFF']],
                ];
                $sheet->mergeCells('A' . $rowNum . ':T' . $rowNum);
            }
            
            // Table headers
            $headerRow = in_array($cellValue, [
                'Rank', 'Customer Name', 'Phone', 'Total Loans', 'Active Loans', 'Closed Loans',
                'Total Disbursed', 'Total Paid', 'Total Due', 'Outstanding', 'Repayment Rate',
                'On-Time Payments', 'Late Payments', 'Missed Payments', 'Total Penalties',
                'Penalty Frequency', 'Overdue Amount', 'Days Past Due', 'Performance Score', 'Performance Level'
            ]);
            
            if ($headerRow) {
                $styles['A' . $rowNum . ':T' . $rowNum] = [
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
                    'alignment' => ['horizontal' => 'center'],
                ];
            }
        }
        
        return $styles;
    }
}
