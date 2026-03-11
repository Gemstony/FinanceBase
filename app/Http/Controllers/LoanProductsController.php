<?php

namespace App\Http\Controllers;

use App\Models\LoanProductAccounts;
use App\Models\LoanProductApprovalLevels;
use App\Models\LoanProductCashConfigs;
use App\Models\LoanProductFees;
use App\Models\LoanProductPenalties;
use App\Models\LoanProductRules;
use App\Models\LoanProductTypes;
use App\Models\LoanProducts;
use App\Models\Role;
use App\Models\SubShop;
use App\Models\InterestMethods;
use App\Models\InterestCycles;
use App\Models\RepaymentFrequencies;
use App\Models\LoanFees;
use App\Models\LoanPenalties;
use App\Models\ChartsOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LoanProductsController extends Controller
{
    private function getShopSubshopIds(SubShop $subshop)
    {
        return SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
    }

    private function applyCrossFieldValidation($validator, Request $request): void
    {
        $validator->after(function ($v) use ($request) {
            $minLoan = $request->input('min_loan_amount');
            $maxLoan = $request->input('max_loan_amount');
            $defaultLoan = $request->input('default_loan_amount');
            $minCollateralCoverage = $request->input('min_collateral_coverage_ratio');

            if ($minLoan !== null && $maxLoan !== null && is_numeric($minLoan) && is_numeric($maxLoan)) {
                if ((float)$minLoan > (float)$maxLoan) {
                    $v->errors()->add('min_loan_amount', 'Minimum Loan Amount cannot be greater than Maximum Loan Amount.');
                }
            }

            if ($defaultLoan !== null && $defaultLoan !== '' && is_numeric($defaultLoan)) {
                if ($minLoan !== null && $minLoan !== '' && is_numeric($minLoan) && (float)$defaultLoan < (float)$minLoan) {
                    $v->errors()->add('default_loan_amount', 'Default Loan Amount cannot be less than Minimum Loan Amount.');
                }
                if ($maxLoan !== null && $maxLoan !== '' && is_numeric($maxLoan) && (float)$defaultLoan > (float)$maxLoan) {
                    $v->errors()->add('default_loan_amount', 'Default Loan Amount cannot be greater than Maximum Loan Amount.');
                }
            }

            if ($request->boolean('requires_approval')) {
                $levels = $request->input('approval_levels', []);
                $levels = is_array($levels) ? array_values($levels) : [];

                if (count($levels) === 0) {
                    $v->errors()->add('approval_levels', 'At least one approval level is required when Requires Approval is enabled.');
                    return;
                }

                $seenOrders = [];
                foreach ($levels as $idx => $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $roleId = $row['role_id'] ?? null;
                    $levelOrder = $row['level_order'] ?? null;
                    $rowMin = $row['min_loan_amount'] ?? null;
                    $rowMax = $row['max_loan_amount'] ?? null;

                    $hasAny = ($roleId !== null && $roleId !== '')
                        || ($levelOrder !== null && $levelOrder !== '')
                        || ($rowMin !== null && $rowMin !== '')
                        || ($rowMax !== null && $rowMax !== '');
                    if (!$hasAny) {
                        continue;
                    }

                    if ($roleId === null || $roleId === '') {
                        $v->errors()->add("approval_levels.$idx.role_id", 'Approver Role is required.');
                    }

                    if ($levelOrder === null || $levelOrder === '' || !is_numeric($levelOrder) || (int)$levelOrder < 1) {
                        $v->errors()->add("approval_levels.$idx.level_order", 'Level Order must be an integer of 1 or greater.');
                    } else {
                        $ord = (int)$levelOrder;
                        if (isset($seenOrders[$ord])) {
                            $v->errors()->add("approval_levels.$idx.level_order", 'Level Order must be unique.');
                        }
                        $seenOrders[$ord] = true;
                    }

                    if ($rowMin !== null && $rowMin !== '' && !is_numeric($rowMin)) {
                        $v->errors()->add("approval_levels.$idx.min_loan_amount", 'Min Amount must be numeric.');
                    }
                    if ($rowMax !== null && $rowMax !== '' && !is_numeric($rowMax)) {
                        $v->errors()->add("approval_levels.$idx.max_loan_amount", 'Max Amount must be numeric.');
                    }

                    if ($rowMin !== null && $rowMin !== '' && $minLoan !== null && $minLoan !== '' && is_numeric($rowMin) && is_numeric($minLoan)) {
                        if ((float)$rowMin < (float)$minLoan) {
                            $v->errors()->add("approval_levels.$idx.min_loan_amount", 'Min Amount cannot be less than the Product Minimum Loan Amount.');
                        }
                    }
                    if ($rowMax !== null && $rowMax !== '' && $maxLoan !== null && $maxLoan !== '' && is_numeric($rowMax) && is_numeric($maxLoan)) {
                        if ((float)$rowMax > (float)$maxLoan) {
                            $v->errors()->add("approval_levels.$idx.max_loan_amount", 'Max Amount cannot be greater than the Product Maximum Loan Amount.');
                        }
                    }

                    if ($rowMin !== null && $rowMin !== '' && $rowMax !== null && $rowMax !== '' && is_numeric($rowMin) && is_numeric($rowMax)) {
                        if ((float)$rowMin > (float)$rowMax) {
                            $v->errors()->add("approval_levels.$idx.min_loan_amount", 'Min Amount cannot be greater than Max Amount.');
                        }
                    }
                }
            }

            if ($request->boolean('requires_collateral')) {
                if ($minCollateralCoverage === null || $minCollateralCoverage === '') {
                    $v->errors()->add('min_collateral_coverage_ratio', 'Minimum Collateral Coverage is required when collateral is required.');
                }
            }

        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('dashboard')->with('error', 'Subshop session not found. Please login again.');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);

        $q = request('q');
        $isActive = request('is_active');
        $isVisible = request('is_visible');
        $requiresApproval = request('requires_approval');
        $typeId = request('loan_product_type_id');

        $loanProductTypes = LoanProductTypes::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->orderBy('name')
            ->get();

        $loanProductsQuery = LoanProducts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->with([
                'rules',
                'interestMethod',
                'interestCycle',
                'repaymentFrequency',
            ]);

        if ($q !== null && $q !== '') {
            $loanProductsQuery->where(function ($w) use ($q) {
                $w->where('name', 'like', '%' . $q . '%')
                    ->orWhere('code', 'like', '%' . $q . '%');
            });
        }

        if ($isActive === '1' || $isActive === '0') {
            $loanProductsQuery->where('is_active', $isActive === '1');
        }

        if ($isVisible === '1' || $isVisible === '0') {
            $loanProductsQuery->where('is_visible', $isVisible === '1');
        }

        if ($requiresApproval === '1' || $requiresApproval === '0') {
            $loanProductsQuery->where('requires_approval', $requiresApproval === '1');
        }

        if ($typeId !== null && $typeId !== '') {
            $loanProductsQuery->where('loan_product_type_id', $typeId);
        }

        $loanProducts = $loanProductsQuery
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'count' => (clone $loanProductsQuery)->count(),
            'active' => (clone $loanProductsQuery)->where('is_active', true)->count(),
            'visible' => (clone $loanProductsQuery)->where('is_visible', true)->count(),
            'requires_approval' => (clone $loanProductsQuery)->where('requires_approval', true)->count(),
        ];

        return view('loans.loan_products.loan_products', compact(
            'subshop',
            'loanProducts',
            'loanProductTypes',
            'q',
            'isActive',
            'isVisible',
            'requiresApproval',
            'typeId',
            'summary'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $subshopId = session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);

        // Load lookups scoped to current shop (all subshops)
        $interestMethods = InterestMethods::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $roles = Role::where(function($q) use ($subshop) {
            $q->whereNull('shop_id')->orWhere('shop_id', $subshop->shop_id);
        })->get();


        $loanProductTypes = LoanProductTypes::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();


        $interestCycles = InterestCycles::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $repaymentFrequencies = RepaymentFrequencies::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $loanFees = LoanFees::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $loanPenalties = LoanPenalties::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Chart of accounts for mapping principal, interest, penalties, fees, write-offs
        $accounts = ChartsOfAccount::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get();

        return view('loans.loan_products.create_loan_product', compact(
            'subshop',
            'interestMethods',
            'loanProductTypes',
            'interestCycles',
            'repaymentFrequencies',
            'loanFees',
            'loanPenalties',
            'accounts',
            'roles'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return back()->withInput()->with('error', 'Subshop session not found. Please login again.');
        }

        try {
            $feesDbg = $request->input('fees_config', []);
            $penDbg = $request->input('penalties_config', []);
            $appDbg = $request->input('approval_levels', []);
            \Log::info('LoanProduct store() repeater payload counts', [
                'fees_rows' => is_array($feesDbg) ? count($feesDbg) : null,
                'penalties_rows' => is_array($penDbg) ? count($penDbg) : null,
                'approvals_rows' => is_array($appDbg) ? count($appDbg) : null,
                'fees_keys' => is_array($feesDbg) ? array_keys($feesDbg) : null,
                'penalties_keys' => is_array($penDbg) ? array_keys($penDbg) : null,
                'approvals_keys' => is_array($appDbg) ? array_keys($appDbg) : null,
                'requires_approval' => $request->boolean('requires_approval'),
            ]);
        } catch (\Throwable $e) {
            // ignore logging failures
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:loan_products,code'],
            'description' => ['nullable', 'string'],
            'loan_product_type_id' => ['required', 'integer', 'exists:loan_product_types,id'],

            'interest_method_id' => ['required', 'integer', 'exists:interest_methods,id'],
            'interest_cycle_id' => ['required', 'integer', 'exists:interest_cycles,id'],
            'repayment_frequency_id' => ['required', 'integer', 'exists:repayment_frequencies,id'],

            'default_installments' => ['nullable', 'integer', 'min:1'],
            'min_installments' => ['nullable', 'integer', 'min:1'],
            'max_installments' => ['nullable', 'integer', 'min:1'],

            'default_loan_amount' => ['nullable', 'numeric', 'min:0'],

            'supports_collateral' => ['nullable'],
            'requires_approval' => ['nullable'],
            'is_active' => ['nullable'],
            'is_visible' => ['nullable'],

            // Rules
            'min_age' => ['nullable', 'integer', 'min:0'],
            'max_age' => ['nullable', 'integer', 'min:0'],
            'min_membership_days' => ['nullable', 'integer', 'min:0'],
            'max_active_loans' => ['nullable', 'integer', 'min:0'],
            'requires_active_savings' => ['nullable'],
            'min_savings_balance' => ['nullable', 'numeric', 'min:0'],
            'loan_to_savings_ratio' => ['nullable', 'numeric', 'min:0'],
            'min_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'max_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'grace_period_days' => ['required', 'integer', 'min:0'],
            'penalty_start_day' => ['nullable', 'integer', 'min:0'],
            'auto_apply_penalty' => ['nullable'],
            'allow_interest_override' => ['nullable'],
            'allow_top_up' => ['nullable'],
            'min_repayment_ratio_for_topup' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allow_restructure' => ['nullable'],
            'requires_guarantor' => ['nullable'],
            'manual_override_allowed' => ['nullable'],
            'requires_collateral' => ['nullable'],
            'min_collateral_coverage_ratio' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requires_security_deposit' => ['nullable'],
            'min_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Accounts
            'principal_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'interest_receivable_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'interest_income_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'penalty_receivable_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'penalty_income_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'write_off_expense_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'fee_income_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],
            'customer_savings_control_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],
            'security_deposit_control_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],
            'customer_savings_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],
            'customer_security_deposit_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],

            // Fees
            'fees_config' => ['nullable', 'array'],
            'fees_config.*.loan_fee_id' => ['nullable', 'integer', 'exists:loan_fees,id'],

            // Penalties
            'penalties_config' => ['nullable', 'array'],
            'penalties_config.*.loan_penalty_id' => ['nullable', 'integer', 'exists:loan_penalties,id'],
            'penalties_config.*.grace_days_override' => ['nullable', 'integer', 'min:0'],
            'penalties_config.*.auto_apply' => ['nullable'],
            'penalties_config.*.max_applications' => ['nullable', 'integer', 'min:0'],

            // Approval
            'approval_levels' => ['nullable', 'array'],
            'approval_levels.*.level_order' => ['nullable', 'integer', 'min:1'],
            'approval_levels.*.role_id' => ['nullable', 'string'],
            'approval_levels.*.min_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'approval_levels.*.max_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'approval_levels.*.mandatory' => ['nullable'],
            'approval_levels.*.can_override_rules' => ['nullable'],
            'approval_levels.*.can_reject' => ['nullable'],
        ]);

        $this->applyCrossFieldValidation($validator, $request);
        $validated = $validator->validate();

        $createdCounts = ['fees' => 0, 'penalties' => 0, 'approvals' => 0];

        try {
            DB::transaction(function () use ($request, $subshopId, $validated, &$createdCounts) {
                $loanProduct = LoanProducts::create([
                    'subshop_id' => $subshopId,
                    'name' => $validated['name'],
                    'code' => $validated['code'],
                    'description' => $validated['description'] ?? null,
                    'loan_product_type_id' => $validated['loan_product_type_id'],
                    'interest_method_id' => $validated['interest_method_id'],
                    'interest_cycle_id' => $validated['interest_cycle_id'],
                    'repayment_frequency_id' => $validated['repayment_frequency_id'],
                    'default_installments' => $validated['default_installments'] ?? null,
                    'min_installments' => $validated['min_installments'] ?? null,
                    'max_installments' => $validated['max_installments'] ?? null,
                    'default_loan_amount' => $validated['default_loan_amount'] ?? null,
                    'supports_collateral' => $request->boolean('supports_collateral'),
                    'requires_approval' => $request->boolean('requires_approval'),
                    'is_active' => $request->boolean('is_active'),
                    'is_visible' => $request->boolean('is_visible'),
                ]);

                LoanProductRules::create([
                    'subshop_id' => $subshopId,
                    'loan_product_id' => $loanProduct->id,
                    'min_age' => $validated['min_age'] ?? null,
                    'max_age' => $validated['max_age'] ?? null,
                    'min_membership_days' => $validated['min_membership_days'] ?? null,
                    'requires_active_savings' => $request->boolean('requires_active_savings'),
                    'min_savings_balance' => $validated['min_savings_balance'] ?? null,
                    'loan_to_savings_ratio' => $validated['loan_to_savings_ratio'] ?? null,
                    'min_loan_amount' => $validated['min_loan_amount'] ?? null,
                    'max_loan_amount' => $validated['max_loan_amount'] ?? null,
                    'max_active_loans' => $validated['max_active_loans'] ?? null,
                    // also written to loan_products (as requested)
                    'min_installments' => $validated['min_installments'] ?? null,
                    'max_installments' => $validated['max_installments'] ?? null,
                    'grace_period_days' => $validated['grace_period_days'] ?? 0,
                    'requires_security_deposit' => $request->boolean('requires_security_deposit'),
                    'requires_collateral' => $request->boolean('requires_collateral'),
                    'min_collateral_coverage_ratio' => $validated['min_collateral_coverage_ratio'] ?? null,
                    'min_interest_rate' => $validated['min_interest_rate'] ?? null,
                    'max_interest_rate' => $validated['max_interest_rate'] ?? null,
                    'allow_interest_override' => $request->boolean('allow_interest_override'),
                    'penalty_start_day' => $validated['penalty_start_day'] ?? 0,
                    'auto_apply_penalty' => $request->boolean('auto_apply_penalty'),
                    'allow_top_up' => $request->boolean('allow_top_up'),
                    'min_repayment_ratio_for_topup' => $validated['min_repayment_ratio_for_topup'] ?? null,
                    'allow_restructure' => $request->boolean('allow_restructure'),
                    'requires_guarantor' => $request->boolean('requires_guarantor'),
                    'manual_override_allowed' => $request->boolean('manual_override_allowed'),
                    'is_active' => true,
                ]);

                LoanProductCashConfigs::create([
                    'subshop_id' => $subshopId,
                    'loan_product_id' => $loanProduct->id,
                    'deposit_requirement' => 'none',
                    'deposit_value' => null,
                    'deposit_basis' => null,
                    'use_customer_savings' => false,
                    'lock_period_days' => null,
                    'allow_withdrawal_during_loan' => false,
                    'is_refundable' => false,
                    'apply_on_default' => false,
                    'is_active' => true,
                ]);

                LoanProductAccounts::create([
                    'subshop_id' => $subshopId,
                    'loan_product_id' => $loanProduct->id,
                    'principal_account_id' => $validated['principal_account_id'],
                    'customer_savings_control_account_id' => $validated['customer_savings_control_account_id'] ?? null,
                    'security_deposit_control_account_id' => $validated['security_deposit_control_account_id'] ?? null,
                    'interest_receivable_account_id' => $validated['interest_receivable_account_id'],
                    'interest_income_account_id' => $validated['interest_income_account_id'],
                    'penalty_receivable_account_id' => $validated['penalty_receivable_account_id'],
                    'penalty_income_account_id' => $validated['penalty_income_account_id'],
                    'fee_income_account_id' => $validated['fee_income_account_id'] ?? null,
                    'customer_savings_account_id' => $validated['customer_savings_account_id'] ?? null,
                    'customer_security_deposit_account_id' => $validated['customer_security_deposit_account_id'] ?? null,
                    'write_off_expense_account_id' => $validated['write_off_expense_account_id'],
                    'is_active' => true,
                ]);

                $fees = $request->input('fees_config', []);
                if (is_array($fees)) {
                    foreach ($fees as $feeRow) {
                        $loanFeeId = $feeRow['loan_fee_id'] ?? null;
                        if (!$loanFeeId) {
                            continue;
                        }

                        LoanProductFees::create([
                            'subshop_id' => $subshopId,
                            'loan_product_id' => $loanProduct->id,
                            'loan_fee_id' => $loanFeeId,
                            'charge_event' => 'disbursement',
                            'payment_method' => 'upfront',
                            'auto_apply' => true,
                            'max_applications' => null,
                            'is_waivable' => false,
                            'is_mandatory' => true,
                            'is_active' => true,
                        ]);

                        $createdCounts['fees']++;
                    }
                }

                $penalties = $request->input('penalties_config', []);
                if (is_array($penalties)) {
                    foreach ($penalties as $penRow) {
                        $loanPenaltyId = $penRow['loan_penalty_id'] ?? null;
                        if (!$loanPenaltyId) {
                            continue;
                        }

                        LoanProductPenalties::create([
                            'subshop_id' => $subshopId,
                            'loan_product_id' => $loanProduct->id,
                            'loan_penalty_id' => $loanPenaltyId,
                            'grace_days_override' => isset($penRow['grace_days_override']) && $penRow['grace_days_override'] !== ''
                                ? (int) $penRow['grace_days_override']
                                : null,
                            'auto_apply' => array_key_exists('auto_apply', $penRow) ? !empty($penRow['auto_apply']) : true,
                            'max_applications' => isset($penRow['max_applications']) && $penRow['max_applications'] !== ''
                                ? (int) $penRow['max_applications']
                                : null,
                            'is_active' => true,
                        ]);

                        $createdCounts['penalties']++;
                    }
                }

                if ($request->boolean('requires_approval')) {
                    $approvalLevels = $request->input('approval_levels', []);
                    if (is_array($approvalLevels)) {
                        foreach ($approvalLevels as $lvlRow) {
                            $order = $lvlRow['level_order'] ?? null;
                            $roleId = $lvlRow['role_id'] ?? null;
                            if (!$order || !$roleId) {
                                continue;
                            }

                            LoanProductApprovalLevels::create([
                                'subshop_id' => $subshopId,
                                'loan_product_id' => $loanProduct->id,
                                'level_order' => (int) $order,
                                'role_id' => (string) $roleId,
                                'min_loan_amount' => $lvlRow['min_loan_amount'] ?? null,
                                'max_loan_amount' => $lvlRow['max_loan_amount'] ?? null,
                                'mandatory' => !empty($lvlRow['mandatory']),
                                'can_override_rules' => !empty($lvlRow['can_override_rules']),
                                'can_reject' => array_key_exists('can_reject', $lvlRow) ? !empty($lvlRow['can_reject']) : true,
                                'is_active' => true,
                            ]);

                            $createdCounts['approvals']++;
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create loan product', [
                'subshop_id' => $subshopId,
                'exception' => $e,
            ]);
            return back()->withInput()->with('error', 'Failed to create loan product. Please review the form and try again.');
        }

        $suffix = " (Fees: {$createdCounts['fees']}, Penalties: {$createdCounts['penalties']}, Approvals: {$createdCounts['approvals']})";
        return redirect()->route('loans.loan_products.index')->with('success', 'Loan product created successfully.' . $suffix);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('dashboard')->with('error', 'Subshop session not found. Please login again.');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);

        $loanProduct = LoanProducts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->with([
                'rules',
                'cashConfigs',
                'accounts.principalAccount',
                'accounts.interestReceivableAccount',
                'accounts.interestIncomeAccount',
                'accounts.penaltyReceivableAccount',
                'accounts.penaltyIncomeAccount',
                'accounts.feeIncomeAccount',
                'accounts.writeOffExpenseAccount',
                'fees.loanFee',
                'penalties.loanPenalty',
                'approvalLevels.role',
                'interestMethod',
                'interestCycle',
                'repaymentFrequency',
                'type',
            ])
            ->findOrFail($id);

        $loanProductRules = $loanProduct->rules;
        $loanProductCashConfig = $loanProduct->cashConfigs;
        $loanProductAccounts = $loanProduct->accounts;
        $loanProductFees = $loanProduct->fees;
        $loanProductPenalties = $loanProduct->penalties;
        $loanProductApprovalLevels = $loanProduct->approvalLevels;

        return view('loans.loan_products.view_loan_product', compact(
            'subshop',
            'loanProduct',
            'loanProductRules',
            'loanProductCashConfig',
            'loanProductAccounts',
            'loanProductFees',
            'loanProductPenalties',
            'loanProductApprovalLevels'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('dashboard')->with('error', 'Subshop session not found. Please login again.');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);

        $loanProduct = LoanProducts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->with([
                'rules',
                'cashConfigs',
                'accounts',
                'fees',
                'penalties',
                'approvalLevels',
                'interestMethod',
                'interestCycle',
                'repaymentFrequency',
                'type',
            ])
            ->findOrFail($id);

        $interestMethods = InterestMethods::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $roles = Role::where(function($q) use ($subshop) {
            $q->whereNull('shop_id')->orWhere('shop_id', $subshop->shop_id);
        })->get();

        $loanProductTypes = LoanProductTypes::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $interestCycles = InterestCycles::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $repaymentFrequencies = RepaymentFrequencies::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $loanFees = LoanFees::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $loanPenalties = LoanPenalties::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $accounts = ChartsOfAccount::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get();

        $loanProductRules = $loanProduct->rules;
        $loanProductCashConfig = $loanProduct->cashConfigs;
        $loanProductAccounts = $loanProduct->accounts;
        $loanProductFees = $loanProduct->fees;
        $loanProductPenalties = $loanProduct->penalties;
        $loanProductApprovalLevels = $loanProduct->approvalLevels;

        return view('loans.loan_products.create_loan_product', compact(
            'subshop',
            'loanProduct',
            'interestMethods',
            'loanProductTypes',
            'interestCycles',
            'repaymentFrequencies',
            'loanFees',
            'loanPenalties',
            'accounts',
            'roles',
            'loanProductRules',
            'loanProductCashConfig',
            'loanProductAccounts',
            'loanProductFees',
            'loanProductPenalties',
            'loanProductApprovalLevels'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return back()->withInput()->with('error', 'Subshop session not found. Please login again.');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);

        $loanProduct = LoanProducts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->findOrFail($id);

        $ownerSubshopId = $loanProduct->subshop_id;

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('loan_products', 'code')->ignore($loanProduct->id)],
            'description' => ['nullable', 'string'],
            'loan_product_type_id' => ['required', 'integer', 'exists:loan_product_types,id'],

            'interest_method_id' => ['required', 'integer', 'exists:interest_methods,id'],
            'interest_cycle_id' => ['required', 'integer', 'exists:interest_cycles,id'],
            'repayment_frequency_id' => ['required', 'integer', 'exists:repayment_frequencies,id'],

            'default_installments' => ['nullable', 'integer', 'min:1'],
            'min_installments' => ['nullable', 'integer', 'min:1'],
            'max_installments' => ['nullable', 'integer', 'min:1'],

            'default_loan_amount' => ['nullable', 'numeric', 'min:0'],

            'supports_collateral' => ['nullable'],
            'requires_approval' => ['nullable'],
            'is_active' => ['nullable'],
            'is_visible' => ['nullable'],

            // Rules
            'min_age' => ['nullable', 'integer', 'min:0'],
            'max_age' => ['nullable', 'integer', 'min:0'],
            'min_membership_days' => ['nullable', 'integer', 'min:0'],
            'max_active_loans' => ['nullable', 'integer', 'min:0'],
            'requires_active_savings' => ['nullable'],
            'min_savings_balance' => ['nullable', 'numeric', 'min:0'],
            'loan_to_savings_ratio' => ['nullable', 'numeric', 'min:0'],
            'min_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'max_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'grace_period_days' => ['required', 'integer', 'min:0'],
            'penalty_start_day' => ['nullable', 'integer', 'min:0'],
            'auto_apply_penalty' => ['nullable'],
            'allow_interest_override' => ['nullable'],
            'allow_top_up' => ['nullable'],
            'min_repayment_ratio_for_topup' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allow_restructure' => ['nullable'],
            'requires_guarantor' => ['nullable'],
            'manual_override_allowed' => ['nullable'],
            'requires_collateral' => ['nullable'],
            'min_collateral_coverage_ratio' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requires_security_deposit' => ['nullable'],
            'min_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Accounts
            'principal_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'interest_receivable_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'interest_income_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'penalty_receivable_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'penalty_income_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'write_off_expense_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'fee_income_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],
            'customer_savings_control_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],
            'security_deposit_control_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],
            'customer_savings_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],
            'customer_security_deposit_account_id' => ['nullable', 'integer', 'exists:charts_of_accounts,id'],

            // Fees
            'fees_config' => ['nullable', 'array'],
            'fees_config.*.loan_fee_id' => ['nullable', 'integer', 'exists:loan_fees,id'],

            // Penalties
            'penalties_config' => ['nullable', 'array'],
            'penalties_config.*.loan_penalty_id' => ['nullable', 'integer', 'exists:loan_penalties,id'],
            'penalties_config.*.grace_days_override' => ['nullable', 'integer', 'min:0'],
            'penalties_config.*.auto_apply' => ['nullable'],
            'penalties_config.*.max_applications' => ['nullable', 'integer', 'min:0'],

            // Approval
            'approval_levels' => ['nullable', 'array'],
            'approval_levels.*.level_order' => ['nullable', 'integer', 'min:1'],
            'approval_levels.*.role_id' => ['nullable', 'string'],
            'approval_levels.*.min_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'approval_levels.*.max_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'approval_levels.*.mandatory' => ['nullable'],
            'approval_levels.*.can_override_rules' => ['nullable'],
            'approval_levels.*.can_reject' => ['nullable'],
        ]);

        $this->applyCrossFieldValidation($validator, $request);
        $validated = $validator->validate();

        try {
            DB::transaction(function () use ($request, $ownerSubshopId, $validated, $loanProduct) {
                $loanProduct->update([
                    'name' => $validated['name'],
                    'code' => $validated['code'],
                    'description' => $validated['description'] ?? null,
                    'loan_product_type_id' => $validated['loan_product_type_id'],
                    'interest_method_id' => $validated['interest_method_id'],
                    'interest_cycle_id' => $validated['interest_cycle_id'],
                    'repayment_frequency_id' => $validated['repayment_frequency_id'],
                    'default_installments' => $validated['default_installments'] ?? null,
                    'min_installments' => $validated['min_installments'] ?? null,
                    'max_installments' => $validated['max_installments'] ?? null,
                    'default_loan_amount' => $validated['default_loan_amount'] ?? null,
                    'supports_collateral' => $request->boolean('supports_collateral'),
                    'requires_approval' => $request->boolean('requires_approval'),
                    'is_active' => $request->boolean('is_active'),
                    'is_visible' => $request->boolean('is_visible'),
                ]);

                LoanProductRules::updateOrCreate(
                    ['subshop_id' => $ownerSubshopId, 'loan_product_id' => $loanProduct->id],
                    [
                        'min_age' => $validated['min_age'] ?? null,
                        'max_age' => $validated['max_age'] ?? null,
                        'min_membership_days' => $validated['min_membership_days'] ?? null,
                        'requires_active_savings' => $request->boolean('requires_active_savings'),
                        'min_savings_balance' => $validated['min_savings_balance'] ?? null,
                        'loan_to_savings_ratio' => $validated['loan_to_savings_ratio'] ?? null,
                        'min_loan_amount' => $validated['min_loan_amount'] ?? null,
                        'max_loan_amount' => $validated['max_loan_amount'] ?? null,
                        'max_active_loans' => $validated['max_active_loans'] ?? null,
                        'min_installments' => $validated['min_installments'] ?? null,
                        'max_installments' => $validated['max_installments'] ?? null,
                        'grace_period_days' => $validated['grace_period_days'] ?? 0,
                        'requires_security_deposit' => $request->boolean('requires_security_deposit'),
                        'requires_collateral' => $request->boolean('requires_collateral'),
                        'min_collateral_coverage_ratio' => $validated['min_collateral_coverage_ratio'] ?? null,
                        'min_interest_rate' => $validated['min_interest_rate'] ?? null,
                        'max_interest_rate' => $validated['max_interest_rate'] ?? null,
                        'allow_interest_override' => $request->boolean('allow_interest_override'),
                        'penalty_start_day' => $validated['penalty_start_day'] ?? 0,
                        'auto_apply_penalty' => $request->boolean('auto_apply_penalty'),
                        'allow_top_up' => $request->boolean('allow_top_up'),
                        'min_repayment_ratio_for_topup' => $validated['min_repayment_ratio_for_topup'] ?? null,
                        'allow_restructure' => $request->boolean('allow_restructure'),
                        'requires_guarantor' => $request->boolean('requires_guarantor'),
                        'manual_override_allowed' => $request->boolean('manual_override_allowed'),
                        'is_active' => true,
                    ]
                );

                LoanProductCashConfigs::updateOrCreate(
                    ['subshop_id' => $ownerSubshopId, 'loan_product_id' => $loanProduct->id],
                    [
                        'deposit_requirement' => 'none',
                        'deposit_value' => null,
                        'deposit_basis' => null,
                        'use_customer_savings' => false,
                        'lock_period_days' => null,
                        'allow_withdrawal_during_loan' => false,
                        'is_refundable' => false,
                        'apply_on_default' => false,
                        'is_active' => true,
                    ]
                );

                LoanProductAccounts::updateOrCreate(
                    ['subshop_id' => $ownerSubshopId, 'loan_product_id' => $loanProduct->id],
                    [
                        'principal_account_id' => $validated['principal_account_id'],
                        'customer_savings_control_account_id' => $validated['customer_savings_control_account_id'] ?? null,
                        'security_deposit_control_account_id' => $validated['security_deposit_control_account_id'] ?? null,
                        'interest_receivable_account_id' => $validated['interest_receivable_account_id'],
                        'interest_income_account_id' => $validated['interest_income_account_id'],
                        'penalty_receivable_account_id' => $validated['penalty_receivable_account_id'],
                        'penalty_income_account_id' => $validated['penalty_income_account_id'],
                        'fee_income_account_id' => $validated['fee_income_account_id'] ?? null,
                        'customer_savings_account_id' => $validated['customer_savings_account_id'] ?? null,
                        'customer_security_deposit_account_id' => $validated['customer_security_deposit_account_id'] ?? null,
                        'write_off_expense_account_id' => $validated['write_off_expense_account_id'],
                        'is_active' => true,
                    ]
                );

                LoanProductFees::query()
                    ->where('subshop_id', $ownerSubshopId)
                    ->where('loan_product_id', $loanProduct->id)
                    ->delete();

                $fees = $request->input('fees_config', []);
                if (is_array($fees)) {
                    foreach ($fees as $feeRow) {
                        $loanFeeId = $feeRow['loan_fee_id'] ?? null;
                        if (!$loanFeeId) {
                            continue;
                        }

                        LoanProductFees::create([
                            'subshop_id' => $ownerSubshopId,
                            'loan_product_id' => $loanProduct->id,
                            'loan_fee_id' => $loanFeeId,
                            'charge_event' => 'disbursement',
                            'payment_method' => 'upfront',
                            'auto_apply' => true,
                            'max_applications' => null,
                            'is_waivable' => false,
                            'is_mandatory' => true,
                            'is_active' => true,
                        ]);
                    }
                }

                LoanProductPenalties::query()
                    ->where('subshop_id', $ownerSubshopId)
                    ->where('loan_product_id', $loanProduct->id)
                    ->delete();

                $penalties = $request->input('penalties_config', []);
                if (is_array($penalties)) {
                    foreach ($penalties as $penRow) {
                        $loanPenaltyId = $penRow['loan_penalty_id'] ?? null;
                        if (!$loanPenaltyId) {
                            continue;
                        }

                        LoanProductPenalties::create([
                            'subshop_id' => $ownerSubshopId,
                            'loan_product_id' => $loanProduct->id,
                            'loan_penalty_id' => $loanPenaltyId,
                            'grace_days_override' => isset($penRow['grace_days_override']) && $penRow['grace_days_override'] !== ''
                                ? (int) $penRow['grace_days_override']
                                : null,
                            'auto_apply' => array_key_exists('auto_apply', $penRow) ? !empty($penRow['auto_apply']) : true,
                            'max_applications' => isset($penRow['max_applications']) && $penRow['max_applications'] !== ''
                                ? (int) $penRow['max_applications']
                                : null,
                            'is_active' => true,
                        ]);
                    }
                }

                LoanProductApprovalLevels::query()
                    ->where('subshop_id', $ownerSubshopId)
                    ->where('loan_product_id', $loanProduct->id)
                    ->delete();

                if ($request->boolean('requires_approval')) {
                    $approvalLevels = $request->input('approval_levels', []);
                    if (is_array($approvalLevels)) {
                        foreach ($approvalLevels as $lvlRow) {
                            $order = $lvlRow['level_order'] ?? null;
                            $roleId = $lvlRow['role_id'] ?? null;
                            if (!$order || !$roleId) {
                                continue;
                            }

                            LoanProductApprovalLevels::create([
                                'subshop_id' => $ownerSubshopId,
                                'loan_product_id' => $loanProduct->id,
                                'level_order' => (int) $order,
                                'role_id' => (string) $roleId,
                                'min_loan_amount' => $lvlRow['min_loan_amount'] ?? null,
                                'max_loan_amount' => $lvlRow['max_loan_amount'] ?? null,
                                'mandatory' => !empty($lvlRow['mandatory']),
                                'can_override_rules' => !empty($lvlRow['can_override_rules']),
                                'can_reject' => array_key_exists('can_reject', $lvlRow) ? !empty($lvlRow['can_reject']) : true,
                                'is_active' => true,
                            ]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to update loan product', [
                'subshop_id' => $subshopId,
                'loan_product_id' => $loanProduct->id,
                'exception' => $e,
            ]);
            return back()->withInput()->with('error', 'Failed to update loan product. Please review the form and try again.');
        }

        return redirect()->route('loans.loan_products.index')->with('success', 'Loan product updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('dashboard')->with('error', 'Subshop session not found. Please login again.');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = $this->getShopSubshopIds($subshop);

        $loanProduct = LoanProducts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->findOrFail($id);

        try {
            $loanProduct->delete();
        } catch (\Throwable $e) {
            Log::error('Failed to delete loan product', [
                'subshop_id' => $subshopId,
                'loan_product_id' => $loanProduct->id,
                'exception' => $e,
            ]);
            return back()->with('error', 'Failed to delete loan product. Please try again.');
        }

        return redirect()->route('loans.loan_products.index')->with('success', 'Loan product deleted successfully.');
    }
}
