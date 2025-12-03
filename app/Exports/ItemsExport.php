<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;


class ItemsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $items;

    public function __construct($items)
    {
        $this->items = $items;
    }

    public function collection()
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'SKU',
            'Barcode',
            'Category',
            'Supplier',
            'Min Selling Price',
            'Max Selling Price',
            'Avg Cost Price',
            'Total Quantity',
            'Number of Batches',
            'Earliest Expiry',
            'Margin %',
            'Min Quantity',
            'Max Quantity',
            'Unit',
            'Status',
            'Created At'
        ];
    }

    public function map($item): array
    {
        $totalQuantity = $item->itemBatches->sum('quantity');
        $batchCount = $item->itemBatches->count();
        $minPrice = $item->itemBatches->min('selling_price');
        $maxPrice = $item->itemBatches->max('selling_price');
        $avgCostPrice = $item->itemBatches->avg('cost_price');
        $earliestExpiry = $item->itemBatches->whereNotNull('expire_date')->min('expire_date');
        $marginPercentage = $avgCostPrice && $avgCostPrice > 0
            ? (($item->price - $avgCostPrice) / $avgCostPrice) * 100
            : ($item->cost_price && $item->cost_price > 0 ? $item->margin_percentage : 0);

        return [
            $item->id,
            $item->name,
            $item->sku ?? '',
            $item->barcode ?? '',
            $item->category ? $item->category->name : '',
            $item->supplier ? $item->supplier->name : '',
            $minPrice ? number_format((float) $minPrice, 2) : number_format((float) $item->price, 2),
            $maxPrice ? number_format((float) $maxPrice, 2) : number_format((float) $item->price, 2),
            $avgCostPrice ? number_format((float) $avgCostPrice, 2) : ($item->cost_price ? number_format((float) $item->cost_price, 2) : ''),
            $totalQuantity,
            $batchCount,
            $earliestExpiry ? $earliestExpiry : '',
            number_format((float) $marginPercentage, 2),
            $item->min_quantity ?? '',
            $item->max_quantity ?? '',
            $item->unit ?? '',
            $item->is_active ? 'Active' : 'Inactive',
            $item->created_at->format('Y-m-d H:i:s')
        ];
    }
}
