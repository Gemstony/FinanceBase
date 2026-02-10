<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPenalties extends Model
{
    protected $fillable = [
        'subshop_id',
        'name',
        'code',
        'penalty_type',
        'amount',
        'percentage',
        'grace_period_days',
        'frequency',
        'income_account_id',
        'receivable_account_id',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'grace_period_days' => 'integer',
        'frequency' => 'string',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'frequency' => 'once',
    ];

    /**
     * Get the income account that owns the loan penalty.
     */
    public function incomeAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'income_account_id');
    }

    /**
     * Get the receivable account that owns the loan penalty.
     */
    public function receivableAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'receivable_account_id');
    }

    /**
     * Get the subshop that owns the loan penalty.
     */
    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }
}
