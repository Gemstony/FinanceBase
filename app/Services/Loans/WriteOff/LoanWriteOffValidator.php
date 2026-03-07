<?php

declare(strict_types=1);

namespace App\Services\Loans\WriteOff;

use App\Models\Loans;
use App\Services\Loans\Risk\LoanDelinquencyEngine;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class LoanWriteOffValidator
{
    public function __construct(
        private readonly LoanDelinquencyEngine $delinquencyEngine
    ) {}

    /**
     * Validate whether a loan can be written off.
     *
     * Validation rules (why each exists):
     * 1) Loan must exist
     *    - prevents acting on a missing/unsaved model.
     *
     * 2) Loan must not already be written off
     *    - prevents duplicate write-off entries and double recognition of losses.
     *
     * 3) Loan must not be closed
     *    - paid-off loans have no receivable to write off.
     *
     * 4) Loan must have remaining outstanding balance
     *    - ensures we only write off actual receivables.
     *
     * 5) Loan delinquency must be >= PAR180 (180 days overdue)
     *    - standard microfinance rule: only severely delinquent loans are eligible.
     */
    public function validate(Loans $loan, int $parDays = 180, ?Carbon $asOfDate = null): void
    {
        if (empty($loan->id)) {
            throw new RuntimeException('Unable to write off: loan does not exist.');
        }

        $status = Str::lower((string) $loan->status);

        if ($status === 'written_off') {
            throw new RuntimeException('Unable to write off: loan is already written off.');
        }

        if (in_array($status, ['paid_off', 'rejected'], true)) {
            throw new RuntimeException("Unable to write off: loan is closed (status={$loan->status}).");
        }

        $outstanding = $this->calculateOutstandingAmount($loan);
        if ($outstanding <= 0.0) {
            throw new RuntimeException('Unable to write off: loan has no outstanding balance.');
        }

        $asOf = ($asOfDate ?? Carbon::today())->startOfDay();

        // Determine max days overdue using the same risk logic used elsewhere in the system.
        // LoanDelinquencyEngine::classifyLoanRisk uses max overdue days; we replicate the threshold check here.
        $maxDaysOverdue = (int) $loan->installments()
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->get(['due_date'])
            ->map(function ($i) use ($asOf) {
                $due = $i->due_date instanceof Carbon ? $i->due_date->copy()->startOfDay() : Carbon::parse($i->due_date)->startOfDay();
                return max(0, (int) $due->diffInDays($asOf, false));
            })
            ->max();

        if ($maxDaysOverdue < $parDays) {
            throw new RuntimeException("Unable to write off: loan does not meet PAR{$parDays} (max_days_overdue={$maxDaysOverdue}).");
        }
    }

    /**
     * Outstanding for eligibility is derived from the schedule (installments), not the loan header.
     * This prevents stale header totals from allowing/disallowing write-off incorrectly.
     */
    private function calculateOutstandingAmount(Loans $loan): float
    {
        return (float) $loan->installments()
            ->where('is_active', true)
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');
    }
}
