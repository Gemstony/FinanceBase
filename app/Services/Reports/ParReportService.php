<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LoanProducts;
use App\Models\SubShop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ParReportService
{
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $asAt = $filters['as_at_date'];

        $dpdMin = array_key_exists('dpd_min', $filters) && $filters['dpd_min'] !== null ? (int) $filters['dpd_min'] : null;
        $dpdMax = array_key_exists('dpd_max', $filters) && $filters['dpd_max'] !== null ? (int) $filters['dpd_max'] : null;

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $loanIds = $this->filteredLoanIds($filters, $subshopIds);

        $loanAggBase = $this->loanParBase($subshopIds, $loanIds, $asAt);

        $loanAgg = DB::query()
            ->fromSub($loanAggBase, 'la')
            ->when($dpdMin !== null, fn ($q) => $q->where('la.dpd', '>=', $dpdMin))
            ->when($dpdMax !== null, fn ($q) => $q->where('la.dpd', '<=', $dpdMax))
            ->select([
                'la.loan_id',
                'la.dpd',
                'la.overdue_amount',
                'la.outstanding_balance',
            ]);

        $portfolioOutstanding = (float) DB::query()->fromSub($loanAgg, 'la')->sum('la.outstanding_balance');
        $totalOverdueAmount = (float) DB::query()->fromSub($loanAgg, 'la')->sum('la.overdue_amount');

        $summary = $this->summaryKpis($loanAgg, $portfolioOutstanding, $totalOverdueAmount);

        $agingBuckets = $this->agingBuckets($loanAgg, $portfolioOutstanding);

        $byProduct = $this->parByProduct($loanAgg, $subshopIds, $portfolioOutstanding);
        $byBranch = $this->parByBranch($loanAgg, $subshopIds, $portfolioOutstanding);
        $byOfficer = $this->parByOfficer($loanAgg, $subshopIds, $loanIds, $portfolioOutstanding);

        $trends = $this->parTrends($filters, $accessibleSubshopIds);

        $highRiskPortfolio = $this->highRiskPortfolio($loanAgg);
        $topRiskyLoans = $this->topRiskyLoans($loanAgg);
        $concentration = $this->riskConcentration($loanAgg, $portfolioOutstanding);
        $writeoffExposure = $this->writeoffExposure($loanAgg);
        $recoveryImpact = $this->recoveryImpact($filters, $accessibleSubshopIds);
        $segmentation = $this->portfolioSegmentation($loanAgg, $portfolioOutstanding);

        $loansList = $this->loanLevelList($filters, $subshopIds, $loanIds, $asAt);

        return [
            'filters' => [
                'as_at_date' => $asAt->toDateString(),
                'subshop_ids' => $subshopIds,
                'dpd_min' => $dpdMin,
                'dpd_max' => $dpdMax,
            ],
            'portfolio_outstanding' => round($portfolioOutstanding, 2),
            'summary' => $summary,
            'aging_buckets' => $agingBuckets,
            'by_product' => $byProduct,
            'by_branch' => $byBranch,
            'by_officer' => $byOfficer,
            'trends' => $trends,
            'high_risk_portfolio' => $highRiskPortfolio,
            'top_risky_loans' => $topRiskyLoans,
            'concentration' => $concentration,
            'writeoff_exposure' => $writeoffExposure,
            'recovery_impact' => $recoveryImpact,
            'segmentation' => $segmentation,
            'loans' => $loansList,
        ];
    }

    private function resolveSubshopFilter(?int $subshopId, array $accessibleSubshopIds): array
    {
        if ($subshopId) {
            return in_array($subshopId, $accessibleSubshopIds, true) ? [$subshopId] : [];
        }

        return $accessibleSubshopIds;
    }

    private function filteredLoanIds(array $filters, array $subshopIds)
    {
        $q = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when(!empty($filters['loan_product_id']), fn ($qq) => $qq->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when(!empty($filters['loan_status']), fn ($qq) => $qq->where('loans.status', (string) $filters['loan_status']));

        if (!empty($filters['loan_officer_id'])) {
            $latestDisb = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
                ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', (int) $filters['loan_officer_id']);
        }

        return $q->pluck('loans.id');
    }

    private function loanParBase(array $subshopIds, $loanIds, Carbon $asAt): QueryBuilder
    {
        $asAtDate = $asAt->toDateString();

        $unpaid = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.outstanding_amount) as outstanding_balance')
            ->groupBy('li.loan_id');

        $overdue = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->where('li.outstanding_amount', '>', 0)
            ->whereDate('li.due_date', '<', $asAtDate)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('MAX(DATEDIFF(?, li.due_date)) as dpd', [$asAtDate])
            ->selectRaw('SUM(li.outstanding_amount) as overdue_amount')
            ->groupBy('li.loan_id');

        return DB::query()
            ->fromSub($unpaid, 'u')
            ->leftJoinSub($overdue, 'o', fn ($j) => $j->on('o.loan_id', '=', 'u.loan_id'))
            ->selectRaw('u.loan_id as loan_id')
            ->selectRaw('COALESCE(o.dpd, 0) as dpd')
            ->selectRaw('COALESCE(o.overdue_amount, 0) as overdue_amount')
            ->selectRaw('u.outstanding_balance as outstanding_balance');
    }

    private function summaryKpis(QueryBuilder $loanAgg, float $portfolioOutstanding, float $totalOverdueAmount): array
    {
        $atRiskOutstanding = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 0)->sum('la.overdue_amount');

        $par30Outstanding = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 30)->sum('la.outstanding_balance');
        $par60Outstanding = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 60)->sum('la.outstanding_balance');
        $par90Outstanding = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 90)->sum('la.outstanding_balance');

        $par30 = $portfolioOutstanding > 0 ? round(($par30Outstanding / $portfolioOutstanding) * 100, 2) : 0.0;
        $par60 = $portfolioOutstanding > 0 ? round(($par60Outstanding / $portfolioOutstanding) * 100, 2) : 0.0;
        $par90 = $portfolioOutstanding > 0 ? round(($par90Outstanding / $portfolioOutstanding) * 100, 2) : 0.0;

        $nplOutstanding = $par90Outstanding;
        $nplRatio = $portfolioOutstanding > 0 ? round(($nplOutstanding / $portfolioOutstanding) * 100, 2) : 0.0;

        $nplLoans = (int) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 90)->count('la.loan_id');

        return [
            'total_portfolio_outstanding' => round($portfolioOutstanding, 2),
            'total_overdue_amount' => round($totalOverdueAmount, 2),
            'total_at_risk_amount' => round($atRiskOutstanding, 2),
            'par30_pct' => $par30,
            'par60_pct' => $par60,
            'par90_pct' => $par90,
            'npl_loans' => $nplLoans,
            'npl_outstanding' => round($nplOutstanding, 2),
            'npl_ratio_pct' => $nplRatio,
        ];
    }

    private function agingBuckets(QueryBuilder $loanAgg, float $portfolioOutstanding): array
    {
        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->selectRaw("CASE
                WHEN la.dpd <= 0 THEN 'Current'
                WHEN la.dpd <= 30 THEN '1-30'
                WHEN la.dpd <= 60 THEN '31-60'
                WHEN la.dpd <= 90 THEN '61-90'
                ELSE '90+'
            END as bucket")
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->groupBy('bucket')
            ->get();

        $order = ['Current' => 0, '1-30' => 1, '31-60' => 2, '61-90' => 3, '90+' => 4];

        $mapped = $rows->map(function ($r) use ($portfolioOutstanding) {
            $out = (float) ($r->outstanding ?? 0);
            $pct = $portfolioOutstanding > 0 ? round(($out / $portfolioOutstanding) * 100, 2) : 0.0;

            return [
                'bucket' => (string) ($r->bucket ?? ''),
                'loans' => (int) ($r->loans_count ?? 0),
                'outstanding' => round($out, 2),
                'pct' => $pct,
            ];
        })->all();

        usort($mapped, fn ($a, $b) => ($order[$a['bucket']] ?? 99) <=> ($order[$b['bucket']] ?? 99));

        return $mapped;
    }

    private function parByProduct(QueryBuilder $loanAgg, array $subshopIds, float $portfolioOutstanding): array
    {
        $products = LoanProducts::query()->whereIn('subshop_id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw('SUM(la.outstanding_balance) as total_portfolio')
            ->selectRaw('SUM(CASE WHEN la.dpd > 30 THEN la.outstanding_balance ELSE 0 END) as par30_out')
            ->selectRaw('SUM(CASE WHEN la.dpd > 60 THEN la.outstanding_balance ELSE 0 END) as par60_out')
            ->selectRaw('SUM(CASE WHEN la.dpd > 90 THEN la.outstanding_balance ELSE 0 END) as par90_out')
            ->groupBy('product_id')
            ->get();

        $mapped = $rows->map(function ($r) use ($products) {
            $pid = (int) $r->product_id;
            $total = (float) ($r->total_portfolio ?? 0);

            $par30Out = (float) ($r->par30_out ?? 0);
            $par60Out = (float) ($r->par60_out ?? 0);
            $par90Out = (float) ($r->par90_out ?? 0);

            return [
                'product_id' => $pid,
                'product' => (string) ($products[$pid]->name ?? 'Unknown'),
                'total_portfolio' => round($total, 2),
                'par30_pct' => $total > 0 ? round(($par30Out / $total) * 100, 2) : 0.0,
                'par60_pct' => $total > 0 ? round(($par60Out / $total) * 100, 2) : 0.0,
                'par90_pct' => $total > 0 ? round(($par90Out / $total) * 100, 2) : 0.0,
            ];
        })->values()->all();

        usort($mapped, fn ($a, $b) => ($b['total_portfolio'] <=> $a['total_portfolio']));

        return $mapped;
    }

    private function parByBranch(QueryBuilder $loanAgg, array $subshopIds, float $portfolioOutstanding): array
    {
        $subshops = SubShop::query()->whereIn('id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->selectRaw('loans.subshop_id as subshop_id')
            ->selectRaw('SUM(la.outstanding_balance) as total_portfolio')
            ->selectRaw('SUM(CASE WHEN la.dpd > 30 THEN la.outstanding_balance ELSE 0 END) as par30_out')
            ->selectRaw('SUM(CASE WHEN la.dpd > 90 THEN la.outstanding_balance ELSE 0 END) as par90_out')
            ->groupBy('subshop_id')
            ->get();

        $mapped = $rows->map(function ($r) use ($subshops) {
            $sid = (int) $r->subshop_id;
            $total = (float) ($r->total_portfolio ?? 0);

            $par30Out = (float) ($r->par30_out ?? 0);
            $par90Out = (float) ($r->par90_out ?? 0);

            return [
                'subshop_id' => $sid,
                'branch' => (string) ($subshops[$sid]->name ?? 'Unknown'),
                'total_portfolio' => round($total, 2),
                'par30_pct' => $total > 0 ? round(($par30Out / $total) * 100, 2) : 0.0,
                'par90_pct' => $total > 0 ? round(($par90Out / $total) * 100, 2) : 0.0,
            ];
        })->values()->all();

        usort($mapped, fn ($a, $b) => ($b['total_portfolio'] <=> $a['total_portfolio']));

        return $mapped;
    }

    private function parByOfficer(QueryBuilder $loanAgg, array $subshopIds, $loanIds, float $portfolioOutstanding): array
    {
        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $loanOfficer = DB::table('loans')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->select(['loans.id as loan_id', 'ld.processed_by as officer_id']);

        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->joinSub($loanOfficer, 'lo', fn ($j) => $j->on('lo.loan_id', '=', 'la.loan_id'))
            ->selectRaw('lo.officer_id as officer_id')
            ->selectRaw('SUM(la.outstanding_balance) as total_portfolio')
            ->selectRaw('SUM(CASE WHEN la.dpd > 30 THEN la.outstanding_balance ELSE 0 END) as par30_out')
            ->selectRaw('SUM(CASE WHEN la.dpd > 90 THEN la.outstanding_balance ELSE 0 END) as par90_out')
            ->groupBy('lo.officer_id')
            ->get();

        $officerIds = $rows->pluck('officer_id')->filter()->unique()->values();
        $users = User::query()->whereIn('id', $officerIds)->get(['id', 'name'])->keyBy('id');

        $mapped = $rows->map(function ($r) use ($users) {
            $oid = (int) $r->officer_id;
            $total = (float) ($r->total_portfolio ?? 0);

            $par30Out = (float) ($r->par30_out ?? 0);
            $par90Out = (float) ($r->par90_out ?? 0);

            return [
                'officer_id' => $oid,
                'officer' => (string) ($users[$oid]->name ?? 'Unknown'),
                'total_portfolio' => round($total, 2),
                'par30_pct' => $total > 0 ? round(($par30Out / $total) * 100, 2) : 0.0,
                'par90_pct' => $total > 0 ? round(($par90Out / $total) * 100, 2) : 0.0,
            ];
        })->values()->all();

        usort($mapped, fn ($a, $b) => ($b['total_portfolio'] <=> $a['total_portfolio']));

        return $mapped;
    }

    private function loanLevelList(array $filters, array $subshopIds, $loanIds, Carbon $asAt): LengthAwarePaginator
    {
        $dpdMin = array_key_exists('dpd_min', $filters) && $filters['dpd_min'] !== null ? (int) $filters['dpd_min'] : null;
        $dpdMax = array_key_exists('dpd_max', $filters) && $filters['dpd_max'] !== null ? (int) $filters['dpd_max'] : null;

        $perPage = !empty($filters['per_page']) ? max(5, min(200, (int) $filters['per_page'])) : 25;
        $page = !empty($filters['page']) ? max(1, (int) $filters['page']) : null;

        $loanAgg = $this->loanParBase($subshopIds, $loanIds, $asAt);

        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $officerMap = DB::table('loan_disbursements as ld')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.id', '=', 'ld.id'))
            ->selectRaw('ld.loan_id as loan_id, ld.processed_by as officer_id');

        $q = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->leftJoinSub($officerMap, 'om', fn ($j) => $j->on('om.loan_id', '=', 'loans.id'))
            ->leftJoin('users as u', 'u.id', '=', 'om.officer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as p', 'p.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->when($dpdMin !== null, fn ($qq) => $qq->where('la.dpd', '>=', $dpdMin))
            ->when($dpdMax !== null, fn ($qq) => $qq->where('la.dpd', '<=', $dpdMax))
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'c.name as customer',
                'p.name as product',
                'ss.name as branch',
                'u.name as officer',
                'loans.status as loan_status',
                DB::raw('COALESCE(la.outstanding_balance,0) as outstanding_balance'),
                DB::raw('COALESCE(la.overdue_amount,0) as overdue_amount'),
                DB::raw('COALESCE(la.dpd,0) as dpd'),
            ])
            ->orderByDesc('la.dpd')
            ->orderByDesc('la.outstanding_balance');

        return $q->paginate($perPage, ['*'], 'page', $page);
    }

    private function parTrends(array $filters, array $accessibleSubshopIds): array
    {
        $asAt = $filters['as_at_date'];
        $start = (clone $asAt)->subMonthsNoOverflow(11)->startOfMonth();
        $end = (clone $asAt)->endOfMonth();

        $dpdMin = array_key_exists('dpd_min', $filters) && $filters['dpd_min'] !== null ? (int) $filters['dpd_min'] : null;
        $dpdMax = array_key_exists('dpd_max', $filters) && $filters['dpd_max'] !== null ? (int) $filters['dpd_max'] : null;

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $loanIds = $this->filteredLoanIds($filters, $subshopIds);

        $months = $this->monthsBetween($start, $end);

        $labels = [];
        $par30 = [];
        $par60 = [];
        $par90 = [];
        $atRiskAmount = [];

        foreach ($months as [$from, $to]) {
            $labels[] = $to->format('Y-m');

            $loanAggBase = $this->loanParBase($subshopIds, $loanIds, $to);
            $loanAgg = DB::query()
                ->fromSub($loanAggBase, 'la')
                ->when($dpdMin !== null, fn ($q) => $q->where('la.dpd', '>=', $dpdMin))
                ->when($dpdMax !== null, fn ($q) => $q->where('la.dpd', '<=', $dpdMax))
                ->select([
                    'la.loan_id',
                    'la.dpd',
                    'la.overdue_amount',
                    'la.outstanding_balance',
                ]);

            $portfolioOutstanding = (float) DB::query()->fromSub($loanAgg, 'la')->sum('la.outstanding_balance');

            $par30Out = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 30)->sum('la.outstanding_balance');
            $par60Out = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 60)->sum('la.outstanding_balance');
            $par90Out = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 90)->sum('la.outstanding_balance');

            $riskAmt = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 0)->sum('la.overdue_amount');

            $par30[] = $portfolioOutstanding > 0 ? round(($par30Out / $portfolioOutstanding) * 100, 2) : 0.0;
            $par60[] = $portfolioOutstanding > 0 ? round(($par60Out / $portfolioOutstanding) * 100, 2) : 0.0;
            $par90[] = $portfolioOutstanding > 0 ? round(($par90Out / $portfolioOutstanding) * 100, 2) : 0.0;
            $atRiskAmount[] = round($riskAmt, 2);
        }

        return [
            'labels' => $labels,
            'par30' => $par30,
            'par60' => $par60,
            'par90' => $par90,
            'at_risk_amount' => $atRiskAmount,
        ];
    }

    private function monthsBetween(Carbon $dateFrom, Carbon $dateTo): array
    {
        $start = (clone $dateFrom)->startOfMonth();
        $end = (clone $dateTo)->endOfMonth();

        $months = [];
        $cursor = (clone $start);
        while ($cursor->lte($end)) {
            $mFrom = (clone $cursor)->startOfMonth();
            $mTo = (clone $cursor)->endOfMonth();
            if ($mFrom->lt($dateFrom)) {
                $mFrom = clone $dateFrom;
            }
            if ($mTo->gt($dateTo)) {
                $mTo = clone $dateTo;
            }
            $months[] = [$mFrom, $mTo];
            $cursor->addMonth();
        }

        return $months;
    }

    private function highRiskPortfolio(QueryBuilder $loanAgg): array
    {
        $over60 = DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 60);
        $over90 = DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 90);

        return [
            'over_60' => [
                'loans' => (int) (clone $over60)->count('la.loan_id'),
                'outstanding' => round((float) (clone $over60)->sum('la.outstanding_balance'), 2),
            ],
            'over_90' => [
                'loans' => (int) (clone $over90)->count('la.loan_id'),
                'outstanding' => round((float) (clone $over90)->sum('la.outstanding_balance'), 2),
            ],
        ];
    }

    private function topRiskyLoans(QueryBuilder $loanAgg): array
    {
        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->where('la.dpd', '>', 0)
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'c.name as customer',
                DB::raw('la.dpd as dpd'),
                DB::raw('la.outstanding_balance as outstanding'),
            ])
            ->orderByDesc('la.outstanding_balance')
            ->orderByDesc('la.dpd')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'loan_id' => (int) $r->loan_id,
            'loan_code' => (string) $r->loan_code,
            'customer' => (string) ($r->customer ?? 'Unknown'),
            'dpd' => (int) ($r->dpd ?? 0),
            'outstanding' => round((float) ($r->outstanding ?? 0), 2),
        ])->values()->all();
    }

    private function riskConcentration(QueryBuilder $loanAgg, float $portfolioOutstanding): array
    {
        $riskOutstanding = (float) DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 30)->sum('la.outstanding_balance');

        $topCustomers = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->where('la.dpd', '>', 30)
            ->selectRaw('loans.customer_id as customer_id')
            ->selectRaw('COALESCE(c.name, "Unknown") as customer')
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->groupBy('customer_id', 'customer')
            ->orderByDesc('outstanding')
            ->limit(5)
            ->get()
            ->map(function ($r) use ($riskOutstanding) {
                $out = (float) ($r->outstanding ?? 0);
                return [
                    'customer_id' => (int) ($r->customer_id ?? 0),
                    'customer' => (string) ($r->customer ?? 'Unknown'),
                    'outstanding' => round($out, 2),
                    'pct_of_risk' => $riskOutstanding > 0 ? round(($out / $riskOutstanding) * 100, 2) : 0.0,
                ];
            })->values()->all();

        $topBranches = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->where('la.dpd', '>', 30)
            ->selectRaw('loans.subshop_id as subshop_id')
            ->selectRaw('COALESCE(ss.name, "Unknown") as branch')
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->groupBy('subshop_id', 'branch')
            ->orderByDesc('outstanding')
            ->limit(5)
            ->get()
            ->map(function ($r) use ($riskOutstanding) {
                $out = (float) ($r->outstanding ?? 0);
                return [
                    'subshop_id' => (int) ($r->subshop_id ?? 0),
                    'branch' => (string) ($r->branch ?? 'Unknown'),
                    'outstanding' => round($out, 2),
                    'pct_of_risk' => $riskOutstanding > 0 ? round(($out / $riskOutstanding) * 100, 2) : 0.0,
                ];
            })->values()->all();

        $topProducts = DB::query()
            ->fromSub($loanAgg, 'la')
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->leftJoin('loan_products as lp', 'lp.id', '=', 'loans.loan_product_id')
            ->where('la.dpd', '>', 30)
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw('COALESCE(lp.name, "Unknown") as product')
            ->selectRaw('SUM(la.outstanding_balance) as outstanding')
            ->groupBy('product_id', 'product')
            ->orderByDesc('outstanding')
            ->limit(5)
            ->get()
            ->map(function ($r) use ($riskOutstanding) {
                $out = (float) ($r->outstanding ?? 0);
                return [
                    'product_id' => (int) ($r->product_id ?? 0),
                    'product' => (string) ($r->product ?? 'Unknown'),
                    'outstanding' => round($out, 2),
                    'pct_of_risk' => $riskOutstanding > 0 ? round(($out / $riskOutstanding) * 100, 2) : 0.0,
                ];
            })->values()->all();

        return [
            'risk_outstanding' => round($riskOutstanding, 2),
            'risk_pct_of_portfolio' => $portfolioOutstanding > 0 ? round(($riskOutstanding / $portfolioOutstanding) * 100, 2) : 0.0,
            'top_customers' => $topCustomers,
            'top_branches' => $topBranches,
            'top_products' => $topProducts,
        ];
    }

    private function writeoffExposure(QueryBuilder $loanAgg): array
    {
        $over90 = DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 90);
        $over120 = DB::query()->fromSub($loanAgg, 'la')->where('la.dpd', '>', 120);

        return [
            'dpd_over_90' => [
                'loans' => (int) (clone $over90)->count('la.loan_id'),
                'outstanding' => round((float) (clone $over90)->sum('la.outstanding_balance'), 2),
            ],
            'dpd_over_120' => [
                'loans' => (int) (clone $over120)->count('la.loan_id'),
                'outstanding' => round((float) (clone $over120)->sum('la.outstanding_balance'), 2),
            ],
        ];
    }

    private function recoveryImpact(array $filters, array $accessibleSubshopIds): array
    {
        $asAt = $filters['as_at_date'];
        $prevAsAt = (clone $asAt)->subMonthNoOverflow()->endOfMonth();

        $dpdMin = array_key_exists('dpd_min', $filters) && $filters['dpd_min'] !== null ? (int) $filters['dpd_min'] : null;
        $dpdMax = array_key_exists('dpd_max', $filters) && $filters['dpd_max'] !== null ? (int) $filters['dpd_max'] : null;

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $loanIds = $this->filteredLoanIds($filters, $subshopIds);

        $curAggBase = $this->loanParBase($subshopIds, $loanIds, $asAt);
        $prevAggBase = $this->loanParBase($subshopIds, $loanIds, $prevAsAt);

        $curAgg = DB::query()
            ->fromSub($curAggBase, 'la')
            ->when($dpdMin !== null, fn ($q) => $q->where('la.dpd', '>=', $dpdMin))
            ->when($dpdMax !== null, fn ($q) => $q->where('la.dpd', '<=', $dpdMax))
            ->select([
                'la.loan_id',
                'la.dpd',
                'la.overdue_amount',
                'la.outstanding_balance',
            ]);

        $prevAgg = DB::query()
            ->fromSub($prevAggBase, 'la')
            ->when($dpdMin !== null, fn ($q) => $q->where('la.dpd', '>=', $dpdMin))
            ->when($dpdMax !== null, fn ($q) => $q->where('la.dpd', '<=', $dpdMax))
            ->select([
                'la.loan_id',
                'la.dpd',
                'la.overdue_amount',
                'la.outstanding_balance',
            ]);

        $curPortfolio = (float) DB::query()->fromSub($curAgg, 'la')->sum('la.outstanding_balance');
        $prevPortfolio = (float) DB::query()->fromSub($prevAgg, 'la')->sum('la.outstanding_balance');

        $curPar30Out = (float) DB::query()->fromSub($curAgg, 'la')->where('la.dpd', '>', 30)->sum('la.outstanding_balance');
        $prevPar30Out = (float) DB::query()->fromSub($prevAgg, 'la')->where('la.dpd', '>', 30)->sum('la.outstanding_balance');

        $curPar30 = $curPortfolio > 0 ? round(($curPar30Out / $curPortfolio) * 100, 2) : 0.0;
        $prevPar30 = $prevPortfolio > 0 ? round(($prevPar30Out / $prevPortfolio) * 100, 2) : 0.0;

        $curAtRisk = (float) DB::query()->fromSub($curAgg, 'la')->where('la.dpd', '>', 0)->sum('la.overdue_amount');
        $prevAtRisk = (float) DB::query()->fromSub($prevAgg, 'la')->where('la.dpd', '>', 0)->sum('la.overdue_amount');

        $recovered = $this->recoveredAmountBetween($subshopIds, $loanIds, (clone $prevAsAt)->addDay()->startOfDay(), (clone $asAt)->endOfDay());

        return [
            'previous_as_at' => $prevAsAt->toDateString(),
            'current_as_at' => $asAt->toDateString(),
            'previous_par30_pct' => $prevPar30,
            'current_par30_pct' => $curPar30,
            'par30_change_pct_points' => round($curPar30 - $prevPar30, 2),
            'previous_at_risk_amount' => round($prevAtRisk, 2),
            'current_at_risk_amount' => round($curAtRisk, 2),
            'at_risk_change_amount' => round($curAtRisk - $prevAtRisk, 2),
            'recovered_amount' => round($recovered, 2),
        ];
    }

    private function recoveredAmountBetween(array $subshopIds, $loanIds, Carbon $from, Carbon $to): float
    {
        $sumExpr = 'SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0))';

        return (float) DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$from->toDateString(), $to->toDateString()])
            ->where('li.is_active', true)
            ->where('li.status', 'overdue')
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw($sumExpr . ' as recovered')
            ->value('recovered');
    }

    private function portfolioSegmentation(QueryBuilder $loanAgg, float $portfolioOutstanding): array
    {
        $buckets = [
            'Performing (Current)' => ['min' => null, 'max' => 0],
            'At Risk (1-30)' => ['min' => 1, 'max' => 30],
            'Critical (31-90)' => ['min' => 31, 'max' => 90],
            'Default (90+)' => ['min' => 91, 'max' => null],
        ];

        $rows = [];
        foreach ($buckets as $label => $range) {
            $q = DB::query()->fromSub($loanAgg, 'la');

            if ($range['min'] === null && $range['max'] !== null) {
                $q->where('la.dpd', '<=', $range['max']);
            } elseif ($range['min'] !== null && $range['max'] !== null) {
                $q->whereBetween('la.dpd', [$range['min'], $range['max']]);
            } elseif ($range['min'] !== null && $range['max'] === null) {
                $q->where('la.dpd', '>=', $range['min']);
            }

            $amt = (float) $q->sum('la.outstanding_balance');

            $rows[] = [
                'segment' => $label,
                'amount' => round($amt, 2),
                'pct' => $portfolioOutstanding > 0 ? round(($amt / $portfolioOutstanding) * 100, 2) : 0.0,
            ];
        }

        return $rows;
    }
}
