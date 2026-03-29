<?php

namespace App\Services\Loans\Penalties;

use App\Models\LoanInstallments;
use App\Models\LoanPenaltyApplications;
use App\Models\LoanProductPenalties;
use App\Models\SubShop;
use App\Services\Sms\SmsManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenaltyAccrualEngine
{
    public function __construct(
        protected PenaltyCalculator $penaltyCalculator
    ) {
    }

    /**
     * Process daily penalty accrual.
     *
     * Banking rule implemented:
     * Apply penalty when:
     * - installment.status = overdue
     * - and days_overdue > grace_days
     *
     * This engine is designed to be safe for a daily scheduler:
     * - it prevents duplicates by checking if an application already exists for today.
     * - it records an auditable row in loan_penalty_applications.
     * - it increments installment.penalty_due and recomputes total_due/outstanding_amount.
     */
    public function processDailyPenalties(?Carbon $asOfDate = null): void
    {
        $today = ($asOfDate ?? Carbon::today())->startOfDay();

        $installments = LoanInstallments::query()
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $today->toDateString())
            ->where('outstanding_amount', '>', 0)
            ->with(['loan.loanProduct.penalties.loanPenalty'])
            ->get();

        if ($installments->isEmpty()) {
            return;
        }

        foreach ($installments as $installment) {
            DB::transaction(function () use ($installment, $today) {
                $lockedInstallment = LoanInstallments::query()
                    ->where('id', $installment->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedInstallment) {
                    return;
                }

                $loan = $lockedInstallment->loan;
                if (!$loan || !$loan->is_active) {
                    return;
                }

                $loan->loadMissing('loanProduct.penalties.loanPenalty');

                $rules = $loan->loanProduct?->penalties
                    ?->where('is_active', true)
                    ->where('auto_apply', true);

                if (!$rules || $rules->isEmpty()) {
                    return;
                }

                foreach ($rules as $rule) {
                    /** @var LoanProductPenalties $rule */
                    $penaltyConfig = $rule->loanPenalty;
                    if (!$penaltyConfig || !$penaltyConfig->is_active) {
                        continue;
                    }

                    $graceDays = (int) ($rule->grace_days_override ?? $penaltyConfig->grace_period_days ?? 0);
                    $daysOverdue = $this->penaltyCalculator->calculateDaysOverdue($lockedInstallment, $today);

                    // Only start penalty after grace period.
                    if ($daysOverdue <= $graceDays) {
                        continue;
                    }

                    // Enforce max applications per loan/product-penalty rule if configured.
                    if (!is_null($rule->max_applications)) {
                        $appliedCount = LoanPenaltyApplications::query()
                            ->where('loan_id', $loan->id)
                            ->where('loan_product_penalty_id', $rule->id)
                            ->count();

                        if ($appliedCount >= (int) $rule->max_applications) {
                            continue;
                        }
                    }

                    if ($this->penaltyAlreadyAppliedToday($loan->id, $rule->id, $today)) {
                        continue;
                    }

                    // Supported penalty type for this accrual engine:
                    // - DAILY_PERCENTAGE: percentage is interpreted as per-day percent (e.g. 0.1% per day).
                    // - FIXED: amount applied per day.
                    $penaltyAmount = 0.0;
                    $type = strtoupper((string) ($penaltyConfig->penalty_type ?? ''));

                    if ($type === 'DAILY_PERCENTAGE') {
                        $ratePerDay = ((float) ($penaltyConfig->percentage ?? 0)) / 100;
                        $penaltyAmount = $this->penaltyCalculator->calculatePenalty($lockedInstallment, $ratePerDay);
                    } elseif ($type === 'FIXED') {
                        $penaltyAmount = round(max(0.0, (float) ($penaltyConfig->amount ?? 0)), 2);
                    }

                    if ($penaltyAmount <= 0) {
                        continue;
                    }

                    // 1) Record auditable penalty application (loan-level, per rule, per day).
                    LoanPenaltyApplications::create([
                        'loan_id' => $loan->id,
                        'loan_product_penalty_id' => $rule->id,
                        'amount' => $penaltyAmount,
                        'charge_event' => 'overdue_installment',
                        'applied_on' => $today->toDateString(),
                        'is_paid' => false,
                    ]);

                    // 2) Increment installment penalty_due and recompute totals.
                    $lockedInstallment->penalty_due = round((float) $lockedInstallment->penalty_due + $penaltyAmount, 2);

                    $lockedInstallment->total_due = round(
                        (float) $lockedInstallment->principal_due +
                        (float) $lockedInstallment->interest_due +
                        (float) $lockedInstallment->fees_due +
                        (float) $lockedInstallment->penalty_due,
                        2
                    );

                    $lockedInstallment->outstanding_amount = round(
                        max(0.0, (float) $lockedInstallment->total_due - (float) $lockedInstallment->amount_paid),
                        2
                    );

                    $lockedInstallment->save();

                    // Send SMS notification for penalty application
                    try {
                        $customer = $loan->customer;
                        if ($customer && $customer->phone) {
                            $shopId = SubShop::where('id', $loan->subshop_id)->value('shop_id');
                            app(SmsManager::class)->sendEvent('loan.penalty', [
                                'shop_id' => $shopId,
                                'subshop_id' => $loan->subshop_id,
                                'user_id' => Auth::id(),
                                'phone' => $customer->phone,
                                'data' => [
                                    'name' => $customer->name,
                                    'amount' => $penaltyAmount,
                                    'date' => $today->format('Y-m-d H:i'),
                                    'loan_code' => $loan->loan_code ?? 'N/A',
                                    'installment_number' => $lockedInstallment->installment_number
                                ]
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Don't let SMS failure affect the penalty processing
                        Log::warning('Failed to send loan penalty SMS: ' . $e->getMessage());
                    }
                }
            }, 3);
        }
    }

    /**
     * Prevent duplicate penalties by checking the unique (loan_id, loan_product_penalty_id, applied_on).
     */
    public function penaltyAlreadyAppliedToday(int $loanId, int $loanProductPenaltyId, Carbon $today): bool
    {
        return LoanPenaltyApplications::query()
            ->where('loan_id', $loanId)
            ->where('loan_product_penalty_id', $loanProductPenaltyId)
            ->whereDate('applied_on', $today->toDateString())
            ->exists();
    }
}
