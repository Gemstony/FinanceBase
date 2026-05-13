<?php

declare(strict_types=1);

namespace App\Services\Reports\Accounting;


use App\Services\Reports\Accounting\AccountClassificationTrait;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProfitLossService
{
    use AccountClassificationTrait;    /**
     * @param array{from_date:Carbon|null,to_date:Carbon|null,subshop_id?:int|null,account_group_id?:int|null,compare?:string|null,show_pct?:bool|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $accountGroupId = isset($filters['account_group_id']) && $filters['account_group_id'] ? (int) $filters['account_group_id'] : null;

        $compare = (string) ($filters['compare'] ?? 'none');
        $compare = $compare !== '' ? $compare : 'none';

        $showPct = (bool) ($filters['show_pct'] ?? false);

        $currentRows = $this->accountTotals($subshopIds, $fromDate, $toDate, $accountGroupId);
        
        // Validate that journal entries are balanced
        $totalDebits = array_sum(array_column($currentRows, 'total_debit'));
        $totalCredits = array_sum(array_column($currentRows, 'total_credit'));
        if (abs($totalDebits - $totalCredits) > 0.01) {
            // Log warning or add validation flag
            // For now, we'll just continue but this could be extended to add validation to results
            error_log(sprintf('Journal entries are not balanced: debits=%.2f, credits=%.2f, difference=%.2f', $totalDebits, $totalCredits, $totalDebits - $totalCredits));
        }
        
        $currentTree = $this->buildTree($currentRows);

        $previousTree = null;
        $previousPeriod = null;

        if ($compare !== 'none' && $fromDate && $toDate) {
            [$prevFrom, $prevTo] = $this->resolvePreviousPeriod($fromDate, $toDate, $compare);
            $previousPeriod = ['from_date' => $prevFrom->toDateString(), 'to_date' => $prevTo->toDateString(), 'mode' => $compare];

            $prevRows = $this->accountTotals($subshopIds, $prevFrom, $prevTo, $accountGroupId);
            $previousTree = $this->buildTree($prevRows);

            $currentTree = $this->mergeWithPrevious($currentTree, $previousTree);
        }

        $totals = $this->calculateTotals($currentTree);

        if ($showPct) {
            $currentTree = $this->applyPercentages($currentTree, $totals);
        }

        return [
            'filters' => [
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'subshop_ids' => $subshopIds,
                'account_group_id' => $accountGroupId,
                'compare' => $compare,
                'show_pct' => $showPct,
            ],
            'previous_period' => $previousPeriod,
            'tree' => $currentTree,
            'totals' => $totals,
        ];
    }

    /**
     * @param array{from_date:Carbon|null,to_date:Carbon|null,subshop_id?:int|null,account_group_id?:int|null,compare?:string|null,show_pct?:bool|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function export(array $filters, array $accessibleSubshopIds): array
    {
        return $this->build($filters, $accessibleSubshopIds);
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
     * @return array<int, array{section:string,account_class_id:int,account_class_code:string,account_class_name:string,group_id:int,group_code:string,group_name:string,account_id:int,account_code:string,account_name:string,total_debit:float,total_credit:float,amount:float}>
     */
    private function accountTotals(array $subshopIds, ?Carbon $fromDate, ?Carbon $toDate, ?int $accountGroupId): array
    {
        $q = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_groups as ag', 'ag.id', '=', 'coa.account_group_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->when($fromDate !== null, fn ($qq) => $qq->whereDate('je.transaction_date', '>=', $fromDate->toDateString()))
            ->when($toDate !== null, fn ($qq) => $qq->whereDate('je.transaction_date', '<=', $toDate->toDateString()))
            ->when($accountGroupId !== null, fn ($qq) => $qq->where('coa.account_group_id', $accountGroupId))
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
            $classCode = (string) ($r->account_class_code ?? '');
            $className = (string) ($r->account_class_name ?? '');
            $section = $this->classifyAccountClass($classCode, $className);

            if (!in_array($section, ['income', 'expense'], true)) {
                return null;
            }

            $debit = round((float) ($r->total_debit ?? 0), 2);
            $credit = round((float) ($r->total_credit ?? 0), 2);
            $amount = $section === 'income' ? round($credit - $debit, 2) : round($debit - $credit, 2);

            return [
                'section' => $section,
                'account_class_id' => (int) ($r->account_class_id ?? 0),
                'account_class_code' => (string) ($r->account_class_code ?? ''),
                'account_class_name' => (string) ($r->account_class_name ?? ''),
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



    /**
     * @param array<int, array{section:string,account_class_id:int,account_class_code:string,account_class_name:string,group_id:int,group_code:string,group_name:string,account_id:int,account_code:string,account_name:string,amount:float}> $rows
     */
    private function buildTree(array $rows): array
    {
        $tree = [
            'income' => ['label' => 'Income', 'groups' => [], 'total' => 0.0],
            'expense' => ['label' => 'Expenses', 'groups' => [], 'total' => 0.0],
        ];

        foreach ($rows as $r) {
            $section = (string) ($r['section'] ?? '');
            if (!isset($tree[$section])) {
                continue;
            }

            $groupKey = (string) ($r['group_id'] ?? 0);
            if (!isset($tree[$section]['groups'][$groupKey])) {
                $tree[$section]['groups'][$groupKey] = [
                    'group_id' => (int) ($r['group_id'] ?? 0),
                    'group_code' => (string) ($r['group_code'] ?? ''),
                    'group_name' => (string) ($r['group_name'] ?? ''),
                    'accounts' => [],
                    'subtotal' => 0.0,
                ];
            }

            $amt = round((float) ($r['amount'] ?? 0), 2);

            $tree[$section]['groups'][$groupKey]['accounts'][] = [
                'account_id' => (int) ($r['account_id'] ?? 0),
                'account_code' => (string) ($r['account_code'] ?? ''),
                'account_name' => (string) ($r['account_name'] ?? ''),
                'amount' => $amt,
            ];

            $tree[$section]['groups'][$groupKey]['subtotal'] = round(((float) $tree[$section]['groups'][$groupKey]['subtotal']) + $amt, 2);
            $tree[$section]['total'] = round(((float) $tree[$section]['total']) + $amt, 2);
        }

        foreach (['income', 'expense'] as $section) {
            if (isset($tree[$section]['groups']) && is_array($tree[$section]['groups'])) {
                uasort($tree[$section]['groups'], fn ($a, $b) => strcmp((string) ($a['group_code'] ?? ''), (string) ($b['group_code'] ?? '')));

                foreach ($tree[$section]['groups'] as $gk => $g) {
                    if (isset($tree[$section]['groups'][$gk]['accounts']) && is_array($tree[$section]['groups'][$gk]['accounts'])) {
                        usort($tree[$section]['groups'][$gk]['accounts'], fn ($a, $b) => strcmp((string) ($a['account_code'] ?? ''), (string) ($b['account_code'] ?? '')));
                    }
                }
            }
        }

        return $tree;
    }

    private function calculateTotals(array $tree): array
    {
        $totalIncome = round((float) ($tree['income']['total'] ?? 0), 2);
        $totalExpense = round((float) ($tree['expense']['total'] ?? 0), 2);
        $net = round($totalIncome - $totalExpense, 2);

        return [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpense,
            'net_profit' => $net,
            'net_label' => $net >= 0 ? 'Net Profit' : 'Net Loss',
        ];
    }

    private function resolvePreviousPeriod(Carbon $fromDate, Carbon $toDate, string $mode): array
    {
        if ($mode === 'prev_year') {
            return [$fromDate->copy()->subYear(), $toDate->copy()->subYear()];
        }

        $days = $fromDate->diffInDays($toDate) + 1;
        $prevTo = $fromDate->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        return [$prevFrom, $prevTo];
    }

    private function mergeWithPrevious(array $currentTree, array $previousTree): array
    {
        foreach (['income', 'expense'] as $section) {
            $prevGroups = (array) ($previousTree[$section]['groups'] ?? []);

            foreach ($currentTree[$section]['groups'] as $gk => $group) {
                $prevGroup = (array) ($prevGroups[$gk] ?? null);

                $prevAccountsById = [];
                foreach (($prevGroup['accounts'] ?? []) as $pa) {
                    $prevAccountsById[(int) ($pa['account_id'] ?? 0)] = (float) ($pa['amount'] ?? 0);
                }

                foreach (($currentTree[$section]['groups'][$gk]['accounts'] ?? []) as $idx => $acc) {
                    $aid = (int) ($acc['account_id'] ?? 0);
                    $prevAmt = round((float) ($prevAccountsById[$aid] ?? 0), 2);
                    $curAmt = round((float) ($acc['amount'] ?? 0), 2);

                    $currentTree[$section]['groups'][$gk]['accounts'][$idx]['previous_amount'] = $prevAmt;
                    $currentTree[$section]['groups'][$gk]['accounts'][$idx]['difference'] = round($curAmt - $prevAmt, 2);
                }

                $prevSubtotal = round((float) ($prevGroup['subtotal'] ?? 0), 2);
                $curSubtotal = round((float) ($currentTree[$section]['groups'][$gk]['subtotal'] ?? 0), 2);

                $currentTree[$section]['groups'][$gk]['previous_subtotal'] = $prevSubtotal;
                $currentTree[$section]['groups'][$gk]['difference_subtotal'] = round($curSubtotal - $prevSubtotal, 2);
            }

            $currentTree[$section]['previous_total'] = round((float) ($previousTree[$section]['total'] ?? 0), 2);
            $currentTree[$section]['difference_total'] = round(((float) ($currentTree[$section]['total'] ?? 0)) - ((float) ($currentTree[$section]['previous_total'] ?? 0)), 2);
        }

        return $currentTree;
    }

    private function applyPercentages(array $tree, array $totals): array
    {
        $totalIncome = (float) ($totals['total_income'] ?? 0);
        $totalExpense = (float) ($totals['total_expenses'] ?? 0);

        foreach (['income', 'expense'] as $section) {
            $den = $section === 'income' ? $totalIncome : $totalExpense;

            foreach (($tree[$section]['groups'] ?? []) as $gk => $group) {
                $sub = (float) ($group['subtotal'] ?? 0);
                $tree[$section]['groups'][$gk]['pct'] = $den > 0 ? round(($sub / $den) * 100, 2) : 0.0;

                foreach (($group['accounts'] ?? []) as $idx => $acc) {
                    $amt = (float) ($acc['amount'] ?? 0);
                    $tree[$section]['groups'][$gk]['accounts'][$idx]['pct'] = $den > 0 ? round(($amt / $den) * 100, 2) : 0.0;
                }
            }
        }

        return $tree;
    }
}
