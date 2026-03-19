<?php

declare(strict_types=1);

namespace App\Services\Reports\Accounting;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    /**
     * @param array{account_id:int,from_date?:Carbon|null,to_date?:Carbon|null,subshop_id?:int|null,reference_type?:string|null,reference_search?:string|null,per_page?:int|null,page?:int|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $accountId = (int) ($filters['account_id'] ?? 0);
        if ($accountId <= 0) {
            return [
                'filters' => [],
                'account' => null,
                'account_type' => null,
                'opening' => ['total_debit' => 0.0, 'total_credit' => 0.0, 'balance' => 0.0],
                'totals' => ['period_debit' => 0.0, 'period_credit' => 0.0, 'closing_balance' => 0.0],
                'transactions' => null,
            ];
        }

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        $account = $this->loadAccount($accountId);
        $accountType = $this->detectAccountType((string) ($account['account_class_code'] ?? ''), (string) ($account['account_class_name'] ?? ''));

        $opening = $this->openingBalance($subshopIds, $accountId, $fromDate, $accountType);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = $perPage > 0 ? min($perPage, 200) : 50;

        $transactionsPaginator = $this->transactionsPaginated(
            subshopIds: $subshopIds,
            accountId: $accountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null,
            referenceSearch: $filters['reference_search'] ?? null,
            perPage: $perPage,
            page: isset($filters['page']) ? (int) $filters['page'] : null
        );

        $rows = array_map(static fn ($r) => is_array($r) ? $r : (array) $r, $transactionsPaginator->items());

        $pageStartBalance = $opening['balance'];
        if (!empty($rows)) {
            $first = $rows[0];
            $pageStartBalance = round(
                $opening['balance'] + $this->priorMovementsBeforeRow(
                    subshopIds: $subshopIds,
                    accountId: $accountId,
                    fromDate: $fromDate,
                    toDate: $toDate,
                    referenceType: $filters['reference_type'] ?? null,
                    referenceSearch: $filters['reference_search'] ?? null,
                    firstTransactionDate: (string) ($first['transaction_date'] ?? ''),
                    firstLineId: (int) ($first['line_id'] ?? 0),
                    accountType: $accountType
                ),
                2
            );
        }

        $running = $pageStartBalance;
        $rowsWithRunning = [];
        foreach ($rows as $r) {
            $debit = round((float) ($r['debit'] ?? 0), 2);
            $credit = round((float) ($r['credit'] ?? 0), 2);

            $running = round($running + $this->movement($debit, $credit, $accountType), 2);

            $r['running_balance'] = $running;
            $rowsWithRunning[] = $r;
        }

        $transactionsPaginator->setCollection(collect($rowsWithRunning));

        $periodTotals = $this->periodTotals(
            subshopIds: $subshopIds,
            accountId: $accountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null,
            referenceSearch: $filters['reference_search'] ?? null
        );

        $closingBalance = round(
            $opening['balance'] + $this->movement($periodTotals['period_debit'], $periodTotals['period_credit'], $accountType),
            2
        );

        return [
            'filters' => [
                'account_id' => $accountId,
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'subshop_ids' => $subshopIds,
                'reference_type' => $filters['reference_type'] ?? null,
                'reference_search' => $filters['reference_search'] ?? null,
            ],
            'account' => $account,
            'account_type' => $accountType,
            'opening' => $opening,
            'totals' => [
                'period_debit' => $periodTotals['period_debit'],
                'period_credit' => $periodTotals['period_credit'],
                'closing_balance' => $closingBalance,
            ],
            'transactions' => $transactionsPaginator,
        ];
    }

    /**
     * @param array{account_id:int,from_date?:Carbon|null,to_date?:Carbon|null,subshop_id?:int|null,reference_type?:string|null,reference_search?:string|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function export(array $filters, array $accessibleSubshopIds): array
    {
        $accountId = (int) ($filters['account_id'] ?? 0);
        if ($accountId <= 0) {
            return $this->build($filters, $accessibleSubshopIds);
        }

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        $account = $this->loadAccount($accountId);
        $accountType = $this->detectAccountType((string) ($account['account_class_code'] ?? ''), (string) ($account['account_class_name'] ?? ''));

        $opening = $this->openingBalance($subshopIds, $accountId, $fromDate, $accountType);

        $rows = $this->transactionsAll(
            subshopIds: $subshopIds,
            accountId: $accountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null,
            referenceSearch: $filters['reference_search'] ?? null
        );

        $running = $opening['balance'];
        foreach ($rows as &$r) {
            $debit = round((float) ($r['debit'] ?? 0), 2);
            $credit = round((float) ($r['credit'] ?? 0), 2);
            $running = round($running + $this->movement($debit, $credit, $accountType), 2);
            $r['running_balance'] = $running;
        }
        unset($r);

        $periodTotals = $this->periodTotals(
            subshopIds: $subshopIds,
            accountId: $accountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null,
            referenceSearch: $filters['reference_search'] ?? null
        );

        $closingBalance = round(
            $opening['balance'] + $this->movement($periodTotals['period_debit'], $periodTotals['period_credit'], $accountType),
            2
        );

        return [
            'filters' => [
                'account_id' => $accountId,
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'subshop_ids' => $subshopIds,
                'reference_type' => $filters['reference_type'] ?? null,
                'reference_search' => $filters['reference_search'] ?? null,
            ],
            'account' => $account,
            'account_type' => $accountType,
            'opening' => $opening,
            'totals' => [
                'period_debit' => $periodTotals['period_debit'],
                'period_credit' => $periodTotals['period_credit'],
                'closing_balance' => $closingBalance,
            ],
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

    /** @return array{account_id:int,account_code:string,account_name:string,account_class_id:int,account_class_code:string,account_class_name:string} */
    private function loadAccount(int $accountId): array
    {
        $r = DB::table('charts_of_accounts as coa')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->where('coa.id', $accountId)
            ->select([
                'coa.id as account_id',
                'coa.account_code as account_code',
                'coa.account_name as account_name',
                'coa.account_class_id as account_class_id',
                'ac.code as account_class_code',
                'ac.name as account_class_name',
            ])
            ->first();

        return [
            'account_id' => (int) ($r->account_id ?? $accountId),
            'account_code' => (string) ($r->account_code ?? ''),
            'account_name' => (string) ($r->account_name ?? ''),
            'account_class_id' => (int) ($r->account_class_id ?? 0),
            'account_class_code' => (string) ($r->account_class_code ?? ''),
            'account_class_name' => (string) ($r->account_class_name ?? ''),
        ];
    }

    private function detectAccountType(string $classCode, string $className): string
    {
        $code = trim($classCode);
        if ($code === '1') {
            return 'asset';
        }
        if ($code === '2') {
            return 'liability';
        }
        if ($code === '3') {
            return 'equity';
        }

        $name = strtolower(trim($className));
        if (str_contains($name, 'asset')) {
            return 'asset';
        }
        if (str_contains($name, 'liabil')) {
            return 'liability';
        }
        if (str_contains($name, 'equity')) {
            return 'equity';
        }

        return 'other';
    }

    /** @return array{total_debit:float,total_credit:float,balance:float} */
    private function openingBalance(array $subshopIds, int $accountId, ?Carbon $fromDate, string $accountType): array
    {
        if (!$fromDate) {
            return ['total_debit' => 0.0, 'total_credit' => 0.0, 'balance' => 0.0];
        }

        $from = $fromDate->toDateString();

        $r = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->where('jel.account_id', $accountId)
            ->whereDate('je.transaction_date', '<', $from)
            ->selectRaw('SUM(jel.debit) as total_debit')
            ->selectRaw('SUM(jel.credit) as total_credit')
            ->first();

        $debit = round((float) ($r->total_debit ?? 0), 2);
        $credit = round((float) ($r->total_credit ?? 0), 2);

        $balance = $this->movement($debit, $credit, $accountType);

        return [
            'total_debit' => $debit,
            'total_credit' => $credit,
            'balance' => round($balance, 2),
        ];
    }

    private function movement(float $debit, float $credit, string $accountType): float
    {
        if ($accountType === 'asset') {
            return $debit - $credit;
        }

        if ($accountType === 'liability' || $accountType === 'equity') {
            return $credit - $debit;
        }

        return $debit - $credit;
    }

    private function baseTransactionsQuery(
        array $subshopIds,
        int $accountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType,
        ?string $referenceSearch
    ) {
        $q = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by')
            ->whereIn('je.subshop_id', $subshopIds)
            ->where('jel.account_id', $accountId)
            ->when($fromDate !== null, fn ($qq) => $qq->whereDate('je.transaction_date', '>=', $fromDate->toDateString()))
            ->when($toDate !== null, fn ($qq) => $qq->whereDate('je.transaction_date', '<=', $toDate->toDateString()))
            ->when($referenceType !== null && $referenceType !== '', fn ($qq) => $qq->where('je.reference_type', $referenceType));

        if ($referenceSearch !== null && trim($referenceSearch) !== '') {
            $search = trim($referenceSearch);
            $searchNoHash = ltrim($search, '#');

            $q->where(function ($qq) use ($search, $searchNoHash) {
                $qq->where('je.description', 'like', '%' . $search . '%')
                    ->orWhere('jel.description', 'like', '%' . $search . '%')
                    ->orWhere('je.reference_type', 'like', '%' . $search . '%');

                if (ctype_digit($searchNoHash)) {
                    $id = (int) $searchNoHash;
                    $qq->orWhere('je.id', $id)
                        ->orWhere('je.reference_id', $id);
                }
            });
        }

        return $q;
    }

    private function transactionsPaginated(
        array $subshopIds,
        int $accountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType,
        ?string $referenceSearch,
        int $perPage,
        ?int $page
    ): LengthAwarePaginator {
        $q = $this->baseTransactionsQuery($subshopIds, $accountId, $fromDate, $toDate, $referenceType, $referenceSearch)
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
        int $accountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType,
        ?string $referenceSearch
    ): array {
        $q = $this->baseTransactionsQuery($subshopIds, $accountId, $fromDate, $toDate, $referenceType, $referenceSearch)
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
            ])
            ->orderBy('je.transaction_date')
            ->orderBy('jel.id');

        return $q->get()->map(fn ($r) => [
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
        ])->all();
    }

    /** @return array{period_debit:float,period_credit:float} */
    private function periodTotals(
        array $subshopIds,
        int $accountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType,
        ?string $referenceSearch
    ): array {
        $r = $this->baseTransactionsQuery($subshopIds, $accountId, $fromDate, $toDate, $referenceType, $referenceSearch)
            ->selectRaw('SUM(jel.debit) as period_debit')
            ->selectRaw('SUM(jel.credit) as period_credit')
            ->first();

        return [
            'period_debit' => round((float) ($r->period_debit ?? 0), 2),
            'period_credit' => round((float) ($r->period_credit ?? 0), 2),
        ];
    }

    private function priorMovementsBeforeRow(
        array $subshopIds,
        int $accountId,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        ?string $referenceType,
        ?string $referenceSearch,
        string $firstTransactionDate,
        int $firstLineId,
        string $accountType
    ): float {
        if ($firstTransactionDate === '' || $firstLineId <= 0) {
            return 0.0;
        }

        $r = $this->baseTransactionsQuery($subshopIds, $accountId, $fromDate, $toDate, $referenceType, $referenceSearch)
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

        return $this->movement($debit, $credit, $accountType);
    }
}
