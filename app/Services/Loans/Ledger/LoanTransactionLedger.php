<?php

declare(strict_types=1);

namespace App\Services\Loans\Ledger;

use App\Models\Loans;
use Carbon\Carbon;

class LoanTransactionLedger
{
    public function __construct(
        private readonly LoanLedgerRecorder $recorder,
        private readonly LoanTransactionBuilder $builder,
    ) {
    }

    /**
     * Record a loan disbursement event.
     *
     * @param Loans $loan        The loan being disbursed
     * @param float $amount      Disbursement amount
     * @param int   $referenceId Reference ID (e.g., disbursement record ID)
     *
     * @return \App\Models\LoanTransactions
     */
    public function recordDisbursement(Loans $loan, float $amount, int $referenceId)
    {
        $transaction = $this->builder
            ->reset()
            ->setLoan((int) $loan->id)
            ->setTransactionType('loan_disbursement')
            ->setAmount($amount)
            ->setPrincipalAmount($amount)
            ->setReference('loan_disbursement', $referenceId)
            ->setDescription("Loan disbursement – {$loan->loan_code}")
            ->setTransactionDate(Carbon::now())
            ->build();

        return $this->recorder->record($transaction);
    }

    /**
     * Record a loan repayment event.
     *
     * @param Loans $loan        The loan being repaid
     * @param float $amount      Total payment amount
     * @param float $principal   Principal portion
     * @param float $interest    Interest portion
     * @param float $penalty     Penalty portion
     * @param float $fees        Fee portion
     * @param int   $referenceId Reference ID (e.g., payment record ID)
     *
     * @return \App\Models\LoanTransactions
     */
    public function recordRepayment(
        Loans $loan,
        float $amount,
        float $principal,
        float $interest,
        float $penalty,
        float $fees,
        int $referenceId
    ) {
        $transaction = $this->builder
            ->reset()
            ->setLoan((int) $loan->id)
            ->setTransactionType('repayment')
            ->setAmount($amount)
            ->setPrincipalAmount($principal)
            ->setInterestAmount($interest)
            ->setPenaltyAmount($penalty)
            ->setFeeAmount($fees)
            ->setReference('loan_payment', $referenceId)
            ->setDescription("Loan repayment – {$loan->loan_code}")
            ->setTransactionDate(Carbon::now())
            ->build();

        return $this->recorder->record($transaction);
    }

    /**
     * Record an interest accrual event.
     *
     * @param Loans $loan           The loan for which interest is accrued
     * @param float $interestAmount Accrued interest amount
     * @param int   $referenceId    Reference ID (e.g., accrual record ID)
     *
     * @return \App\Models\LoanTransactions
     */
    public function recordInterestAccrual(Loans $loan, float $interestAmount, int $referenceId)
    {
        $transaction = $this->builder
            ->reset()
            ->setLoan((int) $loan->id)
            ->setTransactionType('interest_accrual')
            ->setAmount($interestAmount)
            ->setInterestAmount($interestAmount)
            ->setReference('loan_interest_accrual', $referenceId)
            ->setDescription("Interest accrual – {$loan->loan_code}")
            ->setTransactionDate(Carbon::now())
            ->build();

        return $this->recorder->record($transaction);
    }

    /**
     * Record a penalty application event.
     *
     * @param Loans $loan           The loan for which penalty is applied
     * @param float $penaltyAmount Penalty amount
     * @param int   $referenceId    Reference ID (e.g., penalty record ID)
     *
     * @return \App\Models\LoanTransactions
     */
    public function recordPenaltyApplication(Loans $loan, float $penaltyAmount, int $referenceId)
    {
        $transaction = $this->builder
            ->reset()
            ->setLoan((int) $loan->id)
            ->setTransactionType('penalty_applied')
            ->setAmount($penaltyAmount)
            ->setPenaltyAmount($penaltyAmount)
            ->setReference('loan_penalty_application', $referenceId)
            ->setDescription("Penalty applied – {$loan->loan_code}")
            ->setTransactionDate(Carbon::now())
            ->build();

        return $this->recorder->record($transaction);
    }

    /**
     * Record a loan write-off event.
     *
     * @param Loans $loan        The loan being written off
     * @param float $amount      Write-off amount (typically outstanding principal)
     * @param int   $referenceId Reference ID (e.g., write-off record ID)
     *
     * @return \App\Models\LoanTransactions
     */
    public function recordWriteOff(Loans $loan, float $amount, int $referenceId)
    {
        $transaction = $this->builder
            ->reset()
            ->setLoan((int) $loan->id)
            ->setTransactionType('writeoff')
            ->setAmount($amount)
            ->setPrincipalAmount($amount)
            ->setReference('loan_writeoff', $referenceId)
            ->setDescription("Loan write-off – {$loan->loan_code}")
            ->setTransactionDate(Carbon::now())
            ->build();

        return $this->recorder->record($transaction);
    }

    /**
     * Record a loan recovery event (post-write-off collection).
     *
     * @param Loans $loan        The loan for which recovery is recorded
     * @param float $amount      Recovery amount received
     * @param int   $referenceId Reference ID (e.g., recovery record ID)
     *
     * @return \App\Models\LoanTransactions
     */
    public function recordRecovery(Loans $loan, float $amount, int $referenceId)
    {
        $transaction = $this->builder
            ->reset()
            ->setLoan((int) $loan->id)
            ->setTransactionType('recovery')
            ->setAmount($amount)
            ->setPrincipalAmount($amount)
            ->setReference('loan_writeoff_recovery', $referenceId)
            ->setDescription("Loan recovery – {$loan->loan_code}")
            ->setTransactionDate(Carbon::now())
            ->build();

        return $this->recorder->record($transaction);
    }
}
