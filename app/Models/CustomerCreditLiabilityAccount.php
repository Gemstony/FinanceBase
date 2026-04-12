<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCreditLiabilityAccount extends Model
{
    protected $fillable = [
        'subshop_id',
        'chart_of_account_id',
        'notes',
    ];

    /**
     * Get the subshop that owns the liability account configuration.
     */
    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class);
    }

    /**
     * Get the chart of account for this liability account.
     */
    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class);
    }

    /**
     * Get liability account for a specific subshop
     */
    public static function forSubshop(int $subshopId): ?self
    {
        return static::where('subshop_id', $subshopId)->first();
    }

    /**
     * Check if subshop has liability account configured
     */
    public static function isConfiguredForSubshop(int $subshopId): bool
    {
        return static::where('subshop_id', $subshopId)->exists();
    }
}
