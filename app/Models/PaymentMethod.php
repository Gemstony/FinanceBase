<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

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
