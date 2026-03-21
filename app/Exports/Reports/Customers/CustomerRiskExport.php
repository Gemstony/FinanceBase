<?php

namespace App\Exports\Reports\Customers;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerRiskExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
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
        $topRiskCustomers = $this->reportData['top_risk_customers'] ?? collect();
        
        $data = [];
        
        // Add summary section
        $data[] = ['CUSTOMER RISK REPORT'];
        $data[] = ['Branch: ' . $this->subshopName];
        $data[] = [];
        
        // Add summary metrics
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Total Customers', $metrics['total_customers'] ?? 0];
        $data[] = ['Low Risk', $metrics['low_risk_count'] ?? 0];
        $data[] = ['Medium Risk', $metrics['medium_risk_count'] ?? 0];
        $data[] = ['High Risk', $metrics['high_risk_count'] ?? 0];
        $data[] = ['Defaulted', $metrics['defaulted_count'] ?? 0];
        $data[] = ['Average Risk Score', $metrics['average_risk_score'] ?? 0];
        $data[] = [];
        
        // Add financial summary
        $data[] = ['FINANCIAL SUMMARY'];
        $data[] = ['Total Outstanding', number_format($metrics['total_outstanding'] ?? 0, 2)];
        $data[] = ['Total Overdue', number_format($metrics['total_overdue'] ?? 0, 2)];
        $data[] = ['Outstanding Penalties', number_format($metrics['total_penalties'] ?? 0, 2)];
        $data[] = [];
        
        // Add top risk customers section
        if ($topRiskCustomers->count() > 0) {
            $data[] = ['TOP RISK CUSTOMERS'];
            $data[] = ['Customer Name', 'Phone', 'Loans', 'Outstanding', 'Overdue', 'Days Past Due', 'Risk Score', 'Risk Level'];
            
            foreach ($topRiskCustomers as $customer) {
                $data[] = [
                    $customer->name ?? '',
                    $customer->phone ?? '',
                    $customer->total_loans ?? 0,
                    number_format($customer->outstanding_balance ?? 0, 2),
                    number_format($customer->overdue_amount ?? 0, 2),
                    $customer->days_past_due ?? 0,
                    $customer->risk_score ?? 0,
                    $customer->risk_level ?? 'Low Risk',
                ];
            }
            $data[] = [];
        }
        
        // Add detailed records
        $data[] = ['CUSTOMER RISK DETAILS'];
        $data[] = ['Customer Name', 'Phone', 'Loans', 'Outstanding', 'Overdue', 'DPD', 'Penalties', 'On-Time', 'Late', 'Missed', 'Risk Score', 'Risk Level'];
        
        foreach ($customers as $customer) {
            $data[] = [
                $customer->name ?? '',
                $customer->phone ?? '',
                $customer->total_loans ?? 0,
                number_format($customer->outstanding_balance ?? 0, 2),
                number_format($customer->overdue_amount ?? 0, 2),
                $customer->days_past_due ?? 0,
                number_format($customer->outstanding_penalties ?? 0, 2),
                $customer->on_time_payments ?? 0,
                $customer->late_payments ?? 0,
                $customer->missed_payments ?? 0,
                $customer->risk_score ?? 0,
                $customer->risk_level ?? 'Low Risk',
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
        return 'Customer Risk Report';
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
            if ($rowNum === 1 && strpos($cellValue, 'CUSTOMER RISK REPORT') !== false) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => 'center'],
                ];
                $sheet->mergeCells('A' . $rowNum . ':L' . $rowNum);
            }
            
            // Section headers
            if (in_array($cellValue, ['SUMMARY METRICS', 'FINANCIAL SUMMARY', 'TOP RISK CUSTOMERS', 'CUSTOMER RISK DETAILS'])) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '007BFF']],
                    'font' => ['color' => ['rgb' => 'FFFFFF']],
                ];
                $sheet->mergeCells('A' . $rowNum . ':L' . $rowNum);
            }
            
            // Table headers
            $headerRow = in_array($cellValue, [
                'Customer Name', 'Phone', 'Loans', 'Outstanding', 'Overdue', 
                'DPD', 'Penalties', 'On-Time', 'Late', 'Missed', 'Risk Score', 'Risk Level'
            ]);
            
            if ($headerRow) {
                $styles['A' . $rowNum . ':L' . $rowNum] = [
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
                    'alignment' => ['horizontal' => 'center'],
                ];
            }
        }
        
        return $styles;
    }
}
