<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'shop_id',
        'subject',
        'content',
        'type',
        'priority',
        'is_urgent',
        'delivery_methods',
        'scheduled_at',
        'sent_at',
    ];

    protected $casts = [
        'delivery_methods' => 'array',
        'is_urgent' => 'boolean',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    // Relationships
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class)->with('user');
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, MessageRecipient::class, 'message_id', 'id', 'id', 'user_id');
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->whereHas('recipients', function ($q) {
            $q->where('is_read', false);
        });
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_at')->where('sent_at', null);
    }

    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    // Helper Methods
    public function isScheduled(): bool
    {
        return !is_null($this->scheduled_at) && is_null($this->sent_at);
    }

    public function isSent(): bool
    {
        return !is_null($this->sent_at);
    }

    public function getUnreadCount(): int
    {
        return $this->recipients()->where('is_read', false)->count();
    }

    public function markAsReadFor(User $user): bool
    {
        $recipient = $this->recipients()->where('user_id', $user->id)->first();

        if ($recipient && !$recipient->is_read) {
            return $recipient->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return false;
    }

    public function getPriorityBadgeClass(): string
    {
        return match($this->priority) {
            'urgent' => 'badge-danger',
            'high' => 'badge-warning',
            'normal' => 'badge-info',
            'low' => 'badge-secondary',
            default => 'badge-info',
        };
    }

    public function getTypeIcon(): string
    {
        return match($this->type) {
            'email' => 'fas fa-envelope',
            'notification' => 'fas fa-bell',
            'system' => 'fas fa-cog',
            'bulk' => 'fas fa-bullhorn',
            default => 'fas fa-envelope',
        };
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'email' => 'Email',
            'notification' => 'Notification',
            'system' => 'System Message',
            'bulk' => 'Bulk Message',
            default => 'Message',
        };
    }
}
