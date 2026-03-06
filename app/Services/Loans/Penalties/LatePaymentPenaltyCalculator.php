<?php

namespace App\Services\Loans\Penalties;

use InvalidArgumentException;

class LatePaymentPenaltyCalculator implements PenaltyCalculatorInterface
{
    /**
     * Late payment penalty applied once after due date.
     */
    public function calculate(float $overdueAmount, float $penaltyRate): float
    {
        if ($overdueAmount < 0) {
            throw new InvalidArgumentException('Overdue amount cannot be negative.');
        }

        if ($penaltyRate < 0) {
            throw new InvalidArgumentException('Penalty rate cannot be negative.');
        }

        // This calculator applies the penalty only once.
        return $penaltyRate;
    }
}
