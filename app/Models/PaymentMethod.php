<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'code',
        'description',
        'status',
        'is_repayment_method',
        'is_deposit_method',
        'is_refund_method',
        'is_withdrawal_method',
        'requires_bank_account',
        'requires_phone',
        'account_type',
        'sort_order',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_repayment_method' => 'boolean',
        'is_deposit_method' => 'boolean',
        'is_refund_method' => 'boolean',
        'is_withdrawal_method' => 'boolean',
        'requires_bank_account' => 'boolean',
        'requires_phone' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the shop that owns this payment method
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the payments for this payment method
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the subscriptions using this payment method
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }
}
