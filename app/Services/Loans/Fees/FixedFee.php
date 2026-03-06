<?php

namespace App\Services\Loans\Fees;

use InvalidArgumentException;

class FixedFee implements FeeCalculator
{
    public function calculate(float $baseAmount, float $feeValue): float
    {
        if ($feeValue < 0) {
            throw new InvalidArgumentException('Fee value cannot be negative.');
        }

        return $feeValue;
    }
}
