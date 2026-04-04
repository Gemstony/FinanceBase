<?php

namespace Tests\Feature;

use App\Models\PaymentConfig;
use App\Models\PaymentTransaction;
use App\Services\Payments\Providers\AzamPayProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AzamPayTest extends TestCase
{
    use RefreshDatabase;

    protected int $shopId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        PaymentConfig::create([
            'shop_id' => $this->shopId,
            'provider' => 'azampay',
            'api_url' => 'https://sandbox.azampay.co.tz',
            'api_key' => 'test_api_key_'.$this->shopId,
            'secret_key' => 'test_secret_key_'.$this->shopId,
            'config_json' => json_encode([
                'client_id' => 'test_client_id_'.$this->shopId,
                'client_secret' => 'test_client_secret_'.$this->shopId,
                'app_name' => 'TestApp',
            ]),
            'environment' => 'sandbox',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function test_can_generate_token(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'test_access_token_123',
                ],
            ], 200),
        ]);

        Cache::flush();

        $provider = new AzamPayProvider($this->shopId);
        $response = $provider->testConnection([]);

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('success', $response['message']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://authenticator-sandbox.azampay.co.tz/AppRegistration/GenerateToken'
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_token_is_cached(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'cached_token_456',
                ],
            ], 200),
        ]);

        $provider = new AzamPayProvider($this->shopId);

        $response1 = $provider->testConnection([]);
        Cache::put('azampay_token_'.$this->shopId, 'cached_token_456', 1800);
        $response2 = $provider->testConnection([]);

        $this->assertTrue($response1['success']);
        $this->assertTrue($response2['success']);
    }

    public function test_mobile_checkout_success(): void
    {
        Http::fake([
            'sandbox.azampay.co.tz/azampay/mno/checkout' => Http::response([
                'success' => true,
                'transactionId' => 'TXN_123456',
                'message' => 'Payment request sent',
            ], 200),
            'sandbox.azampay.co.tz/AppRegistration/GenerateToken' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'test_token',
                ],
            ], 200),
        ]);

        $provider = new AzamPayProvider($this->shopId);
        $response = $provider->initiateSTK([
            'phone' => '255712345678',
            'amount' => 5000,
            'reference' => 'REF_TEST_001',
            'network' => 'Mpesa',
        ]);

        $this->assertEquals('pending', $response['status']);
        $this->assertEquals('TXN_123456', $response['external_id']);
        $this->assertEquals('Payment request sent', $response['message']);
    }

    public function test_mobile_checkout_failure(): void
    {
        Http::fake([
            'sandbox.azampay.co.tz/azampay/mno/checkout' => Http::response([
                'success' => false,
                'message' => 'Payment failed: Insufficient funds',
            ], 400),
            'sandbox.azampay.co.tz/AppRegistration/GenerateToken' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'test_token',
                ],
            ], 200),
        ]);

        $provider = new AzamPayProvider($this->shopId);
        $response = $provider->initiateSTK([
            'phone' => '255712345678',
            'amount' => 5000,
            'reference' => 'REF_TEST_002',
        ]);

        $this->assertEquals('failed', $response['status']);
        $this->assertNull($response['external_id']);
        $this->assertStringContainsString('failed', $response['message']);
    }

    public function test_callback_updates_payment(): void
    {
        $transaction = PaymentTransaction::create([
            'shop_id' => $this->shopId,
            'provider' => 'azampay',
            'channel' => 'stk',
            'amount' => 5000,
            'phone' => '255712345678',
            'status' => 'pending',
            'reference' => 'REF_CALLBACK_001',
            'external_id' => 'EXT_123',
        ]);

        $provider = new AzamPayProvider($this->shopId);
        $response = $provider->handleWebhook([
            'transactionstatus' => 'success',
            'externalreference' => 'EXT_123',
            'amount' => 5000,
            'msisdn' => '255712345678',
            'operator' => 'Mpesa',
        ]);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('EXT_123', $response['external_id']);
        $this->assertEquals(5000, $response['amount']);
        $this->assertEquals('255712345678', $response['phone']);
    }

    public function test_invalid_callback(): void
    {
        $provider = new AzamPayProvider($this->shopId);
        $response = $provider->handleWebhook([
            'transactionstatus' => 'unknown_status',
            'externalreference' => null,
            'amount' => 0,
        ]);

        $this->assertEquals('pending', $response['status']);
        $this->assertNull($response['external_id']);
    }

    public function test_callback_failed_status(): void
    {
        $provider = new AzamPayProvider($this->shopId);
        $response = $provider->handleWebhook([
            'transactionstatus' => 'failed',
            'externalreference' => 'EXT_FAIL_001',
            'amount' => 5000,
            'msisdn' => '255712345678',
            'message' => 'Payment declined',
        ]);

        $this->assertEquals('failed', $response['status']);
        $this->assertEquals('EXT_FAIL_001', $response['external_id']);
        $this->assertEquals('Payment declined', $response['message']);
    }

    public function test_b2c_disbursement_success(): void
    {
        Http::fake([
            'sandbox.azampay.co.tz/api/v1/azampay/disburse' => Http::response([
                'success' => true,
                'transactionId' => 'DISBURSE_123',
                'message' => 'Disbursement initiated',
            ], 200),
            'sandbox.azampay.co.tz/AppRegistration/GenerateToken' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'test_token',
                ],
            ], 200),
        ]);

        $provider = new AzamPayProvider($this->shopId);
        $response = $provider->sendB2C([
            'phone' => '255712345678',
            'amount' => 100000,
            'reference' => 'DISB_001',
            'description' => 'Loan disbursement',
            'network' => 'Mpesa',
        ]);

        $this->assertEquals('pending', $response['status']);
        $this->assertEquals('DISBURSE_123', $response['external_id']);
    }

    public function test_get_providers_list(): void
    {
        $provider = new AzamPayProvider($this->shopId);
        $providers = $provider->getProviders();

        $this->assertContains('Mpesa', $providers);
        $this->assertContains('Airtel', $providers);
        $this->assertContains('Tigo', $providers);
        $this->assertContains('Halopesa', $providers);
        $this->assertContains('Azampesa', $providers);
    }

    public function test_phone_number_formatting(): void
    {
        $provider = new AzamPayProvider($this->shopId);

        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('formatPhone');
        $method->setAccessible(true);

        $this->assertEquals('255712345678', $method->invoke($provider, '0712345678'));
        $this->assertEquals('255712345678', $method->invoke($provider, '255712345678'));
        $this->assertEquals('255612345678', $method->invoke($provider, '+255612345678'));
    }

    public function test_network_provider_mapping(): void
    {
        $provider = new AzamPayProvider($this->shopId);

        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('mapNetworkProvider');
        $method->setAccessible(true);

        $this->assertEquals('Mpesa', $method->invoke($provider, 'mpesa'));
        $this->assertEquals('Mpesa', $method->invoke($provider, 'M-Pesa'));
        $this->assertEquals('Airtel', $method->invoke($provider, 'airtel'));
        $this->assertEquals('Tigo', $method->invoke($provider, 'tigo'));
        $this->assertEquals('Halopesa', $method->invoke($provider, 'halopesa'));
        $this->assertEquals('Azampesa', $method->invoke($provider, 'azampesa'));
    }

    public function test_test_connection_returns_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'test_connection_token',
                ],
            ], 200),
        ]);

        $provider = new AzamPayProvider($this->shopId);
        $response = $provider->testConnection([]);

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('successful', $response['message']);
    }

    public function test_test_connection_returns_failure_on_missing_config(): void
    {
        PaymentConfig::where('shop_id', $this->shopId)
            ->where('provider', 'azampay')
            ->update(['config_json' => json_encode([])]);

        $provider = new AzamPayProvider($this->shopId);
        $response = $provider->testConnection([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Configuration incomplete', $response['message']);
    }
}
