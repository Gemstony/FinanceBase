<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrdersItems extends Model
{
    protected $table = 'sales_orders_items';

    protected $fillable = [
        'sales_order_id',
        'item_id',
        'item_name',
        'unit',
        'unit_price',
        'quantity',
        'vat_type',
        'vat_rate',
        'vat_amount',
        'base_amount',
        'line_total',
        'batch_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrders::class, 'sales_order_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }
}
