<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentConfig;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TigoProvider implements PaymentProviderInterface
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
        return 'tigo';
    }

    /**
     * Initiate STK Push payment.
     */
    public function initiateSTK(array $data): array
    {
        try {
            $payload = [
                'amount' => (float) $data['amount'],
                'currency' => 'TZS',
                'externalId' => $data['reference'],
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->formatPhone($data['phone']),
                ],
                'payerMessage' => $data['description'] ?? 'Payment',
                'payeeNote' => $data['reference'],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
                'X-Reference-Id' => $data['reference'],
            ])->post($this->config->api_url.'/collection/v1_0/requesttopay', $payload);

            $responseData = $response->json();

            if ($response->successful() || $response->status() === 202) {
                return [
                    'status' => 'pending',
                    'external_id' => $data['reference'],
                    'message' => 'Payment initiated successfully',
                ];
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => $responseData['message'] ?? 'Payment initiation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Tigo STK Push failed', [
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
                'amount' => (float) $data['amount'],
                'currency' => 'TZS',
                'externalId' => $data['reference'],
                'payee' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->formatPhone($data['phone']),
                ],
                'payerMessage' => $data['description'] ?? 'Disbursement',
                'payeeNote' => $data['reference'],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
                'X-Reference-Id' => $data['reference'],
            ])->post($this->config->api_url.'/disbursement/v1_0/transfer', $payload);

            $responseData = $response->json();

            if ($response->successful() || $response->status() === 202) {
                return [
                    'status' => 'pending',
                    'external_id' => $data['reference'],
                    'message' => 'Disbursement initiated successfully',
                ];
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => $responseData['message'] ?? 'Disbursement initiation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Tigo B2C failed', [
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
     * Handle webhook callback from Tigo.
     */
    public function handleWebhook(array $payload): array
    {
        try {
            $externalId = $payload['referenceId'] ?? $payload['externalId'] ?? null;
            $status = $payload['status'] ?? $payload['financialTransactionStatus'] ?? null;

            if ($status === 'SUCCESSFUL' || $status === 'COMPLETED') {
                return [
                    'status' => 'success',
                    'external_id' => $externalId,
                    'reference' => $payload['externalId'] ?? null,
                    'message' => 'Payment successful',
                ];
            }

            if ($status === 'FAILED' || $status === 'REJECTED') {
                return [
                    'status' => 'failed',
                    'external_id' => $externalId,
                    'reference' => $payload['externalId'] ?? null,
                    'message' => $payload['reason'] ?? 'Payment failed',
                ];
            }

            if ($status === 'REVERSED') {
                return [
                    'status' => 'reversed',
                    'external_id' => $externalId,
                    'reference' => $payload['externalId'] ?? null,
                    'message' => 'Payment reversed',
                ];
            }

            return [
                'status' => 'pending',
                'external_id' => $externalId,
                'reference' => $payload['externalId'] ?? null,
                'message' => 'Payment pending',
            ];
        } catch (\Exception $e) {
            Log::error('Tigo webhook handling failed', [
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
        // Tigo uses signature validation
        $signature = $payload['signature'] ?? null;
        if (! $signature) {
            return false;
        }

        // Validate signature using the secret key
        $expectedSignature = hash_hmac('sha256', json_encode($payload), $this->config->secret_key);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get OAuth access token.
     */
    protected function getAccessToken(): string
    {
        $response = Http::withBasicAuth(
            $this->config->api_key,
            $this->config->secret_key
        )->post($this->config->api_url.'/collection/token/', []);

        return $response->json('access_token');
    }

    /**
     * Format phone number to Tigo format.
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

            // Auth succeeded — attempt a lightweight requesttopay probe in sandbox.
            $reference = 'TEST-'.strtoupper(\Illuminate\Support\Str::random(8));

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
                'X-Reference-Id' => $reference,
            ])->post($this->config->api_url.'/collection/v1_0/requesttopay', [
                'amount' => (float) ($data['amount'] ?? 100),
                'currency' => 'TZS',
                'externalId' => $reference,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->formatPhone($data['phone'] ?? '255000000000'),
                ],
                'payerMessage' => 'Connection Test',
                'payeeNote' => $reference,
            ]);

            // Tigo/MoMo returns 202 for accepted requests
            if ($response->successful() || $response->status() === 202) {
                return [
                    'success' => true,
                    'message' => 'Connection successful. Payment request accepted in sandbox.',
                    'provider_response' => ['status_code' => $response->status(), 'reference' => $reference],
                ];
            }

            $responseData = $response->json();

            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'API call failed with status '.$response->status(),
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
