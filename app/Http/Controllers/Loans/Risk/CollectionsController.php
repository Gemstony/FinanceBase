<?php

namespace App\Http\Controllers\Loans\Risk;

use App\Http\Controllers\Controller;
use App\Models\CollectionsAction;
use App\Models\PromiseToPay;
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

    private function getCurrentSubshopId(): ?int
    {
        $subshopId = (int) session('subshop_id');

        return $subshopId > 0 ? $subshopId : null;
    }

    /**
     * Display the collections worklist.
     */
    public function index(Request $request): View
    {
        $subshopId = $this->getCurrentSubshopId();

        // Use enriched method that pre-computes all metrics in bulk (avoids N+1)
        $loans = $this->delinquencyEngine->getDelinquentLoansEnriched(1, $subshopId);

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

        // Apply filters from request
        $loans = $loans->filter(function ($loan) use ($request) {
            // Risk category filter
            if ($request->filled('risk_category')) {
                $riskCategory = $request->input('risk_category');
                $dpd = $loan->days_past_due ?? 0;
                
                switch ($riskCategory) {
                    case 'par30':
                        if ($dpd > 30) return false;
                        break;
                    case 'par60':
                        if ($dpd > 60) return false;
                        break;
                    case 'par90':
                        if ($dpd > 90) return false;
                        break;
                    case 'default':
                        if ($dpd <= 90) return false;
                        break;
                }
            }

            // Borrower type filter
            if ($request->filled('borrower_type')) {
                if ($loan->borrower_type !== $request->input('borrower_type')) {
                    return false;
                }
            }

            // DPD range filter
            if ($request->filled('min_dpd')) {
                if (($loan->days_past_due ?? 0) < (int) $request->input('min_dpd')) {
                    return false;
                }
            }
            if ($request->filled('max_dpd')) {
                if (($loan->days_past_due ?? 0) > (int) $request->input('max_dpd')) {
                    return false;
                }
            }

            // Officer filter
            if ($request->filled('officer')) {
                if (($loan->loan_officer_id ?? null) != (int) $request->input('officer')) {
                    return false;
                }
            }

            return true;
        });

        // Sort by collection score (higher = more urgent)
        $loans = $loans->sortByDesc('collection_score')->values();

        // Get officers for filter dropdown
        $officers = collect();
        if ($subshopId) {
            $subshop = \App\Models\SubShop::find($subshopId);
            if ($subshop) {
                // Get all users assigned to this shop's subshops
                $shopSubshopIds = \App\Models\SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
                $officers =User::query()
            ->where(function ($q) use ($subshop, $shopSubshopIds) {
                $q->whereHas('shop', function ($sq) use ($subshop) {
                    $sq->where('id', $subshop->shop_id);
                })->orWhereHas('subshops', function ($sq) use ($subshop, $shopSubshopIds) {
                    $sq->where('sub_shops.shop_id', $subshop->shop_id)
                        ->whereIn('sub_shops.id', $shopSubshopIds)
                        ->where('subshop_user.is_active', true);
                });
            })
            ->orderBy('name')
            ->distinct()
            ->get(['id', 'name']);
            }
        }

        return view('risk.collections', compact('loans', 'officers'));
    }

    /**
     * Display collection actions tracker.
     */
    public function actions(Request $request, CollectionsActionService $actionService): View
    {
        $subshopId = $this->getCurrentSubshopId();

        // Build query with filters
        $query = CollectionsAction::with(['loan', 'customer', 'assignedTo'])
            ->orderBy('created_at', 'desc');

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
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
        $stats = $actionService->getDashboardStats($subshopId);

        // Get overdue actions for alerts
        $overdueActions = $actionService->getOverdueActions($subshopId);

        // Get staff for filter dropdown - only users from current shop
        $staff = collect();
        if ($subshopId) {
            $subshop = \App\Models\SubShop::find($subshopId);
            if ($subshop) {
                // Get all users assigned to this shop's subshops
                $shopSubshopIds = \App\Models\SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
                $staff = User::query()
            ->where(function ($q) use ($subshop, $shopSubshopIds) {
                $q->whereHas('shop', function ($sq) use ($subshop) {
                    $sq->where('id', $subshop->shop_id);
                })->orWhereHas('subshops', function ($sq) use ($subshop, $shopSubshopIds) {
                    $sq->where('sub_shops.shop_id', $subshop->shop_id)
                        ->whereIn('sub_shops.id', $shopSubshopIds)
                        ->where('subshop_user.is_active', true);
                });
            })
            ->orderBy('name')
            ->distinct()
            ->get(['id', 'name']);
            }
        }

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
        $subshopId = $this->getCurrentSubshopId();

        // Build query
        $query = PromiseToPay::with(['loan', 'customer'])
            ->orderBy('promise_date', 'asc');

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
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
        $stats = $promiseService->getStatistics($subshopId);

        // Get overdue and due today for alerts
        $overduePromises = $promiseService->getOverduePromises($subshopId);
        $dueToday = $promiseService->getPromisesDueToday($subshopId);

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
        $subshopId = $this->getCurrentSubshopId();
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        // Get actions scheduled for this date
        $todaysActions = CollectionsAction::with(['loan', 'customer'])
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['pending', 'in_progress'])
            ->when($subshopId, fn($q) => $q->where('subshop_id', $subshopId))
            ->orderBy('scheduled_at')
            ->get();

        // Get promises due on this date
        $duePromises = PromiseToPay::with(['loan', 'customer'])
            ->whereDate('promise_date', $date)
            ->whereIn('status', ['pending'])
            ->when($subshopId, fn($q) => $q->where('subshop_id', $subshopId))
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
                ->when($subshopId, fn($q) => $q->where('subshop_id', $subshopId))
                ->get();

            $weekPromises[$dateStr] = PromiseToPay::whereDate('promise_date', $dayDate)
                ->when($subshopId, fn($q) => $q->where('subshop_id', $subshopId))
                ->get();
        }

        return view('collections.schedule', compact('date', 'todaysActions', 'duePromises', 'weekActions', 'weekPromises'));
    }
}
