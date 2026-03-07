<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanInstallments extends Model
{
    use SoftDeletes;

    protected $table = 'loan_installments';

    protected $fillable = [
        'loan_id',
        'subshop_id',
        'installment_number',
        'principal_due',
        'interest_due',
        'fees_due',
        'penalty_due',
        'principal_paid',
        'interest_paid',
        'fees_paid',
        'penalty_paid',
        'total_due',
        'amount_paid',
        'outstanding_amount',
        'due_date',
        'paid_date',
        'status',
        'is_active',
        'principal_account_id',
        'interest_income_account_id',
        'penalty_income_account_id',
        'fee_income_account_id',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'principal_due' => 'decimal:2',
        'interest_due' => 'decimal:2',
        'fees_due' => 'decimal:2',
        'penalty_due' => 'decimal:2',
        'principal_paid' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'fees_paid' => 'decimal:2',
        'penalty_paid' => 'decimal:2',
        'total_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function principalAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'principal_account_id');
    }

    public function interestIncomeAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'interest_income_account_id');
    }

    public function penaltyIncomeAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'penalty_income_account_id');
    }

    public function feeIncomeAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'fee_income_account_id');
    }

    // Accessors for component outstanding amounts
    public function getPrincipalOutstandingAttribute(): float
    {
        return max(0.0, (float) $this->principal_due - (float) $this->principal_paid);
    }

    public function getInterestOutstandingAttribute(): float
    {
        return max(0.0, (float) $this->interest_due - (float) $this->interest_paid);
    }

    public function getFeesOutstandingAttribute(): float
    {
        return max(0.0, (float) $this->fees_due - (float) $this->fees_paid);
    }

    public function getPenaltyOutstandingAttribute(): float
    {
        return max(0.0, (float) $this->penalty_due - (float) $this->penalty_paid);
    }

    public function getTotalOutstandingAttribute(): float
    {
        return round(
            $this->principal_outstanding
            + $this->interest_outstanding
            + $this->fees_outstanding
            + $this->penalty_outstanding,
            2
        );
    }
}
