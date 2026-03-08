<?php

declare(strict_types=1);

namespace App\Services\Loans\Ledger;

use Carbon\Carbon;

class LoanTransactionBuilder
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * Set the loan ID for the transaction.
     *
     * @param int $loanId
     *
     * @return $this
     */
    public function setLoan(int $loanId): self
    {
        $this->data['loan_id'] = $loanId;

        return $this;
    }

    /**
     * Set the transaction type (e.g., loan_disbursement, repayment, interest_accrual, penalty_applied, writeoff, recovery).
     *
     * @param string $type
     *
     * @return $this
     */
    public function setTransactionType(string $type): self
    {
        $this->data['transaction_type'] = $type;

        return $this;
    }

    /**
     * Set the total transaction amount.
     *
     * @param float $amount
     *
     * @return $this
     */
    public function setAmount(float $amount): self
    {
        $this->data['amount'] = round($amount, 2);

        return $this;
    }

    /**
     * Set the principal component amount.
     *
     * @param float $amount
     *
     * @return $this
     */
    public function setPrincipalAmount(float $amount): self
    {
        $this->data['principal_amount'] = round($amount, 2);

        return $this;
    }

    /**
     * Set the interest component amount.
     *
     * @param float $amount
     *
     * @return $this
     */
    public function setInterestAmount(float $amount): self
    {
        $this->data['interest_amount'] = round($amount, 2);

        return $this;
    }

    /**
     * Set the penalty component amount.
     *
     * @param float $amount
     *
     * @return $this
     */
    public function setPenaltyAmount(float $amount): self
    {
        $this->data['penalty_amount'] = round($amount, 2);

        return $this;
    }

    /**
     * Set the fee component amount.
     *
     * @param float $amount
     *
     * @return $this
     */
    public function setFeeAmount(float $amount): self
    {
        $this->data['fee_amount'] = round($amount, 2);

        return $this;
    }

    /**
     * Set the reference type and ID for traceability.
     *
     * @param string $referenceType
     * @param int    $referenceId
     *
     * @return $this
     */
    public function setReference(string $referenceType, int $referenceId): self
    {
        $this->data['reference_type'] = $referenceType;
        $this->data['reference_id'] = $referenceId;

        return $this;
    }

    /**
     * Set a free-form description for the transaction.
     *
     * @param string|null $description
     *
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->data['description'] = $description;

        return $this;
    }

    /**
     * Set the transaction date.
     *
     * @param Carbon $date
     *
     * @return $this
     */
    public function setTransactionDate(Carbon $date): self
    {
        $this->data['transaction_date'] = $date;

        return $this;
    }

    /**
     * Build and return the structured transaction array.
     *
     * The output is ready for insertion into the loan_transactions table.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        // Ensure required fields are present
        if (!isset($this->data['loan_id'])) {
            throw new \InvalidArgumentException('Loan ID is required.');
        }
        if (!isset($this->data['transaction_type'])) {
            throw new \InvalidArgumentException('Transaction type is required.');
        }
        if (!isset($this->data['transaction_date'])) {
            throw new \InvalidArgumentException('Transaction date is required.');
        }

        // Default amounts to zero if not set
        $defaults = [
            'amount' => 0.0,
            'principal_amount' => 0.0,
            'interest_amount' => 0.0,
            'penalty_amount' => 0.0,
            'fee_amount' => 0.0,
            'reference_type' => null,
            'reference_id' => null,
            'description' => null,
        ];

        return array_merge($defaults, $this->data);
    }

    /**
     * Reset the builder for reuse.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->data = [];

        return $this;
    }
}
