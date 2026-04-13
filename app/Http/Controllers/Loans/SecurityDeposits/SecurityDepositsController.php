<?php

namespace App\Http\Controllers\Loans\SecurityDeposits;

use App\Http\Controllers\Controller;
use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\Customers;
use App\Models\LoanSecurityDeposit;
use App\Models\Loans;
use App\Models\SecurityDepositForfeitureAccount;
use App\Models\SecurityDepositLiabilityAccount;
use App\Services\Loans\SecurityDeposits\SecurityDepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $bankAccounts = BankAccounts::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get();

        return view('deposits.collect', compact('loan', 'bankAccounts'));
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

        $bankAccounts = BankAccounts::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get();

        $liabilityConfig = SecurityDepositLiabilityAccount::forSubshop($subshopId);
        $liabilityConfigured = $liabilityConfig !== null;

        $forfeitureConfig = SecurityDepositForfeitureAccount::forSubshop($subshopId);
        $forfeitureConfigured = $forfeitureConfig !== null;

        return view('deposits.loan', compact('loan', 'deposits', 'heldDeposits', 'heldTotal', 'appliedTotal', 'refundedTotal', 'forfeitedTotal', 'activeLoans', 'bankAccounts', 'liabilityConfig', 'liabilityConfigured', 'forfeitureConfig', 'forfeitureConfigured'));
    }

    public function collect(Request $request, Loans $loan): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array((string) $validated['payment_method'], ['bank_transfer', 'mobile_money'], true) && empty($validated['payment_bank_account_id'])) {
            return redirect()->back()->with('error', 'Please select a bank account for this payment method.');
        }

        DB::transaction(function () use ($validated, $loan) {
            $this->service->collectDeposit(
                (int) $loan->customer_id,
                (int) $loan->id,
                (float) $validated['amount'],
                (string) $validated['payment_method'],
                $validated['payment_bank_account_id'] ? (int) $validated['payment_bank_account_id'] : null,
                $validated['notes'] ? (string) $validated['notes'] : null
            );
        });

        return redirect()->back()->with('success', 'Security deposit collected successfully.');
    }

    public function refund(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_id' => ['required', 'integer', 'exists:loan_security_deposits,id'],
            'refund_amount' => ['required', 'numeric', 'min:0.01'],
            'refund_method' => ['required', 'string', 'max:50'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array((string) $validated['refund_method'], ['bank_transfer', 'mobile_money'], true) && empty($validated['bank_account_id'])) {
            return redirect()->back()->with('error', 'Please select a bank account for this refund method.');
        }

        try {
            DB::transaction(function () use ($validated) {
                $this->service->refundDeposit(
                    (int) $validated['deposit_id'],
                    (int) auth()->id(),
                    (float) $validated['refund_amount'],
                    [
                        'refund_method' => (string) $validated['refund_method'],
                        'bank_account_id' => $validated['bank_account_id'] ? (int) $validated['bank_account_id'] : null,
                        'notes' => $validated['notes'] ?? null,
                    ]
                );
            });

            return redirect()->back()->with('success', 'Security deposit refunded successfully.');
        } catch (\Exception $e) {
            Log::error('Security deposit refund failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()->withInput()->with('error', 'Failed to refund security deposit: ' . $e->getMessage());
        }
    }

    public function apply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_id' => ['required', 'integer', 'exists:loan_security_deposits,id'],
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $this->service->applyDepositToLoan(
                    (int) $validated['deposit_id'],
                    (int) $validated['loan_id'],
                    (float) $validated['amount'],
                    $validated['notes'] ?? null
                );
            });

            return redirect()->back()->with('success', 'Security deposit applied successfully.');
        } catch (\Exception $e) {
            Log::error('Security deposit apply failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()->withInput()->with('error', 'Failed to apply security deposit: ' . $e->getMessage());
        }
    }

    public function forfeit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_id' => ['required', 'integer', 'exists:loan_security_deposits,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $this->service->forfeitDeposit(
                    (int) $validated['deposit_id'],
                    (float) $validated['amount'],
                    $validated['notes'] ?? null
                );
            });

            return redirect()->back()->with('success', 'Security deposit forfeited successfully.');
        } catch (\Exception $e) {
            Log::error('Security deposit forfeit failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()->withInput()->with('error', 'Failed to forfeit security deposit: ' . $e->getMessage());
        }
    }

    public function configureLiabilityAccount(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'chart_of_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);

            $subshopId = (int) session('subshop_id');

            $chartAccount = ChartsOfAccount::query()->whereKey($validated['chart_of_account_id'])->firstOrFail();

            if ((int) $chartAccount->accountClass->code !== 2) {
                return redirect()->back()
                    ->with('error', 'Selected account must be a liability account (Account Class 2).');
            }

            if ((int) $chartAccount->subshop_id !== $subshopId) {
                return redirect()->back()
                    ->with('error', 'Selected account does not belong to this branch.');
            }

            if (!$chartAccount->is_active) {
                return redirect()->back()
                    ->with('error', 'Selected account is not active.');
            }

            SecurityDepositLiabilityAccount::updateOrCreate(
                ['subshop_id' => $subshopId],
                [
                    'chart_of_account_id' => (int) $validated['chart_of_account_id'],
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            return redirect()->back()
                ->with('success', 'Security deposit liability account configured successfully.');

        } catch (\Exception $e) {
            Log::error('Security deposit liability account configuration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to configure liability account: ' . $e->getMessage());
        }
    }

    public function configureForfeitureAccount(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'chart_of_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);

            $subshopId = (int) session('subshop_id');

            $chartAccount = ChartsOfAccount::query()->whereKey($validated['chart_of_account_id'])->firstOrFail();

            // Income accounts are typically Class 4 (Revenue) or Class 5 (Other Income)
            $accountClass = (int) ($chartAccount->accountClass?->code ?? 0);
            if (!in_array($accountClass, [4, 5], true)) {
                return redirect()->back()
                    ->with('error', 'Selected account must be a revenue/income account (Account Class 4 or 5).');
            }

            if ((int) $chartAccount->subshop_id !== $subshopId) {
                return redirect()->back()
                    ->with('error', 'Selected account does not belong to this branch.');
            }

            if (!$chartAccount->is_active) {
                return redirect()->back()
                    ->with('error', 'Selected account is not active.');
            }

            SecurityDepositForfeitureAccount::updateOrCreate(
                ['subshop_id' => $subshopId],
                [
                    'chart_of_account_id' => (int) $validated['chart_of_account_id'],
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            return redirect()->back()
                ->with('success', 'Security deposit forfeiture income account configured successfully.');

        } catch (\Exception $e) {
            Log::error('Security deposit forfeiture account configuration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to configure forfeiture account: ' . $e->getMessage());
        }
    }
}
