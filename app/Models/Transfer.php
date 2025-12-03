<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'shop_id',
        'source_subshop_id',
        'destination_subshop_id',
        'status',
        'requested_by_id',
        'approved_by_id',
        'dispatched_by_id',
        'received_by_id',
        'notes',
    ];

    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }

    public function sourceSubshop()
    {
        return $this->belongsTo(SubShop::class, 'source_subshop_id');
    }

    public function destinationSubshop()
    {
        return $this->belongsTo(SubShop::class, 'destination_subshop_id');
    }

    public function audits()
    {
        return $this->hasMany(TransferAudit::class);
    }
}
