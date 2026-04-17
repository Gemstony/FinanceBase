<?php

declare(strict_types=1);

namespace App\Repositories\Loans;

use App\Models\LoanInstallments;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Installment Repository
 *
 * Consolidates installment queries for consistent data access patterns.
 */
class InstallmentRepository
{
    /**
     * Get active installments query.
     */
    public function activeInstallmentsQuery(): Builder
    {
        return LoanInstallments::query()
            ->where('is_active', true);
    }

    /**
     * Get overdue installments.
     */
    public function getOverdue(int $minDays = 1, ?int $loanId = null): Collection
    {
        $cutoffDate = Carbon::today()->subDays($minDays);

        $query = $this->activeInstallmentsQuery()
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoffDate);

        if ($loanId) {
            $query->where('loan_id', $loanId);
        }

        return $query->orderBy('due_date')->get();
    }

    /**
     * Get overdue installment IDs grouped by loan.
     *
     * @return array<int, array<int>> Array of [loan_id => [installment_ids]]
     */
    public function getOverdueByLoan(int $minDays = 1): array
    {
        $cutoffDate = Carbon::today()->subDays($minDays);

        return $this->activeInstallmentsQuery()
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoffDate)
            ->select('loan_id', 'id')
            ->get()
            ->groupBy('loan_id')
            ->map(fn($group) => $group->pluck('id')->toArray())
            ->toArray();
    }

    /**
     * Get loan IDs with overdue installments.
     */
    public function getLoanIdsWithOverdue(int $minDays = 1, ?int $subshopId = null): array
    {
        $cutoffDate = Carbon::today()->subDays($minDays);

        $query = DB::table('loan_installments as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->where('li.is_active', true)
            ->where('li.status', 'overdue')
            ->whereDate('li.due_date', '<', $cutoffDate)
            ->where('l.is_active', true)
            ->whereIn('l.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('l.is_written_off', false);

        if ($subshopId) {
            $query->where('l.subshop_id', $subshopId);
        }

        return $query->distinct()->pluck('li.loan_id')->toArray();
    }

    /**
     * Get installments for a loan.
     */
    public function getForLoan(int $loanId, bool $activeOnly = true): Collection
    {
        $query = LoanInstallments::where('loan_id', $loanId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('installment_number')->get();
    }

    /**
     * Get latest schedule version for a loan.
     */
    public function getLatestScheduleVersion(int $loanId): int
    {
        return (int) $this->activeInstallmentsQuery()
            ->where('loan_id', $loanId)
            ->max('schedule_version');
    }

    /**
     * Get installments by schedule version.
     */
    public function getByScheduleVersion(int $loanId, int $version): Collection
    {
        return $this->activeInstallmentsQuery()
            ->where('loan_id', $loanId)
            ->where('schedule_version', $version)
            ->get();
    }

    /**
     * Get expected amounts for a loan (principal, interest, fees).
     */
    public function getExpectedAmounts(int $loanId, ?int $scheduleVersion = null): array
    {
        $version = $scheduleVersion ?? $this->getLatestScheduleVersion($loanId);

        $result = DB::table('loan_installments')
            ->where('loan_id', $loanId)
            ->where('is_active', true)
            ->where('schedule_version', $version)
            ->selectRaw('SUM(COALESCE(principal_due, 0)) as principal_expected')
            ->selectRaw('SUM(COALESCE(interest_due, 0)) as interest_expected')
            ->selectRaw('SUM(COALESCE(fees_due, 0)) as fees_expected')
            ->first();

        return [
            'principal' => (float) ($result?->principal_expected ?? 0),
            'interest' => (float) ($result?->interest_expected ?? 0),
            'fees' => (float) ($result?->fees_expected ?? 0),
        ];
    }

    /**
     * Get paid amounts for a loan.
     */
    public function getPaidAmounts(int $loanId): array
    {
        $result = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->where('li.loan_id', $loanId)
            ->where('lp.status', 'confirmed')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount, 0)) as principal_paid')
            ->selectRaw('SUM(COALESCE(lpa.interest_amount, 0)) as interest_paid')
            ->selectRaw('SUM(COALESCE(lpa.fee_amount, 0)) as fees_paid')
            ->first();

        return [
            'principal' => (float) ($result?->principal_paid ?? 0),
            'interest' => (float) ($result?->interest_paid ?? 0),
            'fees' => (float) ($result?->fees_paid ?? 0),
        ];
    }

    /**
     * Get max days overdue for loans (bulk).
     *
     * @param array<int> $loanIds
     * @param Carbon|null $asOfDate
     * @return array<int, int> Array of [loan_id => max_dpd]
     */
    public function getMaxDaysOverdueForLoans(array $loanIds, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::today();

        return DB::table('loan_installments')
            ->whereIn('loan_id', $loanIds)
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $asOfDate)
            ->select('loan_id')
            ->selectRaw('MAX(DATEDIFF(?, due_date)) as max_dpd', [$asOfDate->toDateString()])
            ->groupBy('loan_id')
            ->pluck('max_dpd', 'loan_id')
            ->map(fn($dpd) => (int) $dpd)
            ->toArray();
    }

    /**
     * Get upcoming installments (due within days).
     */
    public function getUpcoming(int $days = 7, ?int $loanId = null): Collection
    {
        $from = Carbon::today();
        $to = Carbon::today()->addDays($days);

        $query = $this->activeInstallmentsQuery()
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereDate('due_date', '>=', $from)
            ->whereDate('due_date', '<=', $to);

        if ($loanId) {
            $query->where('loan_id', $loanId);
        }

        return $query->orderBy('due_date')->get();
    }

    /**
     * Count installments by status.
     */
    public function countByStatus(int $loanId): array
    {
        return $this->activeInstallmentsQuery()
            ->where('loan_id', $loanId)
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}
