<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_date',
        'subshop_id',
        'shop_id',
        'portfolio_outstanding',
        'total_active_loans',
        'performing_loans',
        'delinquent_loans',
        'par30_rate',
        'par60_rate',
        'par90_rate',
        'par180_rate',
        'par30_amount',
        'par60_amount',
        'par90_amount',
        'par180_amount',
        'npl_rate',
        'npl_amount',
        'current_count',
        'par30_count',
        'par60_count',
        'par90_count',
        'default_count',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'portfolio_outstanding' => 'decimal:2',
        'par30_rate' => 'decimal:2',
        'par60_rate' => 'decimal:2',
        'par90_rate' => 'decimal:2',
        'par180_rate' => 'decimal:2',
        'par30_amount' => 'decimal:2',
        'par60_amount' => 'decimal:2',
        'par90_amount' => 'decimal:2',
        'par180_amount' => 'decimal:2',
        'npl_rate' => 'decimal:2',
        'npl_amount' => 'decimal:2',
    ];

    /**
     * Get NPL amount (alias for par90_amount).
     */
    public function getNplAmountAttribute(): float
    {
        return (float) ($this->par90_amount ?? 0);
    }

    /**
     * Get NPL rate (alias for par90_rate).
     */
    public function getNplRateAttribute(): float
    {
        return (float) ($this->par90_rate ?? 0);
    }

    /**
     * Get total PAR amount (PAR30+).
     */
    public function getTotalParAmountAttribute(): float
    {
        return $this->par30_amount + $this->par60_amount + $this->par90_amount + $this->par180_amount;
    }

    /**
     * Get total delinquent count.
     */
    public function getTotalDelinquentCountAttribute(): int
    {
        return $this->par30_count + $this->par60_count + $this->par90_count + $this->default_count;
    }

    /**
     * Scope for subshop.
     */
    public function scopeForSubshop($query, ?int $subshopId)
    {
        return $query->where('subshop_id', $subshopId);
    }

    /**
     * Scope for date range.
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('snapshot_date', [$startDate, $endDate]);
    }

    /**
     * Scope for latest snapshot.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('snapshot_date', 'desc');
    }
}
