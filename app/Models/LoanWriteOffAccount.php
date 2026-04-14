<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanWriteOffAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'subshop_id',
        'write_off_expense_account_id',
        'recovery_income_account_id',
        'notes',
    ];

    protected $casts = [
        'subshop_id' => 'integer',
        'write_off_expense_account_id' => 'integer',
        'recovery_income_account_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the subshop that owns this configuration.
     */
    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class);
    }

    /**
     * Get the write-off expense account.
     */
    public function writeOffExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'write_off_expense_account_id');
    }

    /**
     * Get the recovery income account.
     */
    public function recoveryIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartsOfAccount::class, 'recovery_income_account_id');
    }

    /**
     * Get configuration by subshop ID.
     */
    public static function getBySubshop(int $subshopId): ?self
    {
        return self::with(['writeOffExpenseAccount', 'recoveryIncomeAccount'])
            ->where('subshop_id', $subshopId)
            ->first();
    }

    /**
     * Check if configuration exists for a subshop.
     */
    public static function existsForSubshop(int $subshopId): bool
    {
        return self::where('subshop_id', $subshopId)->exists();
    }
}
