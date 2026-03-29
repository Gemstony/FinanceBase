<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BeemProvider implements SmsProviderInterface
{
    protected $apiKey;
    protected $secretKey;
    protected $url;
    protected $senderId;

    public function __construct(string $apiKey, string $secretKey, string $senderId = 'DukaBase', string $url = 'https://apisms.beem.africa/v1/send')
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->senderId = $senderId;
        $this->url = $url;
    }

    /**
     * Send SMS via Beem API
     *
     * @param string $phone
     * @param string $message
     * @return array ['success' => bool, 'message_id' => string|null, 'response' => mixed]
     */
    public function send(string $phone, string $message): array
    {
        $formattedPhone = $this->formatPhoneNumber($phone);

        $postData = [
            'source_addr' => $this->senderId,
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
                $responseData = $response->json();
                $messageId = isset($responseData['message_id']) ? $responseData['message_id'] : null;
                
                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'response' => $responseData
                ];
            } else {
                Log::error('Beem SMS failed to ' . $formattedPhone . ': ' . $response->body());
                
                return [
                    'success' => false,
                    'message_id' => null,
                    'response' => json_decode($response->body(), true)
                ];
            }
        } catch (\Exception $e) {
            Log::error('Beem SMS exception for ' . $formattedPhone . ': ' . $e->getMessage());
            
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