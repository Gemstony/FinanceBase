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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_on' => 'date',
        'is_paid' => 'boolean',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function loanProductFee()
    {
        return $this->belongsTo(LoanProductFees::class, 'loan_product_fee_id');
    }
}
