<?php

namespace App\Services\Loans\Fees;

use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\LoanFeeApplications;
use App\Models\Loans;
use App\Services\Accounting\JournalPostingEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanFeePaymentService
{
    public function __construct(
        private readonly JournalPostingEngine $journalPostingEngine,
    ) {
    }

    /**
     * Pay a pending loan fee.
     *
     * @param int $feeApplicationId The loan fee application ID
     * @param float $amount The amount to pay
     * @param string $paymentMethod The payment method code
     * @param int|null $bankAccountId The bank account ID (for bank transfer/mobile money)
     * @param string|null $notes Optional notes
     * @param int $userId The user ID processing the payment
     * @return LoanFeeApplications
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function payFee(
        int $feeApplicationId,
        float $amount,
        string $paymentMethod,
        ?int $bankAccountId,
        ?string $notes,
        int $userId,
    ): LoanFeeApplications {
        return DB::transaction(function () use ($feeApplicationId, $amount, $paymentMethod, $bankAccountId, $notes, $userId) {
            // Lock the fee application record
            $feeApplication = LoanFeeApplications::lockForUpdate()->findOrFail($feeApplicationId);

            // Validate the fee is not already paid
            if ($feeApplication->is_paid) {
                throw new \InvalidArgumentException('This fee has already been paid.');
            }

            // Validate amount doesn't exceed fee amount
            $feeAmount = (float) $feeApplication->amount;
            if ($amount > $feeAmount) {
                throw new \InvalidArgumentException("Payment amount ({$amount}) cannot exceed fee amount ({$feeAmount}).");
            }
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Payment amount must be greater than 0.');
            }

            $loan = Loans::lockForUpdate()->findOrFail($feeApplication->loan_id);

            // Validate loan is in a state that allows fee payments
            if (!in_array($loan->status, ['disbursed', 'partially_paid', 'pending', 'approved'], true)) {
                throw new \InvalidArgumentException("Cannot pay fees for loans with status: {$loan->status}");
            }

            // Validate bank account if required
            if (in_array($paymentMethod, ['bank_transfer', 'mobile_money'], true)) {
                if (empty($bankAccountId)) {
                    throw new \InvalidArgumentException('Bank account is required for this payment method.');
                }
                $bankAccount = BankAccounts::find($bankAccountId);
                if (!$bankAccount) {
                    throw new \InvalidArgumentException('Selected bank account not found.');
                }
            }

            $now = Carbon::now();

            // Update the fee application record
            $feeApplication->update([
                'is_paid' => true,
                'paid_amount' => $amount,
                'payment_date' => $now->toDateString(),
                'payment_method' => $paymentMethod,
                'payment_reference' => $notes,
                'paid_by' => $userId,
                'paid_at' => $now,
            ]);

            // Post journal entry for the fee payment
            $this->postFeePaymentJournalEntry($loan, $feeApplication, $amount, $paymentMethod, $bankAccountId);

            Log::info('Loan fee payment processed', [
                'fee_application_id' => $feeApplicationId,
                'loan_id' => $loan->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'paid_by' => $userId,
            ]);

            return $feeApplication->fresh();
        });
    }

    /**
     * Pay all pending fees for a loan.
     *
     * @param int $loanId The loan ID
     * @param string $paymentMethod The payment method code
     * @param int|null $bankAccountId The bank account ID
     * @param string|null $notes Optional notes
     * @param int $userId The user ID processing the payment
     * @return array Array of paid fee applications
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function payAllPendingFees(
        int $loanId,
        string $paymentMethod,
        ?int $bankAccountId,
        ?string $notes,
        int $userId,
    ): array {
        return DB::transaction(function () use ($loanId, $paymentMethod, $bankAccountId, $notes, $userId) {
            $pendingFees = LoanFeeApplications::where('loan_id', $loanId)
                ->where('is_paid', false)
                ->get();

            if ($pendingFees->isEmpty()) {
                throw new \InvalidArgumentException('No pending fees found for this loan.');
            }

            $paidFees = [];
            foreach ($pendingFees as $fee) {
                $paidFees[] = $this->payFee(
                    $fee->id,
                    (float) $fee->amount,
                    $paymentMethod,
                    $bankAccountId,
                    $notes,
                    $userId,
                );
            }

            return $paidFees;
        });
    }

    /**
     * Get pending fees total for a loan.
     *
     * @param int $loanId The loan ID
     * @return float
     */
    public function getPendingFeesTotal(int $loanId): float
    {
        return (float) LoanFeeApplications::where('loan_id', $loanId)
            ->where('is_paid', false)
            ->sum('amount');
    }

    /**
     * Get paid fees total for a loan.
     *
     * @param int $loanId The loan ID
     * @return float
     */
    public function getPaidFeesTotal(int $loanId): float
    {
        return (float) LoanFeeApplications::where('loan_id', $loanId)
            ->where('is_paid', true)
            ->sum('paid_amount');
    }

    /**
     * Post journal entry for fee payment.
     *
     * @param Loans $loan
     * @param LoanFeeApplications $feeApplication
     * @param float $amount
     * @param string $paymentMethod
     * @param int|null $bankAccountId
     * @return void
     * @throws \Exception
     */
    private function postFeePaymentJournalEntry(
        Loans $loan,
        LoanFeeApplications $feeApplication,
        float $amount,
        string $paymentMethod,
        ?int $bankAccountId,
    ): void {
        $feeName = optional($feeApplication->loanProductFee)->loanFee->name ?? 'Loan Fee';

        // Build journal entry lines for fee payment
        // Debit: Cash/Bank (Asset)
        // Credit: Fee Income (Income)

        $lines = [];

        // Determine the debit account (Cash/Bank)
        if ($bankAccountId) {
            $bankAccount = BankAccounts::find($bankAccountId);
            if ($bankAccount && $bankAccount->chart_of_account_id) {
                $debitAccountId = $bankAccount->chart_of_account_id;
            } else {
                // Fallback to suspense account or cash account
                $debitAccountId = $this->getCashAccountId($loan->subshop_id);
            }
        } else {
            $debitAccountId = $this->getCashAccountId($loan->subshop_id);
        }

        // Credit account - use loan's fee income account
        $creditAccountId = $loan->fee_income_account_id;
        if (!$creditAccountId) {
            throw new \InvalidArgumentException('Loan product missing fee_income_account_id for fee payment posting.');
        }

        $description = "Fee payment - {$feeName} for loan {$loan->loan_code}";

        $lines[] = [
            'account_id' => $debitAccountId,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
        ];

        $lines[] = [
            'account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
        ];

        // Post the journal entry using JournalPostingEngine
        $this->journalPostingEngine->postJournalEntry(
            $lines,
            'loan_fee_payment',
            $feeApplication->id,
            $description,
            null,
            $loan->subshop_id,
        );
    }

    /**
     * Get cash account ID for a subshop.
     *
     * @param int $subshopId
     * @return int
     * @throws \Exception
     */
    private function getCashAccountId(int $subshopId): int
    {
        // Try to find a cash account in the charts of accounts
        $cashAccount = ChartsOfAccount::where('subshop_id', $subshopId)
            ->where(function ($query) {
                $query->where('account_name', 'like', '%cash%')
                    ->orWhere('account_name', 'like', '%petty cash%');
            })
            ->where('is_active', true)
            ->first();

        if ($cashAccount) {
            return $cashAccount->id;
        }

        throw new \InvalidArgumentException('No cash account configured for this subshop.');
    }
}
