<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Loans\Risk\LoanDelinquencyEngine;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LoanAgingInstallmentReportService
{
    public function __construct(
        private readonly PortfolioRiskCalculator $portfolioRiskCalculator,
        private readonly LoanDelinquencyEngine $delinquencyEngine,
    ) {}

    /**
     * @param  array{as_at_date:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,dpd_min?:int|null,dpd_max?:int|null,customer?:string|null,page?:int|null,per_page?:int|null}  $filters
     * @param  array<int>  $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $asAt = $filters['as_at_date'];
        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);

        // Use LoanDelinquencyEngine as single source of truth for installment-level data
        $base = $this->delinquencyEngine->installmentLevelBaseQuery(
            $subshopIds,
            $asAt,
            $filters['loan_product_id'] ?? null,
            $filters['loan_officer_id'] ?? null,
            $filters['loan_status'] ?? null,
            $filters['customer'] ?? null,
            $filters['dpd_min'] ?? null,
            $filters['dpd_max'] ?? null
        );

        // Each aggregation creates its own fresh fromSub() to avoid query builder reuse issues
        $byProduct = $this->agingByProduct($base);
        $byBranch = $this->agingByBranch($base);
        $byOfficer = $this->agingByOfficer($base);

        $totals = DB::query()->fromSub($base, 'x')
            ->selectRaw('COUNT(*) as total_installments')
            ->selectRaw('SUM(CASE WHEN x.dpd > 0 THEN 1 ELSE 0 END) as total_overdue_installments')
            ->selectRaw('SUM(CASE WHEN x.dpd > 0 THEN x.outstanding_balance ELSE 0 END) as total_overdue_amount')
            ->selectRaw('SUM(x.outstanding_balance) as total_outstanding_amount')
            ->selectRaw('AVG(x.dpd) as avg_dpd')
            ->selectRaw('MAX(x.dpd) as max_dpd')
            ->first();

        $summary = [
            'total_outstanding_installments' => (int) ($totals->total_installments ?? 0),
            'total_outstanding_amount' => round((float) ($totals->total_outstanding_amount ?? 0), 2),
            'total_overdue_installments' => (int) ($totals->total_overdue_installments ?? 0),
            'total_overdue_amount' => round((float) ($totals->total_overdue_amount ?? 0), 2),
            'avg_dpd' => round((float) ($totals->avg_dpd ?? 0), 2),
            'max_dpd' => (int) ($totals->max_dpd ?? 0),
        ];

        $agingBuckets = $this->agingBuckets($base, (float) ($summary['total_outstanding_amount'] ?? 0));
        $installments = $this->installmentList($base, $filters);
        $missedByLoan = $this->missedInstallmentsByLoan($base);

        $partialPayment = $this->partialPaymentAnalysis($base);
        $highRisk = $this->highRiskInstallments($base);
        $dpdDistribution = $this->dpdDistribution($base);
        $recoverySegmentation = $this->recoverySegmentation($base);

        $loanIdsForFifo = collect(array_merge(
            $installments && method_exists($installments, 'items') ? collect($installments->items())->pluck('loan_id')->all() : [],
            collect($highRisk)->pluck('loan_id')->all()
        ))
            ->filter(fn ($v) => (int) $v > 0)
            ->unique()
            ->values()
            ->all();

        $fifoIssues = ! empty($loanIdsForFifo)
            ? $this->fifoAllocationIssues($loanIdsForFifo, $asAt, $subshopIds)
            : [];

        if ($installments && method_exists($installments, 'items')) {
            foreach ($installments->items() as $item) {
                $item->allocation_issue = isset($fifoIssues[(int) ($item->installment_id ?? 0)]) ? 1 : 0;
            }
        }

        foreach ($highRisk as $i => $row) {
            $iid = (int) ($row['installment_id'] ?? 0);
            $highRisk[$i]['allocation_issue'] = $iid && isset($fifoIssues[$iid]) ? 1 : 0;
        }

        return [
            'filters' => [
                'as_at_date' => $asAt->toDateString(),
                'subshop_ids' => $subshopIds,
                'dpd_min' => $filters['dpd_min'] ?? null,
                'dpd_max' => $filters['dpd_max'] ?? null,
                'customer' => $filters['customer'] ?? null,
            ],
            'summary' => $summary,
            'aging_buckets' => $agingBuckets,
            'installments' => $installments,
            'missed_installments' => $missedByLoan,
            'by_product' => $byProduct,
            'by_branch' => $byBranch,
            'by_officer' => $byOfficer,
            'partial_payment' => $partialPayment,
            'high_risk_installments' => $highRisk,
            'dpd_distribution' => $dpdDistribution,
            'recovery_segmentation' => $recoverySegmentation,
        ];
    }

    /** @return array<int> */
    private function resolveSubshopFilter(?int $subshopId, array $accessibleSubshopIds): array
    {
        if ($subshopId) {
            return in_array($subshopId, $accessibleSubshopIds, true) ? [$subshopId] : [];
        }

        return $accessibleSubshopIds;
    }

    /**
     * Strict audit-grade FIFO allocation validation.
     *
     * For each loan, we replay confirmed payments (<= as-at date) in chronological order and
     * verify that allocations never target a later installment while an earlier installment
     * still has remaining due at that moment.
     *
     * @param  array<int>  $loanIds
     * @return array<int, true> installment_id => true
     */
    private function fifoAllocationIssues(array $loanIds, Carbon $asAt, array $subshopIds): array
    {
        $asAtDate = $asAt->toDateString();
        $issues = [];

        $loanIds = array_values(array_unique(array_map('intval', $loanIds)));
        if (empty($loanIds)) {
            return $issues;
        }

        foreach (array_chunk($loanIds, 100) as $chunk) {
            $installments = DB::table('loan_installments as li')
                ->join('loans', 'loans.id', '=', 'li.loan_id')
                ->whereIn('loans.subshop_id', $subshopIds)
                ->whereIn('li.loan_id', $chunk)
                ->where('li.is_active', true)
                ->where('li.status', '!=', 'written_off')
                ->orderBy('li.loan_id')
                ->orderBy('li.due_date')
                ->orderBy('li.installment_number')
                ->get([
                    'li.id as installment_id',
                    'li.loan_id as loan_id',
                    'li.due_date as due_date',
                    'li.installment_number as installment_number',
                    'li.total_due as total_due',
                ]);

            $byLoan = [];
            foreach ($installments as $r) {
                $loanId = (int) $r->loan_id;
                $byLoan[$loanId] ??= [];
                $byLoan[$loanId][] = $r;
            }

            if (empty($byLoan)) {
                continue;
            }

            $payments = DB::table('loan_payments as lp')
                ->whereIn('lp.loan_id', array_keys($byLoan))
                ->where('lp.status', 'confirmed')
                ->whereDate('lp.payment_date', '<=', $asAtDate)
                ->orderBy('lp.payment_date')
                ->orderBy('lp.id')
                ->get(['lp.id', 'lp.loan_id', 'lp.payment_date']);

            if ($payments->isEmpty()) {
                continue;
            }

            $paymentIds = $payments->pluck('id')->map(fn ($v) => (int) $v)->all();
            $allocations = DB::table('loan_payment_allocations as a')
                ->whereIn('a.loan_payment_id', $paymentIds)
                ->selectRaw('a.loan_payment_id as payment_id')
                ->selectRaw('a.loan_installment_id as installment_id')
                ->selectRaw('ROUND(COALESCE(a.principal_amount,0)+COALESCE(a.interest_amount,0)+COALESCE(a.fee_amount,0)+COALESCE(a.penalty_amount,0),2) as total')
                ->get();

            $allocByPayment = [];
            foreach ($allocations as $a) {
                $pid = (int) $a->payment_id;
                $iid = (int) $a->installment_id;
                $allocByPayment[$pid] ??= [];
                $allocByPayment[$pid][$iid] = ($allocByPayment[$pid][$iid] ?? 0.0) + (float) ($a->total ?? 0.0);
            }

            foreach ($byLoan as $loanId => $rows) {
                $indexByInstallment = [];
                $remaining = [];
                foreach (array_values($rows) as $idx => $ins) {
                    $iid = (int) $ins->installment_id;
                    $indexByInstallment[$iid] = $idx;
                    $remaining[$iid] = round((float) ($ins->total_due ?? 0), 2);
                }

                $loanPayments = $payments->where('loan_id', (int) $loanId);
                if ($loanPayments->isEmpty()) {
                    continue;
                }

                foreach ($loanPayments as $p) {
                    $pid = (int) $p->id;
                    $allocMap = $allocByPayment[$pid] ?? [];
                    if (empty($allocMap)) {
                        continue;
                    }

                    $earliestUnpaidIndex = null;
                    foreach ($rows as $idx => $ins) {
                        $iid = (int) $ins->installment_id;
                        if (($remaining[$iid] ?? 0.0) > 0.0) {
                            $earliestUnpaidIndex = $idx;
                            break;
                        }
                    }
                    if ($earliestUnpaidIndex === null) {
                        break;
                    }

                    $allocatedIndices = [];
                    foreach ($allocMap as $iid => $amt) {
                        if ($amt <= 0) {
                            continue;
                        }
                        if (! isset($indexByInstallment[(int) $iid])) {
                            continue;
                        }
                        $allocatedIndices[(int) $iid] = $indexByInstallment[(int) $iid];
                    }
                    if (empty($allocatedIndices)) {
                        continue;
                    }

                    $minAllocatedIndex = min($allocatedIndices);
                    if ($minAllocatedIndex > $earliestUnpaidIndex) {
                        foreach ($allocatedIndices as $iid => $idx) {
                            if ($idx === $minAllocatedIndex) {
                                $issues[(int) $iid] = true;
                            }
                        }
                    }

                    $earliestInsId = (int) ($rows[$earliestUnpaidIndex]->installment_id ?? 0);
                    $earliestRemainingBefore = (float) ($remaining[$earliestInsId] ?? 0.0);
                    $earliestAllocated = (float) ($allocMap[$earliestInsId] ?? 0.0);
                    $earliestSettledByThisPayment = $earliestAllocated >= $earliestRemainingBefore && $earliestRemainingBefore > 0;

                    if (! $earliestSettledByThisPayment) {
                        foreach ($allocatedIndices as $iid => $idx) {
                            if ($idx > $earliestUnpaidIndex) {
                                $issues[(int) $iid] = true;
                            }
                        }
                    }

                    $prev = null;
                    foreach ($allocatedIndices as $iid => $idx) {
                        if ($prev !== null && $idx < $prev) {
                            $issues[(int) $iid] = true;
                        }
                        $prev = $idx;
                    }

                    foreach ($allocMap as $iid => $amt) {
                        $iid = (int) $iid;
                        if (! array_key_exists($iid, $remaining)) {
                            continue;
                        }
                        if ($amt <= 0) {
                            continue;
                        }
                        $remaining[$iid] = round(max(0.0, (float) $remaining[$iid] - (float) $amt), 2);
                    }
                }
            }
        }

        return $issues;
    }

    private function installmentList(QueryBuilder $base, array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(500, (int) ($filters['per_page'] ?? 25)));

        $total = (int) DB::query()->fromSub($base, 'x')->count();

        $items = DB::query()->fromSub($base, 'x')
            ->orderByDesc('x.dpd')
            ->orderBy('x.due_date')
            ->forPage($page, $perPage)
            ->get();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function agingBuckets(QueryBuilder $base, float $totalOutstandingAmount): array
    {
        $rows = DB::query()->fromSub($base, 'x')
            ->selectRaw('x.aging_bucket as bucket')
            ->selectRaw('COUNT(*) as installments')
            ->selectRaw('SUM(x.outstanding_balance) as outstanding')
            ->groupBy('x.aging_bucket')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r->bucket] = [
                'bucket' => (string) $r->bucket,
                'installments' => (int) ($r->installments ?? 0),
                'outstanding' => round((float) ($r->outstanding ?? 0), 2),
                'pct' => $totalOutstandingAmount > 0 ? round(((float) ($r->outstanding ?? 0) / $totalOutstandingAmount) * 100, 2) : 0.0,
            ];
        }

        $order = ['Current', '1-30', '31-60', '61-90', '90+'];
        $out = [];
        foreach ($order as $b) {
            $out[] = $map[$b] ?? ['bucket' => $b, 'installments' => 0, 'outstanding' => 0.0, 'pct' => 0.0];
        }

        return $out;
    }

    private function missedInstallmentsByLoan(QueryBuilder $base): array
    {
        return DB::query()->fromSub($base, 'x')
            ->where('x.dpd', '>', 0)
            ->selectRaw('x.loan_code as loan_code')
            ->selectRaw('x.customer as customer')
            ->selectRaw('COUNT(*) as missed_installments')
            ->selectRaw('SUM(x.outstanding_balance) as overdue_amount')
            ->groupBy('x.loan_code', 'x.customer')
            ->orderByDesc('overdue_amount')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'loan_code' => (string) ($r->loan_code ?? ''),
                'customer' => (string) ($r->customer ?? ''),
                'missed_installments' => (int) ($r->missed_installments ?? 0),
                'overdue_amount' => round((float) ($r->overdue_amount ?? 0), 2),
            ])
            ->all();
    }

    private function agingByProduct(QueryBuilder $base): array
    {
        $rows = DB::query()->fromSub($base, 'x')
            ->selectRaw('x.product as product')
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = 'Current' THEN x.outstanding_balance ELSE 0 END) as current")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '1-30' THEN x.outstanding_balance ELSE 0 END) as d1_30")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '31-60' THEN x.outstanding_balance ELSE 0 END) as d31_60")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '61-90' THEN x.outstanding_balance ELSE 0 END) as d61_90")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '90+' THEN x.outstanding_balance ELSE 0 END) as d90p")
            ->groupBy('x.product')
            ->orderBy('x.product')
            ->get();

        return $rows->map(fn ($r) => [
            'product' => (string) ($r->product ?? ''),
            'current' => round((float) ($r->current ?? 0), 2),
            'd1_30' => round((float) ($r->d1_30 ?? 0), 2),
            'd31_60' => round((float) ($r->d31_60 ?? 0), 2),
            'd61_90' => round((float) ($r->d61_90 ?? 0), 2),
            'd90p' => round((float) ($r->d90p ?? 0), 2),
        ])->all();
    }

    private function agingByBranch(QueryBuilder $base): array
    {
        $rows = DB::query()->fromSub($base, 'x')
            ->selectRaw('x.branch as branch')
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = 'Current' THEN x.outstanding_balance ELSE 0 END) as current")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '1-30' THEN x.outstanding_balance ELSE 0 END) as d1_30")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '31-60' THEN x.outstanding_balance ELSE 0 END) as d31_60")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '61-90' THEN x.outstanding_balance ELSE 0 END) as d61_90")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '90+' THEN x.outstanding_balance ELSE 0 END) as d90p")
            ->groupBy('x.branch')
            ->orderBy('x.branch')
            ->get();

        return $rows->map(fn ($r) => [
            'branch' => (string) ($r->branch ?? ''),
            'current' => round((float) ($r->current ?? 0), 2),
            'd1_30' => round((float) ($r->d1_30 ?? 0), 2),
            'd31_60' => round((float) ($r->d31_60 ?? 0), 2),
            'd61_90' => round((float) ($r->d61_90 ?? 0), 2),
            'd90p' => round((float) ($r->d90p ?? 0), 2),
        ])->all();
    }

    private function agingByOfficer(QueryBuilder $base): array
    {
        $rows = DB::query()->fromSub($base, 'x')
            ->selectRaw('x.officer as officer')
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = 'Current' THEN x.outstanding_balance ELSE 0 END) as current")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '1-30' THEN x.outstanding_balance ELSE 0 END) as d1_30")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '31-60' THEN x.outstanding_balance ELSE 0 END) as d31_60")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '61-90' THEN x.outstanding_balance ELSE 0 END) as d61_90")
            ->selectRaw("SUM(CASE WHEN x.aging_bucket = '90+' THEN x.outstanding_balance ELSE 0 END) as d90p")
            ->groupBy('x.officer')
            ->orderBy('x.officer')
            ->get();

        return $rows->map(fn ($r) => [
            'officer' => (string) ($r->officer ?? ''),
            'current' => round((float) ($r->current ?? 0), 2),
            'd1_30' => round((float) ($r->d1_30 ?? 0), 2),
            'd31_60' => round((float) ($r->d31_60 ?? 0), 2),
            'd61_90' => round((float) ($r->d61_90 ?? 0), 2),
            'd90p' => round((float) ($r->d90p ?? 0), 2),
        ])->all();
    }

    private function partialPaymentAnalysis(QueryBuilder $base): array
    {
        $r = DB::query()->fromSub($base, 'x')
            ->where('x.paid_amount', '>', 0)
            ->selectRaw('COUNT(*) as partial_installments')
            ->selectRaw('SUM(x.paid_amount) as total_partial_paid_amount')
            ->selectRaw('SUM(x.outstanding_balance) as total_partial_outstanding_amount')
            ->first();

        return [
            'partial_installments' => (int) ($r->partial_installments ?? 0),
            'total_partial_paid_amount' => round((float) ($r->total_partial_paid_amount ?? 0), 2),
            'total_partial_outstanding_amount' => round((float) ($r->total_partial_outstanding_amount ?? 0), 2),
        ];
    }

    private function highRiskInstallments(QueryBuilder $base): array
    {
        return DB::query()->fromSub($base, 'x')
            ->where('x.dpd', '>=', 60)
            ->orderByDesc('x.outstanding_balance')
            ->limit(10)
            ->get([
                'x.loan_id',
                'x.installment_id',
                'x.loan_code',
                'x.customer',
                'x.installment_number',
                'x.dpd',
                'x.outstanding_balance',
            ])
            ->map(fn ($r) => [
                'loan_id' => (int) ($r->loan_id ?? 0),
                'installment_id' => (int) ($r->installment_id ?? 0),
                'loan_code' => (string) ($r->loan_code ?? ''),
                'customer' => (string) ($r->customer ?? ''),
                'installment_number' => (int) ($r->installment_number ?? 0),
                'dpd' => (int) ($r->dpd ?? 0),
                'outstanding_balance' => round((float) ($r->outstanding_balance ?? 0), 2),
            ])
            ->all();
    }

    private function dpdDistribution(QueryBuilder $base): array
    {
        $avg = (float) (DB::query()->fromSub($base, 'x')->avg('x.dpd') ?? 0);
        $max = (int) (DB::query()->fromSub($base, 'x')->max('x.dpd') ?? 0);

        $dist = DB::query()->fromSub($base, 'x')
            ->selectRaw('x.aging_bucket as bucket')
            ->selectRaw('COUNT(*) as installments')
            ->groupBy('x.aging_bucket')
            ->get()
            ->map(fn ($r) => [
                'bucket' => (string) ($r->bucket ?? ''),
                'installments' => (int) ($r->installments ?? 0),
            ])
            ->all();

        return [
            'avg_dpd' => round($avg, 2),
            'max_dpd' => $max,
            'distribution' => $dist,
        ];
    }

    private function recoverySegmentation(QueryBuilder $base): array
    {
        $rows = DB::query()->fromSub($base, 'x')
            ->selectRaw(
                "CASE 
".
                " WHEN x.aging_bucket = 'Current' THEN 'Low Risk'
".
                " WHEN x.aging_bucket = '1-30' THEN 'Medium Risk'
".
                " WHEN x.aging_bucket IN ('31-60','61-90') THEN 'High Risk'
".
                " ELSE 'Critical Risk' END as risk"
            )
            ->selectRaw('COUNT(*) as installments')
            ->selectRaw('SUM(x.outstanding_balance) as outstanding')
            ->groupBy('risk')
            ->orderByRaw("FIELD(risk, 'Low Risk', 'Medium Risk', 'High Risk', 'Critical Risk')")
            ->get();

        return $rows->map(fn ($r) => [
            'risk' => (string) ($r->risk ?? ''),
            'installments' => (int) ($r->installments ?? 0),
            'outstanding' => round((float) ($r->outstanding ?? 0), 2),
        ])->all();
    }
}
