<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanFeeApplications extends Model
{
    protected $table = 'loan_fee_applications';

    protected $fillable = [
        'loan_id',
        'loan_product_fee_id',
        'amount',
        'charge_event',
        'applied_on',
        'is_paid',
        'paid_amount',
        'payment_date',
        'payment_method',
        'payment_reference',
        'paid_by',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_on' => 'date',
        'is_paid' => 'boolean',
        'paid_amount' => 'decimal:2',
        'payment_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function loanProductFee()
    {
        return $this->belongsTo(LoanProductFees::class, 'loan_product_fee_id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
