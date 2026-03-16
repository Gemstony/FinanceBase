<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LoanDisbursements;
use App\Models\LoanInstallments;
use App\Models\LoanPayments;
use App\Models\LoanProducts;
use App\Models\Loans;
use App\Models\SubShop;
use App\Models\User;
use App\Services\Loans\Account\LoanBalanceCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LoanPortfolioReportService
{
    public function __construct(
        private readonly LoanBalanceCalculator $loanBalanceCalculator,
    ) {
    }

    /**
     * Build the full loan portfolio report dataset.
     *
     * @param array{date_from:Carbon,date_to:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);

        $loanBase = $this->filteredLoansQuery($filters, $subshopIds);

        $portfolioOutstanding = $this->calculatePortfolioOutstanding($loanBase);

        $activeStatuses = ['disbursed', 'partially_paid', 'defaulted'];
        $activeLoansCount = (clone $loanBase)->whereIn('status', $activeStatuses)->count('id');

        $activeBorrowersCount = (clone $loanBase)
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('customer_id')
            ->distinct()
            ->count('customer_id');

        $disbursed = $this->disbursementMetrics($filters, $subshopIds, $dateFrom, $dateTo);
        $repayments = $this->repaymentMetrics($filters, $subshopIds, $dateFrom, $dateTo);

        $avgLoanSize = $activeLoansCount > 0
            ? round(($portfolioOutstanding / $activeLoansCount), 2)
            : 0.0;

        $summary = [
            'total_outstanding' => $portfolioOutstanding,
            'active_loans' => $activeLoansCount,
            'active_borrowers' => $activeBorrowersCount,
            'total_disbursed_period' => (float) ($disbursed['total_amount'] ?? 0),
            'total_repayments_period' => (float) ($repayments['total_collected'] ?? 0),
            'avg_loan_size' => $avgLoanSize,
        ];

        $composition = [
            'by_product' => $this->compositionByProduct($loanBase),
            'by_branch' => $this->compositionByBranch($loanBase, $subshopIds),
            'by_officer' => $this->compositionByOfficer($filters, $subshopIds, $dateFrom, $dateTo),
        ];

        $par = $this->portfolioAtRisk($filters, $subshopIds, $portfolioOutstanding);

        $aging = $this->portfolioAging($filters, $subshopIds);

        $disbursementAnalysis = $this->disbursementAnalysis($filters, $subshopIds, $dateFrom, $dateTo);

        $repaymentPerformance = $this->repaymentPerformance($filters, $subshopIds, $dateFrom, $dateTo);

        $topBorrowers = $this->topBorrowers($loanBase);

        $trends = $this->portfolioTrends($filters, $subshopIds, $dateFrom, $dateTo);

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_ids' => $subshopIds,
            ],
            'summary' => $summary,
            'composition' => $composition,
            'par' => $par,
            'aging' => $aging,
            'disbursement_analysis' => $disbursementAnalysis,
            'repayment_performance' => $repaymentPerformance,
            'top_borrowers' => $topBorrowers,
            'trends' => $trends,
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
     * Loans base query for the report (subshop-scoped, read-only).
     *
     * IMPORTANT: Loan Officer filtering uses the disbursement processor (`loan_disbursements.processed_by`).
     */
    private function filteredLoansQuery(array $filters, array $subshopIds): Builder
    {
        $q = Loans::query()->whereIn('subshop_id', $subshopIds);

        if (!empty($filters['loan_product_id'])) {
            $q->where('loan_product_id', (int) $filters['loan_product_id']);
        }

        if (!empty($filters['loan_status'])) {
            $q->where('status', (string) $filters['loan_status']);
        }

        // Officer filter via latest disbursement processor.
        if (!empty($filters['loan_officer_id'])) {
            $officerId = (int) $filters['loan_officer_id'];
            $latestDisbursement = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisbursement, 'ld_latest', function ($j) {
                $j->on('ld_latest.loan_id', '=', 'loans.id');
            })->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', $officerId)
                ->select('loans.*');
        }

        return $q;
    }

    private function calculatePortfolioOutstanding(Builder $loanBase): float
    {
        $total = 0.0;

        (clone $loanBase)
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(200, function ($loans) use (&$total) {
                foreach ($loans as $loan) {
                    $balances = $this->loanBalanceCalculator->calculateBalances($loan);
                    $total += (float) ($balances['total_balance'] ?? 0);
                }
            });

        return round($total, 2);
    }

    private function compositionByProduct(Builder $loanBase): array
    {
        $activeStatuses = ['disbursed', 'partially_paid', 'defaulted'];

        $rows = (clone $loanBase)
            ->whereIn('status', $activeStatuses)
            ->selectRaw('loan_product_id, COUNT(*) as loans_count, SUM(COALESCE(outstanding_balance,0)) as outstanding')
            ->groupBy('loan_product_id')
            ->get();

        $products = LoanProducts::query()
            ->whereIn('id', $rows->pluck('loan_product_id')->filter()->unique()->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        $portfolioTotal = (float) $rows->sum('outstanding');

        return $rows->map(function ($r) use ($products, $portfolioTotal) {
            $out = (float) $r->outstanding;
            return [
                'product_id' => (int) $r->loan_product_id,
                'product_name' => (string) ($products[(int) $r->loan_product_id]->name ?? 'Unknown'),
                'loans_count' => (int) $r->loans_count,
                'outstanding' => round($out, 2),
                'pct' => $portfolioTotal > 0 ? round(($out / $portfolioTotal) * 100, 2) : 0.0,
            ];
        })->sortByDesc('outstanding')->values()->all();
    }

    private function compositionByBranch(Builder $loanBase, array $subshopIds): array
    {
        $activeStatuses = ['disbursed', 'partially_paid', 'defaulted'];

        $rows = (clone $loanBase)
            ->whereIn('status', $activeStatuses)
            ->selectRaw('subshop_id, COUNT(*) as loans_count, SUM(COALESCE(outstanding_balance,0)) as outstanding')
            ->groupBy('subshop_id')
            ->get();

        $subshops = SubShop::query()->whereIn('id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $par30ByBranch = $this->par30ByBranch($subshopIds);

        return $rows->map(function ($r) use ($subshops, $par30ByBranch) {
            $sid = (int) $r->subshop_id;
            return [
                'subshop_id' => $sid,
                'branch' => (string) ($subshops[$sid]->name ?? 'Unknown'),
                'active_loans' => (int) $r->loans_count,
                'outstanding' => round((float) $r->outstanding, 2),
                'par30' => round((float) ($par30ByBranch[$sid] ?? 0.0), 2),
            ];
        })->sortByDesc('outstanding')->values()->all();
    }

    /**
     * Officer performance uses latest disbursement processor per loan.
     */
    private function compositionByOfficer(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $activeStatuses = ['disbursed', 'partially_paid', 'defaulted'];

        $loansQ = Loans::query()
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', $activeStatuses);

        if (!empty($filters['loan_product_id'])) {
            $loansQ->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (!empty($filters['loan_status'])) {
            $loansQ->where('loans.status', (string) $filters['loan_status']);
        }

        $latestDisbursement = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        $rows = $loansQ
            ->joinSub($latestDisbursement, 'ld_latest', function ($j) {
                $j->on('ld_latest.loan_id', '=', 'loans.id');
            })
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->selectRaw('ld.processed_by as officer_id, COUNT(loans.id) as loans_count, SUM(COALESCE(loans.outstanding_balance,0)) as outstanding')
            ->groupBy('officer_id')
            ->get();

        $officerIds = $rows->pluck('officer_id')->filter()->unique()->values();
        $officers = User::query()->whereIn('id', $officerIds)->get(['id', 'name'])->keyBy('id');

        // repayments collected in period by officer's portfolio (loans mapped to officer)
        $repaymentsByLoan = DB::table('loan_payments as lp')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('lp.status', 'confirmed')
            ->selectRaw('lp.loan_id, SUM(lp.amount) as collected')
            ->groupBy('lp.loan_id');

        $collectedByOfficer = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', $activeStatuses)
            ->joinSub($latestDisbursement, 'ld_latest', function ($j) {
                $j->on('ld_latest.loan_id', '=', 'loans.id');
            })
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->leftJoinSub($repaymentsByLoan, 'rp', function ($j) {
                $j->on('rp.loan_id', '=', 'loans.id');
            })
            ->selectRaw('ld.processed_by as officer_id, SUM(COALESCE(rp.collected,0)) as collected')
            ->groupBy('officer_id')
            ->pluck('collected', 'officer_id');

        return $rows->map(function ($r) use ($officers, $collectedByOfficer) {
            $oid = (int) $r->officer_id;
            return [
                'officer_id' => $oid,
                'officer' => (string) ($officers[$oid]->name ?? 'Unknown'),
                'loans_managed' => (int) $r->loans_count,
                'outstanding' => round((float) $r->outstanding, 2),
                'repayments_collected' => round((float) ($collectedByOfficer[$oid] ?? 0.0), 2),
            ];
        })->sortByDesc('outstanding')->values()->all();
    }

    private function disbursementMetrics(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $q = LoanDisbursements::query()
            ->join('loans', 'loans.id', '=', 'loan_disbursements.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (!empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (!empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (!empty($filters['loan_officer_id'])) {
            $q->where('loan_disbursements.processed_by', (int) $filters['loan_officer_id']);
        }

        return [
            'loans_disbursed' => (int) (clone $q)->distinct()->count('loan_disbursements.loan_id'),
            'total_amount' => (float) (clone $q)->sum('loan_disbursements.amount'),
        ];
    }

    private function repaymentMetrics(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $q = LoanPayments::query()
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('loan_payments.status', 'confirmed')
            ->whereBetween('loan_payments.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (!empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (!empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (!empty($filters['loan_officer_id'])) {
            // filter to loans belonging to officer portfolio
            $latestDisbursement = DB::table('loan_disbursements as ld')
                ->selectRaw('MAX(ld.id) as id, ld.loan_id')
                ->groupBy('ld.loan_id');

            $q->joinSub($latestDisbursement, 'ld_latest', function ($j) {
                $j->on('ld_latest.loan_id', '=', 'loans.id');
            })
                ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
                ->where('ld.processed_by', (int) $filters['loan_officer_id']);
        }

        return [
            'total_collected' => (float) (clone $q)->sum('loan_payments.amount'),
        ];
    }

    private function portfolioAtRisk(array $filters, array $subshopIds, float $portfolioOutstanding): array
    {
        $asOf = Carbon::today()->toDateString();

        $installments = LoanInstallments::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<=', $asOf)
            ->selectRaw('loan_id, MAX(DATEDIFF(?, due_date)) as days_overdue, SUM(COALESCE(outstanding_amount,0)) as outstanding', [$asOf])
            ->groupBy('loan_id')
            ->get();

        $buckets = [
            'current' => 0.0,
            'par_1_30' => 0.0,
            'par_31_60' => 0.0,
            'par_61_90' => 0.0,
            'par_over_90' => 0.0,
        ];

        foreach ($installments as $row) {
            $days = (int) ($row->days_overdue ?? 0);
            $out = (float) ($row->outstanding ?? 0);
            if ($days <= 0) {
                continue;
            }
            if ($days <= 30) {
                $buckets['par_1_30'] += $out;
            } elseif ($days <= 60) {
                $buckets['par_31_60'] += $out;
            } elseif ($days <= 90) {
                $buckets['par_61_90'] += $out;
            } else {
                $buckets['par_over_90'] += $out;
            }
        }

        $parTotal = array_sum($buckets) - $buckets['current'];
        $current = max(0.0, $portfolioOutstanding - $parTotal);
        $buckets['current'] = $current;

        $rows = [
            ['bucket' => 'Current', 'outstanding' => round($buckets['current'], 2)],
            ['bucket' => 'PAR 1–30', 'outstanding' => round($buckets['par_1_30'], 2)],
            ['bucket' => 'PAR 31–60', 'outstanding' => round($buckets['par_31_60'], 2)],
            ['bucket' => 'PAR 61–90', 'outstanding' => round($buckets['par_61_90'], 2)],
            ['bucket' => 'PAR > 90', 'outstanding' => round($buckets['par_over_90'], 2)],
        ];

        return array_map(function ($r) use ($portfolioOutstanding) {
            $out = (float) $r['outstanding'];
            $r['pct'] = $portfolioOutstanding > 0 ? round(($out / $portfolioOutstanding) * 100, 2) : 0.0;
            return $r;
        }, $rows);
    }

    private function portfolioAging(array $filters, array $subshopIds): array
    {
        // Same as PAR buckets (aging by overdue days)
        $asOf = Carbon::today()->toDateString();

        $rows = LoanInstallments::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereDate('due_date', '<=', $asOf)
            ->selectRaw("CASE 
                WHEN status != 'overdue' THEN 'Current'
                WHEN DATEDIFF(?, due_date) BETWEEN 1 AND 30 THEN '1–30 days'
                WHEN DATEDIFF(?, due_date) BETWEEN 31 AND 60 THEN '31–60 days'
                WHEN DATEDIFF(?, due_date) BETWEEN 61 AND 90 THEN '61–90 days'
                ELSE '90+ days' END as bucket", [$asOf, $asOf, $asOf])
            ->selectRaw('SUM(COALESCE(outstanding_amount,0)) as outstanding')
            ->groupBy('bucket')
            ->get();

        $order = ['Current', '1–30 days', '31–60 days', '61–90 days', '90+ days'];

        $map = $rows->mapWithKeys(fn ($r) => [(string) $r->bucket => (float) $r->outstanding]);

        return collect($order)->map(function ($b) use ($map) {
            return [
                'bucket' => $b,
                'outstanding' => round((float) ($map[$b] ?? 0.0), 2),
            ];
        })->values()->all();
    }

    private function disbursementAnalysis(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $q = DB::table('loan_disbursements as ld')
            ->join('loans', 'loans.id', '=', 'ld.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('ld.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (!empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (!empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (!empty($filters['loan_officer_id'])) {
            $q->where('ld.processed_by', (int) $filters['loan_officer_id']);
        }

        $rows = $q
            ->selectRaw("DATE_FORMAT(ld.disbursement_date, '%Y-%m') as ym, COUNT(DISTINCT ld.loan_id) as loans_disbursed, SUM(ld.amount) as amount")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        return $rows->map(function ($r) {
            $loans = (int) $r->loans_disbursed;
            $amount = (float) $r->amount;
            return [
                'month' => (string) $r->ym,
                'loans_disbursed' => $loans,
                'amount' => round($amount, 2),
                'avg_amount' => $loans > 0 ? round($amount / $loans, 2) : 0.0,
            ];
        })->values()->all();
    }

    private function repaymentPerformance(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $expectedQ = LoanInstallments::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereBetween('due_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $expected = (float) $expectedQ->sum('total_due');

        $collected = (float) DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('lp.amount');

        $eff = $expected > 0 ? round($collected / $expected, 4) : 0.0;

        return [
            'expected' => round($expected, 2),
            'collected' => round($collected, 2),
            'efficiency' => $eff,
            'efficiency_pct' => round($eff * 100, 2),
        ];
    }

    private function topBorrowers(Builder $loanBase): array
    {
        $activeStatuses = ['disbursed', 'partially_paid', 'defaulted'];

        $rows = (clone $loanBase)
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as loan_count, SUM(COALESCE(outstanding_balance,0)) as outstanding')
            ->groupBy('customer_id')
            ->orderByDesc('outstanding')
            ->limit(10)
            ->get();

        $customers = DB::table('customers')
            ->whereIn('id', $rows->pluck('customer_id')->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        return $rows->map(function ($r) use ($customers) {
            $cid = (int) $r->customer_id;
            return [
                'customer_id' => $cid,
                'customer' => (string) ($customers[$cid]->name ?? 'Unknown'),
                'loan_count' => (int) $r->loan_count,
                'outstanding' => round((float) $r->outstanding, 2),
            ];
        })->values()->all();
    }

    private function portfolioTrends(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        // Month list
        $start = (clone $dateFrom)->startOfMonth();
        $end = (clone $dateTo)->startOfMonth();
        $months = [];
        for ($d = $start->copy(); $d->lte($end); $d->addMonth()) {
            $months[] = $d->format('Y-m');
        }

        $disb = DB::table('loan_disbursements as ld')
            ->join('loans', 'loans.id', '=', 'ld.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('ld.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("DATE_FORMAT(ld.disbursement_date, '%Y-%m') as ym, SUM(ld.amount) as amount")
            ->groupBy('ym')
            ->pluck('amount', 'ym');

        $rep = DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("DATE_FORMAT(lp.payment_date, '%Y-%m') as ym, SUM(lp.amount) as amount")
            ->groupBy('ym')
            ->pluck('amount', 'ym');

        // PAR30 trend computed as of each month end (small loop)
        $par30Trend = [];
        foreach ($months as $ym) {
            $asOf = Carbon::createFromFormat('Y-m', $ym)->endOfMonth()->toDateString();
            $par30 = (float) LoanInstallments::query()
                ->whereIn('subshop_id', $subshopIds)
                ->where('is_active', true)
                ->where('status', 'overdue')
                ->whereDate('due_date', '<', Carbon::parse($asOf)->subDays(30)->toDateString())
                ->sum('outstanding_amount');
            $par30Trend[$ym] = $par30;
        }

        // Outstanding trend: sum of installment outstanding amounts as of month-end
        $outTrend = [];
        foreach ($months as $ym) {
            $asOf = Carbon::createFromFormat('Y-m', $ym)->endOfMonth()->toDateString();
            $out = (float) LoanInstallments::query()
                ->whereIn('subshop_id', $subshopIds)
                ->where('is_active', true)
                ->whereDate('due_date', '<=', $asOf)
                ->sum('outstanding_amount');
            $outTrend[$ym] = $out;
        }

        return [
            'labels' => $months,
            'portfolio_outstanding' => array_map(fn ($m) => round((float) ($outTrend[$m] ?? 0.0), 2), $months),
            'disbursements' => array_map(fn ($m) => round((float) ($disb[$m] ?? 0.0), 2), $months),
            'repayments' => array_map(fn ($m) => round((float) ($rep[$m] ?? 0.0), 2), $months),
            'par30' => array_map(fn ($m) => round((float) ($par30Trend[$m] ?? 0.0), 2), $months),
        ];
    }

    private function par30ByBranch(array $subshopIds): array
    {
        $cutoff = Carbon::today()->subDays(30)->toDateString();

        return LoanInstallments::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $cutoff)
            ->selectRaw('subshop_id, SUM(COALESCE(outstanding_amount,0)) as outstanding')
            ->groupBy('subshop_id')
            ->pluck('outstanding', 'subshop_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
