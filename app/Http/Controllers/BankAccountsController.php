<?php

namespace App\Http\Controllers;

use App\Models\BankAccounts;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\SubShop;
use App\Models\ChartsOfAccount;
use App\Models\JournalEntries;
use App\Models\JournalEntryLines;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankAccountsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('accounting.bank_accounts.index')]);
            }
            
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $bankAccounts = BankAccounts::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            $summaryTotalAccounts = (int) $bankAccounts->count();
            $summaryActiveAccounts = (int) $bankAccounts->where('is_active', true)->count();
            $summaryInactiveAccounts = (int) $bankAccounts->where('is_active', false)->count();
            $summaryTotalOpeningBalance = (float) $bankAccounts->sum('opening_balance');

            $chartAccounts = ChartsOfAccount::whereIn('subshop_id', $shopSubshopIds)
                ->where('is_active', true)
                ->orderBy('account_name')
                ->get();

            if ($chartAccounts->isEmpty()) {
                return redirect()->back()->with('info', 'No chart of accounts found for this Branch. Please create accounts first before adding bank accounts.');
            }

            return view('accounting.bank_accounts.index', compact(
                'subshop',
                'bankAccounts',
                'chartAccounts',
                'summaryTotalAccounts',
                'summaryActiveAccounts',
                'summaryInactiveAccounts',
                'summaryTotalOpeningBalance',
            ));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load bank accounts: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('accounting.bank_accounts.index')]);
            }

            $validated = $request->validate([
                'account_name' => 'required|string|max:255',
                'account_type' => 'required|in:bank,cash,mobile_money',
                'bank_name' => 'required_if:account_type,bank,mobile_money|nullable|string|max:255',
                'account_number' => 'required_if:account_type,bank,mobile_money|nullable|string|max:255',
                'opening_balance' => 'required|numeric|min:0',
                'currency_code' => 'required|string|max:3',
                'chart_of_account_id' => 'required|exists:charts_of_accounts,id',
                'is_active' => 'sometimes|boolean',
                'description' => 'nullable|string|max:1000',
            ]);

            $bankAccount = BankAccounts::create([
                'subshop_id' => $subshopId,
                'account_name' => $validated['account_name'],
                'account_type' => $validated['account_type'],
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'opening_balance' => $validated['opening_balance'],
                'currency_code' => $validated['currency_code'],
                'chart_of_account_id' => $validated['chart_of_account_id'],
                'is_active' => $request->boolean('is_active'),
                'description' => $validated['description'],
                'created_by' => auth()->id(),
            ]);

            return redirect()->back()->with('success', 'Bank account created successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create bank account: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(BankAccounts $bank_account)
    {
        $subshopId = (int) session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('accounting.bank_accounts.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

        if (!$shopSubshopIds->contains((int) $bank_account->subshop_id)) {
            abort(403);
        }

        $bankAccount = $bank_account;
        $bankAccount->loadMissing(['chartOfAccount']);

        $glAccountId = (int) ($bankAccount->chart_of_account_id ?? 0);
        if ($glAccountId <= 0) {
            return redirect()->route('accounting.bank_accounts.index')->with('error', 'Bank account is not linked to a GL account.');
        }

        $bankLinesQ = JournalEntryLines::query()
            ->where('account_id', $glAccountId);

        $ledgerBalance = (float) $bankLinesQ->clone()->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as bal')->value('bal');
        $totalInflows = (float) $bankLinesQ->clone()->sum('debit');
        $totalOutflows = (float) $bankLinesQ->clone()->sum('credit');
        $totalTransactions = (int) $bankLinesQ->clone()->count();

        $matchedJournalIdsSub = BankStatementLine::query()
            ->join('bank_statements', 'bank_statement_lines.bank_statement_id', '=', 'bank_statements.id')
            ->where('bank_statements.bank_account_id', (int) $bankAccount->id)
            ->where('bank_statement_lines.is_matched', true)
            ->whereNotNull('bank_statement_lines.matched_journal_entry_id')
            ->select('bank_statement_lines.matched_journal_entry_id');

        $unreconciledTransactions = (int) JournalEntries::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->whereHas('lines', function ($q) use ($glAccountId) {
                $q->where('account_id', $glAccountId);
            })
            ->whereNotIn('id', $matchedJournalIdsSub)
            ->count();

        $startMonth = Carbon::now()->startOfMonth()->subMonths(11);
        $monthlyRows = JournalEntryLines::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $glAccountId)
            ->whereDate('journal_entries.transaction_date', '>=', $startMonth->toDateString())
            ->groupBy('month')
            ->orderBy('month')
            ->selectRaw("DATE_FORMAT(journal_entries.transaction_date, '%Y-%m-01') as month")
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit),0) as inflows')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit),0) as outflows')
            ->get();

        $monthlyActivity = collect();
        for ($i = 0; $i < 12; $i++) {
            $m = $startMonth->copy()->addMonths($i)->format('Y-m-01');
            $row = $monthlyRows->firstWhere('month', $m);
            $in = (float) ($row->inflows ?? 0);
            $out = (float) ($row->outflows ?? 0);
            $monthlyActivity->push([
                'month' => Carbon::parse($m),
                'inflows' => $in,
                'outflows' => $out,
                'net' => $in - $out,
            ]);
        }

        $recentLines = JournalEntryLines::query()
            ->with(['journalEntry'])
            ->where('account_id', $glAccountId)
            ->whereHas('journalEntry', function ($q) use ($shopSubshopIds) {
                $q->whereIn('subshop_id', $shopSubshopIds);
            })
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderByDesc('journal_entries.transaction_date')
            ->orderByDesc('journal_entry_lines.id')
            ->limit(15)
            ->get(['journal_entry_lines.*']);

        $recentNet = (float) $recentLines->sum(function ($l) {
            return (float) $l->debit - (float) $l->credit;
        });
        $runningStart = $ledgerBalance - $recentNet;
        $running = $runningStart;
        $recentTransactions = $recentLines
            ->sortBy(function ($l) {
                return optional($l->journalEntry)->transaction_date ? optional($l->journalEntry)->transaction_date->format('Y-m-d') : '0000-00-00';
            })
            ->values()
            ->map(function ($l) use (&$running) {
                $running += ((float) $l->debit - (float) $l->credit);
                $j = $l->journalEntry;
                return [
                    'date' => $j?->transaction_date,
                    'description' => $j?->description ?? $l->description,
                    'debit' => (float) $l->debit,
                    'credit' => (float) $l->credit,
                    'balance' => $running,
                    'reference_type' => $j?->reference_type,
                    'journal_entry_id' => (int) ($j?->id ?? 0),
                ];
            })
            ->reverse()
            ->values();

        $lastReconciled = BankStatement::query()
            ->where('bank_account_id', (int) $bankAccount->id)
            ->where('status', 'reconciled')
            ->orderByDesc('reconciled_at')
            ->orderByDesc('statement_date')
            ->first();

        $latestStatement = BankStatement::query()
            ->where('bank_account_id', (int) $bankAccount->id)
            ->orderByDesc('statement_date')
            ->orderByDesc('id')
            ->first();

        $unmatchedStatementLines = 0;
        if ($latestStatement) {
            $unmatchedStatementLines = (int) BankStatementLine::query()
                ->where('bank_statement_id', (int) $latestStatement->id)
                ->where(function ($q) {
                    $q->where('is_matched', false)->orWhereNull('matched_journal_entry_id');
                })
                ->count();
        }

        $statementBalance = (float) ($lastReconciled?->closing_balance ?? $latestStatement?->closing_balance ?? 0);
        $difference = (float) ($statementBalance - $ledgerBalance);

        $reconciliationStatus = [
            'last_reconciled_at' => $lastReconciled?->reconciled_at,
            'last_statement_date' => $lastReconciled?->statement_date,
            'last_statement_closing_balance' => (float) ($lastReconciled?->closing_balance ?? 0),
            'latest_statement_id' => (int) ($latestStatement?->id ?? 0),
            'latest_statement_date' => $latestStatement?->statement_date,
            'latest_statement_closing_balance' => (float) ($latestStatement?->closing_balance ?? 0),
            'ledger_balance' => $ledgerBalance,
            'difference' => $difference,
            'unmatched_statement_lines' => $unmatchedStatementLines,
        ];

        $statements = BankStatement::query()
            ->where('bank_account_id', (int) $bankAccount->id)
            ->orderByDesc('statement_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $sourceBreakdown = JournalEntryLines::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $glAccountId)
            ->whereIn('journal_entries.subshop_id', $shopSubshopIds)
            ->groupBy('journal_entries.reference_type')
            ->orderByRaw('COALESCE(SUM(journal_entry_lines.debit),0) + COALESCE(SUM(journal_entry_lines.credit),0) DESC')
            ->selectRaw('journal_entries.reference_type as reference_type')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit),0) as inflows')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit),0) as outflows')
            ->get()
            ->map(function ($r) {
                $in = (float) ($r->inflows ?? 0);
                $out = (float) ($r->outflows ?? 0);
                return [
                    'reference_type' => $r->reference_type ?: 'unknown',
                    'inflows' => $in,
                    'outflows' => $out,
                    'net' => $in - $out,
                ];
            });

        $stats = [
            'ledger_balance' => round($ledgerBalance, 2),
            'total_inflows' => round($totalInflows, 2),
            'total_outflows' => round($totalOutflows, 2),
            'total_transactions' => $totalTransactions,
            'unreconciled_transactions' => $unreconciledTransactions,
        ];

        return view('accounting.bank_accounts.show', compact(
            'subshop',
            'bankAccount',
            'stats',
            'monthlyActivity',
            'recentTransactions',
            'reconciliationStatus',
            'statements',
            'sourceBreakdown',
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $bankAccount = BankAccounts::findOrFail($id);

            $validated = $request->validate([
                'account_name' => 'required|string|max:255',
                'account_type' => 'required|in:bank,cash,mobile_money',
                'bank_name' => 'required_if:account_type,bank,mobile_money|nullable|string|max:255',
                'account_number' => 'required_if:account_type,bank,mobile_money|nullable|string|max:255',
                'opening_balance' => 'required|numeric|min:0',
                'currency_code' => 'required|string|max:3',
                'chart_of_account_id' => 'required|exists:charts_of_accounts,id',
                'is_active' => 'sometimes|boolean',
                'description' => 'nullable|string|max:1000',
            ]);

            $bankAccount->update([
                'account_name' => $validated['account_name'],
                'account_type' => $validated['account_type'],
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'opening_balance' => $validated['opening_balance'],
                'currency_code' => $validated['currency_code'],
                'chart_of_account_id' => $validated['chart_of_account_id'],
                'is_active' => $request->boolean('is_active'),
                'description' => $validated['description'],
                'updated_by' => auth()->id(),
            ]);

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bank account updated successfully!',
                ]);
            }

            return redirect()->back()->with('success', 'Bank account updated successfully!');
            
        } catch (\Exception $e) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update bank account: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to update bank account: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $bankAccount = BankAccounts::findOrFail($id);
            $bankAccount->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Bank account deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank account: ' . $e->getMessage()
            ], 500);
        }
    }
}
