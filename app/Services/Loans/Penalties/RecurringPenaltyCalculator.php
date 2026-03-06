<?php

namespace App\Services\Loans\Penalties;

use InvalidArgumentException;

class RecurringPenaltyCalculator implements PenaltyCalculatorInterface
{
    public function __construct(private readonly int $occurrences)
    {
        if ($this->occurrences < 1) {
            throw new InvalidArgumentException('Occurrences must be at least 1.');
        }
    }

    /**
     * Recurring penalty (e.g. daily/weekly/monthly).
     */
    public function calculate(float $overdueAmount, float $penaltyRate): float
    {
        if ($overdueAmount < 0) {
            throw new InvalidArgumentException('Overdue amount cannot be negative.');
        }

        if ($penaltyRate < 0) {
            throw new InvalidArgumentException('Penalty rate cannot be negative.');
        }

        return $penaltyRate * $this->occurrences;
    }
}
