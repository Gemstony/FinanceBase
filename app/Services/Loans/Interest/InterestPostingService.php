<?php

namespace App\Services\Loans\Interest;

use App\Models\LoanInterestAccruals;
use App\Models\LoanInterestPostings;
use App\Models\LoanInstallments;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class InterestPostingService
{
    /**
     * Post accrued (unposted) interest for a loan.
     *
     * Accounting / operations logic:
     * - Accruals are daily journal-like records.
     * - Posting aggregates unposted accruals into a single posting record.
     * - After posting, accrual rows are marked posted and linked to the posting.
     * - Installment interest due is increased to reflect payable interest.
     *
     * NOTE:
     * This implementation updates installments by distributing posted interest
     * across the earliest active installments (FIFO). This is a common operational
     * approach when installment-level accrual is not tracked.
     */
    public function postAccruedInterest(Loans $loan, ?Carbon $asOfDate = null): ?LoanInterestPostings
    {
        $today = ($asOfDate ?? Carbon::today())->startOfDay();

        return DB::transaction(function () use ($loan, $today) {
            $accruals = LoanInterestAccruals::query()
                ->where('loan_id', $loan->id)
                ->where('is_active', true)
                ->where('is_posted', false)
                ->lockForUpdate()
                ->get();

            if ($accruals->isEmpty()) {
                return null;
            }

            $total = (float) $accruals->sum('daily_interest');
            $total = round(max(0.0, $total), 6);

            if ($total <= 0) {
                // Mark them as posted to prevent reprocessing of zero entries.
                LoanInterestAccruals::query()
                    ->whereIn('id', $accruals->pluck('id'))
                    ->update([
                        'is_posted' => true,
                        'posting_id' => null,
                    ]);

                return null;
            }

            $posting = LoanInterestPostings::create([
                'loan_id' => $loan->id,
                'installment_id' => null,
                'posting_date' => $today->toDateString(),
                'interest_amount' => $total,
                'reference_number' => null,
                'description' => 'Interest posting',
                'is_successful' => true,
                'is_active' => true,
            ]);

            // Link accruals to posting.
            LoanInterestAccruals::query()
                ->whereIn('id', $accruals->pluck('id'))
                ->update([
                    'is_posted' => true,
                    'posting_id' => $posting->id,
                ]);

            // Update installments: allocate posted interest to installment interest_due.
            $this->allocatePostedInterestToInstallments($loan, $total);

            return $posting;
        });
    }

    /**
     * Allocate posted interest to installments (FIFO).
     *
     * Operational assumption:
     * - If interest is accrued at loan level, we still need to make it payable.
     * - We increment interest_due on upcoming installments until amount is exhausted.
     */
    protected function allocatePostedInterestToInstallments(Loans $loan, float $interestAmount): void
    {
        $remaining = round(max(0.0, $interestAmount), 6);
        if ($remaining <= 0) {
            return;
        }

        /** @var Collection<int, LoanInstallments> $installments */
        $installments = LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        foreach ($installments as $inst) {
            /** @var LoanInstallments $inst */
            if ($remaining <= 0) {
                break;
            }

            // Add all remaining to current installment (simple, auditable approach).
            // A more complex approach could prorate by principal_due or by days.
            $add = $remaining;

            $inst->interest_due = round((float) $inst->interest_due + $add, 2);

            $inst->total_due = round(
                (float) $inst->principal_due +
                (float) $inst->interest_due +
                (float) $inst->fees_due +
                (float) $inst->penalty_due,
                2
            );

            $inst->outstanding_amount = round(
                max(0.0, (float) $inst->total_due - (float) $inst->amount_paid),
                2
            );

            $inst->save();

            $remaining = round($remaining - $add, 6);
        }
    }
}
