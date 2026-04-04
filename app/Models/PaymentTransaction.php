<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'subshop_id',
        'customer_id',
        'loan_id',
        'reference',
        'provider',
        'aggregator',
        'channel_provider',
        'channel',
        'amount',
        'fee_amount',
        'net_amount',
        'phone',
        'status',
        'external_id',
        'provider_response',
        'meta',
        'initiated_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'meta' => 'array',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->reference)) {
                $transaction->reference = static::generateReference();
            }
            if (empty($transaction->initiated_at)) {
                $transaction->initiated_at = now();
            }
        });
    }

    /**
     * Generate a unique transaction reference.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'PAY-'.strtoupper(Str::random(10));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Get the shop that owns the transaction.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the subshop that owns the transaction.
     */
    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class);
    }

    /**
     * Get the customer that owns the transaction.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customers::class);
    }

    /**
     * Get the loan that owns the transaction.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loans::class);
    }

    /**
     * Get the logs for the transaction.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(PaymentLog::class, 'transaction_id');
    }

    /**
     * Scope to filter by shop.
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope to filter by subshop.
     */
    public function scopeForSubshop($query, ?int $subshopId)
    {
        return $subshopId ? $query->where('subshop_id', $subshopId) : $query;
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter by channel.
     */
    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by customer.
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to filter by loan.
     */
    public function scopeForLoan($query, int $loanId)
    {
        return $query->where('loan_id', $loanId);
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['initiated', 'pending']);
    }

    /**
     * Check if transaction is successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if transaction is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if transaction is reversed.
     */
    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    /**
     * Mark transaction as pending.
     */
    public function markAsPending(): void
    {
        $this->update(['status' => 'pending']);
    }

    /**
     * Mark transaction as successful.
     */
    public function markAsSuccess(?string $externalId = null, ?string $providerResponse = null): void
    {
        $this->update([
            'status' => 'success',
            'external_id' => $externalId,
            'provider_response' => $providerResponse,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark transaction as failed.
     */
    public function markAsFailed(?string $providerResponse = null): void
    {
        $this->update([
            'status' => 'failed',
            'provider_response' => $providerResponse,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark transaction as reversed.
     */
    public function markAsReversed(?string $providerResponse = null): void
    {
        $this->update([
            'status' => 'reversed',
            'provider_response' => $providerResponse,
            'completed_at' => now(),
        ]);
    }

    /**
     * Find transaction by reference.
     */
    public static function findByReference(string $reference): ?self
    {
        return static::where('reference', $reference)->first();
    }

    /**
     * Find transaction by external ID.
     */
    public static function findByExternalId(string $externalId): ?self
    {
        return static::where('external_id', $externalId)->first();
    }
}
