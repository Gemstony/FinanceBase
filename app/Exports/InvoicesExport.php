<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoicesExport implements FromCollection, WithHeadings, WithMapping
{
    protected Collection $rows;

    public function __construct($rows)
    {
        // Accept array or Collection
        $this->rows = $rows instanceof Collection ? $rows : collect($rows);
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Order No',
            'Date',
            'Customer',
            'Items',
            'Subtotal',
            'VAT',
            'Discount',
            'Grand',
            'Paid',
            'Remaining',
            'Status',
            'Cashier',
            'Notes',
        ];
    }

    public function map($o): array
    {
        $paid = (float)($o->paid_total ?? 0);
        $remain = max(0, (float)$o->grand_total - $paid);
        $status = $remain <= 0 ? 'PAID' : ($paid <= 0 ? 'PENDING' : 'PARTIAL');
        return [
            $o->order_no,
            optional($o->created_at)->format('Y-m-d H:i:s'),
            optional($o->customer)->name ?? '-',
            method_exists($o, 'items') && $o->relationLoaded('items') ? $o->items->sum('quantity') : ($o->items_sum_quantity ?? ''),
            (float)$o->subtotal,
            (float)$o->vat_total,
            (float)$o->discount_total,
            (float)$o->grand_total,
            (float)$paid,
            (float)$remain,
            $status,
            optional($o->creator)->name ?? '-',
            $o->notes,
        ];
    }
}
