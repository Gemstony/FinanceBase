<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BankAccounts;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\ChartsOfAccount;
use App\Models\JournalEntries;
use App\Models\SubShop;
use App\Services\BankReconciliation\AutoJournalService;
use App\Services\BankReconciliation\ReconciliationMatcher;
use App\Services\BankReconciliation\ReconciliationService;
use App\Services\BankReconciliation\StatementImportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly StatementImportService $importService,
        private readonly ReconciliationMatcher $matcher,
        private readonly ReconciliationService $reconciliationService,
        private readonly AutoJournalService $autoJournalService,
    ) {
        $this->middleware('can:perform_bank_reconciliation');
    }

    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $statements = BankStatement::query()
            ->with(['bankAccount'])
            ->whereHas('bankAccount', function ($q) use ($shopSubshopIds) {
                $q->whereIn('subshop_id', $shopSubshopIds);
            })
            ->orderByDesc('id')
            ->paginate(20);

        return view('bank_reconciliation.index', compact('statements'));
    }

    public function create(): View
    {
        $subshopId = (int) session('subshop_id');

        
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        // Get all subshop IDs under this shop for validation
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');
        $bankAccounts = BankAccounts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get(['id', 'account_name']);

        return view('bank_reconciliation.create', compact('bankAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

                
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        // Get all subshop IDs under this shop for validation
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $data = $request->validate([
            'bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'statement_date' => 'required|date',
            'opening_balance' => 'required|numeric',
            'closing_balance' => 'required|numeric',
            'reference_number' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:2000',
        ]);

        $bank = BankAccounts::query()->whereKey((int) $data['bank_account_id'])->firstOrFail();
        if (!in_array((int) $bank->subshop_id, $shopSubshopIds->toArray())) {
            abort(403);
        }

        $statement = BankStatement::query()->create([
            'bank_account_id' => (int) $data['bank_account_id'],
            'statement_date' => Carbon::parse((string) $data['statement_date'])->toDateString(),
            'opening_balance' => round((float) $data['opening_balance'], 2),
            'closing_balance' => round((float) $data['closing_balance'], 2),
            'reference_number' => $data['reference_number'] ?? null,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('bank-reconciliation.show', $statement->id)
            ->with('success', 'Bank statement created.');
    }

    public function show(int $id): View
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $statement = BankStatement::query()
            ->with(['bankAccount', 'lines'])
            ->whereKey($id)
            ->firstOrFail();

        if (!in_array((int) $statement->bankAccount->subshop_id, $shopSubshopIds->toArray())) {
            abort(403);
        }

        $summary = $this->reconciliationService->summary($statement);

        return view('bank_reconciliation.show', compact('statement', 'summary'));
    }

    public function import(Request $request, int $id): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $statement = BankStatement::query()->with('bankAccount')->whereKey($id)->firstOrFail();
        if (!in_array((int) $statement->bankAccount->subshop_id, $shopSubshopIds->toArray())) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        if ((string) $statement->status === 'reconciled') {
            return redirect()->back()->with('error', 'Statement is already reconciled.');
        }

        $count = $this->importService->importCsv($statement, $request->file('file'));

        return redirect()->route('bank-reconciliation.reconcile', $statement->id)
            ->with('success', "Imported {$count} statement lines.");
    }

    public function reconcile(int $id): View
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $statement = BankStatement::query()
            ->with(['bankAccount', 'lines.matchedJournalEntry.lines'])
            ->whereKey($id)
            ->firstOrFail();

        if (!in_array((int) $statement->bankAccount->subshop_id, $shopSubshopIds->toArray())) {
            abort(403);
        }

        $summary = $this->reconciliationService->summary($statement);

        $journals = JournalEntries::query()
            ->with('lines')
            ->whereIn('subshop_id', $shopSubshopIds)
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('bank_reconciliation.reconcile', compact('statement', 'summary', 'journals'));
    }

    public function matchLine(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'statement_line_id' => 'required|integer|exists:bank_statement_lines,id',
            'journal_entry_id' => 'required|integer|exists:journal_entries,id',
        ]);

        $line = BankStatementLine::query()->with('statement')->findOrFail((int) $data['statement_line_id']);

        $this->reconciliationService->manualMatch((int) $data['statement_line_id'], (int) $data['journal_entry_id']);

        return redirect()->route('bank-reconciliation.reconcile', (int) $line->statement->id)
            ->with('success', 'Line matched successfully.');
    }

    public function autoMatch(int $id): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $statement = BankStatement::query()->with('bankAccount')->whereKey($id)->firstOrFail();
        if (!in_array((int) $statement->bankAccount->subshop_id, $shopSubshopIds->toArray())) {
            abort(403);
        }

        if ((string) $statement->status === 'reconciled') {
            return redirect()->back()->with('error', 'Statement is already reconciled.');
        }

        $matched = $this->matcher->autoMatch($statement, 3);

        return redirect()->route('bank-reconciliation.reconcile', $statement->id)
            ->with('success', "Auto-matched {$matched} lines.");
    }

    public function resetMatches(int $id): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $statement = BankStatement::query()->with('bankAccount')->whereKey($id)->firstOrFail();
        if (!in_array((int) $statement->bankAccount->subshop_id, $shopSubshopIds->toArray())) {
            abort(403);
        }

        try {
            $count = $this->reconciliationService->resetMatches($statement);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-reconciliation.reconcile', $statement->id)
            ->with('success', "Reset {$count} matched lines.");
    }

    public function finalize(int $id): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $statement = BankStatement::query()->with('bankAccount')->whereKey($id)->firstOrFail();
        if (!in_array((int) $statement->bankAccount->subshop_id, $shopSubshopIds->toArray())) {
            abort(403);
        }

        try {
            $this->reconciliationService->finalize($statement);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-reconciliation.show', $statement->id)
            ->with('success', 'Statement reconciled successfully.');
    }

    public function createJournal(int $id, int $lineId): View
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $statement = BankStatement::query()
            ->with(['bankAccount'])
            ->whereKey($id)
            ->firstOrFail();

        if (!in_array((int) $statement->bankAccount->subshop_id, $shopSubshopIds->toArray())) {
            abort(403);
        }

        $line = BankStatementLine::query()
            ->with(['statement'])
            ->where('bank_statement_id', (int) $statement->id)
            ->whereKey($lineId)
            ->firstOrFail();

        if ((bool) $line->is_matched && !empty($line->matched_journal_entry_id)) {
            return redirect()->route('bank-reconciliation.reconcile', (int) $statement->id)
                ->with('error', 'This statement line is already matched.');
        }

        // Get shop_id for shop-level account scoping
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $accounts = ChartsOfAccount::query()
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get(['id', 'account_code', 'account_name']);

        $suggestedAccountId = $this->autoJournalService->suggestAccountId($line, $shopId);

        return view('bank_reconciliation.create_journal', compact(
            'statement',
            'line',
            'accounts',
            'suggestedAccountId'
        ));
    }

    public function storeJournal(Request $request, int $id, int $lineId): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $statement = BankStatement::query()->with('bankAccount')->whereKey($id)->firstOrFail();
        if (!in_array((int) $statement->bankAccount->subshop_id, $shopSubshopIds->toArray())) {
            abort(403);
        }

        $line = BankStatementLine::query()
            ->where('bank_statement_id', (int) $statement->id)
            ->whereKey($lineId)
            ->firstOrFail();

        if ((bool) $line->is_matched && !empty($line->matched_journal_entry_id)) {
            return redirect()->route('bank-reconciliation.reconcile', (int) $statement->id)
                ->with('error', 'This statement line is already matched.');
        }

        $data = $request->validate([
            'account_id' => 'required|integer|exists:charts_of_accounts,id',
        ]);

        try {
            $journal = $this->autoJournalService->createForStatementLine((int) $line->id, (int) $data['account_id']);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-reconciliation.reconcile', (int) $statement->id)
            ->with('success', 'Journal entry created and linked successfully. Ref: #' . (int) $journal->id);
    }
}
