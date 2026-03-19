<?php

declare(strict_types=1);

namespace App\Services\Reports\Accounting;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CashFlowService
{
    /**
     * @param array{cash_account_id:int,from_date?:Carbon|null,to_date?:Carbon|null,subshop_id?:int|null,reference_type?:string|null,per_page?:int|null,page?:int|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $cashAccountId = (int) ($filters['cash_account_id'] ?? 0);
        if ($cashAccountId <= 0) {
            return [
                'filters' => [],
                'cash_account' => null,
                'opening_balance' => 0.0,
                'totals' => $this->emptyTotals(),
                'sections' => $this->emptySections(),
                'transactions' => null,
            ];
        }

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        $cashAccount = $this->loadAccount($cashAccountId);

        $opening = $this->openingBalance($subshopIds, $cashAccountId, $fromDate);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = $perPage > 0 ? min($perPage, 200) : 50;

        $transactionsPaginator = $this->transactionsPaginated(
            subshopIds: $subshopIds,
            cashAccountId: $cashAccountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null,
            perPage: $perPage,
            page: isset($filters['page']) ? (int) $filters['page'] : null
        );

        $rows = array_map(function ($r) {
            $row = is_array($r) ? $r : (array) $r;
            $cat = (string) ($row['counter_cash_flow_category'] ?? '');
            $row['activity_type'] = $this->resolveActivityType($cat, (string) ($row['reference_type'] ?? ''));
            return $row;
        }, $transactionsPaginator->items());

        $pageStartBalance = $opening;
        if (!empty($rows)) {
            $first = $rows[0];
            $pageStartBalance = round(
                $opening + $this->priorMovementsBeforeRow(
                    subshopIds: $subshopIds,
                    cashAccountId: $cashAccountId,
                    fromDate: $fromDate,
                    toDate: $toDate,
                    referenceType: $filters['reference_type'] ?? null,
                    firstTransactionDate: (string) ($first['transaction_date'] ?? ''),
                    firstLineId: (int) ($first['line_id'] ?? 0)
                ),
                2
            );
        }

        $running = $pageStartBalance;
        $rowsWithRunning = [];
        foreach ($rows as $r) {
            $debit = round((float) ($r['debit'] ?? 0), 2);
            $credit = round((float) ($r['credit'] ?? 0), 2);

            $running = round($running + ($debit - $credit), 2);

            $r['running_balance'] = $running;
            $rowsWithRunning[] = $r;
        }

        $transactionsPaginator->setCollection(collect($rowsWithRunning));

        $periodTotals = $this->periodTotals(
            subshopIds: $subshopIds,
            cashAccountId: $cashAccountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null
        );

        $sections = $this->sectionTotals(
            subshopIds: $subshopIds,
            cashAccountId: $cashAccountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null
        );

        $closing = round($opening + ($periodTotals['total_inflow'] - $periodTotals['total_outflow']), 2);

        return [
            'filters' => [
                'cash_account_id' => $cashAccountId,
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'subshop_ids' => $subshopIds,
                'reference_type' => $filters['reference_type'] ?? null,
            ],
            'cash_account' => $cashAccount,
            'opening_balance' => $opening,
            'totals' => [
                'total_inflow' => $periodTotals['total_inflow'],
                'total_outflow' => $periodTotals['total_outflow'],
                'net_cash_flow' => $periodTotals['total_inflow'] - $periodTotals['total_outflow'],
                'closing_balance' => $closing,
            ],
            'sections' => $sections,
            'transactions' => $transactionsPaginator,
        ];
    }

    /**
     * @param array{cash_account_id:int,from_date?:Carbon|null,to_date?:Carbon|null,subshop_id?:int|null,reference_type?:string|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function export(array $filters, array $accessibleSubshopIds): array
    {
        $cashAccountId = (int) ($filters['cash_account_id'] ?? 0);
        if ($cashAccountId <= 0) {
            return $this->build($filters, $accessibleSubshopIds);
        }

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        $cashAccount = $this->loadAccount($cashAccountId);
        $opening = $this->openingBalance($subshopIds, $cashAccountId, $fromDate);

        $rows = $this->transactionsAll(
            subshopIds: $subshopIds,
            cashAccountId: $cashAccountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null
        );

        $running = $opening;
        foreach ($rows as &$r) {
            $debit = round((float) ($r['debit'] ?? 0), 2);
            $credit = round((float) ($r['credit'] ?? 0), 2);
            $running = round($running + ($debit - $credit), 2);
            $r['running_balance'] = $running;
        }
        unset($r);

        $periodTotals = $this->periodTotals(
            subshopIds: $subshopIds,
            cashAccountId: $cashAccountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null
        );

        $sections = $this->sectionTotals(
            subshopIds: $subshopIds,
            cashAccountId: $cashAccountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null
        );

        $closing = round($opening + ($periodTotals['total_inflow'] - $periodTotals['total_outflow']), 2);

        return [
            'filters' => [
                'cash_account_id' => $cashAccountId,
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'subshop_ids' => $subshopIds,
                'reference_type' => $filters['reference_type'] ?? null,
            ],
            'cash_account' => $cashAccount,
            'opening_balance' => $opening,
            'totals' => [
                'total_inflow' => $periodTotals['total_inflow'],
                'total_outflow' => $periodTotals['total_outflow'],
                'net_cash_flow' => $periodTotals['total_inflow'] - $periodTotals['total_outflow'],
                'closing_balance' => $closing,
            ],
            'sections' => $sections,
            'transactions_all' => $rows,
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

    /** @return array{account_id:int,account_code:string,account_name:string} */
    private function loadAccount(int $accountId): array
    {
        $r = DB::table('charts_of_accounts as coa')
            ->where('coa.id', $accountId)
            ->select(['coa.id as account_id', 'coa.account_code as account_code', 'coa.account_name as account_name'])
            ->first();

        return [
            'account_id' => (int) ($r->account_id ?? $accountId),
            'account_code' => (string) ($r->account_code ?? ''),
            'account_name' => (string) ($r->account_name ?? ''),
        ];
    }

    private function openingBalance(array $subshopIds, int $cashAccountId, ?Carbon $fromDate): float
    {
        if (!$fromDate) {
            return 0.0;
        }

        $from = $fromDate->toDateString();

        $r = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->where('jel.account_id', $cashAccountId)
            ->whereDate('je.transaction_date', '<', $from)
            ->selectRaw('SUM(jel.debit) as total_debit')
            ->selectRaw('SUM(jel.credit) as total_credit')
            ->first();

        $debit = round((float) ($r->total_debit ?? 0), 2);
        $credit = round((float) ($r->total_credit ?? 0), 2);

        return round($debit - $credit, 2);
    }

    private function baseCashLinesQuery(
        array $subshopIds,
        int $cashAccountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType
    ) {
        return DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->where('jel.account_id', $cashAccountId)
            ->when($fromDate !== null, fn ($qq) => $qq->whereDate('je.transaction_date', '>=', $fromDate->toDateString()))
            ->when($toDate !== null, fn ($qq) => $qq->whereDate('je.transaction_date', '<=', $toDate->toDateString()))
            ->when($referenceType !== null && $referenceType !== '', fn ($qq) => $qq->where('je.reference_type', $referenceType));
    }

    private function transactionsPaginated(
        array $subshopIds,
        int $cashAccountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType,
        int $perPage,
        ?int $page
    ): LengthAwarePaginator {
        $q = $this->baseCashLinesQuery($subshopIds, $cashAccountId, $fromDate, $toDate, $referenceType)
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by')
            ->leftJoin('journal_entry_lines as jel_other', function ($join) use ($cashAccountId) {
                $join->on('jel_other.journal_entry_id', '=', 'jel.journal_entry_id')
                    ->where('jel_other.account_id', '<>', $cashAccountId);
            })
            ->leftJoin('charts_of_accounts as coa_other', 'coa_other.id', '=', 'jel_other.account_id')
            ->select([
                'je.id as journal_entry_id',
                'je.reference_type as reference_type',
                'je.reference_id as reference_id',
                'je.transaction_date as transaction_date',
                'je.description as journal_description',
                'u.name as created_by_name',
                'jel.id as line_id',
                'jel.debit as debit',
                'jel.credit as credit',
                'jel.description as line_description',
                'coa_other.id as counter_account_id',
                'coa_other.account_code as counter_account_code',
                'coa_other.account_name as counter_account_name',
                'coa_other.cash_flow_category as counter_cash_flow_category',
            ])
            ->orderBy('je.transaction_date')
            ->orderBy('jel.id');

        return $q->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transactionsAll(
        array $subshopIds,
        int $cashAccountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType
    ): array {
        $q = $this->baseCashLinesQuery($subshopIds, $cashAccountId, $fromDate, $toDate, $referenceType)
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by')
            ->leftJoin('journal_entry_lines as jel_other', function ($join) use ($cashAccountId) {
                $join->on('jel_other.journal_entry_id', '=', 'jel.journal_entry_id')
                    ->where('jel_other.account_id', '<>', $cashAccountId);
            })
            ->leftJoin('charts_of_accounts as coa_other', 'coa_other.id', '=', 'jel_other.account_id')
            ->select([
                'je.id as journal_entry_id',
                'je.reference_type as reference_type',
                'je.reference_id as reference_id',
                'je.transaction_date as transaction_date',
                'je.description as journal_description',
                'u.name as created_by_name',
                'jel.id as line_id',
                'jel.debit as debit',
                'jel.credit as credit',
                'jel.description as line_description',
                'coa_other.id as counter_account_id',
                'coa_other.account_code as counter_account_code',
                'coa_other.account_name as counter_account_name',
                'coa_other.cash_flow_category as counter_cash_flow_category',
            ])
            ->orderBy('je.transaction_date')
            ->orderBy('jel.id');

        return $q->get()->map(function ($r) {
            $cat = (string) ($r->counter_cash_flow_category ?? '');
            $activity = $this->resolveActivityType($cat, (string) ($r->reference_type ?? ''));

            return [
                'journal_entry_id' => (int) ($r->journal_entry_id ?? 0),
                'reference_type' => (string) ($r->reference_type ?? ''),
                'reference_id' => (int) ($r->reference_id ?? 0),
                'transaction_date' => (string) ($r->transaction_date ?? ''),
                'journal_description' => (string) ($r->journal_description ?? ''),
                'created_by_name' => (string) ($r->created_by_name ?? ''),
                'line_id' => (int) ($r->line_id ?? 0),
                'debit' => round((float) ($r->debit ?? 0), 2),
                'credit' => round((float) ($r->credit ?? 0), 2),
                'line_description' => (string) ($r->line_description ?? ''),
                'counter_account_id' => (int) ($r->counter_account_id ?? 0),
                'counter_account_code' => (string) ($r->counter_account_code ?? ''),
                'counter_account_name' => (string) ($r->counter_account_name ?? ''),
                'activity_type' => $activity,
            ];
        })->all();
    }

    /** @return array{total_inflow:float,total_outflow:float} */
    private function periodTotals(
        array $subshopIds,
        int $cashAccountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType
    ): array {
        $r = $this->baseCashLinesQuery($subshopIds, $cashAccountId, $fromDate, $toDate, $referenceType)
            ->selectRaw('SUM(jel.debit) as total_inflow')
            ->selectRaw('SUM(jel.credit) as total_outflow')
            ->first();

        return [
            'total_inflow' => round((float) ($r->total_inflow ?? 0), 2),
            'total_outflow' => round((float) ($r->total_outflow ?? 0), 2),
        ];
    }

    private function sectionTotals(
        array $subshopIds,
        int $cashAccountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType
    ): array {
        $rows = $this->baseCashLinesQuery($subshopIds, $cashAccountId, $fromDate, $toDate, $referenceType)
            ->leftJoin('journal_entry_lines as jel_other', function ($join) use ($cashAccountId) {
                $join->on('jel_other.journal_entry_id', '=', 'jel.journal_entry_id')
                    ->where('jel_other.account_id', '<>', $cashAccountId);
            })
            ->leftJoin('charts_of_accounts as coa_other', 'coa_other.id', '=', 'jel_other.account_id')
            ->selectRaw('COALESCE(coa_other.cash_flow_category, "") as category')
            ->selectRaw('SUM(jel.debit) as inflow')
            ->selectRaw('SUM(jel.credit) as outflow')
            ->groupBy('category')
            ->get();

        $sections = $this->emptySections();

        foreach ($rows as $r) {
            $cat = (string) ($r->category ?? '');
            $activity = $this->resolveActivityType($cat, null);

            $in = round((float) ($r->inflow ?? 0), 2);
            $out = round((float) ($r->outflow ?? 0), 2);

            if (!isset($sections[$activity])) {
                continue;
            }

            $sections[$activity]['inflow'] = round(((float) $sections[$activity]['inflow']) + $in, 2);
            $sections[$activity]['outflow'] = round(((float) $sections[$activity]['outflow']) + $out, 2);
            $sections[$activity]['net'] = round(((float) $sections[$activity]['inflow']) - ((float) $sections[$activity]['outflow']), 2);
        }

        return $sections;
    }

    private function resolveActivityType(?string $cashFlowCategory, ?string $referenceType): string
    {
        $cat = strtoupper(trim((string) $cashFlowCategory));
        if (in_array($cat, ['OPERATING', 'INVESTING', 'FINANCING'], true)) {
            return $cat;
        }

        $rt = strtolower(trim((string) $referenceType));
        if ($rt !== '') {
            if (str_contains($rt, 'repay') || str_contains($rt, 'interest') || str_contains($rt, 'fee') || str_contains($rt, 'deposit') || str_contains($rt, 'expense')) {
                return 'OPERATING';
            }
            if (str_contains($rt, 'asset') || str_contains($rt, 'purchase') || str_contains($rt, 'sale')) {
                return 'INVESTING';
            }
            if (str_contains($rt, 'capital') || str_contains($rt, 'dividend') || str_contains($rt, 'withdraw') || str_contains($rt, 'loan')) {
                return 'FINANCING';
            }
        }

        return 'OPERATING';
    }

    private function priorMovementsBeforeRow(
        array $subshopIds,
        int $cashAccountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType,
        string $firstTransactionDate,
        int $firstLineId
    ): float {
        if ($firstTransactionDate === '' || $firstLineId <= 0) {
            return 0.0;
        }

        $r = $this->baseCashLinesQuery($subshopIds, $cashAccountId, $fromDate, $toDate, $referenceType)
            ->where(function ($qq) use ($firstTransactionDate, $firstLineId) {
                $qq->whereDate('je.transaction_date', '<', $firstTransactionDate)
                    ->orWhere(function ($qqq) use ($firstTransactionDate, $firstLineId) {
                        $qqq->whereDate('je.transaction_date', '=', $firstTransactionDate)
                            ->where('jel.id', '<', $firstLineId);
                    });
            })
            ->selectRaw('SUM(jel.debit) as total_debit')
            ->selectRaw('SUM(jel.credit) as total_credit')
            ->first();

        $debit = round((float) ($r->total_debit ?? 0), 2);
        $credit = round((float) ($r->total_credit ?? 0), 2);

        return round($debit - $credit, 2);
    }

    private function emptyTotals(): array
    {
        return [
            'total_inflow' => 0.0,
            'total_outflow' => 0.0,
            'net_cash_flow' => 0.0,
            'closing_balance' => 0.0,
        ];
    }

    private function emptySections(): array
    {
        return [
            'OPERATING' => ['inflow' => 0.0, 'outflow' => 0.0, 'net' => 0.0],
            'INVESTING' => ['inflow' => 0.0, 'outflow' => 0.0, 'net' => 0.0],
            'FINANCING' => ['inflow' => 0.0, 'outflow' => 0.0, 'net' => 0.0],
        ];
    }
}
