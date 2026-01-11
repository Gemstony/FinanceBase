<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanProducts extends Model
{
    protected $fillable = [
        'subshop_id',
        'name',
        'code',
        'description',
        'loan_type',
        'is_revolving',
        'interest_method_id',
        'interest_cycle_id',
        'repayment_frequency_id',
        'default_installments',
        'max_installments',
        'min_installments',
        'default_loan_amount',
        'supports_collateral',
        'requires_approval',
        'is_active',
        'is_visible',
    ];

    protected $casts = [
        'is_revolving' => 'boolean',
        'default_installments' => 'integer',
        'max_installments' => 'integer',
        'min_installments' => 'integer',
        'default_loan_amount' => 'decimal:2',
        'supports_collateral' => 'boolean',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
    ];

    // Relationships
    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function interestMethod()
    {
        return $this->belongsTo(InterestMethods::class, 'interest_method_id');
    }

    public function interestCycle()
    {
        return $this->belongsTo(InterestCycles::class, 'interest_cycle_id');
    }

    public function repaymentFrequency()
    {
        return $this->belongsTo(RepaymentFrequencies::class, 'repayment_frequency_id');
    }

    public function rules()
    {
        return $this->hasOne(LoanProductRules::class, 'loan_product_id');
    }

    public function fees()
    {
        return $this->hasMany(LoanProductFees::class, 'loan_product_id');
    }

    public function penalties()
    {
        return $this->hasMany(LoanProductPenalties::class, 'loan_product_id');
    }

    public function accounts()
    {
        return $this->hasOne(LoanProductAccounts::class, 'loan_product_id');
    }

    public function cashConfigs()
    {
        return $this->hasOne(LoanProductCashConfigs::class, 'loan_product_id');
    }

    public function approvalLevels()
    {
        return $this->hasMany(LoanProductApprovalLevels::class, 'loan_product_id');
    }
}
