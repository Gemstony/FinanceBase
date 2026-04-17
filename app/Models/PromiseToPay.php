<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromiseToPay extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'promises_to_pay';

    protected $fillable = [
        'loan_id',
        'customer_id',
        'subshop_id',
        'collections_action_id',
        'amount_promised',
        'promise_date',
        'promise_type',
        'status',
        'fulfilled_at',
        'amount_fulfilled',
        'reminder_sent_at',
        'follow_up_at',
        'reminder_count',
        'promise_notes',
        'outcome_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'promise_date' => 'date',
        'fulfilled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'follow_up_at' => 'datetime',
        'amount_promised' => 'decimal:2',
        'amount_fulfilled' => 'decimal:2',
    ];

    /**
     * Get the loan associated with this promise.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    /**
     * Get the customer associated with this promise.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    /**
     * Get the related collections action.
     */
    public function collectionsAction(): BelongsTo
    {
        return $this->belongsTo(CollectionsAction::class, 'collections_action_id');
    }

    /**
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for pending promises.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for overdue promises.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('promise_date', '<', today());
    }

    /**
     * Scope for promises due today.
     */
    public function scopeDueToday($query)
    {
        return $query->where('promise_date', today());
    }

    /**
     * Scope for promises due in next N days.
     */
    public function scopeDueInDays($query, int $days)
    {
        return $query->whereBetween('promise_date', [today(), today()->addDays($days)]);
    }

    /**
     * Check if promise is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->promise_date->isPast();
    }

    /**
     * Check if promise is fulfilled.
     */
    public function isFulfilled(): bool
    {
        return $this->status === 'fulfilled';
    }

    /**
     * Mark promise as fulfilled.
     */
    public function markFulfilled(float $amountFulfilled, ?string $notes = null): void
    {
        $this->status = 'fulfilled';
        $this->fulfilled_at = now();
        $this->amount_fulfilled = $amountFulfilled;

        if ($notes) {
            $this->outcome_notes = $notes;
        }

        $this->save();
    }

    /**
     * Mark promise as broken.
     */
    public function markBroken(?string $reason = null): void
    {
        $this->status = 'broken';

        if ($reason) {
            $this->outcome_notes = $reason;
        }

        $this->save();
    }

    /**
     * Get fulfillment percentage.
     */
    public function getFulfillmentPercentage(): float
    {
        if ($this->amount_promised <= 0) {
            return 0;
        }

        return min(100, round(($this->amount_fulfilled / $this->amount_promised) * 100, 2));
    }
}
