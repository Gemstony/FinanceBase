<?php

namespace App\Services\Sms;

interface SmsProviderInterface
{
    /**
     * Send SMS message
     *
     * @param string $phone
     * @param string $message
     * @return array
     */
    public function send(string $phone, string $message): array;
}