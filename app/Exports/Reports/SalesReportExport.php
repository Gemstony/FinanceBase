<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

abstract class SalesReportExport implements FromCollection, WithHeadings, WithTitle, WithMapping, ShouldAutoSize, WithStyles
{
    protected $data;
    protected $title;
    protected $headings;
    protected $dateFrom;
    protected $dateTo;
    protected $subshopName;

    public function __construct($data, $title, $headings, $dateFrom, $dateTo, $subshopName = null)
    {
        $this->data = $data;
        $this->title = $title;
        $this->headings = $headings;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->subshopName = $subshopName;
    }

    public function collection()
    {
        return $this->data;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4e73df']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Set title and info rows
        $sheet->insertNewRowBefore(1, 3);
        
        // Title
        $sheet->mergeCells('A1:'.$sheet->getHighestColumn().'1');
        $sheet->setCellValue('A1', $this->title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Date range
        $dateRange = 'Date Range: ' . $this->dateFrom . ' to ' . $this->dateTo;
        if ($this->subshopName) {
            $dateRange .= ' | Subshop: ' . $this->subshopName;
        }
        $sheet->mergeCells('A2:'.$sheet->getHighestColumn().'2');
        $sheet->setCellValue('A2', $dateRange);
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Generated at
        $sheet->mergeCells('A3:'.$sheet->getHighestColumn().'3');
        $sheet->setCellValue('A3', 'Generated at: ' . now()->format('Y-m-d H:i:s'));
        $sheet->getStyle('A3')->getFont()->setItalic(true);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Set column widths and auto size
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders to data
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();
        $dataRange = 'A4:' . $lastCol . $lastRow;
        
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Add alternating row colors
        $rowCount = count($this->data) + 1; // +1 for header
        for ($i = 4; $i <= $rowCount + 3; $i++) { // +3 for the title rows we added
            $color = $i % 2 == 0 ? 'FFFFFF' : 'F8F9FA';
            $sheet->getStyle('A' . $i . ':' . $lastCol . $i)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB($color);
        }

        // Make sure the header stays at the top when scrolling
        $sheet->freezePane('A5');
    }
}
