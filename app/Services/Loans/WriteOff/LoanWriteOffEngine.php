<?php

declare(strict_types=1);

namespace App\Services\Loans\WriteOff;

use App\Models\LoanInstallments;
use App\Models\LoanWriteoffs;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanWriteOffEngine
{
    public function __construct(
        private readonly LoanWriteOffValidator $validator,
        private readonly LoanWriteOffCalculator $calculator
    ) {}

    /**
     * Write off a severely delinquent loan.
     *
     * Accounting/portfolio behavior:
     * - Remaining receivables are recognized as loss (recorded in loan_writeoffs)
     * - The loan is moved to status = written_off, which prevents future accrual engines from processing it
     * - Remaining installments are frozen (deactivated) so they no longer participate in delinquency/collections engines
     * - The system still allows recovery tracking via loan_writeoff_recoveries
     */
    public function writeOffLoan(Loans $loan, string $reason, int $approvedBy): LoanWriteoffs
    {
        $this->validator->validate($loan);

        return DB::transaction(function () use ($loan, $reason, $approvedBy) {
            $balances = $this->calculator->calculateBalances($loan);

            $writeoff = LoanWriteoffs::create([
                'loan_id' => $loan->id,
                'writeoff_date' => Carbon::today()->toDateString(),
                'principal_written_off' => $balances['principal_written_off'],
                'interest_written_off' => $balances['interest_written_off'],
                'fees_written_off' => $balances['fees_written_off'],
                'penalties_written_off' => $balances['penalties_written_off'],
                'total_written_off' => $balances['total_written_off'],
                'reason' => $reason,
                'approved_by' => $approvedBy,
                'approved_at' => Carbon::now(),
            ]);

            // Set loan status to stop accrual engines.
            $loan->status = 'written_off';
            $loan->save();

            // Freeze remaining schedule so it no longer participates in delinquency calculations and accrual engines.
            LoanInstallments::query()
                ->where('loan_id', $loan->id)
                ->where('is_active', true)
                ->where('status', '!=', 'paid')
                ->update([
                    'is_active' => false,
                ]);

            return $writeoff;
        });
    }
}
