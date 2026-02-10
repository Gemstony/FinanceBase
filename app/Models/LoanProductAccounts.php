<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductAccounts extends Model
{
    protected $fillable = [
        'subshop_id',
        'loan_product_id',
        'principal_account_id',
        'customer_savings_control_account_id',
        'security_deposit_control_account_id',
        'interest_receivable_account_id',
        'interest_income_account_id',
        'penalty_receivable_account_id',
        'penalty_income_account_id',
        'fee_income_account_id',
        'customer_savings_account_id',
        'customer_security_deposit_account_id',
        'write_off_expense_account_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProducts::class, 'loan_product_id');
    }

    public function principalAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'principal_account_id');
    }

    public function interestReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'interest_receivable_account_id');
    }

    public function interestIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'interest_income_account_id');
    }

    public function penaltyReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'penalty_receivable_account_id');
    }

    public function penaltyIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'penalty_income_account_id');
    }

    public function feeIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'fee_income_account_id');
    }

    public function writeOffExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'write_off_expense_account_id');
    }
}
