<?php

declare(strict_types=1);

namespace App\Services\Loans\SecurityDeposits;

use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\LoanSecurityDeposit;
use App\Models\Loans;
use App\Models\PaymentMethodAccount;
use App\Models\SecurityDepositForfeitureAccount;
use App\Models\SecurityDepositLiabilityAccount;
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

        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active subshop context is required.');
        }

        // Get and validate liability account from configuration
        $liabilityAccountId = $this->getSecurityDepositLiabilityAccount($subshopId);

        // Resolve cash/bank account with proper validation (Asset Class 1)
        $cashAccountId = $this->resolvePaymentSourceAccountId($paymentMethod, $paymentBankAccountId, $subshopId);

        Log::info('Processing security deposit collection', [
            'subshop_id' => $subshopId,
            'loan_id' => $loanId,
            'borrower_id' => $borrowerId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_bank_account_id' => $paymentBankAccountId,
            'cash_account_id' => $cashAccountId,
            'liability_account_id' => $liabilityAccountId,
        ]);

        return DB::transaction(function () use ($borrowerId, $loanId, $amount, $paymentMethod, $paymentBankAccountId, $notes, $subshopId, $liabilityAccountId, $cashAccountId) {
            $loan = Loans::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();

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

            // Build journal entry with properly resolved accounts
            $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
                ->reset()
                ->addDebit($cashAccountId, $amount, 'Security deposit collected – cash/bank in')
                ->addCredit($liabilityAccountId, $amount, 'Security deposit liability – credit')
                ->getLines();

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

            Log::info('Security deposit collected successfully', [
                'loan_id' => (int) $loan->id,
                'deposit_id' => (int) $deposit->id,
                'customer_id' => $borrowerId,
                'amount' => $amount,
                'cash_account_id' => $cashAccountId,
                'liability_account_id' => $liabilityAccountId,
            ]);

            return $deposit;
        });
    }

    /**
     * @param array{refund_method:string, bank_account_id?:int|null, notes?:string|null} $data
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

        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active subshop context is required.');
        }

        $bankAccountId = isset($data['bank_account_id']) && $data['bank_account_id'] ? (int) $data['bank_account_id'] : null;
        $requiresBank = in_array($refundMethod, ['bank_transfer', 'mobile_money'], true);
        if ($requiresBank && !$bankAccountId) {
            throw new InvalidArgumentException('Bank account is required for this refund method.');
        }

        // Get and validate liability account from configuration
        $liabilityAccountId = $this->getSecurityDepositLiabilityAccount($subshopId);

        // Resolve cash/bank account with proper validation (Asset Class 1)
        $cashAccountId = $this->resolvePaymentSourceAccountId($refundMethod, $bankAccountId, $subshopId);

        Log::info('Processing security deposit refund', [
            'subshop_id' => $subshopId,
            'deposit_id' => $depositId,
            'refund_amount' => $refundAmount,
            'refund_method' => $refundMethod,
            'bank_account_id' => $bankAccountId,
            'cash_account_id' => $cashAccountId,
            'liability_account_id' => $liabilityAccountId,
        ]);

        return DB::transaction(function () use ($depositId, $userId, $refundAmount, $refundMethod, $bankAccountId, $liabilityAccountId, $cashAccountId, $data, $subshopId) {
            $deposit = LoanSecurityDeposit::query()->whereKey($depositId)->lockForUpdate()->firstOrFail();
            $loan = Loans::query()->whereKey((int) $deposit->loan_id)->lockForUpdate()->firstOrFail();

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

            $now = Carbon::now();

            $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
                ->reset()
                ->addDebit($liabilityAccountId, $refundAmount, 'Security deposit refunded – liability reduction')
                ->addCredit($cashAccountId, $refundAmount, 'Security deposit refunded – cash/bank outflow')
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

    public function applyDepositToLoan(int $depositId, int $loanId, float $amount, ?string $notes = null): LoanSecurityDeposit
    {
        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active subshop context is required.');
        }

        // Get liability account for passing to payment processor as source account
        $liabilityAccountId = $this->getSecurityDepositLiabilityAccount($subshopId);

        Log::info('Processing security deposit apply to loan', [
            'subshop_id' => $subshopId,
            'deposit_id' => $depositId,
            'target_loan_id' => $loanId,
            'amount' => $amount,
            'liability_account_id' => $liabilityAccountId,
        ]);

        return DB::transaction(function () use ($depositId, $loanId, $amount, $notes, $subshopId, $liabilityAccountId) {
            $deposit = LoanSecurityDeposit::query()->whereKey($depositId)->lockForUpdate()->firstOrFail();
            $sourceLoan = Loans::query()->whereKey((int) $deposit->loan_id)->lockForUpdate()->firstOrFail();
            $targetLoan = Loans::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();

            if ((int) $deposit->subshop_id !== $subshopId || (int) $targetLoan->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $deposit->status !== 'held') {
                throw new InvalidArgumentException('Only held deposits can be applied.');
            }

            if ((int) $deposit->customer_id !== (int) $targetLoan->customer_id) {
                throw new InvalidArgumentException('Deposit borrower must match target loan borrower.');
            }

            // Validate amount does not exceed deposit balance
            if ($amount > (float) $deposit->amount) {
                throw new InvalidArgumentException(
                    'Apply amount (' . number_format($amount, 2) . ') exceeds deposit balance (' . number_format((float) $deposit->amount, 2) . ').'
                );
            }

            // Handle partial application
            $remainingAmount = (float) $deposit->amount - $amount;

            if ($remainingAmount > 0) {
                // Create new deposit record for remaining amount
                LoanSecurityDeposit::create([
                    'loan_id' => $deposit->loan_id,
                    'customer_id' => $deposit->customer_id,
                    'subshop_id' => $deposit->subshop_id,
                    'amount' => $remainingAmount,
                    'status' => 'held',
                    'notes' => 'Remaining from partial apply of deposit #' . $deposit->id,
                ]);

                // Update original deposit to applied amount
                $deposit->amount = $amount;
            }

            $deposit->status = 'applied';
            $deposit->released_at = Carbon::now();
            $deposit->applied_to_loan_id = (int) $targetLoan->id;
            $deposit->notes = $notes ? ($notes . "\nPartial apply of deposit. Original amount: " . number_format((float) $deposit->amount + $remainingAmount, 2)) : ('Partial apply of deposit. Original amount: ' . number_format((float) $deposit->amount + $remainingAmount, 2));
            $deposit->save();

            // PaymentProcessor handles the journal entry posting with proper allocation
            // (principal/interest/penalty) debiting the security deposit liability account
            $payment = $this->paymentProcessor->processPayment(
                $targetLoan,
                (int) $deposit->customer_id,
                $amount,
                'savings',
                null,
                null,
                Carbon::now(),
                'Applied from security deposit – source loan: ' . $sourceLoan->loan_code,
                null, // Use default strategy
                $liabilityAccountId, // Source account override for security deposit liability
            );

            Log::info('Security deposit applied to loan successfully', [
                'source_loan_id' => (int) $sourceLoan->id,
                'target_loan_id' => (int) $targetLoan->id,
                'deposit_id' => (int) $deposit->id,
                'payment_id' => (int) $payment->id,
                'customer_id' => (int) $deposit->customer_id,
                'amount_applied' => $amount,
                'amount_remaining' => $remainingAmount,
                'liability_account_id' => $liabilityAccountId,
            ]);

            return $deposit;
        });
    }

    public function forfeitDeposit(int $depositId, float $amount, ?string $notes = null): LoanSecurityDeposit
    {
        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            throw new InvalidArgumentException('Active subshop context is required.');
        }

        // Get and validate liability account from configuration
        $liabilityAccountId = $this->getSecurityDepositLiabilityAccount($subshopId);

        Log::info('Processing security deposit forfeit', [
            'subshop_id' => $subshopId,
            'deposit_id' => $depositId,
            'amount' => $amount,
            'liability_account_id' => $liabilityAccountId,
        ]);

        return DB::transaction(function () use ($depositId, $amount, $notes, $subshopId, $liabilityAccountId) {
            $deposit = LoanSecurityDeposit::query()->whereKey($depositId)->lockForUpdate()->firstOrFail();
            $loan = Loans::query()->whereKey((int) $deposit->loan_id)->lockForUpdate()->firstOrFail();

            if ((int) $deposit->subshop_id !== $subshopId) {
                abort(403);
            }

            if ((string) $deposit->status !== 'held') {
                throw new InvalidArgumentException('Only held deposits can be forfeited.');
            }

            // Validate amount does not exceed deposit balance
            if ($amount > (float) $deposit->amount) {
                throw new InvalidArgumentException(
                    'Forfeit amount (' . number_format($amount, 2) . ') exceeds deposit balance (' . number_format((float) $deposit->amount, 2) . ').'
                );
            }

            // Handle partial forfeiture
            $remainingAmount = (float) $deposit->amount - $amount;

            if ($remainingAmount > 0) {
                // Create new deposit record for remaining amount
                LoanSecurityDeposit::create([
                    'loan_id' => $deposit->loan_id,
                    'customer_id' => $deposit->customer_id,
                    'subshop_id' => $deposit->subshop_id,
                    'amount' => $remainingAmount,
                    'status' => 'held',
                    'notes' => 'Remaining from partial forfeit of deposit #' . $deposit->id,
                ]);

                // Update original deposit to forfeited amount
                $deposit->amount = $amount;
            }

            $deposit->status = 'forfeited';
            $deposit->released_at = Carbon::now();
            $deposit->notes = $notes ? ($notes . "\nPartial forfeit of deposit. Original amount: " . number_format((float) $deposit->amount + $remainingAmount, 2)) : ('Partial forfeit of deposit. Original amount: ' . number_format((float) $deposit->amount + $remainingAmount, 2));
            $deposit->save();

            // Get forfeiture income account from configuration
            $forfeitIncomeAccountId = $this->getSecurityDepositForfeitureAccount($subshopId);

            // Build journal entry with configured liability account
            $lines = app(\App\Services\Accounting\JournalEntryBuilder::class)
                ->reset()
                ->addDebit($liabilityAccountId, $amount, "Security deposit forfeited – {$loan->loan_code}")
                ->addCredit($forfeitIncomeAccountId, $amount, "Security deposit forfeiture income – {$loan->loan_code}")
                ->getLines();

            $this->journalPostingEngine->postJournalEntry(
                $lines,
                'security_deposit_forfeited',
                (int) $deposit->id,
                "Security deposit forfeited – {$loan->loan_code}"
            );

            Log::info('Security deposit forfeited successfully', [
                'loan_id' => (int) $loan->id,
                'deposit_id' => (int) $deposit->id,
                'customer_id' => (int) $deposit->customer_id,
                'amount_forfeited' => $amount,
                'amount_remaining' => $remainingAmount,
                'liability_account_id' => $liabilityAccountId,
                'forfeit_income_account_id' => $forfeitIncomeAccountId,
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

    /**
     * Get security deposit liability account for a subshop with full validation
     */
    public function getSecurityDepositLiabilityAccount(int $subshopId): int
    {
        Log::debug('Getting security deposit liability account', ['subshop_id' => $subshopId]);

        $liabilityAccount = SecurityDepositLiabilityAccount::forSubshop($subshopId);

        if (!$liabilityAccount) {
            Log::error('Security deposit liability account not configured', ['subshop_id' => $subshopId]);
            throw new InvalidArgumentException(
                'Security deposit liability account is not configured for this branch. ' .
                'Please configure it first before processing security deposits.'
            );
        }

        Log::debug('Security deposit liability account found', [
            'liability_account_id' => $liabilityAccount->id,
            'chart_of_account_id' => $liabilityAccount->chart_of_account_id,
        ]);

        // Validate that account is still a liability account and active
        $chartAccount = ChartsOfAccount::query()->whereKey($liabilityAccount->chart_of_account_id)->first();

        if (!$chartAccount) {
            Log::error('Configured security deposit liability account no longer exists', [
                'liability_account_id' => $liabilityAccount->chart_of_account_id,
            ]);
            throw new InvalidArgumentException('Configured security deposit liability account no longer exists.');
        }

        if ((int) $chartAccount->accountClass->code !== 2) {
            Log::error('Configured security deposit liability account not liability class', [
                'account_class_code' => $chartAccount->accountClass->code,
            ]);
            throw new InvalidArgumentException('Configured security deposit liability account is not a liability account (Account Class 2).');
        }

        if (!$chartAccount->is_active) {
            Log::error('Configured security deposit liability account not active', [
                'liability_account_id' => $liabilityAccount->chart_of_account_id,
            ]);
            throw new InvalidArgumentException('Configured security deposit liability account is not active.');
        }

        if ((int) $chartAccount->subshop_id !== $subshopId) {
            Log::error('Configured security deposit liability account wrong subshop', [
                'account_subshop_id' => $chartAccount->subshop_id,
                'session_subshop_id' => $subshopId,
            ]);
            throw new InvalidArgumentException('Configured security deposit liability account does not belong to this branch.');
        }

        Log::debug('Security deposit liability account validated', [
            'liability_account_id' => $liabilityAccount->chart_of_account_id,
            'account_name' => $chartAccount->account_name,
        ]);

        return (int) $liabilityAccount->chart_of_account_id;
    }

    /**
     * Resolve payment source account (cash/bank) with proper validation
     * Returns Asset Class 1 account ID
     */
    private function resolvePaymentSourceAccountId(string $paymentMethod, ?int $bankAccountId, int $subshopId): int
    {
        Log::debug('Resolving payment source account', [
            'payment_method' => $paymentMethod,
            'bank_account_id' => $bankAccountId,
            'subshop_id' => $subshopId,
        ]);

        // If bank account provided, use its chart of account
        if ($bankAccountId) {
            $bank = BankAccounts::query()->whereKey($bankAccountId)->first();

            if (!$bank) {
                Log::error('Bank account not found', ['bank_account_id' => $bankAccountId]);
                throw new InvalidArgumentException('Selected bank account not found.');
            }

            if ((int) $bank->subshop_id !== $subshopId) {
                Log::error('Bank account wrong subshop', [
                    'bank_subshop_id' => $bank->subshop_id,
                    'session_subshop_id' => $subshopId,
                ]);
                throw new InvalidArgumentException('Selected bank account does not belong to this branch.');
            }

            if (!$bank->is_active) {
                Log::error('Bank account not active', ['bank_account_id' => $bankAccountId]);
                throw new InvalidArgumentException('Selected bank account is not active.');
            }

            $accountId = (int) $bank->chart_of_account_id;
            if ($accountId <= 0) {
                Log::error('Bank account missing chart_of_account_id', ['bank_account_id' => $bankAccountId]);
                throw new InvalidArgumentException('Bank account is not linked to a chart of account.');
            }

            // Validate that bank account's COA is Asset class (Class 1)
            $chartAccount = ChartsOfAccount::query()->whereKey($accountId)->first();
            if (!$chartAccount) {
                Log::error('Bank linked chart account not found', ['chart_account_id' => $accountId]);
                throw new InvalidArgumentException('Bank account linked chart of account not found.');
            }

            if ((int) $chartAccount->accountClass->code !== 1) {
                Log::error('Bank linked chart account not Asset class', [
                    'chart_account_id' => $accountId,
                    'account_class_code' => $chartAccount->accountClass->code,
                ]);
                throw new InvalidArgumentException('Bank account must be linked to an Asset account (Class 1).');
            }

            Log::debug('Using bank account mapping', [
                'bank_account_id' => $bankAccountId,
                'chart_account_id' => $accountId,
            ]);

            return $accountId;
        }

        // Look up payment method to GL account mapping for cash/mobile_money/etc.
        $method = trim(strtolower($paymentMethod));
        if ($method === '') {
            Log::error('Empty payment method');
            throw new InvalidArgumentException('Payment method is required to resolve payment account.');
        }

        $mapping = PaymentMethodAccount::query()
            ->where('subshop_id', $subshopId)
            ->where('payment_method', $method)
            ->first();

        if (!$mapping) {
            Log::error('Payment method account mapping not found', [
                'subshop_id' => $subshopId,
                'payment_method' => $method,
            ]);
            throw new InvalidArgumentException("Payment method '{$paymentMethod}' is not mapped to a GL account. Please configure it in Payment Method Accounts.");
        }

        $accountId = (int) $mapping->chart_of_account_id;
        if ($accountId <= 0) {
            Log::error('Invalid chart_of_account_id in payment method mapping', [
                'payment_method' => $method,
                'mapping_id' => $mapping->id,
            ]);
            throw new InvalidArgumentException("Invalid chart of account for payment method '{$paymentMethod}'.");
        }

        // Validate that mapped COA is Asset class (Class 1)
        $chartAccount = ChartsOfAccount::query()->whereKey($accountId)->first();
        if (!$chartAccount) {
            Log::error('Payment method linked chart account not found', [
                'chart_account_id' => $accountId,
                'payment_method' => $method,
            ]);
            throw new InvalidArgumentException('Payment method linked chart of account not found.');
        }

        if ((int) $chartAccount->accountClass->code !== 1) {
            Log::error('Payment method linked chart account not Asset class', [
                'chart_account_id' => $accountId,
                'payment_method' => $method,
                'account_class_code' => $chartAccount->accountClass->code,
            ]);
            throw new InvalidArgumentException('Payment method must be mapped to an Asset account (Class 1).');
        }

        if (!$chartAccount->is_active) {
            Log::error('Payment method linked chart account not active', [
                'chart_account_id' => $accountId,
                'payment_method' => $method,
            ]);
            throw new InvalidArgumentException('Payment method linked chart of account is not active.');
        }

        if ((int) $chartAccount->subshop_id !== $subshopId) {
            Log::error('Payment method linked chart account wrong subshop', [
                'chart_account_id' => $accountId,
                'account_subshop_id' => $chartAccount->subshop_id,
                'session_subshop_id' => $subshopId,
            ]);
            throw new InvalidArgumentException('Payment method linked chart of account does not belong to this branch.');
        }

        Log::debug('Using payment method mapping', [
            'payment_method' => $method,
            'chart_account_id' => $accountId,
        ]);

        return $accountId;
    }

    /**
     * Get security deposit forfeiture income account for a subshop with full validation
     */
    public function getSecurityDepositForfeitureAccount(int $subshopId): int
    {
        Log::debug('Getting security deposit forfeiture income account', ['subshop_id' => $subshopId]);

        $forfeitureAccount = SecurityDepositForfeitureAccount::forSubshop($subshopId);

        if (!$forfeitureAccount) {
            Log::error('Security deposit forfeiture income account not configured', ['subshop_id' => $subshopId]);
            throw new InvalidArgumentException(
                'Security deposit forfeiture income account is not configured for this branch. ' .
                'Please configure it first before processing forfeiture transactions.'
            );
        }

        Log::debug('Security deposit forfeiture income account found', [
            'forfeiture_account_id' => $forfeitureAccount->id,
            'chart_of_account_id' => $forfeitureAccount->chart_of_account_id,
        ]);

        // Validate that account is still an income account (Class 4 or 5) and active
        $chartAccount = ChartsOfAccount::query()->whereKey($forfeitureAccount->chart_of_account_id)->first();

        if (!$chartAccount) {
            Log::error('Configured security deposit forfeiture income account no longer exists', [
                'forfeiture_account_id' => $forfeitureAccount->chart_of_account_id,
            ]);
            throw new InvalidArgumentException('Configured security deposit forfeiture income account no longer exists.');
        }

        $accountClass = (int) ($chartAccount->accountClass?->code ?? 0);
        if (!in_array($accountClass, [4, 5], true)) {
            Log::error('Configured forfeiture income account not income class', [
                'account_class_code' => $accountClass,
            ]);
            throw new InvalidArgumentException('Configured forfeiture income account must be a revenue/income account (Account Class 4 or 5).');
        }

        if (!$chartAccount->is_active) {
            Log::error('Configured forfeiture income account not active', [
                'forfeiture_account_id' => $forfeitureAccount->chart_of_account_id,
            ]);
            throw new InvalidArgumentException('Configured forfeiture income account is not active.');
        }

        if ((int) $chartAccount->subshop_id !== $subshopId) {
            Log::error('Configured forfeiture income account wrong subshop', [
                'account_subshop_id' => $chartAccount->subshop_id,
                'session_subshop_id' => $subshopId,
            ]);
            throw new InvalidArgumentException('Configured forfeiture income account does not belong to this branch.');
        }

        Log::debug('Security deposit forfeiture income account validated', [
            'forfeiture_account_id' => $forfeitureAccount->chart_of_account_id,
            'account_name' => $chartAccount->account_name,
        ]);

        return (int) $forfeitureAccount->chart_of_account_id;
    }
}
