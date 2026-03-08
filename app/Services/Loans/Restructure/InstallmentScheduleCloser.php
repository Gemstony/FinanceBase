<?php

declare(strict_types=1);

namespace App\Services\Loans\Restructure;

use App\Models\LoanInstallments;
use App\Models\Loans;

class InstallmentScheduleCloser
{
    /**
     * Close the latest installment schedule.
     *
     * IMPORTANT FINANCIAL RULE:
     * - We never delete historical installments.
     * - We mark the remaining installments as restructured so historical schedule is preserved.
     */
    public function closeLatestSchedule(Loans $loan, int $latestVersion): int
    {
        LoanInstallments::query()
            ->where('loan_id', (int) $loan->id)
            ->where('schedule_version', $latestVersion)
            ->where('is_active', true)
            ->where('status', '!=', 'paid')
            ->update([
                'status' => 'restructured',
                'is_active' => false,
            ]);

        // Also deactivate any already-paid rows in that version to avoid confusion in UI queries that only check is_active.
        LoanInstallments::query()
            ->where('loan_id', (int) $loan->id)
            ->where('schedule_version', $latestVersion)
            ->where('is_active', true)
            ->where('status', 'paid')
            ->update([
                'is_active' => false,
            ]);

        return $latestVersion + 1;
    }
}
