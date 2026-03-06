<?php

namespace App\Services\Loans\Fees;

interface FeeCalculator
{
    public function calculate(float $baseAmount, float $feeValue): float;
}
