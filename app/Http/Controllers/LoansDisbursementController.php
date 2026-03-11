<?php

namespace App\Http\Controllers;

use App\Models\DisbursementMethods;
use App\Models\LoanApprovals;
use App\Models\LoanDisbursements;
use App\Models\Loans;
use App\Models\LoanSecurityDeposit;
use App\Models\SubShop;
use App\Models\LoanProducts;
use App\Services\Loans\Disbursement\LoanDisbursementEngine;
use App\Services\Loans\Ledger\LoanTransactionLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class LoansDisbursementController extends Controller
{
    public function __construct(
        private readonly LoanDisbursementEngine $disbursementEngine,
        private readonly LoanTransactionLedger $ledger,
    ) {
    }

    /**
     * List approved loans pending disbursement with filters.
     *
     * Only loans with status = 'approved' are shown.
     * Filters: Branch (subshop), Loan Officer, Loan Product, Approval Date.
     */
    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        $query = Loans::query()
            ->where('subshop_id', $subshopId)
            ->where('status', 'approved')
            ->with(['customer', 'loanGroup', 'loanProduct.rules', 'collaterals', 'guarantors']);

        // Approval date lives on loan_approvals, not loans. We compute a virtual column.
        $query->select('loans.*')->selectSub(function ($q) {
            $q->from('loan_approvals')
                ->selectRaw('MAX(approved_at)')
                ->whereColumn('loan_approvals.loan_id', 'loans.id')
                ->where('loan_approvals.status', 'approved');
        }, 'approval_date');

        $query->orderBy('approval_date', 'asc');

        // Apply filters
        if ($request->filled('loan_product_id')) {
            $query->where('loan_product_id', $request->input('loan_product_id'));
        }

        if ($request->filled('approval_date_from')) {
            $query->having('approval_date', '>=', $request->input('approval_date_from'));
        }

        if ($request->filled('approval_date_to')) {
            $query->having('approval_date', '<=', $request->input('approval_date_to'));
        }

        $loans = $query->get();

        // Compute readiness badges per loan for the index view (avoid calling controller methods from Blade).
        $loans->transform(function (Loans $loan) {
            $loan->collateral_status_badge = $this->getCollateralStatus($loan);
            $loan->guarantor_status_badge = $this->getGuarantorStatus($loan);
            $loan->fees_status_badge = $this->getFeesStatus($loan);

            return $loan;
        });

        // Prepare filter options
        $loanProducts = LoanProducts::where('subshop_id', $subshopId)->orderBy('name')->get(['id', 'name']);

        return view('loans.disbursements.index', compact('loans', 'subshop', 'loanProducts'));
    }

    /**
     * Show detailed loan information for disbursement.
     *
     * Includes borrower/group, loan product, principal, interest method,
     * installments, collateral, guarantors, and fees.
     */
    public function show(Loans $loan): View
    {
        // Ensure loan belongs to current subshop and is approved
        $this->authorizeLoanInSubshop($loan);
        if ($loan->status !== 'approved') {
            abort(404, 'Loan not found or not approved for disbursement.');
        }

        $loan->load([
            'customer',
            'loanGroup',
            'loanProduct.interestMethod',
            'loanProduct.rules',
            'collaterals.customerCollateral.collateralType',
            'guarantors.guarantor',
        ]);

        // IMPORTANT: `loans.installments` is an integer column and will shadow the `installments()` relationship.
        // Do not access `$loan->installments` as a property anywhere; always query via the relationship method.
        $installments = $loan->installments()->orderBy('installment_number')->get();

        $approvalDate = $this->resolveLoanApprovalDate($loan);

        // Determine collateral and guarantor readiness
        $collateralStatus = $this->getCollateralStatus($loan);
        $guarantorStatus = $this->getGuarantorStatus($loan);
        $feesStatus = $this->getFeesStatus($loan);
        $securityDepositStatus = $this->getSecurityDepositStatus($loan);

        $subshopId = (int) session('subshop_id');
        $disbursementMethods = DisbursementMethods::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'requires_reference']);

        return view('loans.disbursements.show', compact('loan', 'collateralStatus', 'guarantorStatus', 'feesStatus', 'securityDepositStatus', 'approvalDate', 'installments', 'disbursementMethods'));
    }

    /**
     * Disburse a loan.
     *
     * Validates eligibility, calls LoanDisbursementEngine,
     * records ledger entry, updates installments, and handles fees.
     *
     * Idempotent: multiple clicks will not duplicate transactions.
     */
    public function disburse(Request $request, Loans $loan): RedirectResponse
    {
        // Ensure loan belongs to current subshop and is approved
        $this->authorizeLoanInSubshop($loan);
        if ($loan->status !== 'approved') {
            return redirect()->back()->with('error', 'Loan is not approved for disbursement.');
        }

        $request->validate([
            'disbursement_date' => 'required|date',
            'reference_number'  => 'nullable|string|max:100',
            'disbursement_method_id' => 'required|integer|exists:disbursement_methods,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $disbursementDate = Carbon::parse($request->input('disbursement_date'));
        $referenceNumber = $request->input('reference_number');
        $disbursementMethodId = (int) $request->input('disbursement_method_id');
        $notes = $request->input('notes');

        $method = DisbursementMethods::query()->whereKey($disbursementMethodId)->first();
        if (!$method) {
            return redirect()->back()->with('error', 'Invalid disbursement method selected.')->withInput();
        }

        $subshopId = (int) session('subshop_id');
        if ((int) $method->subshop_id !== $subshopId) {
            return redirect()->back()->with('error', 'Invalid disbursement method for this branch.')->withInput();
        }

        if ($method->requires_reference && empty($referenceNumber)) {
            return redirect()->back()->with('error', 'Reference number is required for the selected disbursement method.')->withInput();
        }

        $approvalDate = $this->resolveLoanApprovalDate($loan);
        if ($approvalDate && $disbursementDate->lt($approvalDate->startOfDay())) {
            return redirect()->back()->with('error', 'Disbursement date must be on or after the approval date.')->withInput();
        }

        try {
            // Business rule checks
            $this->validateDisbursementEligibility($loan);

            // Execute disbursement in a transaction to ensure atomicity
            DB::transaction(function () use ($loan, $disbursementDate, $referenceNumber, $disbursementMethodId, $notes) {
                /** @var Loans $loanLocked */
                $loanLocked = Loans::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();

                // Idempotency: do not allow multiple disbursement records for the same loan.
                if ($loanLocked->status === 'disbursed' || LoanDisbursements::query()->where('loan_id', $loanLocked->id)->exists()) {
                    return;
                }

                // Call the existing disbursement engine with the correct signature.
                // Note: the engine currently persists disbursement_date as now(); we still validate user input date here.
                $disbursement = $this->disbursementEngine->disburseLoan(
                    $loanLocked,
                    (float) $loanLocked->principal_amount,
                    $disbursementMethodId,
                    $referenceNumber,
                    (int) Auth::id(),
                    $notes
                );

                // Record in the Loan Transaction Ledger
                $this->ledger->recordDisbursement($loanLocked, (float) $loanLocked->principal_amount, (int) $disbursement->id);

                // Update installments: set start dates and active status based on disbursement date
                $this->activateInstallments($loanLocked);

                // Deduct fees if applicable (placeholder: adjust to real fee deduction logic)
                $this->deductFees($loanLocked, $disbursementDate);
            });

            return redirect()->route('loans.disbursement.index')->with('success', "Loan {$loan->loan_code} has been disbursed successfully.");
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Disbursement failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Ensure the loan belongs to the current subshop.
     */
    private function authorizeLoanInSubshop(Loans $loan): void
    {
        $subshopId = (int) session('subshop_id');
        if ($loan->subshop_id !== $subshopId) {
            abort(403, 'Unauthorized loan.');
        }
    }

    /**
     * Validate business rules before disbursement.
     *
     * - Collateral and guarantors must be present if required by the loan product.
     * - Fees must be cleared (placeholder logic).
     *
     * @throws ValidationException
     */
    private function validateDisbursementEligibility(Loans $loan): void
    {
        $loan->loadMissing(['loanProduct.rules', 'collaterals', 'guarantors']);

        $rules = $loan->loanProduct?->rules;

        // Collateral required?
        // In this system, the loan has a snapshot flag `requires_collateral`.
        if ($loan->requires_collateral && $loan->collaterals->isEmpty()) {
            throw ValidationException::withMessages(['collateral' => 'Collateral is required for this loan product.']);
        }

        // Guarantors required?
        if (($rules?->requires_guarantor ?? false) && $loan->guarantors->isEmpty()) {
            throw ValidationException::withMessages(['guarantor' => 'Guarantors are required for this loan product.']);
        }

        // Placeholder: ensure fees are cleared/deducted
        // In a real system, you would check a loan_fees table or similar.
        // For now, we assume fees are handled elsewhere or not blocking.

        if ((bool) $loan->requires_security_deposit) {
            $required = round((float) ($loan->security_deposit_amount ?? 0.0), 2);
            $paid = (float) LoanSecurityDeposit::query()
                ->where('loan_id', (int) $loan->id)
                ->where('subshop_id', (int) $loan->subshop_id)
                ->where('status', 'held')
                ->sum('amount');

            if ($required > 0 && round($paid, 2) < $required) {
                throw ValidationException::withMessages([
                    'security_deposit' => 'Required security deposit has not been fully collected.',
                ]);
            }
        }
    }

    private function getSecurityDepositStatus(Loans $loan): array
    {
        if (!(bool) $loan->requires_security_deposit) {
            return ['status' => 'Not Required', 'class' => 'bg-secondary'];
        }

        $required = round((float) ($loan->security_deposit_amount ?? 0.0), 2);
        if ($required <= 0) {
            return ['status' => 'Not Required', 'class' => 'bg-secondary'];
        }

        $paid = (float) LoanSecurityDeposit::query()
            ->where('loan_id', (int) $loan->id)
            ->where('subshop_id', (int) $loan->subshop_id)
            ->where('status', 'held')
            ->sum('amount');

        if (round($paid, 2) >= $required) {
            return ['status' => 'Collected', 'class' => 'bg-success'];
        }

        return ['status' => 'Pending', 'class' => 'bg-warning'];
    }

    /**
     * Activate installments based on disbursement date.
     *
     * Sets installment start dates and marks them as active.
     * Interest accrual should start from the disbursement date.
     */
    private function activateInstallments(Loans $loan): void
    {
        // Installment schedules are generated at loan origination. We must not regenerate/shift dates here.
        // We only ensure installments are active so accrual and repayment engines can process them.
        $loan->installments()->update(['is_active' => true]);
    }

    /**
     * Placeholder for fee deduction logic.
     *
     * In a real system, you would:
     * - Identify applicable fees (processing, security deposit, etc.).
     * - Record fee transactions.
     * - Possibly deduct from disbursement amount or create separate fee invoices.
     */
    private function deductFees(Loans $loan, Carbon $disbursementDate): void
    {
        // Placeholder: implement fee deduction based on loan product fees
        // Example: create fee records, update ledger, etc.
        // For now, we do nothing.
    }

    /**
     * Determine collateral readiness status.
     *
     * Returns an array with status and badge class for UI.
     */
    private function getCollateralStatus(Loans $loan): array
    {
        if (!$loan->requires_collateral) {
            return ['status' => 'Not Required', 'class' => 'bg-secondary'];
        }

        $loan->loadMissing('collaterals');
        if ($loan->collaterals->isEmpty()) {
            return ['status' => 'Missing', 'class' => 'bg-danger'];
        }

        $hasVerified = $loan->collaterals->contains(fn ($c) => (string) $c->status === 'verified');

        if (!$hasVerified) {
            return ['status' => 'Pending', 'class' => 'bg-warning'];
        }

        return ['status' => 'Ready', 'class' => 'bg-success'];
    }

    /**
     * Determine guarantor readiness status.
     *
     * Returns an array with status and badge class for UI.
     */
    private function getGuarantorStatus(Loans $loan): array
    {
        $loan->loadMissing('loanProduct.rules');
        $requiresGuarantor = (bool) ($loan->loanProduct?->rules?->requires_guarantor ?? false);

        if (!$requiresGuarantor) {
            return ['status' => 'Not Required', 'class' => 'bg-secondary'];
        }

        $loan->loadMissing('guarantors');
        if ($loan->guarantors->isEmpty()) {
            return ['status' => 'Missing', 'class' => 'bg-danger'];
        }

        // Optionally, check if guarantor documents are verified
        return ['status' => 'Ready', 'class' => 'bg-success'];
    }

    /**
     * Determine fees status.
     *
     * Placeholder: in a real system, check against a loan_fees table.
     */
    private function getFeesStatus(Loans $loan): array
    {
        // Fees in this system are applied to installments via FeeEngine/LoanFeeApplications.
        // For disbursement readiness, we flag if the first installment has fees_due > 0.
        $first = $loan->installments()->orderBy('installment_number')->first();
        if (!$first) {
            return ['status' => '—', 'class' => 'bg-secondary'];
        }

        if ((float) $first->fees_due > 0 && (float) $first->fees_paid < (float) $first->fees_due) {
            return ['status' => 'Pending', 'class' => 'bg-warning'];
        }

        return ['status' => 'Cleared', 'class' => 'bg-success'];
    }

    private function resolveLoanApprovalDate(Loans $loan): ?Carbon
    {
        $approvedAt = LoanApprovals::query()
            ->where('loan_id', $loan->id)
            ->where('status', 'approved')
            ->max('approved_at');

        if ($approvedAt === null) {
            return null;
        }

        return Carbon::parse($approvedAt);
    }
}
