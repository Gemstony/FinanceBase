<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanProductCashConfigs extends Model
{
    protected $fillable = [
        'subshop_id',
        'loan_product_id',
        'deposit_requirement',
        'deposit_value',
        'deposit_basis',
        'use_customer_savings',
        'lock_period_days',
        'allow_withdrawal_during_loan',
        'is_refundable',
        'apply_on_default',
        'is_active',
    ];

    protected $casts = [
        'deposit_value' => 'decimal:2',
        'use_customer_savings' => 'boolean',
        'lock_period_days' => 'integer',
        'allow_withdrawal_during_loan' => 'boolean',
        'is_refundable' => 'boolean',
        'apply_on_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProducts::class, 'loan_product_id');
    }
}
