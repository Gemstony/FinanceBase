<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentConfig;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AzamPayProvider implements PaymentProviderInterface
{
    protected ?PaymentConfig $config = null;

    protected int $shopId;

    protected string $provider = 'azampay';

    protected const TOKEN_CACHE_TTL = 1800;

    protected const LOG_CHANNEL = 'payments';

    protected const DEFAULT_TIMEOUT = 60;

    protected const DEFAULT_CONNECT_TIMEOUT = 30;

    public function __construct(int $shopId)
    {
        $this->shopId = $shopId;
    }

    protected function getConfig(): PaymentConfig
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $this->config = PaymentConfig::where('shop_id', $this->shopId)
            ->where('provider', $this->provider)
            ->where('is_active', 1)
            ->first();

        if (! $this->config) {
            throw new \RuntimeException(
                "AzamPay payment configuration not found for shop_id: {$this->shopId}. ".
                'Please configure AzamPay in Payment Settings > Add Provider.'
            );
        }

        return $this->config;
    }

    protected function getApiUrl(): string
    {
        $config = $this->getConfig();
        $baseUrl = $config->api_url;

        if (empty($baseUrl)) {
            $environment = $config->environment ?? 'sandbox';
            $baseUrl = $environment === 'live'
                ? 'https://api.azampay.co.tz'
                : 'https://sandbox.azampay.co.tz';
        }

        return rtrim($baseUrl, '/');
    }

    protected function getAuthUrl(): string
    {
        $config = $this->getConfig();
        $environment = $config->environment ?? 'sandbox';

        return $environment === 'live'
            ? 'https://authenticator.azampay.co.tz'
            : 'https://authenticator-sandbox.azampay.co.tz';
    }

    protected function getApiKey(): string
    {
        return $this->getConfig()->api_key ?? '';
    }

    protected function getClientId(): string
    {
        $configJson = $this->getConfig()->getConfigJsonDecoded();

        return $configJson['client_id'] ?? '';
    }

    protected function getClientSecret(): string
    {
        $configJson = $this->getConfig()->getConfigJsonDecoded();

        return $configJson['client_secret'] ?? '';
    }

    protected function getAppName(): string
    {
        $configJson = $this->getConfig()->getConfigJsonDecoded();

        return $configJson['app_name'] ?? '';
    }

    public function getProviderName(): string
    {
        return $this->provider;
    }

    public function getDebugInfo(): array
    {
        try {
            $config = $this->getConfig();
            $configJson = $config->getConfigJsonDecoded();

            return [
                'configured' => true,
                'shop_id' => $this->shopId,
                'provider' => $config->provider,
                'environment' => $config->environment ?? 'sandbox',
                'api_url' => $this->getApiUrl(),
                'auth_url' => $this->getAuthUrl(),
                'has_api_key' => ! empty($config->api_key),
                'has_client_id' => ! empty($configJson['client_id'] ?? ''),
                'has_client_secret' => ! empty($configJson['client_secret'] ?? ''),
                'has_app_name' => ! empty($configJson['app_name'] ?? ''),
                'is_active' => $config->is_active,
            ];
        } catch (\Exception $e) {
            return [
                'configured' => false,
                'shop_id' => $this->shopId,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function authenticate(): string
    {
        $cacheKey = 'azampay_token_'.$this->shopId;

        return Cache::remember($cacheKey, now()->addSeconds(self::TOKEN_CACHE_TTL), function () {
            $authUrl = $this->getAuthUrl();
            $clientId = $this->getClientId();
            $clientSecret = $this->getClientSecret();
            $appName = $this->getAppName();

            if (empty($clientId) || empty($clientSecret) || empty($appName)) {
                $missing = [];
                if (empty($clientId)) {
                    $missing[] = 'client_id';
                }
                if (empty($clientSecret)) {
                    $missing[] = 'client_secret';
                }
                if (empty($appName)) {
                    $missing[] = 'app_name';
                }

                $error = 'AzamPay configuration incomplete. Missing required fields: '.implode(', ', $missing);
                $error .= '. Please update your AzamPay configuration in Payment Settings.';

                Log::error('AzamPay authentication: missing credentials', [
                    'shop_id' => $this->shopId,
                    'missing_fields' => $missing,
                ]);
                throw new \RuntimeException($error);
            }

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                    ->timeout(self::DEFAULT_TIMEOUT)
                    ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
                    ->post("{$authUrl}/AppRegistration/GenerateToken", [
                        'appName' => $appName,
                        'clientId' => $clientId,
                        'clientSecret' => $clientSecret,
                    ]);

                $responseData = $response->json();

                Log::channel(self::LOG_CHANNEL)->info('AzamPay authentication request', [
                    'shop_id' => $this->shopId,
                    'auth_url' => $authUrl,
                    'app_name' => $appName,
                    'status_code' => $response->status(),
                ]);

                if ($response->successful()) {
                    $token = $responseData['data']['accessToken'] ?? $responseData['accessToken'] ?? null;

                    if ($token) {
                        Log::channel(self::LOG_CHANNEL)->info('AzamPay authentication success', [
                            'shop_id' => $this->shopId,
                        ]);

                        return $token;
                    }
                }

                $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Authentication failed with status: '.$response->status();
                Log::error('AzamPay authentication failed', [
                    'shop_id' => $this->shopId,
                    'status_code' => $response->status(),
                    'response' => $this->sanitizeResponse($responseData),
                ]);

                throw new \RuntimeException("AzamPay authentication failed: {$errorMessage}. Please check your client credentials in Payment Settings.");
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $errorMsg = $e->getMessage();
                if (str_contains($errorMsg, 'timeout') || str_contains($errorMsg, 'timed out')) {
                    Log::error('AzamPay authentication timeout', [
                        'shop_id' => $this->shopId,
                        'auth_url' => $authUrl,
                        'error' => $errorMsg,
                    ]);
                    throw new \RuntimeException(
                        'Connection to AzamPay authentication server timed out. '.
                        'Please check your internet connection and try again. '.
                        'If this persists, the AzamPay server may be experiencing issues.'
                    );
                }

                Log::error('AzamPay connection error', [
                    'shop_id' => $this->shopId,
                    'auth_url' => $authUrl,
                    'error' => $errorMsg,
                ]);
                throw new \RuntimeException("Failed to connect to AzamPay: {$errorMsg}. Please check your internet connection.");
            }
        });
    }

    public function initiateSTK(array $data): array
    {
        return $this->mobileCheckout($data);
    }

    protected function mobileCheckout(array $data): array
    {
        try {
            $token = $this->authenticate();
            $apiUrl = $this->getApiUrl();
            $apiKey = $this->getApiKey();

            if (empty($apiKey)) {
                Log::error('AzamPay checkout: missing API key', [
                    'shop_id' => $this->shopId,
                ]);

                return [
                    'status' => 'failed',
                    'external_id' => null,
                    'message' => 'AzamPay API key is not configured. Please add your API key in Payment Settings.',
                ];
            }

            $provider = $data['network'] ?? $data['provider'] ?? 'Mpesa';
            $externalId = $data['reference'] ?? Str::uuid()->toString();

            $payload = [
                'amount' => (string) $data['amount'],
                'currency' => 'TZS',
                'externalId' => $externalId,
                'provider' => $this->mapNetworkProvider($provider),
                'accountNumber' => $this->formatPhone($data['phone']),
                'payerPhone' => $this->formatPhone($data['phone']),
            ];

            Log::channel(self::LOG_CHANNEL)->info('AzamPay mobile checkout request', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'amount' => $data['amount'],
                'provider' => $provider,
                'api_url' => "{$apiUrl}/azampay/mno/checkout",
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'X-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(self::DEFAULT_TIMEOUT)
                ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
                ->post("{$apiUrl}/azampay/mno/checkout", $payload);

            $responseData = $response->json();

            Log::channel(self::LOG_CHANNEL)->info('AzamPay mobile checkout response', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'status_code' => $response->status(),
                'success' => $responseData['success'] ?? false,
            ]);

            if ($response->successful() && ($responseData['success'] ?? false)) {
                return [
                    'status' => 'pending',
                    'external_id' => $responseData['transactionId'] ?? $externalId,
                    'message' => $responseData['message'] ?? 'Mobile checkout initiated. Please complete payment on your phone.',
                ];
            }

            $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Mobile checkout failed';
            Log::error('AzamPay mobile checkout failed', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'status_code' => $response->status(),
                'error' => $errorMessage,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => "Payment request failed: {$errorMessage}",
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $errorMsg = $e->getMessage();
            $isTimeout = str_contains($errorMsg, 'timeout') || str_contains($errorMsg, 'timed out');

            Log::error('AzamPay mobile checkout connection error', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'error' => $errorMsg,
            ]);

            if ($isTimeout) {
                Log::warning('AzamPay mobile checkout timeout - treating as pending. Webhook will confirm.', [
                    'shop_id' => $this->shopId,
                    'external_id' => $externalId,
                    'amount' => $data['amount'],
                    'provider' => $provider,
                ]);

                return [
                    'status' => 'pending',
                    'external_id' => $externalId,
                    'message' => 'Payment request sent. Please complete the payment on your phone. If confirmed, the payment will be processed automatically.',
                ];
            }

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => 'Unable to connect to payment provider. Please check your internet connection and try again.',
            ];
        } catch (\RuntimeException $e) {
            Log::error('AzamPay mobile checkout runtime error', [
                'shop_id' => $this->shopId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('AzamPay mobile checkout exception', [
                'shop_id' => $this->shopId,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => 'Payment processing failed: '.$e->getMessage(),
            ];
        }
    }

    public function sendB2C(array $data): array
    {
        try {
            $token = $this->authenticate();
            $apiUrl = $this->getApiUrl();
            $apiKey = $this->getApiKey();

            if (empty($apiKey)) {
                return [
                    'status' => 'failed',
                    'external_id' => null,
                    'message' => 'AzamPay API key is not configured. Please add your API key in Payment Settings.',
                ];
            }

            $provider = $data['network'] ?? $data['provider'] ?? 'Mpesa';
            $externalId = $data['reference'] ?? Str::uuid()->toString();

            $payload = [
                'amount' => (string) $data['amount'],
                'currency' => 'TZS',
                'externalId' => $externalId,
                'provider' => $this->mapNetworkProvider($provider),
                'accountNumber' => $this->formatPhone($data['phone']),
                'recipientPhone' => $this->formatPhone($data['phone']),
                'senderName' => $this->getAppName(),
                'reason' => $data['description'] ?? 'Disbursement',
            ];

            Log::channel(self::LOG_CHANNEL)->info('AzamPay B2C request', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'amount' => $data['amount'],
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'X-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(self::DEFAULT_TIMEOUT)
                ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
                ->post("{$apiUrl}/api/v1/azampay/disburse", $payload);

            $responseData = $response->json();

            Log::channel(self::LOG_CHANNEL)->info('AzamPay B2C response', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'status_code' => $response->status(),
                'success' => $responseData['success'] ?? false,
            ]);

            if ($response->successful() && ($responseData['success'] ?? false)) {
                return [
                    'status' => 'pending',
                    'external_id' => $responseData['transactionId'] ?? $externalId,
                    'message' => $responseData['message'] ?? 'Disbursement initiated',
                ];
            }

            $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Disbursement failed';
            Log::error('AzamPay B2C failed', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'error' => $errorMessage,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => "Disbursement failed: {$errorMessage}",
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('AzamPay B2C connection error', [
                'shop_id' => $this->shopId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => 'Unable to connect to payment provider. Please check your internet connection.',
            ];
        } catch (\Exception $e) {
            Log::error('AzamPay B2C exception', [
                'shop_id' => $this->shopId,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => 'Disbursement failed: '.$e->getMessage(),
            ];
        }
    }

    public function bankCheckout(array $data): array
    {
        try {
            $token = $this->authenticate();
            $apiUrl = $this->getApiUrl();
            $apiKey = $this->getApiKey();

            if (empty($apiKey)) {
                return [
                    'status' => 'failed',
                    'external_id' => null,
                    'message' => 'AzamPay API key is not configured. Please add your API key in Payment Settings.',
                ];
            }

            $externalId = $data['reference'] ?? Str::uuid()->toString();

            $payload = [
                'amount' => (string) $data['amount'],
                'currency' => 'TZS',
                'externalId' => $externalId,
                'bankProvider' => $data['bank_provider'] ?? 'CRDB',
                'accountNumber' => $data['account_number'] ?? $data['phone'],
            ];

            Log::channel(self::LOG_CHANNEL)->info('AzamPay bank checkout request', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'amount' => $data['amount'],
                'bank' => $data['bank_provider'] ?? 'CRDB',
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'X-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(self::DEFAULT_TIMEOUT)
                ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
                ->post("{$apiUrl}/api/v1/azampay/bank/checkout", $payload);

            $responseData = $response->json();

            Log::channel(self::LOG_CHANNEL)->info('AzamPay bank checkout response', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'status_code' => $response->status(),
                'success' => $responseData['success'] ?? false,
            ]);

            if ($response->successful() && ($responseData['success'] ?? false)) {
                return [
                    'status' => 'pending',
                    'external_id' => $responseData['transactionId'] ?? $externalId,
                    'message' => $responseData['message'] ?? 'Bank checkout initiated',
                ];
            }

            $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Bank checkout failed';
            Log::error('AzamPay bank checkout failed', [
                'shop_id' => $this->shopId,
                'external_id' => $externalId,
                'error' => $errorMessage,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => "Bank checkout failed: {$errorMessage}",
            ];
        } catch (\Exception $e) {
            Log::error('AzamPay bank checkout exception', [
                'shop_id' => $this->shopId,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'status' => 'failed',
                'external_id' => null,
                'message' => 'Bank checkout failed: '.$e->getMessage(),
            ];
        }
    }

    public function handleWebhook(array $payload): array
    {
        try {
            $transactionStatus = $payload['transactionstatus'] ?? $payload['status'] ?? null;
            $externalReference = $payload['externalreference'] ?? $payload['externalId'] ?? $payload['reference'] ?? null;
            $amount = $payload['amount'] ?? 0;
            $msisdn = $payload['msisdn'] ?? $payload['phone'] ?? null;
            $operator = $payload['operator'] ?? null;

            Log::channel(self::LOG_CHANNEL)->info('AzamPay webhook received', [
                'shop_id' => $this->shopId,
                'external_reference' => $externalReference,
                'transaction_status' => $transactionStatus,
                'amount' => $amount,
            ]);

            if (in_array($transactionStatus, ['success', 'COMPLETED', 'SUCCESSFUL'], true)) {
                return [
                    'status' => 'success',
                    'external_id' => $externalReference,
                    'reference' => $externalReference,
                    'amount' => $amount,
                    'phone' => $msisdn,
                    'message' => 'Payment successful',
                    'operator' => $operator,
                    'raw' => $payload,
                ];
            }

            if (in_array($transactionStatus, ['failed', 'FAILED', 'FAILURE'], true)) {
                return [
                    'status' => 'failed',
                    'external_id' => $externalReference,
                    'reference' => $externalReference,
                    'amount' => $amount,
                    'phone' => $msisdn,
                    'message' => $payload['message'] ?? 'Payment failed',
                    'operator' => $operator,
                    'raw' => $payload,
                ];
            }

            return [
                'status' => 'pending',
                'external_id' => $externalReference,
                'reference' => $externalReference,
                'amount' => $amount,
                'phone' => $msisdn,
                'message' => 'Payment pending',
                'operator' => $operator,
                'raw' => $payload,
            ];
        } catch (\Exception $e) {
            Log::error('AzamPay webhook failed', [
                'shop_id' => $this->shopId,
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

    public function validateWebhookSignature(array $payload): bool
    {
        return true;
    }

    public function testConnection(array $data): array
    {
        $configErrors = [];
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        $appName = $this->getAppName();

        if (empty($clientId)) {
            $configErrors[] = 'client_id';
        }
        if (empty($clientSecret)) {
            $configErrors[] = 'client_secret';
        }
        if (empty($appName)) {
            $configErrors[] = 'app_name';
        }

        if (! empty($configErrors)) {
            return [
                'success' => false,
                'message' => 'Configuration incomplete. Please add the following fields in Payment Settings: '.implode(', ', $configErrors),
                'provider_response' => [
                    'error' => 'missing_config',
                    'missing_fields' => $configErrors,
                    'base_url' => $this->getApiUrl(),
                ],
            ];
        }

        try {
            $apiUrl = $this->getAuthUrl();
            $environment = $this->getConfig()->environment ?? 'sandbox';

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(self::DEFAULT_TIMEOUT)
                ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
                ->post("{$apiUrl}/AppRegistration/GenerateToken", [
                    'clientId' => $clientId,
                    'clientSecret' => $clientSecret,
                    'appName' => $appName,
                ]);

            $responseData = $response->json();
            $statusCode = $response->status();

            if ($response->successful()) {
                $token = $responseData['data']['accessToken'] ?? $responseData['accessToken'] ?? null;

                if ($token) {
                    return [
                        'success' => true,
                        'message' => 'Connection successful! Authentication verified with AzamPay. You can now process payments.',
                        'provider_response' => [
                            'authenticated' => true,
                            'base_url' => $apiUrl,
                            'auth_url' => $this->getAuthUrl(),
                            'app_name' => $appName,
                            'environment' => $environment,
                            'status_code' => $statusCode,
                        ],
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Authentication response received but no access token found. Please check your credentials in Payment Settings.',
                    'provider_response' => [
                        'status_code' => $statusCode,
                        'response_keys' => array_keys($responseData),
                    ],
                ];
            }

            $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Unknown error';

            return [
                'success' => false,
                'message' => "Authentication failed: {$errorMessage}. Please verify your client credentials in Payment Settings.",
                'provider_response' => [
                    'status_code' => $statusCode,
                    'error' => $errorMessage,
                    'base_url' => $apiUrl,
                ],
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $errorMsg = $e->getMessage();
            $isTimeout = str_contains($errorMsg, 'timeout') || str_contains($errorMsg, 'timed out');

            if ($isTimeout) {
                return [
                    'success' => false,
                    'message' => 'Connection timed out. The AzamPay server may be experiencing issues or your internet connection is slow. Please try again.',
                    'provider_response' => [
                        'error' => 'connection_timeout',
                        'base_url' => $this->getApiUrl(),
                        'hint' => 'For sandbox use: https://sandbox.azampay.co.tz',
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection failed: Unable to reach AzamPay server. Please check your internet connection.',
                'provider_response' => [
                    'error' => 'connection_error',
                    'base_url' => $this->getApiUrl(),
                    'hint' => 'For sandbox use: https://sandbox.azampay.co.tz',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('AzamPay connection test failed', [
                'shop_id' => $this->shopId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
                'provider_response' => ['error' => class_basename($e)],
            ];
        }
    }

    public function getProviders(): array
    {
        return [
            'Mpesa',
            'Airtel',
            'Tigo',
            'Halopesa',
            'Azampesa',
        ];
    }

    protected function mapNetworkProvider(string $provider): string
    {
        return match (strtolower($provider)) {
            'mpesa', 'm-pesa', 'vodacom' => 'Mpesa',
            'airtel' => 'Airtel',
            'tigo', 'tigopesa' => 'Tigo',
            'halopesa', 'halo' => 'Halopesa',
            'azampesa' => 'Azampesa',
            default => $provider,
        };
    }

    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        if (! str_starts_with($phone, '255')) {
            $phone = '255'.$phone;
        }

        return $phone;
    }

    protected function sanitizeResponse(array $response): array
    {
        $sensitive = ['access_token', 'secret_key', 'api_key', 'client_secret', 'Authorization'];

        foreach ($sensitive as $key) {
            unset($response[$key]);
        }

        return $response;
    }
}
