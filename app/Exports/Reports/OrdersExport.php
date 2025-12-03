<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OrdersExport implements WithMultipleSheets
{
    protected $orders;
    protected $dateFrom;
    protected $dateTo;
    protected $subshopName;

    public function __construct($orders, $dateFrom, $dateTo, $subshopName = null)
    {
        $this->orders = $orders;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->subshopName = $subshopName;
    }

    public function sheets(): array
    {
        $sheets = [];
        
        // Orders sheet
        $sheets[] = new class($this->orders, $this->dateFrom, $this->dateTo, $this->subshopName) extends SalesReportExport {
            public function map($order): array
            {
                return [
                    $order->invoice_number,
                    $order->created_at,
                    $order->customer_name,
                    $order->subshop_name ?: 'N/A',
                    number_format($order->grand_total, 2),
                    ucfirst($order->status),
                    ucfirst($order->payment_status),
                    $order->cashier_name ?: 'System',
                ];
            }

            public function title(): string
            {
                return 'Sales Orders';
            }

            public function headings(): array
            {
                return [
                    'Order #',
                    'Date',
                    'Customer',
                    'Subshop',
                    'Amount',
                    'Status',
                    'Payment',
                    'Cashier',
                ];
            }
        };

        return $sheets;
    }
}
