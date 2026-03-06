<?php

namespace App\Services\Loans\Fees;

use InvalidArgumentException;

class PercentageFee implements FeeCalculator
{
    public function calculate(float $baseAmount, float $feeValue): float
    {
        if ($baseAmount < 0) {
            throw new InvalidArgumentException('Base amount cannot be negative.');
        }

        if ($feeValue < 0) {
            throw new InvalidArgumentException('Fee percentage cannot be negative.');
        }

        return ($baseAmount * $feeValue) / 100;
    }
}
