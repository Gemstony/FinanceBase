<?php

declare(strict_types=1);

namespace App\Services\Loans\Restructure;

use App\Models\Loans;
use App\Services\Loans\Account\LoanAccountEngine;
use RuntimeException;

class OutstandingBalanceCalculator
{
    public function __construct(private readonly LoanAccountEngine $loanAccountEngine)
    {
    }

    /**
     * Calculate outstanding balances as at restructure date.
     *
     * We use the LoanAccountEngine (read-only) to ensure balances are consistent
     * with the rest of the platform (accruals, penalties, allocations).
     *
     * @return array{principal:float,interest:float,penalty:float,total:float}
     */
    public function calculate(Loans $loan): array
    {
        $summary = $this->loanAccountEngine->getLoanAccountSummary($loan);

        $principal = (float) ($summary['principal_outstanding'] ?? 0.0);
        $interest = (float) ($summary['interest_outstanding'] ?? 0.0);
        $penalty = (float) ($summary['penalties_outstanding'] ?? 0.0);

        $total = round($principal + $interest + $penalty + (float) ($summary['fees_outstanding'] ?? 0.0), 2);

        if ($principal < 0 || $interest < 0 || $penalty < 0) {
            throw new RuntimeException('Unable to calculate outstanding balances: negative component detected.');
        }

        return [
            'principal' => round($principal, 2),
            'interest' => round($interest, 2),
            'penalty' => round($penalty, 2),
            'total' => round($total, 2),
        ];
    }
}
