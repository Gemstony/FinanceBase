<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\BankAccounts;
use App\Models\Loans;
use App\Models\PaymentMethodAccount;
use Illuminate\Support\Facades\Log;

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
        if ($loanId <= 0) {
            throw new \InvalidArgumentException('Loan repayment posting requires loan_id.');
        }

        $loan = Loans::query()->whereKey($loanId)->first();
        if (! $loan) {
            throw new \InvalidArgumentException("Loan #{$loanId} not found for repayment posting.");
        }

        $subshopId = (int) ($allocation['subshop_id'] ?? $loan->subshop_id ?? 0);
        if ($subshopId <= 0) {
            throw new \InvalidArgumentException('Loan repayment posting requires subshop_id.');
        }

        if ((int) $loan->subshop_id !== $subshopId) {
            throw new \InvalidArgumentException('Subshop mismatch: repayment allocation subshop_id does not match loan subshop.');
        }

        $paymentMethod = (string) ($allocation['payment_method'] ?? 'cash');

        $paymentAccountId = !empty($allocation['payment_account_id']) ? (int) $allocation['payment_account_id'] : null;
        if (! $paymentAccountId) {
            $bankAccountId = !empty($allocation['bank_account_id']) ? (int) $allocation['bank_account_id'] : null;
            if ($bankAccountId) {
                $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
                if (! $bank) {
                    throw new \InvalidArgumentException('Selected bank account not found for repayment posting.');
                }
                if ((int) $bank->subshop_id !== $subshopId) {
                    throw new \InvalidArgumentException('Selected bank account does not belong to this branch.');
                }
                $paymentAccountId = (int) $bank->chart_of_account_id;
                if ($paymentAccountId <= 0) {
                    throw new \InvalidArgumentException('Bank account missing chart_of_account_id mapping.');
                }
            } else {
                $paymentAccountId = $this->resolvePaymentAccountId($paymentMethod, $subshopId);
            }
        }

        if ($paymentAccountId <= 0) {
            throw new \InvalidArgumentException('Payment account mapping could not be resolved.');
        }

        $totalCash = $principal + $interest + $fee + $penalty;
        if ($totalCash > 0) {
            $builder->addDebit(
                $paymentAccountId,
                $totalCash,
                'Loan repayment – cash received'
            );
        }

        // Credit: Loan Portfolio (principal)
        if ($principal > 0) {
            if (! $loan->principal_account_id) {
                throw new \InvalidArgumentException('Loan missing principal_account_id.');
            }
            $builder->addCredit(
                (int) $loan->principal_account_id,
                $principal,
                'Loan repayment – principal portion'
            );
        }

        // Credit: Interest Receivable (accrual)
        if ($interest > 0) {
            if (! $loan->interest_receivable_account_id) {
                throw new \InvalidArgumentException('Loan product missing interest_receivable_account_id.');
            }
            $builder->addCredit(
                (int) $loan->interest_receivable_account_id,
                $interest,
                'Loan repayment – interest receivable'
            );
        }

        // Credit: Penalty Receivable (accrual)
        if ($penalty > 0) {
            if (! $loan->penalty_receivable_account_id) {
                throw new \InvalidArgumentException('Loan product missing penalty_receivable_account_id.');
            }
            $builder->addCredit(
                (int) $loan->penalty_receivable_account_id,
                $penalty,
                'Loan repayment – penalty receivable'
            );
        }

        // Credit: Fee Receivable (accrual)
        if ($fee > 0) {
            if (! $loan->fee_income_account_id) {
                throw new \InvalidArgumentException('Loan product missing fee_income_account_id (fee receivable not configured).');
            }
            $builder->addCredit(
                (int) $loan->fee_income_account_id,
                $fee,
                'Loan repayment – fee receivable'
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
     * - Debit: Increase Cash/Bank (asset) because cash is received.
     * - Credit: Recognize Recovery Income (income) because it offsets previous loss.
     *
     * @param float $amount Recovery amount received
     * @param int|null $bankAccountId Bank account ID for the payment
     * @param string|null $paymentMethod Payment method used
     *
     * @return array Journal lines ready for validation/posting
     */
    public function buildLoanRecoveryEntry(float $amount, ?int $bankAccountId = null, ?string $paymentMethod = null): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $recoveryIncomeAccountId = (int) (session('recovery_income_account_id') ?? 0);
        if ($recoveryIncomeAccountId <= 0) {
            throw new \InvalidArgumentException('Recovery income account is not configured (recovery_income_account_id).');
        }

        // Determine the cash/bank account to debit based on bank account or payment method
        $cashAccountId = null;
        if ($bankAccountId) {
            $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
            $linked = (int) ($bank?->chart_of_account_id ?? 0);
            if ($linked > 0) {
                $cashAccountId = $linked;
            }
        } elseif ($paymentMethod) {
            $subshopId = (int) (session('subshop_id') ?? 0);
            if ($subshopId <= 0) {
                throw new \InvalidArgumentException('subshop_id is required to resolve payment account for recovery posting.');
            }
            $cashAccountId = $this->resolvePaymentAccountId($paymentMethod, $subshopId);
        }

        if (! $cashAccountId) {
            throw new \InvalidArgumentException('Payment account is required for loan recovery posting.');
        }

        // Debit: Cash/Bank account (money coming IN)
        $builder->addDebit(
            $cashAccountId,
            $amount,
            'Loan recovery – cash received'
        );

        // Credit: Recovery Income account (income recognized)
        $builder->addCredit(
            $recoveryIncomeAccountId,
            $amount,
            'Loan recovery – income recognized'
        );

        return $builder->getLines();
    }

    public function buildSecurityDepositCollectedEntry(Loans $loan, float $amount, string $paymentMethod, ?int $bankAccountId = null): array
    {
        $builder = clone $this->builder;
        $builder->reset();

        $cashAccountId = $this->resolvePaymentAccountId($paymentMethod, (int) $loan->subshop_id);
        if ($bankAccountId) {
            $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
            $linked = (int) ($bank?->chart_of_account_id ?? 0);
            if ($linked > 0) {
                $cashAccountId = $linked;
            }
        }
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

        $cashAccountId = $this->resolvePaymentAccountId($paymentMethod, (int) $loan->subshop_id);
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
        $recoveryAccountId = (int) ($loan->fee_income_account_id ?: $loan->interest_income_account_id);

        if ($liabilityAccountId <= 0) {
            throw new \InvalidArgumentException('Loan missing customer_security_deposit_account_id for forfeiture posting.');
        }
        if ($recoveryAccountId <= 0) {
            throw new \InvalidArgumentException('Loan product missing fee_income_account_id or interest_income_account_id for security deposit forfeiture posting.');
        }

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

        $subshopId = (int) (session('subshop_id') ?? 0);
        if ($subshopId <= 0) {
            throw new \InvalidArgumentException('subshop_id is required to resolve payment account for deposit posting.');
        }
        $cashAccountId = $this->resolvePaymentAccountId($paymentMethod, $subshopId);

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

        $subshopId = (int) (session('subshop_id') ?? 0);
        if ($subshopId <= 0) {
            throw new \InvalidArgumentException('subshop_id is required to resolve payment account for withdrawal posting.');
        }
        $cashAccountId = $this->resolvePaymentAccountId($paymentMethod, $subshopId);

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

    private function resolvePaymentAccountId(string $paymentMethod, int $subshopId): int
    {
        $paymentMethod = trim(strtolower($paymentMethod));
        if ($paymentMethod === '') {
            throw new \InvalidArgumentException('Payment method is required to resolve payment account.');
        }
        if ($subshopId <= 0) {
            throw new \InvalidArgumentException('subshop_id is required to resolve payment account.');
        }

        $mapping = PaymentMethodAccount::query()
            ->where('subshop_id', $subshopId)
            ->where('payment_method', $paymentMethod)
            ->first();

        if (! $mapping) {
            Log::warning('Missing payment method GL mapping', [
                'subshop_id' => $subshopId,
                'payment_method' => $paymentMethod,
            ]);
            throw new \InvalidArgumentException("Payment method not mapped to GL account: {$paymentMethod}.");
        }

        return (int) $mapping->chart_of_account_id;
    }
}
