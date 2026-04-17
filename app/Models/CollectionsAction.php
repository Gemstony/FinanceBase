<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use \Illuminate\Support\Carbon;

class CollectionsAction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_id',
        'customer_id',
        'subshop_id',
        'action_type',
        'scheduled_at',
        'completed_at',
        'status',
        'notes',
        'outcome',
        'amount_promised',
        'promise_date',
        'amount_collected',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'promise_date' => 'date',
        'amount_promised' => 'decimal:2',
        'amount_collected' => 'decimal:2',
    ];

    /**
     * Get the loan associated with this action.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    /**
     * Get the customer associated with this action.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    /**
     * Get the assigned user.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for pending actions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for overdue actions.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<', now());
    }

    /**
     * Scope for actions due today.
     */
    public function scopeDueToday($query)
    {
        return $query->whereDate('scheduled_at', today());
    }

    /**
     * Scope for specific action type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('action_type', $type);
    }

    /**
     * Scope for assigned user.
     */
    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Mark action as completed.
     */
    public function markCompleted(?string $outcome = null, ?string $notes = null, ?float $amountCollected = null): void
    {
        $this->status = 'completed';
        $this->completed_at = Carbon::now();

        if ($outcome) {
            $this->outcome = $outcome;
        }

        if ($notes) {
            $this->notes = $notes;
        }

        if ($amountCollected) {
            $this->amount_collected = $amountCollected;
        }

        $this->save();
    }

    /**
     * Check if action is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->scheduled_at
            && $this->scheduled_at->isPast();
    }
}
