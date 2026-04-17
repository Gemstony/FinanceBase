<?php

declare(strict_types=1);

namespace App\Repositories\Loans;

use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Loan Repository
 *
 * Consolidates loan queries for consistent data access patterns.
 */
class LoanRepository
{
    /**
     * Get active loans query (base query used across system).
     */
    public function activeLoansQuery(): Builder
    {
        return Loans::query()
            ->where('is_active', true)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('is_written_off', false);
    }

    /**
     * Get loans by risk status.
     */
    public function getByRiskStatus(string $riskStatus, ?int $subshopId = null): Collection
    {
        $query = $this->activeLoansQuery()
            ->where('risk_status', $riskStatus);

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        return $query->get();
    }

    /**
     * Get loans with overdue installments.
     */
    public function getWithOverdueInstallments(int $minDays = 1, ?int $subshopId = null): Collection
    {
        $cutoffDate = Carbon::today()->subDays($minDays);

        $loanIds = \DB::table('loan_installments')
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoffDate)
            ->distinct()
            ->pluck('loan_id');

        $query = $this->activeLoansQuery()
            ->whereIn('id', $loanIds);

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        return $query->get();
    }

    /**
     * Get loan with all related data for risk calculations.
     */
    public function getWithRiskData(int $loanId): ?Loans
    {
        return $this->activeLoansQuery()
            ->with([
                'installments' => fn($q) => $q->where('is_active', true),
                'customer',
                'loanProduct',
                'latestDisbursement.processor',
            ])
            ->find($loanId);
    }

    /**
     * Get portfolio summary by subshop.
     */
    public function getPortfolioSummaryBySubshop(): Collection
    {
        return $this->activeLoansQuery()
            ->select('subshop_id')
            ->selectRaw('COUNT(*) as loan_count')
            ->selectRaw('SUM(principal_amount) as total_principal')
            ->groupBy('subshop_id')
            ->get();
    }

    /**
     * Update loan risk status.
     */
    public function updateRiskStatus(int $loanId, string $riskStatus, int $maxDaysOverdue): bool
    {
        $loan = Loans::find($loanId);

        if (!$loan) {
            return false;
        }

        return $loan->update([
            'risk_status' => $riskStatus,
            'max_days_overdue' => $maxDaysOverdue,
        ]);
    }

    /**
     * Bulk update risk statuses.
     */
    public function bulkUpdateRiskStatuses(array $updates): int
    {
        $affected = 0;

        foreach ($updates as $loanId => $data) {
            if ($this->updateRiskStatus($loanId, $data['risk_status'], $data['max_dpd'])) {
                $affected++;
            }
        }

        return $affected;
    }

    /**
     * Get loans for collections worklist.
     */
    public function getCollectionsWorklist(?int $subshopId = null, int $minDaysOverdue = 1): Collection
    {
        return $this->getWithOverdueInstallments($minDaysOverdue, $subshopId)
            ->load(['customer', 'loanGroup', 'latestDisbursement.processor']);
    }

    /**
     * Get customer exposure (total outstanding for a customer).
     */
    public function getCustomerExposure(int $customerId): float
    {
        return $this->activeLoansQuery()
            ->where('customer_id', $customerId)
            ->sum('principal_amount'); // This is a simplified calculation
    }

    /**
     * Search loans by various criteria.
     */
    public function search(array $criteria): Collection
    {
        $query = $this->activeLoansQuery();

        if (!empty($criteria['customer_id'])) {
            $query->where('customer_id', $criteria['customer_id']);
        }

        if (!empty($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (!empty($criteria['risk_status'])) {
            $query->where('risk_status', $criteria['risk_status']);
        }

        if (!empty($criteria['subshop_id'])) {
            $query->where('subshop_id', $criteria['subshop_id']);
        }

        if (!empty($criteria['loan_product_id'])) {
            $query->where('loan_product_id', $criteria['loan_product_id']);
        }

        if (!empty($criteria['disbursed_from'])) {
            $query->whereDate('disbursement_date', '>=', $criteria['disbursed_from']);
        }

        if (!empty($criteria['disbursed_to'])) {
            $query->whereDate('disbursement_date', '<=', $criteria['disbursed_to']);
        }

        return $query->get();
    }
}
