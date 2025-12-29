<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepaymentFrequencies extends Model
{
    protected $fillable = [
        'name',
        'code',
        'interval_days',
        'is_month_based',
        'max_installments',
        'min_installments',
        'is_active',
        'subshop_id',
    ];

    protected $casts = [
        'interval_days' => 'integer',
        'is_month_based' => 'boolean',
        'max_installments' => 'integer',
        'min_installments' => 'integer',
        'is_active' => 'boolean',
        'subshop_id' => 'integer',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(Subshop::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySubshop($query, $subshopId)
    {
        return $query->where('subshop_id', $subshopId);
    }
}
