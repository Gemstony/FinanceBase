<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrdersItems extends Model
{
    protected $table = 'purchase_orders_items';

    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'item_name',
        'unit',
        'unit_price',
        'quantity',
        'vat_amount',
        'base_amount',
        'line_total',
        'batch_number',
        'expire_date',
        'cost_price',
        'selling_price',
    ];

    protected $casts = [
        'expire_date' => 'date',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];
}
