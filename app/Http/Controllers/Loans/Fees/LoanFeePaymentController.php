<?php

namespace App\Http\Controllers\Loans\Fees;

use App\Http\Controllers\Controller;
use App\Models\BankAccounts;
use App\Models\LoanFeeApplications;
use App\Models\Loans;
use App\Models\PaymentMethod;
use App\Models\SubShop;
use App\Services\Loans\Fees\LoanFeePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LoanFeePaymentController extends Controller
{
    public function __construct(
        private readonly LoanFeePaymentService $feePaymentService,
    ) {
    }

    /**
     * Show the fee payment form for a loan.
     */
    public function showPaymentForm(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403);
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

        // Get pending fees
        $pendingFees = LoanFeeApplications::with(['loanProductFee.loanFee'])
            ->where('loan_id', $loan->id)
            ->where('is_paid', false)
            ->get();

        $pendingTotal = $pendingFees->sum('amount');

        // Get bank accounts
        $bankAccounts = BankAccounts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get();

        return view('loans.fees.payment-form', compact('loan', 'pendingFees', 'pendingTotal', 'bankAccounts'));
    }

    /**
     * Process payment for a specific fee.
     */
    public function payFee(Request $request, Loans $loan): RedirectResponse
    {
        $validated = $request->validate([
            'fee_application_id' => ['required', 'integer', 'exists:loan_fee_applications,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        

        // Validate payment method exists and is active
        // $paymentMethod = PaymentMethod::where('code', $validated['payment_method'])
        //     ->where('status', 'active')
        //     ->first();
        // if (!$paymentMethod) {
        //     return redirect()->back()->with('error', 'Invalid or inactive payment method selected.');
        // }

        // // Validate bank account requirement based on payment method configuration
        // if ($paymentMethod->requires_bank_account && empty($validated['payment_bank_account_id'])) {
        //     return redirect()->back()->with('error', 'Please select a bank account for this payment method.');
        // }

        // Validate bank account belongs to this shop
        if (!empty($validated['payment_bank_account_id'])) {
            $subshopId = (int) session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            $validBankAccount = BankAccounts::where('id', $validated['payment_bank_account_id'])
                ->whereIn('subshop_id', $shopSubshopIds)
                ->where('is_active', 1)
                ->exists();

            if (!$validBankAccount) {
                return redirect()->back()->with('error', 'Invalid bank account selected.');
            }
        }

        try {
            DB::transaction(function () use ($validated, $loan) {
                $this->feePaymentService->payFee(
                    (int) $validated['fee_application_id'],
                    (float) $validated['amount'],
                    (string) $validated['payment_method'],
                    $validated['payment_bank_account_id'] ? (int) $validated['payment_bank_account_id'] : null,
                    $validated['notes'] ?? null,
                    (int) Auth::id(),
                );
            });

            return redirect()->back()->with('success', 'Fee payment processed successfully.');
        } catch (\Exception $e) {
            Log::error('Fee payment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
                'loan_id' => $loan->id,
            ]);

            return redirect()->back()->withInput()->with('error', 'Failed to process fee payment: ' . $e->getMessage());
        }
    }

    /**
     * Process payment for all pending fees on a loan.
     */
    public function payAllFees(Request $request, Loans $loan): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Validate payment method exists and is active
        // $paymentMethod = PaymentMethod::where('code', $validated['payment_method'])
        //     ->where('status', 'active')
        //     ->first();
        // if (!$paymentMethod) {
        //     return redirect()->back()->with('error', 'Invalid or inactive payment method selected.');
        // }

        // Validate bank account requirement based on payment method configuration
        // if ($paymentMethod->requires_bank_account && empty($validated['payment_bank_account_id'])) {
        //     return redirect()->back()->with('error', 'Please select a bank account for this payment method.');
        // }

        // Validate bank account belongs to this shop
        if (!empty($validated['payment_bank_account_id'])) {
            $subshopId = (int) session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            $validBankAccount = BankAccounts::where('id', $validated['payment_bank_account_id'])
                ->whereIn('subshop_id', $shopSubshopIds)
                ->where('is_active', 1)
                ->exists();

            if (!$validBankAccount) {
                return redirect()->back()->with('error', 'Invalid bank account selected.');
            }
        }

        try {
            $paidFees = DB::transaction(function () use ($validated, $loan) {
                return $this->feePaymentService->payAllPendingFees(
                    $loan->id,
                    (string) $validated['payment_method'],
                    $validated['payment_bank_account_id'] ? (int) $validated['payment_bank_account_id'] : null,
                    $validated['notes'] ?? null,
                    (int) Auth::id(),
                );
            });

            $count = count($paidFees);
            return redirect()->back()->with('success', "Successfully paid {$count} fee(s).");
        } catch (\Exception $e) {
            Log::error('Bulk fee payment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
                'loan_id' => $loan->id,
            ]);

            return redirect()->back()->withInput()->with('error', 'Failed to process fee payments: ' . $e->getMessage());
        }
    }

    /**
     * View fee payment history for a loan.
     */
    public function paymentHistory(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403);
        }

        $paidFees = LoanFeeApplications::with(['loanProductFee.loanFee', 'paidBy'])
            ->where('loan_id', $loan->id)
            ->where('is_paid', true)
            ->orderByDesc('paid_at')
            ->get();

        $totalPaid = $paidFees->sum('paid_amount');

        return view('loans.fees.payment-history', compact('loan', 'paidFees', 'totalPaid'));
    }
}
