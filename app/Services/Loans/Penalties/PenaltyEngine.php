<?php

namespace App\Services\Loans\Penalties;

use App\Models\LoanInstallments;
use App\Models\LoanPenaltyApplications;
use App\Models\LoanProductPenalties;
use App\Models\LoanPenalties;
use App\Models\Loans;
use App\Services\Loans\LifecycleEventMapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PenaltyEngine
{
    public function __construct(
        private readonly LatePaymentPenaltyCalculator $latePaymentCalculator = new LatePaymentPenaltyCalculator(),
        private readonly OneTimePenaltyCalculator $oneTimeCalculator = new OneTimePenaltyCalculator(),
        private readonly LifecycleEventMapper $eventMapper = new LifecycleEventMapper(),
    ) {
    }

    /**
     * Apply penalties to overdue installments.
     *
     * This method is designed to be safe to run repeatedly by recalculating
     * the penalty amount based on the current date and setting `penalty_due`.
     */
    public function applyPenalties(Loans $loan, ?Carbon $asOfDate = null, string $triggerEvent = 'overdue_installment'): void
    {
        $asOf = ($asOfDate ?? now())->startOfDay();
        $applicationEvent = $this->eventMapper->mapToPenaltyApplicationEvent($triggerEvent);

        $loan->loadMissing('loanProduct.penalties.loanPenalty');

        $penaltyRules = $loan->loanProduct?->penalties
            ?->where('is_active', true)
            ->where('auto_apply', true);

        if (!$penaltyRules || $penaltyRules->isEmpty()) {
            return;
        }

        $installments = LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->whereDate('due_date', '<', $asOf->toDateString())
            ->where('outstanding_amount', '>', 0)
            ->get();

        if ($installments->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($loan, $installments, $penaltyRules, $asOf, $applicationEvent) {
            // 1) Record auditable applications (loan-level) once per day per rule.
            foreach ($penaltyRules as $rule) {
                /** @var LoanProductPenalties $rule */
                $penalty = $rule->loanPenalty;
                if (!$penalty instanceof LoanPenalties || !$penalty->is_active) {
                    continue;
                }

                $totalPenaltyAsOf = 0.0;
                foreach ($installments as $installment) {
                    $totalPenaltyAsOf += $this->calculatePenaltyForRule(
                        (float) $installment->outstanding_amount,
                        Carbon::parse($installment->due_date)->startOfDay(),
                        $asOf,
                        $rule,
                        $penalty
                    );
                }

                $this->recordPenaltyApplicationDelta(
                    $loan,
                    $rule,
                    $applicationEvent,
                    $asOf,
                    (float) $totalPenaltyAsOf
                );
            }

            // 2) Set per-installment penalty_due deterministically.
            foreach ($installments as $installment) {
                $computedPenalty = 0.0;

                foreach ($penaltyRules as $rule) {
                    /** @var LoanProductPenalties $rule */
                    $penalty = $rule->loanPenalty;
                    if (!$penalty instanceof LoanPenalties || !$penalty->is_active) {
                        continue;
                    }

                    $computedPenalty += $this->calculatePenaltyForRule(
                        (float) $installment->outstanding_amount,
                        Carbon::parse($installment->due_date)->startOfDay(),
                        $asOf,
                        $rule,
                        $penalty
                    );
                }

                $installment->penalty_due = round($computedPenalty, 2);

                $installment->total_due = round(
                    (float) $installment->principal_due +
                    (float) $installment->interest_due +
                    (float) $installment->fees_due +
                    (float) $installment->penalty_due,
                    2
                );

                $installment->outstanding_amount = round(
                    max(0, (float) $installment->total_due - (float) $installment->amount_paid),
                    2
                );

                $installment->save();
            }
        });
    }

    private function recordPenaltyApplicationDelta(
        Loans $loan,
        LoanProductPenalties $rule,
        string $applicationEvent,
        Carbon $asOf,
        float $totalPenaltyAsOf
    ): void {
        // Store *delta* for the day so recurring penalties can be accumulated over time.
        // Unique constraint: (loan_id, loan_product_penalty_id, applied_on)
        $appliedOn = $asOf->toDateString();

        $existsToday = LoanPenaltyApplications::query()
            ->where('loan_id', $loan->id)
            ->where('loan_product_penalty_id', $rule->id)
            ->whereDate('applied_on', $appliedOn)
            ->exists();

        if ($existsToday) {
            return;
        }

        $totalBeforeToday = (float) LoanPenaltyApplications::query()
            ->where('loan_id', $loan->id)
            ->where('loan_product_penalty_id', $rule->id)
            ->whereDate('applied_on', '<', $appliedOn)
            ->sum('amount');

        $amount = round(max(0, $totalPenaltyAsOf - $totalBeforeToday), 2);
        if ($amount <= 0) {
            return;
        }

        LoanPenaltyApplications::create([
            'loan_id' => $loan->id,
            'loan_product_penalty_id' => $rule->id,
            'amount' => $amount,
            'charge_event' => $applicationEvent,
            'applied_on' => $appliedOn,
            'is_paid' => false,
        ]);
    }

    private function calculatePenaltyForRule(
        float $overdueAmount,
        Carbon $dueDate,
        Carbon $asOf,
        LoanProductPenalties $rule,
        LoanPenalties $penalty
    ): float {
        $graceDays = $rule->grace_days_override ?? $penalty->grace_period_days ?? 0;
        $overdueDays = $dueDate->diffInDays($asOf);
        $daysAfterGrace = $overdueDays - (int) $graceDays;

        if ($daysAfterGrace <= 0) {
            return 0.0;
        }

        // Compute a single-application penalty value.
        $perApplication = $this->computePerApplicationPenaltyValue($overdueAmount, $penalty);

        $frequency = strtolower((string) $penalty->frequency);

        // Compute occurrences.
        $occurrences = match ($frequency) {
            'daily' => $daysAfterGrace,
            'weekly' => (int) floor($daysAfterGrace / 7) ?: 1,
            'monthly' => (int) floor($daysAfterGrace / 30) ?: 1,
            default => 1,
        };

        // Enforce max applications if configured.
        if (!is_null($rule->max_applications)) {
            $occurrences = min($occurrences, (int) $rule->max_applications);
        }

        if ($occurrences < 1) {
            return 0.0;
        }

        $calculator = $this->resolveCalculator($frequency, (int) $graceDays, $occurrences);

        return $calculator->calculate($overdueAmount, $perApplication);
    }

    private function computePerApplicationPenaltyValue(float $overdueAmount, LoanPenalties $penalty): float
    {
        $type = strtoupper((string) $penalty->penalty_type);

        return match ($type) {
            // Fixed penalty amount
            'FIXED' => (float) ($penalty->amount ?? 0),

            // Percentage of overdue amount per application
            'DAILY_PERCENTAGE' => $overdueAmount * ((float) ($penalty->percentage ?? 0) / 100),

            default => throw new InvalidArgumentException("Unsupported penalty_type: {$type}"),
        };
    }

    private function resolveCalculator(string $frequency, int $graceDays, int $occurrences): PenaltyCalculatorInterface
    {
        return match ($frequency) {
            'daily', 'weekly', 'monthly' => new RecurringPenaltyCalculator($occurrences),

            // Applied once after grace period.
            'once', 'per_installment' => $graceDays > 0
                ? $this->oneTimeCalculator
                : $this->latePaymentCalculator,

            default => $this->oneTimeCalculator,
        };
    }
}
