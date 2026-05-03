<?php

namespace App\Services\Loans\Penalties;

use App\Models\BankAccounts;
use App\Models\LoanInstallments;
use App\Models\LoanPenaltyApplications;
use App\Models\LoanPayments;
use App\Models\LoanPaymentAllocations;
use App\Models\Loans;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodAccount;
use App\Models\SubShop;
use App\Services\Accounting\JournalPostingEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PenaltyPaymentService
{
    public function __construct(
        private readonly JournalPostingEngine $journalPostingEngine,
    ) {
    }

    /**
     * Pay a specific penalty application.
     *
     * @param int $penaltyApplicationId
     * @param float $amount
     * @param string $paymentMethod
     * @param int|null $bankAccountId
     * @param string|null $referenceNumber
     * @param string|null $notes
     * @return LoanPayments
     */
    public function payPenalty(
        int $penaltyApplicationId,
        float $amount,
        string $paymentMethod,
        ?int $bankAccountId = null,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): LoanPayments {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $penaltyApplication = LoanPenaltyApplications::findOrFail($penaltyApplicationId);
        $loan = Loans::findOrFail($penaltyApplication->loan_id);

        $outstanding = $penaltyApplication->getOutstandingAmount();

        if ($outstanding <= 0) {
            throw new InvalidArgumentException('This penalty has already been fully settled.');
        }

        if ($amount > $outstanding) {
            throw new InvalidArgumentException(
                sprintf('Payment amount (%.2f) exceeds outstanding penalty (%.2f).', $amount, $outstanding)
            );
        }

        return DB::transaction(function () use (
            $penaltyApplication,
            $loan,
            $amount,
            $paymentMethod,
            $bankAccountId,
            $referenceNumber,
            $notes
        ) {
            // Resolve payment account ID (chart of accounts)
            $paymentAccountId = $this->resolvePaymentAccountId($paymentMethod, (int) $loan->subshop_id, $bankAccountId);

            // Create the payment record
            $payment = new LoanPayments();
            $payment->loan_id = $loan->id;
            $payment->subshop_id = $loan->subshop_id;
            $payment->customer_id = $loan->customer_id ?? $this->resolveCustomerId($loan);
            $payment->user_id = Auth::id();
            $payment->payment_date = Carbon::today()->toDateString();
            $payment->amount = $amount;
            $payment->payment_method = $paymentMethod;
            $payment->payment_account_id = $paymentAccountId;
            $payment->reference_number = $referenceNumber;
            $payment->notes = $notes ?? 'Penalty payment';
            $payment->status = 'confirmed';
            $payment->save();

            // Update penalty application
            $penaltyApplication->paid_amount = round((float) $penaltyApplication->paid_amount + $amount, 2);
            $penaltyApplication->updatePaymentStatus();

            // Create payment allocation
            LoanPaymentAllocations::create([
                'loan_payment_id' => $payment->id,
                'loan_installment_id' => $this->getTargetInstallment($loan)->id ?? null,
                'principal_amount' => 0,
                'interest_amount' => 0,
                'fee_amount' => 0,
                'penalty_amount' => $amount,
            ]);

            // Record journal entry
            $this->postPenaltyPaymentJournalEntry($loan, $penaltyApplication, $amount, $paymentAccountId);

            // Update installment penalty due if applicable
            $this->updateInstallmentPenaltyDue($loan);

            return $payment;
        });
    }

    /**
     * Forgive (waive) a penalty or part of it.
     *
     * @param int $penaltyApplicationId
     * @param float $amount
     * @param string $reason
     * @return LoanPenaltyApplications
     */
    public function forgivePenalty(
        int $penaltyApplicationId,
        float $amount,
        string $reason
    ): LoanPenaltyApplications {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Forgiveness amount must be greater than zero.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Forgiveness reason is required.');
        }

        $penaltyApplication = LoanPenaltyApplications::findOrFail($penaltyApplicationId);
        $loan = Loans::findOrFail($penaltyApplication->loan_id);

        $outstanding = $penaltyApplication->getOutstandingAmount();

        if ($outstanding <= 0) {
            throw new InvalidArgumentException('This penalty has already been fully settled.');
        }

        if ($amount > $outstanding) {
            throw new InvalidArgumentException(
                sprintf('Forgiveness amount (%.2f) exceeds outstanding penalty (%.2f).', $amount, $outstanding)
            );
        }

        return DB::transaction(function () use ($penaltyApplication, $loan, $amount, $reason) {
            $penaltyApplication->forgiven_amount = round((float) $penaltyApplication->forgiven_amount + $amount, 2);
            $penaltyApplication->forgiven_by = Auth::id();
            $penaltyApplication->forgiven_at = now();
            $penaltyApplication->forgiveness_reason = $reason;
            $penaltyApplication->updatePaymentStatus();

            // Record forgiveness journal entry (optional - reverses the penalty revenue)
            $this->postForgivenessJournalEntry($loan, $penaltyApplication, $amount, $reason);

            // Update installment penalty due
            $this->updateInstallmentPenaltyDue($loan);

            return $penaltyApplication;
        });
    }

    /**
     * Get all pending penalties for a loan.
     *
     * @param int $loanId
     * @return array
     */
    public function getPendingPenalties(int $loanId): array
    {
        $penalties = LoanPenaltyApplications::where('loan_id', $loanId)
            ->where(function ($query) {
                $query->where('is_paid', false)
                    ->orWhereRaw('(amount - paid_amount - forgiven_amount) > 0');
            })
            ->with('loanProductPenalty.loanPenalty')
            ->orderBy('applied_on', 'asc')
            ->get();

        $totalPending = 0;
        $items = [];

        foreach ($penalties as $penalty) {
            $outstanding = $penalty->getOutstandingAmount();
            if ($outstanding > 0) {
                $totalPending += $outstanding;
                $items[] = [
                    'id' => $penalty->id,
                    'applied_on' => $penalty->applied_on,
                    'amount' => (float) $penalty->amount,
                    'paid_amount' => (float) $penalty->paid_amount,
                    'forgiven_amount' => (float) $penalty->forgiven_amount,
                    'outstanding' => $outstanding,
                    'penalty_name' => $penalty->loanProductPenalty?->loanPenalty?->name ?? 'Penalty',
                    'status_label' => $penalty->getStatusLabel(),
                    'status_badge_class' => $penalty->getStatusBadgeClass(),
                ];
            }
        }

        return [
            'total_pending' => $totalPending,
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * Get penalty summary for a loan.
     *
     * @param int $loanId
     * @return array
     */
    public function getPenaltySummary(int $loanId): array
    {
        $allPenalties = LoanPenaltyApplications::where('loan_id', $loanId)->get();

        $totalCharged = 0;
        $totalPaid = 0;
        $totalForgiven = 0;
        $totalOutstanding = 0;

        foreach ($allPenalties as $penalty) {
            $totalCharged += (float) $penalty->amount;
            $totalPaid += (float) $penalty->paid_amount;
            $totalForgiven += (float) $penalty->forgiven_amount;
            $totalOutstanding += $penalty->getOutstandingAmount();
        }

        $hasPending = $totalOutstanding > 0;

        $statusLabel = $hasPending ? 'Pending' : ($totalCharged > 0 ? 'Paid' : 'None');
        $statusClass = match ($statusLabel) {
            'Paid' => 'bg-success',
            'Pending' => 'bg-warning',
            default => 'bg-secondary',
        };

        return [
            'total_charged' => $totalCharged,
            'total_paid' => $totalPaid,
            'total_forgiven' => $totalForgiven,
            'total_outstanding' => $totalOutstanding,
            'count' => $allPenalties->count(),
            'has_pending' => $hasPending,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
        ];
    }

    /**
     * Get the installment to associate with penalty payment.
     * Returns the most overdue installment or the first unpaid one.
     */
    private function getTargetInstallment(Loans $loan): ?LoanInstallments
    {
        return LoanInstallments::where('loan_id', $loan->id)
            ->where('is_active', true)
            ->where('outstanding_amount', '>', 0)
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date', 'asc')
            ->first();
    }

    /**
     * Update penalty due amounts on installments.
     */
    private function updateInstallmentPenaltyDue(Loans $loan): void
    {
        $installments = LoanInstallments::where('loan_id', $loan->id)
            ->where('is_active', true)
            ->get();

        foreach ($installments as $installment) {
            // Recalculate total penalty for this installment based on date
            $totalPenaltyForInstallment = LoanPenaltyApplications::where('loan_id', $loan->id)
                ->whereDate('applied_on', '<=', $installment->due_date)
                ->sum('amount');

            $totalPaidForInstallment = LoanPenaltyApplications::where('loan_id', $loan->id)
                ->whereDate('applied_on', '<=', $installment->due_date)
                ->sum('paid_amount');

            $totalForgivenForInstallment = LoanPenaltyApplications::where('loan_id', $loan->id)
                ->whereDate('applied_on', '<=', $installment->due_date)
                ->sum('forgiven_amount');

            $installment->penalty_due = round(
                max(0, $totalPenaltyForInstallment - $totalPaidForInstallment - $totalForgivenForInstallment),
                2
            );

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
    }

    /**
     * Record journal entry for penalty payment.
     */
    private function postPenaltyPaymentJournalEntry(
        Loans $loan,
        LoanPenaltyApplications $penaltyApplication,
        float $amount,
        int $paymentAccountId
    ): void {
        try {
            if (!$paymentAccountId) {
                // Skip journal entry if no payment account resolved
                return;
            }

            // Credit Penalty Revenue (reduce the penalty revenue liability)
            // Debit the payment method account (cash/bank)

            $this->journalPostingEngine->postJournalEntry(
                [
                    [
                        'account_id' => config('accounts.penalty_revenue_account_id'),
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                    [
                        'account_id' => $paymentAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                ],
                'loan_penalty_payment',
                $penaltyApplication->id
            );
        } catch (\Exception $e) {
            // Log but don't fail the payment
            \Log::warning('Failed to record penalty payment journal entry: ' . $e->getMessage());
        }
    }

    /**
     * Record journal entry for penalty forgiveness.
     */
    private function postForgivenessJournalEntry(
        Loans $loan,
        LoanPenaltyApplications $penaltyApplication,
        float $amount,
        string $reason
    ): void {
        try {
            // Credit Penalty Revenue (reduce revenue)
            // Debit Penalty Forgiveness Expense (or Bad Debt)

            $this->journalPostingEngine->postJournalEntry(
                [
                    [
                        'account_id' => config('accounts.penalty_revenue_account_id'),
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                    [
                        'account_id' => config('accounts.penalty_forgiveness_expense_account_id'),
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                ],
                'loan_penalty_forgiveness',
                $penaltyApplication->id
            );
        } catch (\Exception $e) {
            // Log but don't fail the forgiveness
            \Log::warning('Failed to record penalty forgiveness journal entry: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the payment account ID (chart of accounts) from payment method or bank account.
     */
    private function resolvePaymentAccountId(string $paymentMethod, int $subshopId, ?int $bankAccountId = null): int
    {
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        // Get all subshop IDs under this shop for validation
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        // If bank account provided, use its chart of account
        if ($bankAccountId) {
            $bank = BankAccounts::query()->whereKey($bankAccountId)->first();
            if (! $bank) {
                throw new InvalidArgumentException('Selected bank account not found.');
            }

            if (! in_array($bank->subshop_id, $shopSubshopIds->toArray())) {
                throw new InvalidArgumentException('Selected bank account does not belong to this branch.');
            }

            $accountId = (int) $bank->chart_of_account_id;
            if ($accountId <= 0) {
                throw new InvalidArgumentException('Bank account missing chart_of_account_id mapping.');
            }

            return $accountId;
        }

        // Look up payment method to GL account mapping (shop-level scope)
        $method = trim(strtolower($paymentMethod));
        if ($method === '') {
            throw new InvalidArgumentException('payment_method is required to resolve payment GL account.');
        }

        // First try shop-level mapping (new schema)
        $mapping = PaymentMethodAccount::query()
            ->where('shop_id', $shopId)
            ->where('payment_method', $method)
            ->first();

        // Fall back to legacy subshop-level lookup for backward compatibility
        if (! $mapping) {
            $mapping = PaymentMethodAccount::query()
                ->where('subshop_id', $subshopId)
                ->where('payment_method', $method)
                ->first();
        }

        if (! $mapping || ! $mapping->chart_of_account_id) {
            throw new InvalidArgumentException(
                "No GL account mapped for payment method '{$paymentMethod}'. Please configure in Payment Methods."
            );
        }

        return (int) $mapping->chart_of_account_id;
    }

    /**
     * Resolve customer ID from loan (handles both individual and group loans).
     */
    private function resolveCustomerId(Loans $loan): int
    {
        if ($loan->customer_id) {
            return (int) $loan->customer_id;
        }

        if ($loan->loan_group_id) {
            // For group loans, try to get the first group member's customer ID
            $firstMember = \App\Models\LoanGroupMembers::query()
                ->where('loan_group_id', $loan->loan_group_id)
                ->first();

            if ($firstMember && $firstMember->customer_id) {
                return (int) $firstMember->customer_id;
            }
        }

        throw new InvalidArgumentException('Cannot resolve customer_id for loan payment.');
    }
}
