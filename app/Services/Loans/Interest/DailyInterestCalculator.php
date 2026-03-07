<?php

namespace App\Services\Loans\Interest;

class DailyInterestCalculator
{
    /**
     * Calculate daily interest using the standard banking accrual formula.
     *
     * Financial logic:
     * daily_interest = principalBalance × (annualRate / 365)
     *
     * Inputs:
     * - $principalBalance: outstanding principal amount.
     * - $annualRate: annual interest rate as a percentage (e.g. 18 for 18%).
     *
     * Notes:
     * - Returned value is never negative.
     * - Uses BCMath when available for more stable decimal arithmetic.
     */
    public function calculateDailyInterest(float $principalBalance, float $annualRate): float
    {
        $principal = max(0.0, $principalBalance);
        $ratePercent = max(0.0, $annualRate);

        if ($principal <= 0 || $ratePercent <= 0) {
            return 0.0;
        }

        // Convert percent to decimal rate.
        $annualRateDecimal = $ratePercent / 100;

        // Prefer BCMath if available.
        if (function_exists('bcdiv') && function_exists('bcmul')) {
            // Keep higher scale internally then round.
            $scale = 10;
            $principalStr = number_format($principal, 10, '.', '');
            $rateStr = number_format($annualRateDecimal, 10, '.', '');

            $dailyRate = bcdiv($rateStr, '365', $scale);
            $dailyInterest = bcmul($principalStr, $dailyRate, $scale);

            return round(max(0.0, (float) $dailyInterest), 6);
        }

        $dailyInterest = $principal * ($annualRateDecimal / 365);

        return round(max(0.0, $dailyInterest), 6);
    }
}
