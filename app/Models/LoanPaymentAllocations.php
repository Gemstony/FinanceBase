<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPaymentAllocations extends Model
{
    protected $table = 'loan_payment_allocations';

    protected $fillable = [
        'loan_payment_id',
        'loan_installment_id',
        'principal_amount',
        'interest_amount',
        'fee_amount',
        'penalty_amount',
    ];

    protected $casts = [
        'loan_payment_id' => 'integer',
        'loan_installment_id' => 'integer',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
    ];

    public function loanPayment()
    {
        return $this->belongsTo(LoanPayments::class, 'loan_payment_id');
    }

    public function loanInstallment()
    {
        return $this->belongsTo(LoanInstallments::class, 'loan_installment_id');
    }
}
