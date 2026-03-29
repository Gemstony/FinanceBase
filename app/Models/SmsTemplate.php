<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'event',
        'message_template',
        'variables',
        'is_active'
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean'
    ];

    // Scope for active templates
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope by event
    public function scopeByEvent($query, $event)
    {
        return $query->where('event', $event);
    }

    // Relationship
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}