<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPenaltyApplications extends Model
{
    protected $table = 'loan_penalty_applications';

    protected $fillable = [
        'loan_id',
        'loan_product_penalty_id',
        'amount',
        'paid_amount',
        'forgiven_amount',
        'forgiven_by',
        'forgiven_at',
        'forgiveness_reason',
        'charge_event',
        'applied_on',
        'is_paid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'forgiven_amount' => 'decimal:2',
        'applied_on' => 'date',
        'forgiven_at' => 'datetime',
        'is_paid' => 'boolean',
    ];

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function loanProductPenalty(): BelongsTo
    {
        return $this->belongsTo(LoanProductPenalties::class, 'loan_product_penalty_id');
    }

    public function forgivenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forgiven_by');
    }

    /**
     * Get the outstanding amount for this penalty application.
     */
    public function getOutstandingAmount(): float
    {
        return round(
            max(0.0, (float) $this->amount - (float) $this->paid_amount - (float) $this->forgiven_amount),
            2
        );
    }

    /**
     * Check if this penalty is fully paid or forgiven.
     */
    public function isFullySettled(): bool
    {
        return $this->getOutstandingAmount() <= 0;
    }

    /**
     * Update the is_paid flag based on current amounts.
     */
    public function updatePaymentStatus(): void
    {
        $this->is_paid = $this->isFullySettled();
        $this->save();
    }

    /**
     * Get status label for display.
     */
    public function getStatusLabel(): string
    {
        if ($this->isFullySettled()) {
            if ((float) $this->forgiven_amount > 0 && (float) $this->paid_amount <= 0) {
                return 'Forgiven';
            }
            if ((float) $this->forgiven_amount > 0 && (float) $this->paid_amount > 0) {
                return 'Partially Paid & Forgiven';
            }
            return 'Paid';
        }

        if ((float) $this->paid_amount > 0 || (float) $this->forgiven_amount > 0) {
            return 'Partially Settled';
        }

        return 'Outstanding';
    }

    /**
     * Get status badge class for UI.
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->getStatusLabel()) {
            'Paid' => 'bg-success',
            'Forgiven' => 'bg-info',
            'Partially Paid & Forgiven' => 'bg-primary',
            'Partially Settled' => 'bg-warning',
            default => 'bg-danger',
        };
    }
}
