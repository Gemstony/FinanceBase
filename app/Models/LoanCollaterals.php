<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanCollaterals extends Model
{
    use SoftDeletes;

    protected $table = 'loan_collaterals';

    protected $fillable = [
        'subshop_id',
        'loan_id',
        'customer_collateral_id',
        'collateral_value',
        'accepted_value',
        'coverage_ratio',
        'status',
        'verification_date',
        'release_date',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'collateral_value' => 'decimal:2',
        'accepted_value' => 'decimal:2',
        'coverage_ratio' => 'decimal:2',
        'verification_date' => 'date',
        'release_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loans::class, 'loan_id');
    }

    public function customerCollateral()
    {
        return $this->belongsTo(CustomerCollaterals::class, 'customer_collateral_id');
    }
}
