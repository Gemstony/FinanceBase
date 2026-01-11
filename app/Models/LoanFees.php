<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanFees extends Model
{
    protected $fillable = [
        'subshop_id',
        'name',
        'code',
        'fee_type',
        'amount',
        'percentage',
        'apply_on',
        'income_account_id',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the income account that owns the loan fee.
     */
    public function incomeAccount()
    {
        return $this->belongsTo(ChartsOfAccount::class, 'income_account_id');
    }
}
