<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsEvent extends Model
{
    protected $fillable = [
        'shop_id',
        'event_name',
        'template_id',
        'is_enabled'
    ];

    protected $casts = [
        'is_enabled' => 'boolean'
    ];

    // Scope for enabled events
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    // Scope by event name
    public function scopeByEventName($query, $eventName)
    {
        return $query->where('event_name', $eventName);
    }

    // Relationships
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function template()
    {
        return $this->belongsTo(SmsTemplate::class);
    }
}