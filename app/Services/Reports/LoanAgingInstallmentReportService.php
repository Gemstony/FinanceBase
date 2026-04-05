<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LoanAgingInstallmentReportService
{
    private readonly PortfolioRiskCalculator $portfolioRiskCalculator;

    public function __construct(PortfolioRiskCalculator $portfolioRiskCalculator)
    {
        $this->portfolioRiskCalculator = $portfolioRiskCalculator;
    }

    /**
     * @param  array{as_at_date:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,dpd_min?:int|null,dpd_max?:int|null,customer?:string|null,page?:int|null,per_page?:int|null}  $filters
     * @param  array<int>  $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $asAt = $filters['as_at_date'];
        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);

        $base = $this->installmentsBase($filters, $subshopIds, $asAt);

        $portfolioOutstanding = $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops($subshopIds);

        $outstandingMap = $this->getLoanOutstandingMap($subshopIds);

        $loanProductMap = [];
        $loanBranchMap = [];
        $loanOfficerMap = [];

        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $loanDetails = DB::table('loans')
            ->whereIn('loans.id', array_keys($outstandingMap))
            ->leftJoinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->leftJoin('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->leftJoin('users as u', 'u.id', '=', 'ld.processed_by')
            ->leftJoin('loan_products as lp', 'lp.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->select([
                'loans.id as loan_id',
                'loans.loan_product_id',
                'loans.subshop_id',
                'ld.processed_by as officer_id',
            ])
            ->get();

        foreach ($loanDetails as $ld) {
            $loanProductMap[$ld->loan_id] = $ld->loan_product_id;
            $loanBranchMap[$ld->loan_id] = $ld->subshop_id;
            $loanOfficerMap[$ld->loan_id] = $ld->officer_id;
        }

        $products = \App\Models\LoanProducts::query()->whereIn('subshop_id', $subshopIds)->get(['id', 'name'])->keyBy('id');
        $branches = \App\Models\SubShop::query()->whereIn('id', $subshopIds)->get(['id', 'name'])->keyBy('id');
        $officers = \App\Models\User::query()->whereIn('id', array_filter($loanOfficerMap))->get(['id', 'name'])->keyBy('id');

        $byProduct = $this->agingByProductFromMap($outstandingMap, $loanProductMap, $products);
        $byBranch = $this->agingByBranchFromMap($outstandingMap, $loanBranchMap, $branches);
        $byOfficer = $this->agingByOfficerFromMap($outstandingMap, $loanOfficerMap, $officers);

        $totals = DB::query()->fromSub($base, 'x')
            ->selectRaw('COUNT(*) as total_installments')
            ->selectRaw('SUM(CASE WHEN x.dpd > 0 THEN 1 ELSE 0 END) as total_overdue_installments')
            ->selectRaw('SUM(CASE WHEN x.dpd > 0 THEN x.outstanding_balance ELSE 0 END) as total_overdue_amount')
            ->selectRaw('AVG(x.dpd) as avg_dpd')
            ->selectRaw('MAX(x.dpd) as max_dpd')
            ->first();

        $summary = [
            'total_outstanding_installments' => (int) ($totals->total_installments ?? 0),
            'total_outstanding_amount' => round($portfolioOutstanding, 2),
            'total_overdue_installments' => (int) ($totals->total_overdue_installments ?? 0),
            'total_overdue_amount' => round((float) ($totals->total_overdue_amount ?? 0), 2),
            'avg_dpd' => round((float) ($totals->avg_dpd ?? 0), 2),
            'max_dpd' => (int) ($totals->max_dpd ?? 0),
        ];

        $agingBuckets = $this->agingBuckets(DB::query()->fromSub($base, 'x'), (float) ($summary['total_outstanding_amount'] ?? 0));
        $installments = $this->installmentList($filters, $subshopIds, $asAt);
        $missedByLoan = $this->missedInstallmentsByLoan(DB::query()->fromSub($base, 'x'));

        $partialPayment = $this->partialPaymentAnalysis(DB::query()->fromSub($base, 'x'));
        $highRisk = $this->highRiskInstallments(DB::query()->fromSub($base, 'x'));
        $dpdDistribution = $this->dpdDistribution(DB::query()->fromSub($base, 'x'));
        $recoverySegmentation = $this->recoverySegmentation(DB::query()->fromSub($base, 'x'));

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
     * Base dataset: one row per installment (unpaid/partial only), with DPD, bucket, officer, and allocation-issue flag.
     *
     * Uses PortfolioRiskCalculator for loan-level outstanding calculation.
     * Total outstanding sums loan-level outstanding (Expected - Paid), not installment-level.
     */
    private function installmentsBase(array $filters, array $subshopIds, Carbon $asAt): QueryBuilder
    {
        $asAtDate = $asAt->toDateString();

        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $outstandingMap = [];
        $this->portfolioRiskCalculator->activeLoansQuery()
            ->whereIn('subshop_id', $subshopIds)
            ->select(['id'])
            ->chunkById(200, function ($loans) use (&$outstandingMap) {
                foreach ($loans as $loan) {
                    $outstandingMap[$loan->id] = $this->portfolioRiskCalculator->calculateLoanOutstanding($loan);
                }
            });

        $q = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->leftJoin('customers', 'customers.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as lp', 'lp.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->leftJoinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->leftJoin('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->leftJoin('users as u', 'u.id', '=', 'ld.processed_by')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->when(! empty($filters['loan_product_id']), fn ($qq) => $qq->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when(! empty($filters['loan_status']), fn ($qq) => $qq->where('loans.status', (string) $filters['loan_status']))
            ->when(! empty($filters['loan_officer_id']), fn ($qq) => $qq->where('ld.processed_by', (int) $filters['loan_officer_id']))
            ->when(! empty($filters['customer']), function ($qq) use ($filters) {
                $search = trim((string) $filters['customer']);
                if ($search !== '') {
                    $qq->where('customers.name', 'like', '%'.$search.'%');
                }
            })
            ->selectRaw('li.id as installment_id')
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('loans.loan_code as loan_code')
            ->selectRaw('customers.id as customer_id')
            ->selectRaw('customers.name as customer')
            ->selectRaw('COALESCE(lp.name, "") as product')
            ->selectRaw('COALESCE(ss.name, "") as branch')
            ->selectRaw('COALESCE(u.name, "") as officer')
            ->selectRaw('li.installment_number as installment_number')
            ->selectRaw('li.due_date as due_date')
            ->selectRaw('li.total_due as installment_amount')
            ->selectRaw('li.amount_paid as paid_amount')
            ->selectRaw('li.outstanding_amount as outstanding_balance')
            ->selectRaw('li.status as installment_status')
            ->selectRaw('CASE WHEN li.outstanding_amount > 0 AND li.due_date < ? THEN DATEDIFF(?, li.due_date) ELSE 0 END as dpd', [$asAtDate, $asAtDate])
            ->selectRaw(
                "CASE \n".
                " WHEN (CASE WHEN li.outstanding_amount > 0 AND li.due_date < ? THEN DATEDIFF(?, li.due_date) ELSE 0 END) <= 0 THEN 'Current'\n".
                " WHEN (CASE WHEN li.outstanding_amount > 0 AND li.due_date < ? THEN DATEDIFF(?, li.due_date) ELSE 0 END) <= 30 THEN '1-30'\n".
                " WHEN (CASE WHEN li.outstanding_amount > 0 AND li.due_date < ? THEN DATEDIFF(?, li.due_date) ELSE 0 END) <= 60 THEN '31-60'\n".
                " WHEN (CASE WHEN li.outstanding_amount > 0 AND li.due_date < ? THEN DATEDIFF(?, li.due_date) ELSE 0 END) <= 90 THEN '61-90'\n".
                " ELSE '90+' END as aging_bucket",
                [$asAtDate, $asAtDate, $asAtDate, $asAtDate, $asAtDate, $asAtDate, $asAtDate, $asAtDate]
            );

        if (! empty($filters['dpd_min'])) {
            $q->having('dpd', '>=', (int) $filters['dpd_min']);
        }
        if (! empty($filters['dpd_max'])) {
            $q->having('dpd', '<=', (int) $filters['dpd_max']);
        }

        return $q;
    }

    /**
     * Get loan-level outstanding map for use in aggregations.
     */
    private function getLoanOutstandingMap(array $subshopIds): array
    {
        $outstandingMap = [];
        $this->portfolioRiskCalculator->activeLoansQuery()
            ->whereIn('subshop_id', $subshopIds)
            ->select(['id'])
            ->chunkById(200, function ($loans) use (&$outstandingMap) {
                foreach ($loans as $loan) {
                    $outstandingMap[$loan->id] = $this->portfolioRiskCalculator->calculateLoanOutstanding($loan);
                }
            });

        return $outstandingMap;
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

    private function installmentList(array $filters, array $subshopIds, Carbon $asAt): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(500, (int) ($filters['per_page'] ?? 25)));

        $base = $this->installmentsBase($filters, $subshopIds, $asAt);

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
        $rows = $base
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
        return $base
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
        $rows = $base
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
        $rows = $base
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
        $rows = $base
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

    private function agingByProductFromMap(array $outstandingMap, array $loanProductMap, $products): array
    {
        $productOutstanding = [];
        foreach ($outstandingMap as $loanId => $outstanding) {
            $productId = $loanProductMap[$loanId] ?? null;
            if ($productId) {
                $productOutstanding[$productId] = ($productOutstanding[$productId] ?? 0) + $outstanding;
            }
        }

        return collect($productOutstanding)->map(function ($amt, $pid) use ($products) {
            return [
                'product' => (string) ($products[$pid]->name ?? 'Unknown'),
                'current' => round($amt, 2),
                'd1_30' => 0.0,
                'd31_60' => 0.0,
                'd61_90' => 0.0,
                'd90p' => 0.0,
            ];
        })->values()->all();
    }

    private function agingByBranchFromMap(array $outstandingMap, array $loanBranchMap, $branches): array
    {
        $branchOutstanding = [];
        foreach ($outstandingMap as $loanId => $outstanding) {
            $branchId = $loanBranchMap[$loanId] ?? null;
            if ($branchId) {
                $branchOutstanding[$branchId] = ($branchOutstanding[$branchId] ?? 0) + $outstanding;
            }
        }

        return collect($branchOutstanding)->map(function ($amt, $sid) use ($branches) {
            return [
                'branch' => (string) ($branches[$sid]->name ?? 'Unknown'),
                'current' => round($amt, 2),
                'd1_30' => 0.0,
                'd31_60' => 0.0,
                'd61_90' => 0.0,
                'd90p' => 0.0,
            ];
        })->values()->all();
    }

    private function agingByOfficerFromMap(array $outstandingMap, array $loanOfficerMap, $officers): array
    {
        $officerOutstanding = [];
        foreach ($outstandingMap as $loanId => $outstanding) {
            $officerId = $loanOfficerMap[$loanId] ?? null;
            if ($officerId) {
                $officerOutstanding[$officerId] = ($officerOutstanding[$officerId] ?? 0) + $outstanding;
            }
        }

        return collect($officerOutstanding)->map(function ($amt, $oid) use ($officers) {
            return [
                'officer' => (string) ($officers[$oid]->name ?? 'Unknown'),
                'current' => round($amt, 2),
                'd1_30' => 0.0,
                'd31_60' => 0.0,
                'd61_90' => 0.0,
                'd90p' => 0.0,
            ];
        })->values()->all();
    }

    private function partialPaymentAnalysis(QueryBuilder $base): array
    {
        $r = $base
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
        return $base
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
        $avg = (float) ($base->avg('x.dpd') ?? 0);
        $max = (int) ($base->max('x.dpd') ?? 0);

        $dist = $base
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
        $rows = $base
            ->selectRaw(
                "CASE \n".
                " WHEN x.aging_bucket = 'Current' THEN 'Low Risk'\n".
                " WHEN x.aging_bucket = '1-30' THEN 'Medium Risk'\n".
                " WHEN x.aging_bucket IN ('31-60','61-90') THEN 'High Risk'\n".
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
