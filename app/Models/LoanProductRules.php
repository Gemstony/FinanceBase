<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductRules extends Model
{
    protected $fillable = [
        'subshop_id',
        'loan_product_id',

        'min_age',
        'max_age',
        'min_membership_days',
        
        'requires_active_savings',
        'min_savings_balance',
        'loan_to_savings_ratio',

        'min_loan_amount',
        'max_loan_amount',
        'max_active_loans',

        'min_installments',
        'max_installments',
        'grace_period_days',

        'requires_security_deposit',

        'requires_collateral',
        'min_collateral_coverage_ratio',


        'min_interest_rate',
        'max_interest_rate',
        'allow_interest_override',

        'penalty_start_day',
        'auto_apply_penalty',

        'allow_top_up',
        'min_repayment_ratio_for_topup',
        'allow_restructure',

        'requires_guarantor',
        'manual_override_allowed',

        'is_active',
    ];

    protected $casts = [
        'min_age' => 'integer',
        'max_age' => 'integer',
        'min_membership_days' => 'integer',

        'requires_active_savings' => 'boolean',
        'min_savings_balance' => 'decimal:2',

        'min_loan_amount' => 'decimal:2',
        'max_loan_amount' => 'decimal:2',
        'loan_to_savings_ratio' => 'decimal:2',
        'max_active_loans' => 'integer',

        'min_installments' => 'integer',
        'max_installments' => 'integer',
        'grace_period_days' => 'integer',

        'requires_security_deposit' => 'boolean',

        'requires_collateral' => 'boolean',
        'min_collateral_coverage_ratio' => 'decimal:2',

        'min_interest_rate' => 'decimal:2',
        'max_interest_rate' => 'decimal:2',
        'allow_interest_override' => 'boolean',

        'penalty_start_day' => 'integer',
        'auto_apply_penalty' => 'boolean',

        'allow_top_up' => 'boolean',
        'min_repayment_ratio_for_topup' => 'decimal:2',
        'allow_restructure' => 'boolean',

        'requires_guarantor' => 'boolean',
        'manual_override_allowed' => 'boolean',

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
}
