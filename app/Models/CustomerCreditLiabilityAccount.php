<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCreditLiabilityAccount extends Model
{
    protected $fillable = [
        'shop_id',
        'chart_of_account_id',
        'notes',
    ];

    /**
     * Get the shop that owns the liability account configuration.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the subshop that owns the liability account configuration (deprecated - use shop).
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
     * Get liability account for a specific shop
     */
    public static function forShop(int $shopId): ?self
    {
        return static::where('shop_id', $shopId)->first();
    }

    /**
     * Check if shop has liability account configured
     */
    public static function isConfiguredForShop(int $shopId): bool
    {
        return static::where('shop_id', $shopId)->exists();
    }

    /**
     * Get liability account for a specific subshop (uses parent shop)
     * @deprecated Use forShop() instead
     */
    public static function forSubshop(int $subshopId): ?self
    {
        $subshop = SubShop::find($subshopId);
        if (!$subshop) {
            return null;
        }
        return static::forShop($subshop->shop_id);
    }

    /**
     * Check if subshop has liability account configured (uses parent shop)
     * @deprecated Use isConfiguredForShop() instead
     */
    public static function isConfiguredForSubshop(int $subshopId): bool
    {
        $subshop = SubShop::find($subshopId);
        if (!$subshop) {
            return false;
        }
        return static::isConfiguredForShop($subshop->shop_id);
    }
}
