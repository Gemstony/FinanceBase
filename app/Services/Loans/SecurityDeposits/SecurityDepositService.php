<?php

declare(strict_types=1);

namespace App\Services\Loans\SecurityDeposits;

use App\Models\LoanSecurityDeposit;
use App\Models\Loans;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Loans\Repayment\PaymentProcessor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SecurityDepositService
{
    public function __construct(
        private readonly JournalPostingEngine $journalPostingEngine,
        private readonly PaymentProcessor $paymentProcessor,
    ) {
    }

    public function collectDeposit(int $borrowerId, int $loanId, float $amount, string $paymentMethod, ?string $notes = null): LoanSecurityDeposit
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than 0.');
        }

        return DB::transaction(function () use ($borrowerId, $loanId, $amount, $paymentMethod, $notes) {
            $loan = Loans::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();

            $subshopId = (int) session('subshop_id');
            if ((int) $loan->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((int) $loan->customer_id !== $borrowerId) {
                throw new InvalidArgumentException('Borrower does not match the loan borrower.');
            }

            $deposit = LoanSecurityDeposit::create([
                'subshop_id' => $subshopId,
                'customer_id' => $borrowerId,
                'loan_id' => (int) $loan->id,
                'amount' => $amount,
                'status' => 'held',
                'held_at' => Carbon::now(),
                'notes' => $notes,
            ]);

            $lines = app(\App\Services\Accounting\LoanAccountingMapper::class)
                ->buildSecurityDepositCollectedEntry($loan, $amount, $paymentMethod);

            $this->journalPostingEngine->postJournalEntry(
                $lines,
                'security_deposit_collected',
                (int) $deposit->id,
                "Security deposit collected – {$loan->loan_code}"
            );

            Log::info('Security deposit collected', [
                'loan_id' => (int) $loan->id,
                'deposit_id' => (int) $deposit->id,
                'customer_id' => $borrowerId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
            ]);

            return $deposit;
        });
    }

    public function refundDeposit(int $depositId, int $userId, string $paymentMethod, ?string $notes = null): LoanSecurityDeposit
    {
        return DB::transaction(function () use ($depositId, $userId, $paymentMethod, $notes) {
            $deposit = LoanSecurityDeposit::query()->whereKey($depositId)->lockForUpdate()->firstOrFail();
            $loan = Loans::query()->whereKey((int) $deposit->loan_id)->lockForUpdate()->firstOrFail();

            $subshopId = (int) session('subshop_id');
            if ((int) $deposit->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $deposit->status !== 'held') {
                throw new InvalidArgumentException('Only held deposits can be refunded.');
            }

            $deposit->status = 'refunded';
            $deposit->released_at = Carbon::now();
            $deposit->refunded_by = $userId;
            $deposit->notes = $notes;
            $deposit->save();

            $lines = app(\App\Services\Accounting\LoanAccountingMapper::class)
                ->buildSecurityDepositRefundedEntry($loan, (float) $deposit->amount, $paymentMethod);

            $this->journalPostingEngine->postJournalEntry(
                $lines,
                'security_deposit_refunded',
                (int) $deposit->id,
                "Security deposit refunded – {$loan->loan_code}"
            );

            Log::info('Security deposit refunded', [
                'loan_id' => (int) $loan->id,
                'deposit_id' => (int) $deposit->id,
                'customer_id' => (int) $deposit->customer_id,
                'amount' => (float) $deposit->amount,
                'payment_method' => $paymentMethod,
                'refunded_by' => $userId,
            ]);

            return $deposit;
        });
    }

    public function applyDepositToLoan(int $depositId, int $loanId, ?string $notes = null): LoanSecurityDeposit
    {
        return DB::transaction(function () use ($depositId, $loanId, $notes) {
            $deposit = LoanSecurityDeposit::query()->whereKey($depositId)->lockForUpdate()->firstOrFail();
            $sourceLoan = Loans::query()->whereKey((int) $deposit->loan_id)->lockForUpdate()->firstOrFail();
            $targetLoan = Loans::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();

            $subshopId = (int) session('subshop_id');
            if ((int) $deposit->subshop_id !== $subshopId || (int) $targetLoan->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $deposit->status !== 'held') {
                throw new InvalidArgumentException('Only held deposits can be applied.');
            }

            if ((int) $deposit->customer_id !== (int) $targetLoan->customer_id) {
                throw new InvalidArgumentException('Deposit borrower must match target loan borrower.');
            }

            $deposit->status = 'applied';
            $deposit->released_at = Carbon::now();
            $deposit->applied_to_loan_id = (int) $targetLoan->id;
            $deposit->notes = $notes;
            $deposit->save();

            // Create a repayment record so installments are updated
            $this->paymentProcessor->processPayment(
                $targetLoan,
                (int) $deposit->customer_id,
                (float) $deposit->amount,
                'other',
                null, // no transaction reference
                Carbon::now(),
                'Applied from security deposit – source loan: ' . $sourceLoan->loan_code
            );

            $lines = app(\App\Services\Accounting\LoanAccountingMapper::class)
                ->buildSecurityDepositAppliedEntry($sourceLoan, $targetLoan, (float) $deposit->amount);

            $this->journalPostingEngine->postJournalEntry(
                $lines,
                'security_deposit_applied',
                (int) $deposit->id,
                "Security deposit applied – {$targetLoan->loan_code}"
            );

            Log::info('Security deposit applied to loan', [
                'source_loan_id' => (int) $sourceLoan->id,
                'target_loan_id' => (int) $targetLoan->id,
                'deposit_id' => (int) $deposit->id,
                'customer_id' => (int) $deposit->customer_id,
                'amount' => (float) $deposit->amount,
            ]);

            return $deposit;
        });
    }

    public function forfeitDeposit(int $depositId, ?string $notes = null): LoanSecurityDeposit
    {
        return DB::transaction(function () use ($depositId, $notes) {
            $deposit = LoanSecurityDeposit::query()->whereKey($depositId)->lockForUpdate()->firstOrFail();
            $loan = Loans::query()->whereKey((int) $deposit->loan_id)->lockForUpdate()->firstOrFail();

            $subshopId = (int) session('subshop_id');
            if ((int) $deposit->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $deposit->status !== 'held') {
                throw new InvalidArgumentException('Only held deposits can be forfeited.');
            }

            $deposit->status = 'forfeited';
            $deposit->released_at = Carbon::now();
            $deposit->notes = $notes;
            $deposit->save();

            $lines = app(\App\Services\Accounting\LoanAccountingMapper::class)
                ->buildSecurityDepositForfeitedEntry($loan, (float) $deposit->amount);

            $this->journalPostingEngine->postJournalEntry(
                $lines,
                'security_deposit_forfeited',
                (int) $deposit->id,
                "Security deposit forfeited – {$loan->loan_code}"
            );

            Log::info('Security deposit forfeited', [
                'loan_id' => (int) $loan->id,
                'deposit_id' => (int) $deposit->id,
                'customer_id' => (int) $deposit->customer_id,
                'amount' => (float) $deposit->amount,
            ]);

            return $deposit;
        });
    }

    public function getLoanDeposits(int $loanId): Builder
    {
        $subshopId = (int) session('subshop_id');

        return LoanSecurityDeposit::query()
            ->with(['customer', 'loan', 'appliedToLoan', 'refundedBy'])
            ->where('subshop_id', $subshopId)
            ->where('loan_id', $loanId)
            ->orderByDesc('id');
    }

    public function getBorrowerDeposits(int $customerId): Builder
    {
        $subshopId = (int) session('subshop_id');

        return LoanSecurityDeposit::query()
            ->with(['customer', 'loan', 'appliedToLoan', 'refundedBy'])
            ->where('subshop_id', $subshopId)
            ->where('customer_id', $customerId)
            ->orderByDesc('id');
    }
}
