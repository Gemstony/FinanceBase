<?php

namespace App\Services\Loans;

use App\Models\LoanInstallments;
use App\Models\Loans;
use App\Services\Loans\InterestCalculators\CompoundInterest;
use App\Services\Loans\InterestCalculators\FlatInterest;
use App\Services\Loans\InterestCalculators\InterestCalculatorInterface;
use App\Services\Loans\InterestCalculators\ReducingBalanceInterest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanScheduleEngine
{
    /**
     * @param array<string, InterestCalculatorInterface> $calculators
     */
    public function __construct(
        ?ScheduleDateGenerator $dateGenerator = null,
        ?InstallmentGenerator $installmentGenerator = null,
        private readonly array $calculators = []
    ) {
        $this->dateGenerator = $dateGenerator ?? new ScheduleDateGenerator();
        $this->installmentGenerator = $installmentGenerator ?? new InstallmentGenerator();
    }

    private readonly ScheduleDateGenerator $dateGenerator;
    private readonly InstallmentGenerator $installmentGenerator;

    /**
     * Generate a repayment schedule.
     *
     * @return array<int, array{installment_number:int,due_date:string,principal_amount:float,interest_amount:float,total_due:float,remaining_balance:float}>
     */
    public function generate(Loans $loan): array
    {
        $principal = (float) $loan->principal_amount;
        $rate = (float) $loan->interest_rate;
        $installmentsCount = (int) $loan->installments;

        if ($principal <= 0) {
            throw new InvalidArgumentException('Loan principal_amount must be greater than 0.');
        }

        if ($installmentsCount < 1) {
            throw new InvalidArgumentException('Loan installments must be at least 1.');
        }

        $repaymentFrequency = $this->resolveRepaymentFrequencyCode($loan);
        $interestMethodCode = $this->resolveInterestMethodCode($loan);

        $calculator = $this->resolveCalculator($interestMethodCode);

        $disbursementDate = Carbon::parse($loan->disbursement_date);
        $dates = $this->dateGenerator->generate($disbursementDate, $repaymentFrequency, $installmentsCount);

        $breakdown = $calculator->calculate($principal, $rate, $installmentsCount);

        return $this->installmentGenerator->generate($principal, $dates, $breakdown);
    }

    /**
     * Persist schedule into loan_installments.
     *
     * NOTE: This method will upsert by (loan_id, installment_number).
     */
    public function storeSchedule(Loans $loan, array $schedule): void
    {
        DB::transaction(function () use ($loan, $schedule) {
            $principalAccountId = (int) ($loan->principal_account_id ?? 0);
            $interestIncomeAccountId = (int) ($loan->interest_income_account_id ?? 0);
            $penaltyIncomeAccountId = (int) ($loan->penalty_income_account_id ?? 0);

            if ($principalAccountId <= 0) {
                throw new InvalidArgumentException('Unable to store schedule: loan principal_account_id is missing.');
            }
            if ($interestIncomeAccountId <= 0) {
                throw new InvalidArgumentException('Unable to store schedule: loan interest_income_account_id is missing.');
            }
            if ($penaltyIncomeAccountId <= 0) {
                throw new InvalidArgumentException('Unable to store schedule: loan penalty_income_account_id is missing.');
            }

            foreach ($schedule as $row) {
                LoanInstallments::updateOrCreate(
                    [
                        'loan_id' => $loan->id,
                        'installment_number' => (int) $row['installment_number'],
                    ],
                    [
                        'subshop_id' => $loan->subshop_id,
                        'principal_due' => (float) $row['principal_amount'],
                        'interest_due' => (float) $row['interest_amount'],
                        'fees_due' => 0,
                        'penalty_due' => 0,
                        'total_due' => (float) $row['total_due'],
                        'amount_paid' => 0,
                        'outstanding_amount' => (float) $row['total_due'],
                        'due_date' => $row['due_date'],
                        'paid_date' => null,
                        'status' => 'pending',
                        'is_active' => true,
                        'principal_account_id' => $principalAccountId,
                        'interest_income_account_id' => $interestIncomeAccountId,
                        'penalty_income_account_id' => $penaltyIncomeAccountId,
                        'fee_income_account_id' => $loan->fee_income_account_id,
                    ]
                );
            }
        });
    }

    private function resolveCalculator(string $interestMethodCode): InterestCalculatorInterface
    {
        $code = strtoupper(trim($interestMethodCode));

        if (!empty($this->calculators[$code])) {
            return $this->calculators[$code];
        }

        return match ($code) {
            'FLT' => new FlatInterest(),
            'RED' => new ReducingBalanceInterest(),
            'COMP' => new CompoundInterest(),
            default => throw new InvalidArgumentException("Unsupported interest method code: {$code}"),
        };
    }

    private function resolveInterestMethodCode(Loans $loan): string
    {
        $loan->loadMissing('loanProduct.interestMethod');

        $code = $loan->loanProduct?->interestMethod?->code;
        if (is_string($code) && $code !== '') {
            return $code;
        }

        // Fallback: if your Loans table ever stores the method code directly.
        if (property_exists($loan, 'interest_method_code') && is_string($loan->interest_method_code)) {
            return $loan->interest_method_code;
        }

        throw new InvalidArgumentException('Unable to resolve interest method code for this loan.');
    }

    private function resolveRepaymentFrequencyCode(Loans $loan): string
    {
        // Primary as per your system context.
        if (is_string($loan->repayment_frequency_code) && $loan->repayment_frequency_code !== '') {
            return $loan->repayment_frequency_code;
        }

        // Fallback to product repayment frequency code.
        $loan->loadMissing('loanProduct.repaymentFrequency');
        $code = $loan->loanProduct?->repaymentFrequency?->code;
        if (is_string($code) && $code !== '') {
            return $code;
        }

        throw new InvalidArgumentException('Unable to resolve repayment frequency code for this loan.');
    }
}
