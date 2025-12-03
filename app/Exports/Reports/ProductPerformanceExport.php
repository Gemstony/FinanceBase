<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductPerformanceExport implements WithMultipleSheets
{
    protected $products;
    protected $dateFrom;
    protected $dateTo;
    protected $subshopName;

    public function __construct($products, $dateFrom, $dateTo, $subshopName = null)
    {
        $this->products = $products;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->subshopName = $subshopName;
    }

    public function sheets(): array
    {
        $sheets = [];
        
        // Product Performance sheet
        $sheets[] = new class($this->products, $this->dateFrom, $this->dateTo, $this->subshopName) extends SalesReportExport {
            public function map($product): array
            {
                $profit = $product->profit ?? ($product->revenue - $product->cogs);
                
                return [
                    $product->product_name,
                    $product->sku,
                    $product->category_name ?: 'Uncategorized',
                    number_format($product->quantity_sold),
                    number_format($product->revenue, 2),
                    number_format($product->cogs ?? 0, 2),
                    number_format($profit, 2),
                    $product->revenue > 0 ? number_format(($profit / $product->revenue) * 100, 2) . '%' : '0.00%',
                ];
            }

            public function title(): string
            {
                return 'Product Performance';
            }

            public function headings(): array
            {
                return [
                    'Product Name',
                    'SKU',
                    'Category',
                    'Quantity Sold',
                    'Revenue',
                    'COGS',
                    'Profit',
                    'Margin %',
                ];
            }
        };

        return $sheets;
    }
}
