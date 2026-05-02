<?php

namespace App\Services\Loans\Fees;

use App\Models\LoanInstallments;
use App\Models\LoanFeeApplications;
use App\Models\LoanProductFees;
use App\Models\LoanFees;
use App\Models\Loans;
use App\Services\Loans\LifecycleEventMapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FeeEngine
{
    public function __construct(
        private readonly FixedFee $fixedFee = new FixedFee(),
        private readonly PercentageFee $percentageFee = new PercentageFee(),
        private readonly LifecycleEventMapper $eventMapper = new LifecycleEventMapper(),
    ) {
    }

    /**
     * Apply fees configured for a given event.
     *
     * Supported events should match `loan_product_fees.charge_event` values where possible.
     *
     * Idempotency: this method recalculates and sets `fees_due` for the targeted installments.
     */
    public function applyFees(Loans $loan, string $lifecycleEvent, ?Carbon $asOfDate = null): void
    {
        $asOf = ($asOfDate ?? now())->startOfDay();

        $configEvent = $this->eventMapper->mapToFeeConfigEvent($lifecycleEvent);
        $applicationEvent = $this->eventMapper->mapToFeeApplicationEvent($lifecycleEvent);

        // Events like loan_rejected map to null => no fee should be applied.
        if ($configEvent === null || $applicationEvent === null) {
            return;
        }

        $loan->loadMissing('loanProduct.fees.loanFee');

        $rules = $loan->loanProduct?->fees
            ?->where('is_active', true)
            ->where('auto_apply', true)
            ->where('charge_event', $configEvent);

        if (!$rules || $rules->isEmpty()) {
            return;
        }

        $targets = $this->resolveTargetInstallments($loan, $rules);
        if ($targets->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($loan, $targets, $rules, $asOf, $applicationEvent) {
            foreach ($targets as $installment) {
                $appliedOn = $this->resolveAppliedOn($asOf, $installment, (string) optional($rules->first())->charge_event);

                // Create auditable application rows (idempotent via unique constraint).
                foreach ($rules as $rule) {
                    /** @var LoanProductFees $rule */
                    $fee = $rule->loanFee;
                    if (!$fee instanceof LoanFees || !$fee->is_active) {
                        continue;
                    }

                    $baseAmount = $this->resolveBaseAmount($loan, $installment, $fee, $rule->charge_event);
                    $amount = round($this->calculateFeeForRule($baseAmount, $fee), 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    LoanFeeApplications::firstOrCreate(
                        [
                            'loan_id' => $loan->id,
                            'loan_product_fee_id' => $rule->id,
                            'applied_on' => $appliedOn->toDateString(),
                        ],
                        [
                            'amount' => $amount,
                            'charge_event' => $applicationEvent,
                            'is_paid' => false,
                        ]
                    );
                }

                // Note: Fees are now tracked independently in loan_fee_applications table
                // and are NOT included in installment amounts (like security deposits).
                // This allows fees to be paid separately from regular installments.

                // Optional accounting hook - record total fees for this application date
                $totalFees = (float) LoanFeeApplications::query()
                    ->where('loan_id', $loan->id)
                    ->whereIn('loan_product_fee_id', $rules->pluck('id')->all())
                    ->whereDate('applied_on', $appliedOn->toDateString())
                    ->sum('amount');

                if ($totalFees > 0) {
                    $this->recordToAccounting($loan, $installment->id, $totalFees, $asOf);
                }
            }
        });
    }

    /**
     * Apply all auto-applied fees configured on the loan product.
     *
     * This is intended for loan creation flows where we want fees captured immediately
     * after installments are generated, without requiring lifecycle-specific triggers.
     *
     * Note: Fees are tracked independently in loan_fee_applications table and are NOT
     * included in installment amounts (like security deposits).
     */
    public function applyAllFees(Loans $loan, ?Carbon $asOfDate = null): void
    {
        $asOf = ($asOfDate ?? now())->startOfDay();

        $loan->loadMissing('loanProduct.fees.loanFee');

        $rules = $loan->loanProduct?->fees
            ?->where('is_active', true)
            ;

        if (!$rules || $rules->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($loan, $rules, $asOf) {
            $firstInstallment = LoanInstallments::query()
                ->where('loan_id', $loan->id)
                ->where('is_active', true)
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->first();

            if (!$firstInstallment) {
                return;
            }

            $totalFees = 0.0;

            foreach ($rules as $rule) {
                /** @var LoanProductFees $rule */
                $fee = $rule->loanFee;
                if (!$fee instanceof LoanFees || !$fee->is_active) {
                    continue;
                }

                $appliedOn = $asOf;

                $baseAmount = $this->resolveBaseAmount($loan, $firstInstallment, $fee, 'application');
                $amount = round($this->calculateFeeForRule($baseAmount, $fee), 2);
                if ($amount <= 0) {
                    continue;
                }

                LoanFeeApplications::firstOrCreate(
                    [
                        'loan_id' => $loan->id,
                        'loan_product_fee_id' => $rule->id,
                        'applied_on' => $appliedOn->toDateString(),
                    ],
                    [
                        'amount' => $amount,
                        'charge_event' => 'application',
                        'is_paid' => false,
                    ]
                );

                $totalFees += $amount;
            }

            // Note: Fees are tracked independently and are NOT included in installment totals.
            // Installment amounts remain as principal + interest only.
            // Fees must be paid separately through a fee payment flow (like security deposits).

            if ($totalFees > 0) {
                $this->recordToAccounting($loan, (int) $firstInstallment->id, $totalFees, $asOf);
            }
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoanInstallments>
     */
    private function resolveTargetInstallments(Loans $loan, $rules)
    {
        // Determine target installments based on configured charge_event.
        // Since rules are filtered by a single event, we can read the first.
        $event = (string) optional($rules->first())->charge_event;

        $query = LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true);

        return match ($event) {
            'every_installment' => $query->orderBy('installment_number')->get(),
            'first_installment', 'approval', 'disbursement' => $query->orderBy('installment_number')->limit(1)->get(),
            'manual' => collect(),
            default => $query->orderBy('installment_number')->limit(1)->get(),
        };
    }

    private function calculateFeeForRule(float $baseAmount, LoanFees $fee): float
    {
        $type = strtoupper((string) $fee->fee_type);

        return match ($type) {
            'FIXED' => $this->fixedFee->calculate($baseAmount, (float) ($fee->amount ?? 0)),
            'PERCENTAGE' => $this->percentageFee->calculate($baseAmount, (float) ($fee->percentage ?? 0)),
            default => throw new InvalidArgumentException("Unsupported fee_type: {$type}"),
        };
    }

    private function resolveBaseAmount(Loans $loan, LoanInstallments $installment, LoanFees $fee, string $event): float
    {
        // Default base for most fee events is the loan principal.
        $applyOn = strtoupper((string) $fee->apply_on);

        if ($applyOn === 'REPAYMENT' || $event === 'installment_payment') {
            // For repayment-based fees, use the installment amount due.
            return (float) $installment->principal_due + (float) $installment->interest_due;
        }

        if ($applyOn === 'TOP_UP' || $event === 'loan_topup') {
            // Top-up scenarios typically base on principal as well.
            return (float) $loan->principal_amount;
        }

        return (float) $loan->principal_amount;
    }

    private function resolveAppliedOn(Carbon $asOf, LoanInstallments $installment, string $chargeEvent): Carbon
    {
        // For installment-based fee events, store the application against the installment due date.
        // This allows multiple applications across installments without clashing on (loan, fee, applied_on).
        return match ($chargeEvent) {
            'every_installment', 'first_installment' => Carbon::parse($installment->due_date)->startOfDay(),
            default => $asOf,
        };
    }

    /**
     * Optional extension: integrate with accounting/journals if available.
     * Currently a no-op.
     */
    protected function recordToAccounting(Loans $loan, int $installmentId, float $feeAmount, Carbon $asOf): void
    {
        // Intentionally left blank (hook for future journal_entries / loan_transactions integration).
    }
}
