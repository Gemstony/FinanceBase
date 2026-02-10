<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductPenalties extends Model
{
    protected $fillable = [
         'subshop_id',
        'loan_product_id',
        'loan_penalty_id',
        'grace_days_override',
        'auto_apply',
        'max_applications',
        'is_active',
    ];

    protected $casts = [
        'grace_days_override' => 'integer',
        'auto_apply' => 'boolean',
        'max_applications' => 'integer',
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

    public function loanPenalty(): BelongsTo
    {
        return $this->belongsTo(LoanPenalties::class, 'loan_penalty_id');
    }
}
