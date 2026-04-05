<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LoanInstallments;
use App\Models\LoanPaymentAllocations;
use App\Models\LoanPayments;
use App\Models\Loans;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LoanRepaymentReportService
{
    public function __construct(
        private readonly PortfolioRiskCalculator $portfolioRiskCalculator,
    ) {}

    /**
     * @param  array{date_from:Carbon,date_to:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,payment_method?:string|null,loan_status?:string|null,customer_id?:int|null,per_page?:int|null,page?:int|null,drilldown?:array|null}  $filters
     * @param  array<int>  $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);

        $paymentsInPeriodQ = $this->filteredPaymentsQuery($filters, $subshopIds)
            ->whereBetween('loan_payments.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $loanIdsScope = $this->filteredLoansScopeQuery($filters, $subshopIds)->pluck('loans.id');

        $totalCollected = (float) (clone $paymentsInPeriodQ)->sum('loan_payments.amount');
        $txCount = (int) (clone $paymentsInPeriodQ)->count('loan_payments.id');

        $onTimeLate = $this->onTimeVsLate($filters, $subshopIds, $dateFrom, $dateTo, $loanIdsScope);
        $scheduledVsActual = $this->scheduledVsActual($filters, $subshopIds, $dateFrom, $dateTo, $loanIdsScope);
        $recovery = $this->recoveryTracking($filters, $subshopIds, $dateFrom, $dateTo, $loanIdsScope);

        $totalForRate = (int) ($onTimeLate['on_time_payments'] ?? 0) + (int) ($onTimeLate['late_payments'] ?? 0);
        $onTimeRate = $totalForRate > 0 ? round(((int) ($onTimeLate['on_time_payments'] ?? 0) / $totalForRate) * 100, 2) : 0.0;
        $lateRate = $totalForRate > 0 ? round(((int) ($onTimeLate['late_payments'] ?? 0) / $totalForRate) * 100, 2) : 0.0;

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_ids' => $subshopIds,
                'loan_product_id' => $filters['loan_product_id'] ?? null,
                'loan_officer_id' => $filters['loan_officer_id'] ?? null,
                'payment_method' => $filters['payment_method'] ?? null,
                'loan_status' => $filters['loan_status'] ?? null,
                'customer_id' => $filters['customer_id'] ?? null,
            ],
            'summary' => [
                'total_repayments_collected' => round($totalCollected, 2),
                'repayment_transactions' => $txCount,
                'average_payment_amount' => $txCount > 0 ? round($totalCollected / $txCount, 2) : 0.0,
                'on_time_repayment_rate_pct' => $onTimeRate,
                'late_payment_rate_pct' => $lateRate,
                'collection_efficiency_ratio' => (float) ($scheduledVsActual['scheduled_amount'] ?? 0) > 0
                    ? round($totalCollected / (float) $scheduledVsActual['scheduled_amount'], 4)
                    : 0.0,
                'collection_efficiency_pct' => (float) ($scheduledVsActual['collection_efficiency'] ?? 0),
                'recovery_rate_pct' => (float) ($recovery['recovery_rate_pct'] ?? 0),
            ],
            'trends' => $this->repaymentTrends($paymentsInPeriodQ, $dateFrom, $dateTo),
            'by_product' => $this->repaymentsByProduct($paymentsInPeriodQ, $subshopIds),
            'by_branch' => $this->repaymentsByBranch($paymentsInPeriodQ, $subshopIds),
            'by_officer' => $this->repaymentsByOfficer($paymentsInPeriodQ),
            'payment_methods' => $this->paymentMethodAnalysis($paymentsInPeriodQ),
            'on_time_vs_late' => $onTimeLate,
            'aging' => $this->repaymentAging($filters, $subshopIds, $dateFrom, $dateTo, $loanIdsScope),
            'scheduled_vs_actual' => $scheduledVsActual,
            'partial_vs_full' => $this->partialVsFull($filters, $subshopIds, $dateFrom, $dateTo, $loanIdsScope),
            'recovery' => $recovery,
            'top_customers' => $this->topPayingCustomers($paymentsInPeriodQ),
            'loan_level' => $this->loanLevelTable($filters, $subshopIds, $dateFrom, $dateTo, $loanIdsScope),
            'installment_level' => $this->installmentLevelTable($filters, $subshopIds, $dateFrom, $dateTo, $loanIdsScope),
            'officer_performance' => $this->collectionPerformanceByOfficer($filters, $subshopIds, $dateFrom, $dateTo, $loanIdsScope),
        ];
    }

    /** @return array<int> */
    private function resolveSubshopFilter(?int $subshopId, array $accessibleSubshopIds): array
    {
        if ($subshopId) {
            return in_array($subshopId, $accessibleSubshopIds, true) ? [$subshopId] : [-1];
        }

        return $accessibleSubshopIds ?: [-1];
    }

    private function filteredPaymentsQuery(array $filters, array $subshopIds): Builder
    {
        $q = LoanPayments::query()
            ->from('loan_payments')
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('loan_payments.status', 'confirmed');

        if (! empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (! empty($filters['loan_officer_id'])) {
            $q->where('loan_payments.user_id', (int) $filters['loan_officer_id']);
        }
        if (! empty($filters['payment_method'])) {
            $q->where('loan_payments.payment_method', (string) $filters['payment_method']);
        }
        if (! empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (! empty($filters['customer_id'])) {
            $q->where('loan_payments.customer_id', (int) $filters['customer_id']);
        }

        return $q;
    }

    private function filteredLoansScopeQuery(array $filters, array $subshopIds): Builder
    {
        $q = Loans::query()->from('loans')->whereIn('loans.subshop_id', $subshopIds);

        if (! empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (! empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (! empty($filters['customer_id'])) {
            $q->where('loans.customer_id', (int) $filters['customer_id']);
        }

        return $q;
    }

    private function repaymentTrends(Builder $paymentsInPeriodQ, Carbon $dateFrom, Carbon $dateTo): array
    {
        $days = (int) $dateFrom->diffInDays($dateTo) + 1;
        $auto = $days <= 45 ? 'daily' : ($days <= 180 ? 'weekly' : 'monthly');

        return [
            'auto' => $this->repaymentTrendsForGranularity($paymentsInPeriodQ, $auto),
            'daily' => $this->repaymentTrendsForGranularity($paymentsInPeriodQ, 'daily'),
            'weekly' => $this->repaymentTrendsForGranularity($paymentsInPeriodQ, 'weekly'),
            'monthly' => $this->repaymentTrendsForGranularity($paymentsInPeriodQ, 'monthly'),
        ];
    }

    private function repaymentTrendsForGranularity(Builder $paymentsInPeriodQ, string $granularity): array
    {
        if ($granularity === 'daily') {
            $rows = (clone $paymentsInPeriodQ)
                ->selectRaw('DATE(loan_payments.payment_date) as period')
                ->selectRaw('COUNT(loan_payments.id) as payments_count')
                ->selectRaw('SUM(loan_payments.amount) as amount')
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            $mapped = $rows->map(fn ($r) => [
                'period' => (string) $r->period,
                'payments' => (int) ($r->payments_count ?? 0),
                'amount' => round((float) ($r->amount ?? 0), 2),
            ])->all();

            return [
                'granularity' => 'daily',
                'rows' => $mapped,
                'chart' => [
                    'labels' => array_column($mapped, 'period'),
                    'payments' => array_column($mapped, 'payments'),
                    'amount' => array_column($mapped, 'amount'),
                ],
            ];
        }

        if ($granularity === 'weekly') {
            $rows = (clone $paymentsInPeriodQ)
                ->selectRaw('YEARWEEK(loan_payments.payment_date, 1) as yw')
                ->selectRaw('MIN(DATE(loan_payments.payment_date)) as week_start')
                ->selectRaw('MAX(DATE(loan_payments.payment_date)) as week_end')
                ->selectRaw('COUNT(loan_payments.id) as payments_count')
                ->selectRaw('SUM(loan_payments.amount) as amount')
                ->groupBy('yw')
                ->orderBy('yw')
                ->get();

            $mapped = $rows->map(fn ($r) => [
                'period' => (string) ($r->week_start ?? '').' to '.(string) ($r->week_end ?? ''),
                'payments' => (int) ($r->payments_count ?? 0),
                'amount' => round((float) ($r->amount ?? 0), 2),
            ])->all();

            return [
                'granularity' => 'weekly',
                'rows' => $mapped,
                'chart' => [
                    'labels' => array_column($mapped, 'period'),
                    'payments' => array_column($mapped, 'payments'),
                    'amount' => array_column($mapped, 'amount'),
                ],
            ];
        }

        $rows = (clone $paymentsInPeriodQ)
            ->selectRaw("DATE_FORMAT(loan_payments.payment_date, '%Y-%m') as period")
            ->selectRaw('COUNT(loan_payments.id) as payments_count')
            ->selectRaw('SUM(loan_payments.amount) as amount')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $mapped = $rows->map(fn ($r) => [
            'period' => (string) $r->period,
            'payments' => (int) ($r->payments_count ?? 0),
            'amount' => round((float) ($r->amount ?? 0), 2),
        ])->all();

        return [
            'granularity' => 'monthly',
            'rows' => $mapped,
            'chart' => [
                'labels' => array_column($mapped, 'period'),
                'payments' => array_column($mapped, 'payments'),
                'amount' => array_column($mapped, 'amount'),
            ],
        ];
    }

    private function repaymentsByProduct(Builder $paymentsInPeriodQ, array $subshopIds): array
    {
        $rows = (clone $paymentsInPeriodQ)
            ->leftJoin('loan_products as lp', 'lp.id', '=', 'loans.loan_product_id')
            ->selectRaw('loans.loan_product_id as loan_product_id')
            ->selectRaw('COALESCE(lp.name, "Unknown") as product')
            ->selectRaw('COUNT(loan_payments.id) as payments_count')
            ->selectRaw('SUM(loan_payments.amount) as amount')
            ->selectRaw('AVG(loan_payments.amount) as avg_payment')
            ->groupBy('loan_product_id', 'product')
            ->orderByDesc('amount')
            ->get();

        return $rows->map(fn ($r) => [
            'loan_product_id' => (int) ($r->loan_product_id ?? 0),
            'product' => (string) ($r->product ?? ''),
            'payments' => (int) ($r->payments_count ?? 0),
            'amount' => round((float) ($r->amount ?? 0), 2),
            'average_payment' => round((float) ($r->avg_payment ?? 0), 2),
        ])->all();
    }

    private function repaymentsByBranch(Builder $paymentsInPeriodQ, array $subshopIds): array
    {
        $rows = (clone $paymentsInPeriodQ)
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->selectRaw('loans.subshop_id as subshop_id')
            ->selectRaw('COALESCE(ss.name, "Unknown") as branch')
            ->selectRaw('COUNT(loan_payments.id) as payments_count')
            ->selectRaw('SUM(loan_payments.amount) as amount')
            ->selectRaw('AVG(loan_payments.amount) as avg_payment')
            ->groupBy('subshop_id', 'branch')
            ->orderByDesc('amount')
            ->get();

        return $rows->map(fn ($r) => [
            'subshop_id' => (int) ($r->subshop_id ?? 0),
            'branch' => (string) ($r->branch ?? ''),
            'payments' => (int) ($r->payments_count ?? 0),
            'amount' => round((float) ($r->amount ?? 0), 2),
            'average_payment' => round((float) ($r->avg_payment ?? 0), 2),
        ])->all();
    }

    private function repaymentsByOfficer(Builder $paymentsInPeriodQ): array
    {
        $rows = (clone $paymentsInPeriodQ)
            ->leftJoin('users as u', 'u.id', '=', 'loan_payments.user_id')
            ->selectRaw('loan_payments.user_id as user_id')
            ->selectRaw('COALESCE(u.name, "Unknown") as officer')
            ->selectRaw('COUNT(loan_payments.id) as payments_count')
            ->selectRaw('SUM(loan_payments.amount) as amount')
            ->selectRaw('AVG(loan_payments.amount) as avg_payment')
            ->groupBy('user_id', 'officer')
            ->orderByDesc('amount')
            ->get();

        return $rows->map(fn ($r) => [
            'user_id' => (int) ($r->user_id ?? 0),
            'officer' => (string) ($r->officer ?? ''),
            'payments' => (int) ($r->payments_count ?? 0),
            'amount' => round((float) ($r->amount ?? 0), 2),
            'average_payment' => round((float) ($r->avg_payment ?? 0), 2),
        ])->all();
    }

    private function paymentMethodAnalysis(Builder $paymentsInPeriodQ): array
    {
        $rows = (clone $paymentsInPeriodQ)
            ->selectRaw('loan_payments.payment_method as payment_method')
            ->selectRaw('COUNT(loan_payments.id) as payments_count')
            ->selectRaw('SUM(loan_payments.amount) as amount')
            ->selectRaw('AVG(loan_payments.amount) as avg_payment')
            ->groupBy('payment_method')
            ->orderByDesc('amount')
            ->get();

        return $rows->map(fn ($r) => [
            'payment_method' => (string) ($r->payment_method ?? ''),
            'payments' => (int) ($r->payments_count ?? 0),
            'amount' => round((float) ($r->amount ?? 0), 2),
            'average_payment' => round((float) ($r->avg_payment ?? 0), 2),
        ])->all();
    }

    private function topPayingCustomers(Builder $paymentsInPeriodQ): array
    {
        $rows = (clone $paymentsInPeriodQ)
            ->leftJoin('customers as c', 'c.id', '=', 'loan_payments.customer_id')
            ->selectRaw('loan_payments.customer_id as customer_id')
            ->selectRaw('COALESCE(c.name, "Unknown") as customer')
            ->selectRaw('COUNT(loan_payments.id) as payments_count')
            ->selectRaw('SUM(loan_payments.amount) as amount')
            ->whereNotNull('loan_payments.customer_id')
            ->groupBy('customer_id', 'customer')
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'customer_id' => (int) ($r->customer_id ?? 0),
            'customer' => (string) ($r->customer ?? ''),
            'payments' => (int) ($r->payments_count ?? 0),
            'amount' => round((float) ($r->amount ?? 0), 2),
        ])->all();
    }

    private function onTimeVsLate(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIdsScope): array
    {
        $base = LoanPaymentAllocations::query()
            ->from('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (! empty($filters['loan_officer_id'])) {
            $base->where('lp.user_id', (int) $filters['loan_officer_id']);
        }
        if (! empty($filters['payment_method'])) {
            $base->where('lp.payment_method', (string) $filters['payment_method']);
        }

        $rows = (clone $base)
            ->selectRaw('CASE WHEN lp.payment_date <= li.due_date THEN "on_time" ELSE "late" END as bucket')
            ->selectRaw('COUNT(DISTINCT lp.id) as payments_count')
            ->selectRaw('SUM(lpa.principal_amount + lpa.interest_amount + lpa.fee_amount + lpa.penalty_amount) as allocated_amount')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $onTimeAmount = (float) (($rows['on_time']->allocated_amount ?? 0) ?? 0);
        $lateAmount = (float) (($rows['late']->allocated_amount ?? 0) ?? 0);
        $onTimePayments = (int) (($rows['on_time']->payments_count ?? 0) ?? 0);
        $latePayments = (int) (($rows['late']->payments_count ?? 0) ?? 0);
        $totalPayments = $onTimePayments + $latePayments;

        return [
            'on_time_payments' => $onTimePayments,
            'late_payments' => $latePayments,
            'on_time_amount' => round($onTimeAmount, 2),
            'late_amount' => round($lateAmount, 2),
            'on_time_rate' => $totalPayments > 0 ? round(($onTimePayments / $totalPayments) * 100, 2) : 0.0,
        ];
    }

    private function repaymentAging(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIdsScope): array
    {
        $base = LoanPaymentAllocations::query()
            ->from('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereRaw('lp.payment_date > li.due_date');

        if (! empty($filters['loan_officer_id'])) {
            $base->where('lp.user_id', (int) $filters['loan_officer_id']);
        }
        if (! empty($filters['payment_method'])) {
            $base->where('lp.payment_method', (string) $filters['payment_method']);
        }

        $rows = (clone $base)
            ->selectRaw('CASE
                WHEN DATEDIFF(lp.payment_date, li.due_date) BETWEEN 1 AND 30 THEN "1-30"
                WHEN DATEDIFF(lp.payment_date, li.due_date) BETWEEN 31 AND 60 THEN "31-60"
                WHEN DATEDIFF(lp.payment_date, li.due_date) BETWEEN 61 AND 90 THEN "61-90"
                ELSE "90+"
            END as bucket')
            ->selectRaw('COUNT(DISTINCT lp.id) as payments_count')
            ->selectRaw('SUM(lpa.principal_amount + lpa.interest_amount + lpa.fee_amount + lpa.penalty_amount) as amount')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $buckets = ['1-30', '31-60', '61-90', '90+'];
        $mapped = [];
        foreach ($buckets as $b) {
            $mapped[] = [
                'bucket' => $b,
                'payments' => (int) (($rows[$b]->payments_count ?? 0) ?? 0),
                'amount' => round((float) (($rows[$b]->amount ?? 0) ?? 0), 2),
            ];
        }

        return [
            'buckets' => $mapped,
        ];
    }

    private function scheduledVsActual(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIdsScope): array
    {
        $scheduled = (float) LoanInstallments::query()
            ->withoutGlobalScopes()
            ->from('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('li.is_active', 1)
            ->whereBetween('li.due_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('li.total_due');

        $actual = (float) $this->filteredPaymentsQuery($filters, $subshopIds)
            ->whereBetween('loan_payments.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('loan_payments.amount');

        return [
            'scheduled_amount' => round($scheduled, 2),
            'actual_collected' => round($actual, 2),
            'variance' => round($scheduled - $actual, 2),
            'collection_efficiency' => $scheduled > 0 ? round(($actual / $scheduled) * 100, 2) : 0.0,
        ];
    }

    private function partialVsFull(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIdsScope): array
    {
        $base = LoanInstallments::query()
            ->withoutGlobalScopes()
            ->from('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('li.is_active', 1)
            ->whereNotNull('li.paid_date')
            ->whereBetween('li.paid_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $rows = (clone $base)
            ->selectRaw('SUM(CASE WHEN li.status = "paid" THEN 1 ELSE 0 END) as full_count')
            ->selectRaw('SUM(CASE WHEN li.status = "partial" THEN 1 ELSE 0 END) as partial_count')
            ->selectRaw('SUM(CASE WHEN li.status = "paid" THEN li.amount_paid ELSE 0 END) as full_amount')
            ->selectRaw('SUM(CASE WHEN li.status = "partial" THEN li.amount_paid ELSE 0 END) as partial_amount')
            ->first();

        return [
            'full_payments_count' => (int) ($rows->full_count ?? 0),
            'partial_payments_count' => (int) ($rows->partial_count ?? 0),
            'full_payments_amount' => round((float) ($rows->full_amount ?? 0), 2),
            'partial_payments_amount' => round((float) ($rows->partial_amount ?? 0), 2),
        ];
    }

    private function recoveryTracking(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIdsScope): array
    {
        $asOf = $dateTo->toDateString();

        $overdueTotal = (float) LoanInstallments::query()
            ->withoutGlobalScopes()
            ->from('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('li.is_active', 1)
            ->where('li.outstanding_amount', '>', 0)
            ->where('li.due_date', '<', $asOf)
            ->sum('li.outstanding_amount');

        $recoveredQ = LoanPaymentAllocations::query()
            ->from('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('li.due_date', '<', $asOf);

        if (! empty($filters['loan_officer_id'])) {
            $recoveredQ->where('lp.user_id', (int) $filters['loan_officer_id']);
        }
        if (! empty($filters['payment_method'])) {
            $recoveredQ->where('lp.payment_method', (string) $filters['payment_method']);
        }

        $recoveredAmount = (float) (clone $recoveredQ)
            ->sum(DB::raw('(lpa.principal_amount + lpa.interest_amount + lpa.fee_amount + lpa.penalty_amount)'));

        $recoveredTx = (int) (clone $recoveredQ)
            ->distinct('lp.id')
            ->count('lp.id');

        $recoveryRate = $overdueTotal > 0 ? round(($recoveredAmount / $overdueTotal) * 100, 2) : 0.0;

        return [
            'total_overdue_amount' => round($overdueTotal, 2),
            'recovery_collected' => round($recoveredAmount, 2),
            'recovery_transactions' => $recoveredTx,
            'recovery_rate_pct' => $recoveryRate,
        ];
    }

    private function loanLevelTable(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIdsScope): LengthAwarePaginator
    {
        $perPage = ! empty($filters['per_page']) ? max(1, (int) $filters['per_page']) : 15;
        $page = ! empty($filters['page']) ? max(1, (int) $filters['page']) : 1;

        $paymentsInPeriodSub = $this->filteredPaymentsQuery($filters, $subshopIds)
            ->whereBetween('loan_payments.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('loan_payments.loan_id as loan_id')
            ->selectRaw('SUM(loan_payments.amount) as period_paid')
            ->groupBy('loan_id');

        $paymentsToDateSub = $this->filteredPaymentsQuery($filters, $subshopIds)
            ->selectRaw('loan_payments.loan_id as loan_id')
            ->selectRaw('SUM(loan_payments.amount) as total_paid')
            ->groupBy('loan_id');

        $outstandingSub = LoanInstallments::query()
            ->withoutGlobalScopes()
            ->from('loan_installments as li')
            ->where('li.is_active', 1)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.outstanding_amount) as outstanding')
            ->groupBy('loan_id');

        $totalDueSub = LoanInstallments::query()
            ->withoutGlobalScopes()
            ->from('loan_installments as li')
            ->where('li.is_active', 1)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.total_due) as total_due')
            ->groupBy('loan_id');

        $lastPaymentSub = LoanPayments::query()
            ->from('loan_payments')
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('loan_payments.status', 'confirmed')
            ->selectRaw('loan_payments.loan_id as loan_id')
            ->selectRaw('MAX(loan_payments.payment_date) as last_payment_date')
            ->groupBy('loan_id');

        return Loans::query()
            ->from('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->leftJoinSub($paymentsInPeriodSub, 'p_period', 'p_period.loan_id', '=', 'loans.id')
            ->leftJoinSub($paymentsToDateSub, 'p_total', 'p_total.loan_id', '=', 'loans.id')
            ->leftJoinSub($outstandingSub, 'os', 'os.loan_id', '=', 'loans.id')
            ->leftJoinSub($totalDueSub, 'td', 'td.loan_id', '=', 'loans.id')
            ->leftJoinSub($lastPaymentSub, 'lpmax', 'lpmax.loan_id', '=', 'loans.id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as lp', 'lp.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->selectRaw('loans.id as loan_id')
            ->selectRaw('loans.loan_code as loan_code')
            ->selectRaw('COALESCE(c.name, "Unknown") as customer')
            ->selectRaw('COALESCE(lp.name, "Unknown") as product')
            ->selectRaw('COALESCE(ss.name, "Unknown") as branch')
            ->selectRaw('loans.status as status')
            ->selectRaw('COALESCE(td.total_due, 0) as total_due')
            ->selectRaw('COALESCE(p_period.period_paid, 0) as period_paid')
            ->selectRaw('COALESCE(p_total.total_paid, 0) as total_paid')
            ->selectRaw('COALESCE(os.outstanding, 0) as outstanding')
            ->selectRaw('COALESCE(lpmax.last_payment_date, "") as last_payment_date')
            ->orderByDesc('period_paid')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function installmentLevelTable(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIdsScope): LengthAwarePaginator
    {
        $perPage = ! empty($filters['per_page']) ? max(1, (int) $filters['per_page']) : 15;
        $page = ! empty($filters['page']) ? max(1, (int) $filters['page']) : 1;

        $paidInPeriodSub = LoanPaymentAllocations::query()
            ->from('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('lpa.loan_installment_id as installment_id')
            ->selectRaw('SUM(lpa.principal_amount + lpa.interest_amount + lpa.fee_amount + lpa.penalty_amount) as paid_in_period')
            ->groupBy('installment_id');

        return LoanInstallments::query()
            ->withoutGlobalScopes()
            ->from('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('li.is_active', 1)
            ->leftJoinSub($paidInPeriodSub, 'pip', 'pip.installment_id', '=', 'li.id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->selectRaw('li.id as installment_id')
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('li.installment_number as installment_number')
            ->selectRaw('COALESCE(c.name, "Unknown") as customer')
            ->selectRaw('li.due_date as due_date')
            ->selectRaw('li.paid_date as paid_date')
            ->selectRaw('li.status as status')
            ->selectRaw('li.total_due as total_due')
            ->selectRaw('li.amount_paid as amount_paid')
            ->selectRaw('li.outstanding_amount as outstanding_amount')
            ->selectRaw('COALESCE(pip.paid_in_period, 0) as paid_in_period')
            ->orderBy('li.due_date')
            ->paginate($perPage, ['*'], 'installments_page', $page);
    }

    private function collectionPerformanceByOfficer(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIdsScope): array
    {
        $paymentsQ = $this->filteredPaymentsQuery($filters, $subshopIds)
            ->whereBetween('loan_payments.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereIn('loans.id', $loanIdsScope);

        $rows = (clone $paymentsQ)
            ->leftJoin('users as u', 'u.id', '=', 'loan_payments.user_id')
            ->selectRaw('loan_payments.user_id as user_id')
            ->selectRaw('COALESCE(u.name, "Unknown") as officer')
            ->selectRaw('COUNT(loan_payments.id) as payments_count')
            ->selectRaw('SUM(loan_payments.amount) as amount')
            ->groupBy('user_id', 'officer')
            ->orderByDesc('amount')
            ->get();

        $onTimeByOfficer = LoanPaymentAllocations::query()
            ->from('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('lp.user_id as user_id')
            ->selectRaw('COUNT(DISTINCT lp.id) as total_payments')
            ->selectRaw('SUM(CASE WHEN lp.payment_date <= li.due_date THEN 1 ELSE 0 END) as on_time_payments')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $asOf = $dateTo->toDateString();
        $overdueByOfficer = LoanInstallments::query()
            ->withoutGlobalScopes()
            ->from('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->leftJoin('loan_payments as lp', function ($j) use ($dateFrom, $dateTo) {
                $j->on('lp.loan_id', '=', 'loans.id')
                    ->where('lp.status', 'confirmed')
                    ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
            })
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('li.is_active', 1)
            ->where('li.outstanding_amount', '>', 0)
            ->where('li.due_date', '<', $asOf)
            ->selectRaw('lp.user_id as user_id')
            ->selectRaw('SUM(li.outstanding_amount) as overdue_total')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $recoveredByOfficer = LoanPaymentAllocations::query()
            ->from('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.id', $loanIdsScope)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('li.due_date', '<', $asOf)
            ->selectRaw('lp.user_id as user_id')
            ->selectRaw('SUM(lpa.principal_amount + lpa.interest_amount + lpa.fee_amount + lpa.penalty_amount) as recovered_amount')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return $rows->map(function ($r) use ($onTimeByOfficer, $overdueByOfficer, $recoveredByOfficer) {
            $userId = (int) ($r->user_id ?? 0);
            $otRow = $onTimeByOfficer[$userId] ?? null;
            $total = (int) ($otRow->total_payments ?? 0);
            $onTime = (int) ($otRow->on_time_payments ?? 0);

            $overdueTotal = (float) (($overdueByOfficer[$userId]->overdue_total ?? 0) ?? 0);
            $recoveredAmount = (float) (($recoveredByOfficer[$userId]->recovered_amount ?? 0) ?? 0);
            $recoveryRate = $overdueTotal > 0 ? round(($recoveredAmount / $overdueTotal) * 100, 2) : 0.0;

            return [
                'user_id' => $userId,
                'officer' => (string) ($r->officer ?? ''),
                'payments' => (int) ($r->payments_count ?? 0),
                'amount' => round((float) ($r->amount ?? 0), 2),
                'on_time_rate' => $total > 0 ? round(($onTime / $total) * 100, 2) : 0.0,
                'recovery_rate_pct' => $recoveryRate,
            ];
        })->all();
    }
}
