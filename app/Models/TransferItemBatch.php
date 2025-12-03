<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferItemBatch extends Model
{
    protected $fillable = [
        'transfer_item_id',
        'source_item_batch_id',
        'destination_item_batch_id',
        'batch_number',
        'expire_date',
        'cost_price',
        'selling_price_snapshot',
        'planned_qty',
        'dispatched_qty',
        'received_qty',
        'damaged_qty',
        'remarks',
    ];

    protected $casts = [
        'expire_date' => 'date',
        'cost_price' => 'decimal:2',
        'selling_price_snapshot' => 'decimal:2',
        'planned_qty' => 'decimal:3',
        'dispatched_qty' => 'decimal:3',
        'received_qty' => 'decimal:3',
        'damaged_qty' => 'decimal:3',
    ];

    public function transferItem()
    {
        return $this->belongsTo(TransferItem::class);
    }
}
