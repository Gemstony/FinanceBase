<?php

declare(strict_types=1);

namespace App\Services\Loans\Restructure;

use App\Models\Loans;
use Illuminate\Support\Str;
use RuntimeException;

class RestructureValidator
{
    /**
     * Validate whether the loan is eligible for restructuring.
     *
     * Financial integrity rules:
     * - Restructuring is allowed only for running loans (active/delinquent).
     * - Restructuring must never occur for closed contracts (paid off / written off / closed).
     */
    public function validateLoanEligibility(Loans $loan): void
    {
        if (empty($loan->id)) {
            throw new RuntimeException('Unable to restructure: loan not found.');
        }

        if ($loan->is_active !== true) {
            throw new RuntimeException('Unable to restructure: loan is inactive.');
        }

        $status = Str::lower((string) $loan->status);

        // Not allowed states
        if (in_array($status, ['closed', 'paid_off', 'written_off'], true)) {
            throw new RuntimeException("Unable to restructure: loan is closed (status={$loan->status}).");
        }

        if (in_array($status, ['pending', 'approved', 'pending_disbursement'], true)) {
            throw new RuntimeException("Unable to restructure: loan not yet running (status={$loan->status}).");
        }

        // Allowed states (support existing codebase variants)
        $allowed = ['active', 'delinquent', 'disbursed', 'partially_paid', 'defaulted'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException("Unable to restructure: loan status is not eligible (status={$loan->status}).");
        }
    }

    /**
     * Validate input terms.
     */
    public function validateTerms(float $newInterestRate, int $newTerm, int $gracePeriod, bool $capitalizeInterest): void
    {
        if ($newInterestRate < 0 || $newInterestRate > 100) {
            throw new RuntimeException('New interest rate must be between 0 and 100.');
        }

        if ($newTerm < 1) {
            throw new RuntimeException('New term must be at least 1.');
        }

        if ($gracePeriod < 0) {
            throw new RuntimeException('Grace period must be 0 or greater.');
        }

        // Capitalize flag requires no extra validation here; accounting posting will validate mappings.
        if ($capitalizeInterest) {
            // noop
        }
    }
}
