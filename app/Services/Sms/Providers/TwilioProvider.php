<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioProvider implements SmsProviderInterface
{
    protected $accountSid;
    protected $authToken;
    protected $fromNumber;
    protected $url;

    public function __construct(string $accountSid, string $authToken, string $fromNumber)
    {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->fromNumber = $fromNumber;
        $this->url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
    }

    /**
     * Send SMS via Twilio API
     *
     * @param string $phone
     * @param string $message
     * @return array ['success' => bool, 'message_id' => string|null, 'response' => mixed]
     */
    public function send(string $phone, string $message): array
    {
        $formattedPhone = $this->formatPhoneNumber($phone);

        $postData = [
            'From' => $this->fromNumber,
            'To' => $formattedPhone,
            'Body' => $message
        ];

        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post($this->url, $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                $messageId = isset($responseData['sid']) ? $responseData['sid'] : null;
                
                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'response' => $responseData
                ];
            } else {
                Log::error('Twilio SMS failed to ' . $formattedPhone . ': ' . $response->body());
                
                return [
                    'success' => false,
                    'message_id' => null,
                    'response' => json_decode($response->body(), true)
                ];
            }
        } catch (\Exception $e) {
            Log::error('Twilio SMS exception for ' . $formattedPhone . ': ' . $e->getMessage());
            
            return [
                'success' => false,
                'message_id' => null,
                'response' => ['error' => $e->getMessage()]
            ];
        }
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
}