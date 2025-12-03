<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturns extends Model
{
    protected $table = 'purchase_returns';

    protected $fillable = [
        'subshop_id',
        'purchase_order_id',
        'purchase_order_item_id',
        'item_id',
        'quantity_returned',
        'unit_price',
        'base_amount',
        'vat_amount',
        'line_total',
        'reason',
        'processed_by',
        'transaction_id',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrdersItems::class, 'purchase_order_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PurchasesTransactions::class, 'transaction_id');
    }
}
