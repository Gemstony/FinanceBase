<?php

namespace App\Http\Controllers\Loans\Credits;

use App\Http\Controllers\Controller;
use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\CustomerCreditBalances;
use App\Models\CustomerCreditLiabilityAccount;
use App\Models\Customers;
use App\Models\Loans;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodAccount;
use App\Models\SubShop;
use App\Services\Loans\Credits\CustomerCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

        // Verify customer belongs to the current shop
        if (!in_array((int) $customer->subshop_id, $shopSubshopIds->toArray())) {
            abort(403, 'This customer does not belong to this shop');
        }

        $query = CustomerCreditBalances::query()
            ->with(['loan', 'payment', 'appliedToLoan', 'refundedBy'])
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('customer_id', (int) $customer->id)
            ->orderByDesc('id');

        $credits = $query->paginate(20)->withQueryString();

        $activeLoans = Loans::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('customer_id', (int) $customer->id)
            ->whereIn('status', ['disbursed', 'partially_paid'])
            ->where('outstanding_balance', '>', 0)
            ->get();

        $availableCredits = CustomerCreditBalances::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('customer_id', (int) $customer->id)
            ->where('status', 'available')
            ->orderByDesc('id')
            ->get();

        $availableTotal = (float) CustomerCreditBalances::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('customer_id', (int) $customer->id)
            ->where('status', 'available')
            ->sum('amount');

        $appliedTotal = (float) CustomerCreditBalances::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('customer_id', (int) $customer->id)
            ->where('status', 'applied')
            ->sum('amount');

        $refundedTotal = (float) CustomerCreditBalances::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('customer_id', (int) $customer->id)
            ->where('status', 'refunded')
            ->sum('amount');

        $bankAccounts = BankAccounts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get();

        // Use shop-level scoping for liability accounts (all subshops under same shop)
        $liabilityAccounts = ChartsOfAccount::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get();

        return view('credits.show', compact('customer', 'credits', 'availableCredits', 'activeLoans', 'availableTotal', 'appliedTotal', 'refundedTotal', 'bankAccounts', 'liabilityAccounts'));
    }

    public function apply(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'credit_id' => ['required', 'integer', 'exists:customer_credit_balances,id'],
                'loan_id' => ['required', 'integer', 'exists:loans,id'],
            ]);

            DB::transaction(function () use ($validated) {
                $this->creditService->applyCreditToLoan((int) $validated['credit_id'], (int) $validated['loan_id']);
            });

            return redirect()->back()->with('success', 'Credit applied successfully.');
        } catch (\Exception $e) {
            // Log the detailed error for debugging
            Log::error('Credit application failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            // Return user-friendly error message
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to apply credit: ' . $this->getFriendlyErrorMessage($e));
        }
    }

    public function refund(Request $request): RedirectResponse
    {
        try {
            Log::info('CustomerCreditsController::refund started', [
                'user_id' => auth()->id(),
                'request_data' => $request->except(['_token']),
            ]);

            // Get valid refund method codes from configured payment methods
            $validRefundMethods = PaymentMethod::where('status', true)
                ->where('is_refund_method', true)
                ->pluck('code')
                ->toArray();

            $validated = $request->validate([
                'credit_id' => ['required', 'integer', 'exists:customer_credit_balances,id'],
                'refund_amount' => ['required', 'numeric', 'min:0.01'],
                'refund_method' => ['required', 'string', Rule::in($validRefundMethods)],
                'bank_account_id' => [
                    'nullable',
                    'integer',
                    'exists:bank_accounts,id',
                    Rule::requiredIf(function () use ($request) {
                        // Check if selected payment method requires bank account
                        $paymentMethod = PaymentMethod::where('code', $request->refund_method)->first();
                        return $paymentMethod && $paymentMethod->requires_bank_account;
                    })
                ],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);

            Log::debug('Request validation passed', ['validated_data' => $validated]);

            DB::transaction(function () use ($validated) {
                Log::debug('Starting refund transaction', [
                    'credit_id' => $validated['credit_id'],
                    'refund_amount' => $validated['refund_amount'],
                    'refund_method' => $validated['refund_method'],
                    'bank_account_id' => $validated['bank_account_id'],
                ]);

                $this->creditService->refundCredit(
                    (int) $validated['credit_id'],
                    (int) auth()->id(),
                    (float) $validated['refund_amount'],
                    [
                        'refund_method' => (string) $validated['refund_method'],
                        'bank_account_id' => $validated['bank_account_id'] ? (int) $validated['bank_account_id'] : null,
                        'notes' => $validated['notes'] ? (string) $validated['notes'] : null,
                    ]
                );

                Log::info('Refund transaction completed successfully');
            });

            return redirect()->back()->with('success', 'Credit refunded successfully.');

        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error('Credit refund failed in controller', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Return user-friendly error message
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to refund credit: ' . $this->getFriendlyErrorMessage($e));
        }
    }

    /**
     * Configure customer credit liability account for the current shop (shared across all subshops)
     */
    public function configureLiabilityAccount(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'chart_of_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);

            $subshopId = (int) session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopId = $subshop->shop_id;

            // Get all subshop IDs under this shop for validation
            $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

            // Validate that the selected account is a liability account
            $chartAccount = ChartsOfAccount::query()->whereKey($validated['chart_of_account_id'])->firstOrFail();

            if ((int) $chartAccount->accountClass->code !== 2) {
                return redirect()->back()
                    ->with('error', 'Selected account must be a liability account (Account Class 2).');
            }

            // Validate account belongs to any subshop under the same shop (shop-level scope)
            if (!$shopSubshopIds->contains($chartAccount->subshop_id)) {
                return redirect()->back()
                    ->with('error', 'Selected account does not belong to this shop.');
            }

            if (!$chartAccount->is_active) {
                return redirect()->back()
                    ->with('error', 'Selected account is not active.');
            }

            // Create or update liability account configuration at shop level
            CustomerCreditLiabilityAccount::updateOrCreate(
                ['shop_id' => $shopId],
                [
                    'chart_of_account_id' => (int) $validated['chart_of_account_id'],
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            return redirect()->back()
                ->with('success', 'Customer credit liability account configured successfully for all branches.');

        } catch (\Exception $e) {
            Log::error('Liability account configuration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to configure liability account: ' . $this->getFriendlyErrorMessage($e));
        }
    }

    /**
     * Convert technical exception messages to user-friendly messages
     */
    private function getFriendlyErrorMessage(\Exception $e): string
    {
        $message = $e->getMessage();
        
        // Common technical errors and their user-friendly versions
        $errorMap = [
            'Refund amount must be greater than 0.' => 'Refund amount must be greater than zero.',
            'Refund method is required.' => 'Please select a refund method.',
            'Bank account is required for this refund method.' => 'Please select a bank account for this refund method.',
            'Customer credit liability account is required.' => 'System error: Liability account configuration missing.',
            'Selected account must be a liability account (Account Class 2).' => 'Selected liability account is not valid. Please contact administrator.',
            'Selected account must be an asset account (Account Class 1).' => 'Selected bank account is not properly configured. Please contact administrator.',
            'Selected liability account is not active.' => 'Selected liability account is inactive. Please contact administrator.',
            'Selected liability account does not belong to this branch.' => 'Account access denied. Please contact administrator.',
            'Bank account does not belong to this branch.' => 'Bank account access denied. Please select a valid bank account.',
            'Bank account is not linked to a chart of account.' => 'Bank account configuration error. Please contact administrator.',
            'Payment method not mapped to GL account' => 'Payment method is not configured. Please contact administrator.',
            'Invalid chart_of_account_id for payment method' => 'Payment method configuration error. Please contact administrator.',
            'Invalid subshop for this credit.' => 'Credit access denied. Please contact administrator.',
            'Only available credits can be refunded.' => 'This credit cannot be refunded. It may have been already used or refunded.',
            'Refund amount must not exceed available credit amount.' => 'Refund amount exceeds available credit balance.',
            'Credit not found' => 'Selected credit not found. Please try again.',
            'Loan not found' => 'Selected loan not found. Please try again.',
        ];

        // Check for exact matches first
        foreach ($errorMap as $technical => $friendly) {
            if (str_contains($message, $technical)) {
                return $friendly;
            }
        }

        // Check for partial matches
        if (str_contains($message, 'access denied') || str_contains($message, 'unauthorized')) {
            return 'Access denied. Please contact administrator.';
        }
        
        if (str_contains($message, 'validation') || str_contains($message, 'required')) {
            return 'Please check all required fields and try again.';
        }
        
        if (str_contains($message, 'database') || str_contains($message, 'connection')) {
            return 'System error occurred. Please try again later.';
        }

        // Default friendly message for unknown errors
        return 'An error occurred while processing your request. Please try again or contact support if the problem persists.';
    }
}
