<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterestMethods extends Model
{
    protected $fillable = [
        'subshop_id',
        'name',
        'code',
        'supports_installment_based',
        'supports_daily_accrual',
        'is_active',
    ];

    protected $casts = [
        'supports_installment_based' => 'boolean',
        'supports_daily_accrual' => 'boolean',
        'is_active' => 'boolean',
    ];
}
