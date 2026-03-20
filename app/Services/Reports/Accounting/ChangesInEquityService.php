<?php

declare(strict_types=1);

namespace App\Services\Reports\Accounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ChangesInEquityService
{
    /**
     * @param array{from_date:Carbon|null,to_date:Carbon|null,subshop_id?:int|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $subshopIds = $this->resolveSubshopFilter($filters['subshop_id'] ?? null, $accessibleSubshopIds);
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        if (!$fromDate || !$toDate) {
            return [
                'filters' => [
                    'from_date' => null,
                    'to_date' => null,
                    'subshop_ids' => $subshopIds,
                ],
                'has_data' => false,
            ];
        }

        // Get equity accounts
        $equityAccountIds = $this->getEquityAccountIds($subshopIds);
        
        // Calculate components
        $openingEquity = $this->calculateOpeningEquity($subshopIds, $fromDate, $equityAccountIds);
        $capitalContributions = $this->calculateCapitalContributions($subshopIds, $fromDate, $toDate, $equityAccountIds);
        $withdrawals = $this->calculateWithdrawals($subshopIds, $fromDate, $toDate, $equityAccountIds);
        $netProfit = $this->calculateNetProfit($subshopIds, $fromDate, $toDate);
        
        // Calculate closing equity
        $closingEquity = round($openingEquity + $capitalContributions + $netProfit - $withdrawals, 2);
        
        // Get equity from balance sheet for validation
        $balanceSheetEquity = $this->getBalanceSheetEquity($subshopIds, $toDate, $equityAccountIds);
        
        // Validation
        $diff = round($closingEquity - $balanceSheetEquity, 2);
        $balanced = abs($diff) <= 0.01;
        
        // Get equity breakdown
        $equityBreakdown = $this->getEquityBreakdown($subshopIds, $toDate, $equityAccountIds);
        
        // Get retained earnings
        $retainedEarnings = $this->calculateRetainedEarnings($subshopIds, $toDate);

        return [
            'filters' => [
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
                'subshop_ids' => $subshopIds,
            ],
            'has_data' => true,
            'opening_equity' => $openingEquity,
            'capital_contributions' => $capitalContributions,
            'withdrawals' => $withdrawals,
            'net_profit' => $netProfit,
            'closing_equity' => $closingEquity,
            'balance_sheet_equity' => $balanceSheetEquity,
            'validation' => [
                'balanced' => $balanced,
                'difference' => $diff,
            ],
            'equity_breakdown' => $equityBreakdown,
            'retained_earnings' => $retainedEarnings,
        ];
    }

    /**
     * @param array{from_date:Carbon|null,to_date:Carbon|null,subshop_id?:int|null} $filters
     * @param array<int> $accessibleSubshopIds
     */
    public function export(array $filters, array $accessibleSubshopIds): array
    {
        return $this->build($filters, $accessibleSubshopIds);
    }

    /**
     * @return array<int>
     */
    private function resolveSubshopFilter(?int $subshopId, array $accessibleSubshopIds): array
    {
        if ($subshopId) {
            return in_array($subshopId, $accessibleSubshopIds, true) ? [$subshopId] : [];
        }

        return $accessibleSubshopIds;
    }

    /**
     * Get all equity account IDs
     * @return array<int>
     */
    private function getEquityAccountIds(array $subshopIds): array
    {
        return DB::table('charts_of_accounts as coa')
            ->join('account_classes as ac', 'ac.id', '=', 'coa.account_class_id')
            ->whereIn('coa.subshop_id', $subshopIds)
            ->where('ac.code', '3') // EQUITY class code
            ->orWhere(function ($q) use ($subshopIds) {
                $q->whereIn('coa.subshop_id', $subshopIds)
                  ->whereRaw("UPPER(ac.name) LIKE '%EQUITY%'");
            })
            ->distinct()
            ->pluck('coa.id')
            ->toArray();
    }

    /**
     * Calculate opening equity (before from_date)
     * Equity increases on credit, decreases on debit
     */
    private function calculateOpeningEquity(array $subshopIds, Carbon $fromDate, array $equityAccountIds): float
    {
        if (empty($equityAccountIds)) {
            return 0.0;
        }

        $fromDateStr = $fromDate->toDateString();

        $result = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $equityAccountIds)
            ->whereDate('je.transaction_date', '<', $fromDateStr)
            ->selectRaw('SUM(jel.credit) as total_credit, SUM(jel.debit) as total_debit')
            ->first();

        $totalCredit = (float) ($result->total_credit ?? 0);
        $totalDebit = (float) ($result->total_debit ?? 0);

        return round($totalCredit - $totalDebit, 2);
    }

    /**
     * Calculate capital contributions (credits to equity accounts in period)
     */
    private function calculateCapitalContributions(array $subshopIds, Carbon $fromDate, Carbon $toDate, array $equityAccountIds): float
    {
        if (empty($equityAccountIds)) {
            return 0.0;
        }

        $fromDateStr = $fromDate->toDateString();
        $toDateStr = $toDate->toDateString();

        $result = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $equityAccountIds)
            ->whereDate('je.transaction_date', '>=', $fromDateStr)
            ->whereDate('je.transaction_date', '<=', $toDateStr)
            ->selectRaw('SUM(jel.credit) as total_credit')
            ->first();

        return round((float) ($result->total_credit ?? 0), 2);
    }

    /**
     * Calculate withdrawals (debits to equity accounts in period)
     */
    private function calculateWithdrawals(array $subshopIds, Carbon $fromDate, Carbon $toDate, array $equityAccountIds): float
    {
        if (empty($equityAccountIds)) {
            return 0.0;
        }

        $fromDateStr = $fromDate->toDateString();
        $toDateStr = $toDate->toDateString();

        $result = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $equityAccountIds)
            ->whereDate('je.transaction_date', '>=', $fromDateStr)
            ->whereDate('je.transaction_date', '<=', $toDateStr)
            ->selectRaw('SUM(jel.debit) as total_debit')
            ->first();

        return round((float) ($result->total_debit ?? 0), 2);
    }

    /**
     * Calculate net profit (income - expenses) for the period
     */
    private function calculateNetProfit(array $subshopIds, Carbon $fromDate, Carbon $toDate): float
    {
        $fromDateStr = $fromDate->toDateString();
        $toDateStr = $toDate->toDateString();

        // Get income accounts (class code 4 or name contains INCOME)
        $incomeClassId = DB::table('account_classes')
            ->where(function ($q) {
                $q->where('code', '4')
                  ->orWhereRaw("UPPER(name) LIKE '%INCOME%'")
                  ->orWhereRaw("UPPER(name) LIKE '%REVENUE%'");
            })
            ->pluck('id')
            ->toArray();

        // Get expense accounts (class code 5, 6 or name contains EXPENSE/COST)
        $expenseClassId = DB::table('account_classes')
            ->where(function ($q) {
                $q->whereIn('code', ['5', '6'])
                  ->orWhereRaw("UPPER(name) LIKE '%EXPENSE%'")
                  ->orWhereRaw("UPPER(name) LIKE '%COST%'");
            })
            ->pluck('id')
            ->toArray();

        // Calculate total income (credit - debit for income accounts)
        $totalIncome = 0.0;
        if (!empty($incomeClassId)) {
            $incomeResult = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
                ->whereIn('je.subshop_id', $subshopIds)
                ->whereIn('coa.account_class_id', $incomeClassId)
                ->whereDate('je.transaction_date', '>=', $fromDateStr)
                ->whereDate('je.transaction_date', '<=', $toDateStr)
                ->selectRaw('SUM(jel.credit) as total_credit, SUM(jel.debit) as total_debit')
                ->first();

            $incomeCredit = (float) ($incomeResult->total_credit ?? 0);
            $incomeDebit = (float) ($incomeResult->total_debit ?? 0);
            $totalIncome = round($incomeCredit - $incomeDebit, 2);
        }

        // Calculate total expenses (debit - credit for expense accounts)
        $totalExpenses = 0.0;
        if (!empty($expenseClassId)) {
            $expenseResult = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
                ->whereIn('je.subshop_id', $subshopIds)
                ->whereIn('coa.account_class_id', $expenseClassId)
                ->whereDate('je.transaction_date', '>=', $fromDateStr)
                ->whereDate('je.transaction_date', '<=', $toDateStr)
                ->selectRaw('SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
                ->first();

            $expenseDebit = (float) ($expenseResult->total_debit ?? 0);
            $expenseCredit = (float) ($expenseResult->total_credit ?? 0);
            $totalExpenses = round($expenseDebit - $expenseCredit, 2);
        }

        return round($totalIncome - $totalExpenses, 2);
    }

    /**
     * Get equity balance from balance sheet as of to_date
     */
    private function getBalanceSheetEquity(array $subshopIds, Carbon $toDate, array $equityAccountIds): float
    {
        if (empty($equityAccountIds)) {
            return 0.0;
        }

        $toDateStr = $toDate->toDateString();

        $result = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $equityAccountIds)
            ->whereDate('je.transaction_date', '<=', $toDateStr)
            ->selectRaw('SUM(jel.credit) as total_credit, SUM(jel.debit) as total_debit')
            ->first();

        $totalCredit = (float) ($result->total_credit ?? 0);
        $totalDebit = (float) ($result->total_debit ?? 0);

        return round($totalCredit - $totalDebit, 2);
    }

    /**
     * Get equity breakdown by account
     */
    private function getEquityBreakdown(array $subshopIds, Carbon $toDate, array $equityAccountIds): array
    {
        if (empty($equityAccountIds)) {
            return [];
        }

        $toDateStr = $toDate->toDateString();

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->whereIn('je.subshop_id', $subshopIds)
            ->whereIn('jel.account_id', $equityAccountIds)
            ->whereDate('je.transaction_date', '<=', $toDateStr)
            ->groupBy('coa.id', 'coa.account_code', 'coa.account_name')
            ->selectRaw('coa.id as account_id')
            ->selectRaw('coa.account_code as account_code')
            ->selectRaw('coa.account_name as account_name')
            ->selectRaw('SUM(jel.credit) as total_credit')
            ->selectRaw('SUM(jel.debit) as total_debit')
            ->orderBy('coa.account_code')
            ->get();

        return $rows->map(function ($r) {
            $credit = (float) ($r->total_credit ?? 0);
            $debit = (float) ($r->total_debit ?? 0);
            return [
                'account_id' => (int) $r->account_id,
                'account_code' => (string) $r->account_code,
                'account_name' => (string) $r->account_name,
                'balance' => round($credit - $debit, 2),
            ];
        })->toArray();
    }

    /**
     * Calculate retained earnings (cumulative net profit up to date)
     */
    private function calculateRetainedEarnings(array $subshopIds, Carbon $toDate): float
    {
        $toDateStr = $toDate->toDateString();

        // Get income accounts
        $incomeClassId = DB::table('account_classes')
            ->where(function ($q) {
                $q->where('code', '4')
                  ->orWhereRaw("UPPER(name) LIKE '%INCOME%'")
                  ->orWhereRaw("UPPER(name) LIKE '%REVENUE%'");
            })
            ->pluck('id')
            ->toArray();

        // Get expense accounts
        $expenseClassId = DB::table('account_classes')
            ->where(function ($q) {
                $q->whereIn('code', ['5', '6'])
                  ->orWhereRaw("UPPER(name) LIKE '%EXPENSE%'")
                  ->orWhereRaw("UPPER(name) LIKE '%COST%'");
            })
            ->pluck('id')
            ->toArray();

        $totalIncome = 0.0;
        if (!empty($incomeClassId)) {
            $incomeResult = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
                ->whereIn('je.subshop_id', $subshopIds)
                ->whereIn('coa.account_class_id', $incomeClassId)
                ->whereDate('je.transaction_date', '<=', $toDateStr)
                ->selectRaw('SUM(jel.credit) as total_credit, SUM(jel.debit) as total_debit')
                ->first();

            $incomeCredit = (float) ($incomeResult->total_credit ?? 0);
            $incomeDebit = (float) ($incomeResult->total_debit ?? 0);
            $totalIncome = round($incomeCredit - $incomeDebit, 2);
        }

        $totalExpenses = 0.0;
        if (!empty($expenseClassId)) {
            $expenseResult = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('charts_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
                ->whereIn('je.subshop_id', $subshopIds)
                ->whereIn('coa.account_class_id', $expenseClassId)
                ->whereDate('je.transaction_date', '<=', $toDateStr)
                ->selectRaw('SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
                ->first();

            $expenseDebit = (float) ($expenseResult->total_debit ?? 0);
            $expenseCredit = (float) ($expenseResult->total_credit ?? 0);
            $totalExpenses = round($expenseDebit - $expenseCredit, 2);
        }

        return round($totalIncome - $totalExpenses, 2);
    }
}
