<?php

namespace App\Services\Loans\Payments\AllocationStrategies;

class PrincipalFirstStrategy
{
    /**
     * Allocation order within an installment.
     *
     * @return array<int, string>
     */
    public function getPriorityOrder(): array
    {
        return ['principal', 'interest', 'fee', 'penalty'];
    }
}
