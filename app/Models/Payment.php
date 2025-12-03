<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'shop_id',
        'plan_id',
        'subscription_id',
        'amount',
        'currency',
        'payment_method_id',
        'transaction_id',
        'status',
        'payment_date',
        'notes',
        'metadata'
    ];
    
    protected $with = ['paymentMethod'];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the shop that owns this payment
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the plan associated with this payment
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the subscription associated with this payment
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
    
    /**
     * Get the payment method associated with the payment
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Check if payment is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled', 'refunded']);
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency ?? 'USD');
    }

    /**
     * Get payment method label
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        if ($this->paymentMethod) {
            return $this->paymentMethod->name;
        }
        
        // Fallback to old method if payment_method_id is not set
        return match($this->payment_method) {
            'card' => 'Credit/Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            'mpesa' => 'M-Pesa',
            'airtel_money' => 'Airtel Money',
            default => ucfirst(str_replace('_', ' ', $this->payment_method ?? 'Unknown'))
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'completed' => 'badge-success',
            'pending' => 'badge-warning',
            'failed' => 'badge-danger',
            'cancelled' => 'badge-secondary',
            'refunded' => 'badge-info',
            default => 'badge-secondary'
        };
    }
}
