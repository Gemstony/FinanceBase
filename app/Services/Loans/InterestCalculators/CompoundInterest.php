<?php

namespace App\Services\Loans\InterestCalculators;

use InvalidArgumentException;

class CompoundInterest implements InterestCalculatorInterface
{
    /**
     * Compound interest assumption (per-installment compounding rate):
     * - Uses amortized equal payment (annuity-style) schedule.
     * - Interest compounds each period on remaining balance.
     */
    public function calculate(float $principal, float $annualInterestRate, int $numberOfInstallments): array
    {
        if ($numberOfInstallments < 1) {
            throw new InvalidArgumentException('Number of installments must be at least 1.');
        }

        $rate = $annualInterestRate / 100;

        if ($rate <= 0) {
            $principalPerInstallment = $principal / $numberOfInstallments;
            $rows = [];
            for ($i = 1; $i <= $numberOfInstallments; $i++) {
                $rows[] = ['principal' => (float) $principalPerInstallment, 'interest' => 0.0];
            }

            $totalPrincipal = array_sum(array_column($rows, 'principal'));
            $rows[$numberOfInstallments - 1]['principal'] += ($principal - $totalPrincipal);
            return $rows;
        }

        // Annuity payment formula: PMT = P*r*(1+r)^n / ((1+r)^n - 1)
        $pow = pow(1 + $rate, $numberOfInstallments);
        $payment = ($principal * $rate * $pow) / ($pow - 1);

        $rows = [];
        $balance = $principal;

        for ($i = 1; $i <= $numberOfInstallments; $i++) {
            $interest = $balance * $rate;
            $principalDue = $payment - $interest;

            $rows[] = [
                'principal' => (float) $principalDue,
                'interest' => (float) $interest,
            ];

            $balance = max(0, $balance - $principalDue);
        }

        // Adjust rounding on last installment so we close out the balance
        $totalPrincipal = array_sum(array_column($rows, 'principal'));
        $diff = $principal - $totalPrincipal;
        $rows[$numberOfInstallments - 1]['principal'] += $diff;

        return $rows;
    }
}
