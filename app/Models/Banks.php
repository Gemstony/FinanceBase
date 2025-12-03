<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\SubShop;

class Banks extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subshop_id',
        'name',
        'account_name',
        'account_number',
        'branch',
        'email',
        'phone',
        'opening_balance',
        'notes',
        'is_active',
    ];

    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }
}
