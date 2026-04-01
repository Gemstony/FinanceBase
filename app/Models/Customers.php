<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customers extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subshop_id',
        'shop_id',
        'customer_code',
        'name',
        'email',
        'phone',
        'altenative_phone',
        'gender',
        'birth_date',
        'region',
        'district',
        'ward',
        'street',
        'house_no',
        'work',
        'work_address',
        'id_type',
        'id_number',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the subshop that owns this customer
     */
    public function subshop()
    {
        return $this->belongsTo(SubShop::class);
    }

    /**
     * Get the shop that owns this customer
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Scope to get only active customers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
