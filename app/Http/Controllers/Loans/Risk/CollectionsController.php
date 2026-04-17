<?php

namespace App\Http\Controllers\Loans\Risk;

use App\Http\Controllers\Controller;
use App\Models\CollectionsAction;
use App\Models\PromiseToPay;
use App\Models\SubShop;
use App\Models\User;
use App\Services\Loans\Risk\CollectionsActionService;
use App\Services\Loans\Risk\CollectionsScoringService;
use App\Services\Loans\Risk\DpdCalculator;
use App\Services\Loans\Risk\LoanDelinquencyEngine;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use App\Services\Loans\Risk\PromiseToPayService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CollectionsController extends Controller
{
    public function __construct(
        private readonly LoanDelinquencyEngine $delinquencyEngine,
        private readonly PortfolioRiskCalculator $portfolioRisk,
        private readonly DpdCalculator $dpdCalculator,
        private readonly CollectionsScoringService $scoringService,
    ) {
    }

    /**
     * Get all subshop IDs under the current shop for data aggregation.
     *
     * @return array<int>|null Array of subshop IDs or null for all
     */
    private function getShopSubshopIds(): ?array
    {
        $subshopId = (int) session('subshop_id');
        if (!$subshopId) {
            return null;
        }

        $subshop = SubShop::find($subshopId);
        if (!$subshop) {
            return null;
        }

        // Aggregate across all subshops under the same shop
        return SubShop::where('shop_id', $subshop->shop_id)->pluck('id')->toArray();
    }

    /**
     * Display the collections worklist.
     */
    public function index(Request $request): View
    {
        $shopSubshopIds = $this->getShopSubshopIds();

        // Use enriched method that pre-computes all metrics in bulk (avoids N+1)
        $loans = $this->delinquencyEngine->getDelinquentLoansEnriched(1, $shopSubshopIds);

        // Apply eager loading to avoid N+1 on relationships
        $loans->load(['customer', 'loanGroup', 'latestDisbursement.processor']);

        // Calculate collection scores and additional metrics for prioritization
        $loanIds = $loans->pluck('id')->toArray();
        $collectionScores = $this->scoringService->calculateScores($loans);

        // Attach additional computed data to each loan
        foreach ($loans as $loan) {
            $loan->collection_score = $collectionScores[$loan->id] ?? 0;
            $loan->priority_rank = $collectionScores[$loan->id] ?? 0;
        }

        // Sort by collection score (higher = more urgent)
        $loans = $loans->sortByDesc('collection_score')->values();

        return view('risk.collections', compact('loans'));
    }

    /**
     * Display collection actions tracker.
     */
    public function actions(Request $request, CollectionsActionService $actionService): View
    {
        $shopSubshopIds = $this->getShopSubshopIds();

        // Build query with filters
        $query = CollectionsAction::with(['loan', 'customer', 'assignedTo'])
            ->orderBy('created_at', 'desc');

        if ($shopSubshopIds) {
            $query->whereIn('subshop_id', $shopSubshopIds);
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->input('action_type'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $actions = $query->paginate(50);

        // Get statistics
        $stats = $actionService->getDashboardStats($shopSubshopIds);

        // Get overdue actions for alerts
        $overdueActions = $actionService->getOverdueActions($shopSubshopIds);

        // Get staff for filter dropdown
        $staff = User::all();

        return view('collections.actions', compact('actions', 'stats', 'overdueActions', 'staff'));
    }

    /**
     * Create a new collection action from worklist.
     */
    public function createAction(Request $request): RedirectResponse
    {
        $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'customer_id' => 'required|exists:customers,id',
            'action_type' => 'required|in:phone_call,field_visit,sms,email,letter',
            'outcome' => 'required|string',
            'amount_collected' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Get current subshop for recording the action
        $currentSubshopId = (int) session('subshop_id');

        // Create the action - use the user's current subshop
        $action = CollectionsAction::create([
            'loan_id' => $request->input('loan_id'),
            'customer_id' => $request->input('customer_id'),
            'subshop_id' => $currentSubshopId,
            'action_type' => $request->input('action_type'),
            'status' => 'completed',
            'outcome' => $request->input('outcome'),
            'notes' => $request->input('notes'),
            'amount_collected' => $request->input('amount_collected') ? (float) $request->input('amount_collected') : null,
            'completed_at' => now(),
            'assigned_to' => auth()->id(),
            'created_by' => auth()->id(),
            'scheduled_at' => now(),
        ]);

        return back()->with('success', 'Action recorded successfully.');
    }

    /**
     * Record/complete an existing collection action.r
     */
    public function recordAction(Request $request): RedirectResponse
    {
        $request->validate([
            'action_id' => 'required|exists:collections_actions,id',
            'outcome' => 'required|string',
            'amount_collected' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $action = CollectionsAction::findOrFail($request->input('action_id'));
        $service = app(CollectionsActionService::class);

        $service->completeAction(
            $action,
            $request->input('outcome'),
            $request->input('notes'),
            $request->input('amount_collected') ? (float) $request->input('amount_collected') : null
        );

        return back()->with('success', 'Action completed successfully.');
    }

    /**
     * Display promises to pay.
     */
    public function promises(Request $request, PromiseToPayService $promiseService): View
    {
        $shopSubshopIds = $this->getShopSubshopIds();

        // Build query
        $query = PromiseToPay::with(['loan', 'customer'])
            ->orderBy('promise_date', 'asc');

        if ($shopSubshopIds) {
            $query->whereIn('subshop_id', $shopSubshopIds);
        }

        // Apply filters
        if ($request->filled('status')) {
            if ($request->input('status') === 'overdue') {
                $query->where('status', 'pending')->where('promise_date', '<', Carbon::today());
            } else {
                $query->where('status', $request->input('status'));
            }
        }
        if ($request->filled('promise_type')) {
            $query->where('promise_type', $request->input('promise_type'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('promise_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('promise_date', '<=', $request->input('date_to'));
        }

        $promises = $query->paginate(50);

        // Get statistics
        $stats = $promiseService->getStatistics($shopSubshopIds);

        // Get overdue and due today for alerts
        $overduePromises = $promiseService->getOverduePromises($shopSubshopIds);
        $dueToday = $promiseService->getPromisesDueToday($shopSubshopIds);

        return view('collections.promises', compact('promises', 'stats', 'overduePromises', 'dueToday'));
    }

    /**
     * Create a new promise to pay from worklist.
     */
    public function createPromise(Request $request): RedirectResponse
    {
        $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'customer_id' => 'required|exists:customers,id',
            'amount_promised' => 'required|numeric|min:0',
            'promise_date' => 'required|date',
            'promise_type' => 'required|in:partial_payment,full_payment,installment_resumption',
            'notes' => 'nullable|string',
        ]);

        // Get current subshop for recording the promise
        $currentSubshopId = (int) session('subshop_id');

        // Create the promise - use the user's current subshop
        $promise = PromiseToPay::create([
            'loan_id' => $request->input('loan_id'),
            'customer_id' => $request->input('customer_id'),
            'subshop_id' => $currentSubshopId,
            'amount_promised' => (float) $request->input('amount_promised'),
            'promise_date' => $request->input('promise_date'),
            'promise_type' => $request->input('promise_type'),
            'status' => 'pending',
            'notes' => $request->input('notes'),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Promise to Pay recorded successfully.');
    }

    /**
     * Record promise fulfillment or break.
     */
    public function recordPromise(Request $request): RedirectResponse
    {
        $request->validate([
            'promise_id' => 'required|exists:promises_to_pay,id',
            'action' => 'required|in:fulfill,break',
        ]);

        $promise = PromiseToPay::findOrFail($request->input('promise_id'));
        $service = app(PromiseToPayService::class);

        if ($request->input('action') === 'fulfill') {
            $request->validate([
                'amount_paid' => 'required|numeric|min:0',
            ]);

            $service->recordFulfillment(
                $promise,
                (float) $request->input('amount_paid'),
                $request->input('notes')
            );

            return back()->with('success', 'Promise fulfilled successfully.');
        } else {
            $service->markBroken($promise, $request->input('reason'));
            return back()->with('warning', 'Promise marked as broken.');
        }
    }

    /**
     * Display daily collections schedule.
     */
    public function schedule(Request $request): View
    {
        $shopSubshopIds = $this->getShopSubshopIds();
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        // Get actions scheduled for this date
        $todaysActions = CollectionsAction::with(['loan', 'customer'])
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['pending', 'in_progress'])
            ->when($shopSubshopIds, fn($q) => $q->whereIn('subshop_id', $shopSubshopIds))
            ->orderBy('scheduled_at')
            ->get();

        // Get promises due on this date
        $duePromises = PromiseToPay::with(['loan', 'customer'])
            ->whereDate('promise_date', $date)
            ->whereIn('status', ['pending'])
            ->when($shopSubshopIds, fn($q) => $q->whereIn('subshop_id', $shopSubshopIds))
            ->orderBy('amount_promised', 'desc')
            ->get();

        // Get week overview data
        $weekStart = $date->copy()->startOfWeek();
        $weekActions = [];
        $weekPromises = [];

        for ($i = 0; $i < 7; $i++) {
            $dayDate = $weekStart->copy()->addDays($i);
            $dateStr = $dayDate->toDateString();

            $weekActions[$dateStr] = CollectionsAction::whereDate('scheduled_at', $dayDate)
                ->when($shopSubshopIds, fn($q) => $q->whereIn('subshop_id', $shopSubshopIds))
                ->get();

            $weekPromises[$dateStr] = PromiseToPay::whereDate('promise_date', $dayDate)
                ->when($shopSubshopIds, fn($q) => $q->whereIn('subshop_id', $shopSubshopIds))
                ->get();
        }

        return view('collections.schedule', compact('date', 'todaysActions', 'duePromises', 'weekActions', 'weekPromises'));
    }
}
