<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'config_id',
        'provider',
        'test_data',
        'provider_response',
        'status',
        'message',
    ];

    protected $casts = [
        'test_data' => 'array',
        'provider_response' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(PaymentConfig::class, 'config_id');
    }

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }
}
