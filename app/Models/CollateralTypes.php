<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollateralTypes extends Model
{
    protected $fillable = [
        'subshop_id',
        'name',
        'code',
        'description',
        'requires_valuation',
        'default_ltv_ratio',
        'depreciates',
        'revaluation_interval_days',
        'requires_ownership_proof',
        'requires_insurance',
        'allow_multiple_per_loan',
        'is_active',
    ];

    protected $casts = [
        'requires_valuation' => 'boolean',
        'default_ltv_ratio' => 'decimal:2',
        'depreciates' => 'boolean',
        'revaluation_interval_days' => 'integer',
        'requires_ownership_proof' => 'boolean',
        'requires_insurance' => 'boolean',
        'allow_multiple_per_loan' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }
}
