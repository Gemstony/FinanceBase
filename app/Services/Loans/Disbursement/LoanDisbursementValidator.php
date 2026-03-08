<?php

declare(strict_types=1);

namespace App\Services\Loans\Disbursement;

use App\Models\Loans;
use InvalidArgumentException;

class LoanDisbursementValidator
{
    /**
     * Validate whether a loan can be disbursed.
     *
     * Financial meaning:
     * Disbursement is the point at which the institution releases the approved funds to the borrower.
     * From this moment, the loan enters its repayment lifecycle and becomes eligible for accruals
     * (interest/penalties) and borrower repayments.
     *
     * @throws InvalidArgumentException
     */
    public function validate(Loans $loan, float $amount): void
    {
        // Rule 1: Loan must exist.
        // We must never disburse funds against a non-persisted loan record.
        if (!$loan->exists) {
            throw new InvalidArgumentException('Loan not found.');
        }

        $status = (string) $loan->status;

        // Rule 6: Loan must not be rejected or written_off.
        // These represent terminal/invalid states where funds must not be released.
        if (in_array($status, ['rejected', 'written_off'], true)) {
            throw new InvalidArgumentException('This loan cannot be disbursed because it is rejected or written off.');
        }

        // Rule 2: Loan status must be approved.
        // Disbursement can only happen after the credit committee / approval workflow completes.
        if ($status !== 'approved') {
            throw new InvalidArgumentException('This loan must be approved before it can be disbursed.');
        }

        // Rule 3: Loan must not already be active or disbursed.
        // In this codebase, the post-disbursement lifecycle status is `disbursed` (not `active`).
        // Additionally, if installments have begun being paid, it is already in repayment.
        if (in_array($status, ['disbursed', 'partially_paid', 'paid_off', 'defaulted'], true)) {
            throw new InvalidArgumentException('This loan has already been disbursed or is already in repayment/closed.');
        }

        // Rule 4: Disbursement amount must be greater than zero.
        // Disbursing a non-positive amount is not a meaningful financial operation.
        if ($amount <= 0) {
            throw new InvalidArgumentException('Disbursement amount must be greater than zero.');
        }

        // Rule 5: Disbursement amount must not exceed the approved loan principal.
        // Microfinance disbursement must never release more than the principal approved.
        $principal = (float) $loan->principal_amount;
        if ($amount > $principal) {
            throw new InvalidArgumentException('Disbursement amount cannot exceed the approved principal amount.');
        }
    }
}
