<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubShop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'created_by',
        'name',
        'phone',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the shop that owns this subshop
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Users assigned to this subshop
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'subshop_user', 'subshop_id', 'user_id')
            ->withTimestamps()
            ->withPivot(['role', 'permissions', 'is_active']);
    }

    /**
     * Scope to get only active subshops
     */
    public function scopeActive($query)
    {
        return $query->where('sub_shops.is_active', true);
    }
}
