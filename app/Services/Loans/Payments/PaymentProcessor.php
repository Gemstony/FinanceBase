<?php

namespace App\Services\Loans\Payments;

use App\Models\LoanInstallments;
use App\Models\LoanPaymentAllocations;
use App\Models\LoanPayments;
use App\Models\Loans;
use App\Services\Loans\Fees\FeeEngine;
use App\Services\Loans\Penalties\PenaltyEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentProcessor
{
    public function __construct(
        private readonly PaymentAllocator $allocator = new PaymentAllocator(),
        private readonly ?FeeEngine $feeEngine = null,
        private readonly ?PenaltyEngine $penaltyEngine = null,
    ) {
    }

    /**
     * Record a borrower payment and allocate it across unpaid installments.
     *
     * Allocation is stored in loan_payment_allocations as one row per installment,
     * with per-component amounts.
     *
     * @param object $strategy Any object with method getPriorityOrder(): array
     *
     * @return array{payment:LoanPayments, remaining_amount:float, allocations:array<int, array{installment_id:int, principal:float, interest:float, fee:float, penalty:float, total:float}>}
     */
    public function process(
        Loans $loan,
        int $clientId,
        float $paymentAmount,
        string $paymentDate,
        object $strategy,
        ?int $userId = null,
        ?string $paymentMethod = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
    ): array {
        if ($paymentAmount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than 0.');
        }

        if (!method_exists($strategy, 'getPriorityOrder')) {
            throw new InvalidArgumentException('Invalid allocation strategy provided.');
        }

        if (in_array((string) $loan->status, ['paid_off', 'written_off'], true)) {
            throw new InvalidArgumentException('This loan is closed and cannot accept payments.');
        }

        $asOf = Carbon::parse($paymentDate)->startOfDay();

        return DB::transaction(function () use (
            $loan,
            $clientId,
            $paymentAmount,
            $asOf,
            $strategy,
            $userId,
            $paymentMethod,
            $referenceNumber,
            $notes
        ) {
            if ($this->feeEngine) {
                $this->feeEngine->applyFees($loan, 'installment_payment', $asOf);
            }

            if ($this->penaltyEngine) {
                $this->penaltyEngine->applyPenalties($loan, $asOf, 'overdue_installment');
            }

            $installments = LoanInstallments::query()
                ->where('loan_id', $loan->id)
                ->where('is_active', true)
                ->where('outstanding_amount', '>', 0)
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            if ($installments->isEmpty()) {
                throw new InvalidArgumentException('This loan has no unpaid installments.');
            }

            $payment = LoanPayments::create([
                'loan_id' => $loan->id,
                'client_id' => $clientId,
                'user_id' => $userId,
                'amount' => $paymentAmount,
                'payment_date' => $asOf->toDateString(),
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'status' => 'confirmed',
            ]);

            $alreadyAllocated = LoanPaymentAllocations::query()
                ->selectRaw('loan_installment_id, SUM(principal_amount) as principal, SUM(interest_amount) as interest, SUM(fee_amount) as fee, SUM(penalty_amount) as penalty')
                ->whereIn('loan_installment_id', $installments->pluck('id')->all())
                ->groupBy('loan_installment_id')
                ->get()
                ->keyBy('loan_installment_id');

            $items = [];
            foreach ($installments as $ins) {
                $alloc = $alreadyAllocated->get($ins->id);

                $paidPrincipal = (float) ($alloc?->principal ?? 0);
                $paidInterest = (float) ($alloc?->interest ?? 0);
                $paidFee = (float) ($alloc?->fee ?? 0);
                $paidPenalty = (float) ($alloc?->penalty ?? 0);

                $items[] = [
                    'installment' => $ins,
                    'remaining' => [
                        'principal' => max(0.0, (float) $ins->principal_due - $paidPrincipal),
                        'interest' => max(0.0, (float) $ins->interest_due - $paidInterest),
                        'fee' => max(0.0, (float) $ins->fees_due - $paidFee),
                        'penalty' => max(0.0, (float) $ins->penalty_due - $paidPenalty),
                    ],
                ];
            }

            $priorityOrder = (array) $strategy->getPriorityOrder();
            $allocationResult = $this->allocator->allocate($items, $paymentAmount, $priorityOrder);

            $allocationRows = $allocationResult['allocations'];

            $summary = [];
            foreach ($allocationRows as $row) {
                /** @var LoanInstallments $ins */
                $ins = $row['installment'];

                $principal = (float) $row['principal'];
                $interest = (float) $row['interest'];
                $fee = (float) $row['fee'];
                $penalty = (float) $row['penalty'];
                $total = (float) $row['total'];

                LoanPaymentAllocations::create([
                    'loan_payment_id' => $payment->id,
                    'loan_installment_id' => $ins->id,
                    'principal_amount' => $principal,
                    'interest_amount' => $interest,
                    'fee_amount' => $fee,
                    'penalty_amount' => $penalty,
                ]);

                $ins->amount_paid = round(((float) $ins->amount_paid) + $total, 2);
                $ins->outstanding_amount = round(max(0.0, (float) $ins->total_due - (float) $ins->amount_paid), 2);

                if ($ins->outstanding_amount <= 0) {
                    $ins->status = 'paid';
                    $ins->paid_date = $asOf->toDateString();
                } elseif ((float) $ins->amount_paid > 0) {
                    $ins->status = 'partial';
                }

                $ins->save();

                $summary[] = [
                    'installment_id' => (int) $ins->id,
                    'principal' => $principal,
                    'interest' => $interest,
                    'fee' => $fee,
                    'penalty' => $penalty,
                    'total' => $total,
                ];
            }

            $remaining = (float) $allocationResult['remaining_amount'];

            $stillOutstanding = LoanInstallments::query()
                ->where('loan_id', $loan->id)
                ->where('is_active', true)
                ->where('outstanding_amount', '>', 0)
                ->exists();

            if (!$stillOutstanding) {
                $loan->status = 'paid_off';
                $loan->save();
            }

            return [
                'payment' => $payment,
                'remaining_amount' => $remaining,
                'allocations' => $summary,
            ];
        });
    }
}
