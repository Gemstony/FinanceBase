<?php

namespace App\Services\Loans\Penalties;

use InvalidArgumentException;

class OneTimePenaltyCalculator implements PenaltyCalculatorInterface
{
    /**
     * Penalty applied once after grace period elapses.
     */
    public function calculate(float $overdueAmount, float $penaltyRate): float
    {
        if ($overdueAmount < 0) {
            throw new InvalidArgumentException('Overdue amount cannot be negative.');
        }

        if ($penaltyRate < 0) {
            throw new InvalidArgumentException('Penalty rate cannot be negative.');
        }

        return $penaltyRate;
    }
}
