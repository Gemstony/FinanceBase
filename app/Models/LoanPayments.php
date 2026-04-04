<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPayments extends Model
{
    protected $table = 'loan_payments';

    protected $fillable = [
        'loan_id',
        'customer_id',
        'user_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_number',
        'notes',
        'status',
        'external_id',
        'phone',
        'provider',
        'transaction_reference',
    ];

    protected $casts = [
        'loan_id' => 'integer',
        'customer_id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function allocations()
    {
        return $this->hasMany(LoanPaymentAllocations::class, 'loan_payment_id');
    }
}
