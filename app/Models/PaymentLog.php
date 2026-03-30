<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'provider',
        'request_payload',
        'response_payload',
        'status',
    ];

    /**
     * Get the transaction that owns the log.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }

    /**
     * Scope to filter by transaction.
     */
    public function scopeForTransaction($query, int $transactionId)
    {
        return $query->where('transaction_id', $transactionId);
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Create a new log entry.
     */
    public static function log(
        int $transactionId,
        string $provider,
        ?array $requestPayload = null,
        ?array $responsePayload = null,
        ?string $status = null
    ): self {
        return static::create([
            'transaction_id' => $transactionId,
            'provider' => $provider,
            'request_payload' => $requestPayload ? json_encode($requestPayload) : null,
            'response_payload' => $responsePayload ? json_encode($responsePayload) : null,
            'status' => $status,
        ]);
    }

    /**
     * Get decoded request payload.
     */
    public function getDecodedRequestPayloadAttribute(): ?array
    {
        return $this->request_payload ? json_decode($this->request_payload, true) : null;
    }

    /**
     * Get decoded response payload.
     */
    public function getDecodedResponsePayloadAttribute(): ?array
    {
        return $this->response_payload ? json_decode($this->response_payload, true) : null;
    }
}
