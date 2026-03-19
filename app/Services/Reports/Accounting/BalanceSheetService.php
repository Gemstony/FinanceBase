<?php

declare(strict_types=1);

namespace App\Services\Reports\Accounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BalanceSheetService
{
    /**
     * @param array{as_of:Carbon,subshop_id?:int|null,account_class_id?:int|null,compare_as_of?:Carbon|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $asOf = $filters['as_of'];
        $compareAsOf = $filters['compare_as_of'] ?? null;

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $accountClassId = isset($filters['account_class_id']) && $filters['account_class_id'] ? (int) $filters['account_class_id'] : null;

        $rowsCurrent = $this->accountBalances($subshopIds, $asOf, $accountClassId);
        $rowsPrevious = $compareAsOf ? $this->accountBalances($subshopIds, $compareAsOf, $accountClassId) : [];
        $prevMap = [];
        foreach ($rowsPrevious as $r) {
            $prevMap[(int) $r['account_id']] = (float) $r['balance'];
        }

        $tree = $this->buildTree($rowsCurrent, $prevMap);

        $equityComputed = $this->computedEquityLines($subshopIds, $asOf, $compareAsOf);
        $tree['equity']['computed'] = $equityComputed['lines'];
        $tree['equity']['computed_totals'] = $equityComputed['totals'];

        $totals = $this->calculateTotals($tree);

        $diff = round(((float) $totals['assets_total']) - ((float) $totals['liabilities_total'] + (float) $totals['equity_total']), 2);
        $balanced = abs($diff) <= 0.01;

        return [
            'filters' => [
                'as_of' => $asOf->toDateString(),
                'compare_as_of' => $compareAsOf?->toDateString(),
                'subshop_ids' => $subshopIds,
                'account_class_id' => $accountClassId,
            ],
            'tree' => $tree,
            'debug' => [
                'rows' => array_map(function (array $r) {
                    return [
                        'account_id' => (int) ($r['account_id'] ?? 0),
                        'account_code' => (string) ($r['account_code'] ?? ''),
                        'account_name' => (string) ($r['account_name'] ?? ''),
                        'class' => (string) ($r['account_class_name'] ?? ''),
                        'class_code' => (string) ($r['account_class_code'] ?? ''),
                        'category' => (string) ($r['category'] ?? ''),
                        'total_debit' => (float) ($r['total_debit'] ?? 0),
                        'total_credit' => (float) ($r['total_credit'] ?? 0),
                        'balance' => (float) ($r['balance'] ?? 0),
                    ];
                }, $rowsCurrent),
            ],
            'totals' => $totals,
            'validation' => [
                'balanced' => $balanced,
                'difference' => $diff,
            ],
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
     * Balance is returned in its natural sign for Balance Sheet display:
     * - assets: debit - credit
     * - liabilities/equity: credit - debit
     *
     * @return array<int, array{account_id:int,account_code:string,account_name:string,account_class_id:int,account_class_name:string,account_class_code:string,group_id:int,group_name:string,group_code:string,total_debit:float,total_credit:float,balance:float,category:string}>
     */
    private function accountBalances(array $subshopIds, Carbon $asOf, ?int $accountClassId): array
    {
        $asOfDate = $asOf->toDateString();

        $q = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_groups as ag', 'ag.id', '=', 'coa.account_group_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereDate('je.transaction_date', '<=', $asOfDate)
            ->when($accountClassId !== null, fn ($qq) => $qq->where('coa.account_class_id', $accountClassId))
            ->groupBy('jel.account_id', 'coa.account_code', 'coa.account_name', 'coa.account_class_id', 'ac.name', 'ac.code', 'ag.id', 'ag.name', 'ag.code')
            ->selectRaw('jel.account_id as account_id')
            ->selectRaw('coa.account_code as account_code')
            ->selectRaw('coa.account_name as account_name')
            ->selectRaw('coa.account_class_id as account_class_id')
            ->selectRaw('ac.name as account_class_name')
            ->selectRaw('ac.code as account_class_code')
            ->selectRaw('ag.id as group_id')
            ->selectRaw('ag.name as group_name')
            ->selectRaw('ag.code as group_code')
            ->selectRaw('SUM(jel.debit) as total_debit')
            ->selectRaw('SUM(jel.credit) as total_credit')
            ->orderBy('ac.code')
            ->orderBy('ag.code')
            ->orderBy('coa.account_code');

        return $q->get()->map(function ($r) {
            $classCode = (string) ($r->account_class_code ?? '');
            $className = (string) ($r->account_class_name ?? '');
            $category = $this->classifyAccountClass($classCode, $className);

            $totalDebit = round((float) ($r->total_debit ?? 0), 2);
            $totalCredit = round((float) ($r->total_credit ?? 0), 2);

            // Natural Balance Sheet sign conventions
            if ($category === 'assets') {
                $balance = $totalDebit - $totalCredit;
            } elseif ($category === 'liabilities' || $category === 'equity') {
                $balance = $totalCredit - $totalDebit;
            } else {
                // For non-balance-sheet categories (income/expense/unclassified), keep debit-credit as neutral
                $balance = $totalDebit - $totalCredit;
            }

            return [
                'account_id' => (int) ($r->account_id ?? 0),
                'account_code' => (string) ($r->account_code ?? ''),
                'account_name' => (string) ($r->account_name ?? ''),
                'account_class_id' => (int) ($r->account_class_id ?? 0),
                'account_class_name' => $className,
                'account_class_code' => $classCode,
                'group_id' => (int) ($r->group_id ?? 0),
                'group_name' => (string) ($r->group_name ?? ''),
                'group_code' => (string) ($r->group_code ?? ''),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'balance' => round((float) $balance, 2),
                'category' => $category,
            ];
        })->all();
    }

    /**
     * @param array<int, array{account_id:int,account_code:string,account_name:string,account_class_id:int,account_class_name:string,account_class_code:string,group_id:int,group_name:string,group_code:string,balance:float}> $rows
     * @param array<int, float> $prevBalances
     */
    private function buildTree(array $rows, array $prevBalances): array
    {
        $tree = [
            'assets' => ['current' => [], 'non_current' => []],
            'liabilities' => ['current' => [], 'non_current' => []],
            'equity' => ['items' => []],
            'unclassified' => [],
        ];

        foreach ($rows as $r) {
            $category = (string) ($r['category'] ?? $this->classifyAccountClass($r['account_class_code'], $r['account_class_name']));
            $bucket = $this->classifyCurrentNonCurrent($category, $r['group_name'], $r['account_name']);

            $accountId = (int) $r['account_id'];
            $prev = array_key_exists($accountId, $prevBalances) ? round((float) $prevBalances[$accountId], 2) : null;

            $account = [
                'account_id' => $accountId,
                'account_code' => $r['account_code'],
                'account_name' => $r['account_name'],
                'balance' => (float) $r['balance'],
                'prev_balance' => $prev,
            ];

            if ($category === 'assets') {
                $tree['assets'][$bucket] = $this->pushTreeNode($tree['assets'][$bucket], $r, $account);
            } elseif ($category === 'liabilities') {
                $tree['liabilities'][$bucket] = $this->pushTreeNode($tree['liabilities'][$bucket], $r, $account);
            } elseif ($category === 'equity') {
                $tree['equity']['items'] = $this->pushTreeNode($tree['equity']['items'], $r, $account);
            } else {
                $tree['unclassified'] = $this->pushTreeNode($tree['unclassified'], $r, $account);
            }
        }

        $tree['assets']['current'] = $this->withSubtotals($tree['assets']['current']);
        $tree['assets']['non_current'] = $this->withSubtotals($tree['assets']['non_current']);
        $tree['liabilities']['current'] = $this->withSubtotals($tree['liabilities']['current']);
        $tree['liabilities']['non_current'] = $this->withSubtotals($tree['liabilities']['non_current']);
        $tree['equity']['items'] = $this->withSubtotals($tree['equity']['items']);
        $tree['unclassified'] = $this->withSubtotals($tree['unclassified']);

        return $tree;
    }

    /**
     * @param array<string, mixed> $tree
     * @param array{account_class_id:int,account_class_name:string,account_class_code:string,group_id:int,group_name:string,group_code:string} $r
     * @param array{account_id:int,account_code:string,account_name:string,balance:float,prev_balance:?float} $account
     * @return array<string, mixed>
     */
    private function pushTreeNode(array $tree, array $r, array $account): array
    {
        $classKey = (string) $r['account_class_id'];
        $groupKey = (string) $r['group_id'];

        if (!isset($tree[$classKey])) {
            $tree[$classKey] = [
                'class_id' => (int) $r['account_class_id'],
                'class_name' => $r['account_class_name'],
                'class_code' => $r['account_class_code'],
                'groups' => [],
            ];
        }

        if (!isset($tree[$classKey]['groups'][$groupKey])) {
            $tree[$classKey]['groups'][$groupKey] = [
                'group_id' => (int) $r['group_id'],
                'group_name' => $r['group_name'],
                'group_code' => $r['group_code'],
                'accounts' => [],
            ];
        }

        $tree[$classKey]['groups'][$groupKey]['accounts'][] = $account;

        return $tree;
    }

    /**
     * Adds group and class subtotals.
     *
     * @param array<string, mixed> $tree
     * @return array<string, mixed>
     */
    private function withSubtotals(array $tree): array
    {
        foreach ($tree as $ck => $classNode) {
            $classTotal = 0.0;
            $classPrevTotal = 0.0;
            $hasPrev = false;

            foreach (($classNode['groups'] ?? []) as $gk => $groupNode) {
                $groupTotal = 0.0;
                $groupPrevTotal = 0.0;
                $groupHasPrev = false;

                foreach (($groupNode['accounts'] ?? []) as $acc) {
                    $groupTotal += (float) ($acc['balance'] ?? 0);
                    if (array_key_exists('prev_balance', $acc) && $acc['prev_balance'] !== null) {
                        $groupHasPrev = true;
                        $groupPrevTotal += (float) $acc['prev_balance'];
                    }
                }

                $tree[$ck]['groups'][$gk]['subtotal'] = round($groupTotal, 2);
                $tree[$ck]['groups'][$gk]['prev_subtotal'] = $groupHasPrev ? round($groupPrevTotal, 2) : null;

                $classTotal += $groupTotal;
                if ($groupHasPrev) {
                    $hasPrev = true;
                    $classPrevTotal += $groupPrevTotal;
                }
            }

            $tree[$ck]['subtotal'] = round($classTotal, 2);
            $tree[$ck]['prev_subtotal'] = $hasPrev ? round($classPrevTotal, 2) : null;
        }

        uasort($tree, function ($a, $b) {
            return strcmp((string) ($a['class_code'] ?? ''), (string) ($b['class_code'] ?? ''));
        });

        foreach ($tree as $ck => $classNode) {
            if (isset($tree[$ck]['groups']) && is_array($tree[$ck]['groups'])) {
                uasort($tree[$ck]['groups'], function ($a, $b) {
                    return strcmp((string) ($a['group_code'] ?? ''), (string) ($b['group_code'] ?? ''));
                });

                foreach ($tree[$ck]['groups'] as $gk => $groupNode) {
                    if (isset($tree[$ck]['groups'][$gk]['accounts']) && is_array($tree[$ck]['groups'][$gk]['accounts'])) {
                        usort($tree[$ck]['groups'][$gk]['accounts'], function ($a, $b) {
                            return strcmp((string) ($a['account_code'] ?? ''), (string) ($b['account_code'] ?? ''));
                        });
                    }
                }
            }
        }

        return $tree;
    }

    private function classifyAccountClass(string $classCode, string $className): string
    {
        $code = strtoupper(trim($classCode));
        $name = strtoupper(trim($className));

        if ($code !== '') {
            if (str_starts_with($code, '1') || str_contains($code, 'ASSET')) {
                return 'assets';
            }
            if (str_starts_with($code, '2') || str_contains($code, 'LIAB')) {
                return 'liabilities';
            }
            if (str_starts_with($code, '3') || str_contains($code, 'EQUITY')) {
                return 'equity';
            }
            if (str_starts_with($code, '4') || str_starts_with($code, '5') || str_contains($code, 'INCOME') || str_contains($code, 'REVENUE')) {
                return 'income';
            }
            if (str_starts_with($code, '6') || str_contains($code, 'EXPENSE') || str_contains($code, 'COST')) {
                return 'expense';
            }
        }

        if (str_contains($name, 'ASSET')) {
            return 'assets';
        }
        if (str_contains($name, 'LIAB')) {
            return 'liabilities';
        }
        if (str_contains($name, 'EQUITY')) {
            return 'equity';
        }
        if (str_contains($name, 'INCOME') || str_contains($name, 'REVENUE')) {
            return 'income';
        }
        if (str_contains($name, 'EXPENSE') || str_contains($name, 'COST')) {
            return 'expense';
        }

        return 'unclassified';
    }

    private function classifyCurrentNonCurrent(string $category, string $groupName, string $accountName): string
    {
        $g = strtoupper($groupName);
        $a = strtoupper($accountName);

        if ($category === 'assets') {
            if (str_contains($g, 'FIXED') || str_contains($g, 'NON CURRENT') || str_contains($g, 'NON-CURRENT') || str_contains($a, 'FIXED')) {
                return 'non_current';
            }
            return 'current';
        }

        if ($category === 'liabilities') {
            if (str_contains($g, 'LONG') || str_contains($g, 'NON CURRENT') || str_contains($g, 'NON-CURRENT') || str_contains($a, 'LONG')) {
                return 'non_current';
            }
            return 'current';
        }

        return 'current';
    }

    private function computedEquityLines(array $subshopIds, Carbon $asOf, ?Carbon $compareAsOf): array
    {
        $yearStart = $asOf->copy()->startOfYear();
        $priorYearEnd = $yearStart->copy()->subDay();

        $retained = $this->netIncomeAsOf($subshopIds, $priorYearEnd);
        $currentYear = $this->netIncomeBetween($subshopIds, $yearStart, $asOf);

        $retainedPrev = null;
        $currentYearPrev = null;
        if ($compareAsOf) {
            $yearStartPrev = $compareAsOf->copy()->startOfYear();
            $priorYearEndPrev = $yearStartPrev->copy()->subDay();
            $retainedPrev = $this->netIncomeAsOf($subshopIds, $priorYearEndPrev);
            $currentYearPrev = $this->netIncomeBetween($subshopIds, $yearStartPrev, $compareAsOf);
        }

        $lines = [
            [
                'label' => 'Retained Earnings',
                'balance' => round($retained, 2),
                'prev_balance' => $retainedPrev !== null ? round($retainedPrev, 2) : null,
            ],
            [
                'label' => 'Current Year Profit',
                'balance' => round($currentYear, 2),
                'prev_balance' => $currentYearPrev !== null ? round($currentYearPrev, 2) : null,
            ],
        ];

        return [
            'lines' => $lines,
            'totals' => [
                'retained_earnings' => round($retained, 2),
                'current_year_profit' => round($currentYear, 2),
                'prev_retained_earnings' => $retainedPrev !== null ? round($retainedPrev, 2) : null,
                'prev_current_year_profit' => $currentYearPrev !== null ? round($currentYearPrev, 2) : null,
            ],
        ];
    }

    private function netIncomeAsOf(array $subshopIds, Carbon $asOf): float
    {
        $asOfDate = $asOf->toDateString();

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereDate('je.transaction_date', '<=', $asOfDate)
            ->groupBy('ac.id', 'ac.code', 'ac.name')
            ->selectRaw('ac.code as class_code, ac.name as class_name, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->get();

        $income = 0.0;   // Revenue + Income
        $expense = 0.0;  // Expenses
        foreach ($rows as $r) {
            $cat = $this->classifyAccountClass((string) ($r->class_code ?? ''), (string) ($r->class_name ?? ''));
            if ($cat === 'income') {
                $income += ((float) ($r->total_credit ?? 0)) - ((float) ($r->total_debit ?? 0));
            } elseif ($cat === 'expense') {
                $expense += ((float) ($r->total_debit ?? 0)) - ((float) ($r->total_credit ?? 0));
            }
        }

        return $income - $expense;
    }

    private function netIncomeBetween(array $subshopIds, Carbon $from, Carbon $to): float
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereDate('je.transaction_date', '>=', $fromDate)
            ->whereDate('je.transaction_date', '<=', $toDate)
            ->groupBy('ac.id', 'ac.code', 'ac.name')
            ->selectRaw('ac.code as class_code, ac.name as class_name, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->get();

        $income = 0.0;
        $expense = 0.0;
        foreach ($rows as $r) {
            $cat = $this->classifyAccountClass((string) ($r->class_code ?? ''), (string) ($r->class_name ?? ''));
            if ($cat === 'income') {
                $income += ((float) ($r->total_credit ?? 0)) - ((float) ($r->total_debit ?? 0));
            } elseif ($cat === 'expense') {
                $expense += ((float) ($r->total_debit ?? 0)) - ((float) ($r->total_credit ?? 0));
            }
        }

        return $income - $expense;
    }

    /**
     * @param array<string, mixed> $tree
     */
    private function calculateTotals(array $tree): array
    {
        $assetsCurrent = $this->sumClassTotals($tree['assets']['current'] ?? []);
        $assetsNon = $this->sumClassTotals($tree['assets']['non_current'] ?? []);
        $liabCurrent = $this->sumClassTotals($tree['liabilities']['current'] ?? []);
        $liabNon = $this->sumClassTotals($tree['liabilities']['non_current'] ?? []);
        $equity = $this->sumClassTotals($tree['equity']['items'] ?? []);

        $equityComputed = 0.0;
        foreach (($tree['equity']['computed'] ?? []) as $line) {
            $equityComputed += (float) ($line['balance'] ?? 0);
        }

        $assetsTotal = round($assetsCurrent + $assetsNon, 2);
        $liabTotal = round($liabCurrent + $liabNon, 2);
        $equityTotal = round($equity + $equityComputed, 2);

        return [
            'assets_current' => round($assetsCurrent, 2),
            'assets_non_current' => round($assetsNon, 2),
            'assets_total' => $assetsTotal,
            'liabilities_current' => round($liabCurrent, 2),
            'liabilities_non_current' => round($liabNon, 2),
            'liabilities_total' => $liabTotal,
            'equity_accounts_total' => round($equity, 2),
            'equity_computed_total' => round($equityComputed, 2),
            'equity_total' => $equityTotal,
        ];
    }

    /**
     * @param array<string, mixed> $classTree
     */
    private function sumClassTotals(array $classTree): float
    {
        $total = 0.0;
        foreach ($classTree as $c) {
            $total += (float) ($c['subtotal'] ?? 0);
        }
        return $total;
    }
}
