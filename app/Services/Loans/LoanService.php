<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanService
{
    /**
     * Post a repayment to a loan.
     *
     * @param PaymentTransaction $transaction
     * @return void
     */
    public function postRepayment(PaymentTransaction $transaction): void
    {
        try {
            DB::beginTransaction();

            $loan = Loan::findOrFail($transaction->loan_id);

            // Update loan balance
            $loan->decrement('balance', $transaction->amount);

            // Update loan status if fully paid
            if ($loan->balance <= 0) {
                $loan->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            // Create repayment record
            $loan->repayments()->create([
                'amount' => $transaction->amount,
                'payment_method' => 'mobile_money',
                'reference' => $transaction->reference,
                'transaction_id' => $transaction->id,
                'paid_at' => now(),
            ]);

            DB::commit();

            Log::info('Loan repayment posted', [
                'loan_id' => $loan->id,
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to post loan repayment', [
                'loan_id' => $transaction->loan_id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mark a loan as disbursed.
     *
     * @param int $loanId
     * @return void
     */
    public function markDisbursed(int $loanId): void
    {
        try {
            $loan = Loan::findOrFail($loanId);

            $loan->update([
                'status' => 'disbursed',
                'disbursed_at' => now(),
            ]);

            Log::info('Loan marked as disbursed', [
                'loan_id' => $loanId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark loan as disbursed', [
                'loan_id' => $loanId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
