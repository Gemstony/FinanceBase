<?php

namespace App\Services\Loans\InterestCalculators;

interface InterestCalculatorInterface
{
    /**
     * @return array<int, array{principal: float, interest: float}>
     */
    public function calculate(float $principal, float $annualInterestRate, int $numberOfInstallments): array;
}
