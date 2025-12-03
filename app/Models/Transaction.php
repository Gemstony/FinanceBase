<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'customer_id',
        'order_id',
        'created_by',
        'transaction_type',
        'payment_method',
        'total_amount',
        'transaction_date',
        'reference_number',
        'notes'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function order()
    {
        return $this->belongsTo(SalesOrders::class, 'order_id');
    }
}
