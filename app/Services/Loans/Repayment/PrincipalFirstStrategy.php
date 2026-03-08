<?php

declare(strict_types=1);

namespace App\Services\Loans\Repayment;

class PrincipalFirstStrategy
{
    /**
     * @return array<int, string>
     */
    public function getPriorityOrder(): array
    {
        return ['principal', 'interest', 'fee', 'penalty'];
    }
}
