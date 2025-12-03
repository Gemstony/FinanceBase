<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrders extends Model
{
    protected $table = 'sales_orders';

    protected $fillable = [
        'subshop_id',
        'customer_id',
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

        public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id')->withDefault([
            'name' => 'No Customer'
        ]);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault([
            'name' => 'System'
        ]);
    }
    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id')->withDefault([
            'name' => 'Shop'
        ]);
    }
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrdersItems::class, 'sales_order_id');
    }
}
