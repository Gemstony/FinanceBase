<?php

declare(strict_types=1);

namespace App\Services\Loans\Account;

use App\Models\Loans;

class LoanAccountEngine
{
    public function __construct(
        private readonly LoanBalanceCalculator $balanceCalculator,
        private readonly LoanOverdueCalculator $overdueCalculator,
        private readonly LoanNextInstallmentResolver $nextInstallmentResolver,
    ) {
    }

    /**
     * Get a real-time summary of the financial state of a loan.
     *
     * IMPORTANT:
     * - This method is read-only and must never mutate persisted financial records.
     * - It aggregates data from schedule, accruals, penalties and allocations.
     */
    public function getLoanAccountSummary(Loans $loan): array
    {
        $balances = $this->balanceCalculator->calculateBalances($loan);
        $overdue = $this->overdueCalculator->calculateOverdue($loan);
        $nextInstallment = $this->nextInstallmentResolver->getNextInstallment($loan);

        return array_merge(
            $balances,
            $overdue,
            [
                'next_installment' => $nextInstallment,
            ]
        );
    }

    public function getLoanBalance(int $loanId): float
    {
        $loan = Loans::query()->findOrFail($loanId);
        $balances = $this->balanceCalculator->calculateBalances($loan);

        return (float) ($balances['principal_outstanding'] ?? 0.0);
    }
}
