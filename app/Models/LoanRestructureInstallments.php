<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanRestructureInstallments extends Model
{
    protected $table = 'loan_restructure_installments';

    protected $fillable = [
        'restructure_id',
        'installment_id',
        'installment_number',
        'old_due_date',
        'old_principal_due',
        'old_interest_due',
        'old_fees_due',
        'old_penalty_due',
        'principal_paid',
        'interest_paid',
        'fees_paid',
        'penalty_paid',
    ];

    protected $casts = [
        'restructure_id' => 'integer',
        'installment_id' => 'integer',
        'installment_number' => 'integer',
        'old_due_date' => 'date',
        'old_principal_due' => 'decimal:2',
        'old_interest_due' => 'decimal:2',
        'old_fees_due' => 'decimal:2',
        'old_penalty_due' => 'decimal:2',
        'principal_paid' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'fees_paid' => 'decimal:2',
        'penalty_paid' => 'decimal:2',
    ];

    public function restructure()
    {
        return $this->belongsTo(LoanRestructures::class, 'restructure_id');
    }

    public function installment()
    {
        return $this->belongsTo(LoanInstallments::class, 'installment_id');
    }
}
