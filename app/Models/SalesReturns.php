<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturns extends Model
{
    protected $table = 'sales_returns';

    protected $fillable = [
        'subshop_id',
        'sales_order_id',
        'sales_order_item_id',
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

    // Relationships
    public function subshop()
    {
        return $this->belongsTo(SubShop::class);
    }

    public function order()
    {
        return $this->belongsTo(SalesOrders::class, 'sales_order_id');
    }

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrdersItems::class, 'sales_order_item_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
