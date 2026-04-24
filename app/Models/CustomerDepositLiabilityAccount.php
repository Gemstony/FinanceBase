<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDepositLiabilityAccount extends Model
{
    protected $table = 'customer_deposit_liability_accounts';

    protected $fillable = [
        'shop_id',
        'chart_of_account_id',
        'notes',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'chart_of_account_id' => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @deprecated Use shop() instead
     */
    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'chart_of_account_id');
    }

    /**
     * Get liability account for a specific shop
     */
    public static function forShop(int $shopId): ?self
    {
        return static::query()
            ->where('shop_id', $shopId)
            ->with('chartOfAccount.accountClass')
            ->first();
    }

    /**
     * Check if shop has liability account configured
     */
    public static function existsForShop(int $shopId): bool
    {
        return static::query()->where('shop_id', $shopId)->exists();
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
     * @deprecated Use existsForShop() instead
     */
    public static function existsForSubshop(int $subshopId): bool
    {
        $subshop = SubShop::find($subshopId);
        if (!$subshop) {
            return false;
        }
        return static::existsForShop($subshop->shop_id);
    }
}
