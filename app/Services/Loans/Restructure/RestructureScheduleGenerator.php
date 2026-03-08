<?php

declare(strict_types=1);

namespace App\Services\Loans\Restructure;

use App\Models\Loans;
use App\Services\Loans\LoanScheduleEngine;
use Carbon\Carbon;
use InvalidArgumentException;

class RestructureScheduleGenerator
{
    public function __construct(private readonly LoanScheduleEngine $scheduleEngine)
    {
    }

    /**
     * Generate a new schedule for the restructured principal.
     *
     * @return array<int, array{installment_number:int,due_date:string,principal_amount:float,interest_amount:float,total_due:float,remaining_balance:float}>
     */
    public function generate(Loans $loan, float $newPrincipal, float $newInterestRate, int $newTerm, Carbon $anchorDate): array
    {
        if ($newPrincipal <= 0) {
            throw new InvalidArgumentException('New principal must be greater than 0.');
        }
        if ($newTerm < 1) {
            throw new InvalidArgumentException('New term must be at least 1.');
        }

        $virtual = $loan->replicate();
        $virtual->principal_amount = $newPrincipal;
        $virtual->interest_rate = $newInterestRate;
        $virtual->installments = $newTerm;
        $virtual->disbursement_date = $anchorDate->toDateString();

        return $this->scheduleEngine->generate($virtual);
    }
}
