<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReturnsExport implements WithMultipleSheets
{
    protected $returns;
    protected $dateFrom;
    protected $dateTo;
    protected $subshopName;

    public function __construct($returns, $dateFrom, $dateTo, $subshopName = null)
    {
        $this->returns = $returns;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->subshopName = $subshopName;
    }

    public function sheets(): array
    {
        $sheets = [];
        
        // Returns sheet
        $sheets[] = new class($this->returns, $this->dateFrom, $this->dateTo, $this->subshopName) extends SalesReportExport {
            public function map($return): array
            {
                $status = $return->processed_by ? 'Completed' : 'Pending';
                
                return [
                    $return->id,
                    $return->invoice_number,
                    $return->product_name,
                    $return->quantity_returned,
                    number_format($return->return_amount, 2),
                    $return->reason ?: 'No reason provided',
                    $status,
                    $return->created_at,
                    $return->processed_by_name ?: 'N/A',
                ];
            }

            public function title(): string
            {
                return 'Sales Returns';
            }

            public function headings(): array
            {
                return [
                    'Return #',
                    'Order #',
                    'Product',
                    'Qty Returned',
                    'Amount',
                    'Reason',
                    'Status',
                    'Date',
                    'Processed By',
                ];
            }
        };

        return $sheets;
    }
}
