<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentConfig;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirtelProvider implements PaymentProviderInterface
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
        return 'airtel';
    }

    /**
     * Initiate STK Push payment.
     */
    public function initiateSTK(array $data): array
    {
        try {
            $payload = [
                'reference' => $data['reference'],
                'subscriber' => [
                    'country' => 'TZ',
                    'currency' => 'TZS',
                    'msisdn' => $this->formatPhone($data['phone']),
                ],
                'transaction' => [
                    'amount' => (float) $data['amount'],
                    'country' => 'TZ',
                    'currency' => 'TZS',
                    'id' => $data['reference'],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
                'X-Country' => 'TZ',
                'X-Currency' => 'TZS',
            ])->post($this->config->api_url.'/merchant/v1/payments/', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['data']['transaction']['id'])) {
                return [
                    'status' => 'pending',
                    'external_id' => $responseData['data']['transaction']['id'],
                    'message' => $responseData['status']['message'] ?? 'Payment initiated successfully',
                ];
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => $responseData['status']['message'] ?? 'Payment initiation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Airtel STK Push failed', [
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
                'payee' => [
                    'msisdn' => $this->formatPhone($data['phone']),
                ],
                'reference' => $data['reference'],
                'pin' => $this->config->secret_key,
                'transaction' => [
                    'amount' => (float) $data['amount'],
                    'id' => $data['reference'],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
                'X-Country' => 'TZ',
                'X-Currency' => 'TZS',
            ])->post($this->config->api_url.'/standard/v1/disbursements/toAccount/', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['data']['transaction']['id'])) {
                return [
                    'status' => 'pending',
                    'external_id' => $responseData['data']['transaction']['id'],
                    'message' => $responseData['status']['message'] ?? 'Disbursement initiated successfully',
                ];
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => $responseData['status']['message'] ?? 'Disbursement initiation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Airtel B2C failed', [
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
     * Handle webhook callback from Airtel.
     */
    public function handleWebhook(array $payload): array
    {
        try {
            $transaction = $payload['transaction'] ?? [];
            $status = $payload['status'] ?? [];

            $externalId = $transaction['id'] ?? null;
            $reference = $transaction['reference'] ?? null;
            $statusCode = $status['code'] ?? null;

            if ($statusCode === 'TS' || $statusCode === 'AP') {
                return [
                    'status' => 'success',
                    'external_id' => $externalId,
                    'reference' => $reference,
                    'message' => $status['message'] ?? 'Payment successful',
                ];
            }

            if ($statusCode === 'TF' || $statusCode === 'DE') {
                return [
                    'status' => 'failed',
                    'external_id' => $externalId,
                    'reference' => $reference,
                    'message' => $status['message'] ?? 'Payment failed',
                ];
            }

            return [
                'status' => 'pending',
                'external_id' => $externalId,
                'reference' => $reference,
                'message' => $status['message'] ?? 'Payment pending',
            ];
        } catch (\Exception $e) {
            Log::error('Airtel webhook handling failed', [
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
        // Airtel uses signature validation
        $signature = $payload['signature'] ?? null;
        if (! $signature) {
            return false;
        }

        // Validate signature using the secret key
        $expectedSignature = hash_hmac('sha256', json_encode($payload['transaction'] ?? []), $this->config->secret_key);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get OAuth access token.
     */
    protected function getAccessToken(): string
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->config->api_url.'/auth/oauth2/token', [
            'client_id' => $this->config->api_key,
            'client_secret' => $this->config->secret_key,
            'grant_type' => 'client_credentials',
        ]);

        return $response->json('access_token');
    }

    /**
     * Format phone number to Airtel format.
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

            // Auth succeeded — attempt a lightweight payment probe in sandbox.
            $reference = 'TEST-'.strtoupper(\Illuminate\Support\Str::random(8));

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
                'X-Country' => 'TZ',
                'X-Currency' => 'TZS',
            ])->post($this->config->api_url.'/merchant/v1/payments/', [
                'reference' => $reference,
                'subscriber' => [
                    'country' => 'TZ',
                    'currency' => 'TZS',
                    'msisdn' => $this->formatPhone($data['phone'] ?? '255000000000'),
                ],
                'transaction' => [
                    'amount' => (float) ($data['amount'] ?? 100),
                    'country' => 'TZ',
                    'currency' => 'TZS',
                    'id' => $reference,
                ],
            ]);

            $responseData = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful. Payment request accepted in sandbox.',
                    'provider_response' => $this->sanitizeResponse($responseData),
                ];
            }

            return [
                'success' => false,
                'message' => $responseData['status']['message'] ?? 'API call failed with status '.$response->status(),
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
        $sensitive = ['access_token', 'secret_key', 'api_key', 'client_secret', 'Authorization'];
        foreach ($sensitive as $key) {
            unset($response[$key]);
        }

        return $response;
    }
}
