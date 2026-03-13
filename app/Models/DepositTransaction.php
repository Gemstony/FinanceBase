<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'deposit_transactions';

    protected $fillable = [
        'deposit_account_id',
        'transaction_type',
        'payment_method',
        'bank_account_id',
        'amount',
        'balance_after',
        'reference',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function depositAccount(): BelongsTo
    {
        return $this->belongsTo(DepositAccount::class, 'deposit_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccounts::class, 'bank_account_id');
    }
}
