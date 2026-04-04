<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentConfig;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickPesaProvider implements PaymentProviderInterface
{
    protected PaymentConfig $config;

    public function __construct(PaymentConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Get the provider name.
     */
    public function getProviderName(): string
    {
        return 'clickpesa';
    }

    /**
     * Initiate STK Push payment.
     */
    public function initiateSTK(array $data): array
    {
        try {
            $payload = [
                'amount' => $data['amount'],
                'phone' => $this->formatPhone($data['phone']),
                'reference' => $data['reference'],
                'callback_url' => $this->getCallbackUrl(),
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->config->api_url.'/api/v1/payments/stk-push', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['transaction_id'])) {
                return [
                    'status' => 'pending',
                    'external_id' => $responseData['transaction_id'],
                    'message' => $responseData['message'] ?? 'STK Push sent successfully',
                ];
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => $responseData['message'] ?? $responseData['error'] ?? 'STK Push failed',
            ];
        } catch (\Exception $e) {
            Log::error('ClickPesa STK Push failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => 'STK Push failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Send B2C (Business to Customer) disbursement.
     */
    public function sendB2C(array $data): array
    {
        try {
            $payload = [
                'amount' => $data['amount'],
                'phone' => $this->formatPhone($data['phone']),
                'reference' => $data['reference'],
                'description' => $data['description'] ?? 'Disbursement',
                'callback_url' => $this->getCallbackUrl(),
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->config->api_url.'/api/v1/payments/b2c', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['transaction_id'])) {
                return [
                    'status' => 'pending',
                    'external_id' => $responseData['transaction_id'],
                    'message' => $responseData['message'] ?? 'B2C request initiated',
                ];
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => $responseData['message'] ?? $responseData['error'] ?? 'B2C request failed',
            ];
        } catch (\Exception $e) {
            Log::error('ClickPesa B2C failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => 'B2C request failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook callback from ClickPesa.
     */
    public function handleWebhook(array $payload): array
    {
        try {
            // Validate required fields
            if (! isset($payload['reference']) || ! isset($payload['status'])) {
                return [
                    'status' => 'failed',
                    'external_id' => null,
                    'reference' => null,
                    'message' => 'Invalid webhook payload',
                ];
            }

            $status = $this->mapStatus($payload['status']);
            $amount = $payload['amount'] ?? 0;
            $fee = $payload['fee'] ?? 0;

            return [
                'reference' => $payload['reference'],
                'status' => $status,
                'external_id' => $payload['transaction_id'] ?? null,
                'amount' => $amount,
                'fee' => $fee,
                'net' => $amount - $fee,
                'channel_provider' => $payload['provider'] ?? null, // mpesa, airtel, tigo
                'raw' => $payload,
            ];
        } catch (\Exception $e) {
            Log::error('ClickPesa webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'reference' => null,
                'message' => 'Webhook handling failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Validate webhook signature.
     */
    public function validateWebhookSignature(array $payload): bool
    {
        // ClickPesa signature validation
        // If signature is provided in headers, validate it
        if (isset($payload['signature'])) {
            $expectedSignature = hash_hmac('sha256', json_encode($payload['data'] ?? $payload), $this->config->secret_key);

            return hash_equals($expectedSignature, $payload['signature']);
        }

        // If no signature provided, validate based on required fields
        return isset($payload['reference']) && isset($payload['status']);
    }

    /**
     * Map ClickPesa status to system status.
     */
    protected function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCESS', 'COMPLETED', 'SUCCESSFUL' => 'success',
            'FAILED', 'FAILURE', 'ERROR' => 'failed',
            'PENDING', 'PROCESSING', 'INITIATED' => 'pending',
            default => 'failed',
        };
    }

    /**
     * Get OAuth access token.
     */
    protected function getAccessToken(): string
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->config->api_url.'/api/v1/auth/token', [
            'api_key' => $this->config->api_key,
            'secret_key' => $this->config->secret_key,
        ]);

        return $response->json('access_token');
    }

    /**
     * Format phone number to ClickPesa format (255XXXXXXXXX).
     */
    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zero if present
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }

        // Add country code if not present
        if (substr($phone, 0, 3) !== '255') {
            $phone = '255'.$phone;
        }

        return $phone;
    }

    /**
     * Get callback URL.
     */
    protected function getCallbackUrl(): string
    {
        return config('app.url').'/api/payments/clickpesa/webhook';
    }

    /**
     * Test provider connectivity and credentials.
     */
    public function testConnection(array $data): array
    {
        try {
            $token = $this->getAccessToken();

            if (empty($token)) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed: No access token returned.',
                    'provider_response' => ['status' => 'auth_failed'],
                ];
            }

            // Auth succeeded — attempt a lightweight STK probe in sandbox.
            // In sandbox this will either queue or return a sandbox-specific message.
            $reference = 'TEST-'.strtoupper(\Illuminate\Support\Str::random(8));

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->config->api_url.'/api/v1/payments/stk-push', [
                'amount' => $data['amount'] ?? 100,
                'phone' => $this->formatPhone($data['phone'] ?? '255000000000'),
                'reference' => $reference,
                'callback_url' => $this->getCallbackUrl(),
            ]);

            $responseData = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful. STK Push accepted in sandbox.',
                    'provider_response' => $this->sanitizeResponse($responseData),
                ];
            }

            return [
                'success' => false,
                'message' => $responseData['message'] ?? $responseData['error'] ?? 'API call failed with status '.$response->status(),
                'provider_response' => $this->sanitizeResponse($responseData),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
                'provider_response' => ['error' => class_basename($e)],
            ];
        }
    }

    /**
     * Remove sensitive data from response for logging/display.
     */
    protected function sanitizeResponse(array $response): array
    {
        $sensitive = ['access_token', 'secret_key', 'api_key', 'Authorization'];
        foreach ($sensitive as $key) {
            unset($response[$key]);
        }

        return $response;
    }
}
