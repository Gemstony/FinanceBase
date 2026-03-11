<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepositAccount extends Model
{
    protected $table = 'deposit_accounts';

    protected $fillable = [
        'subshop_id',
        'customer_id',
        'deposit_product_id',
        'account_number',
        'balance',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function depositProduct(): BelongsTo
    {
        return $this->belongsTo(DepositProduct::class, 'deposit_product_id');
    }

    public function depositTransactions(): HasMany
    {
        return $this->hasMany(DepositTransaction::class, 'deposit_account_id');
    }

    public function isActive(): bool
    {
        return (string) $this->status === 'active';
    }

    public function isFrozen(): bool
    {
        return (string) $this->status === 'frozen';
    }

    public function isClosed(): bool
    {
        return (string) $this->status === 'closed';
    }
}
