<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'shop_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
        'auto_renew',
        'payment_method',
        'last_payment_date',
        'next_payment_date',
        'cancellation_date',
        'cancellation_reason',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'last_payment_date' => 'datetime',
        'next_payment_date' => 'datetime',
        'cancellation_date' => 'datetime',
        'auto_renew' => 'boolean'
    ];

    /**
     * Get the shop that owns this subscription
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the plan associated with this subscription
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the payments for this subscription
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && (!$this->end_date || $this->end_date->isFuture());
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== 'cancelled';
    }

    /**
     * Check if subscription is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if subscription is about to expire (within 10 days)
     */
    public function isExpiringSoon(): bool
    {
        return $this->end_date &&
               $this->end_date->isFuture() &&
               $this->end_date->diffInDays(now()) <= 10 &&
               $this->status === 'active';
    }

    /**
     * Get subscription status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'active' => 'badge-success',
            'expired' => 'badge-danger',
            'cancelled' => 'badge-secondary',
            'suspended' => 'badge-warning',
            'trial' => 'badge-info',
            default => 'badge-secondary'
        };
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        return $this->end_date ? now()->diffInDays($this->end_date, false) : null;
    }

    /**
     * Get formatted price from plan
     */
    public function getFormattedPriceAttribute(): string
    {
        return $this->plan ? $this->plan->formatted_price : 'N/A';
    }

    /**
     * Get subscription period
     */
    public function getSubscriptionPeriodAttribute(): string
    {
        if (!$this->start_date || !$this->end_date) {
            return 'N/A';
        }

        return $this->start_date->format('M j, Y') . ' - ' . $this->end_date->format('M j, Y');
    }

    /**
     * Cancel the subscription
     */
    public function cancel(?string $reason = null): bool
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_date' => now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false
        ]);

        return true;
    }

    /**
     * Renew the subscription
     */
    public function renew(): bool
    {
        if (!$this->plan) {
            return false;
        }

        $newEndDate = $this->end_date ?? now();

        // Calculate next billing cycle
        switch ($this->plan->billing_cycle) {
            case 'monthly':
                $newEndDate = $newEndDate->addMonth();
                break;
            case 'yearly':
                $newEndDate = $newEndDate->addYear();
                break;
            default:
                return false; // One-time plans can't be renewed
        }

        $this->update([
            'end_date' => $newEndDate,
            'status' => 'active',
            'last_payment_date' => now()
        ]);

        return true;
    }
}
