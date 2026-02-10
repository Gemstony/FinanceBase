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
}
