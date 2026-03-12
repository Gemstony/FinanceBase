<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'short_name',
        'registration_number',
        'license_number',
        'tin',
        'website',
        'country',
        'region',
        'district',
        'street',
        'currency',
        'logo',
        'email',
        'phone',
        'address',
        'description',
        'is_active',
        'status',
        'suspension_reason',
        'max_subshops',
    ];

    protected static function booted(): void
    {
        static::created(function (self $shop) {
            if (!empty($shop->registration_number)) {
                return;
            }

            $shop->registration_number = 'FIN-' . str_pad((string) $shop->id, 6, '0', STR_PAD_LEFT);
            $shop->saveQuietly();
        });
    }

    protected $casts = [
        'is_active' => 'boolean',
        'max_subshops' => 'integer',
        'suspended_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    // Relationship with subshops
    public function subShops()
    {
        return $this->hasMany(SubShop::class);
    }

    /**
     * Get the user that owns the shop.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owner of this shop
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all shopkeepers (users assigned to subshops)
     */
    public function shopkeepers()
    {
        return User::whereHas('subshops', function ($query) {
            $query->where('sub_shops.shop_id', $this->id);
        })->distinct();
    }

    /**
     * Get the subscriptions for this shop
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the payments for this shop
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the active subscription for this shop
     */
    public function activeSubscription()
    {
        return $this->subscriptions()->where('status', 'active')->first();
    }

    /**
     * Get the current plan for this shop
     */
    public function currentPlan()
    {
        return $this->activeSubscription()?->plan;
    }

    /**
     * Check if shop has an active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    /**
     * Check if shop is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if shop is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->is_active;
    }

    /**
     * Check if shop is in trial
     */
    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    /**
     * Suspend the shop
     */
    public function suspend(string $reason = null): bool
    {
        return $this->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $reason ?: 'Subscription expired and grace period exceeded',
            'is_active' => false
        ]);
    }

    /**
     * Reactivate the shop
     */
    public function reactivate(): bool
    {
        return $this->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
            'activated_at' => now(),
            'is_active' => true
        ]);
    }

    /**
     * Get total payments made by this shop
     */
    public function totalPayments()
    {
        return $this->payments()->whereIn('status', ['completed', 'partial'])->sum('amount');
    }
}
