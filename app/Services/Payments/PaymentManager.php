<?php

namespace App\Services\Payments;

use App\Jobs\ProcessPaymentJob;
use App\Models\PaymentConfig;
use App\Models\PaymentTransaction;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\Providers\AirtelProvider;
use App\Services\Payments\Providers\ClickPesaProvider;
use App\Services\Payments\Providers\MpesaProvider;
use App\Services\Payments\Providers\TigoProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentManager
{
    /**
     * Initiate a payment.
     *
     * @param array $data Payment data containing:
     *   - shop_id: int (required)
     *   - customer_id: int (optional)
     *   - loan_id: int (optional)
     *   - amount: float (required)
     *   - phone: string (required)
     *   - channel: string (stk, c2b, b2c) (required)
     *   - provider: string (optional, uses default if not provided)
     *   - subshop_id: int (optional)
     *   - description: string (optional)
     *   - meta: array (optional)
     * @return PaymentTransaction
     * @throws ValidationException
     */
    public function initiatePayment(array $data): PaymentTransaction
    {
        // Validate input
        $this->validatePaymentData($data);

        // Get provider config
        $provider = $data['provider'] ?? null;
        $config = $this->resolveProviderConfig($data['shop_id'], $provider);

        if (!$config) {
            throw ValidationException::withMessages([
                'provider' => 'No active payment provider configured for this shop.',
            ]);
        }

        // Create transaction
        $transaction = PaymentTransaction::create([
            'shop_id' => $data['shop_id'],
            'subshop_id' => $data['subshop_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'loan_id' => $data['loan_id'] ?? null,
            'provider' => $config->provider,
            'channel' => $data['channel'],
            'amount' => $data['amount'],
            'phone' => $data['phone'],
            'status' => 'initiated',
            'meta' => $data['meta'] ?? null,
        ]);

        // Dispatch job to process payment asynchronously
        ProcessPaymentJob::dispatch($transaction->id, $data)->onQueue('payments');

        Log::info('Payment initiated', [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'provider' => $config->provider,
            'channel' => $data['channel'],
            'amount' => $data['amount'],
        ]);

        return $transaction;
    }

    /**
     * Process a payment (called by job).
     *
     * @param int $transactionId
     * @param array $data
     * @return void
     */
    public function processPayment(int $transactionId, array $data): void
    {
        $transaction = PaymentTransaction::find($transactionId);

        if (!$transaction) {
            Log::error('Transaction not found for processing', ['transaction_id' => $transactionId]);
            return;
        }

        try {
            // Get provider
            $provider = $this->resolveProvider($transaction->provider, $transaction->shop_id);

            // Prepare payment data
            $paymentData = [
                'phone' => $transaction->phone,
                'amount' => $transaction->amount,
                'reference' => $transaction->reference,
                'description' => $data['description'] ?? 'Payment',
            ];

            // Call provider based on channel
            if ($transaction->channel === 'b2c') {
                $response = $provider->sendB2C($paymentData);
            } else {
                $response = $provider->initiateSTK($paymentData);
            }

            // Log request/response
            $transaction->logs()->create([
                'provider' => $transaction->provider,
                'request_payload' => json_encode($paymentData),
                'response_payload' => json_encode($response),
                'status' => $response['status'],
            ]);

            // Update transaction status
            if ($response['status'] === 'pending') {
                $transaction->update([
                    'status' => 'pending',
                    'external_id' => $response['external_id'],
                ]);
            } elseif ($response['status'] === 'success') {
                $transaction->markAsSuccess($response['external_id'], json_encode($response));
            } else {
                $transaction->markAsFailed(json_encode($response));
            }

            Log::info('Payment processed', [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'status' => $response['status'],
            ]);
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            $transaction->markAsFailed($e->getMessage());
        }
    }

    /**
     * Handle webhook callback.
     *
     * @param string $provider
     * @param array $payload
     * @return PaymentTransaction|null
     */
    public function handleWebhook(string $provider, array $payload): ?PaymentTransaction
    {
        try {
            // Get provider config
            $config = PaymentConfig::provider($provider)->active()->first();

            if (!$config) {
                Log::error('Provider config not found for webhook', ['provider' => $provider]);
                return null;
            }

            // Resolve provider
            $providerInstance = $this->resolveProvider($provider, $config->shop_id);

            // Handle webhook
            $response = $providerInstance->handleWebhook($payload);

            // Find transaction
            $transaction = $this->findTransactionForWebhook($response, $config->shop_id);

            if (!$transaction) {
                Log::warning('Transaction not found for webhook', [
                    'provider' => $provider,
                    'response' => $response,
                ]);
                return null;
            }

            // Log webhook
            $transaction->logs()->create([
                'provider' => $provider,
                'request_payload' => json_encode($payload),
                'response_payload' => json_encode($response),
                'status' => $response['status'],
            ]);

            // Update transaction status
            $updateData = [];
            
            if ($response['status'] === 'success') {
                $updateData['status'] = 'success';
                $updateData['external_id'] = $response['external_id'];
                $updateData['provider_response'] = json_encode($response);
                $updateData['completed_at'] = now();
            } elseif ($response['status'] === 'failed') {
                $updateData['status'] = 'failed';
                $updateData['provider_response'] = json_encode($response);
                $updateData['completed_at'] = now();
            } elseif ($response['status'] === 'reversed') {
                $updateData['status'] = 'reversed';
                $updateData['provider_response'] = json_encode($response);
                $updateData['completed_at'] = now();
            }
            
            // Handle ClickPesa-specific fields
            if ($provider === 'clickpesa') {
                if (isset($response['fee'])) {
                    $updateData['fee_amount'] = $response['fee'];
                }
                if (isset($response['net'])) {
                    $updateData['net_amount'] = $response['net'];
                }
                if (isset($response['channel_provider'])) {
                    $updateData['channel_provider'] = $response['channel_provider'];
                }
                $updateData['aggregator'] = 'clickpesa';
            }
            
            if (!empty($updateData)) {
                $transaction->update($updateData);
            }

            Log::info('Webhook processed', [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'status' => $response['status'],
            ]);

            return $transaction;
        } catch (\Exception $e) {
            Log::error('Webhook handling failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return null;
        }
    }

    /**
     * Resolve provider config.
     *
     * @param int $shopId
     * @param string|null $provider
     * @return PaymentConfig|null
     */
    protected function resolveProviderConfig(int $shopId, ?string $provider): ?PaymentConfig
    {
        if ($provider) {
            return PaymentConfig::getForProvider($shopId, $provider);
        }

        return PaymentConfig::getDefaultForShop($shopId);
    }

    /**
     * Resolve provider instance.
     *
     * @param string $provider
     * @param int $shopId
     * @return PaymentProviderInterface
     */
    protected function resolveProvider(string $provider, int $shopId): PaymentProviderInterface
    {
        $config = PaymentConfig::getForProvider($shopId, $provider);

        if (!$config) {
            throw new \Exception("Payment provider {$provider} not configured for shop {$shopId}");
        }

        return match ($provider) {
            'mpesa' => new MpesaProvider($config),
            'airtel' => new AirtelProvider($config),
            'tigo' => new TigoProvider($config),
            'clickpesa' => new ClickPesaProvider($config),
            default => throw new \Exception("Unsupported payment provider: {$provider}"),
        };
    }

    /**
     * Find transaction for webhook.
     *
     * @param array $response
     * @param int $shopId
     * @return PaymentTransaction|null
     */
    protected function findTransactionForWebhook(array $response, int $shopId): ?PaymentTransaction
    {
        // Try to find by external_id
        if (!empty($response['external_id'])) {
            $transaction = PaymentTransaction::where('external_id', $response['external_id'])
                ->where('shop_id', $shopId)
                ->first();

            if ($transaction) {
                return $transaction;
            }
        }

        // Try to find by reference
        if (!empty($response['reference'])) {
            $transaction = PaymentTransaction::where('reference', $response['reference'])
                ->where('shop_id', $shopId)
                ->first();

            if ($transaction) {
                return $transaction;
            }
        }

        return null;
    }

    /**
     * Validate payment data.
     *
     * @param array $data
     * @throws ValidationException
     */
    protected function validatePaymentData(array $data): void
    {
        $errors = [];

        if (empty($data['shop_id'])) {
            $errors['shop_id'] = 'Shop ID is required.';
        }

        if (empty($data['amount']) || $data['amount'] <= 0) {
            $errors['amount'] = 'Amount must be greater than zero.';
        }

        if (empty($data['phone'])) {
            $errors['phone'] = 'Phone number is required.';
        }

        if (empty($data['channel']) || !in_array($data['channel'], ['stk', 'c2b', 'b2c'])) {
            $errors['channel'] = 'Channel must be stk, c2b, or b2c.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Get transaction by reference.
     *
     * @param string $reference
     * @param int $shopId
     * @return PaymentTransaction|null
     */
    public function getTransactionByReference(string $reference, int $shopId): ?PaymentTransaction
    {
        return PaymentTransaction::where('reference', $reference)
            ->where('shop_id', $shopId)
            ->first();
    }

    /**
     * Get transactions for a shop.
     *
     * @param int $shopId
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTransactions(int $shopId, array $filters = [])
    {
        $query = PaymentTransaction::where('shop_id', $shopId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['loan_id'])) {
            $query->where('loan_id', $filters['loan_id']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
