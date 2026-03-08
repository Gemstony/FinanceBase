<?php

declare(strict_types=1);

namespace App\Services\Loans\Repayment;

class PenaltyFirstStrategy
{
    /**
     * @return array<int, string>
     */
    public function getPriorityOrder(): array
    {
        return ['penalty', 'interest', 'fee', 'principal'];
    }
}
