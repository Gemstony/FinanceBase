<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\WriteOff;
use App\Models\ItemBatch;
use App\Models\SalesOrdersItems;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subshop_id',
        'name',
        'description',
        'category_id',
        'supplier_id',
        'sku',
        'barcode',
        'batch',
        'price',
        'cost_price',
        'quantity',
        'min_quantity',
        'max_quantity',
        'unit',
        'is_active',
        'expiry_date',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'quantity' => 'integer',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'is_active' => 'boolean',
        'expiry_date' => 'date',
    ];

    // Relationships
    public function subshop()
    {
        return $this->belongsTo(SubShop::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Suppliers::class, 'supplier_id');
    }

    public function writeOffs()
    {
        return $this->hasMany(WriteOff::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function itemBatches()
    {
        return $this->hasMany(ItemBatch::class);
    }
    
    /**
     * Generate the next batch number
     * 
     * @return string
     */
    public static function generateBatchNumber()
    {
        // Get the latest batch number
        $lastItem = self::orderBy('id', 'desc')->first();
        
        if ($lastItem && $lastItem->batch) {
            // Extract the number part and increment
            $lastNumber = (int) substr($lastItem->batch, 1);
            $nextNumber = $lastNumber + 1;
        } else {
            // First batch
            $nextNumber = 1;
        }
        
        // Format with leading zeros (B0001, B0002, etc.)
        return 'B' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // Calculate margin percentage
    public function getMarginPercentageAttribute()
    {
        if ($this->cost_price && $this->cost_price > 0) {
            return round((($this->price - $this->cost_price) / $this->cost_price) * 100, 2);
        }
        return 0;
    }

    // Get total transactions count
    public function getTotalTransactionsAttribute()
    {
        return SalesOrdersItems::where('item_id', $this->id)->count();
    }

    // Get total quantity transacted (sold)
    public function getTotalQuantityTransactedAttribute()
    {
        return (int) SalesOrdersItems::where('item_id', $this->id)->sum('quantity');
    }

    // Get total quantity from batches
    public function getTotalQuantityAttribute()
    {
        return $this->itemBatches()->sum('quantity');
    }

    // Get total write-off quantity
    public function getTotalWriteOffQuantityAttribute()
    {
        return $this->writeOffs()->sum('quantity');
    }

    // Generate SKU based on subshop name with guaranteed uniqueness
    public static function generateSKU($subshopId, $maxAttempts = 3)
    {
        $subshop = SubShop::find($subshopId);
        if (!$subshop) {
            // If no subshop, use a generic prefix
            $prefix = 'IT';
        } else {
            // Get first 2 letters of subshop name, uppercase, or default to 'IT'
            $prefix = strtoupper(substr(trim($subshop->name), 0, 2));
            if (strlen($prefix) < 2) {
                $prefix = str_pad($prefix, 2, 'X');
            }
            // Ensure prefix is alphanumeric only
            $prefix = preg_replace('/[^A-Z0-9]/', 'X', $prefix);
            if (strlen($prefix) < 2) {
                $prefix = 'IT';
            } else {
                $prefix = substr($prefix, 0, 2);
            }
        }

        // First try: Use timestamp-based approach for better uniqueness
        $timestamp = now()->format('ymdHis');
        $random = strtoupper(Str::random(2));
        $sku = "{$prefix}-{$timestamp}{$random}";
        
        if (!self::where('sku', $sku)->exists()) {
            return $sku;
        }

        // If timestamp-based SKU exists (unlikely), fall back to sequential with random suffix
        for ($i = 1; $i <= $maxAttempts; $i++) {
            $randomSuffix = strtoupper(Str::random(4));
            $sku = "{$prefix}-{$randomSuffix}";
            
            if (!self::where('sku', $sku)->exists()) {
                return $sku;
            }
            
            // If we're about to do the last attempt, use a more random approach
            if ($i === $maxAttempts - 1) {
                $sku = "{$prefix}-" . uniqid('', true);
                if (!self::where('sku', $sku)->exists()) {
                    return $sku;
                }
            }
        }
        
        // Final fallback - extremely unlikely to reach here
        return "{$prefix}-" . uniqid('', true) . '-' . strtoupper(Str::random(4));
    }

    // Generate barcode (using a simplified format for now)
    public static function generateBarcode()
    {
        // Generate a 12-digit barcode (simplified version)
        // In a real application, you'd want to use proper barcode standards
        $timestamp = now()->format('ymdHis');
        $random = mt_rand(100, 999);
        return $timestamp . $random;
    }
}
