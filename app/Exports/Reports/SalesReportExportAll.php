<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesReportExportAll implements WithMultipleSheets
{
    protected $orders;
    protected $products;
    protected $returns;
    protected $dateFrom;
    protected $dateTo;
    protected $subshopName;
    protected $kpi;
    protected $totals;

    public function __construct($orders, $products, $returns, $kpi, $dateFrom, $dateTo, $subshopName = null)
    {
        $this->orders = $orders;
        $this->products = $products;
        $this->returns = $returns;
        $this->kpi = $kpi;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->subshopName = $subshopName;
        
        // Use the KPIs passed from the controller instead of recalculating
        // This ensures consistency between the web view and the export
        $this->totals = [
            'net_sales' => $kpi['net_sales'] ?? 0,
            'gross_profit' => $kpi['gross_profit'] ?? 0,
            'returns_amount' => $kpi['returns_amount'] ?? 0,
            'total_orders' => $kpi['orders'] ?? 0,
            'total_units' => $kpi['units'] ?? 0,
            'avg_order_value' => $kpi['aov'] ?? 0,
            'margin_pct' => $kpi['margin_pct'] ?? 0
        ];
    }

    public function sheets(): array
    {
        $sheets = [];
        
        // Add Summary sheet
        $sheets[] = new class($this->totals, $this->dateFrom, $this->dateTo, $this->subshopName) extends SalesReportExport {
            protected $totals;
            
            public function __construct($totals, $dateFrom, $dateTo, $subshopName)
            {
                $this->totals = $totals;
                parent::__construct(collect([]), 'Summary', [], $dateFrom, $dateTo, $subshopName);
            }
            
            public function collection()
            {
                return collect([
                    ['Net Sales', $this->totals['net_sales']],
                    ['Number of Orders', $this->totals['total_orders']],
                    ['Average Order Value', $this->totals['avg_order_value']],
                    ['Units Sold', $this->totals['total_units']],
                    ['Gross Profit', $this->totals['gross_profit']],
                    ['Margin %', $this->totals['margin_pct']],
                    ['Returns Amount', $this->totals['returns_amount']],
                ]);
            }
            
            public function map($row): array
            {
                return $row;
            }
            
            public function title(): string
            {
                return 'Summary';
            }
            
            public function headings(): array
            {
                return ['Metric', 'Value'];
            }
            
            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
            {
                parent::styles($sheet);
                
                // Adjust column widths
                $sheet->getColumnDimension('A')->setWidth(30);
                $sheet->getColumnDimension('B')->setWidth(20);
                
                // Format numbers
                $sheet->getStyle('B2:B8')
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
        };
        
        // Add Orders sheet with proper data
        $sheets[] = new OrdersExport(
            $this->orders->map(function($order) {
                return (object)[
                    'invoice_number' => $order->invoice_number,
                    'created_at' => $order->created_at,
                    'customer_name' => $order->customer_name,
                    'grand_total' => $order->grand_total,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status
                ];
            }),
            $this->dateFrom,
            $this->dateTo,
            $this->subshopName
        );
        
        // Add Product Performance sheet with proper data
        $sheets[] = new ProductPerformanceExport(
            $this->products->map(function($product) {
                return (object)[
                    'product_name' => $product->product_name,
                    'category_name' => $product->category_name ?? 'Uncategorized',
                    'quantity_sold' => $product->quantity_sold,
                    'revenue' => $product->revenue,
                    'profit' => $product->profit ?? 0,
                    'cogs' => $product->cogs ?? 0
                ];
            }),
            $this->dateFrom,
            $this->dateTo,
            $this->subshopName
        );
        
        // Add Returns sheet with proper data
        $sheets[] = new ReturnsExport(
            $this->returns->map(function($return) {
                return (object)[
                    'id' => $return->id,
                    'invoice_number' => $return->invoice_number,
                    'product_name' => $return->product_name,
                    'quantity_returned' => $return->quantity_returned,
                    'return_amount' => $return->return_amount,
                    'processed_by' => $return->processed_by
                ];
            }),
            $this->dateFrom,
            $this->dateTo,
            $this->subshopName
        );
        
        return $sheets;
    }
}
