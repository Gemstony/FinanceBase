<?php

namespace App\Services\Loans\Payments\AllocationStrategies;

class PenaltyFirstStrategy
{
    /**
     * Allocation order within an installment.
     *
     * @return array<int, string>
     */
    public function getPriorityOrder(): array
    {
        return ['penalty', 'fee', 'interest', 'principal'];
    }
}
