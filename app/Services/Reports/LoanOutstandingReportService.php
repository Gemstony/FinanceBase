<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Loans\Risk\LoanDelinquencyEngine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LoanOutstandingReportService
{
    public function __construct(
        private readonly LoanDelinquencyEngine $delinquencyEngine,
    ) {
    }
    /**
     * @param  array{as_at_date:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,customer_id?:int|null}  $filters
     * @param  array<int>  $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds, int $perPage = 50, bool $paginate = true): array
    {
        $asAt = $filters['as_at_date'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);

        $loansBase = $this->filteredLoansQuery($filters, $subshopIds);

        // Get loan IDs for passing to LoanDelinquencyEngine (keep as Collection, not array)
        $loanIds = $loansBase->pluck('loans.id');

        $loanRowsBase = $this->loanOutstandingBaseQuery($loansBase, $asAt);

        $loanRows = $paginate
            ? $this->paginateLoanRows($loanRowsBase, $perPage)
            : $loanRowsBase->orderByDesc('total_outstanding')->get()->all();

        $rowsCollection = collect($loanRows instanceof LengthAwarePaginator ? $loanRows->items() : $loanRows);

        $kpis = $this->summaryKpis($loanRowsBase, $subshopIds, $loanIds);

        return [
            'filters' => [
                'as_at_date' => $asAt->toDateString(),
                'subshop_ids' => $subshopIds,
            ],
            'summary' => $kpis,
            'loans' => $loanRows,
            'by_product' => $this->groupByProduct($loanRowsBase, $subshopIds),
            'by_branch' => $this->groupByBranch($loanRowsBase, $subshopIds),
            'by_officer' => $this->groupByOfficer($loanRowsBase, $subshopIds),
            'distribution' => $this->outstandingDistribution($loanRowsBase),
            'top_borrowers' => $this->topBorrowers($loanRowsBase),
            'status_breakdown' => $this->statusBreakdown($loanRowsBase),
            'vs_disbursed' => $this->outstandingVsDisbursed($filters, $subshopIds, $asAt, $kpis),
            'composition' => $this->principalVsInterestComposition($kpis),
            'snapshot' => $this->timeSnapshot($filters, $subshopIds, $asAt),
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

    private function filteredLoansQuery(array $filters, array $subshopIds): Builder
    {
        // Use LoanDelinquencyEngine's active portfolio logic as base
        // This ensures consistency with other portfolio reports
        $baseStatuses = ['disbursed', 'partially_paid', 'defaulted', 'written_off'];

        $q = \App\Models\Loans::query()
            ->from('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', $baseStatuses)
            ->where('loans.is_active', true);

        if (! empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }

        if (! empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }

        if (! empty($filters['customer_id'])) {
            $q->where('loans.customer_id', (int) $filters['customer_id']);
        }

        if (! empty($filters['loan_officer_id'])) {
            $officerId = (int) $filters['loan_officer_id'];

            $latestDisbursement = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisbursement, 'ld_latest', function ($j) {
                $j->on('ld_latest.loan_id', '=', 'loans.id');
            })
                ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', $officerId)
                ->select('loans.*');
        }

        return $q;
    }

    /**
     * Base outstanding query, returning component-level and total outstanding per loan as-at date.
     * Uses loan_installments.outstanding_amount directly for consistency with LoanDelinquencyEngine.
     */
    private function loanOutstandingBaseQuery(Builder $loansBase, Carbon $asAt)
    {
        $asAtDate = $asAt->toDateString();

        // Aggregate outstanding amounts directly from loan_installments as-at date
        // Total outstanding uses outstanding_amount directly (single source of truth)
        // Component outstanding is calculated from expected minus paid allocations
        $outstandingAgg = DB::table('loan_installments as li')
            ->where('li.is_active', true)
            ->whereDate('li.due_date', '<=', $asAtDate)
            ->selectRaw('li.loan_id')
            ->selectRaw('SUM(COALESCE(li.outstanding_amount,0)) as total_outstanding')
            ->selectRaw('SUM(COALESCE(li.principal_due,0)) as principal_expected')
            ->selectRaw('SUM(COALESCE(li.interest_due,0)) as interest_expected')
            ->selectRaw('SUM(COALESCE(li.fees_due,0)) as fees_expected')
            ->groupBy('li.loan_id');

        // Paid amounts by component as-at date (for accurate component outstanding calculation)
        $paidAgg = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->where('lp.status', 'confirmed')
            ->whereDate('lp.payment_date', '<=', $asAtDate)
            ->whereDate('li.due_date', '<=', $asAtDate)
            ->where('li.is_active', true)
            ->selectRaw('lp.loan_id as loan_id')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)) as principal_paid')
            ->selectRaw('SUM(COALESCE(lpa.interest_amount,0)) as interest_paid')
            ->selectRaw('SUM(COALESCE(lpa.fee_amount,0)) as fees_paid')
            ->groupBy('lp.loan_id');

        $lastPayment = DB::table('loan_payments as lp')
            ->where('lp.status', 'confirmed')
            ->whereDate('lp.payment_date', '<=', $asAtDate)
            ->selectRaw('lp.loan_id, MAX(lp.payment_date) as last_payment_date')
            ->groupBy('lp.loan_id');

        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $loanOfficer = DB::query()
            ->fromSub($loansBase->select(['loans.id']), 'lb')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'lb.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->selectRaw('lb.id as loan_id, ld.processed_by as officer_id');

        $base = DB::query()
            ->fromSub($loansBase->select([
                'loans.id',
                'loans.loan_code',
                'loans.customer_id',
                'loans.loan_product_id',
                'loans.subshop_id',
                'loans.disbursement_date',
                'loans.status',
            ]), 'l')
            ->leftJoin('customers as c', 'c.id', '=', 'l.customer_id')
            ->leftJoin('loan_products as pr', 'pr.id', '=', 'l.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'l.subshop_id')
            ->leftJoinSub($outstandingAgg, 'os', fn ($j) => $j->on('os.loan_id', '=', 'l.id'))
            ->leftJoinSub($paidAgg, 'pd', fn ($j) => $j->on('pd.loan_id', '=', 'l.id'))
            ->leftJoinSub($lastPayment, 'lp', fn ($j) => $j->on('lp.loan_id', '=', 'l.id'))
            ->leftJoinSub($loanOfficer, 'lo', fn ($j) => $j->on('lo.loan_id', '=', 'l.id'))
            ->leftJoin('users as u', 'u.id', '=', 'lo.officer_id')
            ->select([
                'l.id as loan_id',
                'l.loan_code',
                'l.customer_id',
                DB::raw('c.name as customer_name'),
                'l.loan_product_id',
                DB::raw('pr.name as loan_product_name'),
                'l.subshop_id',
                DB::raw('ss.name as branch_name'),
                DB::raw('lo.officer_id as officer_id'),
                DB::raw('u.name as officer_name'),
                'l.disbursement_date',
                'l.status as loan_status',
                DB::raw('COALESCE(os.principal_expected,0) as principal_expected'),
                DB::raw('COALESCE(os.interest_expected,0) as interest_expected'),
                DB::raw('COALESCE(os.fees_expected,0) as fees_expected'),
                DB::raw('COALESCE(pd.principal_paid,0) as principal_paid'),
                DB::raw('COALESCE(pd.interest_paid,0) as interest_paid'),
                DB::raw('COALESCE(pd.fees_paid,0) as fees_paid'),
                DB::raw('lp.last_payment_date as last_payment_date'),
                DB::raw('COALESCE(os.total_outstanding,0) as total_outstanding'),
            ])
            // Calculate component outstanding as expected minus paid (ensuring non-negative)
            ->selectRaw('GREATEST(0, COALESCE(os.principal_expected,0) - COALESCE(pd.principal_paid,0)) as principal_outstanding')
            ->selectRaw('GREATEST(0, COALESCE(os.interest_expected,0) - COALESCE(pd.interest_paid,0)) as interest_outstanding')
            ->selectRaw('GREATEST(0, COALESCE(os.fees_expected,0) - COALESCE(pd.fees_paid,0)) as fees_outstanding');

        // Status filter already applied in filteredLoansQuery using LoanDelinquencyEngine logic
        $wrapped = DB::query()->fromSub($base, 't');

        return $wrapped;
    }

    private function paginateLoanRows($loanRowsBase, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) request('page', 1));
        $perPage = max(1, min(200, $perPage));

        $total = (clone $loanRowsBase)->count();

        $items = (clone $loanRowsBase)
            ->orderByDesc('total_outstanding')
            ->forPage($page, $perPage)
            ->get();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function summaryKpis($loanRowsBase, array $subshopIds, $loanIds): array
    {
        // Use LoanDelinquencyEngine for total outstanding (single source of truth)
        // This ensures consistency with LoanPortfolioReportService
        // $loanIds can be array or Collection - LoanDelinquencyEngine handles both
        $totalOutstanding = $this->delinquencyEngine->calculatePortfolioOutstandingFromInstallments($subshopIds, $loanIds);

        // Get component breakdown and loan count from query (engine doesn't provide components)
        $row = (array) (clone $loanRowsBase)
            ->selectRaw('SUM(principal_outstanding) as principal_outstanding')
            ->selectRaw('SUM(interest_outstanding) as interest_outstanding')
            ->selectRaw('SUM(fees_outstanding) as fees_outstanding')
            ->selectRaw('COUNT(*) as loans_count')
            ->first();

        $loansCount = (int) ($row['loans_count'] ?? 0);

        return [
            'total_outstanding' => round($totalOutstanding, 2),
            'principal_outstanding' => round((float) ($row['principal_outstanding'] ?? 0.0), 2),
            'interest_outstanding' => round((float) ($row['interest_outstanding'] ?? 0.0), 2),
            'fees_outstanding' => round((float) ($row['fees_outstanding'] ?? 0.0), 2),
            'active_loans' => $loansCount,
            'avg_outstanding_per_loan' => $loansCount > 0 ? round($totalOutstanding / $loansCount, 2) : 0.0,
            'loans_count' => $loansCount,
        ];
    }

    private function groupByProduct($loanRowsBase, array $subshopIds): array
    {
        $rows = (clone $loanRowsBase)
            ->selectRaw('loan_product_id, MAX(loan_product_name) as loan_product_name, COUNT(*) as loans_count')
            ->selectRaw('SUM(principal_outstanding) as principal_outstanding')
            ->selectRaw('SUM(interest_outstanding) as interest_outstanding')
            ->selectRaw('SUM(total_outstanding) as total_outstanding')
            ->groupBy('loan_product_id')
            ->orderByDesc('total_outstanding')
            ->get();

        return $rows->map(function ($r) {
            $pid = (int) $r->loan_product_id;

            return [
                'product_id' => $pid,
                'product' => (string) ($r->loan_product_name ?? 'Unknown'),
                'loans_count' => (int) $r->loans_count,
                'principal_outstanding' => round((float) $r->principal_outstanding, 2),
                'interest_outstanding' => round((float) $r->interest_outstanding, 2),
                'total_outstanding' => round((float) $r->total_outstanding, 2),
            ];
        })->values()->all();
    }

    private function groupByBranch($loanRowsBase, array $subshopIds): array
    {
        $rows = (clone $loanRowsBase)
            ->selectRaw('subshop_id, MAX(branch_name) as branch_name, COUNT(*) as loans_count')
            ->selectRaw('SUM(total_outstanding) as total_outstanding')
            ->groupBy('subshop_id')
            ->orderByDesc('total_outstanding')
            ->get();

        return $rows->map(function ($r) {
            $sid = (int) $r->subshop_id;

            return [
                'subshop_id' => $sid,
                'branch' => (string) ($r->branch_name ?? 'Unknown'),
                'loans_count' => (int) $r->loans_count,
                'total_outstanding' => round((float) $r->total_outstanding, 2),
            ];
        })->values()->all();
    }

    private function groupByOfficer($loanRowsBase, array $subshopIds): array
    {
        $rows = (clone $loanRowsBase)
            ->whereNotNull('officer_id')
            ->selectRaw('officer_id, MAX(officer_name) as officer_name, COUNT(*) as loans_count, SUM(total_outstanding) as total_outstanding')
            ->groupBy('officer_id')
            ->orderByDesc('total_outstanding')
            ->get();

        return $rows->map(function ($r) {
            $oid = (int) $r->officer_id;

            return [
                'officer_id' => $oid,
                'officer' => (string) ($r->officer_name ?? 'Unknown'),
                'loans_count' => (int) $r->loans_count,
                'total_outstanding' => round((float) $r->total_outstanding, 2),
            ];
        })->values()->all();
    }

    private function outstandingDistribution($loanRowsBase): array
    {
        $rows = (clone $loanRowsBase)
            ->selectRaw("CASE
                WHEN total_outstanding BETWEEN 0 AND 500000 THEN '0 - 500,000'
                WHEN total_outstanding BETWEEN 500001 AND 1000000 THEN '500,001 - 1,000,000'
                WHEN total_outstanding BETWEEN 1000001 AND 5000000 THEN '1,000,001 - 5,000,000'
                ELSE '5,000,000+' END as bucket")
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(total_outstanding) as total_outstanding')
            ->groupBy('bucket')
            ->get();

        $order = ['0 - 500,000', '500,001 - 1,000,000', '1,000,001 - 5,000,000', '5,000,000+'];
        $map = $rows->mapWithKeys(fn ($r) => [(string) $r->bucket => $r]);

        return collect($order)->map(function ($b) use ($map) {
            $r = $map[$b] ?? null;

            return [
                'range' => $b,
                'loans_count' => (int) ($r->loans_count ?? 0),
                'total_outstanding' => round((float) ($r->total_outstanding ?? 0), 2),
            ];
        })->values()->all();
    }

    private function topBorrowers($loanRowsBase): array
    {
        $rows = (clone $loanRowsBase)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as loans_count, SUM(total_outstanding) as total_outstanding')
            ->groupBy('customer_id')
            ->orderByDesc('total_outstanding')
            ->limit(10)
            ->get();

        $names = DB::table('customers')
            ->whereIn('id', $rows->pluck('customer_id')->filter()->unique()->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        return $rows->map(function ($r) use ($names) {
            $cid = (int) $r->customer_id;

            return [
                'customer_id' => $cid,
                'customer' => (string) ($names[$cid]->name ?? 'Unknown'),
                'loans_count' => (int) $r->loans_count,
                'total_outstanding' => round((float) $r->total_outstanding, 2),
            ];
        })->values()->all();
    }

    private function statusBreakdown($loanRowsBase): array
    {
        $rows = (clone $loanRowsBase)
            ->selectRaw('loan_status, COUNT(*) as loans_count, SUM(total_outstanding) as total_outstanding')
            ->groupBy('loan_status')
            ->orderByDesc('total_outstanding')
            ->get();

        return $rows->map(fn ($r) => [
            'status' => (string) $r->loan_status,
            'loans_count' => (int) $r->loans_count,
            'total_outstanding' => round((float) $r->total_outstanding, 2),
        ])->values()->all();
    }

    private function outstandingVsDisbursed(array $filters, array $subshopIds, Carbon $asAt, array $existingSummary): array
    {
        $asAtDate = $asAt->toDateString();

        $q = DB::table('loan_disbursements as ld')
            ->join('loans', 'loans.id', '=', 'ld.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereDate('ld.disbursement_date', '<=', $asAtDate);

        if (! empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (! empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (! empty($filters['loan_officer_id'])) {
            $q->where('ld.processed_by', (int) $filters['loan_officer_id']);
        }
        if (! empty($filters['customer_id'])) {
            $q->where('loans.customer_id', (int) $filters['customer_id']);
        }

        $totalDisbursed = (float) (clone $q)->sum('ld.amount');

        // Use the already-calculated summary to avoid re-querying
        $outstanding = (float) ($existingSummary['total_outstanding'] ?? 0);

        $recovered = $totalDisbursed - $outstanding;
        $recoveryRate = $totalDisbursed > 0 ? round(($recovered / $totalDisbursed) * 100, 2) : 0.0;

        return [
            'total_disbursed' => round($totalDisbursed, 2),
            'total_outstanding' => round($outstanding, 2),
            'total_recovered' => round($recovered, 2),
            'recovery_rate_pct' => $recoveryRate,
        ];
    }

    private function principalVsInterestComposition(array $kpis): array
    {
        $principal = (float) ($kpis['principal_outstanding'] ?? 0);
        $interest = (float) ($kpis['interest_outstanding'] ?? 0);
        $total = (float) ($kpis['total_outstanding'] ?? 0);

        return [
            'principal_pct' => $total > 0 ? round(($principal / $total) * 100, 2) : 0.0,
            'interest_pct' => $total > 0 ? round(($interest / $total) * 100, 2) : 0.0,
            'fees_pct' => $total > 0 ? round((((float) ($kpis['fees_outstanding'] ?? 0)) / $total) * 100, 2) : 0.0,
        ];
    }

    private function timeSnapshot(array $filters, array $subshopIds, Carbon $asAt): array
    {
        // Build month-end dates for the last 12 months
        $monthEnds = [];
        $start = (clone $asAt)->startOfMonth()->subMonths(11);
        for ($d = $start->copy(); $d->lte($asAt); $d->addMonth()) {
            $monthEnd = $d->copy()->endOfMonth();
            // Don't go beyond asAt date
            if ($monthEnd->gt($asAt)) {
                $monthEnd = $asAt->copy();
            }
            $monthEnds[] = $monthEnd;
        }

        // Get all activity (disbursements and repayments) in a single batch query
        // This helps determine which months have loan activity
        $loansBase = $this->filteredLoansQuery($filters, $subshopIds);
        $loanIds = $loansBase->pluck('loans.id');

        if ($loanIds->isEmpty()) {
            // Return empty snapshot if no loans match filters
            return collect($monthEnds)->map(fn ($dt) => [
                'date' => $dt->toDateString(),
                'total_outstanding' => 0.0,
            ])->all();
        }

        // Get monthly disbursement totals in one query
        $startDate = $monthEnds[0]->copy()->startOfMonth();
        $endDate = $asAt->copy();

        $loanIdsArray = $loanIds->toArray();

        $disbursedByMonth = DB::table('loan_disbursements as ld')
            ->whereIn('ld.loan_id', $loanIdsArray)
            ->whereBetween('ld.disbursement_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw("DATE_FORMAT(ld.disbursement_date, '%Y-%m') as ym")
            ->selectRaw('SUM(ld.amount) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        // Get monthly repayment totals in one query
        $repaidByMonth = DB::table('loan_payments as lp')
            ->join('loan_payment_allocations as lpa', 'lpa.loan_payment_id', '=', 'lp.id')
            ->whereIn('lp.loan_id', $loanIdsArray)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw("DATE_FORMAT(lp.payment_date, '%Y-%m') as ym")
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0) + COALESCE(lpa.interest_amount,0) + COALESCE(lpa.fee_amount,0)) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        // Calculate outstanding for each month-end using a single query per month
        // but optimized by reusing the loan scope
        $rows = [];
        foreach ($monthEnds as $dt) {
            $ym = $dt->format('Y-m');
            $hasActivity = !empty($disbursedByMonth[$ym]) || !empty($repaidByMonth[$ym]);

            // Always calculate outstanding for the current/final month, or months with activity
            $summaryTotal = 0.0;
            if ($hasActivity || $dt->format('Y-m') === $asAt->format('Y-m')) {
                $loanRowsBase = $this->loanOutstandingBaseQuery($loansBase, $dt);
                $summary = $this->summaryKpis($loanRowsBase, $subshopIds, $loanIds);
                $summaryTotal = (float) ($summary['total_outstanding'] ?? 0);
            }

            $rows[] = [
                'date' => $dt->toDateString(),
                'total_outstanding' => $summaryTotal,
            ];
        }

        return $rows;
    }
}
