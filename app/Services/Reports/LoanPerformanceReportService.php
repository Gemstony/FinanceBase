<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LoanInstallments;
use App\Models\LoanPayments;
use App\Models\LoanProducts;
use App\Models\Loans;
use App\Models\LoanWriteoffRecoveries;
use App\Models\LoanWriteoffs;
use App\Models\User;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LoanPerformanceReportService
{
    private readonly PortfolioRiskCalculator $portfolioRiskCalculator;

    public function __construct(PortfolioRiskCalculator $portfolioRiskCalculator)
    {
        $this->portfolioRiskCalculator = $portfolioRiskCalculator;
    }

    /**
     * @param  array{date_from:Carbon,date_to:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null}  $filters
     * @param  array<int>  $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);

        $loansQ = $this->filteredLoansQuery($filters, $subshopIds);
        $loanIds = (clone $loansQ)->pluck('loans.id');

        $summary = $this->summaryMetrics($subshopIds, $dateFrom, $dateTo, $loanIds);

        $byProduct = $this->performanceByProduct($subshopIds, $dateFrom, $dateTo, $loanIds);
        $byOfficer = $this->performanceByOfficer($subshopIds, $dateFrom, $dateTo, $loanIds);

        $behavior = $this->repaymentBehavior($subshopIds, $dateFrom, $dateTo, $loanIds);

        $topWorst = $this->topAndWorstLoans($subshopIds, $dateFrom, $dateTo, $loanIds);

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_ids' => $subshopIds,
            ],
            'summary' => $summary,
            'trends' => $this->repaymentTrends($subshopIds, $dateFrom, $dateTo, $loanIds),
            'collection_efficiency' => $this->collectionEfficiency($subshopIds, $dateFrom, $dateTo, $loanIds),
            'on_time_late' => $this->onTimeVsLate($subshopIds, $dateFrom, $dateTo, $loanIds),
            'by_product' => $byProduct,
            'by_officer' => $byOfficer,
            'delinquency' => $this->delinquencyAndDefaults($subshopIds, $loanIds),
            'behavior' => $behavior,
            'top_worst_loans' => $topWorst,
            'expected_vs_actual' => [
                'expected' => (float) ($summary['total_expected'] ?? 0),
                'actual' => (float) ($summary['total_collected'] ?? 0),
                'difference' => round(((float) ($summary['total_collected'] ?? 0)) - ((float) ($summary['total_expected'] ?? 0)), 2),
            ],
            'write_off' => $this->writeOffAndRecovery($subshopIds, $dateFrom, $dateTo, $loanIds),
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
        $q = Loans::query()
            ->from('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false);

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

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     */
    private function summaryMetrics(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $totalExpected = (float) LoanInstallments::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereBetween('due_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->sum('total_due');

        $totalCollected = (float) LoanPayments::query()
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('loan_payments.status', 'confirmed')
            ->whereBetween('loan_payments.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_payments.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->sum('loan_payments.amount');

        $collectionRate = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 2) : 0.0;

        // On-time/late using payment allocations vs installment due_date.
        $allocBase = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        $totalAllocated = (float) (clone $allocBase)
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) as amt')
            ->value('amt');

        $onTimeAllocated = (float) (clone $allocBase)
            ->whereRaw('lp.payment_date <= li.due_date')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) as amt')
            ->value('amt');

        $lateAllocated = (float) (clone $allocBase)
            ->whereRaw('lp.payment_date > li.due_date')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) as amt')
            ->value('amt');

        $onTimeRate = $totalAllocated > 0 ? round(($onTimeAllocated / $totalAllocated) * 100, 2) : 0.0;
        $lateRate = $totalAllocated > 0 ? round(($lateAllocated / $totalAllocated) * 100, 2) : 0.0;

        $avgDaysLate = (float) (clone $allocBase)
            ->whereRaw('lp.payment_date > li.due_date')
            ->selectRaw('AVG(DATEDIFF(lp.payment_date, li.due_date)) as avg_days')
            ->value('avg_days');
        $avgDaysLate = round($avgDaysLate ?: 0.0, 2);

        $totalLoans = (int) $this->portfolioRiskCalculator->activeLoansQuery()
            ->whereIn('subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->count('id');

        $defaultedLoans = (int) LoanInstallments::query()
            ->join('loans', 'loans.id', '=', 'loan_installments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loan_installments.is_active', true)
            ->where('loan_installments.status', 'overdue')
            ->whereDate('loan_installments.due_date', '<', Carbon::today()->subDays(90)->toDateString())
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_installments.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->distinct()
            ->count('loan_installments.loan_id');

        $defaultRate = $totalLoans > 0 ? round(($defaultedLoans / $totalLoans) * 100, 2) : 0.0;

        return [
            'total_expected' => round($totalExpected, 2),
            'total_collected' => round($totalCollected, 2),
            'collection_rate_pct' => $collectionRate,
            'on_time_rate_pct' => $onTimeRate,
            'late_payment_rate_pct' => $lateRate,
            'default_rate_pct' => $defaultRate,
            'avg_days_late' => $avgDaysLate,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     */
    private function repaymentTrends(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $start = (clone $dateFrom)->startOfMonth();
        $end = (clone $dateTo)->startOfMonth();
        $months = [];
        for ($d = $start->copy(); $d->lte($end); $d->addMonth()) {
            $months[] = $d->format('Y-m');
        }

        $expected = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw("DATE_FORMAT(li.due_date, '%Y-%m') as ym, SUM(li.total_due) as expected")
            ->groupBy('ym')
            ->pluck('expected', 'ym');

        $collected = DB::table('loan_payments as lp')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw("DATE_FORMAT(lp.payment_date, '%Y-%m') as ym, SUM(lp.amount) as collected")
            ->groupBy('ym')
            ->pluck('collected', 'ym');

        $rows = [];
        foreach ($months as $ym) {
            $exp = (float) ($expected[$ym] ?? 0.0);
            $col = (float) ($collected[$ym] ?? 0.0);
            $eff = $exp > 0 ? round(($col / $exp) * 100, 2) : 0.0;
            $rows[] = [
                'month' => $ym,
                'expected' => round($exp, 2),
                'collected' => round($col, 2),
                'efficiency_pct' => $eff,
            ];
        }

        return [
            'rows' => $rows,
            'chart' => [
                'labels' => array_column($rows, 'month'),
                'expected' => array_column($rows, 'expected'),
                'collected' => array_column($rows, 'collected'),
                'efficiency_pct' => array_column($rows, 'efficiency_pct'),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     */
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

        $missedQ = LoanInstallments::query()
            ->whereIn('subshop_id', $subshopIds)
            ->where('is_active', true)
            ->whereBetween('due_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('outstanding_amount', '>', 0)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        return [
            'expected' => round($expected, 2),
            'collected' => round($collected, 2),
            'efficiency_pct' => $eff,
            'missed_payments_count' => (int) (clone $missedQ)->count('id'),
            'missed_payments_amount' => round((float) (clone $missedQ)->sum('outstanding_amount'), 2),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     */
    private function onTimeVsLate(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $alloc = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        $sumExpr = 'SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0))';

        $onTime = (array) (clone $alloc)
            ->whereRaw('lp.payment_date <= li.due_date')
            ->selectRaw('COUNT(DISTINCT lp.id) as payments_count')
            ->selectRaw($sumExpr.' as amount')
            ->first();

        $late = (array) (clone $alloc)
            ->whereRaw('lp.payment_date > li.due_date')
            ->selectRaw('COUNT(DISTINCT lp.id) as payments_count')
            ->selectRaw($sumExpr.' as amount')
            ->first();

        $missed = (array) DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('li.outstanding_amount', '>', 0)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('COUNT(li.id) as missed_count')
            ->selectRaw('SUM(li.outstanding_amount) as missed_amount')
            ->first();

        return [
            'on_time' => [
                'count' => (int) ($onTime['payments_count'] ?? 0),
                'amount' => round((float) ($onTime['amount'] ?? 0), 2),
            ],
            'late' => [
                'count' => (int) ($late['payments_count'] ?? 0),
                'amount' => round((float) ($late['amount'] ?? 0), 2),
            ],
            'missed' => [
                'count' => (int) ($missed['missed_count'] ?? 0),
                'amount' => round((float) ($missed['missed_amount'] ?? 0), 2),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     */
    private function delinquencyAndDefaults(array $subshopIds, $loanIds): array
    {
        $overdueLoans = (int) LoanInstallments::query()
            ->join('loans', 'loans.id', '=', 'loan_installments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loan_installments.is_active', true)
            ->where('loan_installments.status', 'overdue')
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_installments.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->distinct()
            ->count('loan_installments.loan_id');

        $overdueAmount = (float) LoanInstallments::query()
            ->join('loans', 'loans.id', '=', 'loan_installments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loan_installments.is_active', true)
            ->where('loan_installments.status', 'overdue')
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_installments.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->sum('loan_installments.outstanding_amount');

        $loans90 = (int) LoanInstallments::query()
            ->join('loans', 'loans.id', '=', 'loan_installments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loan_installments.is_active', true)
            ->where('loan_installments.status', 'overdue')
            ->whereDate('loan_installments.due_date', '<', Carbon::today()->subDays(90)->toDateString())
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_installments.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->distinct()
            ->count('loan_installments.loan_id');

        $totalLoans = (int) $this->portfolioRiskCalculator->activeLoansQuery()
            ->whereIn('subshop_id', $subshopIds)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->count('id');

        $defaultRate = $totalLoans > 0 ? round(($loans90 / $totalLoans) * 100, 2) : 0.0;

        return [
            'overdue_loans' => $overdueLoans,
            'overdue_amount' => round($overdueAmount, 2),
            'loans_over_90_days' => $loans90,
            'default_rate_pct' => $defaultRate,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     * @return array<int, array{product_id:int,product_name:string,total_loans:int,expected:float,collected:float,efficiency_pct:float,par30:float}>
     */
    private function performanceByProduct(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $products = LoanProducts::query()
            ->whereIn('subshop_id', $subshopIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('id');

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

        $productExpected = [];
        $productCollected = [];
        $productPar30 = [];
        $productOutstanding = [];

        $dateFromStr = $dateFrom->toDateString();
        $dateToStr = $dateTo->toDateString();
        $par30Cutoff = Carbon::today()->subDays(30)->toDateString();

        foreach ($productLoanMap as $pid => $row) {
            $loanIdsArr = array_filter(explode(',', $row->loan_ids ?? ''));
            $totalOut = 0.0;
            $expSum = 0.0;
            $colSum = 0.0;
            $par30Sum = 0.0;

            foreach ($loanIdsArr as $loanId) {
                $loan = \App\Models\Loans::find((int) $loanId);
                if ($loan) {
                    $out = $this->portfolioRiskCalculator->calculateLoanOutstanding($loan);
                    $totalOut += $out;
                    $productOutstanding[$pid] = $totalOut;

                    $exp = DB::table('loan_installments as li')
                        ->where('li.loan_id', $loanId)
                        ->where('li.is_active', true)
                        ->whereBetween('li.due_date', [$dateFromStr, $dateToStr])
                        ->sum('li.total_due');
                    $expSum += (float) $exp;

                    $col = DB::table('loan_payments as lp')
                        ->join('loans', 'loans.id', '=', 'lp.loan_id')
                        ->where('lp.loan_id', $loanId)
                        ->where('lp.status', 'confirmed')
                        ->whereBetween('lp.payment_date', [$dateFromStr, $dateToStr])
                        ->sum('lp.amount');
                    $colSum += (float) $col;

                    $par30 = DB::table('loan_installments as li')
                        ->where('li.loan_id', $loanId)
                        ->where('li.is_active', true)
                        ->where('li.status', 'overdue')
                        ->whereDate('li.due_date', '<', $par30Cutoff)
                        ->sum('li.outstanding_amount');
                    $par30Sum += (float) $par30;
                }
            }

            $productExpected[$pid] = $expSum;
            $productCollected[$pid] = $colSum;
            $productPar30[$pid] = $par30Sum;
        }

        $loanCounts = DB::table('loans')
            ->whereIn('subshop_id', $subshopIds)
            ->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('is_active', true)
            ->where('is_written_off', false)
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('loan_product_id, COUNT(*) as total_loans')
            ->groupBy('loan_product_id')
            ->pluck('total_loans', 'loan_product_id');

        $result = [];
        foreach ($products as $pid => $p) {
            $exp = (float) ($productExpected[$pid] ?? 0);
            $col = (float) ($productCollected[$pid] ?? 0);

            $result[] = [
                'product_id' => (int) $pid,
                'product_name' => (string) ($p->name ?? ''),
                'total_loans' => (int) ($loanCounts[$pid] ?? 0),
                'expected' => round($exp, 2),
                'collected' => round($col, 2),
                'efficiency_pct' => $exp > 0 ? round(($col / $exp) * 100, 2) : 0.0,
                'par30' => round((float) ($productPar30[$pid] ?? 0), 2),
            ];
        }

        return $result;
    }

    /**
     * Officer report uses:
     * - loans managed: loan_disbursements.processed_by (latest per loan)
     * - collections: loan_payments.user_id (who recorded payment)
     *
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     * @return array<int, array{officer_id:int,officer:string,loans_managed:int,collected:float,expected:float,efficiency_pct:float,par30:float}>
     */
    private function performanceByOfficer(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
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

        $officerLoanMap = [];
        $officerExpected = [];
        $officerCollected = [];
        $officerPar30 = [];
        $officerOutstanding = [];
        $officerLoansManaged = [];

        $dateFromStr = $dateFrom->toDateString();
        $dateToStr = $dateTo->toDateString();
        $par30Cutoff = Carbon::today()->subDays(30)->toDateString();

        foreach ($loanOfficer as $lo) {
            $oid = (int) $lo->officer_id;
            if (! isset($officerExpected[$oid])) {
                $officerExpected[$oid] = 0.0;
                $officerCollected[$oid] = 0.0;
                $officerPar30[$oid] = 0.0;
                $officerOutstanding[$oid] = 0.0;
                $officerLoansManaged[$oid] = 0;
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
                    $officerLoansManaged[$oid]++;

                    $exp = DB::table('loan_installments as li')
                        ->where('li.loan_id', $loanId)
                        ->where('li.is_active', true)
                        ->whereBetween('li.due_date', [$dateFromStr, $dateToStr])
                        ->sum('li.total_due');
                    $officerExpected[$oid] += (float) $exp;

                    $col = DB::table('loan_payments as lp')
                        ->where('lp.loan_id', $loanId)
                        ->where('lp.status', 'confirmed')
                        ->whereBetween('lp.payment_date', [$dateFromStr, $dateToStr])
                        ->sum('lp.amount');
                    $officerCollected[$oid] += (float) $col;

                    $par30 = DB::table('loan_installments as li')
                        ->where('li.loan_id', $loanId)
                        ->where('li.is_active', true)
                        ->where('li.status', 'overdue')
                        ->whereDate('li.due_date', '<', $par30Cutoff)
                        ->sum('li.outstanding_amount');
                    $officerPar30[$oid] += (float) $par30;
                }
            }
        }

        $users = User::query()->whereIn('id', array_keys($officerOutstanding))->get(['id', 'name'])->keyBy('id');

        $rows = [];
        foreach ($officerOutstanding as $oid => $total) {
            $exp = $officerExpected[$oid] ?? 0;
            $col = $officerCollected[$oid] ?? 0;

            $rows[] = [
                'officer_id' => $oid,
                'officer' => (string) ($users[$oid]->name ?? 'Unknown'),
                'loans_managed' => (int) ($officerLoansManaged[$oid] ?? 0),
                'expected' => round($exp, 2),
                'collected' => round($col, 2),
                'efficiency_pct' => $exp > 0 ? round(($col / $exp) * 100, 2) : 0.0,
                'par30' => round((float) ($officerPar30[$oid] ?? 0), 2),
            ];
        }

        usort($rows, fn ($a, $b) => ($b['expected'] <=> $a['expected']));

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     */
    private function repaymentBehavior(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $alloc = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        $delayAvg = (float) (clone $alloc)
            ->selectRaw('AVG(DATEDIFF(lp.payment_date, li.due_date)) as avg_delay')
            ->value('avg_delay');

        $early = (array) (clone $alloc)
            ->whereRaw('lp.payment_date < li.due_date')
            ->selectRaw('COUNT(DISTINCT lp.id) as cnt')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) as amt')
            ->first();

        $repeatLate = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereRaw('lp.payment_date > li.due_date')
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('lp.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('lp.customer_id as customer_id, COUNT(*) as late_allocations')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('late_allocations')
            ->limit(10)
            ->get();

        $customerNames = DB::table('customers')
            ->whereIn('id', $repeatLate->pluck('customer_id')->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        $repeatLateRows = $repeatLate->map(function ($r) use ($customerNames) {
            $cid = (int) $r->customer_id;

            return [
                'customer_id' => $cid,
                'customer' => (string) ($customerNames[$cid]->name ?? 'Unknown'),
                'late_payments_count' => (int) $r->late_allocations,
            ];
        })->values()->all();

        return [
            'avg_repayment_delay_days' => round($delayAvg ?: 0.0, 2),
            'early_payments_count' => (int) ($early['cnt'] ?? 0),
            'early_payments_amount' => round((float) ($early['amt'] ?? 0), 2),
            'repeat_late_payers' => $repeatLateRows,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     */
    private function topAndWorstLoans(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $loanAgg = DB::table('loan_installments as li')
            ->join('loans', 'loans.id', '=', 'li.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('li.is_active', true)
            ->whereBetween('li.due_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('li.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('li.loan_id, SUM(li.total_due) as expected, SUM(li.outstanding_amount) as outstanding')
            ->groupBy('li.loan_id');

        $allocAgg = DB::table('loan_payment_allocations as lpa')
            ->join('loan_payments as lp', 'lp.id', '=', 'lpa.loan_payment_id')
            ->join('loan_installments as li', 'li.id', '=', 'lpa.loan_installment_id')
            ->join('loans', 'loans.id', '=', 'lp.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted'])
            ->where('loans.is_active', true)
            ->where('loans.is_written_off', false)
            ->where('lp.status', 'confirmed')
            ->whereBetween('lp.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('lp.loan_id as loan_id')
            ->selectRaw('SUM(COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) as collected_alloc')
            ->selectRaw('SUM(CASE WHEN lp.payment_date > li.due_date THEN (COALESCE(lpa.principal_amount,0)+COALESCE(lpa.interest_amount,0)+COALESCE(lpa.fee_amount,0)+COALESCE(lpa.penalty_amount,0)) ELSE 0 END) as late_alloc')
            ->groupBy('lp.loan_id');

        $rows = DB::query()
            ->fromSub($loanAgg, 'la')
            ->leftJoinSub($allocAgg, 'pa', fn ($j) => $j->on('pa.loan_id', '=', 'la.loan_id'))
            ->join('loans', 'loans.id', '=', 'la.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->select([
                'la.loan_id',
                'loans.loan_code',
                'loans.customer_id',
                'loans.loan_product_id',
                DB::raw('COALESCE(la.expected,0) as expected'),
                DB::raw('COALESCE(pa.collected_alloc,0) as collected_alloc'),
                DB::raw('COALESCE(pa.late_alloc,0) as late_alloc'),
                DB::raw('COALESCE(la.outstanding,0) as outstanding'),
            ])
            ->get();

        $customerNames = DB::table('customers')
            ->whereIn('id', $rows->pluck('customer_id')->filter()->unique()->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        $productNames = LoanProducts::query()
            ->whereIn('id', $rows->pluck('loan_product_id')->filter()->unique()->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        $mapped = $rows->map(function ($r) use ($customerNames, $productNames) {
            $expected = (float) $r->expected;
            $collected = (float) $r->collected_alloc;
            $late = (float) $r->late_alloc;

            $eff = $expected > 0 ? round(($collected / $expected) * 100, 2) : 0.0;
            $latePct = $collected > 0 ? round(($late / $collected) * 100, 2) : 0.0;

            return [
                'loan_id' => (int) $r->loan_id,
                'loan_code' => (string) $r->loan_code,
                'customer' => (string) ($customerNames[(int) $r->customer_id]->name ?? 'Unknown'),
                'product' => (string) ($productNames[(int) $r->loan_product_id]->name ?? 'Unknown'),
                'expected' => round($expected, 2),
                'collected' => round($collected, 2),
                'efficiency_pct' => $eff,
                'late_amount' => round($late, 2),
                'late_pct_of_collected' => $latePct,
                'outstanding_in_period' => round((float) $r->outstanding, 2),
            ];
        });

        $top = $mapped
            ->sort(function ($a, $b) {
                return ($b['efficiency_pct'] <=> $a['efficiency_pct'])
                    ?: ($a['late_pct_of_collected'] <=> $b['late_pct_of_collected'])
                    ?: ($b['expected'] <=> $a['expected']);
            })
            ->take(10)
            ->values()
            ->all();

        $worst = $mapped
            ->sort(function ($a, $b) {
                return ($b['outstanding_in_period'] <=> $a['outstanding_in_period'])
                    ?: ($a['efficiency_pct'] <=> $b['efficiency_pct']);
            })
            ->take(10)
            ->values()
            ->all();

        return [
            'top' => $top,
            'worst' => $worst,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $loanIds
     */
    private function writeOffAndRecovery(array $subshopIds, Carbon $dateFrom, Carbon $dateTo, $loanIds): array
    {
        $writeoffsQ = LoanWriteoffs::query()
            ->join('loans', 'loans.id', '=', 'loan_writeoffs.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('loan_writeoffs.writeoff_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_writeoffs.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        $writtenOffLoans = (int) (clone $writeoffsQ)->distinct()->count('loan_writeoffs.loan_id');
        $amountWrittenOff = (float) (clone $writeoffsQ)->sum('loan_writeoffs.total_written_off');

        $recoveriesQ = LoanWriteoffRecoveries::query()
            ->join('loans', 'loans.id', '=', 'loan_writeoff_recoveries.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('loan_writeoff_recoveries.recovery_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($loanIds->isNotEmpty(), fn ($q) => $q->whereIn('loan_writeoff_recoveries.loan_id', $loanIds), fn ($q) => $q->whereRaw('1=0'));

        $recoveriesAmount = (float) (clone $recoveriesQ)->sum('loan_writeoff_recoveries.total_recovered');

        return [
            'written_off_loans' => $writtenOffLoans,
            'amount_written_off' => round($amountWrittenOff, 2),
            'recoveries_after_writeoff' => round($recoveriesAmount, 2),
        ];
    }
}
