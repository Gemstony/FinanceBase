<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterestAccrualAccount extends Model
{
    protected $table = 'interest_accrual_accounts';

    protected $fillable = [
        'subshop_id',
        'interest_receivable_account_id',
        'interest_income_account_id',
        'notes',
    ];

    protected $casts = [
        'subshop_id' => 'integer',
        'interest_receivable_account_id' => 'integer',
        'interest_income_account_id' => 'integer',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function interestReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'interest_receivable_account_id');
    }

    public function interestIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'interest_income_account_id');
    }

    public static function forSubshop(int $subshopId): ?self
    {
        return static::query()
            ->where('subshop_id', $subshopId)
            ->with(['interestReceivableAccount.accountClass', 'interestIncomeAccount.accountClass'])
            ->first();
    }

    public static function existsForSubshop(int $subshopId): bool
    {
        return static::query()->where('subshop_id', $subshopId)->exists();
    }
}
