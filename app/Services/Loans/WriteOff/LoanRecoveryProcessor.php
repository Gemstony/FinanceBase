<?php

declare(strict_types=1);

namespace App\Services\Loans\WriteOff;

use App\Models\LoanInstallments;
use App\Models\LoanPayments;
use App\Models\LoanWriteoffRecoveries;
use App\Models\LoanWriteoffs;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LoanRecoveryProcessor
{
    /**
     * Process a recovery payment for a written-off loan.
     *
     * Recovery accounting rules:
     * - Do NOT reopen the loan
     * - Track recovered amounts separately in loan_writeoff_recoveries
     * - Allocation priority is the same as normal: penalty -> fees -> interest -> principal
     */
    public function processRecovery(Loans $loan, LoanPayments $payment): LoanWriteoffRecoveries
    {
        if (Str::lower((string) $loan->status) !== 'written_off') {
            throw new RuntimeException('Recovery processing is only allowed for written-off loans.');
        }

        $writeoff = $this->getLatestWriteoff($loan);

        return DB::transaction(function () use ($loan, $payment, $writeoff) {
            // Compute current remaining balances from (possibly frozen) installments.
            $installments = LoanInstallments::query()
                ->where('loan_id', $loan->id)
                ->where('status', '!=', 'paid')
                ->get([
                    'principal_due', 'principal_paid',
                    'interest_due', 'interest_paid',
                    'fees_due', 'fees_paid',
                    'penalty_due', 'penalty_paid',
                ]);

            $remainingPenalty = (float) $installments->sum(fn ($i) => max(0.0, (float) $i->penalty_due - (float) $i->penalty_paid));
            $remainingFees = (float) $installments->sum(fn ($i) => max(0.0, (float) $i->fees_due - (float) $i->fees_paid));
            $remainingInterest = (float) $installments->sum(fn ($i) => max(0.0, (float) $i->interest_due - (float) $i->interest_paid));
            $remainingPrincipal = (float) $installments->sum(fn ($i) => max(0.0, (float) $i->principal_due - (float) $i->principal_paid));

            $amount = round((float) $payment->amount, 2);
            if ($amount <= 0.0) {
                throw new RuntimeException('Recovery payment amount must be greater than 0.');
            }

            // Allocation priority: penalty -> fees -> interest -> principal
            $recoveredPenalties = min($amount, round($remainingPenalty, 2));
            $amount = round($amount - $recoveredPenalties, 2);

            $recoveredFees = min($amount, round($remainingFees, 2));
            $amount = round($amount - $recoveredFees, 2);

            $recoveredInterest = min($amount, round($remainingInterest, 2));
            $amount = round($amount - $recoveredInterest, 2);

            $recoveredPrincipal = min($amount, round($remainingPrincipal, 2));
            $amount = round($amount - $recoveredPrincipal, 2);

            $totalRecovered = round($recoveredPenalties + $recoveredFees + $recoveredInterest + $recoveredPrincipal, 2);

            return LoanWriteoffRecoveries::create([
                'loan_id' => $loan->id,
                'writeoff_id' => $writeoff->id,
                'payment_id' => $payment->id,
                'recovery_date' => Carbon::parse($payment->payment_date ?? Carbon::today()->toDateString())->toDateString(),
                'recovered_principal' => $recoveredPrincipal,
                'recovered_interest' => $recoveredInterest,
                'recovered_fees' => $recoveredFees,
                'recovered_penalties' => $recoveredPenalties,
                'total_recovered' => $totalRecovered,
                'notes' => null,
            ]);
        });
    }

    private function getLatestWriteoff(Loans $loan): LoanWriteoffs
    {
        $writeoff = $loan->writeoffs()->orderByDesc('writeoff_date')->first();

        if (!$writeoff) {
            throw new RuntimeException('Unable to process recovery: no write-off record found for this loan.');
        }

        return $writeoff;
    }
}
