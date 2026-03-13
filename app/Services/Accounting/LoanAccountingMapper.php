<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\BankAccounts;
use App\Models\Loans;

class LoanAccountingMapper
{
    public function __construct(
        private readonly JournalEntryBuilder $builder,
    ) {
    }

    /**
     * Build journal entry lines for a loan disbursement.
     *
     * Financial meaning:
     * - Debit: Increase Loan Portfolio (asset) because the institution now has a receivable.
     * - Credit: Decrease Cash/Bank (asset) because funds are released to the borrower.
     *
     * @param Loans  $loan   The loan being disbursed
     * @param float  $amount Disbursement amount
     * @param int    $creditAccountId Chart of Accounts ID for the bank/cash account to credit
     *
     * @return array Journal lines ready for validation/posting
     */
    public function buildLoanDisbursementEntry(Loans $loan, float $amount, int $creditAccountId): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        // Debit: Loan Portfolio account (principal_account_id on loan)
        $builder->addDebit(
            (int) $loan->principal_account_id,
            $amount,
            "Loan disbursement – {$loan->loan_code}"
        );

        $builder->addCredit(
            $creditAccountId,
            $amount,
            "Cash disbursement – {$loan->loan_code}"
        );

        return $builder->getLines();
    }

    /**
     * Build journal entry lines for a loan repayment.
     *
     * Financial meaning:
     * - Debit: Increase Cash (asset) because cash is received.
     * - Credit: Decrease Loan Portfolio (principal portion).
     * - Credit: Recognize Interest Income (interest portion).
     * - Credit: Recognize Penalty Income (penalty portion).
     * - Credit: Recognize Fee Income (fee portion).
     *
     * @param array $allocation Expected keys: principal_amount, interest_amount, fee_amount, penalty_amount, loan_id
     *
     * @return array Journal lines ready for validation/posting
     */
    public function buildLoanRepaymentEntry(array $allocation): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $principal = (float) ($allocation['principal_amount'] ?? 0);
        $interest = (float) ($allocation['interest_amount'] ?? 0);
        $fee = (float) ($allocation['fee_amount'] ?? 0);
        $penalty = (float) ($allocation['penalty_amount'] ?? 0);

        $loanId = (int) ($allocation['loan_id'] ?? 0);
        $loan = $loanId > 0
            ? Loans::query()->whereKey($loanId)->first()
            : null;

        $bankAccountId = !empty($allocation['bank_account_id']) ? (int) $allocation['bank_account_id'] : null;
        $paymentMethod = (string) ($allocation['payment_method'] ?? 'cash');

        $cashAccountId = 1;
        if ($bankAccountId) {
            $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
            $linked = (int) ($bank?->chart_of_account_id ?? 0);
            if ($linked > 0) {
                $cashAccountId = $linked;
            }
        }
        if (!$bankAccountId) {
            $cashAccountId = $this->resolveCashAccountId($paymentMethod);
        }

        // Debit: Cash account (placeholder; replace with actual cash account ID)
        $totalCash = $principal + $interest + $fee + $penalty;
        if ($totalCash > 0) {
            $builder->addDebit(
                $cashAccountId,
                $totalCash,
                'Loan repayment – cash received'
            );
        }

        // Credit: Loan Portfolio (principal)
        if ($principal > 0) {
            $builder->addCredit(
                (int) ($loan?->principal_account_id ?: 1),
                $principal,
                'Loan repayment – principal portion'
            );
        }

        // Credit: Interest Income
        if ($interest > 0) {
            $builder->addCredit(
                (int) ($loan?->interest_income_account_id ?: 1),
                $interest,
                'Loan repayment – interest income'
            );
        }

        // Credit: Penalty Income
        if ($penalty > 0) {
            $builder->addCredit(
                (int) ($loan?->penalty_income_account_id ?: 1),
                $penalty,
                'Loan repayment – penalty income'
            );
        }

        // Credit: Fee Income
        if ($fee > 0) {
            $builder->addCredit(
                (int) ($loan?->fee_income_account_id ?: 1),
                $fee,
                'Loan repayment – fee income'
            );
        }

        return $builder->getLines();
    }

    /**
     * Build journal entry lines for a loan write-off.
     *
     * Financial meaning:
     * - Debit: Recognize Loan Loss Expense (expense) because the receivable is deemed uncollectible.
     * - Credit: Decrease Loan Portfolio (asset) to remove the receivable.
     *
     * @param Loans  $loan   The loan being written off
     * @param float  $amount Write-off amount (typically outstanding principal)
     *
     * @return array Journal lines ready for validation/posting
     */
    public function buildLoanWriteOffEntry(Loans $loan, float $amount): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        // Debit: Loan Loss Expense account (write_off_expense_account_id on loan)
        $builder->addDebit(
            (int) $loan->write_off_expense_account_id,
            $amount,
            "Loan write-off – {$loan->loan_code}"
        );

        // Credit: Loan Portfolio (principal_account_id)
        $builder->addCredit(
            (int) $loan->principal_account_id,
            $amount,
            "Write-off of loan principal – {$loan->loan_code}"
        );

        return $builder->getLines();
    }

    /**
     * Build journal entry lines for a loan recovery (post-write-off collection).
     *
     * Financial meaning:
     * - Debit: Increase Cash (asset) because cash is received.
     * - Credit: Recognize Recovery Income (income) because it offsets previous loss.
     *
     * @param float $amount Recovery amount received
     *
     * @return array Journal lines ready for validation/posting
     */
    public function buildLoanRecoveryEntry(float $amount): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        // Debit: Cash account (placeholder; replace with actual cash account ID)
        $builder->addDebit(
            1, // placeholder cash account ID
            $amount,
            'Loan recovery – cash received'
        );

        // Credit: Recovery Income account (placeholder; replace with actual recovery income account ID)
        $builder->addCredit(
            1, // placeholder recovery income account ID
            $amount,
            'Loan recovery – income recognized'
        );

        return $builder->getLines();
    }

    public function buildSecurityDepositCollectedEntry(Loans $loan, float $amount, string $paymentMethod): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $cashAccountId = $this->resolveCashAccountId($paymentMethod);
        $liabilityAccountId = (int) $loan->customer_security_deposit_account_id;

        $builder->addDebit(
            $cashAccountId,
            $amount,
            "Security deposit collected – {$loan->loan_code}"
        );

        $builder->addCredit(
            $liabilityAccountId,
            $amount,
            "Security deposit liability – {$loan->loan_code}"
        );

        return $builder->getLines();
    }

    public function buildSecurityDepositRefundedEntry(Loans $loan, float $amount, string $paymentMethod): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $cashAccountId = $this->resolveCashAccountId($paymentMethod);
        $liabilityAccountId = (int) $loan->customer_security_deposit_account_id;

        $builder->addDebit(
            $liabilityAccountId,
            $amount,
            "Security deposit refunded – {$loan->loan_code}"
        );

        $builder->addCredit(
            $cashAccountId,
            $amount,
            "Security deposit cash refund – {$loan->loan_code}"
        );

        return $builder->getLines();
    }

    public function buildSecurityDepositAppliedEntry(Loans $sourceLoan, Loans $targetLoan, float $amount): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $liabilityAccountId = (int) $sourceLoan->customer_security_deposit_account_id;

        $builder->addDebit(
            $liabilityAccountId,
            $amount,
            "Security deposit applied – {$sourceLoan->loan_code}"
        );

        $builder->addCredit(
            (int) $targetLoan->principal_account_id,
            $amount,
            "Applied to loan receivable – {$targetLoan->loan_code}"
        );

        return $builder->getLines();
    }

    public function buildSecurityDepositForfeitedEntry(Loans $loan, float $amount): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $liabilityAccountId = (int) $loan->customer_security_deposit_account_id;
        $recoveryAccountId = (int) ($loan->fee_income_account_id ?: ($loan->interest_income_account_id ?: 1));

        $builder->addDebit(
            $liabilityAccountId,
            $amount,
            "Security deposit forfeited – {$loan->loan_code}"
        );

        $builder->addCredit(
            $recoveryAccountId,
            $amount,
            "Security deposit forfeiture income – {$loan->loan_code}"
        );

        return $builder->getLines();
    }

    public function buildDepositReceivedEntry(float $amount, string $paymentMethod, int $customerDepositsLiabilityAccountId): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $cashAccountId = $this->resolveCashAccountId($paymentMethod);

        $builder->addDebit(
            $cashAccountId,
            $amount,
            'Customer deposit – cash received'
        );

        $builder->addCredit(
            $customerDepositsLiabilityAccountId,
            $amount,
            'Customer deposits liability – funds held'
        );

        return $builder->getLines();
    }

    public function buildDepositWithdrawalEntry(float $amount, string $paymentMethod, int $customerDepositsLiabilityAccountId): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $cashAccountId = $this->resolveCashAccountId($paymentMethod);

        $builder->addDebit(
            $customerDepositsLiabilityAccountId,
            $amount,
            'Customer deposits liability – withdrawal'
        );

        $builder->addCredit(
            $cashAccountId,
            $amount,
            'Customer withdrawal – cash paid'
        );

        return $builder->getLines();
    }

    public function buildDepositLoanPaymentEntry(Loans $loan, float $amount, int $customerDepositsLiabilityAccountId): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $builder->addDebit(
            $customerDepositsLiabilityAccountId,
            $amount,
            'Customer deposits liability – loan payment'
        );

        $builder->addCredit(
            (int) $loan->principal_account_id,
            $amount,
            'Loan receivable – payment from savings'
        );

        return $builder->getLines();
    }

    private function resolveCashAccountId(string $paymentMethod): int
    {
        return 1;
    }
}
