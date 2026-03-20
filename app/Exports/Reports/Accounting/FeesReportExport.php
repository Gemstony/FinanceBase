<?php

namespace App\Exports\Reports\Accounting;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FeesReportExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected $reportData;
    protected $dateFrom;
    protected $dateTo;
    protected $subshopName;

    public function __construct(array $reportData, string $dateFrom, string $dateTo, string $subshopName = 'All Branches')
    {
        $this->reportData = $reportData;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->subshopName = $subshopName;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $details = $this->reportData['details'] ?? collect();
        $metrics = $this->reportData['metrics'] ?? [];
        
        $data = [];
        
        // Add summary section
        $data[] = ['FEES REPORT - ' . $this->dateFrom . ' to ' . $this->dateTo];
        $data[] = ['Branch: ' . $this->subshopName];
        $data[] = [];
        
        // Add summary metrics
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Total Charged', number_format($metrics['total_charged'] ?? 0, 2)];
        $data[] = ['Total Paid', number_format($metrics['total_paid'] ?? 0, 2)];
        $data[] = ['Total Outstanding', number_format($metrics['total_outstanding'] ?? 0, 2)];
        $data[] = ['Collection Rate', number_format($metrics['collection_rate'] ?? 0, 1) . '%'];
        $data[] = ['Total Transactions', $metrics['total_transactions'] ?? 0];
        $data[] = ['Paid Count', $metrics['paid_count'] ?? 0];
        $data[] = ['Outstanding Count', $metrics['outstanding_count'] ?? 0];
        $data[] = [];
        
        // Add summary by fee type
        $summaryByFeeType = $this->reportData['summary_by_fee_type'] ?? collect();
        
        if ($summaryByFeeType->count() > 0) {
            $data[] = ['SUMMARY BY FEE TYPE'];
            $data[] = ['Fee Type', 'Charged', 'Paid', 'Outstanding', 'Collection %'];
            
            foreach ($summaryByFeeType as $fee) {
                $data[] = [
                    $fee->fee_name . ' (' . $fee->fee_code . ')',
                    number_format($fee->total_charged, 2),
                    number_format($fee->total_paid, 2),
                    number_format($fee->total_outstanding, 2),
                    $fee->collection_rate . '%'
                ];
            }
            
            $data[] = [
                'Total',
                number_format($metrics['total_charged'] ?? 0, 2),
                number_format($metrics['total_paid'] ?? 0, 2),
                number_format($metrics['total_outstanding'] ?? 0, 2),
                number_format($metrics['collection_rate'] ?? 0, 1) . '%'
            ];
        }
        
        $data[] = [];
        
        // Add detailed records
        $data[] = ['DETAILED FEE RECORDS'];
        $data[] = ['Date', 'Loan', 'Customer', 'Phone', 'Product', 'Fee Type', 'Fee Code', 'Charged', 'Paid', 'Outstanding', 'Status'];
        
        foreach ($details as $detail) {
            $data[] = [
                \Carbon\Carbon::parse($detail->applied_on)->format('d/m/Y'),
                $detail->loan_code ?? '',
                $detail->customer_name ?? '',
                $detail->customer_phone ?? '',
                $detail->loan_product_name ?? '',
                $detail->fee_name ?? '',
                $detail->fee_code ?? '',
                number_format($detail->amount, 2),
                number_format($detail->paid_amount, 2),
                number_format($detail->outstanding_amount, 2),
                $detail->outstanding_amount > 0 ? 'Outstanding' : 'Paid'
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
        return 'Fees Report';
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        $styles = [];
        
        // Style header rows
        $row = 1;
        foreach ($sheet->getRowIterator() as $rowNum => $row) {
            $cellValue = $sheet->getCell('A' . $rowNum)->getValue();
            
            // Main report title
            if ($rowNum === 1 && strpos($cellValue, 'FEES REPORT') !== false) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => 'center'],
                ];
                $sheet->mergeCells('A' . $rowNum . ':K' . $rowNum);
            }
            
            // Section headers
            if (in_array($cellValue, ['SUMMARY METRICS', 'SUMMARY BY FEE TYPE', 'DETAILED FEE RECORDS'])) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '17A2B8']],
                    'font' => ['color' => ['rgb' => 'FFFFFF']],
                ];
                $sheet->mergeCells('A' . $rowNum . ':K' . $rowNum);
            }
            
            // Table headers in detail section
            if (in_array($cellValue, ['Fee Type', 'Date', 'Fee Type', 'Charged', 'Paid', 'Outstanding', 'Collection %'])) {
                $styles['A' . $rowNum . ':K' . $rowNum] = [
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
                    'alignment' => ['horizontal' => 'center'],
                ];
            }
        }
        
        return $styles;
    }
}
