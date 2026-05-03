<?php

namespace App\Http\Controllers\Loans\Penalties;

use App\Http\Controllers\Controller;
use App\Models\BankAccounts;
use App\Models\LoanPenaltyApplications;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Loans\Penalties\PenaltyPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PenaltyPaymentController extends Controller
{
    public function __construct(
        protected PenaltyPaymentService $penaltyService
    ) {
    }

    /**
     * Show the penalty payment form for a loan.
     */
    public function showPaymentForm(Loans $loan): View
    {
        $this->authorizeLoanInSubshop($loan);

        $loan->load([
            'customer',
            'loanGroup',
            'loanProduct',
        ]);


        $subshopId = (int) session('subshop_id');
        $penaltyData = $this->penaltyService->getPendingPenalties($loan->id);
        $penaltySummary = $this->penaltyService->getPenaltySummary($loan->id);

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

        $bankAccounts = BankAccounts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get();

        return view('loans.penalties.payment-form', compact(
            'loan',
            'penaltyData',
            'penaltySummary',
            'bankAccounts'
        ));
    }

    /**
     * Process a penalty payment.
     */
    public function processPayment(Request $request, Loans $loan): RedirectResponse
    {
        $this->authorizeLoanInSubshop($loan);

        $validated = $request->validate([
            'penalty_application_id' => 'required|integer|exists:loan_penalty_applications,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $payment = $this->penaltyService->payPenalty(
                (int) $validated['penalty_application_id'],
                (float) $validated['amount'],
                (string) $validated['payment_method'],
                $validated['payment_bank_account_id'] ? (int) $validated['payment_bank_account_id'] : null,
                $validated['reference_number'] ?? null,
                $validated['notes'] ?? null
            );

            return redirect()
                ->route('loans.loans.show', $loan)
                ->with('success', sprintf(
                    'Penalty payment of %s processed successfully.',
                    number_format((float) $validated['amount'], 2)
                ));
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to process penalty payment: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the forgiveness form for a specific penalty.
     */
    public function showForgiveForm(Loans $loan, int $penaltyId): View
    {
        $this->authorizeLoanInSubshop($loan);

        $penalty = LoanPenaltyApplications::with('loanProductPenalty.loanPenalty')
            ->where('loan_id', $loan->id)
            ->findOrFail($penaltyId);

        $loan->load(['customer', 'loanGroup']);

        return view('loans.penalties.forgive-form', compact('loan', 'penalty'));
    }

    /**
     * Process penalty forgiveness.
     */
    public function processForgiveness(Request $request, Loans $loan, int $penaltyId): RedirectResponse
    {
        $this->authorizeLoanInSubshop($loan);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $penalty = $this->penaltyService->forgivePenalty(
                $penaltyId,
                (float) $validated['amount'],
                (string) $validated['reason']
            );

            return redirect()
                ->route('loans.loans.show', $loan)
                ->with('success', sprintf(
                    'Penalty of %s has been forgiven.',
                    number_format((float) $validated['amount'], 2)
                ));
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to forgive penalty: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get pending penalties for a loan (AJAX endpoint).
     */
    public function getPendingPenalties(Loans $loan): \Illuminate\Http\JsonResponse
    {
        $this->authorizeLoanInSubshop($loan);

        $penaltyData = $this->penaltyService->getPendingPenalties($loan->id);

        return response()->json($penaltyData);
    }

    /**
     * Authorize that the loan belongs to the current user's subshop.
     */
    private function authorizeLoanInSubshop(Loans $loan): void
    {
        $subshopId = (int) session('subshop_id');

        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403, 'This loan does not belong to your branch.');
        }
    }
}
