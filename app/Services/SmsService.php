<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SmsLog;

class SmsService
{
    protected $apiKey;
    protected $secretKey;
    protected $url;

    public function __construct()
    {
        $this->apiKey = config('services.beem.api_key');
        $this->secretKey = config('services.beem.secret_key');
        $this->url = 'https://apisms.beem.africa/v1/send';
    }

    public function send(string $phone, string $message): bool
    {
        return $this->sendSms($phone, $message);
    }

    /**
     * Format phone number to international format (255...)
     * Handles numbers starting with 0 (local) or already international
     */
    public function formatPhoneNumber($phone)
    {
        // Remove any spaces, dashes, or other non-numeric characters
        $phone = preg_replace('/\D/', '', $phone);

        // If starts with 0, replace with 255
        if (str_starts_with($phone, '0')) {
            $phone = '255' . substr($phone, 1);
        }

        // If doesn't start with 255, assume it's already international or add 255
        if (!str_starts_with($phone, '255')) {
            $phone = '255' . $phone;
        }

        return $phone;
    }

    /**
     * Send SMS to a single recipient
     * Optionally pass $context = [
     *   'shop_id' => int|null,
     *   'subshop_id' => int|null,
     *   'owner_id' => int|null,
     *   'type' => string|null
     * ]
     */
    public function sendSms($phone, $message, array $context = null)
    {
        $formattedPhone = $this->formatPhoneNumber($phone);

        $sensitive = is_array($context) && ($context['sensitive'] ?? false) === true;
        $messageForStorage = $sensitive ? '[REDACTED]' : $message;

        $log = null;
        try {
            // Pre-create log as queued if logging is desired
            if (is_array($context)) {
                $log = SmsLog::create([
                    'shop_id' => $context['shop_id'] ?? null,
                    'subshop_id' => $context['subshop_id'] ?? null,
                    'owner_id' => $context['owner_id'] ?? null,
                    'phone' => $formattedPhone,
                    'message' => $messageForStorage,
                    'type' => $context['type'] ?? null,
                    'status' => 'queued',
                    'provider' => 'beem',
                ]);
            }
        } catch (\Throwable $e) {
            // Logging should never block SMS sending
            Log::warning('Failed to pre-create SMS log: ' . $e->getMessage());
        }

        $postData = [
            'source_addr' => 'DukaBase',
            'encoding' => 0,
            'schedule_time' => '',
            'message' => $message,
            'recipients' => [
                [
                    'recipient_id' => '1',
                    'dest_addr' => $formattedPhone
                ]
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':' . $this->secretKey),
                'Content-Type' => 'application/json',
            ])->post($this->url, $postData);

            if ($response->successful()) {
                Log::info('SMS sent successfully to ' . $formattedPhone);
                if ($log) {
                    try {
                        $log->update([
                            'status' => 'sent',
                            'provider_response' => $response->body(),
                            'sent_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to update SMS log (success): ' . $e->getMessage());
                    }
                }
                return true;
            } else {
                Log::error('SMS failed to ' . $formattedPhone . ': ' . $response->body());
                if ($log) {
                    try {
                        $log->update([
                            'status' => 'failed',
                            'provider_response' => $response->body(),
                            'sent_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to update SMS log (failure): ' . $e->getMessage());
                    }
                }
                return false;
            }
        } catch (\Exception $e) {
            Log::error('SMS exception for ' . $formattedPhone . ': ' . $e->getMessage());
            if ($log) {
                try {
                    $log->update([
                        'status' => 'error',
                        'provider_response' => $e->getMessage(),
                        'sent_at' => now(),
                    ]);
                } catch (\Throwable $te) {
                    Log::warning('Failed to update SMS log (exception): ' . $te->getMessage());
                }
            }
            return false;
        }
    }

    /**
     * Send password reset SMS to user
     * 
     * @param string $phoneNumber The user's phone number
     * @param string $newPassword The new password that was set
     * @param array|null $context Optional context for logging (shop_id, subshop_id, owner_id)
     * @return bool True if SMS was sent successfully, false otherwise
     */
    public function sendPasswordResetSms(string $phoneNumber, string $newPassword, array $context = null): bool
    {
        $message = "Your password has been successfully reset. Your new password is: {$newPassword}. Please keep it secure and change it after first login. Delete This Message Thank you.";
        
        // Mark as sensitive for logging purposes (don't store actual password in logs)
        if ($context === null) {
            $context = [];
        }
        $context['sensitive'] = true;
        $context['type'] = 'password_reset';
        
        return $this->sendSms($phoneNumber, $message, $context);
    }
}
