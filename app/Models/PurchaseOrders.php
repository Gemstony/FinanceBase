<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrders extends Model
{
    protected $table = 'purchase_orders';
    use SoftDeletes;

    protected $fillable = [
        'subshop_id',
        'supplier_id',
        'created_by',
        'order_no',
        'subtotal',
        'vat_total',
        'discount_percent',
        'discount_cash',
        'discount_total',
        'grand_total',
        'payment_method',
        'amount_paid',
        'change_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'vat_total' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_cash' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Suppliers::class, 'supplier_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrdersItems::class, 'purchase_order_id');
    }

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }
}
