<?php

namespace App\Services;

class PrinterService
{
    public function testConnection(string $ip, int $port = 9100, float $timeout = 1.0): array
    {
        $start = microtime(true);
        $errno = 0; $errstr = '';
        $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if ($fp) {
            fclose($fp);
            return [
                'ok' => true,
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            ];
        }
        return [
            'ok' => false,
            'error' => $errstr ?: 'Unable to connect',
        ];
    }

    public function autoDetect(int $port = 9100, float $timeout = 0.15, ?string $clientIp = null): array
    {
        $candidates = [];
        $prefixes = [];

        // Try to infer /24 from client IP if private address
        if ($clientIp && filter_var($clientIp, FILTER_VALIDATE_IP)) {
            if (preg_match('/^(10|192\.168|172\.(1[6-9]|2[0-9]|3[0-1]))\./', $clientIp)) {
                $parts = explode('.', $clientIp);
                if (count($parts) === 4) {
                    $prefixes[] = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.'; // /24
                }
            }
        }

        // Fallback common home/office subnets
        if (empty($prefixes)) {
            $prefixes = ['192.168.0.', '192.168.1.'];
        }

        foreach ($prefixes as $prefix) {
            // Prioritize mid-range hosts (common DHCP ranges)
            $order = array_merge(range(50, 200), range(1, 49), range(201, 254));
            foreach ($order as $i) {
                $candidates[] = $prefix . $i;
            }
        }
        $found = [];
        $deadline = microtime(true) + 5.0; // hard 5s budget
        foreach ($candidates as $ip) {
            $errno = 0; $errstr = '';
            $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if ($fp) {
                fclose($fp);
                $found[] = [
                    'ip_address' => $ip,
                    'port' => $port,
                    'name' => null,
                ];
            }
            if (microtime(true) > $deadline) {
                break; // stop scanning when time budget exceeded
            }
        }
        return $found;
    }
}
