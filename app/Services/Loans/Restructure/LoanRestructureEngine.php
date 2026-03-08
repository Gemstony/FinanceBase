<?php

declare(strict_types=1);

namespace App\Services\Loans\Restructure;

use App\Models\LoanRestructureInstallments;
use App\Models\LoanRestructures;
use App\Models\Loans;
use App\Services\Accounting\JournalPostingEngine;
use App\Services\Loans\Ledger\LoanTransactionLedger;
use App\Services\Loans\LoanScheduleEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LoanRestructureEngine
{
    public function __construct(
        private readonly RestructureValidator $validator,
        private readonly OutstandingBalanceCalculator $balanceCalculator,
        private readonly InstallmentScheduleCloser $scheduleCloser,
        private readonly RestructureScheduleGenerator $scheduleGenerator,
        private readonly LoanScheduleEngine $scheduleEngine,
        private readonly LoanTransactionLedger $ledger,
        private readonly JournalPostingEngine $journalPostingEngine,
    ) {
    }

    /**
     * Execute an approved restructure request.
     *
     * This method enforces:
     * - transactional execution
     * - versioned schedule generation
     * - no deletion of historical installments
     */
    public function execute(LoanRestructures $request): LoanRestructures
    {
        return DB::transaction(function () use ($request) {
            $request = LoanRestructures::query()->whereKey((int) $request->id)->lockForUpdate()->firstOrFail();

            if ((string) $request->status !== 'approved') {
                throw new RuntimeException('Restructure request must be approved before execution.');
            }

            $loan = Loans::query()->whereKey((int) $request->loan_id)->lockForUpdate()->firstOrFail();

            $this->validator->validateLoanEligibility($loan);

            $effectiveDate = $request->restructure_date ? Carbon::parse($request->restructure_date)->startOfDay() : Carbon::today()->startOfDay();

            $newInterestRate = (float) ($request->new_interest_rate ?? $loan->interest_rate);
            $newTerm = (int) ($request->new_term ?? $request->new_term_months ?? $loan->installments);
            $gracePeriod = (int) ($request->grace_period ?? 0);
            $capitalizeInterest = ((float) ($request->capitalized_interest ?? 0.0)) > 0.0;

            $this->validator->validateTerms($newInterestRate, $newTerm, $gracePeriod, $capitalizeInterest);

            $balances = $this->balanceCalculator->calculate($loan);

            $request->old_principal_balance = (float) $balances['principal'];
            $request->old_interest_balance = (float) $balances['interest'];
            $request->old_penalty_balance = (float) $balances['penalty'];

            // Interest capitalization decision.
            $capitalizedInterest = (float) ($request->capitalized_interest ?? 0.0);
            if ($capitalizeInterest && $capitalizedInterest <= 0) {
                $capitalizedInterest = (float) $balances['interest'];
                $request->capitalized_interest = $capitalizedInterest;
            }

            $newPrincipal = (float) $balances['principal'];
            if ($capitalizeInterest) {
                $newPrincipal = round($newPrincipal + $capitalizedInterest, 2);
            }

            // Determine latest schedule version.
            $latestVersion = (int) (\App\Models\LoanInstallments::query()
                ->where('loan_id', (int) $loan->id)
                ->max('schedule_version') ?: 1);

            // Preserve history by snapshotting the latest schedule rows.
            $latestRows = \App\Models\LoanInstallments::query()
                ->where('loan_id', (int) $loan->id)
                ->where('schedule_version', $latestVersion)
                ->orderBy('installment_number')
                ->get();

            foreach ($latestRows as $ins) {
                LoanRestructureInstallments::create([
                    'restructure_id' => (int) $request->id,
                    'loan_id' => (int) $loan->id,
                    'installment_id' => (int) $ins->id,
                    'installment_number' => (int) $ins->installment_number,
                    'old_due_date' => $ins->due_date,
                    'old_principal_due' => (float) $ins->principal_due,
                    'old_interest_due' => (float) $ins->interest_due,
                    'old_fees_due' => (float) $ins->fees_due,
                    'old_penalty_due' => (float) $ins->penalty_due,
                    'principal_paid' => (float) $ins->principal_paid,
                    'interest_paid' => (float) $ins->interest_paid,
                    'fees_paid' => (float) $ins->fees_paid,
                    'penalty_paid' => (float) $ins->penalty_paid,
                ]);
            }

            // Close existing installments (no delete).
            $newVersion = $this->scheduleCloser->closeLatestSchedule($loan, $latestVersion);

            // Generate and store new schedule.
            $schedule = $this->scheduleGenerator->generate($loan, $newPrincipal, $newInterestRate, $newTerm, $effectiveDate);

            $this->scheduleEngine->storeSchedule($loan, $schedule, $newVersion);

            // Update loan terms.
            $loan->interest_rate = $newInterestRate;
            $loan->installments = $newTerm;
            $last = end($schedule);
            $loan->maturity_date = !empty($last['due_date'])
                ? Carbon::parse((string) $last['due_date'])->toDateString()
                : $loan->maturity_date;

            // If interest is capitalized, update principal_amount to new principal base.
            if ($capitalizeInterest) {
                $loan->principal_amount = $newPrincipal;
            }
            $loan->save();

            // Record ledger event.
            $this->ledger->recordRestructure(
                $loan,
                (float) $request->old_principal_balance,
                (float) $request->old_interest_balance,
                (float) $request->old_penalty_balance,
                (int) $request->id
            );

            // Post accounting adjustment for capitalization.
            if ($capitalizeInterest && $capitalizedInterest > 0) {
                $principalAccountId = (int) ($loan->principal_account_id ?? 0);
                $interestReceivableAccountId = (int) ($loan->interest_receivable_account_id ?? 0);

                if ($principalAccountId <= 0 || $interestReceivableAccountId <= 0) {
                    throw new RuntimeException('Unable to post capitalization: required loan accounts are missing.');
                }

                $this->journalPostingEngine->postJournalEntry(
                    [
                        [
                            'account_id' => $principalAccountId,
                            'debit' => $capitalizedInterest,
                            'credit' => 0,
                            'description' => 'Capitalize interest into principal',
                        ],
                        [
                            'account_id' => $interestReceivableAccountId,
                            'debit' => 0,
                            'credit' => $capitalizedInterest,
                            'description' => 'Reduce interest receivable (capitalized)',
                        ],
                    ],
                    'loan_restructure',
                    (int) $request->id,
                    'Loan restructure – interest capitalization'
                );
            }

            $request->status = 'executed';
            $request->executed_at = now();
            $request->save();

            return $request;
        });
    }
}
