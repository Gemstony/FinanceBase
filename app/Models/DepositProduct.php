<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepositProduct extends Model
{
    protected $table = 'deposit_products';

    protected $fillable = [
        'subshop_id',
        'name',
        'type',
        'interest_rate',
        'minimum_balance',
        'withdrawal_fee',
        'is_active',
    ];

    protected $casts = [
        'interest_rate' => 'decimal:2',
        'minimum_balance' => 'decimal:2',
        'withdrawal_fee' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function depositAccounts(): HasMany
    {
        return $this->hasMany(DepositAccount::class, 'deposit_product_id');
    }
}
