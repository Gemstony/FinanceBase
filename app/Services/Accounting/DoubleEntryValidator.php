<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use InvalidArgumentException;

class DoubleEntryValidator
{
    /**
     * Validate that a set of journal entry lines is balanced.
     *
     * Double-entry accounting principle:
     * - Every transaction must have at least one debit and one credit.
     * - Total debits must equal total credits.
     * - A line must not contain both a debit and a credit amount.
     *
     * This protects the integrity of the General Ledger.
     *
     * @param array $journalLines Each line must contain: account_id, debit, credit
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $journalLines): void
    {
        if (empty($journalLines)) {
            throw new InvalidArgumentException('Journal entry must contain at least one line.');
        }

        $totalDebits = 0.0;
        $totalCredits = 0.0;

        foreach ($journalLines as $index => $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            // Rule: No line may have both a debit and a credit.
            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException("Line {$index} contains both debit and credit amounts.");
            }

            // Rule: Amounts must be non-negative.
            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException("Line {$index} has negative amounts.");
            }

            $totalDebits += $debit;
            $totalCredits += $credit;
        }

        // Rule: Total debits must equal total credits.
        if (round($totalDebits, 2) !== round($totalCredits, 2)) {
            throw new InvalidArgumentException(
                "Journal entry is not balanced. Total debits ({$totalDebits}) do not equal total credits ({$totalCredits})."
            );
        }
    }
}
