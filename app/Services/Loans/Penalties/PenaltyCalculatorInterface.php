<?php

namespace App\Services\Loans\Penalties;

interface PenaltyCalculatorInterface
{
    /**
     * @param float $overdueAmount Amount subject to penalties.
     * @param float $penaltyRate Penalty rate/value (interpretation is decided by the engine).
     */
    public function calculate(float $overdueAmount, float $penaltyRate): float;
}
