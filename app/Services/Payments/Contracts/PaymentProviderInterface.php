<?php

namespace App\Services\Payments\Contracts;

interface PaymentProviderInterface
{
    /**
     * Initiate STK Push payment.
     *
     * @param  array  $data  Payment data containing:
     *                       - phone: string
     *                       - amount: float
     *                       - reference: string
     *                       - description: string (optional)
     * @return array Standardized response:
     *               - status: 'pending'|'success'|'failed'
     *               - external_id: string|null
     *               - message: string
     */
    public function initiateSTK(array $data): array;

    /**
     * Send B2C (Business to Customer) disbursement.
     *
     * @param  array  $data  Payment data containing:
     *                       - phone: string
     *                       - amount: float
     *                       - reference: string
     *                       - description: string (optional)
     * @return array Standardized response:
     *               - status: 'pending'|'success'|'failed'
     *               - external_id: string|null
     *               - message: string
     */
    public function sendB2C(array $data): array;

    /**
     * Handle webhook callback from provider.
     *
     * @param  array  $payload  Webhook payload from provider
     * @return array Standardized response:
     *               - status: 'success'|'failed'|'reversed'
     *               - external_id: string|null
     *               - reference: string|null
     *               - message: string
     */
    public function handleWebhook(array $payload): array;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;

    /**
     * Validate webhook signature.
     *
     * @param  array  $payload  Webhook payload
     */
    public function validateWebhookSignature(array $payload): bool;

    /**
     * Test provider connectivity and credentials.
     *
     * Makes a lightweight API call (auth check or status probe) without
     * creating real transactions. Designed for sandbox diagnostics.
     *
     * @param  array  $data  Test data containing:
     *                       - phone: string
     *                       - amount: float
     *                       - channel: string (stk|b2c)
     *                       - bank_account: string (optional, for bank checkout)
     * @return array Standardized response:
     *               - success: bool
     *               - message: string
     *               - provider_response: array (sanitized, no secrets)
     */
    public function testConnection(array $data): array;
}
