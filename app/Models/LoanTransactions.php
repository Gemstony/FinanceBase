<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanTransactions extends Model
{
    protected $table = 'loan_transactions';

    protected $fillable = [
        'loan_id',
        'transaction_type',
        'transaction_date',
        'amount',
        'principal_amount',
        'interest_amount',
        'penalty_amount',
        'fee_amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'loan_id' => 'integer',
        'amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'reference_id' => 'integer',
        'transaction_date' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }
}
