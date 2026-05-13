<?php

declare(strict_types=1);

namespace App\Services\Reports\Accounting;

use App\Services\Reports\Accounting\AccountClassificationTrait;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CashBookService
{
    use AccountClassificationTrait;
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $accountId = (int) ($filters['cash_account_id'] ?? 0);
        if ($accountId <= 0) {
            return [
                'filters' => [],
                'account' => null,
                'opening' => ['total_debit' => 0.0, 'total_credit' => 0.0, 'balance' => 0.0],
                'totals' => ['period_debit' => 0.0, 'period_credit' => 0.0, 'closing_balance' => 0.0],
                'transactions' => null,
            ];
        }

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        $account = $this->loadAccount($accountId);

        // Validate that the account is a valid cash/bank account
        if (($account['is_valid_cash_account'] ?? false) === false) {
            return [
                'filters' => [
                    'cash_account_id' => $accountId,
                    'from_date' => $fromDate?->toDateString(),
                    'to_date' => $toDate?->toDateString(),
                    'subshop_ids' => $subshopIds,
                    'reference_type' => $filters['reference_type'] ?? null,
                    'reference_search' => $filters['reference_search'] ?? null,
                ],
                'account' => $account,
                'error' => $account['validation_error'] ?? 'Invalid cash account',
                'opening' => ['total_debit' => 0.0, 'total_credit' => 0.0, 'balance' => 0.0],
                'totals' => ['period_debit' => 0.0, 'period_credit' => 0.0, 'closing_balance' => 0.0],
                'transactions' => null,
            ];
        }

        $opening = $this->openingBalance($subshopIds, $accountId, $fromDate);

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
            accountId: $accountId,
            fromDate: $fromDate,
            toDate: $toDate,
            referenceType: $filters['reference_type'] ?? null,
            referenceSearch: $filters['reference_search'] ?? null
        );

        $closingBalance = round($opening['balance'] + (($periodTotals['period_debit'] ?? 0) - ($periodTotals['period_credit'] ?? 0)), 2);

        return [
            'filters' => [
                'cash_account_id' => $accountId,
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'subshop_ids' => $subshopIds,
                'reference_type' => $filters['reference_type'] ?? null,
                'reference_search' => $filters['reference_search'] ?? null,
            ],
            'account' => $account,
            'opening' => $opening,
            'totals' => [
                'period_debit' => $periodTotals['period_debit'],
                'period_credit' => $periodTotals['period_credit'],
                'closing_balance' => $closingBalance,
            ],
            'transactions' => $transactionsPaginator,
        ];
    }

    public function export(array $filters, array $accessibleSubshopIds): array
    {
        $accountId = (int) ($filters['cash_account_id'] ?? 0);
        if ($accountId <= 0) {
            return $this->build($filters, $accessibleSubshopIds);
        }

        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        $account = $this->loadAccount($accountId);

        // Validate that the account is a valid cash/bank account
        if (($account['is_valid_cash_account'] ?? false) === false) {
            return [
                'filters' => [
                    'cash_account_id' => $accountId,
                    'from_date' => $fromDate?->toDateString(),
                    'to_date' => $toDate?->toDateString(),
                    'subshop_ids' => $subshopIds,
                    'reference_type' => $filters['reference_type'] ?? null,
                    'reference_search' => $filters['reference_search'] ?? null,
                ],
                'account' => $account,
                'error' => $account['validation_error'] ?? 'Invalid cash account',
                'opening' => ['total_debit' => 0.0, 'total_credit' => 0.0, 'balance' => 0.0],
                'totals' => ['period_debit' => 0.0, 'period_credit' => 0.0, 'closing_balance' => 0.0],
                'transactions_all' => [],
            ];
        }

        $opening = $this->openingBalance($subshopIds, $accountId, $fromDate);

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
            $running = round($running + ($debit - $credit), 2);
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

        $closingBalance = round($opening['balance'] + (($periodTotals['period_debit'] ?? 0) - ($periodTotals['period_credit'] ?? 0)), 2);

        return [
            'filters' => [
                'cash_account_id' => $accountId,
                'from_date' => $fromDate?->toDateString(),
                'to_date' => $toDate?->toDateString(),
                'subshop_ids' => $subshopIds,
                'reference_type' => $filters['reference_type'] ?? null,
                'reference_search' => $filters['reference_search'] ?? null,
            ],
            'account' => $account,
            'opening' => $opening,
            'totals' => [
                'period_debit' => $periodTotals['period_debit'],
                'period_credit' => $periodTotals['period_credit'],
                'closing_balance' => $closingBalance,
            ],
            'transactions_all' => $rows,
        ];
    }

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

        if (!$r) {
            return [
                'account_id' => $accountId,
                'account_code' => '',
                'account_name' => 'Account not found',
                'account_class_id' => 0,
                'account_class_code' => '',
                'account_class_name' => '',
            ];
        }

        $classCode = (string) ($r->account_class_code ?? '');
        $className = (string) ($r->account_class_name ?? '');
        $category = $this->classifyAccountClass($classCode, $className);

        // Validate that this is an asset account (required for cash book reporting)
        if ($category !== 'assets') {
            return [
                'account_id' => $accountId,
                'account_code' => (string) ($r->account_code ?? ''),
                'account_name' => (string) ($r->account_name ?? ''),
                'account_class_id' => (int) ($r->account_class_id ?? 0),
                'account_class_code' => $classCode,
                'account_class_name' => $className,
                'is_valid_cash_account' => false,
                'validation_error' => 'Account must be an asset account (class code 1) for cash book reporting',
            ];
        }

        return [
            'account_id' => (int) ($r->account_id ?? $accountId),
            'account_code' => (string) ($r->account_code ?? ''),
            'account_name' => (string) ($r->account_name ?? ''),
            'account_class_id' => (int) ($r->account_class_id ?? 0),
            'account_class_code' => $classCode,
            'account_class_name' => $className,
            'is_valid_cash_account' => true,
        ];
    }

    private function openingBalance(array $subshopIds, int $accountId, ?Carbon $fromDate): array
    {
        if (!$fromDate) {
            return ['total_debit' => 0.0, 'total_credit' => 0.0, 'balance' => 0.0];
        }

        $r = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->where('jel.account_id', $accountId)
            ->whereDate('je.transaction_date', '<', $fromDate->toDateString())
            ->selectRaw('COALESCE(SUM(jel.debit),0) as total_debit, COALESCE(SUM(jel.credit),0) as total_credit')
            ->first();

        $totalDebit = round((float) ($r->total_debit ?? 0), 2);
        $totalCredit = round((float) ($r->total_credit ?? 0), 2);

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => round($totalDebit - $totalCredit, 2),
        ];
    }

    private function transactionsBaseQuery(array $subshopIds, int $accountId, ?Carbon $fromDate, ?Carbon $toDate, ?string $referenceType, ?string $referenceSearch)
    {
        $q = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by')
            ->whereIn('je.subshop_id', $subshopIds)
            ->where('jel.account_id', $accountId);

        if ($fromDate) {
            $q->whereDate('je.transaction_date', '>=', $fromDate->toDateString());
        }
        if ($toDate) {
            $q->whereDate('je.transaction_date', '<=', $toDate->toDateString());
        }

        if ($referenceType) {
            $q->where('je.reference_type', $referenceType);
        }

        if ($referenceSearch) {
            $ref = trim((string) $referenceSearch);
            $ref = ltrim($ref, '#');

            $q->where(function ($w) use ($ref) {
                if ($ref !== '' && ctype_digit($ref)) {
                    $id = (int) $ref;
                    $w->where('je.id', $id)->orWhere('je.reference_id', $id);
                } else {
                    $w->where('je.description', 'like', '%' . $ref . '%');
                }
            });
        }

        return $q;
    }

    private function transactionsPaginated(array $subshopIds, int $accountId, ?Carbon $fromDate, ?Carbon $toDate, ?string $referenceType, ?string $referenceSearch, int $perPage, ?int $page): LengthAwarePaginator
    {
        $q = $this->transactionsBaseQuery($subshopIds, $accountId, $fromDate, $toDate, $referenceType, $referenceSearch)
            ->select([
                'jel.id as line_id',
                'je.transaction_date as transaction_date',
                'je.id as journal_entry_id',
                'je.reference_type as reference_type',
                'je.reference_id as reference_id',
                'je.description as journal_description',
                'jel.description as line_description',
                'u.name as created_by_name',
                'jel.debit as debit',
                'jel.credit as credit',
            ])
            ->orderBy('je.transaction_date')
            ->orderBy('jel.id');

        return $q->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    private function transactionsAll(array $subshopIds, int $accountId, ?Carbon $fromDate, ?Carbon $toDate, ?string $referenceType, ?string $referenceSearch): array
    {
        $rows = $this->transactionsBaseQuery($subshopIds, $accountId, $fromDate, $toDate, $referenceType, $referenceSearch)
            ->select([
                'jel.id as line_id',
                'je.transaction_date as transaction_date',
                'je.id as journal_entry_id',
                'je.reference_type as reference_type',
                'je.reference_id as reference_id',
                'je.description as journal_description',
                'jel.description as line_description',
                'u.name as created_by_name',
                'jel.debit as debit',
                'jel.credit as credit',
            ])
            ->orderBy('je.transaction_date')
            ->orderBy('jel.id')
            ->get();

        return array_map(static fn ($r) => is_array($r) ? $r : (array) $r, $rows->all());
    }

    private function priorMovementsBeforeRow(array $subshopIds, int $accountId, ?Carbon $fromDate, ?Carbon $toDate, ?string $referenceType, ?string $referenceSearch, string $firstTransactionDate, int $firstLineId): float
    {
        if ($firstTransactionDate === '') {
            return 0.0;
        }

        $q = $this->transactionsBaseQuery($subshopIds, $accountId, $fromDate, $toDate, $referenceType, $referenceSearch);

        $q->where(function ($w) use ($firstTransactionDate, $firstLineId) {
            $w->whereDate('je.transaction_date', '<', $firstTransactionDate);
            $w->orWhere(function ($w2) use ($firstTransactionDate, $firstLineId) {
                $w2->whereDate('je.transaction_date', '=', $firstTransactionDate)
                    ->where('jel.id', '<', $firstLineId);
            });
        });

        $r = $q->selectRaw('COALESCE(SUM(jel.debit),0) as total_debit, COALESCE(SUM(jel.credit),0) as total_credit')->first();

        return round(((float) ($r->total_debit ?? 0)) - ((float) ($r->total_credit ?? 0)), 2);
    }

    private function periodTotals(array $subshopIds, int $accountId, ?Carbon $fromDate, ?Carbon $toDate, ?string $referenceType, ?string $referenceSearch): array
    {
        $q = $this->transactionsBaseQuery($subshopIds, $accountId, $fromDate, $toDate, $referenceType, $referenceSearch);

        $r = $q->selectRaw('COALESCE(SUM(jel.debit),0) as period_debit, COALESCE(SUM(jel.credit),0) as period_credit')->first();

        return [
            'period_debit' => round((float) ($r->period_debit ?? 0), 2),
            'period_credit' => round((float) ($r->period_credit ?? 0), 2),
        ];
    }
}
