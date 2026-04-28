<?php

declare(strict_types=1);

namespace App\Services\Loans\WriteOff;

use App\Models\BankAccounts;
use App\Models\LoanInstallments;
use App\Models\LoanPayments;
use App\Models\LoanWriteOffAccount;
use App\Models\LoanWriteoffRecoveries;
use App\Models\LoanWriteoffs;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Accounting\VoucherService;
use App\Services\Loans\Ledger\LoanTransactionLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class LoanRecoveryProcessor
{
    /** @var array<int, array<string, mixed>> Cache of recovery income account IDs by subshop */
    private array $recoveryIncomeAccountCache = [];

    public function __construct(
        private readonly LoanTransactionLedger $ledger,
        private readonly JournalPostingEngine $journalPostingEngine,
        private readonly VoucherService $voucherService,
    ) {
    }

    /**
     * Get the recovery income account ID for a subshop.
     *
     * @param int $subshopId The subshop ID
     * @return int The recovery income account ID
     * @throws InvalidArgumentException If account is not configured
     */
    private function getRecoveryIncomeAccountId(int $subshopId): int
    {
        // Return cached result if available
        if (isset($this->recoveryIncomeAccountCache[$subshopId])) {
            return $this->recoveryIncomeAccountCache[$subshopId];
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        // Get all subshop IDs under this shop for validation
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $config = LoanWriteOffAccount::whereIn('subshop_id', $shopSubshopIds)->first();

        if (! $config) {
            $message = "Loan write-off accounts not configured. Recovery income account is required. (visit Accounting > Accounting Settings > Loan Write-off Accounts)";
            Log::warning($message, ['subshop_id' => $subshopId]);
            throw new InvalidArgumentException($message);
        }

        // Validate the recovery income account
        $incomeAccount = $config->recoveryIncomeAccount;
        if (! $incomeAccount) {
            $message = "Recovery income account not found (ID: {$config->recovery_income_account_id}) (visit Accounting > Accounting Settings > Loan Write-off Accounts)";
            Log::warning($message, ['subshop_id' => $subshopId, 'account_id' => $config->recovery_income_account_id]);
            throw new InvalidArgumentException($message);
        }

        if (! $incomeAccount->is_active) {
            $message = "Recovery income account is inactive (ID: {$config->recovery_income_account_id}) (visit Accounting > Accounting Settings > Loan Write-off Accounts)";
            Log::warning($message, ['subshop_id' => $subshopId, 'account_id' => $config->recovery_income_account_id]);
            throw new InvalidArgumentException($message);
        }

        // Validate it's a Revenue account (Class 4)
        $incomeClassId = $incomeAccount->accountClass->code;
        if ($incomeClassId != 4) {
            $message = "Recovery income account must be Class 4 (Revenue), got Class {$incomeClassId} (visit Accounting > Accounting Settings > Loan Write-off Accounts)";
            Log::warning($message, [
                'subshop_id' => $subshopId,
                'account_id' => $config->recovery_income_account_id,
                'account_class_id' => $incomeClassId,
            ]);
            throw new InvalidArgumentException($message);
        }

        // Cache and return
        $this->recoveryIncomeAccountCache[$subshopId] = (int) $config->recovery_income_account_id;

        Log::info('Recovery income account validated and cached', [
            'subshop_id' => $subshopId,
            'recovery_income_account_id' => $this->recoveryIncomeAccountCache[$subshopId],
        ]);

        return $this->recoveryIncomeAccountCache[$subshopId];
    }

    public function process(
        Loans $loan,
        string $recoveryDate,
        float $recoveredPrincipal,
        float $recoveredInterest,
        float $recoveredFees,
        float $recoveredPenalties,
        ?string $notes,
        int $recordedBy,
        ?int $bankAccountId = null,
        ?string $paymentMethod = null,
        ?string $transactionReference = null,
    ): LoanWriteoffRecoveries {
        if (Str::lower((string) $loan->status) !== 'written_off') {
            throw new RuntimeException('Recovery processing is only allowed for written-off loans.');
        }

        $recoveredPrincipal = round(max(0.0, $recoveredPrincipal), 2);
        $recoveredInterest = round(max(0.0, $recoveredInterest), 2);
        $recoveredFees = round(max(0.0, $recoveredFees), 2);
        $recoveredPenalties = round(max(0.0, $recoveredPenalties), 2);

        $totalRecovered = round($recoveredPrincipal + $recoveredInterest + $recoveredFees + $recoveredPenalties, 2);
        if ($totalRecovered <= 0.0) {
            throw new RuntimeException('Total recovered amount must be greater than 0.');
        }

        $writeoff = $this->getLatestWriteoff($loan);
        $date = Carbon::parse($recoveryDate)->toDateString();

        return DB::transaction(function () use (
            $loan,
            $writeoff,
            $date,
            $totalRecovered,
            $recoveredPrincipal,
            $recoveredInterest,
            $recoveredFees,
            $recoveredPenalties,
            $notes,
            $recordedBy,
            $bankAccountId,
            $paymentMethod,
            $transactionReference
        ) {
            $payment = LoanPayments::create([
                'loan_id' => $loan->id,
                'customer_id' => (int) $loan->customer_id,
                'user_id' => $recordedBy > 0 ? $recordedBy : null,
                'subshop_id' => (int) ($loan->subshop_id ?? 0),
                'amount' => $totalRecovered,
                'payment_date' => $date,
                'payment_method' => $paymentMethod,
                'reference_number' => $transactionReference,
                'notes' => $notes,
                'status' => 'confirmed',
            ]);

            $recovery = LoanWriteoffRecoveries::create([
                'loan_id' => $loan->id,
                'writeoff_id' => $writeoff->id,
                'payment_id' => $payment->id,
                'recovery_date' => $date,
                'recovered_principal' => $recoveredPrincipal,
                'recovered_interest' => $recoveredInterest,
                'recovered_fees' => $recoveredFees,
                'recovered_penalties' => $recoveredPenalties,
                'total_recovered' => $totalRecovered,
                'notes' => $notes,
                'bank_account_id' => $bankAccountId,
                'payment_method' => $paymentMethod,
                'transaction_reference' => $transactionReference,
            ]);

            $this->ledger->recordRecovery(
                loan: $loan,
                amount: $totalRecovered,
                referenceId: (int) $recovery->id
            );

            // Get recovery income account from configuration
            $subshopId = (int) ($loan->subshop_id ?? 0);
            if ($subshopId <= 0) {
                throw new InvalidArgumentException('Loan must have a valid subshop_id for recovery posting');
            }
            $recoveryIncomeAccountId = $this->getRecoveryIncomeAccountId($subshopId);

            // Prepare recovery data for journal entry
            $recoveryData = [
                'principal' => $recoveredPrincipal,
                'interest' => $recoveredInterest,
                'fees' => $recoveredFees,
                'penalties' => $recoveredPenalties,
                'total' => $totalRecovered,
                'bank_account_id' => $bankAccountId,
                'payment_method' => $paymentMethod,
                'subshop_id' => $subshopId,
            ];

            // Create journal entry for the recovery (bank account balance is tracked via journal/voucher)
            $journal = $this->journalPostingEngine->postLoanRecovery(
                $recoveryData,
                $recoveryIncomeAccountId,
                (int) $recovery->id
            );

            // Create receipt voucher for the recovery
            $this->voucherService->createVoucherFromJournalEntry(
                $journal,
                'receipt',
                [
                    'payment_method' => $paymentMethod,
                    'bank_account_id' => $bankAccountId,
                    'description' => 'Loan write-off recovery receipt voucher # ' . $recovery->id,
                    'reference_type' => 'loan_writeoff_recovery',
                    'reference_id' => (int) $recovery->id,
                ]
            );

            Log::info('Loan recovery processed successfully', [
                'loan_id' => $loan->id,
                'recovery_id' => $recovery->id,
                'writeoff_id' => $writeoff->id,
                'total_recovered' => $totalRecovered,
                'subshop_id' => $subshopId,
            ]);

            return $recovery;
        });
    }

    public function processAutoSplit(
        Loans $loan,
        string $recoveryDate,
        float $amount,
        ?string $notes,
        int $recordedBy,
        ?int $bankAccountId = null,
        ?string $paymentMethod = null,
        ?string $transactionReference = null,
    ): LoanWriteoffRecoveries {
        $amount = round(max(0.0, $amount), 2);
        if ($amount <= 0.0) {
            throw new RuntimeException('Recovery amount must be greater than 0.');
        }

        $writeoff = $this->getLatestWriteoff($loan);

        $alreadyRecovered = LoanWriteoffRecoveries::query()
            ->where('writeoff_id', (int) $writeoff->id)
            ->selectRaw(
                'COALESCE(SUM(recovered_penalties),0) AS penalties, '
                . 'COALESCE(SUM(recovered_fees),0) AS fees, '
                . 'COALESCE(SUM(recovered_interest),0) AS interest, '
                . 'COALESCE(SUM(recovered_principal),0) AS principal'
            )
            ->first();

        $remainingPenalties = round(max(0.0, (float) $writeoff->penalties_written_off - (float) ($alreadyRecovered->penalties ?? 0)), 2);
        $remainingFees = round(max(0.0, (float) $writeoff->fees_written_off - (float) ($alreadyRecovered->fees ?? 0)), 2);
        $remainingInterest = round(max(0.0, (float) $writeoff->interest_written_off - (float) ($alreadyRecovered->interest ?? 0)), 2);
        $remainingPrincipal = round(max(0.0, (float) $writeoff->principal_written_off - (float) ($alreadyRecovered->principal ?? 0)), 2);

        $allocPenalties = min($remainingPenalties, $amount);
        $amount = round($amount - $allocPenalties, 2);

        $allocFees = min($remainingFees, $amount);
        $amount = round($amount - $allocFees, 2);

        $allocInterest = min($remainingInterest, $amount);
        $amount = round($amount - $allocInterest, 2);

        $allocPrincipal = min($remainingPrincipal, $amount);

        $totalAllocated = round($allocPenalties + $allocFees + $allocInterest + $allocPrincipal, 2);
        if ($totalAllocated <= 0.0) {
            throw new RuntimeException('No remaining written-off balances available to recover.');
        }

        return $this->process(
            loan: $loan,
            recoveryDate: $recoveryDate,
            recoveredPrincipal: $allocPrincipal,
            recoveredInterest: $allocInterest,
            recoveredFees: $allocFees,
            recoveredPenalties: $allocPenalties,
            notes: $notes,
            recordedBy: $recordedBy,
            bankAccountId: $bankAccountId,
            paymentMethod: $paymentMethod,
            transactionReference: $transactionReference,
        );
    }

    /**
     * Process a recovery payment for a written-off loan.
     *
     * Recovery accounting rules:
     * - Do NOT reopen the loan
     * - Track recovered amounts separately in loan_writeoff_recoveries
     * - Allocation priority is the same as normal: penalty -> fees -> interest -> principal
     */
    public function processRecovery(
        Loans $loan, 
        LoanPayments $payment,
        ?int $bankAccountId = null,
        ?string $paymentMethod = null,
        ?string $transactionReference = null
    ): LoanWriteoffRecoveries
    {
        if (Str::lower((string) $loan->status) !== 'written_off') {
            throw new RuntimeException('Recovery processing is only allowed for written-off loans.');
        }

        $writeoff = $this->getLatestWriteoff($loan);

        return DB::transaction(function () use ($loan, $payment, $writeoff, $bankAccountId, $paymentMethod, $transactionReference) {
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

            $recovery = LoanWriteoffRecoveries::create([
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
                'bank_account_id' => $bankAccountId,
                'payment_method' => $paymentMethod ?? $payment->payment_method,
                'transaction_reference' => $transactionReference ?? $payment->reference_number,
            ]);

            // Get recovery income account from configuration
            $subshopId = (int) ($loan->subshop_id ?? 0);
            if ($subshopId <= 0) {
                throw new InvalidArgumentException('Loan must have a valid subshop_id for recovery posting');
            }
            $recoveryIncomeAccountId = $this->getRecoveryIncomeAccountId($subshopId);

            // Prepare recovery data for journal entry
            $recoveryData = [
                'principal' => $recoveredPrincipal,
                'interest' => $recoveredInterest,
                'fees' => $recoveredFees,
                'penalties' => $recoveredPenalties,
                'total' => $totalRecovered,
                'bank_account_id' => $bankAccountId,
                'payment_method' => $paymentMethod ?? $payment->payment_method,
                'subshop_id' => $subshopId,
            ];

            // Create journal entry for the recovery (bank account balance is tracked via journal/voucher)
            $journal = $this->journalPostingEngine->postLoanRecovery(
                $recoveryData,
                $recoveryIncomeAccountId,
                (int) $recovery->id
            );

            // Create receipt voucher for the recovery
            $this->voucherService->createVoucherFromJournalEntry(
                $journal,
                'receipt',
                [
                    'payment_method' => $paymentMethod ?? $payment->payment_method,
                    'bank_account_id' => $bankAccountId,
                    'description' => 'Loan write-off recovery receipt voucher # ' . $recovery->id,
                    'reference_type' => 'loan_writeoff_recovery',
                    'reference_id' => (int) $recovery->id,
                ]
            );

            Log::info('Loan recovery processed successfully via payment', [
                'loan_id' => $loan->id,
                'recovery_id' => $recovery->id,
                'payment_id' => $payment->id,
                'total_recovered' => $totalRecovered,
                'subshop_id' => $subshopId,
            ]);

            return $recovery;
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
