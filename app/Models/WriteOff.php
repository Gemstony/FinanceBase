<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class WriteOff extends Model
{
    protected $table = 'write_offs';
    
    protected $fillable = [
        'subshop_id',
        'created_by',
        'item_id',
        'batch_id',
        'quantity',
        'reason',
        'write_off_date',
        'description',
        'unit_price',
        'total_value',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'write_off_date' => 'date',
        'unit_price' => 'decimal:2',
        'total_value' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault([
            'name' => 'System'
        ]);
    }

    public function reviewed()
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withDefault([
            'name' => 'System'
        ]);
    }

    public function subshop()
    {
        return $this->belongsTo(SubShop::class, 'subshop_id');
    }

    public function batch()
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }
}
