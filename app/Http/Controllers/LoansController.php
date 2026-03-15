<?php

namespace App\Http\Controllers;

use App\Models\CustomerCollaterals;
use App\Models\Customers;
use App\Models\LoanApprovals;
use App\Models\LoanCollaterals;
use App\Models\LoanFeeApplications;
use App\Models\LoanGroups;
use App\Models\LoanInstallments;
use App\Models\LoanPenalties;
use App\Models\LoanProductAccounts;
use App\Models\LoanProductApprovalLevels;
use App\Models\LoanProductFees;
use App\Models\LoanProductPenalties;
use App\Models\LoanProductRules;
use App\Models\LoanProductTypes;
use App\Models\LoanProducts;
use App\Models\LoanSecurityDeposit;
use App\Models\Loans;
use App\Models\BankAccounts;
use App\Models\loanGuarantors;
use App\Models\Messages;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shop;
use App\Models\SubShop;
use App\Services\Loans\Fees\FeeEngine;
use App\Services\Loans\LoanScheduleEngine;
use App\Services\Loans\Penalties\PenaltyEngine;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class LoansController extends Controller
{
    public function apiCustomers(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string'],
            'id' => ['nullable'],
            'subshop_id' => ['nullable', 'integer'],
        ]);

        $subshopId = session('subshop_id') ?? $request->input('subshop_id');
        if (!$subshopId) {
            return response()->json(['error' => 'No subshop selected'], 400);
        }

        $q = $request->get('q');
        $id = $request->get('id');

        $customers = Customers::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->when($id, function ($query) use ($id) {
                $query->where('id', (int) $id);
            })
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone']);

        return response()->json($customers);
    }

    public function apiLoanGroups(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string'],
            'id' => ['nullable'],
            'subshop_id' => ['nullable', 'integer'],
        ]);

        $subshopId = session('subshop_id') ?? $request->input('subshop_id');
        if (!$subshopId) {
            return response()->json(['error' => 'No subshop selected'], 400);
        }

        $q = $request->get('q');
        $id = $request->get('id');

        $groups = LoanGroups::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->when($id, function ($query) use ($id) {
                $query->where('id', (int) $id);
            })
            ->when($q, function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json($groups);
    }

    public function apiCollaterals(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string'],
            'id' => ['nullable'],
            'customer_id' => ['nullable', 'integer'],
            'subshop_id' => ['nullable', 'integer'],
        ]);

        $subshopId = session('subshop_id') ?? $request->input('subshop_id');
        if (!$subshopId) {
            return response()->json(['error' => 'No subshop selected'], 400);
        }

        $q = $request->get('q');
        $id = $request->get('id');
        $customerId = $request->get('customer_id');

        $query = CustomerCollaterals::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true);

        if ($customerId) {
            $query->where('customer_id', (int) $customerId);
        }

        if ($id) {
            $query->where('id', (int) $id);
        }

        if ($q) {
            $query->where('description', 'like', "%{$q}%");
        }

        $collaterals = $query->orderBy('description')
            ->limit(20)
            ->get(['id', 'description', 'estimated_value']);

        return response()->json($collaterals);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        $q = (string) $request->query('q', '');
        $status = (string) $request->query('status', '');
        $borrowerType = (string) $request->query('borrower_type', '');
        $loanProductId = (string) $request->query('loan_product_id', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $query = Loans::query()
            ->where('subshop_id', $subshopId)
            ->with(['loanProduct', 'customer', 'loanGroup']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('loan_code', 'like', '%' . $q . '%')
                    ->orWhere('id', $q)
                    ->orWhereHas('customer', function ($c) use ($q) {
                        $c->where('name', 'like', '%' . $q . '%');
                    })
                    ->orWhereHas('loanGroup', function ($g) use ($q) {
                        $g->where('name', 'like', '%' . $q . '%');
                    })
                    ->orWhereHas('loanProduct', function ($p) use ($q) {
                        $p->where('name', 'like', '%' . $q . '%');
                    });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($borrowerType !== '') {
            $query->where('borrower_type', $borrowerType);
        }

        if ($loanProductId !== '') {
            $query->where('loan_product_id', (int) $loanProductId);
        }

        if ($dateFrom !== '') {
            $query->whereDate('disbursement_date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('disbursement_date', '<=', $dateTo);
        }

        $summaryBase = (clone $query)->selectRaw(
            "COUNT(*) as total,\n" .
            "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,\n" .
            "SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,\n" .
            "SUM(CASE WHEN status = 'disbursed' THEN 1 ELSE 0 END) as disbursed,\n" .
            "SUM(CASE WHEN status = 'paid_off' THEN 1 ELSE 0 END) as paid_off,\n" .
            "COALESCE(SUM(principal_amount), 0) as principal_sum,\n" .
            "COALESCE(SUM(outstanding_balance), 0) as outstanding_sum"
        )->first();

        $summary = [
            'total' => (int) ($summaryBase->total ?? 0),
            'pending' => (int) ($summaryBase->pending ?? 0),
            'approved' => (int) ($summaryBase->approved ?? 0),
            'disbursed' => (int) ($summaryBase->disbursed ?? 0),
            'paid_off' => (int) ($summaryBase->paid_off ?? 0),
            'principal_sum' => (float) ($summaryBase->principal_sum ?? 0),
            'outstanding_sum' => (float) ($summaryBase->outstanding_sum ?? 0),
        ];

        $loans = $query
            ->orderByDesc('id')
            ->get();

        $loanProducts = LoanProducts::query()
            ->where('subshop_id', $subshopId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $statuses = [
            'pending',
            'approved',
            'rejected',
            'disbursed',
            'partially_paid',
            'paid_off',
            'defaulted',
            'written_off',
        ];

        return view('loans.loans.index', compact(
            'subshop',
            'loans',
            'summary',
            'loanProducts',
            'statuses',
            'q',
            'status',
            'borrowerType',
            'loanProductId',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        $loanProducts = LoanProducts::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->where('is_visible', true)
            ->with(['rules', 'cashConfigs', 'accounts', 'repaymentFrequency', 'interestMethod'])
            ->orderBy('name')
            ->get();

        $customers = Customers::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $loanGroups = LoanGroups::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->with(['members' => function ($q) {
                $q->where('is_active', true)->with('customer');
            }])
            ->orderBy('name')
            ->get();

        $customerCollaterals = CustomerCollaterals::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        return view('loans.loans.create_loan', compact(
            'subshop',
            'loanProducts',
            'customers',
            'loanGroups',
            'customerCollaterals'
        ));
    }

    public function calculator(): View
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        $loanProducts = LoanProducts::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->where('is_visible', true)
            ->with(['rules', 'repaymentFrequency', 'interestMethod'])
            ->orderBy('name')
            ->get();

        return view('loans.loans.calculator.loan_calculator', compact('subshop', 'loanProducts'));
    }

    public function calculateLoan(Request $request, LoanScheduleEngine $scheduleEngine): JsonResponse
    {
        $subshopId = (int) session('subshop_id');
        if (!$subshopId) {
            return response()->json(['message' => 'Branch session not found. Please login again.'], 422);
        }

        $validated = $request->validate([
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'borrower_type' => ['required', Rule::in(['individual', 'group'])],
            'principal_amount' => ['required', 'numeric', 'min:0.01'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'installments' => ['required', 'integer', 'min:1'],
            'disbursement_date' => ['nullable', 'date'],
            'repayment_start_date' => ['nullable', 'date'],
        ]);

        $product = LoanProducts::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->with(['rules', 'repaymentFrequency', 'interestMethod'])
            ->find((int) $validated['loan_product_id']);

        if (!$product) {
            return response()->json(['message' => 'Invalid loan product for this branch.'], 422);
        }

        $rules = $product->rules;
        $principal = (float) $validated['principal_amount'];
        $installments = (int) $validated['installments'];
        $interestRate = (float) $validated['interest_rate'];

        if ($rules) {
            if (!is_null($rules->min_loan_amount) && $principal < (float) $rules->min_loan_amount) {
                return response()->json(['message' => 'Principal amount is below the product minimum.'], 422);
            }
            if (!is_null($rules->max_loan_amount) && $principal > (float) $rules->max_loan_amount) {
                return response()->json(['message' => 'Principal amount is above the product maximum.'], 422);
            }
            if (!is_null($rules->min_installments) && $installments < (int) $rules->min_installments) {
                return response()->json(['message' => 'Installments are below the product minimum.'], 422);
            }
            if (!is_null($rules->max_installments) && $installments > (int) $rules->max_installments) {
                return response()->json(['message' => 'Installments are above the product maximum.'], 422);
            }
            if (!is_null($rules->min_interest_rate) && $interestRate < (float) $rules->min_interest_rate) {
                return response()->json(['message' => 'Interest rate is below the product minimum.'], 422);
            }
            if (!is_null($rules->max_interest_rate) && $interestRate > (float) $rules->max_interest_rate) {
                return response()->json(['message' => 'Interest rate is above the product maximum.'], 422);
            }
        }

        $disbursementDate = $request->filled('disbursement_date')
            ? Carbon::parse($request->input('disbursement_date'))->toDateString()
            : now()->toDateString();

        $repaymentStartDate = $request->filled('repayment_start_date')
            ? Carbon::parse($request->input('repayment_start_date'))->toDateString()
            : null;

        $scheduleAnchorDate = $repaymentStartDate ?? $disbursementDate;

        $loan = new Loans();
        $loan->subshop_id = $subshopId;
        $loan->loan_product_id = (int) $product->id;
        $loan->borrower_type = (string) $validated['borrower_type'];
        $loan->principal_amount = $principal;
        $loan->interest_rate = $interestRate;
        $loan->installments = $installments;
        $loan->disbursement_date = $scheduleAnchorDate;
        $loan->repayment_frequency_code = $product->repaymentFrequency?->code;
        $loan->setRelation('loanProduct', $product);

        try {
            $schedule = $scheduleEngine->generate($loan);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $interestTotal = 0.0;
        $principalTotal = 0.0;
        $totalPayable = 0.0;
        foreach ($schedule as $row) {
            $interestTotal += (float) ($row['interest_amount'] ?? 0);
            $principalTotal += (float) ($row['principal_amount'] ?? 0);
            $totalPayable += (float) ($row['total_due'] ?? 0);
        }

        $last = collect($schedule)->last();
        $maturity = is_array($last) && !empty($last['due_date'])
            ? Carbon::parse((string) $last['due_date'])->toDateString()
            : null;

        return response()->json([
            'schedule' => $schedule,
            'maturity_date' => $maturity,
            'totals' => [
                'principal' => round($principalTotal, 2),
                'interest' => round($interestTotal, 2),
                'total_payable' => round($totalPayable, 2),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        Request $request,
        LoanScheduleEngine $scheduleEngine,
        FeeEngine $feeEngine,
        PenaltyEngine $penaltyEngine,
    ): RedirectResponse {
        $subshopId = (int) session('subshop_id');
        if (!$subshopId) {
            return back()->withInput()->with('error', 'Branch session not found. Please login again.');
        }

        $validator = Validator::make($request->all(), [
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],

            'loan_type' => ['required', Rule::in(['individual', 'group'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'loan_group_id' => ['nullable', 'integer', 'exists:loan_groups,id'],

            'principal_amount' => ['required', 'numeric', 'min:0.01'],
            'disbursement_date' => ['nullable', 'date'],
            'repayment_start_date' => ['nullable', 'date'],

            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'installments' => ['required', 'integer', 'min:1'],

            'collateral_ids' => ['nullable', 'array'],
            'collateral_ids.*' => ['integer', 'exists:customer_collaterals,id'],

            'guarantor_ids' => ['nullable', 'array'],
            'guarantor_ids.*' => ['integer', 'exists:customers,id'],
            'is_joint_liability' => ['nullable', 'boolean'],

            'security_deposit_amount' => ['nullable', 'numeric', 'min:0'],
        ]);


        // dd($validator);
        // exit;

        $validator->after(function ($v) use ($request, $subshopId) {
            $loanType = $request->input('loan_type');
            if ($loanType === 'individual' && !$request->filled('customer_id')) {
                $v->errors()->add('customer_id', 'Customer is required for individual loans.');
            }
            if ($loanType === 'group' && !$request->filled('loan_group_id')) {
                $v->errors()->add('loan_group_id', 'Loan group is required for group loans.');
            }
            if ($loanType === 'individual' && $request->filled('loan_group_id')) {
                $v->errors()->add('loan_group_id', 'You cannot select a group for an individual loan.');
            }
            if ($loanType === 'group' && $request->filled('customer_id')) {
                $v->errors()->add('customer_id', 'You cannot select an individual customer for a group loan.');
            }

            $product = LoanProducts::query()
                ->where('subshop_id', $subshopId)
                ->where('is_active', true)
                ->with(['rules'])
                ->find((int) $request->input('loan_product_id'));

            if (!$product) {
                $v->errors()->add('loan_product_id', 'Invalid loan product.');
                return;
            }

            $rules = $product->rules;
            if (!$rules) {
                return;
            }

            $principal = (float) $request->input('principal_amount');
            $installments = (int) $request->input('installments');
            $interestRate = (float) $request->input('interest_rate');

            // Business rule enforcement (policy guardrails).
            if (!is_null($rules->min_loan_amount) && $principal < (float) $rules->min_loan_amount) {
                $v->errors()->add('principal_amount', 'Principal amount is below the product minimum.');
            }
            if (!is_null($rules->max_loan_amount) && $principal > (float) $rules->max_loan_amount) {
                $v->errors()->add('principal_amount', 'Principal amount is above the product maximum.');
            }
            if (!is_null($rules->min_installments) && $installments < (int) $rules->min_installments) {
                $v->errors()->add('installments', 'Installments are below the product minimum.');
            }
            if (!is_null($rules->max_installments) && $installments > (int) $rules->max_installments) {
                $v->errors()->add('installments', 'Installments are above the product maximum.');
            }
            if (!is_null($rules->min_interest_rate) && $interestRate < (float) $rules->min_interest_rate) {
                $v->errors()->add('interest_rate', 'Interest rate is below the product minimum.');
            }
            if (!is_null($rules->max_interest_rate) && $interestRate > (float) $rules->max_interest_rate) {
                $v->errors()->add('interest_rate', 'Interest rate is above the product maximum.');
            }

            if ($rules->requires_collateral && empty($request->input('collateral_ids', []))) {
                $v->errors()->add('collateral_ids', 'Collateral is required for this product.');
            }
            if ($rules->requires_guarantor && empty($request->input('guarantor_ids', []))) {
                $v->errors()->add('guarantor_ids', 'Guarantors are required for this product.');
            }
            if ($rules->requires_security_deposit && !$request->filled('security_deposit_amount')) {
                $v->errors()->add('security_deposit_amount', 'Security deposit amount is required for this product.');
            }
        });

        $validated = $validator->validate();
        $loanId = null;
      

        try {
            $loanId = DB::transaction(fn () => $this->storeLoanWithinTransaction(
                $validated,
                $request,
                $subshopId,
                $scheduleEngine,
                $feeEngine,
                $penaltyEngine
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to create loan', [
                'subshop_id' => $subshopId,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $message = 'Failed to create loan. Please review the form and try again.';
            if (config('app.debug')) {
                $message .= ' (' . $e->getMessage() . ')';
            }

            return back()->withInput()->with('error', $message);
        }

        $loanCode = Loans::query()->whereKey((int) $loanId)->value('loan_code');

        return redirect()->route('loans.loans.show', ['loan' => $loanCode ?: $loanId])
            ->with('success', 'Loan created successfully.');
    }

    private function storeLoanWithinTransaction(
        array $validated,
        Request $request,
        int $subshopId,
        LoanScheduleEngine $scheduleEngine,
        FeeEngine $feeEngine,
        PenaltyEngine $penaltyEngine
    ): int {
        $loanProduct = $this->getLoanProductForSubshop($validated, $subshopId);

        $disbursementDate = $this->getDisbursementDate($request);
        $repaymentStartDate = $this->getRepaymentStartDate($request);

        // Schedule anchor: if repayment_start_date is specified, use it.
        $scheduleAnchorDate = $repaymentStartDate ?? $disbursementDate;

        //creating laon to the laon table
        $loan = $this->createLoan($validated, $request, $subshopId, $loanProduct, $disbursementDate);

        // Guarantors
        $this->storeGuarantors($request, $loan);

        // Collaterals
        $this->storeCollaterals($request, $subshopId, $loan);

        // Installment schedule
        $this->generateAndStoreSchedule($scheduleEngine, $loan, $scheduleAnchorDate);

        // Fees (application)
        $feeEngine->applyAllFees($loan, now());
        $feeEngine->applyFees($loan, 'loan_submitted', now());

        // Penalties (safe no-op at creation; keeps behavior consistent)
        $penaltyEngine->applyPenalties($loan, now(), 'overdue_installment');

        // Approvals
        $this->storeApprovalLevelsIfRequired($subshopId, $loanProduct, $loan);

        return (int) $loan->id;
    }

    private function getLoanProductForSubshop(array $validated, int $subshopId): LoanProducts
    {
        return LoanProducts::query()
            ->where('subshop_id', $subshopId)
            ->with(['rules', 'cashConfigs', 'accounts', 'repaymentFrequency', 'interestMethod', 'approvalLevels'])
            ->findOrFail((int) $validated['loan_product_id']);
    }

    private function getDisbursementDate(Request $request): string
    {
        return $request->filled('disbursement_date')
            ? Carbon::parse($request->input('disbursement_date'))->toDateString()
            : now()->toDateString();
    }

    private function getRepaymentStartDate(Request $request): ?string
    {
        return $request->filled('repayment_start_date')
            ? Carbon::parse($request->input('repayment_start_date'))->toDateString()
            : null;
    }

    private function createLoan(
        array $validated,
        Request $request,
        int $subshopId,
        LoanProducts $loanProduct,
        string $disbursementDate
    ): Loans {
        $rules = $loanProduct->rules;
        $accounts = $loanProduct->accounts;
        $repaymentFrequency = $loanProduct->repaymentFrequency;

        if (!$repaymentFrequency || empty($repaymentFrequency->code)) {
            throw new \RuntimeException('Loan product is missing repayment frequency configuration.');
        }

        if (!$accounts) {
            throw new \RuntimeException('Loan product is missing account configuration.');
        }

        $requiredAccountIds = [
            'principal_account_id' => $accounts->principal_account_id,
            'interest_receivable_account_id' => $accounts->interest_receivable_account_id,
            'interest_income_account_id' => $accounts->interest_income_account_id,
            'penalty_receivable_account_id' => $accounts->penalty_receivable_account_id,
            'penalty_income_account_id' => $accounts->penalty_income_account_id,
            'write_off_expense_account_id' => $accounts->write_off_expense_account_id,
        ];

        foreach ($requiredAccountIds as $key => $val) {
            if (is_null($val) || (int) $val <= 0) {
                throw new \RuntimeException('Loan product has invalid account configuration: ' . $key);
            }
        }

        return Loans::create([
            'subshop_id' => $subshopId,
            'loan_product_id' => $loanProduct->id,
            'borrower_type' => $validated['loan_type'],
            'customer_id' => $validated['loan_type'] === 'individual' ? (int) $validated['customer_id'] : null,
            'loan_group_id' => $validated['loan_type'] === 'group' ? (int) $validated['loan_group_id'] : null,
            'principal_amount' => (float) $validated['principal_amount'],
            'interest_rate' => (float) $validated['interest_rate'],
            'installments' => (int) $validated['installments'],
            'installments_paid' => 0,
            'outstanding_balance' => (float) $validated['principal_amount'],
            'next_installment_amount' => null,
            'disbursement_date' => $disbursementDate,
            'maturity_date' => null,
            'repayment_frequency_code' => (string) $repaymentFrequency->code,
            'supports_collateral' => (bool) $loanProduct->supports_collateral,
            'requires_approval' => (bool) $loanProduct->requires_approval,
            'status' => 'pending',
            'is_active' => true,
            'allow_top_up' => (bool) ($rules?->allow_top_up ?? false),
            'requires_collateral' => (bool) ($rules?->requires_collateral ?? false),
            'collateral_value' => null,
            'collateral_coverage_ratio' => null,
            'requires_security_deposit' => (bool) ($rules?->requires_security_deposit ?? false),
            'security_deposit_amount' => $request->filled('security_deposit_amount')
                ? (float) $validated['security_deposit_amount']
                : null,
            'approval_completed' => false,
            'approval_history' => null,
            'principal_account_id' => (int) $accounts->principal_account_id,
            'interest_receivable_account_id' => (int) $accounts->interest_receivable_account_id,
            'interest_income_account_id' => (int) $accounts->interest_income_account_id,
            'penalty_receivable_account_id' => (int) $accounts->penalty_receivable_account_id,
            'penalty_income_account_id' => (int) $accounts->penalty_income_account_id,
            'write_off_expense_account_id' => (int) $accounts->write_off_expense_account_id,
            'fee_income_account_id' => $accounts?->fee_income_account_id,
            'customer_savings_account_id' => $accounts?->customer_savings_account_id,
            'customer_security_deposit_account_id' => $accounts?->customer_security_deposit_account_id,
        ]);
    }

    private function storeGuarantors(Request $request, Loans $loan): void
    {
        $guarantorIds = $request->input('guarantor_ids', []);
        if (!is_array($guarantorIds) || empty($guarantorIds)) {
            return;
        }

        $isJoint = (bool) $request->boolean('is_joint_liability');
        foreach (array_unique($guarantorIds) as $gid) {
            loanGuarantors::updateOrCreate(
                ['loan_id' => $loan->id, 'guarantor_id' => (int) $gid],
                ['is_joint_liability' => $isJoint]
            );
        }
    }

    private function storeCollaterals(Request $request, int $subshopId, Loans $loan): void
    {
        $collateralIds = $request->input('collateral_ids', []);
        if (!is_array($collateralIds) || empty($collateralIds)) {
            return;
        }

        $totalCollateralValue = 0.0;
        $collaterals = CustomerCollaterals::query()
            ->where('subshop_id', $subshopId)
            ->whereIn('id', $collateralIds)
            ->get();

        foreach ($collaterals as $c) {
            $value = (float) $c->estimated_value;
            $totalCollateralValue += $value;

            LoanCollaterals::create([
                'subshop_id' => $subshopId,
                'loan_id' => $loan->id,
                'customer_collateral_id' => $c->id,
                'collateral_value' => $value,
                'accepted_value' => null,
                'coverage_ratio' => null,
                'status' => 'pending_verification',
                'verification_date' => null,
                'release_date' => null,
                'notes' => null,
                'is_active' => true,
            ]);
        }

        if ($totalCollateralValue <= 0) {
            return;
        }

        $loan->collateral_value = $totalCollateralValue;
        $loan->collateral_coverage_ratio = $loan->principal_amount > 0
            ? round(($totalCollateralValue / (float) $loan->principal_amount) * 100, 2)
            : null;
        $loan->save();
    }

    private function generateAndStoreSchedule(LoanScheduleEngine $scheduleEngine, Loans $loan, string $scheduleAnchorDate): void
    {
        $loan->disbursement_date = $scheduleAnchorDate;
        $schedule = $scheduleEngine->generate($loan);
        $scheduleEngine->storeSchedule($loan, $schedule);

        // Maturity date = last due date
        $last = collect($schedule)->last();
        if (is_array($last) && !empty($last['due_date'])) {
            $loan->maturity_date = Carbon::parse($last['due_date'])->toDateString();
            $loan->save();
        }
    }

    private function storeApprovalLevelsIfRequired(int $subshopId, LoanProducts $loanProduct, Loans $loan): void
    {
        if (!$loanProduct->requires_approval) {
            return;
        }

        $levels = LoanProductApprovalLevels::query()
            ->where('loan_product_id', $loanProduct->id)
            ->where('is_active', true)
            ->orderBy('level_order')
            ->get();

        foreach ($levels as $lvl) {
            LoanApprovals::create([
                'subshop_id' => $subshopId,
                'loan_id' => $loan->id,
                'loan_product_approval_level_id' => $lvl->id,
                'approved_by' => null,
                'level_order' => (int) $lvl->level_order,
                'status' => 'pending',
                'approved_at' => null,
                'comments' => null,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Loans $loan): View
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        // Ensure the loan belongs to the current subshop (security check)
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(404);
        }

        $latestScheduleVersion = (int) (LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->max('schedule_version') ?: 1);

        $allInstallments = LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->orderByDesc('schedule_version')
            ->orderBy('installment_number')
            ->get();

        $installmentsByVersion = $allInstallments->groupBy('schedule_version');

        // Keep $installments for existing UI sections: show the latest schedule first.
        $installments = $installmentsByVersion->get($latestScheduleVersion, collect());

        $collaterals = LoanCollaterals::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->with('customerCollateral')
            ->get();

        $guarantors = loanGuarantors::query()
            ->where('loan_id', $loan->id)
            ->with('guarantor')
            ->get();

        $loanFees = LoanFeeApplications::query()
            ->where('loan_id', $loan->id)
            ->with('loanProductFee.loanFee')
            ->get();

        $approvals = LoanApprovals::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->with('loanProductApprovalLevel.role')
            ->orderBy('level_order')
            ->get();

        $securityDepositRequired = round((float) ($loan->security_deposit_amount ?? 0.0), 2);
        $securityDepositPaid = (float) LoanSecurityDeposit::query()
            ->where('subshop_id', (int) $loan->subshop_id)
            ->where('loan_id', (int) $loan->id)
            ->where('status', 'held')
            ->sum('amount');

        $securityDepositStatus = 'not_required';
        if ((bool) $loan->requires_security_deposit) {
            if ($securityDepositRequired > 0 && round($securityDepositPaid, 2) >= $securityDepositRequired) {
                $securityDepositStatus = 'held';
            } else {
                $securityDepositStatus = 'pending';
            }
        }

        $securityDeposits = LoanSecurityDeposit::query()
            ->where('subshop_id', (int) $loan->subshop_id)
            ->where('loan_id', (int) $loan->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $bankAccounts = BankAccounts::query()
            ->where('subshop_id', (int) $loan->subshop_id)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get();

        return view('loans.loans.show', compact(
            'subshop',
            'loan',
            'installments',
            'allInstallments',
            'installmentsByVersion',
            'latestScheduleVersion',
            'collaterals',
            'guarantors',
            'loanFees',
            'approvals',
            'securityDepositRequired',
            'securityDepositPaid',
            'securityDepositStatus',
            'securityDeposits',
            'bankAccounts'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Loans $loan)
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        // Ensure the loan belongs to the current subshop (security check)
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(404);
        }

        $loan->load(['loanProduct.rules', 'customer', 'loanGroup']);

        $loanProducts = LoanProducts::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->where('is_visible', true)
            ->with(['rules', 'cashConfigs', 'accounts', 'repaymentFrequency', 'interestMethod'])
            ->orderBy('name')
            ->get();

        $customers = Customers::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $loanGroups = LoanGroups::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->with(['members' => function ($q) {
                $q->where('is_active', true)->with('customer');
            }])
            ->orderBy('name')
            ->get();

        $customerCollaterals = CustomerCollaterals::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        $selectedGuarantorIds = loanGuarantors::query()
            ->where('loan_id', $loan->id)
            ->pluck('guarantor_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $isJointLiability = (bool) loanGuarantors::query()
            ->where('loan_id', $loan->id)
            ->where('is_joint_liability', true)
            ->exists();

        $selectedCollateralIds = LoanCollaterals::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->pluck('customer_collateral_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        return view('loans.loans.edit_loan', compact(
            'subshop',
            'loan',
            'loanProducts',
            'customers',
            'loanGroups',
            'customerCollaterals',
            'selectedGuarantorIds',
            'selectedCollateralIds',
            'isJointLiability'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Loans $loan)
    {
        $subshopId = (int) session('subshop_id');
        if (!$subshopId) {
            return back()->withInput()->with('error', 'Branch session not found. Please login again.');
        }

        // Ensure the loan belongs to the current subshop (security check)
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(404);
        }

        $loan->load(['loanProduct.rules', 'loanProduct.accounts', 'loanProduct.repaymentFrequency', 'loanProduct.interestMethod']);

        // Microfinance best practice: only allow edits before disbursement / before any repayment activity.
        if ((string) $loan->status !== 'pending') {
            return back()->with('error', 'This loan cannot be edited at its current status.');
        }

        $hasPaidInstallments = LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where(function ($q) {
                $q->where('amount_paid', '>', 0)
                    ->orWhere('principal_paid', '>', 0)
                    ->orWhere('interest_paid', '>', 0)
                    ->orWhere('fees_paid', '>', 0)
                    ->orWhere('penalty_paid', '>', 0);
            })
            ->exists();

        if ($hasPaidInstallments || (int) $loan->installments_paid > 0) {
            return back()->with('error', 'This loan cannot be edited because repayments have already started.');
        }

        $validator = Validator::make($request->all(), [
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],

            'loan_type' => ['required', Rule::in(['individual', 'group'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'loan_group_id' => ['nullable', 'integer', 'exists:loan_groups,id'],

            'principal_amount' => ['required', 'numeric', 'min:0.01'],
            'disbursement_date' => ['nullable', 'date'],
            'repayment_start_date' => ['nullable', 'date'],

            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'installments' => ['required', 'integer', 'min:1'],

            'collateral_ids' => ['nullable', 'array'],
            'collateral_ids.*' => ['integer', 'exists:customer_collaterals,id'],

            'guarantor_ids' => ['nullable', 'array'],
            'guarantor_ids.*' => ['integer', 'exists:customers,id'],
            'is_joint_liability' => ['nullable', 'boolean'],

            'security_deposit_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validator->after(function ($v) use ($request, $subshopId) {
            $loanType = $request->input('loan_type');
            if ($loanType === 'individual' && !$request->filled('customer_id')) {
                $v->errors()->add('customer_id', 'Customer is required for individual loans.');
            }
            if ($loanType === 'group' && !$request->filled('loan_group_id')) {
                $v->errors()->add('loan_group_id', 'Loan group is required for group loans.');
            }
            if ($loanType === 'individual' && $request->filled('loan_group_id')) {
                $v->errors()->add('loan_group_id', 'You cannot select a group for an individual loan.');
            }
            if ($loanType === 'group' && $request->filled('customer_id')) {
                $v->errors()->add('customer_id', 'You cannot select an individual customer for a group loan.');
            }

            $product = LoanProducts::query()
                ->where('subshop_id', $subshopId)
                ->with(['rules'])
                ->find((int) $request->input('loan_product_id'));

            if (!$product) {
                $v->errors()->add('loan_product_id', 'Invalid loan product.');
                return;
            }

            $rules = $product->rules;
            if (!$rules) {
                return;
            }

            $principal = (float) $request->input('principal_amount');
            $installments = (int) $request->input('installments');
            $interestRate = (float) $request->input('interest_rate');

            if (!is_null($rules->min_loan_amount) && $principal < (float) $rules->min_loan_amount) {
                $v->errors()->add('principal_amount', 'Principal amount is below the product minimum.');
            }
            if (!is_null($rules->max_loan_amount) && $principal > (float) $rules->max_loan_amount) {
                $v->errors()->add('principal_amount', 'Principal amount is above the product maximum.');
            }
            if (!is_null($rules->min_installments) && $installments < (int) $rules->min_installments) {
                $v->errors()->add('installments', 'Installments are below the product minimum.');
            }
            if (!is_null($rules->max_installments) && $installments > (int) $rules->max_installments) {
                $v->errors()->add('installments', 'Installments are above the product maximum.');
            }
            if (!is_null($rules->min_interest_rate) && $interestRate < (float) $rules->min_interest_rate) {
                $v->errors()->add('interest_rate', 'Interest rate is below the product minimum.');
            }
            if (!is_null($rules->max_interest_rate) && $interestRate > (float) $rules->max_interest_rate) {
                $v->errors()->add('interest_rate', 'Interest rate is above the product maximum.');
            }

            if ($rules->requires_collateral && empty($request->input('collateral_ids', []))) {
                $v->errors()->add('collateral_ids', 'Collateral is required for this product.');
            }
            if ($rules->requires_guarantor && empty($request->input('guarantor_ids', []))) {
                $v->errors()->add('guarantor_ids', 'Guarantors are required for this product.');
            }
            if ($rules->requires_security_deposit && !$request->filled('security_deposit_amount')) {
                $v->errors()->add('security_deposit_amount', 'Security deposit amount is required for this product.');
            }
        });

        $validated = $validator->validate();

        try {
            DB::transaction(function () use ($loan, $request, $validated, $subshopId) {
                $loanProduct = LoanProducts::query()
                    ->where('subshop_id', $subshopId)
                    ->where('is_active', true)
                    ->with(['rules', 'cashConfigs', 'accounts', 'repaymentFrequency', 'interestMethod', 'approvalLevels'])
                    ->findOrFail((int) $validated['loan_product_id']);

                $disbursementDate = $this->getDisbursementDate($request);
                $repaymentStartDate = $this->getRepaymentStartDate($request);
                $scheduleAnchorDate = $repaymentStartDate ?? $disbursementDate;

                $rules = $loanProduct->rules;
                $accounts = $loanProduct->accounts;
                $repaymentFrequency = $loanProduct->repaymentFrequency;

                if (!$repaymentFrequency || empty($repaymentFrequency->code)) {
                    throw new \RuntimeException('Loan product is missing repayment frequency configuration.');
                }
                if (!$accounts) {
                    throw new \RuntimeException('Loan product is missing account configuration.');
                }

                $loan->loan_product_id = (int) $loanProduct->id;
                $loan->borrower_type = (string) $validated['loan_type'];
                $loan->customer_id = $validated['loan_type'] === 'individual' ? (int) $validated['customer_id'] : null;
                $loan->loan_group_id = $validated['loan_type'] === 'group' ? (int) $validated['loan_group_id'] : null;
                $loan->principal_amount = (float) $validated['principal_amount'];
                $loan->interest_rate = (float) $validated['interest_rate'];
                $loan->installments = (int) $validated['installments'];
                $loan->installments_paid = 0;
                $loan->outstanding_balance = (float) $validated['principal_amount'];
                $loan->disbursement_date = $scheduleAnchorDate;
                $loan->maturity_date = null;
                $loan->repayment_frequency_code = (string) $repaymentFrequency->code;
                $loan->supports_collateral = (bool) $loanProduct->supports_collateral;
                $loan->requires_approval = (bool) $loanProduct->requires_approval;
                $loan->allow_top_up = (bool) ($rules?->allow_top_up ?? false);
                $loan->requires_collateral = (bool) ($rules?->requires_collateral ?? false);
                $loan->requires_security_deposit = (bool) ($rules?->requires_security_deposit ?? false);
                $loan->security_deposit_amount = $request->filled('security_deposit_amount')
                    ? (float) $validated['security_deposit_amount']
                    : null;

                $loan->principal_account_id = (int) $accounts->principal_account_id;
                $loan->interest_receivable_account_id = (int) $accounts->interest_receivable_account_id;
                $loan->interest_income_account_id = (int) $accounts->interest_income_account_id;
                $loan->penalty_receivable_account_id = (int) $accounts->penalty_receivable_account_id;
                $loan->penalty_income_account_id = (int) $accounts->penalty_income_account_id;
                $loan->write_off_expense_account_id = (int) $accounts->write_off_expense_account_id;
                $loan->fee_income_account_id = $accounts?->fee_income_account_id;
                $loan->customer_savings_account_id = $accounts?->customer_savings_account_id;
                $loan->customer_security_deposit_account_id = $accounts?->customer_security_deposit_account_id;
                $loan->save();

                // Replace guarantors
                loanGuarantors::query()->where('loan_id', $loan->id)->delete();
                $this->storeGuarantors($request, $loan);

                // Replace collaterals
                LoanCollaterals::query()
                    ->where('loan_id', $loan->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
                $loan->collateral_value = null;
                $loan->collateral_coverage_ratio = null;
                $loan->save();
                $this->storeCollaterals($request, $subshopId, $loan);

                // Replace schedule (safe because we have asserted no payments exist)
                LoanInstallments::query()->where('loan_id', $loan->id)->delete();
                $scheduleEngine = app(LoanScheduleEngine::class);
                $this->generateAndStoreSchedule($scheduleEngine, $loan, $scheduleAnchorDate);

                // If approval is required for the chosen product, ensure approval rows exist.
                // (This keeps the loan consistent if product is changed pre-approval.)
                LoanApprovals::query()->where('loan_id', $loan->id)->update(['is_active' => false]);
                $this->storeApprovalLevelsIfRequired($subshopId, $loanProduct, $loan);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to update loan', [
                'subshop_id' => $subshopId,
                'loan_id' => $loan->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $message = 'Failed to update loan. Please review the form and try again.';
            if (config('app.debug')) {
                $message .= ' (' . $e->getMessage() . ')';
            }

            return back()->withInput()->with('error', $message);
        }

        return redirect()->route('loans.loans.show', ['loan' => $loan->id])
            ->with('success', 'Loan updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Loans $loan)
    {
        $subshopId = (int) session('subshop_id');
        if (!$subshopId) {
            return back()->with('error', 'Branch session not found. Please login again.');
        }

        // Ensure the loan belongs to the current subshop (security check)
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(404);
        }

        // Microfinance best practice: only allow deletion before any repayment activity.
        // Only allow deletion if loan is pending and no installments have been paid.
        if ($loan->status !== 'pending') {
            return back()->with('error', 'Only pending loans can be deleted.');
        }

        $hasPaidInstallments = LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where(function ($q) {
                $q->where('amount_paid', '>', 0)
                  ->orWhereNotNull('paid_date')
                  ->orWhereIn('status', ['paid', 'partial']);
            })
            ->exists();

        if ($hasPaidInstallments) {
            return back()->with('error', 'Loan cannot be deleted because repayment activity exists.');
        }

        \DB::beginTransaction();
        try {
            // Delete related records safely (cascade)
            LoanInstallments::query()->where('loan_id', $loan->id)->delete();
            LoanCollaterals::query()->where('loan_id', $loan->id)->delete();
            \App\Models\loanGuarantors::query()->where('loan_id', $loan->id)->delete();
            LoanApprovals::query()->where('loan_id', $loan->id)->delete();

            // Delete the loan itself
            $loan->delete();

            \DB::commit();

            return redirect()->route('loans.loans.index')->with('success', 'Loan deleted successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Loan delete error: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete loan. Please try again.');
        }
    }
}
