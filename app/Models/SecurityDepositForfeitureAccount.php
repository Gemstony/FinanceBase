<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityDepositForfeitureAccount extends Model
{
    protected $table = 'security_deposit_forfeiture_accounts';

    protected $fillable = [
        'subshop_id',
        'chart_of_account_id',
        'notes',
    ];

    protected $casts = [
        'subshop_id' => 'integer',
        'chart_of_account_id' => 'integer',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'chart_of_account_id');
    }

    public static function forSubshop(int $subshopId): ?self
    {
        return static::query()
            ->where('subshop_id', $subshopId)
            ->with('chartOfAccount.accountClass')
            ->first();
    }

    public static function existsForSubshop(int $subshopId): bool
    {
        return static::query()->where('subshop_id', $subshopId)->exists();
    }
}
