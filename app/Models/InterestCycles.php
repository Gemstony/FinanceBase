<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterestCycles extends Model
{
    protected $fillable = [
        'subshop_id',
        'name',
        'code',
        'interval_days',
        'is_installment_based',
        'is_active',
    ];

    protected $casts = [
        'is_installment_based' => 'boolean',
        'is_active' => 'boolean',
    ];
}
