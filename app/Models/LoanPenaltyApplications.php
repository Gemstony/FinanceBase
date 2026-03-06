<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPenaltyApplications extends Model
{
    protected $table = 'loan_penalty_applications';

    protected $fillable = [
        'loan_id',
        'loan_product_penalty_id',
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

    public function loanProductPenalty()
    {
        return $this->belongsTo(LoanProductPenalties::class, 'loan_product_penalty_id');
    }
}
