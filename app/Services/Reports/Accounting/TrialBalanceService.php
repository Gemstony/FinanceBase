<?php

declare(strict_types=1);

namespace App\Services\Reports\Accounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    /**
     * @param array{as_of:Carbon,subshop_id?:int|null,account_class_id?:int|null,hide_zero?:bool|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $asOf = $filters['as_of'];
        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $accountClassId = isset($filters['account_class_id']) && $filters['account_class_id'] ? (int) $filters['account_class_id'] : null;
        $hideZero = (bool) ($filters['hide_zero'] ?? false);

        $rows = $this->accountTotals($subshopIds, $asOf, $accountClassId);

        $tree = $this->buildTree($rows, $hideZero);

        $totals = $this->calculateTotals($tree);
        $diff = round(((float) $totals['total_debit']) - ((float) $totals['total_credit']), 2);
        $balanced = abs($diff) <= 0.01;

        return [
            'filters' => [
                'as_of' => $asOf->toDateString(),
                'subshop_ids' => $subshopIds,
                'account_class_id' => $accountClassId,
                'hide_zero' => $hideZero,
            ],
            'tree' => $tree,
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
     * @return array<int, array{account_id:int,account_code:string,account_name:string,account_class_id:int,account_class_name:string,account_class_code:string,group_id:int,group_name:string,group_code:string,total_debit:float,total_credit:float,debit_balance:float,credit_balance:float}>
     */
    private function accountTotals(array $subshopIds, Carbon $asOf, ?int $accountClassId): array
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
            $debit = round((float) ($r->total_debit ?? 0), 2);
            $credit = round((float) ($r->total_credit ?? 0), 2);

            $net = $debit - $credit;
            $debitBal = $net > 0 ? round($net, 2) : 0.0;
            $creditBal = $net < 0 ? round(abs($net), 2) : 0.0;

            return [
                'account_id' => (int) ($r->account_id ?? 0),
                'account_code' => (string) ($r->account_code ?? ''),
                'account_name' => (string) ($r->account_name ?? ''),
                'account_class_id' => (int) ($r->account_class_id ?? 0),
                'account_class_name' => (string) ($r->account_class_name ?? ''),
                'account_class_code' => (string) ($r->account_class_code ?? ''),
                'group_id' => (int) ($r->group_id ?? 0),
                'group_name' => (string) ($r->group_name ?? ''),
                'group_code' => (string) ($r->group_code ?? ''),
                'total_debit' => $debit,
                'total_credit' => $credit,
                'debit_balance' => $debitBal,
                'credit_balance' => $creditBal,
            ];
        })->all();
    }

    /**
     * @param array<int, array{account_id:int,account_code:string,account_name:string,account_class_id:int,account_class_name:string,account_class_code:string,group_id:int,group_name:string,group_code:string,total_debit:float,total_credit:float,debit_balance:float,credit_balance:float}> $rows
     * @return array<string, mixed>
     */
    private function buildTree(array $rows, bool $hideZero): array
    {
        $tree = [];

        foreach ($rows as $r) {
            if ($hideZero && abs((float) ($r['debit_balance'] ?? 0)) <= 0.00001 && abs((float) ($r['credit_balance'] ?? 0)) <= 0.00001) {
                continue;
            }

            $classKey = (string) $r['account_class_id'];
            $groupKey = (string) $r['group_id'];

            if (!isset($tree[$classKey])) {
                $tree[$classKey] = [
                    'class_id' => (int) $r['account_class_id'],
                    'class_name' => (string) $r['account_class_name'],
                    'class_code' => (string) $r['account_class_code'],
                    'groups' => [],
                ];
            }

            if (!isset($tree[$classKey]['groups'][$groupKey])) {
                $tree[$classKey]['groups'][$groupKey] = [
                    'group_id' => (int) $r['group_id'],
                    'group_name' => (string) $r['group_name'],
                    'group_code' => (string) $r['group_code'],
                    'accounts' => [],
                ];
            }

            $tree[$classKey]['groups'][$groupKey]['accounts'][] = [
                'account_id' => (int) $r['account_id'],
                'account_code' => (string) $r['account_code'],
                'account_name' => (string) $r['account_name'],
                'debit' => (float) $r['debit_balance'],
                'credit' => (float) $r['credit_balance'],
            ];
        }

        return $this->withSubtotals($tree);
    }

    /**
     * @param array<string, mixed> $tree
     * @return array<string, mixed>
     */
    private function withSubtotals(array $tree): array
    {
        foreach ($tree as $ck => $classNode) {
            $classDebit = 0.0;
            $classCredit = 0.0;

            foreach (($classNode['groups'] ?? []) as $gk => $groupNode) {
                $groupDebit = 0.0;
                $groupCredit = 0.0;

                foreach (($groupNode['accounts'] ?? []) as $acc) {
                    $groupDebit += (float) ($acc['debit'] ?? 0);
                    $groupCredit += (float) ($acc['credit'] ?? 0);
                }

                $tree[$ck]['groups'][$gk]['subtotal_debit'] = round($groupDebit, 2);
                $tree[$ck]['groups'][$gk]['subtotal_credit'] = round($groupCredit, 2);

                $classDebit += $groupDebit;
                $classCredit += $groupCredit;

                if (isset($tree[$ck]['groups'][$gk]['accounts']) && is_array($tree[$ck]['groups'][$gk]['accounts'])) {
                    usort($tree[$ck]['groups'][$gk]['accounts'], function ($a, $b) {
                        return strcmp((string) ($a['account_code'] ?? ''), (string) ($b['account_code'] ?? ''));
                    });
                }
            }

            $tree[$ck]['subtotal_debit'] = round($classDebit, 2);
            $tree[$ck]['subtotal_credit'] = round($classCredit, 2);

            if (isset($tree[$ck]['groups']) && is_array($tree[$ck]['groups'])) {
                uasort($tree[$ck]['groups'], function ($a, $b) {
                    return strcmp((string) ($a['group_code'] ?? ''), (string) ($b['group_code'] ?? ''));
                });
            }
        }

        uasort($tree, function ($a, $b) {
            return strcmp((string) ($a['class_code'] ?? ''), (string) ($b['class_code'] ?? ''));
        });

        return $tree;
    }

    /**
     * @param array<string, mixed> $tree
     */
    private function calculateTotals(array $tree): array
    {
        $debit = 0.0;
        $credit = 0.0;

        foreach ($tree as $classNode) {
            $debit += (float) ($classNode['subtotal_debit'] ?? 0);
            $credit += (float) ($classNode['subtotal_credit'] ?? 0);
        }

        return [
            'total_debit' => round($debit, 2),
            'total_credit' => round($credit, 2),
        ];
    }
}
