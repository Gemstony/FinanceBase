<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class SmsConfig extends Model
{
    protected $fillable = [
        'shop_id',
        'provider',
        'api_url',
        'api_key',
        'secret_key',
        'sender_id',
        'is_active',
        'is_default',
        'rate_limit_per_minute'
    ];

    protected $hidden = [
        'api_key',
        'secret_key'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'rate_limit_per_minute' => 'integer',
    ];

    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                try {
                    return $value ? Crypt::decryptString($value) : null;
                } catch (\Exception $e) {
                    return $value;
                }
            },
            set: fn ($value) => $value ? Crypt::encryptString($value) : null
        );
    }

    protected function secretKey(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                try {
                    return $value ? Crypt::decryptString($value) : null;
                } catch (\Exception $e) {
                    return $value;
                }
            },
            set: fn ($value) => $value ? Crypt::encryptString($value) : null
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}