<?php

namespace App\Http\Controllers;

use App\Models\LoanRestructures;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Loans\Restructure\LoanRestructureEngine;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class LoanRestructureController extends Controller
{
    public function __construct(private readonly LoanRestructureEngine $engine)
    {
    }

    /**
     * Approval queue for pending restructure requests.
     */
    public function index(): View
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        $requests = LoanRestructures::query()
            ->where('is_active', true)
            ->where('status', 'pending')
            ->whereHas('loan', fn ($q) => $q->where('subshop_id', $subshopId))
            ->with(['loan.loanProduct', 'loan.customer', 'loan.loanGroup'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('loans.restructures.index', compact('subshop', 'requests'));
    }

    /**
     * List all restructured loans for management.
     */
    public function managed(): View
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        $restructures = LoanRestructures::query()
            ->whereHas('loan', fn ($q) => $q->where('subshop_id', $subshopId))
            ->with(['loan.loanProduct', 'loan.customer', 'loan.loanGroup'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('loans.restructures.restructured_loans', compact('subshop', 'restructures'));
    }

    /**
     * Show form to request a restructure for a given loan.
     */
    public function create(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        if ((int) $loan->subshop_id !== $subshopId) {
            abort(404);
        }

        return view('loans.restructures.create', compact('subshop', 'loan'));
    }

    /**
     * Store a restructure request (pending approval).
     */
    public function store(Request $request, Loans $loan): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(404);
        }

        $validated = $request->validate([
            'restructure_date' => ['nullable', 'date'],
            'new_interest_rate' => ['required', 'numeric', 'min:0'],
            'new_term' => ['required', 'integer', 'min:1'],
            'grace_period' => ['nullable', 'integer', 'min:0'],
            'capitalize_interest' => ['nullable', 'boolean'],
            'capitalized_interest' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $capitalize = $request->boolean('capitalize_interest');

        $restructure = LoanRestructures::create([
            'loan_id' => (int) $loan->id,
            'restructure_date' => $validated['restructure_date'] ?? Carbon::today()->toDateString(),
            'restructure_type' => 'reschedule',
            'old_term_months' => (int) $loan->installments,
            'old_interest_rate' => (float) $loan->interest_rate,
            'new_term' => (int) $validated['new_term'],
            'new_interest_rate' => (float) $validated['new_interest_rate'],
            'grace_period' => (int) ($validated['grace_period'] ?? 0),
            'capitalized_interest' => $capitalize ? (float) ($validated['capitalized_interest'] ?? 0.0) : 0.0,
            'reason' => (string) $validated['reason'],
            'status' => 'pending',
            'requested_by' => (int) $user->id,
            'approved_by' => null,
            'approved_at' => null,
            'executed_at' => null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('loan.restructures.history', $loan)
            ->with('success', 'Restructure request submitted successfully.');
    }

    public function approve(LoanRestructures $restructure): RedirectResponse
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        return DB::transaction(function () use ($restructure, $user) {
            $locked = LoanRestructures::query()->whereKey((int) $restructure->id)->lockForUpdate()->firstOrFail();

            if ((string) $locked->status !== 'pending') {
                return back()->with('error', 'This restructure request is not pending.');
            }

            $locked->status = 'approved';
            $locked->approved_by = (int) $user->id;
            $locked->approved_at = Carbon::now();
            $locked->save();

            return back()->with('success', 'Restructure request approved successfully.');
        });
    }

    public function reject(Request $request, LoanRestructures $restructure): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Please provide a reason for rejection.',
        ]);

        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        return DB::transaction(function () use ($restructure, $user, $validated) {
            $locked = LoanRestructures::query()->whereKey((int) $restructure->id)->lockForUpdate()->firstOrFail();

            if ((string) $locked->status !== 'pending') {
                return back()->with('error', 'This restructure request is not pending.');
            }

            $locked->status = 'rejected';
            $locked->approved_by = (int) $user->id;
            $locked->approved_at = Carbon::now();
            $locked->reason = (string) $validated['reason'];
            $locked->is_active = false;
            $locked->save();

            return back()->with('success', 'Restructure request rejected successfully.');
        });
    }

    public function execute(LoanRestructures $restructure): RedirectResponse
    {
        try {
            $this->engine->execute($restructure);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to execute restructure.');
        }

        $loan = Loans::query()->find($restructure->loan_id);

        if ($loan) {
            return redirect()->route('loans.loans.show', $loan)->with('success', 'Restructure executed successfully.');
        }

        return redirect()->route('loan.restructures.index')->with('success', 'Restructure executed successfully.');
    }

    public function history(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        if ((int) $loan->subshop_id !== $subshopId) {
            abort(404);
        }

        $requests = LoanRestructures::query()
            ->where('loan_id', (int) $loan->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('loans.restructures.history', compact('subshop', 'loan', 'requests'));
    }
}
