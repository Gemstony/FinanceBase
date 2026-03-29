<?php

namespace App\Services\Sms;

use App\Models\SmsConfig;
use App\Models\SmsEvent;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Sms\Providers\BeemProvider;
use App\Services\Sms\Providers\TwilioProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendSmsJob;

class SmsManager
{
    protected $templateEngine;

    public function __construct(SmsTemplateEngine $templateEngine)
    {
        $this->templateEngine = $templateEngine;
    }

    /**
     * Send SMS based on event
     *
     * @param string $event
     * @param array $payload
     * @return bool True if SMS was queued successfully, false otherwise
     */
    public function sendEvent(string $event, array $payload): bool
    {
        // Validate required payload fields
        if (!isset($payload['shop_id'])) {
            Log::error('SMS Manager: shop_id is required in payload');
            return false;
        }

        if (!isset($payload['phone'])) {
            Log::error('SMS Manager: phone is required in payload');
            return false;
        }

        // Get SMS event configuration for this shop and event
        $smsEvent = SmsEvent::where('shop_id', $payload['shop_id'])
            ->where('event_name', $event)
            ->enabled()
            ->first();

        if (!$smsEvent) {
            Log::info("SMS Manager: No enabled SMS event found for shop {$payload['shop_id']} and event {$event}");
            return false;
        }

        // Get template
        $template = $smsEvent->template;
        if (!$template || !$template->is_active) {
            Log::warning("SMS Manager: No active template found for event {$event}");
            return false;
        }

        // Render message
        $data = $payload['data'] ?? [];
        $message = $this->templateEngine->render($template->message_template, $data);

        // Get SMS config (provider) for this shop
        $config = SmsConfig::where('shop_id', $payload['shop_id'])
            ->where('is_active', true)
            ->first();

        // If no active config, try to get default
        if (!$config) {
            $config = SmsConfig::where('shop_id', $payload['shop_id'])
                ->where('is_default', true)
                ->first();
        }

        if (!$config) {
            Log::error("SMS Manager: No SMS configuration found for shop {$payload['shop_id']}");
            return false;
        }

        // Check rate limiting (basic implementation)
        // In a production system, you'd want to use Redis or similar for distributed rate limiting
        $this->checkRateLimit($config);

        // Prepare job payload
        $jobPayload = [
            'shop_id' => $payload['shop_id'],
            'subshop_id' => $payload['subshop_id'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'phone' => $payload['phone'],
            'message' => $message,
            'template_id' => $template->id,
            'event' => $event,
            'provider' => $config->provider,
            'config_id' => $config->id,
            'sensitive' => $payload['sensitive'] ?? false,
        ];

        // Dispatch job to queue
        SendSmsJob::dispatch($jobPayload)->onQueue('sms');

        Log::info("SMS Manager: Queued SMS for shop {$payload['shop_id']}, event {$event}, phone {$payload['phone']}");
        return true;
    }

    /**
     * Check rate limiting for SMS config
     *
     * @param SmsConfig $config
     * @return void
     */
    protected function checkRateLimit(SmsConfig $config): void
    {
        // Basic rate limiting - in production, use Redis or similar
        // For now, we'll just log if we're approaching limits
        // A full implementation would track sent messages per minute
        // This is a placeholder for the rate limiting logic
    }

    /**
     * Get provider instance based on config
     *
     * @param SmsConfig $config
     * @return SmsProviderInterface
     */
    protected function getProvider(SmsConfig $config): SmsProviderInterface
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