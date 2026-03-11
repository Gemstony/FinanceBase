<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanSecurityDeposit extends Model
{
    protected $table = 'loan_security_deposits';

    protected $fillable = [
        'subshop_id',
        'customer_id',
        'loan_id',
        'amount',
        'status',
        'held_at',
        'released_at',
        'applied_to_loan_id',
        'refunded_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'held_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function appliedToLoan(): BelongsTo
    {
        return $this->belongsTo(Loans::class, 'applied_to_loan_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function isHeld(): bool
    {
        return (string) $this->status === 'held';
    }

    public function isRefunded(): bool
    {
        return (string) $this->status === 'refunded';
    }

    public function isApplied(): bool
    {
        return (string) $this->status === 'applied';
    }
}
