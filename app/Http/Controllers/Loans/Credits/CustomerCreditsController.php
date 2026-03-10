<?php

namespace App\Http\Controllers\Loans\Credits;

use App\Http\Controllers\Controller;
use App\Models\CustomerCreditBalances;
use App\Models\Customers;
use App\Models\Loans;
use App\Services\Loans\Credits\CustomerCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerCreditsController extends Controller
{
    public function __construct(private readonly CustomerCreditService $creditService)
    {
    }

    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $query = CustomerCreditBalances::query()
            ->with(['customer', 'loan', 'payment', 'appliedToLoan', 'refundedBy'])
            ->where('subshop_id', $subshopId)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('borrower')) {
            $borrower = (string) $request->string('borrower');
            $query->whereHas('customer', function ($q) use ($borrower) {
                $q->where('name', 'like', '%' . $borrower . '%');
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->string('date_to'));
        }

        $credits = $query->paginate(20)->withQueryString();

        return view('credits.index', compact('credits'));
    }

    public function show(Customers $customer, Request $request): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $customer->subshop_id !== $subshopId) {
            abort(403);
        }

        $query = CustomerCreditBalances::query()
            ->with(['loan', 'payment', 'appliedToLoan', 'refundedBy'])
            ->where('subshop_id', $subshopId)
            ->where('customer_id', (int) $customer->id)
            ->orderByDesc('id');

        $credits = $query->paginate(20)->withQueryString();

        $activeLoans = Loans::query()
            ->where('subshop_id', $subshopId)
            ->where('customer_id', (int) $customer->id)
            ->whereIn('status', ['disbursed', 'partially_paid'])
            ->where('outstanding_balance', '>', 0)
            ->get();

        $availableCredits = CustomerCreditBalances::query()
            ->where('subshop_id', $subshopId)
            ->where('customer_id', (int) $customer->id)
            ->where('status', 'available')
            ->orderByDesc('id')
            ->get();

        $availableTotal = (float) CustomerCreditBalances::query()
            ->where('subshop_id', $subshopId)
            ->where('customer_id', (int) $customer->id)
            ->where('status', 'available')
            ->sum('amount');

        $appliedTotal = (float) CustomerCreditBalances::query()
            ->where('subshop_id', $subshopId)
            ->where('customer_id', (int) $customer->id)
            ->where('status', 'applied')
            ->sum('amount');

        $refundedTotal = (float) CustomerCreditBalances::query()
            ->where('subshop_id', $subshopId)
            ->where('customer_id', (int) $customer->id)
            ->where('status', 'refunded')
            ->sum('amount');

        return view('credits.show', compact('customer', 'credits', 'availableCredits', 'activeLoans', 'availableTotal', 'appliedTotal', 'refundedTotal'));
    }

    public function apply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'credit_id' => ['required', 'integer', 'exists:customer_credit_balances,id'],
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
        ]);

        DB::transaction(function () use ($validated) {
            $this->creditService->applyCreditToLoan((int) $validated['credit_id'], (int) $validated['loan_id']);
        });

        return redirect()->back()->with('success', 'Credit applied successfully.');
    }

    public function refund(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'credit_id' => ['required', 'integer', 'exists:customer_credit_balances,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated) {
            $this->creditService->refundCredit((int) $validated['credit_id'], (int) auth()->id(), $validated['notes'] ? (string) $validated['notes'] : null);
        });

        return redirect()->back()->with('success', 'Credit refunded successfully.');
    }
}
