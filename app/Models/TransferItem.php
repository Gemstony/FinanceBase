<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferItem extends Model
{
    protected $fillable = [
        'transfer_id',
        'source_item_id',
        'destination_item_id',
        'planned_qty',
        'dispatched_qty',
        'received_qty',
        'uom',
        'item_name_snapshot',
        'sku_snapshot',
    ];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function batches()
    {
        return $this->hasMany(TransferItemBatch::class);
    }
}
