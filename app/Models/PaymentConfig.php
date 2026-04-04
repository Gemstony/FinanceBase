<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PaymentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'provider',
        'api_url',
        'api_key',
        'secret_key',
        'shortcode',
        'passkey',
        'config_json',
        'environment',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'secret_key',
        'passkey',
        'config_json',
    ];

    /**
     * Encrypt config_json before saving.
     */
    public function setConfigJsonAttribute($value): void
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $this->attributes['config_json'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt config_json when retrieving.
     */
    public function getConfigJsonAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Get decoded config_json as array.
     */
    public function getConfigJsonDecoded(): array
    {
        $json = $this->config_json;

        return $json ? json_decode($json, true) : [];
    }

    /**
     * Encrypt sensitive attributes before saving.
     */
    public function setApiKeyAttribute($value): void
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setSecretKeyAttribute($value): void
    {
        $this->attributes['secret_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setPasskeyAttribute($value): void
    {
        $this->attributes['passkey'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt sensitive attributes when retrieving.
     */
    public function getApiKeyAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getSecretKeyAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getPasskeyAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Get the shop that owns the payment config.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Scope to filter by shop.
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope to filter active configs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter by environment.
     */
    public function scopeEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Get the default config for a shop.
     */
    public static function getDefaultForShop(int $shopId): ?self
    {
        return static::forShop($shopId)
            ->active()
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get config for a specific provider and shop.
     */
    public static function getForProvider(int $shopId, string $provider): ?self
    {
        return static::forShop($shopId)
            ->active()
            ->provider($provider)
            ->first();
    }

    /**
     * Set this config as default for the shop.
     */
    public function setAsDefault(): void
    {
        // Remove default from other configs in the same shop
        static::forShop($this->shop_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
