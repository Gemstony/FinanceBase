<?php

namespace App\Services\Loans\InterestCalculators;

use InvalidArgumentException;

class FlatInterest implements InterestCalculatorInterface
{
    /**
     * Flat rate assumption (per-installment rate):
     * - Interest per installment stays constant.
     * - Principal is equally distributed.
     */
    public function calculate(float $principal, float $annualInterestRate, int $numberOfInstallments): array
    {
        if ($numberOfInstallments < 1) {
            throw new InvalidArgumentException('Number of installments must be at least 1.');
        }

        $rate = $annualInterestRate / 100;
        $principalPerInstallment = $principal / $numberOfInstallments;
        $interestPerInstallment = $principal * $rate;

        $rows = [];
        for ($i = 1; $i <= $numberOfInstallments; $i++) {
            $rows[] = [
                'principal' => (float) $principalPerInstallment,
                'interest' => (float) $interestPerInstallment,
            ];
        }

        // Adjust rounding on last principal installment
        $totalPrincipal = array_sum(array_column($rows, 'principal'));
        $diff = $principal - $totalPrincipal;
        $rows[$numberOfInstallments - 1]['principal'] += $diff;

        return $rows;
    }
}
