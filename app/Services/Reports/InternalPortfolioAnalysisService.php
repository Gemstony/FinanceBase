<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Customers;
use App\Models\LoanInstallments;
use App\Models\LoanInterestAccruals;
use App\Models\LoanPayments;
use App\Models\LoanProducts;
use App\Models\Loans;
use App\Models\SubShop;
use App\Models\User;
use App\Services\Loans\Risk\LoanDelinquencyEngine;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InternalPortfolioAnalysisService
{
    public function __construct(
        private readonly PortfolioRiskCalculator $portfolioRiskCalculator,
        private readonly LoanDelinquencyEngine $delinquencyEngine,
    ) {}

    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);

        $loanBase = $this->filteredLoansQuery($filters, $subshopIds);
        $loanIds = (clone $loanBase)->pluck('loans.id');

        $asAt = (clone $dateTo)->endOfDay();
        $loanAgg = $this->loanAggAsAt($subshopIds, $loanIds, $asAt);

        $portfolioOutstanding = $this->calculatePortfolioOutstanding($subshopIds, $loanIds);

        $collection = $this->collectionEfficiency($subshopIds, $dateFrom, $dateTo, $loanIds);
        $par30Pct = $this->parPct($subshopIds, $loanIds, $portfolioOutstanding, 30);
        $defaultRatePct = $this->defaultRatePct($subshopIds, $loanIds);

        $health = $this->portfolioHealthScore($par30Pct, (float) ($collection['efficiency_pct'] ?? 0.0), $defaultRatePct);

        $profitability = $this->productProfitability($subshopIds, $dateFrom, $dateTo, $loanIds);
        $riskReturn = $this->riskVsReturn($profitability);

        $officerPerformance = $this->officerPerformance($filters, $subshopIds, $dateFrom, $dateTo, $loanIds, $loanAgg);
        $customerSegmentation = $this->customerSegmentation($subshopIds, $loanIds, $loanAgg, $portfolioOutstanding);
        $loanCycle = $this->loanCycleAnalysis($subshopIds, $dateFrom, $dateTo, $filters);
        $cohorts = $this->cohortAnalysis($subshopIds, $dateFrom, $dateTo, $filters);
        $growthVsRisk = $this->growthVsRisk($subshopIds, $dateFrom, $dateTo, $filters);
        $incomeVsPortfolio = $this->incomeVsPortfolio($subshopIds, $dateFrom, $dateTo, $growthVsRisk);
        $concentration = $this->concentrationRisk($subshopIds, $loanIds, $loanAgg, $portfolioOutstanding);
        $behavior = $this->behavioralRisk($subshopIds, $dateFrom, $dateTo, $loanIds);
        $cross = $this->crossAnalysis($filters, $subshopIds, $loanIds, $loanAgg);
        $earlyWarning = $this->earlyWarningIndicators($growthVsRisk, $collection);
        $insights = $this->strategicInsights($riskReturn, $growthVsRisk, $earlyWarning);

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_ids' => $subshopIds,
            ],
            'as_at_date' => $asAt->toDateTimeString(),
            'summary' => [
                'portfolio_outstanding' => round($portfolioOutstanding, 2),
                'par30_pct' => round($par30Pct, 2),
                'collection_efficiency_pct' => round((float) ($collection['efficiency_pct'] ?? 0.0), 2),
                'default_rate_pct' => round($defaultRatePct, 2),
                'health_score_pct' => round((float) ($health['score_pct'] ?? 0.0), 2),
                'health_category' => (string) ($health['category'] ?? ''),
            ],
            'portfolio_health' => $health,
            'collection_efficiency' => $collection,
            'profitability_by_product' => $profitability,
            'risk_vs_return' => $riskReturn,
            'officer_performance' => $officerPerformance,
            'customer_segmentation' => $customerSegmentation,
            'loan_cycle_analysis' => $loanCycle,
            'cohort_analysis' => $cohorts,
            'growth_vs_risk' => $growthVsRisk,
            'income_vs_portfolio' => $incomeVsPortfolio,
            'concentration_risk' => $concentration,
            'early_warning' => $earlyWarning,
            'behavioral_risk' => $behavior,
            'cross_analysis' => $cross,
            'strategic_insights' => $insights,
            'charts' => $this->charts($growthVsRisk, $profitability, $officerPerformance, $customerSegmentation),
        ];
    }

    private function resolveSubshopFilter(?int $subshopId, array $accessibleSubshopIds): array
    {
        if ($subshopId) {
            return in_array($subshopId, $accessibleSubshopIds, true) ? [$subshopId] : [-1];
        }

        return $accessibleSubshopIds ?: [-1];
    }

    private function filteredLoansQuery(array $filters, array $subshopIds): Builder
    {
        $q = Loans::query()->from('loans')->whereIn('loans.subshop_id', $subshopIds);

        if (! empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }

        if (! empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
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

    private function calculatePortfolioOutstanding(array $subshopIds, $loanIds): float
    {
        // Use delinquencyEngine to respect loanIds filters and avoid N+1
        return $this->delinquencyEngine->calculatePortfolioOutstandingFromInstallments($subshopIds, $loanIds);
    }

    private function loanAggAsAt(array $subshopIds, $loanIds, Carbon $asAt)
    {
        $asAtDate = $asAt->toDateString();

        $overdueByLoan = DB::table('loan_installments as li')
            ->whereIn('li.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereDate('li.due_date', '<=', $asAtDate)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id, SUM(CASE WHEN li.status = "overdue" THEN COALESCE(li.total_due,0) ELSE 0 END) as overdue_amount')
            ->groupBy('li.loan_id');

        $dpdByLoan = DB::table('loan_installments as li')
            ->whereIn('li.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereDate('li.due_date', '<=', $asAtDate)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id, MAX(CASE WHEN li.status = "overdue" THEN DATEDIFF(?, li.due_date) ELSE 0 END) as dpd', [$asAtDate])
            ->groupBy('li.loan_id');

        $activeStatuses = ['disbursed', 'partially_paid', 'defaulted'];

        return DB::table('loans as l')
            ->whereIn('l.subshop_id', $subshopIds)
            ->whereIn('l.status', $activeStatuses)
            ->where('l.is_written_off', false)
            ->where('l.is_active', true)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('l.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->leftJoinSub($overdueByLoan, 'o', function ($j) {
                $j->on('o.loan_id', '=', 'l.id');
            })
            ->leftJoinSub($dpdByLoan, 'd', function ($j) {
                $j->on('d.loan_id', '=', 'l.id');
            })
            ->selectRaw('l.id as loan_id, COALESCE(d.dpd,0) as dpd, COALESCE(o.overdue_amount,0) as overdue_amount, 0 as outstanding_balance');
    }

    private function parPct(array $subshopIds, $loanIds, float $portfolioOutstanding, int $dpdThreshold): float
    {
        if ($portfolioOutstanding <= 0) {
            return 0.0;
        }

        // Use delinquencyEngine for correct max_dpd >= threshold calculation (replaces N+1)
        $base = $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, Carbon::today());
        $parOut = (float) DB::query()->fromSub($base, 'd')
            ->where('d.max_dpd', '>=', $dpdThreshold)
            ->sum('d.outstanding_balance');

        return round(($parOut / $portfolioOutstanding) * 100, 2);
    }

    private function defaultRatePct(array $subshopIds, $loanIds): float
    {
        $totalLoans = (int) Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('is_written_off', false)
            ->where('is_active', true)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->count('id');

        // Use delinquencyEngine for correct max_dpd >= 90 calculation (replaces due_date check)
        $defaultedLoans = (int) DB::query()
            ->fromSub($this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, Carbon::today()), 'd')
            ->where('d.max_dpd', '>=', 90)
            ->count('d.loan_id');

        return $totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0.0;
    }

    private function portfolioHealthScore(float $par30Pct, float $collectionEfficiencyPct, float $defaultRatePct): array
    {
        $parComponent = (1 - min(max($par30Pct, 0), 100) / 100) * 50;
        $collectionComponent = (min(max($collectionEfficiencyPct, 0), 100) / 100) * 30;
        $defaultComponent = (1 - min(max($defaultRatePct, 0), 100) / 100) * 20;

        $score = round($parComponent + $collectionComponent + $defaultComponent, 2);

        $category = 'Risky';
        if ($score >= 75) {
            $category = 'Good';
        } elseif ($score >= 50) {
            $category = 'Moderate';
        }

        return [
            'score_pct' => $score,
            'category' => $category,
            'components' => [
                'par_component' => round($parComponent, 2),
                'collection_component' => round($collectionComponent, 2),
                'default_component' => round($defaultComponent, 2),
            ],
            'inputs' => [
                'par30_pct' => round($par30Pct, 2),
                'collection_efficiency_pct' => round($collectionEfficiencyPct, 2),
                'default_rate_pct' => round($defaultRatePct, 2),
            ],
        ];
    }

    private function collectionEfficiency(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $expected = (float) LoanInstallments::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereBetween('due_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->sum('total_due');

        $collected = (float) LoanPayments::query()
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('loan_payments.status', 'confirmed')
            ->whereBetween('loan_payments.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_payments.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->sum('loan_payments.amount');

        $eff = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0.0;

        return [
            'expected' => round($expected, 2),
            'collected' => round($collected, 2),
            'efficiency_pct' => $eff,
        ];
    }

    private function productProfitability(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $asAt = (clone $dateTo)->endOfDay();

        // Use delinquencyEngine as single source of truth for delinquency data
        $delinq = $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, $asAt);

        // Portfolio outstanding per product using SQL (replaces N+1 loop)
        $byProduct = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw('SUM(d.outstanding_balance) as outstanding')
            ->groupBy('loans.loan_product_id')
            ->pluck('outstanding', 'product_id');

        // PAR30 outstanding per product using SQL with max_dpd >= 30 (replaces N+1 loop)
        $par30ByProduct = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->where('d.max_dpd', '>=', 30)
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw('SUM(d.outstanding_balance) as par30_outstanding')
            ->groupBy('loans.loan_product_id')
            ->pluck('par30_outstanding', 'product_id');

        $accrued = LoanInterestAccruals::query()
            ->join('loans', 'loans.id', '=', 'loan_interest_accruals.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('loan_interest_accruals.is_active', true)
            ->whereBetween('loan_interest_accruals.accrual_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_interest_accruals.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('loans.loan_product_id, SUM(COALESCE(loan_interest_accruals.daily_interest,0)) as interest_earned')
            ->groupBy('loans.loan_product_id')
            ->pluck('interest_earned', 'loan_product_id');

        $allocBase = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        $fees = (clone $allocBase)
            ->selectRaw('loans.loan_product_id, SUM(COALESCE(lpa.fee_amount,0)) as fees')
            ->groupBy('loans.loan_product_id')
            ->pluck('fees', 'loan_product_id');

        $penalties = (clone $allocBase)
            ->selectRaw('loans.loan_product_id, SUM(COALESCE(lpa.penalty_amount,0)) as penalties')
            ->groupBy('loans.loan_product_id')
            ->pluck('penalties', 'loan_product_id');

        $products = LoanProducts::query()
            ->whereIn('subshop_id', $subshopIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $rows = [];
        foreach ($products as $pid => $p) {
            $interest = (float) ($accrued[$pid] ?? 0.0);
            $fee = (float) ($fees[$pid] ?? 0.0);
            $pen = (float) ($penalties[$pid] ?? 0.0);
            $rev = $interest + $fee + $pen;
            $cost = 0.0;
            $profit = $rev - $cost;

            $total = (float) ($byProduct[(int) $pid] ?? 0.0);
            $par30Out = (float) ($par30ByProduct[(int) $pid] ?? 0.0);
            $par30Pct = $total > 0 ? round(($par30Out / $total) * 100, 2) : 0.0;

            $rows[] = [
                'product_id' => (int) $pid,
                'product' => (string) ($p->name ?? 'Unknown'),
                'interest_earned' => round($interest, 2),
                'fees_collected' => round($fee, 2),
                'penalties_collected' => round($pen, 2),
                'revenue' => round($rev, 2),
                'estimated_cost' => round($cost, 2),
                'profit' => round($profit, 2),
                'par30_pct' => $par30Pct,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['profit'] <=> $a['profit']));

        return $rows;
    }

    private function riskVsReturn(array $profitabilityRows): array
    {
        $rows = [];
        foreach ($profitabilityRows as $r) {
            $par30 = (float) ($r['par30_pct'] ?? 0.0);
            $riskLevel = 'Low';
            if ($par30 >= 15) {
                $riskLevel = 'High';
            } elseif ($par30 >= 5) {
                $riskLevel = 'Medium';
            }

            $rows[] = [
                'product_id' => (int) ($r['product_id'] ?? 0),
                'product' => (string) ($r['product'] ?? ''),
                'profit' => (float) ($r['profit'] ?? 0.0),
                'par30_pct' => round((float) ($r['par30_pct'] ?? 0.0), 2),
                'risk_level' => $riskLevel,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['profit'] <=> $a['profit']));

        return $rows;
    }

    private function officerPerformance(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds, $loanAgg): array
    {
        $activeStatuses = ['disbursed', 'partially_paid', 'defaulted'];
        $asAt = (clone $dateTo)->endOfDay();

        // Use delinquencyEngine as single source of truth for delinquency data
        $delinq = $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, $asAt);

        $latestDisbursement = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        // Get loan-to-officer mapping for collection efficiency
        $loanOfficerMap = DB::table('loans')
            ->joinSub($latestDisbursement, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', $activeStatuses)
            ->where('loans.is_written_off', false)
            ->where('loans.is_active', true)
            ->when(! empty($filters['loan_product_id']), fn ($q) => $q->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('loans.id as loan_id, ld.processed_by as officer_id')
            ->pluck('officer_id', 'loan_id');

        $officerIds = $loanOfficerMap->values()->unique()->filter()->values();
        $officers = User::query()->whereIn('id', $officerIds)->get(['id', 'name'])->keyBy('id');

        // Portfolio outstanding per officer using SQL (replaces N+1 loop)
        $portfolioByOfficer = DB::table('loans')
            ->joinSub($latestDisbursement, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->joinSub($delinq, 'd', fn ($j) => $j->on('d.loan_id', '=', 'loans.id'))
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', $activeStatuses)
            ->where('loans.is_written_off', false)
            ->where('loans.is_active', true)
            ->when(! empty($filters['loan_product_id']), fn ($q) => $q->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('ld.processed_by as officer_id, SUM(d.outstanding_balance) as total, COUNT(DISTINCT loans.id) as loans_count')
            ->groupBy('ld.processed_by')
            ->get()->keyBy('officer_id');

        // PAR30 outstanding per officer using SQL with max_dpd >= 30 (replaces N+1 loop)
        $par30ByOfficer = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->joinSub($latestDisbursement, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->where('d.max_dpd', '>=', 30)
            ->when(! empty($filters['loan_product_id']), fn ($q) => $q->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('ld.processed_by as officer_id, SUM(d.outstanding_balance) as par30_outstanding')
            ->groupBy('ld.processed_by')
            ->pluck('par30_outstanding', 'officer_id');

        $disbursedByOfficer = DB::table('loan_disbursements as ld')
            ->join('loans', 'loans.id', '=', 'ld.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('ld.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('ld.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('ld.processed_by as officer_id, COUNT(DISTINCT ld.loan_id) as disbursed')
            ->groupBy('officer_id')
            ->pluck('disbursed', 'officer_id');

        $effByOfficer = $this->collectionEfficiencyByOfficer($subshopIds, $dateFrom, $dateTo, $loanOfficerMap);

        $rows = [];
        foreach ($portfolioByOfficer as $oid => $r) {
            $total = (float) $r->total;
            $par30Out = (float) ($par30ByOfficer[$oid] ?? 0.0);
            $par30 = $total > 0 ? round(($par30Out / $total) * 100, 2) : 0.0;
            $eff = (float) ($effByOfficer[$oid]['efficiency_pct'] ?? 0.0);

            $score = $this->officerScore($par30, $eff, (int) ($disbursedByOfficer[$oid] ?? 0));

            $rows[] = [
                'officer_id' => (int) $oid,
                'officer' => (string) ($officers[$oid]->name ?? 'Unknown'),
                'total_portfolio' => round($total, 2),
                'par30_pct' => $par30,
                'collection_efficiency_pct' => round($eff, 2),
                'loans_disbursed' => (int) ($disbursedByOfficer[$oid] ?? 0),
                'score_pct' => $score,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['score_pct'] <=> $a['score_pct']));

        return $rows;
    }

    private function officerScore(float $par30Pct, float $collectionEfficiencyPct, int $loansDisbursed): float
    {
        $parComponent = (1 - min(max($par30Pct, 0), 100) / 100) * 50;
        $collectionComponent = (min(max($collectionEfficiencyPct, 0), 100) / 100) * 35;
        $disbComponent = min(max($loansDisbursed, 0), 100) / 100 * 15;

        return round($parComponent + $collectionComponent + $disbComponent, 2);
    }

    private function collectionEfficiencyByOfficer(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanToOfficer): array
    {
        $loanIds = $loanToOfficer->keys()->values();

        $expectedByLoan = DB::table('loan_installments as li')
            ->whereIn('li.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id, SUM(COALESCE(li.total_due,0)) as expected')
            ->groupBy('li.loan_id')
            ->pluck('expected', 'loan_id');

        $collectedByLoan = DB::table('loan_payments as lp')
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('lp.loan_id, SUM(COALESCE(lp.amount,0)) as collected')
            ->groupBy('lp.loan_id')
            ->pluck('collected', 'loan_id');

        $officerAgg = [];
        foreach ($loanToOfficer as $loanId => $oid) {
            $oid = (int) $oid;
            if (! $oid) {
                continue;
            }
            if (! isset($officerAgg[$oid])) {
                $officerAgg[$oid] = ['expected' => 0.0, 'collected' => 0.0];
            }
            $officerAgg[$oid]['expected'] += (float) ($expectedByLoan[$loanId] ?? 0.0);
            $officerAgg[$oid]['collected'] += (float) ($collectedByLoan[$loanId] ?? 0.0);
        }

        $out = [];
        foreach ($officerAgg as $oid => $v) {
            $exp = (float) $v['expected'];
            $col = (float) $v['collected'];
            $out[$oid] = [
                'expected' => round($exp, 2),
                'collected' => round($col, 2),
                'efficiency_pct' => $exp > 0 ? round(($col / $exp) * 100, 2) : 0.0,
            ];
        }

        return $out;
    }

    private function customerSegmentation(array $subshopIds, $loanIds, $loanAgg, float $portfolioOutstanding): array
    {
        $asAt = Carbon::today();

        // Use delinquencyEngine as single source of truth for delinquency data
        $delinq = $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, $asAt);

        // Get customers with loans
        $loans = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('is_written_off', false)
            ->where('is_active', true)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->get(['id', 'customer_id']);

        $customerIds = $loans->pluck('customer_id')->filter()->unique()->values();

        // Customer loan counts for segment classification
        $customerLoanCounts = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, COUNT(*) as cnt')
            ->groupBy('customer_id')
            ->pluck('cnt', 'customer_id');

        // Portfolio exposure per customer using SQL (replaces N+1 loop)
        $exposureByCustomer = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->whereIn('loans.customer_id', $customerIds)
            ->selectRaw('loans.customer_id as customer_id, SUM(d.outstanding_balance) as outstanding, COUNT(*) as loan_count')
            ->groupBy('loans.customer_id')
            ->get()->keyBy('customer_id');

        // PAR30 risk per customer using SQL with max_dpd >= 30 (replaces N+1 loop)
        $riskOutByCustomer = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->where('d.max_dpd', '>=', 30)
            ->whereIn('loans.customer_id', $customerIds)
            ->selectRaw('loans.customer_id as customer_id, SUM(d.outstanding_balance) as par30_outstanding')
            ->groupBy('loans.customer_id')
            ->pluck('par30_outstanding', 'customer_id');

        $exposures = $exposureByCustomer->pluck('outstanding')->all();
        sort($exposures);
        $vipThreshold = 0.0;
        if (count($exposures) > 0) {
            $idx = (int) floor((count($exposures) - 1) * 0.9);
            $vipThreshold = (float) ($exposures[$idx] ?? 0.0);
        }

        $segments = [
            'New Borrowers' => ['customers' => 0, 'portfolio' => 0.0, 'risk_outstanding' => 0.0],
            'Repeat Borrowers' => ['customers' => 0, 'portfolio' => 0.0, 'risk_outstanding' => 0.0],
            'High-Risk Borrowers' => ['customers' => 0, 'portfolio' => 0.0, 'risk_outstanding' => 0.0],
            'VIP' => ['customers' => 0, 'portfolio' => 0.0, 'risk_outstanding' => 0.0],
        ];

        foreach ($customerIds as $cid) {
            $cid = (int) $cid;
            $loanCountAllTime = (int) ($customerLoanCounts[$cid] ?? 0);
            $expData = $exposureByCustomer->get($cid);
            $portfolio = (float) ($expData?->outstanding ?? 0.0);
            $risk = (float) ($riskOutByCustomer[$cid] ?? 0.0);

            $isHighRisk = $portfolio > 0 && (($risk / $portfolio) * 100) >= 10;
            $isVip = $portfolio >= $vipThreshold && ! $isHighRisk;

            if ($isVip) {
                $segments['VIP']['customers'] += 1;
                $segments['VIP']['portfolio'] += $portfolio;
                $segments['VIP']['risk_outstanding'] += $risk;
            } elseif ($isHighRisk) {
                $segments['High-Risk Borrowers']['customers'] += 1;
                $segments['High-Risk Borrowers']['portfolio'] += $portfolio;
                $segments['High-Risk Borrowers']['risk_outstanding'] += $risk;
            } elseif ($loanCountAllTime <= 1) {
                $segments['New Borrowers']['customers'] += 1;
                $segments['New Borrowers']['portfolio'] += $portfolio;
                $segments['New Borrowers']['risk_outstanding'] += $risk;
            } else {
                $segments['Repeat Borrowers']['customers'] += 1;
                $segments['Repeat Borrowers']['portfolio'] += $portfolio;
                $segments['Repeat Borrowers']['risk_outstanding'] += $risk;
            }
        }

        $rows = [];
        foreach ($segments as $name => $v) {
            $portfolio = (float) $v['portfolio'];
            $risk = (float) $v['risk_outstanding'];
            $par30 = $portfolio > 0 ? round(($risk / $portfolio) * 100, 2) : 0.0;

            $rows[] = [
                'segment' => $name,
                'customers' => (int) $v['customers'],
                'portfolio' => round($portfolio, 2),
                'par30_pct' => $par30,
                'pct_of_portfolio' => $portfolioOutstanding > 0 ? round(($portfolio / $portfolioOutstanding) * 100, 2) : 0.0,
            ];
        }

        return $rows;
    }

    private function loanCycleAnalysis(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, array $filters): array
    {
        $disbursedLoans = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->whereBetween('disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereNotNull('customer_id')
            ->when(! empty($filters['loan_product_id']), fn ($q) => $q->where('loan_product_id', (int) $filters['loan_product_id']))
            ->get(['id', 'customer_id', 'principal_amount']);

        $customerIds = $disbursedLoans->pluck('customer_id')->filter()->unique()->values();

        $counts = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, COUNT(*) as cnt')
            ->groupBy('customer_id')
            ->pluck('cnt', 'customer_id');

        $buckets = [
            '1st Loan' => ['loans' => 0, 'total_principal' => 0.0],
            '2nd Loan' => ['loans' => 0, 'total_principal' => 0.0],
            '3rd+ Loan' => ['loans' => 0, 'total_principal' => 0.0],
        ];

        foreach ($disbursedLoans as $l) {
            $cid = (int) $l->customer_id;
            $c = (int) ($counts[$cid] ?? 0);
            $bucket = $c <= 1 ? '1st Loan' : ($c === 2 ? '2nd Loan' : '3rd+ Loan');
            $buckets[$bucket]['loans'] += 1;
            $buckets[$bucket]['total_principal'] += (float) ($l->principal_amount ?? 0.0);
        }

        $rows = [];
        foreach ($buckets as $name => $v) {
            $loans = (int) $v['loans'];
            $total = (float) $v['total_principal'];
            $rows[] = [
                'cycle' => $name,
                'loans' => $loans,
                'avg_loan_size' => $loans > 0 ? round($total / $loans, 2) : 0.0,
            ];
        }

        return $rows;
    }

    private function cohortAnalysis(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, array $filters): array
    {
        $loans = Loans::query()
            ->whereIn('subshop_id', $subshopIds)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('is_written_off', false)
            ->where('is_active', true)
            ->whereBetween('disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when(! empty($filters['loan_product_id']), fn ($q) => $q->where('loan_product_id', (int) $filters['loan_product_id']))
            ->get(['id', 'disbursement_date']);

        $byMonth = [];
        foreach ($loans as $l) {
            $ym = $l->disbursement_date ? Carbon::parse($l->disbursement_date)->format('Y-m') : null;
            if (! $ym) {
                continue;
            }
            $byMonth[$ym] = $byMonth[$ym] ?? ['loan_ids' => [], 'count' => 0];
            $byMonth[$ym]['loan_ids'][] = (int) $l->id;
            $byMonth[$ym]['count'] += 1;
        }

        ksort($byMonth);

        $asAt = (clone $dateTo)->endOfDay();
        $rows = [];
        foreach ($byMonth as $ym => $v) {
            $loanIds = collect($v['loan_ids']);
            $portfolio = $this->calculatePortfolioOutstanding($subshopIds, $loanIds);
            $par30 = $this->parPct($subshopIds, $loanIds, $portfolio, 30);

            $rows[] = [
                'cohort_month' => $ym,
                'loans_disbursed' => (int) $v['count'],
                'portfolio_outstanding' => round($portfolio, 2),
                'par30_pct' => round($par30, 2),
            ];
        }

        return $rows;
    }

    private function growthVsRisk(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, array $filters): array
    {
        $start = (clone $dateFrom)->startOfMonth();
        $end = (clone $dateTo)->startOfMonth();

        $months = [];
        for ($d = $start->copy(); $d->lte($end); $d->addMonth()) {
            $months[] = $d->copy();
        }

        $rows = [];
        foreach ($months as $m) {
            $asAt = $m->copy()->endOfMonth();
            if ($asAt->gt($dateTo)) {
                $asAt = $dateTo->copy();
            }

            $loanQ = Loans::query()
                ->whereIn('subshop_id', $subshopIds)
                ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
                ->where('is_written_off', false)
                ->where('is_active', true)
                ->whereDate('disbursement_date', '<=', $asAt->toDateString())
                ->when(! empty($filters['loan_product_id']), fn ($q) => $q->where('loan_product_id', (int) $filters['loan_product_id']))
                ->when(! empty($filters['loan_status']), fn ($q) => $q->where('status', (string) $filters['loan_status']));

            $loanIds = $loanQ->pluck('id');
            $loanAgg = $this->loanAggAsAt($subshopIds, $loanIds, $asAt);
            $portfolio = $this->calculatePortfolioOutstanding($subshopIds, $loanIds);
            $par30 = $this->parPct($subshopIds, $loanIds, $portfolio, 30);
            $avgDpd = (float) DB::query()->fromSub($loanAgg, 'la')->avg('la.dpd');

            $rows[] = [
                'month' => $asAt->format('Y-m'),
                'portfolio_outstanding' => round($portfolio, 2),
                'par30_pct' => round($par30, 2),
                'avg_dpd' => round($avgDpd, 2),
            ];
        }

        return $rows;
    }

    private function incomeVsPortfolio(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, array $growthVsRisk): array
    {
        $income = (float) LoanInterestAccruals::query()
            ->join('loans', 'loans.id', '=', 'loan_interest_accruals.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('loan_interest_accruals.is_active', true)
            ->whereBetween('loan_interest_accruals.accrual_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('loan_interest_accruals.daily_interest');

        $portfolios = array_column($growthVsRisk, 'portfolio_outstanding');
        $avgPortfolio = count($portfolios) > 0 ? array_sum($portfolios) / count($portfolios) : 0.0;

        $yield = $avgPortfolio > 0 ? round(($income / $avgPortfolio) * 100, 2) : 0.0;

        return [
            'interest_income' => round($income, 2),
            'avg_portfolio' => round($avgPortfolio, 2),
            'yield_pct' => $yield,
        ];
    }

    private function concentrationRisk(array $subshopIds, $loanIds, $loanAgg, float $portfolioOutstanding): array
    {
        $asAt = Carbon::today();

        // Use delinquencyEngine as single source of truth for outstanding data
        $delinq = $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, $asAt);

        // Portfolio outstanding by customer using SQL (replaces N+1 loop)
        $byCustomer = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->whereNotNull('loans.customer_id')
            ->selectRaw('loans.customer_id as customer_id, SUM(d.outstanding_balance) as outstanding')
            ->groupBy('loans.customer_id')
            ->pluck('outstanding', 'customer_id')
            ->all();

        // Portfolio outstanding by branch using SQL (replaces N+1 loop)
        $byBranch = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->whereNotNull('loans.subshop_id')
            ->selectRaw('loans.subshop_id as subshop_id, SUM(d.outstanding_balance) as outstanding')
            ->groupBy('loans.subshop_id')
            ->pluck('outstanding', 'subshop_id')
            ->all();

        // Portfolio outstanding by product using SQL (replaces N+1 loop)
        $byProduct = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->whereNotNull('loans.loan_product_id')
            ->selectRaw('loans.loan_product_id as product_id, SUM(d.outstanding_balance) as outstanding')
            ->groupBy('loans.loan_product_id')
            ->pluck('outstanding', 'product_id')
            ->all();

        arsort($byCustomer);
        arsort($byBranch);
        arsort($byProduct);

        $subshops = SubShop::query()->whereIn('id', array_keys($byBranch))->get(['id', 'name'])->keyBy('id');
        $products = LoanProducts::query()->whereIn('id', array_keys($byProduct))->get(['id', 'name'])->keyBy('id');
        $customers = Customers::query()->whereIn('id', array_keys($byCustomer))->get(['id', 'name'])->keyBy('id');

        $topCustomers = array_slice($byCustomer, 0, 10, true);
        $topBranches = array_slice($byBranch, 0, 10, true);
        $topProducts = array_slice($byProduct, 0, 10, true);

        $fmt = function (array $arr, callable $labelFn) use ($portfolioOutstanding) {
            $out = [];
            foreach ($arr as $k => $v) {
                $out[] = [
                    'label' => $labelFn($k),
                    'exposure' => round((float) $v, 2),
                    'pct' => $portfolioOutstanding > 0 ? round(((float) $v / $portfolioOutstanding) * 100, 2) : 0.0,
                ];
            }

            return $out;
        };

        return [
            'top_customers' => $fmt($topCustomers, fn ($cid) => (string) ($customers[(int) $cid]->name ?? ('Customer #'.(int) $cid))),
            'top_branches' => $fmt($topBranches, fn ($sid) => (string) ($subshops[(int) $sid]->name ?? ('Branch #'.(int) $sid))),
            'top_products' => $fmt($topProducts, fn ($pid) => (string) ($products[(int) $pid]->name ?? ('Product #'.(int) $pid))),
        ];
    }

    private function behavioralRisk(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $allocBase = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->whereNotNull('loans.customer_id');

        $lateByCustomer = (clone $allocBase)
            ->whereRaw('lp.payment_date > li.due_date')
            ->selectRaw('loans.customer_id, COUNT(*) as late_count, AVG(DATEDIFF(lp.payment_date, li.due_date)) as avg_days_late')
            ->groupBy('loans.customer_id')
            ->orderByDesc('late_count')
            ->limit(20)
            ->get();

        // Fetch customer names for the late payers
        $customerIds = $lateByCustomer->pluck('customer_id')->filter()->unique()->values()->all();
        $customers = Customers::query()->whereIn('id', $customerIds)->get(['id', 'name'])->keyBy('id');

        $rows = [];
        foreach ($lateByCustomer as $r) {
            $cid = (int) $r->customer_id;
            $rows[] = [
                'customer' => (string) ($customers[$cid]->name ?? ('Customer #'.$cid)),
                'late_payments' => (int) $r->late_count,
                'avg_days_late' => round((float) ($r->avg_days_late ?? 0.0), 2),
            ];
        }

        return [
            'repeat_late_payers' => $rows,
        ];
    }

    private function crossAnalysis(array $filters, array $subshopIds, $loanIds, $loanAgg): array
    {
        $asAt = Carbon::today();
        $latestDisbursement = DB::table('loan_disbursements as ld')
            ->selectRaw('MAX(ld.id) as id, ld.loan_id')
            ->groupBy('ld.loan_id');

        // Use delinquencyEngine as single source of truth for delinquency data
        $delinq = $this->delinquencyEngine->parBaseQuery($subshopIds, $loanIds, $asAt);

        // Cross-analysis totals using SQL aggregation (replaces N+1 loop)
        $totals = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->joinSub($latestDisbursement, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->when(! empty($filters['loan_product_id']), fn ($q) => $q->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('loans.loan_product_id, loans.subshop_id, ld.processed_by as officer_id')
            ->selectRaw('SUM(d.outstanding_balance) as total')
            ->groupBy('loans.loan_product_id', 'loans.subshop_id', 'ld.processed_by')
            ->get();

        // Cross-analysis PAR30 using SQL aggregation with max_dpd >= 30 (replaces N+1 loop)
        $par30Data = DB::query()
            ->fromSub($delinq, 'd')
            ->join('loans', 'loans.id', '=', 'd.loan_id')
            ->joinSub($latestDisbursement, 'ld_latest', fn ($j) => $j->on('ld_latest.loan_id', '=', 'loans.id'))
            ->join('loan_disbursements as ld', 'ld.id', '=', 'ld_latest.id')
            ->where('d.max_dpd', '>=', 30)
            ->when(! empty($filters['loan_product_id']), fn ($q) => $q->where('loans.loan_product_id', (int) $filters['loan_product_id']))
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loans.id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('loans.loan_product_id, loans.subshop_id, ld.processed_by as officer_id')
            ->selectRaw('SUM(d.outstanding_balance) as par30')
            ->groupBy('loans.loan_product_id', 'loans.subshop_id', 'ld.processed_by')
            ->get()
            ->keyBy(fn ($r) => $r->loan_product_id.'|'.$r->subshop_id.'|'.$r->officer_id);

        $bucket = [];
        foreach ($totals as $r) {
            $key = ((int) $r->loan_product_id).'|'.((int) $r->subshop_id).'|'.((int) $r->officer_id);
            $par30Record = $par30Data->get($key);

            $bucket[$key] = [
                'loan_product_id' => (int) $r->loan_product_id,
                'subshop_id' => (int) $r->subshop_id,
                'officer_id' => (int) $r->officer_id,
                'total' => (float) $r->total,
                'par30' => (float) ($par30Record?->par30 ?? 0.0),
            ];
        }

        $subshops = SubShop::query()->whereIn('id', collect($bucket)->pluck('subshop_id')->unique()->values())->get(['id', 'name'])->keyBy('id');
        $products = LoanProducts::query()->whereIn('id', collect($bucket)->pluck('loan_product_id')->unique()->values())->get(['id', 'name'])->keyBy('id');
        $officers = User::query()->whereIn('id', collect($bucket)->pluck('officer_id')->unique()->values())->get(['id', 'name'])->keyBy('id');

        $rows = [];
        foreach ($bucket as $r) {
            $total = (float) $r['total'];
            $par30Pct = $total > 0 ? round(((float) $r['par30'] / $total) * 100, 2) : 0.0;

            $rows[] = [
                'product' => (string) ($products[(int) $r['loan_product_id']]->name ?? 'Unknown'),
                'branch' => (string) ($subshops[(int) $r['subshop_id']]->name ?? 'Unknown'),
                'officer' => (string) ($officers[(int) $r['officer_id']]->name ?? 'Unknown'),
                'par30_pct' => $par30Pct,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['par30_pct'] <=> $a['par30_pct']));

        return array_slice($rows, 0, 50);
    }

    private function earlyWarningIndicators(array $growthVsRisk, array $collection): array
    {
        $par = array_column($growthVsRisk, 'par30_pct');
        $dpd = array_column($growthVsRisk, 'avg_dpd');
        $warnPar = false;
        $warnDpd = false;
        if (count($par) >= 3) {
            $n = count($par);
            $warnPar = ($par[$n - 1] ?? 0) > ($par[$n - 2] ?? 0) && ($par[$n - 2] ?? 0) > ($par[$n - 3] ?? 0);
        }

        if (count($dpd) >= 3) {
            $n = count($dpd);
            $warnDpd = ($dpd[$n - 1] ?? 0) > ($dpd[$n - 2] ?? 0) && ($dpd[$n - 2] ?? 0) > ($dpd[$n - 3] ?? 0);
        }

        $eff = (float) ($collection['efficiency_pct'] ?? 0.0);

        return [
            'flags' => [
                'increasing_par_trend' => $warnPar,
                'rising_avg_dpd' => $warnDpd,
                'declining_collection_efficiency' => $eff < 80,
            ],
            'thresholds' => [
                'collection_efficiency_warning_below' => 80,
            ],
        ];
    }

    private function strategicInsights(array $riskVsReturn, array $growthVsRisk, array $earlyWarning): array
    {
        $insights = [];

        foreach (array_slice($riskVsReturn, 0, 5) as $r) {
            if (($r['risk_level'] ?? '') === 'High') {
                $insights[] = 'Product '.($r['product'] ?? '').' shows high risk (PAR30) relative to return. Consider reviewing lending policy and underwriting.';
            }
        }

        if (! empty($earlyWarning['flags']['increasing_par_trend'])) {
            $insights[] = 'PAR30 trend is increasing across recent months. Consider tightening credit controls and strengthening collections.';
        }

        $last = end($growthVsRisk);
        if ($last && ((float) ($last['par30_pct'] ?? 0.0)) < 5 && ((float) ($last['portfolio_outstanding'] ?? 0.0)) > 0) {
            $insights[] = 'Portfolio growth remains healthy with low PAR30. Consider expansion or scaling high-performing branches/products.';
        }

        return array_values(array_unique($insights));
    }

    private function charts(array $growthVsRisk, array $profitability, array $officers, array $segments): array
    {
        return [
            'growth_vs_risk' => [
                'labels' => array_column($growthVsRisk, 'month'),
                'portfolio' => array_column($growthVsRisk, 'portfolio_outstanding'),
                'par30' => array_column($growthVsRisk, 'par30_pct'),
            ],
            'profitability_by_product' => [
                'labels' => array_column($profitability, 'product'),
                'profit' => array_column($profitability, 'profit'),
                'revenue' => array_column($profitability, 'revenue'),
            ],
            'officer_performance' => [
                'labels' => array_column($officers, 'officer'),
                'score' => array_column($officers, 'score_pct'),
                'par30' => array_column($officers, 'par30_pct'),
            ],
            'customer_segments' => [
                'labels' => array_column($segments, 'segment'),
                'portfolio' => array_column($segments, 'portfolio'),
                'par30' => array_column($segments, 'par30_pct'),
            ],
        ];
    }
}
