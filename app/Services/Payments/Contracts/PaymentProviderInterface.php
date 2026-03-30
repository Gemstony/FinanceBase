<?php

namespace App\Services\Payments\Contracts;

interface PaymentProviderInterface
{
    /**
     * Initiate STK Push payment.
     *
     * @param array $data Payment data containing:
     *   - phone: string
     *   - amount: float
     *   - reference: string
     *   - description: string (optional)
     * @return array Standardized response:
     *   - status: 'pending'|'success'|'failed'
     *   - external_id: string|null
     *   - message: string
     */
    public function initiateSTK(array $data): array;

    /**
     * Send B2C (Business to Customer) disbursement.
     *
     * @param array $data Payment data containing:
     *   - phone: string
     *   - amount: float
     *   - reference: string
     *   - description: string (optional)
     * @return array Standardized response:
     *   - status: 'pending'|'success'|'failed'
     *   - external_id: string|null
     *   - message: string
     */
    public function sendB2C(array $data): array;

    /**
     * Handle webhook callback from provider.
     *
     * @param array $payload Webhook payload from provider
     * @return array Standardized response:
     *   - status: 'success'|'failed'|'reversed'
     *   - external_id: string|null
     *   - reference: string|null
     *   - message: string
     */
    public function handleWebhook(array $payload): array;

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Validate webhook signature.
     *
     * @param array $payload Webhook payload
     * @return bool
     */
    public function validateWebhookSignature(array $payload): bool;
}
