<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LoanProducts;
use App\Models\SubShop;
use App\Models\User;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DelinquencyReportService
{
    private readonly PortfolioRiskCalculator $portfolioRiskCalculator;

    public function __construct(PortfolioRiskCalculator $portfolioRiskCalculator)
    {
        $this->portfolioRiskCalculator = $portfolioRiskCalculator;
    }

    /**
     * @param  array{date_from:Carbon,date_to:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,dpd_min?:int|null,dpd_max?:int|null,page?:int|null,per_page?:int|null}  $filters
     * @param  array<int>  $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $loanIds = $this->filteredLoanIds($filters, $subshopIds);

        $portfolioOutstanding = $this->portfolioOutstanding($subshopIds, $loanIds);
        $summary = $this->summaryKpis($subshopIds, $loanIds, $portfolioOutstanding);

        $aging = $this->agingAnalysis($subshopIds, $loanIds, $portfolioOutstanding);
        $delinquentList = $this->delinquentLoanList($filters, $subshopIds, $loanIds);
        $byOfficer = $this->delinquencyByOfficer($subshopIds, $loanIds, $portfolioOutstanding);
        $byBranch = $this->delinquencyByBranch($subshopIds, $loanIds, $portfolioOutstanding);
        $byProduct = $this->delinquencyByProduct($subshopIds, $loanIds, $portfolioOutstanding);
        $highRisk = $this->highRiskLoans($subshopIds, $loanIds);
        $recovery = $this->recoveryTracking($subshopIds, $loanIds, $dateFrom, $dateTo, (float) ($summary['total_overdue_amount'] ?? 0));
        $trends = $this->delinquencyTrends($subshopIds, $loanIds, $dateFrom, $dateTo, $portfolioOutstanding);
        $dpd = $this->dpdAnalysis($subshopIds, $loanIds);
        $missed = $this->missedInstallments($subshopIds, $loanIds);
        $writeoff = $this->writeoffCandidates($subshopIds, $loanIds);

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_ids' => $subshopIds,
                'dpd_min' => $filters['dpd_min'] ?? null,
                'dpd_max' => $filters['dpd_max'] ?? null,
            ],
            'portfolio_outstanding' => round($portfolioOutstanding, 2),
            'summary' => $summary,
            'aging' => $aging,
            'delinquent_loans' => $delinquentList,
            'by_officer' => $byOfficer,
            'by_branch' => $byBranch,
            'by_product' => $byProduct,
            'high_risk' => $highRisk,
            'recovery' => $recovery,
            'trends' => $trends,
            'dpd_analysis' => $dpd,
            'missed_installments' => $missed,
            'writeoff_candidates' => $writeoff,
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
     * Officer is derived from latest disbursement processor (loan_disbursements.processed_by).
     *
     * @param  array{loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null}  $filters
     */
    private function filteredLoanIds(array $filters, array $subshopIds)
    {
        $q = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->when(! empty($filters['loan_product_id']), fn ($qq) => $qq->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when(! empty($filters['loan_status']), fn ($qq) => $qq->where('loans.status', (string) $filters['loan_status']));

        if (! empty($filters['loan_officer_id'])) {
            $latestDisb = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
                ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', (int) $filters['loan_officer_id']);
        }

        return $q->pluck('loans.id');
    }

    private function portfolioOutstanding(array $subshopIds, $loanIds): float
    {
        return $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops($subshopIds);
    }

    private function summaryKpis(array $subshopIds, $loanIds, float $portfolioOutstanding): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds);

        $totalOverdueLoans = (int) DB::query()->fromSub($delinq, 'd')->count('d.loan_id');
        $totalOverdueAmount = (float) DB::query()->fromSub($delinq, 'd')->sum('d.overdue_amount');

        $par30Outstanding = (float) DB::query()->fromSub($delinq, 'd')->where('d.max_dpd', '>', 30)->sum('d.outstanding_balance');
        $par60Outstanding = (float) DB::query()->fromSub($delinq, 'd')->where('d.max_dpd', '>', 60)->sum('d.outstanding_balance');
        $par90Outstanding = (float) DB::query()->fromSub($delinq, 'd')->where('d.max_dpd', '>', 90)->sum('d.outstanding_balance');

        $par30 = $portfolioOutstanding > 0 ? round(($par30Outstanding / $portfolioOutstanding) * 100, 2) : 0.0;
        $par60 = $portfolioOutstanding > 0 ? round(($par60Outstanding / $portfolioOutstanding) * 100, 2) : 0.0;
        $par90 = $portfolioOutstanding > 0 ? round(($par90Outstanding / $portfolioOutstanding) * 100, 2) : 0.0;

        $npl = (int) DB::query()->fromSub($delinq, 'd')->where('d.max_dpd', '>', 90)->count('d.loan_id');
        $avgDpd = (float) DB::query()->fromSub($delinq, 'd')->avg('d.max_dpd');

        return [
            'total_overdue_loans' => $totalOverdueLoans,
            'total_overdue_amount' => round($totalOverdueAmount, 2),
            'par30_pct' => $par30,
            'par60_pct' => $par60,
            'par90_pct' => $par90,
            'npl_loans' => $npl,
            'avg_dpd' => round($avgDpd ?: 0.0, 2),
        ];
    }

    private function delinquentLoansBase(array $subshopIds, $loanIds): QueryBuilder
    {
        $overdue = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('li.is_active', true)
            ->where('li.status', 'overdue')
            ->where('li.outstanding_amount', '>', 0)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('MAX(DATEDIFF(CURDATE(), li.due_date)) as max_dpd')
            ->selectRaw('SUM(li.outstanding_amount) as overdue_amount')
            ->groupBy('li.loan_id');

        $allOutstanding = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('li.is_active', true)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('SUM(li.outstanding_amount) as outstanding_balance')
            ->groupBy('li.loan_id');

        return DB::query()
            ->fromSub($overdue, 'o')
            ->joinSub($allOutstanding, 'a', fn ($j) => $j->on('a.loan_id', '=', 'o.loan_id'))
            ->select(['o.loan_id', 'o.max_dpd', 'o.overdue_amount', 'a.outstanding_balance']);
    }

    private function agingAnalysis(array $subshopIds, $loanIds, float $portfolioOutstanding): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds);

        $rows = DB::query()
            ->fromSub($delinq, 'd')
            ->selectRaw("CASE
                WHEN d.max_dpd <= 0 THEN 'Current'
                WHEN d.max_dpd <= 30 THEN '1-30'
                WHEN d.max_dpd <= 60 THEN '31-60'
                WHEN d.max_dpd <= 90 THEN '61-90'
                ELSE '90+'
            END as bucket")
            ->selectRaw('COUNT(*) as loans_count')
            ->selectRaw('SUM(d.outstanding_balance) as outstanding')
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

    private function delinquentLoanList(array $filters, array $subshopIds, $loanIds): LengthAwarePaginator
    {
        $dpdMin = array_key_exists('dpd_min', $filters) && $filters['dpd_min'] !== null ? (int) $filters['dpd_min'] : null;
        $dpdMax = array_key_exists('dpd_max', $filters) && $filters['dpd_max'] !== null ? (int) $filters['dpd_max'] : null;

        $perPage = ! empty($filters['per_page']) ? max(5, min(200, (int) $filters['per_page'])) : 25;
        $page = ! empty($filters['page']) ? max(1, (int) $filters['page']) : null;

        $overdueAgg = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('li.is_active', true)
            ->where('li.status', 'overdue')
            ->where('li.outstanding_amount', '>', 0)
            ->selectRaw('li.loan_id as loan_id')
            ->selectRaw('MAX(DATEDIFF(CURDATE(), li.due_date)) as dpd')
            ->selectRaw('SUM(li.outstanding_amount) as overdue_amount')
            ->groupBy('li.loan_id');

        $lastPayment = DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('lp.status', 'confirmed')
            ->selectRaw('lp.loan_id as loan_id, MAX(lp.payment_date) as last_payment_date')
            ->groupBy('lp.loan_id');

        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $officerMap = DB::table('loan_disbursements as ld')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.id', '=', 'ld.id'))
            ->selectRaw('ld.loan_id as loan_id, ld.processed_by as officer_id');

        $q = DB::table('loans')
            ->joinSub($overdueAgg, 'od', fn ($j) => $j->on('od.loan_id', '=', 'loans.id'))
            ->leftJoinSub($lastPayment, 'lp', fn ($j) => $j->on('lp.loan_id', '=', 'loans.id'))
            ->leftJoinSub($officerMap, 'om', fn ($j) => $j->on('om.loan_id', '=', 'loans.id'))
            ->leftJoin('users as u', 'u.id', '=', 'om.officer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as p', 'p.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->when($loanIds->isNotEmpty(), fn ($qq) => $qq->whereIn('loans.id', $loanIds), fn ($qq) => $qq->whereRaw('1=0'))
            ->when($dpdMin !== null, fn ($qq) => $qq->where('od.dpd', '>=', $dpdMin))
            ->when($dpdMax !== null, fn ($qq) => $qq->where('od.dpd', '<=', $dpdMax))
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'c.name as customer',
                'p.name as product',
                'ss.name as branch',
                'u.name as officer',
                'loans.status as loan_status',
                DB::raw('COALESCE(od.overdue_amount,0) as overdue_amount'),
                DB::raw('COALESCE(od.dpd,0) as dpd'),
                'lp.last_payment_date as last_payment_date',
            ])
            ->orderByDesc('od.dpd');

        return $q->paginate($perPage, ['*'], 'page', $page);
    }

    private function delinquencyByOfficer(array $subshopIds, $loanIds, float $portfolioOutstanding): array
    {
        $latestDisb = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $loanOfficer = DB::table('loans')
            ->joinSub($latestDisb, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('loans.id as loan_id, ld.processed_by as officer_id')
            ->get();

        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds);
        $delinquentLoanIds = DB::query()
            ->fromSub($delinq, 'd')
            ->pluck('d.loan_id');

        $officerOutstanding = [];
        $officerOverdueLoans = [];
        $officerOverdueAmount = [];
        $officerPar30 = [];
        $officerLoanMap = [];

        foreach ($loanOfficer as $lo) {
            $oid = (int) $lo->officer_id;
            if (! isset($officerOutstanding[$oid])) {
                $officerOutstanding[$oid] = 0.0;
                $officerOverdueLoans[$oid] = 0;
                $officerOverdueAmount[$oid] = 0.0;
                $officerPar30[$oid] = 0.0;
                $officerLoanMap[$oid] = [];
            }
            $officerLoanMap[$oid][] = (int) $lo->loan_id;
        }

        foreach ($officerLoanMap as $oid => $loanIdList) {
            foreach ($loanIdList as $loanId) {
                $loan = \App\Models\Loans::find($loanId);
                if ($loan) {
                    $out = $this->portfolioRiskCalculator->calculateLoanOutstanding($loan);
                    $officerOutstanding[$oid] += $out;

                    if ($delinquentLoanIds->contains($loanId)) {
                        $dpd = DB::table('loan_installments as li')
                            ->where('li.loan_id', $loanId)
                            ->where('li.is_active', true)
                            ->where('li.outstanding_amount', '>', 0)
                            ->whereDate('li.due_date', '<', Carbon::today()->toDateString())
                            ->max(DB::raw('DATEDIFF(CURDATE(), li.due_date)'));

                        $currentDpd = (int) ($dpd ?? 0);
                        $officerOverdueLoans[$oid]++;
                        $officerOverdueAmount[$oid] += $out;
                        if ($currentDpd > 30) {
                            $officerPar30[$oid] += $out;
                        }
                    }
                }
            }
        }

        $totalLoansByOfficer = [];
        foreach ($officerLoanMap as $oid => $loanIdList) {
            $totalLoansByOfficer[$oid] = count($loanIdList);
        }

        $users = User::query()->whereIn('id', array_keys($officerOutstanding))->get(['id', 'name'])->keyBy('id');

        $mapped = [];
        foreach ($officerOutstanding as $oid => $total) {
            $par30Out = $officerPar30[$oid] ?? 0;
            $par30 = $total > 0 ? round(($par30Out / $total) * 100, 2) : 0.0;

            $mapped[] = [
                'officer_id' => $oid,
                'officer' => (string) ($users[$oid]->name ?? 'Unknown'),
                'total_loans' => (int) ($totalLoansByOfficer[$oid] ?? 0),
                'overdue_loans' => (int) ($officerOverdueLoans[$oid] ?? 0),
                'overdue_amount' => round((float) ($officerOverdueAmount[$oid] ?? 0), 2),
                'par30_pct' => $par30,
            ];
        }

        usort($mapped, fn ($a, $b) => ($b['overdue_amount'] <=> $a['overdue_amount']));

        return $mapped;
    }

    private function delinquencyByBranch(array $subshopIds, $loanIds, float $portfolioOutstanding): array
    {
        $subshops = SubShop::query()->whereIn('id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $branchOutstanding = [];
        $branchOverdueLoans = [];
        $branchOverdueAmount = [];
        $branchPar30 = [];

        foreach ($subshopIds as $subshopId) {
            $outstanding = $this->portfolioRiskCalculator->calculateTotalPortfolioOutstandingForSubshops([$subshopId]);
            $branchOutstanding[$subshopId] = $outstanding;

            $overdueLoanIds = DB::table('loan_installments as li')
                ->join('loans', 'loans.id', '=', 'li.loan_id')
                ->whereIn('loans.subshop_id', [$subshopId])
                ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
                ->where('loans.is_active', true)
                ->where('loans.is_written_off', false)
                ->where('li.is_active', true)
                ->where('li.status', 'overdue')
                ->where('li.outstanding_amount', '>', 0)
                ->pluck('li.loan_id')
                ->unique();

            $overdueCount = 0;
            $overdueAmt = 0.0;
            $par30Amt = 0.0;

            foreach ($overdueLoanIds as $loanId) {
                $loan = \App\Models\Loans::find($loanId);
                if ($loan) {
                    $out = $this->portfolioRiskCalculator->calculateLoanOutstanding($loan);
                    $dpd = DB::table('loan_installments as li')
                        ->where('li.loan_id', $loanId)
                        ->where('li.is_active', true)
                        ->where('li.outstanding_amount', '>', 0)
                        ->whereDate('li.due_date', '<', Carbon::today()->toDateString())
                        ->max(DB::raw('DATEDIFF(CURDATE(), li.due_date)'));

                    $currentDpd = (int) ($dpd ?? 0);
                    if ($currentDpd > 0) {
                        $overdueCount++;
                        $overdueAmt += $out;
                    }
                    if ($currentDpd > 30) {
                        $par30Amt += $out;
                    }
                }
            }

            $branchOverdueLoans[$subshopId] = $overdueCount;
            $branchOverdueAmount[$subshopId] = $overdueAmt;
            $branchPar30[$subshopId] = $par30Amt;
        }

        $totalLoans = DB::table('loans')
            ->whereIn('subshop_id', $subshopIds)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('is_active', true)
            ->where('is_written_off', false)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('subshop_id, COUNT(*) as total_loans')
            ->groupBy('subshop_id')
            ->pluck('total_loans', 'subshop_id');

        $mapped = [];
        foreach ($branchOutstanding as $sid => $total) {
            $par30Out = $branchPar30[$sid] ?? 0;
            $par30 = $total > 0 ? round(($par30Out / $total) * 100, 2) : 0.0;

            $mapped[] = [
                'branch_id' => (int) $sid,
                'branch' => (string) ($subshops[$sid]->name ?? 'Unknown'),
                'total_loans' => (int) ($totalLoans[$sid] ?? 0),
                'overdue_loans' => (int) ($branchOverdueLoans[$sid] ?? 0),
                'overdue_amount' => round((float) ($branchOverdueAmount[$sid] ?? 0), 2),
                'par30_pct' => $par30,
            ];
        }

        usort($mapped, fn ($a, $b) => ($b['overdue_amount'] <=> $a['overdue_amount']));

        return $mapped;
    }

    private function delinquencyByProduct(array $subshopIds, $loanIds, float $portfolioOutstanding): array
    {
        $products = LoanProducts::query()->whereIn('subshop_id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $productLoanMap = DB::table('loans')
            ->whereIn('subshop_id', $subshopIds)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('is_active', true)
            ->where('is_written_off', false)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('loan_product_id, GROUP_CONCAT(id) as loan_ids')
            ->groupBy('loan_product_id')
            ->get()
            ->keyBy('loan_product_id');

        $productOutstanding = [];
        $productOverdueLoans = [];
        $productOverdueAmount = [];
        $productPar30 = [];

        foreach ($productLoanMap as $pid => $row) {
            $loanIdsArr = array_filter(explode(',', $row->loan_ids ?? ''));
            $totalOut = 0.0;
            $overdueCount = 0;
            $overdueAmt = 0.0;
            $par30Amt = 0.0;

            foreach ($loanIdsArr as $loanId) {
                $loan = \App\Models\Loans::find((int) $loanId);
                if ($loan) {
                    $out = $this->portfolioRiskCalculator->calculateLoanOutstanding($loan);
                    $totalOut += $out;

                    $dpd = DB::table('loan_installments as li')
                        ->where('li.loan_id', $loanId)
                        ->where('li.is_active', true)
                        ->where('li.outstanding_amount', '>', 0)
                        ->whereDate('li.due_date', '<', Carbon::today()->toDateString())
                        ->max(DB::raw('DATEDIFF(CURDATE(), li.due_date)'));

                    $currentDpd = (int) ($dpd ?? 0);
                    if ($currentDpd > 0) {
                        $overdueCount++;
                        $overdueAmt += $out;
                    }
                    if ($currentDpd > 30) {
                        $par30Amt += $out;
                    }
                }
            }

            $productOutstanding[$pid] = $totalOut;
            $productOverdueLoans[$pid] = $overdueCount;
            $productOverdueAmount[$pid] = $overdueAmt;
            $productPar30[$pid] = $par30Amt;
        }

        $totalLoans = DB::table('loans')
            ->whereIn('subshop_id', $subshopIds)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('is_active', true)
            ->where('is_written_off', false)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('loan_product_id, COUNT(*) as total_loans')
            ->groupBy('loan_product_id')
            ->pluck('total_loans', 'loan_product_id');

        $mapped = [];
        foreach ($productOutstanding as $pid => $total) {
            $par30Out = $productPar30[$pid] ?? 0;
            $par30 = $total > 0 ? round(($par30Out / $total) * 100, 2) : 0.0;

            $mapped[] = [
                'product_id' => (int) $pid,
                'product' => (string) ($products[$pid]->name ?? 'Unknown'),
                'total_loans' => (int) ($totalLoans[$pid] ?? 0),
                'overdue_loans' => (int) ($productOverdueLoans[$pid] ?? 0),
                'overdue_amount' => round((float) ($productOverdueAmount[$pid] ?? 0), 2),
                'par30_pct' => $par30,
            ];
        }

        usort($mapped, fn ($a, $b) => ($b['overdue_amount'] <=> $a['overdue_amount']));

        return $mapped;
    }

    private function highRiskLoans(array $subshopIds, $loanIds): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds);

        $rows = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as p', 'p.id', '=', 'loans.loan_product_id')
            ->where('d.max_dpd', '>=', 60)
            ->select([
                'd.loan_id',
                'loans.loan_code',
                'c.name as customer',
                'p.name as product',
                DB::raw('d.max_dpd as dpd'),
                DB::raw('d.overdue_amount as overdue_amount'),
                DB::raw('d.outstanding_balance as outstanding'),
            ])
            ->orderByDesc('d.max_dpd')
            ->orderByDesc('d.outstanding_balance')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'loan_id' => (int) $r->loan_id,
            'loan_code' => (string) $r->loan_code,
            'customer' => (string) ($r->customer ?? 'Unknown'),
            'product' => (string) ($r->product ?? 'Unknown'),
            'dpd' => (int) ($r->dpd ?? 0),
            'overdue_amount' => round((float) ($r->overdue_amount ?? 0), 2),
            'outstanding' => round((float) ($r->outstanding ?? 0), 2),
        ])->values()->all();
    }

    private function recoveryTracking(array $subshopIds, $loanIds, Carbon $dateFrom, Carbon $dateTo, float $totalOverdueAmount): array
    {
        $sumExpr = 'SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0))';

        $recovered = (float) DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('li.is_active', true)
            ->where('li.status', 'overdue')
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw($sumExpr.' as recovered')
            ->value('recovered');

        $rate = $totalOverdueAmount > 0 ? round(($recovered / $totalOverdueAmount) * 100, 2) : 0.0;

        return [
            'recovered_amount' => round($recovered, 2),
            'recovery_rate_pct' => $rate,
        ];
    }

    private function delinquencyTrends(array $subshopIds, $loanIds, Carbon $dateFrom, Carbon $dateTo, float $portfolioOutstanding): array
    {
        $months = $this->monthsBetween($dateFrom, $dateTo);

        $labels = [];
        $par30 = [];
        $par60 = [];
        $par90 = [];
        $overdueAmt = [];
        $delinqLoans = [];

        foreach ($months as [$from, $to]) {
            $labels[] = $from->format('Y-m');

            $overdueAgg = DB::table('loan_installments as li')
                ->join('loans', 'loans.id', '=', 'li.loan_id')
                ->whereIn('loans.subshop_id', $subshopIds)
                ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
                ->where('loans.is_active', true)
                ->where('loans.is_written_off', false)
                ->where('li.is_active', true)
                ->where('li.status', 'overdue')
                ->where('li.outstanding_amount', '>', 0)
                ->whereDate('li.due_date', '<=', $to->toDateString())
                ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
                ->selectRaw('li.loan_id as loan_id')
                ->selectRaw('MAX(DATEDIFF(?, li.due_date)) as max_dpd', [$to->toDateString()])
                ->selectRaw('SUM(li.outstanding_amount) as overdue_amount')
                ->groupBy('li.loan_id');

            $allOutstanding = DB::table('loan_installments as li')
                ->join('loans', 'loans.id', '=', 'li.loan_id')
                ->whereIn('loans.subshop_id', $subshopIds)
                ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
                ->where('loans.is_active', true)
                ->where('loans.is_written_off', false)
                ->where('li.is_active', true)
                ->where('li.outstanding_amount', '>', 0)
                ->whereDate('li.due_date', '<=', $to->toDateString())
                ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
                ->selectRaw('li.loan_id as loan_id')
                ->selectRaw('SUM(li.outstanding_amount) as outstanding_balance')
                ->groupBy('li.loan_id');

            $base = DB::query()
                ->fromSub($overdueAgg, 'o')
                ->joinSub($allOutstanding, 'a', fn ($j) => $j->on('a.loan_id', '=', 'o.loan_id'))
                ->select(['o.loan_id', 'o.max_dpd', 'o.overdue_amount', 'a.outstanding_balance']);

            $overdue = (float) DB::query()->fromSub($base, 'd')->sum('d.overdue_amount');
            $count = (int) DB::query()->fromSub($base, 'd')->count('d.loan_id');

            $par30Out = (float) DB::query()->fromSub($base, 'd')->where('d.max_dpd', '>', 30)->sum('d.outstanding_balance');
            $par60Out = (float) DB::query()->fromSub($base, 'd')->where('d.max_dpd', '>', 60)->sum('d.outstanding_balance');
            $par90Out = (float) DB::query()->fromSub($base, 'd')->where('d.max_dpd', '>', 90)->sum('d.outstanding_balance');

            $par30[] = $portfolioOutstanding > 0 ? round(($par30Out / $portfolioOutstanding) * 100, 2) : 0.0;
            $par60[] = $portfolioOutstanding > 0 ? round(($par60Out / $portfolioOutstanding) * 100, 2) : 0.0;
            $par90[] = $portfolioOutstanding > 0 ? round(($par90Out / $portfolioOutstanding) * 100, 2) : 0.0;
            $overdueAmt[] = round($overdue, 2);
            $delinqLoans[] = $count;
        }

        return [
            'chart' => [
                'labels' => $labels,
                'par30' => $par30,
                'par60' => $par60,
                'par90' => $par90,
                'overdue_amount' => $overdueAmt,
                'delinquent_loans' => $delinqLoans,
            ],
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

    private function dpdAnalysis(array $subshopIds, $loanIds): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds);

        $avg = (float) DB::query()->fromSub($delinq, 'd')->avg('d.max_dpd');
        $max = (int) DB::query()->fromSub($delinq, 'd')->max('d.max_dpd');

        $distRows = DB::query()
            ->fromSub($delinq, 'd')
            ->selectRaw("CASE
                WHEN d.max_dpd <= 30 THEN '1-30'
                WHEN d.max_dpd <= 60 THEN '31-60'
                WHEN d.max_dpd <= 90 THEN '61-90'
                WHEN d.max_dpd <= 120 THEN '91-120'
                ELSE '120+'
            END as bucket")
            ->selectRaw('COUNT(*) as loans_count')
            ->groupBy('bucket')
            ->get();

        $order = ['1-30' => 1, '31-60' => 2, '61-90' => 3, '91-120' => 4, '120+' => 5];
        $dist = $distRows->map(fn ($r) => [
            'bucket' => (string) ($r->bucket ?? ''),
            'loans' => (int) ($r->loans_count ?? 0),
        ])->all();
        usort($dist, fn ($a, $b) => ($order[$a['bucket']] ?? 99) <=> ($order[$b['bucket']] ?? 99));

        return [
            'avg_dpd' => round($avg ?: 0.0, 2),
            'max_dpd' => $max,
            'distribution' => $dist,
        ];
    }

    private function missedInstallments(array $subshopIds, $loanIds): array
    {
        $rows = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('li.is_active', true)
            ->where('li.status', 'overdue')
            ->where('li.outstanding_amount', '>', 0)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id as loan_id, COUNT(*) as missed_installments')
            ->groupBy('li.loan_id')
            ->orderByDesc('missed_installments')
            ->limit(10)
            ->get();

        $loanCodes = DB::table('loans')->whereIn('id', $rows->pluck('loan_id')->values())->pluck('loan_code', 'id');

        return $rows->map(fn ($r) => [
            'loan_id' => (int) $r->loan_id,
            'loan_code' => (string) ($loanCodes[(int) $r->loan_id] ?? ''),
            'missed_installments' => (int) $r->missed_installments,
        ])->values()->all();
    }

    private function writeoffCandidates(array $subshopIds, $loanIds): array
    {
        $delinq = $this->delinquentLoansBase($subshopIds, $loanIds);

        $lastPayment = DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('lp.status', 'confirmed')
            ->selectRaw('lp.loan_id as loan_id, MAX(lp.payment_date) as last_payment_date')
            ->groupBy('lp.loan_id');

        $rows = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->leftJoinSub($lastPayment, 'lp', fn ($j) => $j->on('lp.loan_id', '=', 'd.loan_id'))
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->where('d.max_dpd', '>=', 90)
            ->where(function ($q) {
                $q->whereNull('lp.last_payment_date')
                    ->orWhereDate('lp.last_payment_date', '<', Carbon::today()->subDays(60)->toDateString());
            })
            ->select([
                'd.loan_id',
                'loans.loan_code',
                'c.name as customer',
                DB::raw('d.max_dpd as dpd'),
                DB::raw('d.outstanding_balance as outstanding'),
                'lp.last_payment_date as last_payment_date',
            ])
            ->orderByDesc('d.max_dpd')
            ->orderByDesc('d.outstanding_balance')
            ->limit(20)
            ->get();

        return $rows->map(fn ($r) => [
            'loan_id' => (int) $r->loan_id,
            'loan_code' => (string) $r->loan_code,
            'customer' => (string) ($r->customer ?? 'Unknown'),
            'dpd' => (int) ($r->dpd ?? 0),
            'outstanding' => round((float) ($r->outstanding ?? 0), 2),
            'last_payment_date' => $r->last_payment_date,
            'recommendation' => 'Consider Write-Off',
        ])->values()->all();
    }
}
