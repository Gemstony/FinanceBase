<?php

namespace App\Services\Loans;

use Carbon\Carbon;
use InvalidArgumentException;

class ScheduleDateGenerator
{
    /**
     * @return array<int, Carbon>
     */
    public function generate(Carbon $disbursementDate, string $repaymentFrequency, int $installmentsCount): array
    {
        if ($installmentsCount < 1) {
            throw new InvalidArgumentException('Installments count must be at least 1.');
        }

        // Normalize input (DB might store codes like DLY/WKY/MTH, while UI might store daily/weekly/monthly)
        $frequency = strtoupper(trim($repaymentFrequency));
        $dates = [];
        $current = $disbursementDate->copy();

        for ($i = 1; $i <= $installmentsCount; $i++) {
            $current = $this->nextDate($current, $frequency);
            $dates[] = $current->copy();
        }

        return $dates;
    }

    private function nextDate(Carbon $from, string $frequency): Carbon
    {
        return match ($frequency) {
            'DAILY', 'DLY' => $from->copy()->addDay(),
            'WEEKLY', 'WKY' => $from->copy()->addDays(7),
            // Month-based schedules should avoid overflow (e.g. Jan 31st -> Feb 28/29)
            'MONTHLY', 'MTH' => $from->copy()->addMonthNoOverflow(),
            default => throw new InvalidArgumentException("Unsupported repayment frequency: {$frequency}"),
        };
    }
}
