<?php

namespace App\Services\Loans\InterestCalculators;

use InvalidArgumentException;

class ReducingBalanceInterest implements InterestCalculatorInterface
{
    /**
     * Reducing balance assumption (per-installment rate):
     * - Principal is equally distributed.
     * - Interest is calculated on remaining balance each period.
     */
    public function calculate(float $principal, float $annualInterestRate, int $numberOfInstallments): array
    {
        if ($numberOfInstallments < 1) {
            throw new InvalidArgumentException('Number of installments must be at least 1.');
        }

        $rate = $annualInterestRate / 100;
        $principalPerInstallment = $principal / $numberOfInstallments;

        $rows = [];
        $balance = $principal;

        for ($i = 1; $i <= $numberOfInstallments; $i++) {
            $interest = $balance * $rate;
            $rows[] = [
                'principal' => (float) $principalPerInstallment,
                'interest' => (float) $interest,
            ];

            $balance = max(0, $balance - $principalPerInstallment);
        }

        // Adjust rounding on last principal installment
        $totalPrincipal = array_sum(array_column($rows, 'principal'));
        $diff = $principal - $totalPrincipal;
        $rows[$numberOfInstallments - 1]['principal'] += $diff;

        return $rows;
    }
}
