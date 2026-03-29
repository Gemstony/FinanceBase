<?php

namespace App\Jobs;

use App\Models\SmsConfig;
use App\Models\SmsLog;
use App\Services\Sms\Providers\BeemProvider;
use App\Services\Sms\Providers\TwilioProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;
    protected $tries = 3;
    protected $backoff = [60, 120, 180]; // 1 min, 2 min, 3 min

    /**
     * Create a new job instance.
     *
     * @param array $payload
     * @return void
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Extract payload data
        $shopId = $this->payload['shop_id'];
        $subshopId = $this->payload['subshop_id'] ?? null;
        $userId = $this->payload['user_id'] ?? null;
        $phone = $this->payload['phone'];
        $message = $this->payload['message'];
        $templateId = $this->payload['template_id'];
        $event = $this->payload['event'];
        $provider = $this->payload['provider'];
        $configId = $this->payload['config_id'];
        $sensitive = $this->payload['sensitive'] ?? false;

        // Find SMS log to update
        $log = SmsLog::where('shop_id', $shopId)
            ->where('phone', $phone)
            ->where('message', $sensitive ? '[REDACTED]' : $message)
            ->where('template_id', $templateId)
            ->where('event', $event)
            ->where('status', 'queued')
            ->latest()
            ->first();

        if (!$log) {
            // Create log if it doesn't exist
            $log = SmsLog::create([
                'shop_id' => $shopId,
                'subshop_id' => $subshopId,
                'user_id' => $userId,
                'phone' => $phone,
                'message' => $sensitive ? '[REDACTED]' : $message,
                'template_id' => $templateId,
                'event' => $event,
                'status' => 'queued',
                'provider' => $provider,
            ]);
        }

        // Increment attempts
        $log->increment('attempts');

        try {
            // Get SMS config
            $config = SmsConfig::find($configId);
            if (!$config) {
                throw new \Exception("SMS configuration not found for ID {$configId}");
            }

            // Get provider instance
            $providerInstance = $this->getProviderInstance($config);

            // Send SMS
            $result = $providerInstance->send($phone, $message);

            if ($result['success']) {
                // Update log as sent
                $log->update([
                    'status' => 'sent',
                    'provider_message_id' => $result['message_id'],
                    'provider_response' => json_encode($result['response']),
                    'sent_at' => now(),
                ]);

                Log::info("SMS sent successfully to {$phone} via {$provider}");
            } else {
                // Update log as failed
                $log->update([
                    'status' => 'failed',
                    'provider_response' => json_encode($result['response']),
                    'sent_at' => now(),
                ]);

                Log::warning("SMS failed to {$phone} via {$provider}: " . json_encode($result['response']));

                // If we have attempts left, let it fail so queue can retry
                if ($this->attempts() < $this->maxTries()) {
                    throw new \Exception("SMS failed: " . json_encode($result['response']));
                }
            }
        } catch (\Exception $e) {
            // Update log as failed/error
            $log->update([
                'status' => $this->attempts() < $this->maxTries() ? 'failed' : 'error',
                'error_code' => $e->getCode() ?? 'UNKNOWN',
                'error_message' => $e->getMessage(),
                'provider_response' => isset($result) ? json_encode($result['response'] ?? null) : null,
                'sent_at' => now(),
            ]);

            Log::error("SMS job failed for {$phone}: {$e->getMessage()}");

            // If we haven't exceeded max attempts, let the job fail so it can be retried
            if ($this->attempts() < $this->maxTries()) {
                throw $e;
            }
        }
    }

    /**
     * Get provider instance based on config
     *
     * @param SmsConfig $config
     * @return SmsProviderInterface
     */
    protected function getProviderInstance(SmsConfig $config): SmsProviderInterface
    {
        switch ($config->provider) {
            case 'twilio':
                return new TwilioProvider(
                    $config->api_key,
                    $config->secret_key,
                    $config->sender_id
                );
            case 'beem':
            default:
                return new BeemProvider(
                    $config->api_key,
                    $config->secret_key,
                    $config->sender_id,
                    $config->api_url
                );
        }
    }
}