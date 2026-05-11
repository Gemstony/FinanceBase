<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\DisbursementMethods;
use App\Models\LoanPayments;
use App\Models\LoanProducts;
use App\Models\SubShop;
use App\Models\User;
use App\Models\LoanDisbursements;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LoanDisbursementReportService
{
    /**
     * @param array{date_from:Carbon,date_to:Carbon,subshop_id?:int|null,loan_product_id?:int|null,loan_officer_id?:int|null,loan_status?:string|null,disbursement_method_id?:int|null,per_page?:int|null,page?:int|null,drilldown?:array|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_ids' => $subshopIds,
                'loan_product_id' => $filters['loan_product_id'] ?? null,
                'loan_officer_id' => $filters['loan_officer_id'] ?? null,
                'loan_status' => $filters['loan_status'] ?? null,
                'disbursement_method_id' => $filters['disbursement_method_id'] ?? null,
            ],
            'summary' => $this->summaryKpis($filters, $subshopIds, $dateFrom, $dateTo),
            'trends' => $this->disbursementTrends($filters, $subshopIds, $dateFrom, $dateTo),
            'by_product' => $this->disbursementByProduct($filters, $subshopIds, $dateFrom, $dateTo),
            'by_branch' => $this->disbursementByBranch($filters, $subshopIds, $dateFrom, $dateTo),
            'by_officer' => $this->disbursementByOfficer($filters, $subshopIds, $dateFrom, $dateTo),
            'new_vs_repeat' => $this->newVsRepeatBorrowers($filters, $subshopIds, $dateFrom, $dateTo),
            'loan_size_distribution' => $this->loanSizeDistribution($filters, $subshopIds, $dateFrom, $dateTo),
            'status_analysis' => $this->statusAnalysis($filters, $subshopIds, $dateFrom, $dateTo),
            'method_analysis' => $this->methodAnalysis($filters, $subshopIds, $dateFrom, $dateTo),
            'disbursement_vs_repayment' => $this->disbursementVsRepayment($filters, $subshopIds, $dateFrom, $dateTo),
            'top_borrowers' => $this->topBorrowers($filters, $subshopIds, $dateFrom, $dateTo),
            'efficiency' => $this->efficiencyMetrics($filters, $subshopIds, $dateFrom, $dateTo),
            'detailed_list' => $this->detailedDisbursementList($filters, $subshopIds, $dateFrom, $dateTo),
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

    private function filteredDisbursementsQuery(array $filters, array $subshopIds): Builder
    {
        $q = LoanDisbursements::query()
            ->from('loan_disbursements')
            ->join('loans', 'loans.id', '=', 'loan_disbursements.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->where('loans.is_active', true)
            ->whereIn('loans.status', ['disbursed', 'partially_paid', 'defaulted']);

        if (!empty($filters['drilldown']) && is_array($filters['drilldown'])) {
            $dd = $filters['drilldown'];
            if (!empty($dd['product_id'])) {
                $q->where('loans.loan_product_id', (int) $dd['product_id']);
            }
            if (!empty($dd['branch_id'])) {
                $q->where('loans.subshop_id', (int) $dd['branch_id']);
            }
            if (!empty($dd['officer_id'])) {
                $q->where('loan_disbursements.processed_by', (int) $dd['officer_id']);
            }
        }

        if (!empty($filters['loan_product_id'])) {
            $q->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }
        if (!empty($filters['loan_officer_id'])) {
            $q->where('loan_disbursements.processed_by', (int) $filters['loan_officer_id']);
        }
        if (!empty($filters['loan_status'])) {
            $q->where('loans.status', (string) $filters['loan_status']);
        }
        if (!empty($filters['disbursement_method_id'])) {
            $q->where('loan_disbursements.disbursement_method_id', (int) $filters['disbursement_method_id']);
        }

        return $q;
    }

    private function summaryKpis(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $q = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $totalLoans = (int) (clone $q)->distinct()->count('loan_disbursements.loan_id');
        $totalAmount = (float) (clone $q)->sum('loan_disbursements.amount');
        $avgLoan = $totalLoans > 0 ? round($totalAmount / $totalLoans, 2) : 0.0;

        $newRepeat = $this->newVsRepeatBorrowers($filters, $subshopIds, $dateFrom, $dateTo);

        $days = max(1, (int) $dateFrom->diffInDays($dateTo) + 1);
        $prevTo = (clone $dateFrom)->subDay()->endOfDay();
        $prevFrom = (clone $prevTo)->subDays($days - 1)->startOfDay();
        $prevAmount = (float) $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$prevFrom->toDateString(), $prevTo->toDateString()])
            ->sum('loan_disbursements.amount');
        $growthPct = $prevAmount > 0 ? round((($totalAmount - $prevAmount) / $prevAmount) * 100, 2) : 0.0;

        return [
            'total_loans_disbursed' => $totalLoans,
            'total_disbursement_amount' => round($totalAmount, 2),
            'average_loan_size' => $avgLoan,
            'new_borrowers' => (int) ($newRepeat['new']['count'] ?? 0),
            'repeat_borrowers' => (int) ($newRepeat['repeat']['count'] ?? 0),
            'disbursement_growth_pct' => $growthPct,
        ];
    }

    private function disbursementTrends(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $days = (int) $dateFrom->diffInDays($dateTo) + 1;

        $q = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($days <= 60) {
            $rows = (clone $q)
                ->selectRaw('DATE(loan_disbursements.disbursement_date) as period')
                ->selectRaw('COUNT(DISTINCT loan_disbursements.loan_id) as loans_count')
                ->selectRaw('SUM(loan_disbursements.amount) as amount')
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            $mapped = $rows->map(fn ($r) => [
                'period' => (string) $r->period,
                'loans' => (int) ($r->loans_count ?? 0),
                'amount' => round((float) ($r->amount ?? 0), 2),
            ])->all();

            return [
                'granularity' => 'daily',
                'rows' => $mapped,
                'chart' => [
                    'labels' => array_column($mapped, 'period'),
                    'loans' => array_column($mapped, 'loans'),
                    'amount' => array_column($mapped, 'amount'),
                ],
            ];
        }

        $rows = (clone $q)
            ->selectRaw("DATE_FORMAT(loan_disbursements.disbursement_date, '%Y-%m') as period")
            ->selectRaw('COUNT(DISTINCT loan_disbursements.loan_id) as loans_count')
            ->selectRaw('SUM(loan_disbursements.amount) as amount')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $mapped = $rows->map(fn ($r) => [
            'period' => (string) $r->period,
            'loans' => (int) ($r->loans_count ?? 0),
            'amount' => round((float) ($r->amount ?? 0), 2),
        ])->all();

        return [
            'granularity' => 'monthly',
            'rows' => $mapped,
            'chart' => [
                'labels' => array_column($mapped, 'period'),
                'loans' => array_column($mapped, 'loans'),
                'amount' => array_column($mapped, 'amount'),
            ],
        ];
    }

    private function disbursementByProduct(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $products = LoanProducts::query()->whereIn('subshop_id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $rows = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('loans.loan_product_id as product_id')
            ->selectRaw('COUNT(DISTINCT loan_disbursements.loan_id) as loans_count')
            ->selectRaw('SUM(loan_disbursements.amount) as amount')
            ->groupBy('product_id')
            ->get();

        return $rows->map(function ($r) use ($products) {
            $cnt = (int) ($r->loans_count ?? 0);
            $amt = (float) ($r->amount ?? 0);
            $pid = (int) ($r->product_id ?? 0);

            return [
                'product_id' => $pid,
                'product' => (string) ($products[$pid]->name ?? 'Unknown'),
                'loans' => $cnt,
                'amount' => round($amt, 2),
                'avg_loan_size' => $cnt > 0 ? round($amt / $cnt, 2) : 0.0,
            ];
        })->sortByDesc('amount')->values()->all();
    }

    private function disbursementByBranch(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $subshops = SubShop::query()->whereIn('id', $subshopIds)->get(['id', 'name'])->keyBy('id');

        $rows = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('loans.subshop_id as subshop_id')
            ->selectRaw('COUNT(DISTINCT loan_disbursements.loan_id) as loans_count')
            ->selectRaw('SUM(loan_disbursements.amount) as amount')
            ->groupBy('subshop_id')
            ->get();

        return $rows->map(function ($r) use ($subshops) {
            $cnt = (int) ($r->loans_count ?? 0);
            $amt = (float) ($r->amount ?? 0);
            $sid = (int) ($r->subshop_id ?? 0);

            return [
                'branch_id' => $sid,
                'branch' => (string) ($subshops[$sid]->name ?? 'Unknown'),
                'loans' => $cnt,
                'amount' => round($amt, 2),
                'avg_loan_size' => $cnt > 0 ? round($amt / $cnt, 2) : 0.0,
            ];
        })->sortByDesc('amount')->values()->all();
    }

    private function disbursementByOfficer(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $officers = User::query()->get(['id', 'name'])->keyBy('id');

        $rows = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('loan_disbursements.processed_by as officer_id')
            ->selectRaw('COUNT(DISTINCT loan_disbursements.loan_id) as loans_count')
            ->selectRaw('SUM(loan_disbursements.amount) as amount')
            ->groupBy('officer_id')
            ->get();

        return $rows->map(function ($r) use ($officers) {
            $cnt = (int) ($r->loans_count ?? 0);
            $amt = (float) ($r->amount ?? 0);
            $oid = (int) ($r->officer_id ?? 0);

            return [
                'officer_id' => $oid,
                'officer' => (string) ($officers[$oid]->name ?? 'Unknown'),
                'loans' => $cnt,
                'amount' => round($amt, 2),
                'avg_loan_size' => $cnt > 0 ? round($amt / $cnt, 2) : 0.0,
            ];
        })->sortByDesc('amount')->values()->all();
    }

    private function newVsRepeatBorrowers(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $base = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereNotNull('loans.customer_id');

        $firstDisb = DB::query()
            ->fromSub((clone $base)
                ->selectRaw('loans.customer_id as customer_id')
                ->selectRaw('MIN(loan_disbursements.disbursement_date) as first_disbursement_date')
                ->groupBy('loans.customer_id'), 'fd');

        $periodDisb = (clone $base)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('loans.customer_id as customer_id')
            ->selectRaw('SUM(loan_disbursements.amount) as amount')
            ->groupBy('loans.customer_id');

        $rows = DB::query()
            ->fromSub($periodDisb, 'pd')
            ->joinSub($firstDisb, 'fd', fn ($j) => $j->on('fd.customer_id', '=', 'pd.customer_id'))
            ->selectRaw("CASE WHEN fd.first_disbursement_date BETWEEN ? AND ? THEN 'new' ELSE 'repeat' END as borrower_type", [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('COUNT(*) as customers_count')
            ->selectRaw('SUM(pd.amount) as total_amount')
            ->groupBy('borrower_type')
            ->get();

        $new = ['count' => 0, 'amount' => 0.0];
        $repeat = ['count' => 0, 'amount' => 0.0];

        foreach ($rows as $r) {
            if ((string) ($r->borrower_type ?? '') === 'new') {
                $new = ['count' => (int) ($r->customers_count ?? 0), 'amount' => round((float) ($r->total_amount ?? 0), 2)];
            } else {
                $repeat = ['count' => (int) ($r->customers_count ?? 0), 'amount' => round((float) ($r->total_amount ?? 0), 2)];
            }
        }

        return ['new' => $new, 'repeat' => $repeat];
    }

    private function loanSizeDistribution(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("CASE
                WHEN loan_disbursements.amount < 500000 THEN '0-500K'
                WHEN loan_disbursements.amount < 1000000 THEN '500K-1M'
                WHEN loan_disbursements.amount < 5000000 THEN '1M-5M'
                ELSE '5M+'
            END as bucket")
            ->selectRaw('COUNT(DISTINCT loan_disbursements.loan_id) as loans_count')
            ->selectRaw('SUM(loan_disbursements.amount) as amount')
            ->groupBy('bucket')
            ->get();

        $order = ['0-500K' => 1, '500K-1M' => 2, '1M-5M' => 3, '5M+' => 4];
        $mapped = $rows->map(fn ($r) => [
            'bucket' => (string) ($r->bucket ?? ''),
            'loans' => (int) ($r->loans_count ?? 0),
            'amount' => round((float) ($r->amount ?? 0), 2),
        ])->all();
        usort($mapped, fn ($a, $b) => ($order[$a['bucket']] ?? 99) <=> ($order[$b['bucket']] ?? 99));

        return $mapped;
    }

    private function statusAnalysis(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $approvalSub = DB::table('loan_approvals as la')
            ->selectRaw('la.loan_id, MAX(la.approved_at) as approval_date')
            ->where('la.status', 'approved')
            ->groupBy('la.loan_id');

        $rejectionSub = DB::table('loan_approvals as la')
            ->selectRaw('la.loan_id, MAX(la.approved_at) as rejection_date')
            ->where('la.status', 'rejected')
            ->groupBy('la.loan_id');

        $baseLoans = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->leftJoinSub($approvalSub, 'ap', fn ($j) => $j->on('ap.loan_id', '=', 'loans.id'))
            ->leftJoinSub($rejectionSub, 'rej', fn ($j) => $j->on('rej.loan_id', '=', 'loans.id'));

        if (!empty($filters['drilldown']) && is_array($filters['drilldown'])) {
            $dd = $filters['drilldown'];
            if (!empty($dd['product_id'])) {
                $baseLoans->where('loans.loan_product_id', (int) $dd['product_id']);
            }
            if (!empty($dd['branch_id'])) {
                $baseLoans->where('loans.subshop_id', (int) $dd['branch_id']);
            }
        }

        if (!empty($filters['loan_product_id'])) {
            $baseLoans->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }

        if (!empty($filters['loan_status'])) {
            $baseLoans->where('loans.status', (string) $filters['loan_status']);
        }

        $disbQ = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $disbursedCount = (int) (clone $disbQ)->distinct()->count('loan_disbursements.loan_id');
        $disbursedAmount = (float) (clone $disbQ)->sum('loan_disbursements.amount');

        $approvedNotDisbursedQ = (clone $baseLoans)
            ->where('loans.status', 'approved')
            ->whereNotNull('ap.approval_date')
            ->whereBetween(DB::raw('DATE(ap.approval_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereNotExists(function ($sq) {
                $sq->selectRaw('1')
                    ->from('loan_disbursements as ld2')
                    ->whereColumn('ld2.loan_id', 'loans.id');
            });

        $approvedNotDisbursedCount = (int) (clone $approvedNotDisbursedQ)->count('loans.id');
        $approvedNotDisbursedAmount = (float) (clone $approvedNotDisbursedQ)->sum('loans.principal_amount');

        $cancelledQ = (clone $baseLoans)
            ->where('loans.status', 'rejected')
            ->whereNotNull('rej.rejection_date')
            ->whereBetween(DB::raw('DATE(rej.rejection_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $cancelledCount = (int) (clone $cancelledQ)->count('loans.id');
        $cancelledAmount = (float) (clone $cancelledQ)->sum('loans.principal_amount');

        return [
            'approved_not_disbursed' => [
                'count' => $approvedNotDisbursedCount,
                'amount' => round($approvedNotDisbursedAmount, 2),
            ],
            'disbursed' => [
                'count' => $disbursedCount,
                'amount' => round($disbursedAmount, 2),
            ],
            'cancelled' => [
                'count' => $cancelledCount,
                'amount' => round($cancelledAmount, 2),
            ],
        ];
    }

    private function methodAnalysis(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $methods = DisbursementMethods::query()
            ->whereIn('subshop_id', $subshopIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $rows = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('loan_disbursements.disbursement_method_id as method_id')
            ->selectRaw('COUNT(DISTINCT loan_disbursements.loan_id) as loans_count')
            ->selectRaw('SUM(loan_disbursements.amount) as amount')
            ->groupBy('method_id')
            ->get();

        return $rows->map(function ($r) use ($methods) {
            $mid = (int) ($r->method_id ?? 0);
            return [
                'method_id' => $mid,
                'method' => (string) ($methods[$mid]->name ?? 'Unknown'),
                'loans' => (int) ($r->loans_count ?? 0),
                'amount' => round((float) ($r->amount ?? 0), 2),
            ];
        })->sortByDesc('amount')->values()->all();
    }

    private function disbursementVsRepayment(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        // Total disbursed in the period (includes top-ups as separate disbursements)
        $disbQ = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $totalDisbursed = (float) (clone $disbQ)->sum('loan_disbursements.amount');

        // For repayments, we want ALL loans that match the filter criteria (product, officer, etc.)
        // regardless of when they were disbursed. This ensures repayments on pre-existing loans
        // are included, and top-up loans are properly accounted for.
        $loanIdsScope = DB::table('loans')
            ->whereIn('subshop_id', $subshopIds)
            ->when(!empty($filters['loan_product_id']), fn ($q) => $q->where('loan_product_id', (int) $filters['loan_product_id']))
            ->when(!empty($filters['loan_status']), fn ($q) => $q->where('status', (string) $filters['loan_status']))
            ->when(!empty($filters['drilldown']) && is_array($filters['drilldown']), function ($q) use ($filters) {
                $dd = $filters['drilldown'];
                if (!empty($dd['product_id'])) {
                    $q->where('loan_product_id', (int) $dd['product_id']);
                }
                if (!empty($dd['branch_id'])) {
                    $q->where('subshop_id', (int) $dd['branch_id']);
                }
                // Officer drilldown: filter to loans disbursed by that officer (any time)
                if (!empty($dd['officer_id'])) {
                    $q->whereExists(function ($sq) use ($dd) {
                        $sq->selectRaw('1')
                            ->from('loan_disbursements as ld')
                            ->whereColumn('ld.loan_id', 'loans.id')
                            ->where('ld.processed_by', (int) $dd['officer_id']);
                    });
                }
            })
            // Officer filter: include loans that ever had disbursement by this officer
            ->when(!empty($filters['loan_officer_id']), function ($q) use ($filters) {
                $q->whereExists(function ($sq) use ($filters) {
                    $sq->selectRaw('1')
                        ->from('loan_disbursements as ld')
                        ->whereColumn('ld.loan_id', 'loans.id')
                        ->where('ld.processed_by', (int) $filters['loan_officer_id']);
                });
            })
            // Method filter: include loans that used this disbursement method (any time)
            ->when(!empty($filters['disbursement_method_id']), function ($q) use ($filters) {
                $q->whereExists(function ($sq) use ($filters) {
                    $sq->selectRaw('1')
                        ->from('loan_disbursements as ld')
                        ->whereColumn('ld.loan_id', 'loans.id')
                        ->where('ld.disbursement_method_id', (int) $filters['disbursement_method_id']);
                });
            })
            ->pluck('id');

        // Get repayments in the period for all matching loans (including top-ups)
        // Note: This includes all payment allocations (principal + interest + fees)
        $paymentsQ = LoanPayments::query()
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->whereBetween('loan_payments.payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('loan_payments.status', 'confirmed')
            ->when($loanIdsScope->isNotEmpty(), fn ($q) => $q->whereIn('loan_payments.loan_id', $loanIdsScope), fn ($q) => $q->whereRaw('1=0'));

        $totalRepaid = (float) (clone $paymentsQ)->sum('loan_payments.amount');

        return [
            'total_disbursed' => round($totalDisbursed, 2),
            'total_repaid' => round($totalRepaid, 2),
            'net_portfolio_growth' => round($totalDisbursed - $totalRepaid, 2),
        ];
    }

    private function topBorrowers(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $baseQ = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        return $baseQ
            ->selectRaw(
                'loans.customer_id as customer_id, c.name as customer, COUNT(DISTINCT loan_disbursements.loan_id) as loans_count, SUM(loan_disbursements.amount) as amount'
            )
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->whereNotNull('loans.customer_id')
            ->groupBy('customer_id', 'customer')
            ->orderByDesc('amount')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'customer_id' => $r->customer_id,
                'customer' => $r->customer,
                'loans' => (int) $r->loans_count,
                'amount' => (float) $r->amount,
            ])
            ->all();
    }

    private function efficiencyMetrics(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $approvalSub = DB::table('loan_approvals as la')
            ->selectRaw('la.loan_id, MAX(la.approved_at) as approval_date')
            ->where('la.status', 'approved')
            ->groupBy('la.loan_id');

        $disbQ = $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->leftJoinSub($approvalSub, 'ap', fn ($j) => $j->on('ap.loan_id', '=', 'loans.id'));

        $avgDays = (float) (clone $disbQ)
            ->whereNotNull('ap.approval_date')
            ->avg(DB::raw('DATEDIFF(loan_disbursements.disbursement_date, DATE(ap.approval_date))'));

        $approvedInPeriodQ = DB::table('loans')
            ->whereIn('loans.subshop_id', $subshopIds)
            ->joinSub($approvalSub, 'ap', fn ($j) => $j->on('ap.loan_id', '=', 'loans.id'))
            ->whereBetween(DB::raw('DATE(ap.approval_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (!empty($filters['loan_product_id'])) {
            $approvedInPeriodQ->where('loans.loan_product_id', (int) $filters['loan_product_id']);
        }

        // Officer filter: include loans that ever had disbursement by this officer
        if (!empty($filters['loan_officer_id'])) {
            $approvedInPeriodQ->whereExists(function ($sq) use ($filters) {
                $sq->selectRaw('1')
                    ->from('loan_disbursements as ld')
                    ->whereColumn('ld.loan_id', 'loans.id')
                    ->where('ld.processed_by', (int) $filters['loan_officer_id']);
            });
        }

        if (!empty($filters['drilldown']) && is_array($filters['drilldown'])) {
            $dd = $filters['drilldown'];
            if (!empty($dd['product_id'])) {
                $approvedInPeriodQ->where('loans.loan_product_id', (int) $dd['product_id']);
            }
            if (!empty($dd['branch_id'])) {
                $approvedInPeriodQ->where('loans.subshop_id', (int) $dd['branch_id']);
            }
            // Officer drilldown: filter to loans disbursed by that officer (any time)
            if (!empty($dd['officer_id'])) {
                $approvedInPeriodQ->whereExists(function ($sq) use ($dd) {
                    $sq->selectRaw('1')
                        ->from('loan_disbursements as ld')
                        ->whereColumn('ld.loan_id', 'loans.id')
                        ->where('ld.processed_by', (int) $dd['officer_id']);
                });
            }
        }

        $approvedCount = (int) (clone $approvedInPeriodQ)->count('loans.id');

        $disbursedCount = (int) (clone $disbQ)->distinct()->count('loan_disbursements.loan_id');

        $conversion = $approvedCount > 0 ? round(($disbursedCount / $approvedCount) * 100, 2) : 0.0;

        return [
            'avg_time_to_disburse_days' => round($avgDays, 2),
            'approval_conversion_rate_pct' => $conversion,
        ];
    }

    private function detailedDisbursementList(array $filters, array $subshopIds, Carbon $dateFrom, Carbon $dateTo): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 25);
        if ($perPage <= 0) {
            $perPage = 25;
        }

        return $this->filteredDisbursementsQuery($filters, $subshopIds)
            ->leftJoin('customers as c', 'c.id', '=', 'loans.customer_id')
            ->leftJoin('loan_products as lp', 'lp.id', '=', 'loans.loan_product_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'loans.subshop_id')
            ->leftJoin('users as u', 'u.id', '=', 'loan_disbursements.processed_by')
            ->leftJoin('disbursement_methods as dm', 'dm.id', '=', 'loan_disbursements.disbursement_method_id')
            ->whereBetween('loan_disbursements.disbursement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->select([
                'loan_disbursements.id as disbursement_id',
                'loans.id as loan_id',
                'loans.loan_code as loan_code',
                'c.name as customer',
                'lp.name as product',
                'ss.name as branch',
                'u.name as officer',
                'loan_disbursements.disbursement_date as disbursement_date',
                'loan_disbursements.amount as amount',
                'dm.name as disbursement_method',
                'loans.status as loan_status',
            ])
            ->orderByDesc('loan_disbursements.disbursement_date')
            ->paginate($perPage);
    }
}
