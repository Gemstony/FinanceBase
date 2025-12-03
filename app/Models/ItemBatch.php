<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemBatch extends Model {
    protected $table = 'item_batches';
    protected $fillable = [
        'item_id',
        'batch_number',
        'quantity',
        'cost_price',
        'selling_price',
        'expire_date',
        'manufacture_date'];
    protected $casts = [
        'expire_date' => 'date',
        'manufacture_date' => 'date',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2'];

    public function item() { return $this->belongsTo(Item::class); }

    /**
     * Generate the next batch number
     * 
     * @return string
     */
    public static function generateBatchNumber($itemId = null): string
    {
        if (!$itemId) {
            // Fallback for backward compatibility - generate globally unique
            $lastBatch = self::whereRaw("batch_number REGEXP '^BATCH-[0-9]+$'")
                ->orderBy('id', 'desc')
                ->first();
            
            if ($lastBatch) {
                $lastNumber = (int) substr($lastBatch->batch_number, 6);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }
            
            return 'BATCH-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        }
        
        // Generate batch number unique per item
        $lastBatch = self::where('item_id', $itemId)
            ->whereRaw("batch_number REGEXP '^BATCH-[0-9]+$'")
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastBatch) {
            $lastNumber = (int) substr($lastBatch->batch_number, 6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return 'BATCH-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
