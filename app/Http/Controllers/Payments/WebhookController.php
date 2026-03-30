<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\HandleWebhookJob;
use App\Models\PaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle M-Pesa webhook.
     */
    public function mpesa(Request $request)
    {
        try {
            $payload = $request->all();

            Log::info('M-Pesa webhook received', $payload);

            // Validate provider exists
            $config = PaymentConfig::provider('mpesa')->active()->first();
            if (!$config) {
                Log::warning('M-Pesa webhook received but provider not configured');
                return response()->json(['status' => 'error', 'message' => 'Provider not configured'], 400);
            }

            // Dispatch webhook job
            HandleWebhookJob::dispatch('mpesa', $payload)->onQueue('webhooks');

            return response()->json(['status' => 'success', 'message' => 'Webhook received']);
        } catch (\Exception $e) {
            Log::error('M-Pesa webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook handling failed'], 500);
        }
    }

    /**
     * Handle Airtel webhook.
     */
    public function airtel(Request $request)
    {
        try {
            $payload = $request->all();

            Log::info('Airtel webhook received', $payload);

            // Validate provider exists
            $config = PaymentConfig::provider('airtel')->active()->first();
            if (!$config) {
                Log::warning('Airtel webhook received but provider not configured');
                return response()->json(['status' => 'error', 'message' => 'Provider not configured'], 400);
            }

            // Dispatch webhook job
            HandleWebhookJob::dispatch('airtel', $payload)->onQueue('webhooks');

            return response()->json(['status' => 'success', 'message' => 'Webhook received']);
        } catch (\Exception $e) {
            Log::error('Airtel webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook handling failed'], 500);
        }
    }

    /**
     * Handle Tigo webhook.
     */
    public function tigo(Request $request)
    {
        try {
            $payload = $request->all();

            Log::info('Tigo webhook received', $payload);

            // Validate provider exists
            $config = PaymentConfig::provider('tigo')->active()->first();
            if (!$config) {
                Log::warning('Tigo webhook received but provider not configured');
                return response()->json(['status' => 'error', 'message' => 'Provider not configured'], 400);
            }

            // Dispatch webhook job
            HandleWebhookJob::dispatch('tigo', $payload)->onQueue('webhooks');

            return response()->json(['status' => 'success', 'message' => 'Webhook received']);
        } catch (\Exception $e) {
            Log::error('Tigo webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook handling failed'], 500);
        }
    }
}
