<?php

namespace App\Services\Loans;

use InvalidArgumentException;

class LifecycleEventMapper
{
    /**
     * Map newer lifecycle events to existing loan_product_fees.charge_event values.
     */
    public function mapToFeeConfigEvent(string $lifecycleEvent): ?string
    {
        $event = strtolower(trim($lifecycleEvent));

        return match ($event) {
            // Newer lifecycle events
            // loan_product_fees.charge_event enum does NOT contain "application".
            // We treat "loan_submitted" as the trigger for fees configured as "manual",
            // while recording the auditable application row as "application".
            'loan_submitted' => 'manual',
            'application' => 'manual',
            'loan_rejected' => null,
            'loan_approved' => 'approval',
            'loan_disbursed' => 'disbursement',

            // Existing engine / UI events
            'approval' => 'approval',
            'disbursement' => 'disbursement',
            'first_installment' => 'first_installment',
            'every_installment' => 'every_installment',
            'manual' => 'manual',

            default => throw new InvalidArgumentException("Unsupported lifecycle event: {$lifecycleEvent}"),
        };
    }

    /**
     * Map lifecycle events to loan_fee_applications.charge_event values.
     */
    public function mapToFeeApplicationEvent(string $lifecycleEvent): ?string
    {
        $event = strtolower(trim($lifecycleEvent));

        return match ($event) {
            'loan_submitted' => 'application',
            'application' => 'application',
            'loan_rejected' => null,
            'loan_approved', 'approval' => 'approval',
            'loan_disbursed', 'disbursement' => 'disbursement',
            'first_installment' => 'first_installment',
            'every_installment' => 'every_installment',
            'manual' => 'manual',
            default => 'manual',
        };
    }

    /**
     * Map penalty trigger to loan_penalty_applications.charge_event values.
     */
    public function mapToPenaltyApplicationEvent(string $triggerEvent): string
    {
        $event = strtolower(trim($triggerEvent));

        return match ($event) {
            'overdue_installment' => 'overdue_installment',
            'late_payment' => 'late_payment',
            'manual' => 'manual',
            default => 'overdue_installment',
        };
    }
}
