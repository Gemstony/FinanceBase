<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductFees extends Model
{
    protected $fillable = [
        'subshop_id',
        'loan_product_id',
        'loan_fee_id',
        'charge_event',
        'payment_method',
        'auto_apply',
        'max_applications',
        'is_waivable',
        'is_mandatory',
        'is_active',
    ];

    protected $casts = [
        'auto_apply' => 'boolean',
        'max_applications' => 'integer',
        'is_waivable' => 'boolean',
        'is_mandatory' => 'boolean',
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

    public function loanFee(): BelongsTo
    {
        return $this->belongsTo(LoanFees::class, 'loan_fee_id');
    }
}
