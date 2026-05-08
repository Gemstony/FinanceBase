<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Loans\Risk\LoanDelinquencyEngine;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ExpectedVsCollectedReportService
{
    public function __construct(
        private readonly LoanDelinquencyEngine $delinquencyEngine,
    ) {
    }
    /**
     * @param array{start_date:Carbon,end_date:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,customer_id?:int|null,group_by?:string|null,per_page?:int|null,page?:int|null,installments_page?:int|null,loan_id?:int|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $start = $filters['start_date'];
        $end = $filters['end_date'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $loanIds = $this->filteredLoanIds($filters, $subshopIds);

        $summary = $this->summaryKpis($subshopIds, $start, $end, $loanIds);
        $period = $this->periodBreakdown($filters, $subshopIds, $start, $end, $loanIds);

        $loanLevel = $this->loanLevelTable($filters, $subshopIds, $start, $end, $loanIds);
        $installmentLevel = $this->installmentLevelTable($filters, $subshopIds, $start, $end, $loanIds);

        $byProduct = $this->byProduct($subshopIds, $start, $end, $loanIds);
        $byBranch = $this->byBranch($subshopIds, $start, $end, $loanIds);
        $byOfficer = $this->byOfficer($subshopIds, $start, $end, $loanIds);

        $topUnder = $this->topAndUnderperformingLoans($subshopIds, $start, $end, $loanIds);
        $missed = $this->missedCollections($subshopIds, $start, $end, $loanIds);
        $partial = $this->partialPayments($subshopIds, $start, $end, $loanIds);

        $expected = (float) ($summary['total_expected'] ?? 0);
        $collected = (float) ($summary['total_collected'] ?? 0);
        $variance = round($expected - $collected, 2);
        $rate = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0.0;

        return [
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'subshop_ids' => $subshopIds,
                'group_by' => $period['group_by'] ?? 'auto',
            ],
            'summary' => $summary,
            'totals' => [
                'expected' => round($expected, 2),
                'collected' => round($collected, 2),
                'variance' => $variance,
                'collection_rate_pct' => $rate,
            ],
            'period_breakdown' => $period,
            'loan_level' => $loanLevel,
            'installment_level' => $installmentLevel,
            'by_product' => $byProduct,
            'by_branch' => $byBranch,
            'by_officer' => $byOfficer,
            'top_underperforming' => $topUnder,
            'missed_collections' => $missed,
            'partial_payments' => $partial,
            'arrears_contribution' => [
                'shortfall' => max(0, $variance),
                'shortfall_pct_of_expected' => $expected > 0 ? round((max(0, $variance) / $expected) * 100, 2) : 0.0,
            ],
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

    /**
     * @param array{loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,customer_id?:int|null,loan_id?:int|null} $filters
     */
    private function filteredLoanIds(array $filters, array $subshopIds)
    {
        // Active portfolio loan filter - consistent with LoanDelinquencyEngine logic
        // This ensures same loan scope as other reports (Arrears, Outstanding, etc.)
        $q = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->when(!empty($filters['loan_id']), fn ($qq) => $qq->where('loans.id', (int) $filters['loan_id']))
            ->when(!empty($filters['loan_product_id']), fn ($qq) => $qq->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when(!empty($filters['loan_status']), fn ($qq) => $qq->where('loans.status', (string) $filters['loan_status']))
            ->when(!empty($filters['customer_id']), fn ($qq) => $qq->where('loans.customer_id', (int) $filters['customer_id']));

        if (!empty($filters['loan_officer_id'])) {
            $latestDisb = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
                ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', (int) $filters['loan_officer_id']);
        }

        return $q->distinct()->pluck('loans.id');
    }

    private function summaryKpis(array $subshopIds, Carbon $start, Carbon $end, $loanIds): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $expectedQ = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        $totalExpected = (float) (clone $expectedQ)->sum('li.total_due');
        $totalDueInstallments = (int) (clone $expectedQ)->count('li.id');

        // Use payment allocations for consistency with detail tables (installmentLevelTable, etc.)
        // This ensures we only count payments actually applied to installments within the period
        $totalCollected = (float) DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->sum(DB::raw('COALESCE(lpa.principal_amount,0) + COALESCE(lpa.interest_amount,0) + COALESCE(lpa.fee_amount,0) + COALESCE(lpa.penalty_amount,0)'));

        $alloc = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        $allocByInstallment = DB::query()
            ->fromSub(
                (clone $alloc)
                    ->selectRaw('li.id as installment_id')
                    ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) as allocated')
                    ->groupBy('li.id'),
                'a'
            );

        $totalPaidInstallments = (int) DB::query()->fromSub($allocByInstallment, 'x')->where('x.allocated', '>=', DB::raw('0.000001'))->count('*');

        $variance = $totalExpected - $totalCollected;
        $collectionRate = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 2) : 0.0;

        return [
            'total_expected' => round($totalExpected, 2),
            'total_collected' => round($totalCollected, 2),
            'total_variance' => round($variance, 2),
            'collection_rate_pct' => $collectionRate,
            'total_due_installments' => $totalDueInstallments,
            'total_paid_installments' => $totalPaidInstallments,
        ];
    }

    private function resolveGroupBy(array $filters, Carbon $start, Carbon $end): string
    {
        $gb = strtolower((string) ($filters['group_by'] ?? 'auto'));
        if (in_array($gb, ['daily', 'weekly', 'monthly'], true)) {
            return $gb;
        }

        $days = $start->diffInDays($end) + 1;
        if ($days <= 31) {
            return 'daily';
        }
        if ($days <= 120) {
            return 'weekly';
        }
        return 'monthly';
    }

    private function periodBreakdown(array $filters, array $subshopIds, Carbon $start, Carbon $end, $loanIds): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $groupBy = $this->resolveGroupBy($filters, $start, $end);

        $expectedBase = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        // Use payment allocations for collected amounts (consistent with summaryKpis)
        // Base query without selects - selects added per group type to avoid GROUP BY conflicts
        $collectedBase = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        if ($groupBy === 'daily') {
            $expectedRows = (clone $expectedBase)
                ->selectRaw('DATE(li.due_date) as period')
                ->selectRaw('SUM(li.total_due) as expected')
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            $collectedRows = (clone $collectedBase)
                ->selectRaw('DATE(lp.payment_date) as period')
                ->selectRaw('SUM(COALESCE(lpa.principal_amount,0) + COALESCE(lpa.interest_amount,0) + COALESCE(lpa.fee_amount,0) + COALESCE(lpa.penalty_amount,0)) as collected')
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            $periods = [];
            for ($d = $start->copy()->startOfDay(); $d <= $end->copy()->startOfDay(); $d->addDay()) {
                $periods[] = $d->toDateString();
            }
        } elseif ($groupBy === 'weekly') {
            $expectedRows = (clone $expectedBase)
                ->selectRaw("DATE_FORMAT(li.due_date, '%x-W%v') as period")
                ->selectRaw('MIN(DATE(li.due_date)) as period_start')
                ->selectRaw('SUM(li.total_due) as expected')
                ->groupBy('period')
                ->orderBy('period_start')
                ->get();

            $collectedRows = (clone $collectedBase)
                ->selectRaw("DATE_FORMAT(lp.payment_date, '%x-W%v') as period")
                ->selectRaw('MIN(DATE(lp.payment_date)) as period_start')
                ->selectRaw('SUM(COALESCE(lpa.principal_amount,0) + COALESCE(lpa.interest_amount,0) + COALESCE(lpa.fee_amount,0) + COALESCE(lpa.penalty_amount,0)) as collected')
                ->groupBy('period')
                ->orderBy('period_start')
                ->get();

            $periods = array_values(array_unique(array_merge(
                $expectedRows->pluck('period')->all(),
                $collectedRows->pluck('period')->all()
            )));
        } else {
            $expectedRows = (clone $expectedBase)
                ->selectRaw("DATE_FORMAT(li.due_date, '%Y-%m') as period")
                ->selectRaw('SUM(li.total_due) as expected')
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            $collectedRows = (clone $collectedBase)
                ->selectRaw("DATE_FORMAT(lp.payment_date, '%Y-%m') as period")
                ->selectRaw('SUM(COALESCE(lpa.principal_amount,0) + COALESCE(lpa.interest_amount,0) + COALESCE(lpa.fee_amount,0) + COALESCE(lpa.penalty_amount,0)) as collected')
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            $periods = [];
            $cursor = $start->copy()->startOfMonth();
            $endMonth = $end->copy()->startOfMonth();
            while ($cursor <= $endMonth) {
                $periods[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }
        }

        $expMap = $expectedRows->keyBy('period');
        $colMap = $collectedRows->keyBy('period');

        $rows = [];
        foreach ($periods as $p) {
            $expected = (float) (($expMap[$p]->expected ?? 0) ?? 0);
            $collected = (float) (($colMap[$p]->collected ?? 0) ?? 0);
            $variance = $expected - $collected;
            $rate = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0.0;
            $rows[] = [
                'period' => (string) $p,
                'expected' => round($expected, 2),
                'collected' => round($collected, 2),
                'variance' => round($variance, 2),
                'collection_rate_pct' => $rate,
            ];
        }

        return [
            'group_by' => $groupBy,
            'rows' => $rows,
            'chart' => [
                'labels' => array_column($rows, 'period'),
                'expected' => array_column($rows, 'expected'),
                'collected' => array_column($rows, 'collected'),
                'collection_rate_pct' => array_column($rows, 'collection_rate_pct'),
            ],
        ];
    }

    private function loanLevelTable(array $filters, array $subshopIds, Carbon $start, Carbon $end, $loanIds): LengthAwarePaginator
    {
        $perPage = !empty($filters['per_page']) ? max(5, min(200, (int) $filters['per_page'])) : 25;
        $page = !empty($filters['page']) ? max(1, (int) $filters['page']) : 1;

        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $expectedAgg = DB::table('loan_installments as li')
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.total_due) as expected')
            ->groupBy('li.loan_id');

        $collectedAgg = DB::table('loan_payments as lp')
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->selectRaw('lp.loan_id as loan_id')
            ->selectRaw('SUM(lp.amount) as collected')
            ->groupBy('lp.loan_id');

        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $officerMap = DB::table('loan_disbursements as ld')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.id', '=', 'ld.id'))
            ->selectRaw('ld.loan_id as loan_id, ld.processed_by as officer_id');

        $q = DB::table('loans')
            ->joinSub($expectedAgg, 'e', fn ($j) => $j->on('e.loan_id', '=', 'loans.id'))
            ->leftJoinSub($collectedAgg, 'c2', fn ($j) => $j->on('c2.loan_id', '=', 'loans.id'))
            ->leftJoin('customers as cu', 'cu.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as p', 'p.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->leftJoinSub($officerMap, 'om', fn ($j) => $j->on('om.loan_id', '=', 'loans.id'))
            ->leftJoin('users as u', 'u.id', '=', 'om.officer_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'cu.id as customer_id',
                'cu.name as customer',
                'p.name as product',
                'ss.name as branch',
                'u.name as officer',
                'loans.status as loan_status',
                DB::raw('COALESCE(e.expected,0) as expected'),
                DB::raw('COALESCE(c2.collected,0) as collected'),
                DB::raw('(COALESCE(e.expected,0) - COALESCE(c2.collected,0)) as variance'),
            ])
            ->orderByDesc('variance');

        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($r) {
            $expected = (float) ($r->expected ?? 0);
            $collected = (float) ($r->collected ?? 0);
            $r->collection_rate_pct = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0.0;
            return $r;
        });

        return $paginator;
    }

    private function installmentLevelTable(array $filters, array $subshopIds, Carbon $start, Carbon $end, $loanIds): LengthAwarePaginator
    {
        $perPage = !empty($filters['per_page']) ? max(5, min(200, (int) $filters['per_page'])) : 25;
        $page = !empty($filters['installments_page']) ? max(1, (int) $filters['installments_page']) : 1;

        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $allocAgg = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->selectRaw('lpa.loan_installment_id as installment_id')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) as collected')
            ->groupBy('installment_id');

        $q = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->leftJoinSub($allocAgg, 'a', fn ($j) => $j->on('a.installment_id', '=', 'li.id'))
            ->leftJoin('customers as cu', 'cu.id', '=', 'loans.customer_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('li.loan_id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->select([
                'li.loan_id as loan_id',
                'loans.loan_code as loan_code',
                'cu.id as customer_id',
                'cu.name as customer',
                'li.installment_number as installment_number',
                'li.due_date as due_date',
            ])
            ->selectRaw('li.total_due as expected')
            ->selectRaw('COALESCE(a.collected,0) as collected')
            ->selectRaw('(li.total_due - COALESCE(a.collected,0)) as variance')
            ->orderBy('li.due_date')
            ->orderBy('li.installment_number');

        $paginator = $q->paginate($perPage, ['*'], 'installments_page', $page);

        $paginator->getCollection()->transform(function ($r) {
            $expected = (float) ($r->expected ?? 0);
            $collected = (float) ($r->collected ?? 0);
            if ($collected >= $expected && $expected > 0) {
                $r->status = 'Paid';
            } elseif ($collected > 0 && $collected < $expected) {
                $r->status = 'Partial';
            } else {
                $r->status = 'Unpaid';
            }
            return $r;
        });

        return $paginator;
    }

    private function baseLoanAgg(array $subshopIds, Carbon $start, Carbon $end, $loanIds): QueryBuilder
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $expectedAgg = DB::table('loan_installments as li')
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.total_due) as expected')
            ->groupBy('li.loan_id');

        $collectedAgg = DB::table('loan_payments as lp')
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->selectRaw('lp.loan_id as loan_id')
            ->selectRaw('SUM(lp.amount) as collected')
            ->groupBy('lp.loan_id');

        return DB::table('loans')
            ->joinSub($expectedAgg, 'e', fn ($j) => $j->on('e.loan_id', '=', 'loans.id'))
            ->leftJoinSub($collectedAgg, 'c', fn ($j) => $j->on('c.loan_id', '=', 'loans.id'))
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->selectRaw('loans.id as loan_id')
            ->selectRaw('loans.loan_product_id as loan_product_id')
            ->selectRaw('loans.subshop_id as subshop_id')
            ->selectRaw('COALESCE(e.expected,0) as expected')
            ->selectRaw('COALESCE(c.collected,0) as collected');
    }

    private function byProduct(array $subshopIds, Carbon $start, Carbon $end, $loanIds): array
    {
        $rows = DB::query()->fromSub($this->baseLoanAgg($subshopIds, $start, $end, $loanIds), 'x')
            ->selectRaw('x.loan_product_id as loan_product_id')
            ->selectRaw('SUM(x.expected) as expected')
            ->selectRaw('SUM(x.collected) as collected')
            ->groupBy('x.loan_product_id')
            ->get();

        $products = DB::table('loan_products')->whereIn('id', $rows->pluck('loan_product_id')->filter()->values())->get(['id', 'name'])->keyBy('id');

        return $rows->map(function ($r) use ($products) {
            $expected = (float) ($r->expected ?? 0);
            $collected = (float) ($r->collected ?? 0);
            $rate = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0.0;
            return [
                'product_id' => (int) ($r->loan_product_id ?? 0),
                'product' => (string) ($products[$r->loan_product_id]->name ?? 'Unknown'),
                'expected' => round($expected, 2),
                'collected' => round($collected, 2),
                'variance' => round($expected - $collected, 2),
                'collection_rate_pct' => $rate,
            ];
        })->values()->all();
    }

    private function byBranch(array $subshopIds, Carbon $start, Carbon $end, $loanIds): array
    {
        $rows = DB::query()->fromSub($this->baseLoanAgg($subshopIds, $start, $end, $loanIds), 'x')
            ->selectRaw('x.subshop_id as subshop_id')
            ->selectRaw('SUM(x.expected) as expected')
            ->selectRaw('SUM(x.collected) as collected')
            ->groupBy('x.subshop_id')
            ->get();

        $branches = DB::table('sub_shops')->whereIn('id', $rows->pluck('subshop_id')->filter()->values())->get(['id', 'name'])->keyBy('id');

        return $rows->map(function ($r) use ($branches) {
            $expected = (float) ($r->expected ?? 0);
            $collected = (float) ($r->collected ?? 0);
            $rate = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0.0;
            return [
                'subshop_id' => (int) ($r->subshop_id ?? 0),
                'branch' => (string) ($branches[$r->subshop_id]->name ?? 'Unknown'),
                'expected' => round($expected, 2),
                'collected' => round($collected, 2),
                'variance' => round($expected - $collected, 2),
                'collection_rate_pct' => $rate,
            ];
        })->values()->all();
    }

    private function byOfficer(array $subshopIds, Carbon $start, Carbon $end, $loanIds): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $expectedAgg = DB::table('loan_installments as li')
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.total_due) as expected')
            ->groupBy('li.loan_id');

        $collectedAgg = DB::table('loan_payments as lp')
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->selectRaw('lp.loan_id as loan_id')
            ->selectRaw('SUM(lp.amount) as collected')
            ->groupBy('lp.loan_id');

        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $officerMap = DB::table('loan_disbursements as ld')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.id', '=', 'ld.id'))
            ->selectRaw('ld.loan_id as loan_id, ld.processed_by as officer_id');

        $rows = DB::table('loans')
            ->joinSub($expectedAgg, 'e', fn ($j) => $j->on('e.loan_id', '=', 'loans.id'))
            ->leftJoinSub($collectedAgg, 'c', fn ($j) => $j->on('c.loan_id', '=', 'loans.id'))
            ->leftJoinSub($officerMap, 'om', fn ($j) => $j->on('om.loan_id', '=', 'loans.id'))
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->selectRaw('om.officer_id as officer_id')
            ->selectRaw('SUM(COALESCE(e.expected,0)) as expected')
            ->selectRaw('SUM(COALESCE(c.collected,0)) as collected')
            ->groupBy('om.officer_id')
            ->get();

        $officers = DB::table('users')->whereIn('id', $rows->pluck('officer_id')->filter()->values())->get(['id', 'name'])->keyBy('id');

        return $rows->map(function ($r) use ($officers) {
            $expected = (float) ($r->expected ?? 0);
            $collected = (float) ($r->collected ?? 0);
            $rate = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0.0;
            return [
                'officer_id' => (int) ($r->officer_id ?? 0),
                'officer' => (string) ($officers[$r->officer_id]->name ?? 'Unassigned'),
                'expected' => round($expected, 2),
                'collected' => round($collected, 2),
                'variance' => round($expected - $collected, 2),
                'collection_rate_pct' => $rate,
            ];
        })->values()->all();
    }

    private function topAndUnderperformingLoans(array $subshopIds, Carbon $start, Carbon $end, $loanIds): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $expectedAgg = DB::table('loan_installments as li')
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.total_due) as expected')
            ->groupBy('li.loan_id');

        $collectedAgg = DB::table('loan_payments as lp')
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->selectRaw('lp.loan_id as loan_id')
            ->selectRaw('SUM(lp.amount) as collected')
            ->groupBy('lp.loan_id');

        $base = DB::table('loans')
            ->joinSub($expectedAgg, 'e', fn ($j) => $j->on('e.loan_id', '=', 'loans.id'))
            ->leftJoinSub($collectedAgg, 'c', fn ($j) => $j->on('c.loan_id', '=', 'loans.id'))
            ->leftJoin('customers as cu', 'cu.id', '=', 'loans.customer_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->selectRaw('loans.id as loan_id')
            ->selectRaw('loans.loan_code as loan_code')
            ->selectRaw('cu.name as customer')
            ->selectRaw('COALESCE(e.expected,0) as expected')
            ->selectRaw('COALESCE(c.collected,0) as collected')
            ->selectRaw('CASE WHEN COALESCE(e.expected,0) > 0 THEN ROUND((COALESCE(c.collected,0) / COALESCE(e.expected,0)) * 100, 2) ELSE 0 END as collection_rate_pct');

        $best = (clone $base)
            ->orderByDesc('collection_rate_pct')
            ->orderByDesc('expected')
            ->limit(10)
            ->get();

        $worst = (clone $base)
            ->orderBy('collection_rate_pct')
            ->orderByDesc('expected')
            ->limit(10)
            ->get();

        $map = fn ($rows) => $rows->map(fn ($r) => [
            'loan_id' => (int) ($r->loan_id ?? 0),
            'loan_code' => (string) ($r->loan_code ?? ''),
            'customer' => (string) ($r->customer ?? ''),
            'expected' => round((float) ($r->expected ?? 0), 2),
            'collected' => round((float) ($r->collected ?? 0), 2),
            'collection_rate_pct' => (float) ($r->collection_rate_pct ?? 0),
        ])->values()->all();

        return [
            'top' => $map($best),
            'underperforming' => $map($worst),
        ];
    }

    private function missedCollections(array $subshopIds, Carbon $start, Carbon $end, $loanIds): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $allocAgg = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->selectRaw('lpa.loan_installment_id as installment_id')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) as collected')
            ->groupBy('installment_id');

        $rows = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->leftJoinSub($allocAgg, 'a', fn ($j) => $j->on('a.installment_id', '=', 'li.id'))
            ->leftJoin('customers as cu', 'cu.id', '=', 'loans.customer_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('li.loan_id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->whereRaw('COALESCE(a.collected,0) <= 0')
            ->selectRaw('loans.id as loan_id, loans.loan_code as loan_code, cu.name as customer')
            ->selectRaw('SUM(li.total_due) as expected')
            ->selectRaw('SUM(COALESCE(a.collected,0)) as collected')
            ->groupBy('loans.id', 'loans.loan_code', 'cu.name')
            ->orderByDesc('expected')
            ->limit(50)
            ->get();

        return $rows->map(fn ($r) => [
            'loan_id' => (int) ($r->loan_id ?? 0),
            'loan_code' => (string) ($r->loan_code ?? ''),
            'customer' => (string) ($r->customer ?? ''),
            'expected' => round((float) ($r->expected ?? 0), 2),
            'collected' => round((float) ($r->collected ?? 0), 2),
            'missed_amount' => round(((float) ($r->expected ?? 0)) - ((float) ($r->collected ?? 0)), 2),
        ])->values()->all();
    }

    private function partialPayments(array $subshopIds, Carbon $start, Carbon $end, $loanIds): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $allocAgg = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$startDate, $endDate])
            ->selectRaw('lpa.loan_installment_id as installment_id')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) as collected')
            ->groupBy('installment_id');

        $row = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->leftJoinSub($allocAgg, 'a', fn ($j) => $j->on('a.installment_id', '=', 'li.id'))
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$startDate, $endDate])
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('li.loan_id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->selectRaw('SUM(CASE WHEN COALESCE(a.collected,0) > 0 AND COALESCE(a.collected,0) < li.total_due THEN 1 ELSE 0 END) as partial_installments')
            ->selectRaw('SUM(CASE WHEN COALESCE(a.collected,0) > 0 AND COALESCE(a.collected,0) < li.total_due THEN (li.total_due - COALESCE(a.collected,0)) ELSE 0 END) as remaining_amount')
            ->first();

        return [
            'partial_installments' => (int) ($row->partial_installments ?? 0),
            'remaining_amount' => round((float) ($row->remaining_amount ?? 0), 2),
        ];
    }
}
