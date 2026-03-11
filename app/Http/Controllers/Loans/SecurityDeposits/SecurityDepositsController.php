<?php

namespace App\Http\Controllers\Loans\SecurityDeposits;

use App\Http\Controllers\Controller;
use App\Models\Customers;
use App\Models\LoanSecurityDeposit;
use App\Models\Loans;
use App\Services\Loans\SecurityDeposits\SecurityDepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SecurityDepositsController extends Controller
{
    public function __construct(private readonly SecurityDepositService $service)
    {
    }

    public function collectForm(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403);
        }

        return view('deposits.collect', compact('loan'));
    }

    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $query = LoanSecurityDeposit::query()
            ->with(['customer', 'loan', 'appliedToLoan', 'refundedBy'])
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

        if ($request->filled('loan_code')) {
            $loanCode = (string) $request->string('loan_code');
            $query->whereHas('loan', function ($q) use ($loanCode) {
                $q->where('loan_code', 'like', '%' . $loanCode . '%');
            });
        }

        $deposits = $query->paginate(20)->withQueryString();

        return view('deposits.index', compact('deposits'));
    }

    public function borrower(Customers $customer): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $customer->subshop_id !== $subshopId) {
            abort(403);
        }

        $deposits = $this->service->getBorrowerDeposits((int) $customer->id)
            ->paginate(20)
            ->withQueryString();

        return view('deposits.borrower', compact('customer', 'deposits'));
    }

    public function loan(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403);
        }

        $deposits = $this->service->getLoanDeposits((int) $loan->id)
            ->paginate(20)
            ->withQueryString();

        // Compute totals for the sidebar
        $heldDeposits = $this->service->getLoanDeposits((int) $loan->id)
            ->where('status', 'held')
            ->get();

        $heldTotal = $heldDeposits->sum('amount');
        $appliedTotal = $this->service->getLoanDeposits((int) $loan->id)
            ->where('status', 'applied')
            ->sum('amount');
        $refundedTotal = $this->service->getLoanDeposits((int) $loan->id)
            ->where('status', 'refunded')
            ->sum('amount');
        $forfeitedTotal = $this->service->getLoanDeposits((int) $loan->id)
            ->where('status', 'forfeited')
            ->sum('amount');

        // Active loans for apply dropdown (excluding current loan) — only for the deposit's borrower
        $activeLoans = Loans::query()
            ->where('subshop_id', $subshopId)
            ->where('customer_id', (int) $loan->customer_id)
            ->where('status', 'disbursed')
            ->where('outstanding_balance', '>', 0)
            ->where('id', '!=', (int) $loan->id)
            ->orderBy('loan_code')
            ->get(['id', 'loan_code', 'outstanding_balance']);

        return view('deposits.loan', compact('loan', 'deposits', 'heldDeposits', 'heldTotal', 'appliedTotal', 'refundedTotal', 'forfeitedTotal', 'activeLoans'));
    }

    public function collect(Request $request, Loans $loan): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated, $loan) {
            $this->service->collectDeposit(
                (int) $loan->customer_id,
                (int) $loan->id,
                (float) $validated['amount'],
                (string) $validated['payment_method'],
                $validated['notes'] ? (string) $validated['notes'] : null
            );
        });

        return redirect()->back()->with('success', 'Security deposit collected successfully.');
    }

    public function refund(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_id' => ['required', 'integer', 'exists:loan_security_deposits,id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated) {
            $this->service->refundDeposit(
                (int) $validated['deposit_id'],
                (int) auth()->id(),
                (string) $validated['payment_method'],
                $validated['notes'] ?? null
            );
        });

        return redirect()->back()->with('success', 'Security deposit refunded successfully.');
    }

    public function apply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_id' => ['required', 'integer', 'exists:loan_security_deposits,id'],
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated) {
            $this->service->applyDepositToLoan(
                (int) $validated['deposit_id'],
                (int) $validated['loan_id'],
                $validated['notes'] ?? null
            );
        });

        return redirect()->back()->with('success', 'Security deposit applied successfully.');
    }

    public function forfeit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_id' => ['required', 'integer', 'exists:loan_security_deposits,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated) {
            $this->service->forfeitDeposit(
                (int) $validated['deposit_id'],
                $validated['notes'] ?? null
            );
        });

        return redirect()->back()->with('success', 'Security deposit forfeited successfully.');
    }
}
