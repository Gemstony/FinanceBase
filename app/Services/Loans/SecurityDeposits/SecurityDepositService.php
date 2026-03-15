<?php

declare(strict_types=1);

namespace App\Services\Loans\SecurityDeposits;

use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\LoanSecurityDeposit;
use App\Models\Loans;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Accounting\VoucherService;
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
        private readonly VoucherService $voucherService,
    ) {
    }

    public function collectDeposit(int $borrowerId, int $loanId, float $amount, string $paymentMethod, ?int $paymentBankAccountId = null, ?string $notes = null): LoanSecurityDeposit
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than 0.');
        }

        return DB::transaction(function () use ($borrowerId, $loanId, $amount, $paymentMethod, $paymentBankAccountId, $notes) {
            $loan = Loans::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();

            $subshopId = (int) session('subshop_id');
            if ((int) $loan->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((int) $loan->customer_id !== $borrowerId) {
                throw new InvalidArgumentException('Borrower does not match the loan borrower.');
            }

            $requiresBank = in_array(strtolower(trim($paymentMethod)), ['bank_transfer', 'mobile_money'], true);
            if ($requiresBank && !$paymentBankAccountId) {
                throw new InvalidArgumentException('Bank account is required for this payment method.');
            }

            if ($paymentBankAccountId) {
                $bankAccount = BankAccounts::query()->whereKey($paymentBankAccountId)->firstOrFail();
                if ((int) $bankAccount->subshop_id !== $subshopId) {
                    throw new InvalidArgumentException('Selected bank account does not belong to this branch.');
                }
            }

            $deposit = LoanSecurityDeposit::create([
                'subshop_id' => $subshopId,
                'customer_id' => $borrowerId,
                'loan_id' => (int) $loan->id,
                'payment_bank_account_id' => $paymentBankAccountId,
                'amount' => $amount,
                'status' => 'held',
                'held_at' => Carbon::now(),
                'notes' => $notes,
            ]);

            $lines = app(\App\Services\Accounting\LoanAccountingMapper::class)
                ->buildSecurityDepositCollectedEntry($loan, $amount, $paymentMethod, $paymentBankAccountId);

            $journal = $this->journalPostingEngine->postJournalEntry(
                $lines,
                'security_deposit_collected',
                (int) $deposit->id,
                "Security deposit collected – {$loan->loan_code}"
            );

            $this->voucherService->createVoucherFromJournalEntry(
                $journal,
                'receipt',
                [
                    'payment_method' => $paymentMethod,
                    'bank_account_id' => $paymentBankAccountId,
                    'description' => "Security deposit collected – {$loan->loan_code}",
                ]
            );

            Log::info('Security deposit collected', [
                'loan_id' => (int) $loan->id,
                'deposit_id' => (int) $deposit->id,
                'customer_id' => $borrowerId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_bank_account_id' => $paymentBankAccountId,
            ]);

            return $deposit;
        });
    }

    /**
     * @param array{refund_method:string, bank_account_id?:int|null, liability_account_id?:int, notes?:string|null} $data
     */
    public function refundDeposit(int $depositId, int $userId, float $refundAmount, array $data): LoanSecurityDeposit
    {
        $refundAmount = round((float) $refundAmount, 2);
        if ($refundAmount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than 0.');
        }

        $refundMethod = (string) ($data['refund_method'] ?? '');
        if ($refundMethod === '') {
            throw new InvalidArgumentException('Refund method is required.');
        }

        $bankAccountId = isset($data['bank_account_id']) && $data['bank_account_id'] ? (int) $data['bank_account_id'] : null;
        $requiresBank = in_array($refundMethod, ['bank_transfer', 'mobile_money'], true);
        if ($requiresBank && !$bankAccountId) {
            throw new InvalidArgumentException('Bank account is required for this refund method.');
        }

        $liabilityAccountId = !empty($data['liability_account_id']) ? (int) $data['liability_account_id'] : 0;
        if ($liabilityAccountId <= 0) {
            throw new InvalidArgumentException('Security deposit liability account is required.');
        }

        return DB::transaction(function () use ($depositId, $userId, $refundAmount, $refundMethod, $bankAccountId, $liabilityAccountId, $data) {
            $deposit = LoanSecurityDeposit::query()->whereKey($depositId)->lockForUpdate()->firstOrFail();
            $loan = Loans::query()->whereKey((int) $deposit->loan_id)->lockForUpdate()->firstOrFail();

            $subshopId = (int) session('subshop_id');
            if ((int) $deposit->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $deposit->status !== 'held') {
                throw new InvalidArgumentException('Only held deposits can be refunded.');
            }

            $depositAmount = round((float) $deposit->amount, 2);
            if ($refundAmount > $depositAmount) {
                throw new InvalidArgumentException('Refund amount must not exceed held deposit amount.');
            }

            if ($bankAccountId) {
                $bankAccount = BankAccounts::query()->whereKey($bankAccountId)->firstOrFail();
                if ((int) $bankAccount->subshop_id !== $subshopId) {
                    throw new InvalidArgumentException('Selected bank account does not belong to this branch.');
                }
            }

            $liabilityAccount = ChartsOfAccount::query()->whereKey($liabilityAccountId)->firstOrFail();
            if ((int) $liabilityAccount->subshop_id !== $subshopId) {
                throw new InvalidArgumentException('Selected liability account does not belong to this branch.');
            }

            $creditAccountId = 1;
            if ($bankAccountId) {
                $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
                $linked = (int) ($bank?->chart_of_account_id ?? 0);
                if ($linked > 0) {
                    $creditAccountId = $linked;
                }
            }

            $now = Carbon::now();

            $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
                ->reset()
                ->addDebit($liabilityAccountId, $refundAmount, 'Security deposit refunded – liability reduction')
                ->addCredit($creditAccountId, $refundAmount, 'Security deposit refunded – cash/bank outflow')
                ->getLines();

            // Partial refund
            if ($refundAmount < $depositAmount) {
                $remaining = round($depositAmount - $refundAmount, 2);
                if ($remaining <= 0) {
                    throw new InvalidArgumentException('Invalid remaining deposit after refund.');
                }

                $deposit->amount = $remaining;
                $deposit->save();

                $refundedDeposit = LoanSecurityDeposit::query()->create([
                    'subshop_id' => (int) $deposit->subshop_id,
                    'customer_id' => (int) $deposit->customer_id,
                    'loan_id' => (int) $deposit->loan_id,
                    'amount' => $refundAmount,
                    'status' => 'refunded',
                    'held_at' => $deposit->held_at,
                    'released_at' => $now,
                    'refunded_by' => $userId,
                    'refund_method' => $refundMethod,
                    'bank_account_id' => $bankAccountId,
                    'notes' => $data['notes'] ?? null,
                ]);

                $journal = $this->journalPostingEngine->postJournalEntry(
                    $lines,
                    'security_deposit_refunded',
                    (int) $refundedDeposit->id,
                    "Security deposit refunded – {$loan->loan_code}"
                );

                $this->voucherService->createVoucherFromJournalEntry(
                    $journal,
                    'payment',
                    [
                        'payment_method' => $refundMethod,
                        'bank_account_id' => $bankAccountId,
                        'description' => "Security deposit refunded – {$loan->loan_code}",
                    ]
                );

                Log::info('Security deposit partially refunded', [
                    'original_deposit_id' => (int) $deposit->id,
                    'refunded_deposit_id' => (int) $refundedDeposit->id,
                    'loan_id' => (int) $loan->id,
                    'customer_id' => (int) $deposit->customer_id,
                    'refund_amount' => (float) $refundAmount,
                    'remaining_amount' => (float) $remaining,
                    'refund_method' => $refundMethod,
                    'bank_account_id' => $bankAccountId,
                    'refunded_by' => $userId,
                ]);

                return $refundedDeposit;
            }

            $deposit->status = 'refunded';
            $deposit->released_at = $now;
            $deposit->refunded_by = $userId;
            $deposit->refund_method = $refundMethod;
            $deposit->bank_account_id = $bankAccountId;
            $deposit->notes = $data['notes'] ?? null;
            $deposit->save();

            $journal = $this->journalPostingEngine->postJournalEntry(
                $lines,
                'security_deposit_refunded',
                (int) $deposit->id,
                "Security deposit refunded – {$loan->loan_code}"
            );

            $this->voucherService->createVoucherFromJournalEntry(
                $journal,
                'payment',
                [
                    'payment_method' => $refundMethod,
                    'bank_account_id' => $bankAccountId,
                    'description' => "Security deposit refunded – {$loan->loan_code}",
                ]
            );

            Log::info('Security deposit refunded', [
                'loan_id' => (int) $loan->id,
                'deposit_id' => (int) $deposit->id,
                'customer_id' => (int) $deposit->customer_id,
                'amount' => (float) $refundAmount,
                'refund_method' => $refundMethod,
                'bank_account_id' => $bankAccountId,
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
                null,
                null,
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
