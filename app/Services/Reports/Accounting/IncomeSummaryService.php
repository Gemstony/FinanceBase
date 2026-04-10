<?php

declare(strict_types=1);

namespace App\Services\Reports\Accounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IncomeSummaryService
{
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $accountGroupId = isset($filters['account_group_id']) && $filters['account_group_id'] ? (int) $filters['account_group_id'] : null;
        $incomeAccountId = isset($filters['income_account_id']) && $filters['income_account_id'] ? (int) $filters['income_account_id'] : null;

        $currentRows = $this->incomeAccountTotals($subshopIds, $fromDate, $toDate, $accountGroupId, $incomeAccountId);

        $previous = null;
        $previousRows = [];
        if ($fromDate && $toDate) {
            [$prevFrom, $prevTo] = $this->resolvePreviousPeriod($fromDate, $toDate);
            $previous = [
                'from_date' => $prevFrom->toDateString(),
                'to_date' => $prevTo->toDateString(),
            ];
            $previousRows = $this->incomeAccountTotals($subshopIds, $prevFrom, $prevTo, $accountGroupId, $incomeAccountId);
        }

        $merged = $this->mergeWithPrevious($currentRows, $previousRows);
        $tree = $this->buildTree($merged);

        $totalIncome = round(array_sum(array_map(static fn ($r) => (float) ($r['amount'] ?? 0), $merged)), 2);
        $tree = $this->applyPercentages($tree, $totalIncome);

        $top = $this->topIncome($merged, 5);
        if ($totalIncome > 0) {
            foreach ($top as $idx => $t) {
                $top[$idx]['percentage'] = round((((float) ($t['amount'] ?? 0)) / $totalIncome) * 100, 2);
            }
        }

        $monthly = $this->monthlyTrend($subshopIds, $fromDate, $toDate, $accountGroupId, $incomeAccountId);
        $charts = $this->chartDatasets($merged, $top, $monthly);

        $previousTotal = round(array_sum(array_map(static fn ($r) => (float) ($r['previous_amount'] ?? 0), $merged)), 2);
        $differenceTotal = round($totalIncome - $previousTotal, 2);

        return [
            'filters' => [
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'subshop_ids' => $subshopIds,
                'account_group_id' => $accountGroupId,
                'income_account_id' => $incomeAccountId,
            ],
            'previous_period' => $previous,
            'tree' => $tree,
            'totals' => [
                'total_income' => $totalIncome,
                'previous_total_income' => $previousTotal,
                'difference_total_income' => $differenceTotal,
            ],
            'top_income' => $top,
            'monthly_trend' => $monthly,
            'charts' => $charts,
        ];
    }

    public function export(array $filters, array $accessibleSubshopIds): array
    {
        return $this->build($filters, $accessibleSubshopIds);
    }

    private function resolveSubshopFilter(?int $subshopId, array $accessibleSubshopIds): array
    {
        if ($subshopId) {
            return in_array($subshopId, $accessibleSubshopIds, true) ? [$subshopId] : [];
        }

        return $accessibleSubshopIds;
    }

    private function resolvePreviousPeriod(Carbon $fromDate, Carbon $toDate): array
    {
        $days = $fromDate->diffInDays($toDate) + 1;
        $prevTo = $fromDate->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);
        return [$prevFrom, $prevTo];
    }

    private function classifyAccountClass(string $classCode, string $className): string
    {
        $code = strtoupper(trim($classCode));
        $name = strtoupper(trim($className));

        if ($code !== '') {
            if (str_starts_with($code, '4') || str_contains($code, 'INCOME')) {
                return 'income';
            }
        }

        if (str_contains($name, 'INCOME')) {
            return 'income';
        }

        return 'unclassified';
    }

    private function incomeAccountTotals(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate, ?int $accountGroupId, ?int $incomeAccountId): array
    {
        if (empty($subshopIds)) {
            return [];
        }

        $q = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_groups as ag', 'ag.id', '=', 'coa.account_group_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->when($fromDate !== null, fn ($qq) => $qq->whereDate('je.transaction_date', '>=', $fromDate->toDateString()))
            ->when($toDate !== null, fn ($qq) => $qq->whereDate('je.transaction_date', '<=', $toDate->toDateString()))
            ->when($accountGroupId !== null, fn ($qq) => $qq->where('coa.account_group_id', $accountGroupId))
            ->when($incomeAccountId !== null, fn ($qq) => $qq->where('coa.id', $incomeAccountId))
            ->groupBy('coa.id', 'coa.account_code', 'coa.account_name', 'ag.id', 'ag.code', 'ag.name', 'ac.id', 'ac.code', 'ac.name')
            ->select([
                'ac.id as account_class_id',
                'ac.code as account_class_code',
                'ac.name as account_class_name',
                'ag.id as group_id',
                'ag.code as group_code',
                'ag.name as group_name',
                'coa.id as account_id',
                'coa.account_code as account_code',
                'coa.account_name as account_name',
            ])
            ->selectRaw('SUM(jel.debit) as total_debit')
            ->selectRaw('SUM(jel.credit) as total_credit');

        return $q->get()->map(function ($r) {
            $section = $this->classifyAccountClass((string) ($r->account_class_code ?? ''), (string) ($r->account_class_name ?? ''));
            if ($section !== 'income') {
                return null;
            }

            $debit = round((float) ($r->total_debit ?? 0), 2);
            $credit = round((float) ($r->total_credit ?? 0), 2);
            $amount = round($credit - $debit, 2);

            return [
                'group_id' => (int) ($r->group_id ?? 0),
                'group_code' => (string) ($r->group_code ?? ''),
                'group_name' => (string) ($r->group_name ?? ''),
                'account_id' => (int) ($r->account_id ?? 0),
                'account_code' => (string) ($r->account_code ?? ''),
                'account_name' => (string) ($r->account_name ?? ''),
                'total_debit' => $debit,
                'total_credit' => $credit,
                'amount' => $amount,
            ];
        })->filter()->values()->all();
    }

    private function mergeWithPrevious(array $currentRows, array $previousRows): array
    {
        $prevByAccount = [];
        foreach ($previousRows as $r) {
            $prevByAccount[(int) ($r['account_id'] ?? 0)] = (float) ($r['amount'] ?? 0);
        }

        $merged = [];
        foreach ($currentRows as $r) {
            $aid = (int) ($r['account_id'] ?? 0);
            $prevAmt = round((float) ($prevByAccount[$aid] ?? 0), 2);
            $curAmt = round((float) ($r['amount'] ?? 0), 2);

            $r['previous_amount'] = $prevAmt;
            $r['difference'] = round($curAmt - $prevAmt, 2);
            $merged[] = $r;
        }

        return $merged;
    }

    private function buildTree(array $rows): array
    {
        $tree = [];

        foreach ($rows as $r) {
            $groupKey = (string) ($r['group_id'] ?? 0);
            if (!isset($tree[$groupKey])) {
                $tree[$groupKey] = [
                    'group_id' => (int) ($r['group_id'] ?? 0),
                    'group_code' => (string) ($r['group_code'] ?? ''),
                    'group_name' => (string) ($r['group_name'] ?? ''),
                    'accounts' => [],
                    'subtotal' => 0.0,
                    'previous_subtotal' => 0.0,
                    'difference_subtotal' => 0.0,
                ];
            }

            $amt = round((float) ($r['amount'] ?? 0), 2);
            $prevAmt = round((float) ($r['previous_amount'] ?? 0), 2);
            $diff = round((float) ($r['difference'] ?? 0), 2);

            $tree[$groupKey]['accounts'][] = [
                'account_id' => (int) ($r['account_id'] ?? 0),
                'account_code' => (string) ($r['account_code'] ?? ''),
                'account_name' => (string) ($r['account_name'] ?? ''),
                'amount' => $amt,
                'previous_amount' => $prevAmt,
                'difference' => $diff,
                'percentage' => 0.0,
            ];

            $tree[$groupKey]['subtotal'] = round(((float) $tree[$groupKey]['subtotal']) + $amt, 2);
            $tree[$groupKey]['previous_subtotal'] = round(((float) $tree[$groupKey]['previous_subtotal']) + $prevAmt, 2);
            $tree[$groupKey]['difference_subtotal'] = round(((float) $tree[$groupKey]['difference_subtotal']) + $diff, 2);
        }

        uasort($tree, fn ($a, $b) => strcmp((string) ($a['group_code'] ?? ''), (string) ($b['group_code'] ?? '')));
        foreach ($tree as $gk => $g) {
            if (!empty($tree[$gk]['accounts']) && is_array($tree[$gk]['accounts'])) {
                usort($tree[$gk]['accounts'], fn ($a, $b) => ((float) ($b['amount'] ?? 0)) <=> ((float) ($a['amount'] ?? 0)));
            }
        }

        return $tree;
    }

    private function applyPercentages(array $tree, float $totalIncome): array
    {
        if ($totalIncome <= 0) {
            return $tree;
        }

        foreach ($tree as $gk => $g) {
            foreach (($g['accounts'] ?? []) as $idx => $acc) {
                $amt = (float) ($acc['amount'] ?? 0);
                $pct = round(($amt / $totalIncome) * 100, 2);
                $tree[$gk]['accounts'][$idx]['percentage'] = $pct;
            }
        }

        return $tree;
    }

    private function topIncome(array $rows, int $limit): array
    {
        $sorted = $rows;
        usort($sorted, fn ($a, $b) => ((float) ($b['amount'] ?? 0)) <=> ((float) ($a['amount'] ?? 0)));
        $sorted = array_slice($sorted, 0, max(0, $limit));

        return array_map(static function ($r) {
            return [
                'account_id' => (int) ($r['account_id'] ?? 0),
                'account_code' => (string) ($r['account_code'] ?? ''),
                'account_name' => (string) ($r['account_name'] ?? ''),
                'group_name' => (string) ($r['group_name'] ?? ''),
                'amount' => (float) ($r['amount'] ?? 0),
                'percentage' => 0.0,
            ];
        }, $sorted);
    }

    private function monthlyTrend(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate, ?int $accountGroupId, ?int $incomeAccountId): array
    {
        if (!$fromDate || !$toDate || empty($subshopIds)) {
            return [];
        }

        $q2 = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereDate('je.transaction_date', '>=', $fromDate->toDateString())
            ->whereDate('je.transaction_date', '<=', $toDate->toDateString())
            ->when($accountGroupId !== null, fn ($qq) => $qq->where('coa.account_group_id', $accountGroupId))
            ->when($incomeAccountId !== null, fn ($qq) => $qq->where('coa.id', $incomeAccountId))
            ->where(function ($w) {
                $w->whereRaw("UPPER(ac.code) like '4%'")
                    ->orWhereRaw("UPPER(ac.code) like '5%'")
                    ->orWhereRaw("UPPER(ac.code) like '%INCOME%'")
                    ->orWhereRaw("UPPER(ac.code) like '%REVENUE%'")
                    ->orWhereRaw("UPPER(ac.name) like '%INCOME%'")
                    ->orWhereRaw("UPPER(ac.name) like '%REVENUE%'");
            })
            ->selectRaw("DATE_FORMAT(je.transaction_date, '%Y-%m') as month")
            ->selectRaw('SUM(jel.credit - jel.debit) as amount')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $out = [];
        foreach ($q2 as $r) {
            $month = (string) ($r->month ?? '');
            if ($month === '') {
                continue;
            }
            $out[] = [
                'month' => $month,
                'amount' => round((float) ($r->amount ?? 0), 2),
            ];
        }

        return $out;
    }

    private function chartDatasets(array $rows, array $top, array $monthly): array
    {
        $sorted = $rows;
        usort($sorted, fn ($a, $b) => ((float) ($b['amount'] ?? 0)) <=> ((float) ($a['amount'] ?? 0)));

        $pieLabels = [];
        $pieValues = [];
        $maxPie = 8;
        $others = 0.0;
        foreach ($sorted as $idx => $r) {
            $label = trim((string) ($r['account_code'] ?? '') . ' ' . (string) ($r['account_name'] ?? ''));
            $val = (float) ($r['amount'] ?? 0);
            if ($idx < $maxPie) {
                $pieLabels[] = $label !== '' ? $label : ('Account #' . (int) ($r['account_id'] ?? 0));
                $pieValues[] = round($val, 2);
            } else {
                $others += $val;
            }
        }
        if ($others > 0.00001) {
            $pieLabels[] = 'Others';
            $pieValues[] = round($others, 2);
        }

        $barLabels = [];
        $barValues = [];
        foreach ($top as $t) {
            $barLabels[] = trim((string) ($t['account_code'] ?? '') . ' ' . (string) ($t['account_name'] ?? ''));
            $barValues[] = round((float) ($t['amount'] ?? 0), 2);
        }

        $lineLabels = [];
        $lineValues = [];
        foreach ($monthly as $m) {
            $lineLabels[] = (string) ($m['month'] ?? '');
            $lineValues[] = round((float) ($m['amount'] ?? 0), 2);
        }

        return [
            'pie' => ['labels' => $pieLabels, 'values' => $pieValues],
            'bar' => ['labels' => $barLabels, 'values' => $barValues],
            'line' => ['labels' => $lineLabels, 'values' => $lineValues],
        ];
    }
}
