<?php

declare(strict_types=1);

namespace App\Services\Loans\Ledger;

use App\Models\LoanTransactions;
use App\Services\Loans\Account\LoanAccountEngine;
use App\Services\Loans\Account\LoanBalanceCalculator;
use Carbon\Carbon;

class LoanLedgerRecorder
{
    public function __construct(
        private readonly LoanAccountEngine $accountEngine,
    ) {
    }

    /**
     * Record a transaction into the loan_transactions ledger.
     *
     * This method:
     * 1. Retrieves the current loan balance before this transaction.
     * 2. Computes the new balance after applying the transaction amounts.
     * 3. Inserts the transaction with balance_after for auditability.
     *
     * Balance calculation:
     * - For disbursements: balance increases (outstanding principal grows).
     * - For repayments/write-offs/recoveries: balance decreases.
     * - Interest/penalty/fee accruals do not affect principal balance but are recorded for completeness.
     *
     * @param array<string, mixed> $transactionData Structured data from LoanTransactionBuilder
     *
     * @return LoanTransactions
     */
    public function record(array $transactionData): LoanTransactions
    {
        // Retrieve current balance using LoanAccountEngine (read-only)
        $loanId = (int) $transactionData['loan_id'];
        $balanceBefore = $this->accountEngine->getLoanBalance($loanId);

        // Compute balance after the transaction
        $balanceAfter = $this->computeBalanceAfter($transactionData, $balanceBefore);

        // Prepare final record with balance_after and timestamps
        $record = array_merge($transactionData, [
            'balance_after' => round($balanceAfter, 2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Persist the immutable ledger entry
        return LoanTransactions::create($record);
    }

    /**
     * Compute the new loan balance after the transaction.
     *
     * Ledger entries must maintain a running balance so that:
     * - Statements can be generated.
     * - Auditors can trace balance changes chronologically.
     * - Regulatory reports can reconstruct balances at any point in time.
     *
     * @param array<string, mixed> $transactionData
     * @param float                $balanceBefore
     *
     * @return float
     */
    private function computeBalanceAfter(array $transactionData, float $balanceBefore): float
    {
        $type = $transactionData['transaction_type'];
        $principal = (float) ($transactionData['principal_amount'] ?? 0);
        $interest = (float) ($transactionData['interest_amount'] ?? 0);
        $penalty = (float) ($transactionData['penalty_amount'] ?? 0);
        $fee = (float) ($transactionData['fee_amount'] ?? 0);

        // Principal balance changes only for these transaction types
        switch ($type) {
            case 'loan_disbursement':
                // Disbursement increases outstanding principal balance
                $balanceBefore += $principal;
                break;

            case 'repayment':
                // Repayment reduces outstanding principal balance
                $balanceBefore -= $principal;
                break;

            case 'writeoff':
                // Write-off reduces outstanding principal balance (amount written off)
                $balanceBefore -= $principal;
                break;

            case 'recovery':
                // Recovery reduces outstanding principal balance (amount recovered)
                $balanceBefore -= $principal;
                break;

            // Accruals do not affect principal balance in this ledger; they are informational.
            case 'interest_accrual':
            case 'penalty_applied':
                // No change to principal balance
                break;

            default:
                // Defensive: unknown transaction types leave balance unchanged
                break;
        }

        // Ensure balance never goes negative due to rounding or data errors
        return max(0.0, $balanceBefore);
    }
}
