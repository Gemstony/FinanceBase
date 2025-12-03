<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_cycle', // monthly, yearly
        'status', // active, inactive, archived
        'features',
        'limits',
        'is_popular',
        'sort_order'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'limits' => 'array',
        'is_popular' => 'boolean'
    ];

    /**
     * Get the subscriptions for this plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the payments for this plan
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get active subscriptions for this plan
     */
    public function activeSubscriptions()
    {
        return $this->subscriptions()->where('status', 'active');
    }

    /**
     * Check if plan is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2) . ' ' . strtoupper($this->currency ?? 'USD');
    }

    /**
     * Get billing cycle label
     */
    public function getBillingCycleLabelAttribute(): string
    {
        return match($this->billing_cycle) {
            'monthly' => 'Monthly',
            '2_months' => '2 Months',
            '3_months' => '3 Months',
            '6_months' => '6 Months',
            'yearly' => 'Yearly',
            'one_time' => 'One-time',
            default => 'Unknown'
        };
    }

    /**
     * Calculate subscription end date based on billing cycle
     */
    public function calculateEndDate(\Carbon\Carbon $startDate = null): \Carbon\Carbon
    {
        $startDate = $startDate ?: now();

        return match($this->billing_cycle) {
            'monthly' => $startDate->copy()->addMonth(),
            '2_months' => $startDate->copy()->addMonths(2),
            '3_months' => $startDate->copy()->addMonths(3),
            '6_months' => $startDate->copy()->addMonths(6),
            'yearly' => $startDate->copy()->addYear(),
            'one_time' => $startDate->copy()->addCentury(), // Effectively never expires
            default => $startDate->copy()->addMonth()
        };
    }
}
