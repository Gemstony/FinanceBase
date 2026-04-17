<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskThreshold extends Model
{
    use HasFactory;

    protected $fillable = [
        'subshop_id',
        'par30_warning_threshold',
        'par30_critical_threshold',
        'par90_warning_threshold',
        'par90_critical_threshold',
        'max_exposure_per_customer',
        'max_portfolio_percentage_per_customer',
        'max_sector_concentration',
        'max_product_concentration',
        'provision_rate_par30',
        'provision_rate_par60',
        'provision_rate_par90',
        'provision_rate_default',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'par30_warning_threshold' => 'decimal:2',
        'par30_critical_threshold' => 'decimal:2',
        'par90_warning_threshold' => 'decimal:2',
        'par90_critical_threshold' => 'decimal:2',
        'max_exposure_per_customer' => 'decimal:2',
        'max_portfolio_percentage_per_customer' => 'decimal:2',
        'max_sector_concentration' => 'decimal:2',
        'max_product_concentration' => 'decimal:2',
        'provision_rate_par30' => 'decimal:2',
        'provision_rate_par60' => 'decimal:2',
        'provision_rate_par90' => 'decimal:2',
        'provision_rate_default' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the subshop.
     */
    public function subshop(): BelongsTo
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    /**
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for global (non-subshop specific) thresholds.
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('subshop_id');
    }

    /**
     * Scope for active thresholds.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get thresholds for a subshop (or global if not found).
     */
    public static function forSubshop(?int $subshopId): ?self
    {
        $thresholds = self::where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->first();

        if (!$thresholds && $subshopId) {
            // Fall back to global thresholds
            $thresholds = self::global()->active()->first();
        }

        return $thresholds;
    }

    /**
     * Get provision rate for a given risk status.
     */
    public function getProvisionRate(string $riskStatus): float
    {
        return match ($riskStatus) {
            'par30' => (float) $this->provision_rate_par30,
            'par60' => (float) $this->provision_rate_par60,
            'par90' => (float) $this->provision_rate_par90,
            'default' => (float) $this->provision_rate_default,
            default => 0,
        };
    }

    /**
     * Check if PAR30 rate is at warning level.
     */
    public function isPar30Warning(float $rate): bool
    {
        return $rate >= $this->par30_warning_threshold;
    }

    /**
     * Check if PAR30 rate is at critical level.
     */
    public function isPar30Critical(float $rate): bool
    {
        return $rate >= $this->par30_critical_threshold;
    }

    /**
     * Check if PAR90/NPL rate is at warning level.
     */
    public function isPar90Warning(float $rate): bool
    {
        return $rate >= $this->par90_warning_threshold;
    }

    /**
     * Check if PAR90/NPL rate is at critical level.
     */
    public function isPar90Critical(float $rate): bool
    {
        return $rate >= $this->par90_critical_threshold;
    }
}
