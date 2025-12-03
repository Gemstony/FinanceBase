<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageRecipient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'message_id',
        'user_id',
        'is_read',
        'read_at',
        'delivery_status',
        'delivery_error',
        'deleted_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByDeliveryStatus($query, $status)
    {
        return $query->where('delivery_status', $status);
    }

    // Helper Methods
    public function markAsRead(): bool
    {
        if (!$this->is_read) {
            return $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return true;
    }

    public function markAsDelivered(): bool
    {
        return $this->update(['delivery_status' => 'delivered']);
    }

    public function markAsFailed(string $error = null): bool
    {
        return $this->update([
            'delivery_status' => 'failed',
            'delivery_error' => $error,
        ]);
    }

    public function isDelivered(): bool
    {
        return $this->delivery_status === 'delivered';
    }

    public function isFailed(): bool
    {
        return $this->delivery_status === 'failed';
    }

    public function getDeliveryStatusBadgeClass(): string
    {
        return match($this->delivery_status) {
            'delivered' => 'badge-success',
            'sent' => 'badge-info',
            'pending' => 'badge-warning',
            'failed' => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    public function getDeliveryStatusLabel(): string
    {
        return match($this->delivery_status) {
            'delivered' => 'Delivered',
            'sent' => 'Sent',
            'pending' => 'Pending',
            'failed' => 'Failed',
            default => 'Unknown',
        };
    }
}
