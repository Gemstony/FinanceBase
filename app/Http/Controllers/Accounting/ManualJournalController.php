<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartsOfAccount;
use App\Models\JournalEntries;
use App\Models\User;
use App\Services\Accounting\ManualJournalService;
use App\Models\SubShop;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class ManualJournalController extends Controller
{
    public function __construct(private readonly ManualJournalService $service)
    {
    }

    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $baseQuery = JournalEntries::query()
            ->with(['creator', 'lines.account'])
            ->where('subshop_id', $subshopId)
            ->where('reference_type', 'manual_draft');

        if ($request->filled('reference')) {
            $reference = trim((string) $request->string('reference'));
            $reference = ltrim($reference, '#');
            if (ctype_digit($reference)) {
                $baseQuery->whereKey((int) $reference);
            }
        }

        if ($request->filled('description')) {
            $baseQuery->where('description', 'like', '%' . trim((string) $request->string('description')) . '%');
        }

        if ($request->filled('created_by')) {
            $baseQuery->where('created_by', (int) $request->input('created_by'));
        }

        if ($request->filled('date_from')) {
            $baseQuery->whereDate('transaction_date', '>=', (string) $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $baseQuery->whereDate('transaction_date', '<=', (string) $request->input('date_to'));
        }

        $summaryQuery = (clone $baseQuery)->without(['creator', 'lines.account']);
        $totalCount = (clone $summaryQuery)->count();
        $draftCount = (clone $summaryQuery)->where('reference_id', 0)->count();
        $postedCount = (clone $summaryQuery)->where('reference_id', '>', 0)->count();
        $reversedCount = JournalEntries::query()
            ->where('subshop_id', $subshopId)
            ->where('reference_type', 'manual_reversal')
            ->whereIn('reference_id', (clone $summaryQuery)->select('id'))
            ->distinct('reference_id')
            ->count('reference_id');

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status === 'draft') {
                $baseQuery->where('reference_id', 0);
            } elseif ($status === 'posted') {
                $baseQuery->where('reference_id', '>', 0);
            } elseif ($status === 'reversed') {
                $baseQuery->whereIn('id', function ($q) use ($subshopId) {
                    $q->from('journal_entries')
                        ->select('reference_id')
                        ->where('subshop_id', $subshopId)
                        ->where('reference_type', 'manual_reversal');
                });
            }
        }

        $journals = $baseQuery
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $draftIds = $journals->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $reversedDraftIds = [];
        if (!empty($draftIds)) {
            $reversedDraftIds = JournalEntries::query()
                ->where('subshop_id', $subshopId)
                ->where('reference_type', 'manual_reversal')
                ->whereIn('reference_id', $draftIds)
                ->pluck('reference_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $userIds = JournalEntries::query()
            ->where('subshop_id', $subshopId)
            ->where('reference_type', 'manual_draft')
            ->distinct()
            ->pluck('created_by')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $users = empty($userIds)
            ? collect()
            : User::query()
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get(['id', 'name']);

        return view('accounting.manual_journals.index', compact(
            'journals',
            'reversedDraftIds',
            'totalCount',
            'draftCount',
            'postedCount',
            'reversedCount',
            'users',
        ));
    }

    public function create(): View
    {
        $subshopId = (int) session('subshop_id');

        
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        // Get all subshop IDs under this shop for validation
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $accounts = ChartsOfAccount::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get(['id', 'account_code', 'account_name']);

        return view('accounting.manual_journals.create', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $draft = $this->service->createDraftJournal(
                Carbon::parse((string) $validated['transaction_date']),
                $validated['description'] ?? null
            );

            $this->service->addJournalLines($draft, $validated['lines']);
            $this->service->validateJournal($draft);

            $this->service->postJournal($draft);

            return redirect()->route('accounting.manual-journals.show', (int) $draft->id)
                ->with('success', 'Manual journal posted successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to create manual journal.';
            return back()->withInput()->with('error', $msg);
        }
    }

    public function show(int $id, Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $journal = JournalEntries::query()
            ->with(['creator', 'lines.account'])
            ->where('subshop_id', $subshopId)
            ->whereKey($id)
            ->firstOrFail();

        $isReversed = JournalEntries::query()
            ->where('subshop_id', $subshopId)
            ->where('reference_type', 'manual_reversal')
            ->where('reference_id', (int) $journal->id)
            ->exists();

        return view('accounting.manual_journals.show', compact('journal', 'isReversed'));
    }

    public function post(int $id, Request $request): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

        $journal = JournalEntries::query()
            ->with('lines')
            ->where('subshop_id', $subshopId)
            ->whereKey($id)
            ->firstOrFail();

        try {
            $this->service->postJournal($journal);

            return redirect()->route('accounting.manual-journals.show', (int) $journal->id)
                ->with('success', 'Manual journal posted successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to post manual journal.';
            return back()->with('error', $msg);
        }
    }

    public function reverse(int $id, Request $request): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

        $journal = JournalEntries::query()
            ->with('lines')
            ->where('subshop_id', $subshopId)
            ->whereKey($id)
            ->firstOrFail();

        try {
            $reversal = $this->service->reverseJournal($journal);

            return redirect()->route('accounting.manual-journals.show', (int) $journal->id)
                ->with('success', 'Reversal journal posted successfully (Journal #' . (int) $reversal->id . ').');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to reverse manual journal.';
            return back()->with('error', $msg);
        }
    }
}
