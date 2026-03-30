<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentConfig;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaProvider implements PaymentProviderInterface
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
        return 'mpesa';
    }

    /**
     * Initiate STK Push payment.
     */
    public function initiateSTK(array $data): array
    {
        try {
            $timestamp = date('YmdHis');
            $password = base64_encode(
                $this->config->shortcode . $this->config->passkey . $timestamp
            );

            $payload = [
                'BusinessShortCode' => $this->config->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) $data['amount'],
                'PartyA' => $this->formatPhone($data['phone']),
                'PartyB' => $this->config->shortcode,
                'PhoneNumber' => $this->formatPhone($data['phone']),
                'CallBackURL' => $this->getCallbackUrl(),
                'AccountReference' => $data['reference'],
                'TransactionDesc' => $data['description'] ?? 'Payment',
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->post($this->config->api_url . '/mpesa/stkpush/v1/processrequest', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['CheckoutRequestID'])) {
                return [
                    'status' => 'pending',
                    'external_id' => $responseData['CheckoutRequestID'],
                    'message' => $responseData['CustomerMessage'] ?? 'STK Push sent successfully',
                ];
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => $responseData['errorMessage'] ?? $responseData['ResponseDescription'] ?? 'STK Push failed',
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa STK Push failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => 'STK Push failed: ' . $e->getMessage(),
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
                'InitiatorName' => $this->config->shortcode,
                'SecurityCredential' => $this->getSecurityCredential(),
                'CommandID' => 'BusinessPayment',
                'Amount' => (int) $data['amount'],
                'PartyA' => $this->config->shortcode,
                'PartyB' => $this->formatPhone($data['phone']),
                'Remarks' => $data['description'] ?? 'Disbursement',
                'QueueTimeOutURL' => $this->getTimeoutUrl(),
                'ResultURL' => $this->getResultUrl(),
                'Occasion' => $data['reference'] ?? '',
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->post($this->config->api_url . '/mpesa/b2c/v1/paymentrequest', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['ConversationID'])) {
                return [
                    'status' => 'pending',
                    'external_id' => $responseData['ConversationID'],
                    'message' => $responseData['ResponseDescription'] ?? 'B2C request initiated',
                ];
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => $responseData['errorMessage'] ?? $responseData['ResponseDescription'] ?? 'B2C request failed',
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa B2C failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => 'B2C request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook callback from M-Pesa.
     */
    public function handleWebhook(array $payload): array
    {
        try {
            // Handle STK callback
            if (isset($payload['Body']['stkCallback'])) {
                return $this->handleSTKCallback($payload['Body']['stkCallback']);
            }

            // Handle B2C result
            if (isset($payload['Result'])) {
                return $this->handleB2CResult($payload['Result']);
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'reference' => null,
                'message' => 'Unknown webhook type',
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'reference' => null,
                'message' => 'Webhook handling failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle STK callback.
     */
    protected function handleSTKCallback(array $callback): array
    {
        $resultCode = $callback['ResultCode'] ?? null;
        $externalId = $callback['CheckoutRequestID'] ?? null;
        $reference = $callback['CallbackMetadata']['Item'][0]['Value'] ?? null;

        if ($resultCode === 0) {
            return [
                'status' => 'success',
                'external_id' => $externalId,
                'reference' => $reference,
                'message' => 'Payment successful',
            ];
        }

        return [
            'status' => 'failed',
            'external_id' => $externalId,
            'reference' => $reference,
            'message' => $callback['ResultDesc'] ?? 'Payment failed',
        ];
    }

    /**
     * Handle B2C result.
     */
    protected function handleB2CResult(array $result): array
    {
        $resultCode = $result['ResultCode'] ?? null;
        $externalId = $result['ConversationID'] ?? null;
        $reference = $result['OriginatorConversationID'] ?? null;

        if ($resultCode === 0) {
            return [
                'status' => 'success',
                'external_id' => $externalId,
                'reference' => $reference,
                'message' => 'Disbursement successful',
            ];
        }

        return [
            'status' => 'failed',
            'external_id' => $externalId,
            'reference' => $reference,
            'message' => $result['ResultDesc'] ?? 'Disbursement failed',
        ];
    }

    /**
     * Validate webhook signature.
     */
    public function validateWebhookSignature(array $payload): bool
    {
        // M-Pesa doesn't use signature validation in the same way
        // We validate based on the presence of expected fields
        return isset($payload['Body']) || isset($payload['Result']);
    }

    /**
     * Get OAuth access token.
     */
    protected function getAccessToken(): string
    {
        $response = Http::withBasicAuth(
            $this->config->api_key,
            $this->config->secret_key
        )->get($this->config->api_url . '/oauth/v1/generate?grant_type=client_credentials');

        return $response->json('access_token');
    }

    /**
     * Get security credential for B2C.
     */
    protected function getSecurityCredential(): string
    {
        // In production, this should be encrypted with the M-Pesa public key
        return base64_encode($this->config->secret_key);
    }

    /**
     * Format phone number to M-Pesa format (255XXXXXXXXX).
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
            $phone = '255' . $phone;
        }

        return $phone;
    }

    /**
     * Get callback URL.
     */
    protected function getCallbackUrl(): string
    {
        return config('app.url') . '/api/payments/mpesa/webhook';
    }

    /**
     * Get timeout URL.
     */
    protected function getTimeoutUrl(): string
    {
        return config('app.url') . '/api/payments/mpesa/webhook';
    }

    /**
     * Get result URL.
     */
    protected function getResultUrl(): string
    {
        return config('app.url') . '/api/payments/mpesa/webhook';
    }
}
