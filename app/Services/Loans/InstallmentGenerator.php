<?php

namespace App\Services\Loans;

use Carbon\Carbon;
use InvalidArgumentException;

class InstallmentGenerator
{
    /**
     * @param array<int, Carbon> $dates
     * @param array<int, array{principal: float, interest: float}> $breakdown
     * @return array<int, array{installment_number:int,due_date:string,principal_amount:float,interest_amount:float,total_due:float,remaining_balance:float}>
     */
    public function generate(float $principal, array $dates, array $breakdown): array
    {
        if (count($dates) !== count($breakdown)) {
            throw new InvalidArgumentException('Dates count must match breakdown count.');
        }

        $rows = [];
        $balance = round($principal, 2);

        foreach ($breakdown as $idx => $item) {
            $principalDue = round((float) ($item['principal'] ?? 0.0), 2);
            $interestDue = round((float) ($item['interest'] ?? 0.0), 2);

            $balance = round(max(0, $balance - $principalDue), 2);
            $totalDue = round($principalDue + $interestDue, 2);

            $rows[] = [
                'installment_number' => $idx + 1,
                'due_date' => $dates[$idx]->toDateString(),
                'principal_amount' => $principalDue,
                'interest_amount' => $interestDue,
                'total_due' => $totalDue,
                'remaining_balance' => $balance,
            ];
        }

        return $rows;
    }
}
