<?php

namespace App\Exports\Reports\Accounting;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PenaltiesReportExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
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
        $summaryByPenaltyType = $this->reportData['summary_by_penalty_type'] ?? collect();
        $agingAnalysis = $this->reportData['aging_analysis'] ?? [];
        
        $data = [];
        
        // Add summary section
        $data[] = ['PENALTIES REPORT - ' . $this->dateFrom . ' to ' . $this->dateTo];
        $data[] = ['Branch: ' . $this->subshopName];
        $data[] = [];
        
        // Add summary metrics
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Total Applied', number_format($metrics['total_applied'] ?? 0, 2)];
        $data[] = ['Total Paid', number_format($metrics['total_paid'] ?? 0, 2)];
        $data[] = ['Total Outstanding', number_format($metrics['total_outstanding'] ?? 0, 2)];
        $data[] = ['Collection Rate', number_format($metrics['collection_rate'] ?? 0, 1) . '%'];
        $data[] = ['Total Transactions', $metrics['total_transactions'] ?? 0];
        $data[] = ['Paid Count', $metrics['paid_count'] ?? 0];
        $data[] = ['Outstanding Count', $metrics['outstanding_count'] ?? 0];
        $data[] = [];
        
        // Add summary by penalty type
        if ($summaryByPenaltyType->count() > 0) {
            $data[] = ['SUMMARY BY PENALTY TYPE'];
            $data[] = ['Penalty Type', 'Applied', 'Paid', 'Outstanding', 'Collection %'];
            
            foreach ($summaryByPenaltyType as $penalty) {
                $data[] = [
                    $penalty->penalty_name . ' (' . $penalty->penalty_code . ')',
                    number_format($penalty->total_applied, 2),
                    number_format($penalty->total_paid, 2),
                    number_format($penalty->total_outstanding, 2),
                    $penalty->collection_rate . '%'
                ];
            }
            
            $data[] = [
                'Total',
                number_format($metrics['total_applied'] ?? 0, 2),
                number_format($metrics['total_paid'] ?? 0, 2),
                number_format($metrics['total_outstanding'] ?? 0, 2),
                number_format($metrics['collection_rate'] ?? 0, 1) . '%'
            ];
        }
        
        $data[] = [];
        
        // Add aging analysis
        if (!empty($agingAnalysis)) {
            $data[] = ['AGING ANALYSIS'];
            $data[] = ['Aging Bucket', 'Outstanding Amount'];
            $data[] = ['0-30 Days', number_format($agingAnalysis['0-30_days'] ?? 0, 2)];
            $data[] = ['31-60 Days', number_format($agingAnalysis['31-60_days'] ?? 0, 2)];
            $data[] = ['61-90 Days', number_format($agingAnalysis['61-90_days'] ?? 0, 2)];
            $data[] = ['90+ Days', number_format($agingAnalysis['90+_days'] ?? 0, 2)];
            $data[] = [];
        }
        
        // Add detailed records
        $data[] = ['DETAILED PENALTY RECORDS'];
        $data[] = ['Date', 'Loan', 'Customer', 'Phone', 'Product', 'Penalty Type', 'Penalty Code', 'Applied', 'Paid', 'Outstanding', 'Days Past Due', 'Status'];
        
        foreach ($details as $detail) {
            $data[] = [
                \Carbon\Carbon::parse($detail->applied_on)->format('d/m/Y'),
                $detail->loan_code ?? '',
                $detail->customer_name ?? '',
                $detail->customer_phone ?? '',
                $detail->loan_product_name ?? '',
                $detail->penalty_name ?? '',
                $detail->penalty_code ?? '',
                number_format($detail->amount, 2),
                number_format($detail->paid_amount, 2),
                number_format($detail->outstanding_amount, 2),
                $detail->days_past_due ?? 0,
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
        return 'Penalties Report';
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
            if ($rowNum === 1 && strpos($cellValue, 'PENALTIES REPORT') !== false) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => 'center'],
                ];
                $sheet->mergeCells('A' . $rowNum . ':M' . $rowNum);
            }
            
            // Section headers
            if (in_array($cellValue, ['SUMMARY METRICS', 'SUMMARY BY PENALTY TYPE', 'AGING ANALYSIS', 'DETAILED PENALTY RECORDS'])) {
                $styles['A' . $rowNum] = [
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC3545']],
                    'font' => ['color' => ['rgb' => 'FFFFFF']],
                ];
                $sheet->mergeCells('A' . $rowNum . ':M' . $rowNum);
            }
            
            // Table headers in detail section
            if (in_array($cellValue, ['Penalty Type', 'Date', 'Penalty Type', 'Applied', 'Paid', 'Outstanding', 'Days Past Due', 'Collection %'])) {
                $styles['A' . $rowNum . ':M' . $rowNum] = [
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
                    'alignment' => ['horizontal' => 'center'],
                ];
            }
        }
        
        return $styles;
    }
}