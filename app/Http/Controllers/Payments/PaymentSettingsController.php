<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use App\Models\PaymentTestLog;
use App\Models\SubShop;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentSettingsController extends Controller
{
    public function index()
    {
        $subshopId = session('subshop_id');

        if (! $subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('settings.payment_settings.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);

        return view('payments.payment_settings', compact('subshop'));
    }

    /**
     * Show the provider test page.
     */
    public function showTestPage()
    {
        $subshopId = session('subshop_id');

        if (! $subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('payments.configs.test')]);
        }

        $subshop = SubShop::findOrFail($subshopId);
        $configs = PaymentConfig::where('shop_id', $subshop->shop_id)
            ->active()
            ->orderBy('provider')
            ->get();

        $recentTests = PaymentTestLog::forShop($subshop->shop_id)
            ->latest()
            ->limit(10)
            ->get();

        return view('payments.configs.test', compact('configs', 'recentTests', 'subshop'));
    }

    /**
     * Run a provider connection test (AJAX endpoint).
     */
    public function testProvider(Request $request, PaymentManager $paymentManager)
    {
        $subshopId = session('subshop_id');

        if (! $subshopId) {
            return response()->json(['success' => false, 'message' => 'No subshop selected.'], 403);
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $validator = Validator::make($request->all(), [
            'config_id' => 'required|integer|exists:payment_configs,id',
            'amount' => 'required|numeric|min:1|max:999999',
            'phone_number' => 'required|string',
            'channel' => 'required|in:stk,b2c',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Load config and verify it belongs to this shop
        $config = PaymentConfig::where('id', $request->config_id)
            ->where('shop_id', $shopId)
            ->active()
            ->first();

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'Payment configuration not found or inactive for this shop.',
            ], 404);
        }

        try {
            $provider = $paymentManager->resolveProviderFromConfig($config);

            $testData = [
                'phone' => $request->phone_number,
                'amount' => $request->amount,
                'channel' => $request->channel,
            ];

            $result = $provider->testConnection($testData);

            // Log the test attempt
            PaymentTestLog::create([
                'shop_id' => $shopId,
                'config_id' => $config->id,
                'provider' => $config->provider,
                'test_data' => $testData,
                'provider_response' => $result['provider_response'] ?? [],
                'status' => $result['success'] ? 'success' : 'failed',
                'message' => $result['message'] ?? null,
            ]);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'provider' => $config->provider,
                'environment' => $config->environment,
                'provider_response' => $result['provider_response'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('Provider test failed', [
                'config_id' => $config->id,
                'provider' => $config->provider,
                'error' => $e->getMessage(),
            ]);

            PaymentTestLog::create([
                'shop_id' => $shopId,
                'config_id' => $config->id,
                'provider' => $config->provider,
                'test_data' => $request->only(['phone_number', 'amount', 'channel']),
                'provider_response' => ['exception' => class_basename($e)],
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test failed: '.$e->getMessage(),
                'provider' => $config->provider,
                'environment' => $config->environment,
                'provider_response' => [],
            ], 500);
        }
    }
}
