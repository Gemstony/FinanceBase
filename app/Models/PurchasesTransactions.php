<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasesTransactions extends Model
{
    protected $table = 'purchases_transactions';

    protected $fillable = [
        'supplier_id',
        'purchase_order_id',
        'created_by',
        'transaction_type',
        'payment_method',
        'total_amount',
        'transaction_date',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrders::class, 'purchase_order_id');
    }
}
