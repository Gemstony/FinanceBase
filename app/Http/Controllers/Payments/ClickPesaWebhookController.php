<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\HandleWebhookJob;
use App\Models\PaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClickPesaWebhookController extends Controller
{
    /**
     * Handle ClickPesa webhook.
     */
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();

            Log::info('ClickPesa webhook received', $payload);

            // Validate provider exists
            $config = PaymentConfig::provider('clickpesa')->active()->first();
            if (!$config) {
                Log::warning('ClickPesa webhook received but provider not configured');
                return response()->json(['status' => 'error', 'message' => 'Provider not configured'], 400);
            }

            // Validate signature if provided
            if ($request->hasHeader('X-ClickPesa-Signature')) {
                $signature = $request->header('X-ClickPesa-Signature');
                $expectedSignature = hash_hmac('sha256', json_encode($payload), $config->secret_key);
                
                if (!hash_equals($expectedSignature, $signature)) {
                    Log::warning('ClickPesa webhook signature validation failed', [
                        'expected' => $expectedSignature,
                        'received' => $signature,
                    ]);
                    return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
                }
            }

            // Validate required fields
            if (!isset($payload['reference']) || !isset($payload['status'])) {
                Log::warning('ClickPesa webhook missing required fields', $payload);
                return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
            }

            // Dispatch webhook job
            HandleWebhookJob::dispatch('clickpesa', $payload)->onQueue('webhooks');

            return response()->json(['status' => 'success', 'message' => 'Webhook received']);
        } catch (\Exception $e) {
            Log::error('ClickPesa webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook handling failed'], 500);
        }
    }
}
