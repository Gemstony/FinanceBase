<?php

namespace App\Exports\Reports\Customers;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerListExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
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
        
        $data = [];
        
        // Add summary section
        $data[] = ['CUSTOMER LIST REPORT'];
        $data[] = ['Branch: ' . $this->subshopName];
        $data[] = [];
        
        // Add summary metrics
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Total Customers', $metrics['total_customers'] ?? 0];
        $data[] = ['Active Customers', $metrics['active_customers'] ?? 0];
        $data[] = ['Customers with Loans', $metrics['customers_with_loans'] ?? 0];
        $data[] = ['Defaulted Customers', $metrics['defaulted_customers'] ?? 0];
        $data[] = [];
        
        // Add detailed records
        $data[] = ['CUSTOMER RECORDS'];
        $data[] = ['Customer Name', 'Phone', 'Email', 'Status', 'Total Loans', 'Active Loans', 'Disbursed', 'Repaid', 'Outstanding', 'Overdue', 'Risk Status'];
        
        foreach ($customers as $customer) {
            $data[] = [
                $customer->name ?? '',
                $customer->phone ?? '',
                $customer->email ?? '',
                $customer->is_active ? 'Active' : 'Inactive',
                $customer->total_loans ?? 0,
                $customer->active_loans ?? 0,
                number_format($customer->total_disbursed ?? 0, 2),
                number_format($customer->total_repaid ?? 0, 2),
                number_format($customer->outstanding_balance ?? 0, 2),
                number_format($customer->overdue_amount ?? 0, 2),
                $customer->risk_status ?? 'Unknown',
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
        return 'Customer List Report';
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
            if ($rowNum === 1 && strpos($cellValue, 'CUSTOMER LIST REPORT') !== false) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => 'center'],
                ];
                $sheet->mergeCells('A' . $rowNum . ':L' . $rowNum);
            }
            
            // Section headers
            if (in_array($cellValue, ['SUMMARY METRICS', 'CUSTOMER RECORDS'])) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '007BFF']],
                    'font' => ['color' => ['rgb' => 'FFFFFF']],
                ];
                $sheet->mergeCells('A' . $rowNum . ':L' . $rowNum);
            }
            
            // Table headers
            if (in_array($cellValue, ['Customer Name', 'Phone', 'Email', 'Status', 'Total Loans', 'Active Loans', 'Disbursed', 'Repaid', 'Outstanding', 'Overdue', 'Risk Status'])) {
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