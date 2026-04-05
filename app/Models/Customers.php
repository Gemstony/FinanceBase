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
        'customer_image',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function subshop()
    {
        return $this->belongsTo(SubShop::class);
    }

    public function files()
    {
        return $this->hasMany(CustomerFile::class, 'customer_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->customer_image) {
            return null;
        }

        return asset('storage/'.$this->customer_image);
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->customer_image) {
            return asset('storage/'.$this->customer_image);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=random&color=fff&size=40';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
