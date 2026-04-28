 @extends('adminlte::page')

@php($isEdit = isset($loanProduct))

@section('title', ($isEdit ? 'Edit Loan Product - ' : 'Create Loan Product - ') . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-cogs"></i> {{ $isEdit ? 'Edit Loan Product' : 'Create Loan Product' }}</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-cogs"></i> {{ $isEdit ? 'Edit Loan Product' : 'Create Loan Product' }}</h1>
                <p class="mb-0 text-light">{{ $isEdit ? 'Editing product for:' : 'Creating product for:' }} <strong>{{ $subshop->name }}</strong></p>
            </div>

            <a href="{{ route('loans.loan_products.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i>
                    Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('loans.loans_settings.index') }}">Loans Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('loans.loan_products.index') }}">Loan Products</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $isEdit ? 'Edit' : 'Create' }}</li>
        </ol>
    </nav>
</div>
@stop

@section('content')
<div class="container-fluid">
    @if($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @php($loanProductRules = $loanProductRules ?? null)
    @php($loanProductCashConfig = $loanProductCashConfig ?? null)
    <form method="POST" action="{{ $isEdit ? route('loans.loan_products.update', $loanProduct->id ?? 0) : route('loans.loan_products.store') }}" id="loanProductForm">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">

        <div class="row">

            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-info-circle mr-1"></i> Basic Product Information
                    </div>
                    <div class="card-body">
                        <div class="card card-outline card-secondary collapsed-card mb-3">
                            <div class="card-header p-2">
                                <h3 class="card-title text-muted">Information &amp; Guidelines (Click + to expand)</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-2">Use this section to define the loan product identity and how it will appear to staff during appraisal and disbursement.</p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Mandatory:</strong> Product Name and Product Code. Use a consistent naming standard and a unique code to avoid confusion across branches.</li>
                                    <li><strong>Optional:</strong> Description. Use it to clarify target clients, purpose, and any internal notes for credit staff.</li>
                                    <li><strong>Status:</strong> Active controls whether the product can be used. Visible controls whether it appears in common selection lists (useful for phasing out products).</li>
                                    <li><strong>Operational flags:</strong> Supports Collateral and Requires Approval should match your credit policy and approval workflow.</li>
                                </ul>
                                <p class="mb-0"><strong>Business impact:</strong> Incorrect product setup can lead to approving loans under the wrong policy, misrouting approvals, or creating inconsistent reporting for portfolio analysis.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="name">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                value="{{ old('name', $isEdit ? $loanProduct->name : '') }}">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="code">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code" name="code" required maxlength="20"
                                    placeholder="e.g., SL01"
                                    value="{{ old('code', $isEdit ? $loanProduct->code : '') }}"
                                    {{ $isEdit ? 'readonly' : '' }}>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="loan_type">Product Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="loan_product_type_id" name="loan_product_type_id"
                                    required>
                                    <option value="">Select Product Type</option>
                                    @foreach($loanProductTypes as $lp)
                                    <option value="{{ $lp->id }}"
                                        {{ (string)old('loan_product_type_id', $isEdit ? $loanProduct->loan_product_type_id : '') === (string)$lp->id ? 'selected' : '' }}>
                                        {{ $lp->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                placeholder="Optional description">{{ old('description', $isEdit ? $loanProduct->description : '') }}</textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="supports_collateral"
                                        name="supports_collateral" value="1"
                                        {{ old('supports_collateral', $isEdit ? ($loanProduct->supports_collateral ? '1' : '') : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="supports_collateral">Supports
                                        Collateral</label>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="requires_approval"
                                        name="requires_approval" value="1"
                                        {{ old('requires_approval', $isEdit ? ($loanProduct->requires_approval ? '1' : '') : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="requires_approval">Requires
                                        Approval</label>
                                </div>
                            </div>

                            <div class="form-group col-md-4">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active"
                                        value="1"
                                        {{ old('is_active', $isEdit ? ($loanProduct->is_active ? '1' : '') : '1') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="form-group col-md-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_visible"
                                        name="is_visible" value="1"
                                        {{ old('is_visible', $isEdit ? ($loanProduct->is_visible ? '1' : '') : '1') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_visible">Visible</label>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


        </div>
        <div class="row">

            <div class="col-lg-12" id="interestTenureCard">
                <div class="card mb-3">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-percent mr-1"></i> Interest & Repayment / Tenure
                    </div>
                    <div class="card-body">
                        <div class="card card-outline card-secondary collapsed-card mb-3">
                            <div class="card-header p-2">
                                <h3 class="card-title text-muted">Information &amp; Guidelines (Click + to expand)</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-2">This section controls how interest is calculated and how clients will repay the loan over time.</p>
                                <p class="mb-2"><strong>Loan term &amp; repayment frequency</strong></p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Mandatory:</strong> Repayment Frequency. Choose what matches the client cash-flow (weekly, bi-weekly, monthly, etc.).</li>
                                    <li><strong>Recommended:</strong> Min and Max Installments to enforce realistic tenures and prevent over-stretching.</li>
                                    <li><strong>Optional:</strong> Default Installments and Default Loan Amount. Use these as defaults for faster data entry—staff can adjust during appraisal.</li>
                                </ul>
                                <p class="mb-2"><strong>Interest configuration</strong></p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Mandatory:</strong> Interest Method, Interest Cycle, and Default Interest Rate.</li>
                                    <li><strong>Optional:</strong> Min/Max Interest Rate. Use these to guide pricing and prevent out-of-policy rates.</li>
                                </ul>
                                <p class="mb-0"><strong>Business impact:</strong> Wrong interest method/cycle or repayment frequency can cause incorrect schedules, client disputes, and unreliable portfolio yield reporting.</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="interest_method_id">Interest Method <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="interest_method_id" name="interest_method_id" required>
                                    <option value="">Select method</option>
                                    @foreach($interestMethods as $m)
                                    <option value="{{ $m->id }}"
                                        {{ (string)old('interest_method_id', $isEdit ? $loanProduct->interest_method_id : '') === (string)$m->id ? 'selected' : '' }}>
                                        {{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="interest_cycle_id">Interest Cycle <span class="text-danger">*</span></label>
                                <select class="form-control" id="interest_cycle_id" name="interest_cycle_id" required>
                                    <option value="">Select cycle</option>
                                    @foreach($interestCycles as $c)
                                    <option value="{{ $c->id }}"
                                        {{ (string)old('interest_cycle_id', $isEdit ? $loanProduct->interest_cycle_id : '') === (string)$c->id ? 'selected' : '' }}>
                                        {{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="repayment_frequency_id">Repayment Frequency <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="repayment_frequency_id" name="repayment_frequency_id"
                                    required>
                                    <option value="">Select frequency</option>
                                    @foreach($repaymentFrequencies as $f)
                                    <option value="{{ $f->id }}"
                                        {{ (string)old('repayment_frequency_id', $isEdit ? $loanProduct->repayment_frequency_id : '') === (string)$f->id ? 'selected' : '' }}>
                                        {{ $f->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="default_installments">Default Installments</label>
                                <input type="number" class="form-control" id="default_installments"
                                    name="default_installments" min="1"
                                    value="{{ old('default_installments', $isEdit ? $loanProduct->default_installments : '') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="min_installments">Min Installments</label>
                                <input type="number" class="form-control" id="min_installments" name="min_installments"
                                    min="1"
                                    value="{{ old('min_installments', $isEdit ? $loanProduct->min_installments : '') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="max_installments">Max Installments</label>
                                <input type="number" class="form-control" id="max_installments" name="max_installments"
                                    min="1"
                                    value="{{ old('max_installments', $isEdit ? $loanProduct->max_installments : '') }}">
                            </div>
                        </div>
                        <div class="form-row">

                            <div class="form-group col-md-6">
                                <label for="min_interest_rate">Min Interest Rate (%)</label>
                                <input type="number" step="0.01" class="form-control" id="min_interest_rate"
                                    name="min_interest_rate" min="0" max="100" value="{{ old('min_interest_rate', $loanProductRules->min_interest_rate ?? '') }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="max_interest_rate">Max Interest Rate (%)</label>
                                <input type="number" step="0.01" class="form-control" id="max_interest_rate"
                                    name="max_interest_rate" min="0" max="100" value="{{ old('max_interest_rate', $loanProductRules->max_interest_rate ?? '') }}">
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <i class="fas fa-sliders-h mr-1"></i> Loan Amount Rules
                    </div>
                    <div class="card-body">
                        <div class="card card-outline card-secondary collapsed-card mb-3">
                            <div class="card-header p-2">
                                <h3 class="card-title text-muted">Information &amp; Guidelines (Click + to expand)</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-2">Define the allowed loan amount range for this product so loan officers do not disburse amounts outside policy.</p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Recommended:</strong> Minimum and Maximum Loan Amount. Set these based on your product design, client segment, and risk appetite.</li>
                                    <li><strong>Amount increments/step:</strong> If your institution uses fixed increments (for example in 10,000 steps), enforce it through internal procedures and approval checks.</li>
                                </ul>
                                <p class="mb-0"><strong>Business impact:</strong> Wrong ranges can lead to under-lending (client dissatisfaction) or over-lending (higher default risk and poor portfolio quality).</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="min_loan_amount">Minimum Loan Amount</label>
                                <input type="number" step="0.01" class="form-control" id="min_loan_amount"
                                    name="min_loan_amount" min="0" value="{{ old('min_loan_amount', $loanProductRules->min_loan_amount ?? '') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="max_loan_amount">Maximum Loan Amount</label>
                                <input type="number" step="0.01" class="form-control" id="max_loan_amount"
                                    name="max_loan_amount" min="0" value="{{ old('max_loan_amount', $loanProductRules->max_loan_amount ?? '') }}">
                            </div>

                            <div class="form-group col-md-4">
                                <label for="default_loan_amount">Default Loan Amount</label>
                                <input type="number" step="0.01" class="form-control" id="default_loan_amount"
                                    name="default_loan_amount" min="0"
                                    value="{{ old('default_loan_amount', $isEdit ? $loanProduct->default_loan_amount : '') }}">
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card mb-3">

                    <div class="card-header bg-danger" style="">
                        <i class="fas fa-balance-scale mr-1"></i> Eligibility & Behavioral Rules
                    </div>
                    <div class="card-body">
                        <div class="card card-outline card-secondary collapsed-card mb-3">
                            <div class="card-header p-2">
                                <h3 class="card-title text-muted">Information &amp; Guidelines (Click + to expand)</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-2">Use these rules to enforce eligibility, protect the portfolio, and standardize decisions across branches.</p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Eligibility (Optional but recommended):</strong> Min/Max Age, Min Membership Days, and Max Active Loans help prevent lending to clients outside policy.</li>
                                    <li><strong>Requires Active Savings:</strong> When enabled, the client must have an active savings account. Set a <strong>Minimum Savings Balance</strong> to protect liquidity and encourage discipline.</li>
                                    <li><strong>Loan to Savings Ratio (Optional):</strong> Limits exposure based on savings. A lower ratio is more conservative.</li>
                                </ul>
                                <p class="mb-2"><strong>Grace period &amp; penalties</strong></p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Grace Period Days:</strong> Number of days past due before penalties are considered. Align with your arrears management policy.</li>
                                    <li><strong>Penalty Start Day:</strong> Day count from due date when penalties start. Use a clear, consistent rule across products.</li>
                                    <li><strong>Auto Apply Penalty:</strong> If enabled, penalties apply automatically according to configured rules—ensure staff understand the operational impact before switching it on.</li>
                                </ul>
                                <p class="mb-2"><strong>Top-up &amp; restructure rules</strong></p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Revolving vs non-revolving:</strong> Revolving products typically do not follow fixed installments; confirm this matches your product design.</li>
                                    <li><strong>Minimum Repayment Ratio for Top-Up:</strong> Prevents top-ups before the client demonstrates repayment capacity.</li>
                                    <li><strong>Allow Restructure:</strong> Only enable if your institution has clear governance and documentation requirements for restructures.</li>
                                </ul>
                                <p class="mb-2"><strong>Collateral, guarantors, and overrides</strong></p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Requires Guarantor:</strong> Use for higher-risk client segments or specific products. Ensure staff follow the required documentation process.</li>
                                    <li><strong>Security Deposit &amp; Collateral Coverage:</strong> Configure under the Collateral Rules section when the product supports collateral.</li>
                                    <li><strong>Manual Override Allowed:</strong> If enabled, staff may approve exceptions; apply strict approval controls and audit trails.</li>
                                </ul>
                                <p class="mb-0"><strong>Business impact:</strong> Weak or inconsistent rules can lead to policy exceptions, higher arrears, and unfair lending decisions across clients.</p>
                            </div>
                        </div>
                        <h6 class="mb-2">Eligibility</h6>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="min_age">Min Age</label>
                                <input type="number" class="form-control" id="min_age" name="min_age" min="0"
                                    value="{{ old('min_age', $loanProductRules->min_age ?? '') }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="max_age">Max Age</label>
                                <input type="number" class="form-control" id="max_age" name="max_age" min="0"
                                    value="{{ old('max_age', $loanProductRules->max_age ?? '') }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="min_membership_days">Min Membership Days</label>
                                <input type="number" class="form-control" id="min_membership_days" name="min_membership_days" min="0"
                                    value="{{ old('min_membership_days', $loanProductRules->min_membership_days ?? '') }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="max_active_loans">Max Active Loans</label>
                                <input type="number" class="form-control" id="max_active_loans" name="max_active_loans" min="0"
                                    value="{{ old('max_active_loans', $loanProductRules->max_active_loans ?? 1) }}">
                            </div>
                        </div>

                        <!-- <hr>
                        <h6 class="mb-2">Savings Requirements</h6>
                        <div class="form-row align-items-center">
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="requires_active_savings" name="requires_active_savings" value="1"
                                        {{ old('requires_active_savings', ($loanProductRules->requires_active_savings ?? false) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="requires_active_savings">Requires Active Savings</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row savings-rules-fields" style="display:none;">
                            <div class="form-group col-md-4">
                                <label for="min_savings_balance">Min Savings Balance</label>
                                <input type="number" step="0.01" class="form-control" id="min_savings_balance" name="min_savings_balance" min="0"
                                    value="{{ old('min_savings_balance', $loanProductRules->min_savings_balance ?? '') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="loan_to_savings_ratio">Loan to Savings Ratio</label>
                                <input type="number" step="0.01" class="form-control" id="loan_to_savings_ratio" name="loan_to_savings_ratio" min="0"
                                    value="{{ old('loan_to_savings_ratio', $loanProductRules->loan_to_savings_ratio ?? '') }}">
                                <small class="form-text text-muted">Example: 2.00 means loan <= savings x 2.</small>
                            </div>
                        </div> -->

                        <hr>
                        <h6 class="mb-2">Repayment & Penalty Behavior</h6>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="grace_period_days">Grace Period Days</label>
                                <input type="number" class="form-control" id="grace_period_days" name="grace_period_days" min="0" required
                                    value="{{ old('grace_period_days', $loanProductRules->grace_period_days ?? 0) }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="penalty_start_day">Penalty Start Day</label>
                                <input type="number" class="form-control" id="penalty_start_day" name="penalty_start_day" min="0"
                                    value="{{ old('penalty_start_day', $loanProductRules->penalty_start_day ?? 1) }}">
                            </div>
                            <div class="form-group col-md-3">
                                <div class="custom-control custom-switch mt-4">
                                    <input type="checkbox" class="custom-control-input" id="auto_apply_penalty" name="auto_apply_penalty" value="1"
                                        {{ old('auto_apply_penalty', ($loanProductRules->auto_apply_penalty ?? true) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="auto_apply_penalty">Auto Apply Penalty</label>
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <div class="custom-control custom-switch mt-4">
                                    <input type="checkbox" class="custom-control-input" id="allow_interest_override" name="allow_interest_override" value="1"
                                        {{ old('allow_interest_override', ($loanProductRules->allow_interest_override ?? false) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="allow_interest_override">Allow Interest Override</label>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-2">Top-up & Restructure</h6>
                        <div class="form-row">
                            <div class="form-group col-md-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="allow_top_up"
                                        name="allow_top_up" value="1" 
                                          {{ old('allow_top_up', ($loanProductRules->allow_top_up ?? false) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="allow_top_up">Allow Top-Up</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row top-up-section" style="display: none;">
                            <div class="form-group col-md-4">
                                <label for="min_repayment_ratio_for_topup">Min Repayment Ratio for Top-Up (%)</label>
                                <input type="number" step="0.01" class="form-control" id="min_repayment_ratio_for_topup" name="min_repayment_ratio_for_topup" min="0" max="100"
                                    value="{{ old('min_repayment_ratio_for_topup', $loanProductRules->min_repayment_ratio_for_topup ?? '') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-switch mt-4">
                                    <input type="checkbox" class="custom-control-input" id="allow_restructure" name="allow_restructure" value="1"
                                        {{ old('allow_restructure', ($loanProductRules->allow_restructure ?? false) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="allow_restructure">Allow Restructure</label>
                                </div>
                            </div>
                        </div>


                        <hr>
                        <h6 class="mb-2">Guarantor</h6>

                        <div class="rules-collateral-fields">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="requires_guarantor" name="requires_guarantor" value="1"
                                            {{ old('requires_guarantor', ($loanProductRules->requires_guarantor ?? false) ? '1' : '') ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="requires_guarantor">Requires Guarantor</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <!-- <h6 class="mb-2">Governance</h6>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="manual_override_allowed" name="manual_override_allowed" value="1"
                                        {{ old('manual_override_allowed', ($loanProductRules->manual_override_allowed ?? false) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="manual_override_allowed">Manual Override Allowed</label>
                                </div>
                            </div>
                        </div> -->

                        <h6 class="mb-2">Security Deposit</h6>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="requires_security_deposit" name="requires_security_deposit" value="1"
                                        {{ old('requires_security_deposit', ($loanProductRules->requires_security_deposit ?? false) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="requires_security_deposit">Requires Security Deposit</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" id="collateralRulesCard" >
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header bg-secondary" style="background:#f8f9fa;">
                        <i class="fas fa-hand-holding-usd mr-1"></i> Collateral Rules
                    </div>
                    <div class="card-body">
                        <div class="card card-outline card-secondary collapsed-card mb-3">
                            <div class="card-header p-2">
                                <h3 class="card-title text-muted">Information &amp; Guidelines (Click + to expand)</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-2">Configure collateral and security deposit requirements for this product. These settings affect loan eligibility checks and cash handling at origination/closure.</p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Security Deposit:</strong> If enabled, the product requires a security deposit at origination or as per policy.</li>
                                    <li><strong>Collateral Coverage:</strong> If enabled, you must set a minimum coverage percentage. This ensures collateral value meets policy before disbursement.</li>
                                </ul>
                                <p class="mb-0"><strong>Business impact:</strong> Incorrect settings can cause weak risk controls, delays in appraisal, or disputes at closure (refunds/returns).</p>
                            </div>
                        </div>


                        <h6 class="mb-2">Collateral Coverage</h6>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="requires_collateral" name="requires_collateral" value="1"
                                        {{ old('requires_collateral', ($loanProductRules->requires_collateral ?? false) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="requires_collateral">Requires Collateral</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row collateral-only" >
                            <div class="form-group col-md-4">
                                <label for="min_collateral_coverage_ratio">Min Collateral Coverage (%)</label>
                                <input type="number" step="0.01" class="form-control" id="min_collateral_coverage_ratio" name="min_collateral_coverage_ratio" min="0"
                                    value="{{ old('min_collateral_coverage_ratio', $loanProductRules->min_collateral_coverage_ratio ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-wallet mr-1"></i> Accounting Mapping
                    </div>
                    <div class="card-body">
                        <div class="card card-outline card-secondary collapsed-card mb-3">
                            <div class="card-header p-2">
                                <h3 class="card-title text-muted">Information &amp; Guidelines (Click + to expand)</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-2">Map this loan product to the correct accounts so postings are accurate for financial statements, aging reports, and income recognition.</p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Mandatory:</strong> Principal, Interest Receivable, Interest Income, Penalty Receivable, Penalty Income, and Write-off Expense.</li>
                                    <li><strong>Optional:</strong> Fee Income (if you track fees separately), Customer Savings Control (if savings rules are used), and Security Deposit Control (if security deposit is used).</li>
                                </ul>
                                <p class="mb-0"><strong>Business impact:</strong> Wrong account mapping causes misstatements in reports, inaccurate receivables, and reconciliation issues during audits.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="principal_account_id">Principal Account (Asset) <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="principal_account_id" name="principal_account_id" required>
                                <option value="">Select account</option>
                                @foreach($assetsAccounts as $a)
                                <option value="{{ $a->id }}" {{ (string)old('principal_account_id', $loanProductAccounts?->principal_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="interest_receivable_account_id">Interest Receivable (Asset) <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="interest_receivable_account_id"
                                    name="interest_receivable_account_id" required>
                                    <option value="">Select account</option>
                                    @foreach($assetsAccounts as $a)
                                    <option value="{{ $a->id }}" {{ (string)old('interest_receivable_account_id', $loanProductAccounts?->interest_receivable_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="interest_income_account_id">Interest Income (Income) <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="interest_income_account_id"
                                    name="interest_income_account_id" required>
                                    <option value="">Select account</option>
                                    @foreach($incomeAccounts as $a)
                                    <option value="{{ $a->id }}" {{ (string)old('interest_income_account_id', $loanProductAccounts?->interest_income_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="penalty_receivable_account_id">Penalty Receivable (Asset) <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="penalty_receivable_account_id"
                                    name="penalty_receivable_account_id" required>
                                    <option value="">Select account</option>
                                    @foreach($assetsAccounts as $a)
                                    <option value="{{ $a->id }}" {{ (string)old('penalty_receivable_account_id', $loanProductAccounts?->penalty_receivable_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="penalty_income_account_id">Penalty Income (Income) <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="penalty_income_account_id"
                                    name="penalty_income_account_id" required>
                                    <option value="">Select account</option>
                                    @foreach($incomeAccounts as $a)
                                    <option value="{{ $a->id }}" {{ (string)old('penalty_income_account_id', $loanProductAccounts?->penalty_income_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="fee_income_account_id">Fee Income (Income) <span
                                        class="text-danger">*</span></label></label>
                                <select class="form-control" id="fee_income_account_id" name="fee_income_account_id" required>
                                    <option value="">Select account (optional)</option>
                                    @foreach($incomeAccounts as $a)
                                    <option value="{{ $a->id }}" {{ (string)old('fee_income_account_id', $loanProductAccounts?->fee_income_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="write_off_expense_account_id">Write-off Expense (Expense) <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="write_off_expense_account_id"
                                    name="write_off_expense_account_id" required>
                                    <option value="">Select account</option> 
                                    @foreach($expensesAccounts as $a)
                                        <option value="{{ $a->id }}" {{ (string)old('write_off_expense_account_id', $loanProductAccounts?->write_off_expense_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="customer_savings_control_account_id">Customer Savings Control
                                    (Liability) <span class="text-danger">*</span></label>
                                <select class="form-control" id="customer_savings_control_account_id"
                                    name="customer_savings_control_account_id" required>
                                    <option value="">Select account</option>
                                    @foreach($liabilityAccounts as $a)
                                    <option value="{{ $a->id }}" {{ (string)old('customer_savings_control_account_id', $loanProductAccounts?->customer_savings_control_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="security_deposit_control_account_id">Security Deposit Control
                                    (Liability) <span class="text-danger">*</span></label>
                                <select class="form-control" id="security_deposit_control_account_id"
                                    name="security_deposit_control_account_id" required>
                                    <option value="">Select account</option>
                                    @foreach($liabilityAccounts as $a)
                                    <option value="{{ $a->id }}" {{ (string)old('security_deposit_control_account_id', $loanProductAccounts?->security_deposit_control_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="customer_savings_account_id">Customer Savings Account (Liability) <span class="text-danger">*</span></label></label>
                                <select class="form-control" id="customer_savings_account_id" name="customer_savings_account_id" required>
                                    <option value="">Select account</option>
                                    @foreach($liabilityAccounts as $a)
                                    <option value="{{ $a->id }}" {{ (string)old('customer_savings_account_id', $loanProductAccounts?->customer_savings_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="customer_security_deposit_account_id">Customer Security Deposit Account (Liability) <span class="text-danger">*</span></label>
                                <select class="form-control" id="customer_security_deposit_account_id" name="customer_security_deposit_account_id" required>
                                    <option value="">Select account</option>
                                    @foreach($liabilityAccounts as $a)
                                    <option value="{{ $a->id }}" {{ (string)old('customer_security_deposit_account_id', $loanProductAccounts?->customer_security_deposit_account_id ?? '') === (string)$a->id ? 'selected' : '' }}>{{ $a->account_code }}: {{ $a->account_name }} ({{ $a->accountClass->name }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-list mr-1"></i> Default Fees & Penalties
                    </div>
                    <div class="card-body">
                        <div class="card card-outline card-secondary collapsed-card mb-3">
                            <div class="card-header p-2">
                                <h3 class="card-title text-muted">Information &amp; Guidelines (Click + to expand)</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-2">Use this section to define which fees and penalties apply by default to loans under this product.</p>
                                <p class="mb-2"><strong>Loan fees configuration (Optional)</strong></p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Fee selection:</strong> Select the fee(s) you want to apply for this product.</li>
                                    <li><strong>How it applies:</strong> The system applies configured product fees during loan creation to keep setup simple and consistent.</li>
                                </ul>
                                <p class="mb-2"><strong>Penalty configuration (Optional)</strong></p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Grace Days Override:</strong> Overrides the default grace period for this penalty, if needed.</li>
                                    <li><strong>Auto Apply:</strong> Applies the penalty automatically when the client is past due based on the configured behavior.</li>
                                    <li><strong>Maximum Applications:</strong> Caps how many times the penalty can be applied to avoid excessive charges.</li>
                                </ul>
                                <p class="mb-0"><strong>Business impact:</strong> Incorrect fees or penalties create client disputes, compliance risk, and inaccurate income reporting.</p>
                            </div>
                        </div>

                        <hr>
                        <div class="mt-2">
                            <h6>Fees Configuration (Optional)</h6>
                            @php($feesConfig = old('fees_config', ($isEdit ? ($loanProductFees ?? collect())->values()->map(function($f){
                                return [
                                    'loan_fee_id' => $f->loan_fee_id,
                                ];
                            })->toArray() : [])))
                            @php($feesConfig = is_array($feesConfig) && count($feesConfig) ? $feesConfig : [[]])
                            <div id="feesRepeater">
                                @foreach($feesConfig as $i => $row)
                                    <div class="border rounded p-2 mb-2 fee-config" data-index="{{ $i }}">
                                        <div class="form-row">

                                            <div class="form-group col-md-4">
                                                <label>Fee Name</label>
                                                <select class="form-control" id="loan_fee_id_{{ $i }}" name="fees_config[{{ $i }}][loan_fee_id]" form="loanProductForm">
                                                    <option value="">Select Loan Feee</option>
                                                    @foreach($loanFees as $fee)
                                                    <option value="{{ $fee->id }}" {{ (string)($row['loan_fee_id'] ?? '') === (string)$fee->id ? 'selected' : '' }}>
                                                        {{ $fee->name }}: Tsh {{ $fee->amount }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-12 text-right">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-fee-row" {{ $i === 0 ? 'disabled' : '' }}>Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addFeeRow"><i class="fas fa-plus"></i> Add Fee</button>
                        </div>





                        <hr>
                        <div class="mt-2">
                            <h6>Penalties Configuration (Optional)</h6>
                            @php($penaltiesConfig = old('penalties_config', ($isEdit ? ($loanProductPenalties ?? collect())->values()->map(function($p){
                                return [
                                    'loan_penalty_id' => $p->loan_penalty_id,
                                    'max_applications' => $p->max_applications,
                                    'grace_days_override' => $p->grace_days_override,
                                    'auto_apply' => $p->auto_apply,
                                ];
                            })->toArray() : [])))
                            @php($penaltiesConfig = is_array($penaltiesConfig) && count($penaltiesConfig) ? $penaltiesConfig : [[]])
                            <div id="penaltiesRepeater">
                                @foreach($penaltiesConfig as $i => $row)
                                    <div class="border rounded p-2 mb-2 penalty-config" data-index="{{ $i }}">
                                        <div class="form-row">
                                            <div class="form-group col-md-4">
                                                <label>Penalty Type</label>
                                                <select class="form-control" id="loan_penalty_id_{{ $i }}" name="penalties_config[{{ $i }}][loan_penalty_id]" form="loanProductForm">
                                                    <option value="">Select Loan Penalty</option>
                                                    @foreach($loanPenalties as $lp)
                                                    <option value="{{ $lp->id }}" {{ (string)($row['loan_penalty_id'] ?? '') === (string)$lp->id ? 'selected' : '' }}>
                                                        {{ $lp->name }}: Tsh {{ $lp->amount }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="form-group col-md-4">
                                                <label>Max Applications</label>
                                                <input type="number" class="form-control" name="penalties_config[{{ $i }}][max_applications]" form="loanProductForm" min="0" value="{{ $row['max_applications'] ?? '' }}">
                                            </div>

                                            <div class="form-group col-md-4">
                                                <label>Grace Period Override</label>
                                                <input type="number" class="form-control" name="penalties_config[{{ $i }}][grace_days_override]" form="loanProductForm" min="0" value="{{ $row['grace_days_override'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-3">
                                                <div class="custom-control custom-switch mt-4">
                                                    <input type="checkbox" class="custom-control-input" id="pen_auto_apply_{{ $i }}" name="penalties_config[{{ $i }}][auto_apply]" form="loanProductForm" value="1" {{ array_key_exists('auto_apply', $row) ? (!empty($row['auto_apply']) ? 'checked' : '') : 'checked' }}>
                                                    <label class="custom-control-label" for="pen_auto_apply_{{ $i }}">Auto Apply</label>
                                                </div>
                                            </div>
                                            <div class="form-group col-md-9 text-right">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-penalty-config" {{ $i === 0 ? 'disabled' : '' }}>Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addPenaltyRow"><i class="fas fa-plus"></i> Add Penalty</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header" style="background:#f0f3f5;">
                        <i class="fas fa-user-check mr-1"></i> Approval Workflow
                    </div>
                    <div class="card-body">
                        <div class="card card-outline card-secondary collapsed-card mb-3">
                            <div class="card-header p-2">
                                <h3 class="card-title text-muted">Information &amp; Guidelines (Click + to expand)</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-2">Set the approval levels required for this product. Use this to enforce segregation of duties and appropriate oversight based on loan size and risk.</p>
                                <ul class="mb-2 pl-3">
                                    <li><strong>Mandatory:</strong> Level order and Approver Role. Define a clear sequence (Level 1, Level 2, etc.).</li>
                                    <li><strong>Optional:</strong> Min/Max Amount thresholds for each level. Use these to route larger exposures to senior roles.</li>
                                    <li><strong>Controls:</strong> Mandatory ensures the level cannot be skipped; Can Override Rules should be limited to senior roles with audit requirements.</li>
                                </ul>
                                <p class="mb-0"><strong>Business impact:</strong> Misconfigured approval levels can bypass controls, delay disbursement, or create non-compliance with internal governance.</p>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Approval Levels</h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addApprovalLevel"><i
                                    class="fas fa-plus"></i> Add Level</button>
                        </div>
                        <div id="approvalLevels">
                            @php($approvalConfig = old('approval_levels', ($isEdit ? ($loanProductApprovalLevels ?? collect())->values()->map(function($a){
                                return [
                                    'level_order' => $a->level_order,
                                    'role_id' => $a->role_id,
                                    'min_loan_amount' => $a->min_loan_amount,
                                    'max_loan_amount' => $a->max_loan_amount,
                                    'mandatory' => $a->mandatory,
                                    'can_override_rules' => $a->can_override_rules,
                                    'can_reject' => $a->can_reject,
                                ];
                            })->toArray() : [])))
                            @php($approvalConfig = is_array($approvalConfig) && count($approvalConfig) ? $approvalConfig : [[ 'level_order' => 1 ]])

                            @foreach($approvalConfig as $i => $row)
                                <div class="border rounded p-2 mb-2 approval-level" data-index="{{ $i }}">
                                    <div class="form-row">
                                        <div class="form-group col-md-2">
                                            <label>Level Order</label>
                                            <input type="number" class="form-control" name="approval_levels[{{ $i }}][level_order]" form="loanProductForm" min="1" value="{{ $row['level_order'] ?? ($i + 1) }}">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>Approver Role</label>
                                            <select name="approval_levels[{{ $i }}][role_id]" class="form-control" form="loanProductForm" required>
                                                <option value="">-- Select Role --</option>
                                                @foreach ($roles as $role)
                                                    @if($role->name !== 'Super Admin')
                                                        <option value="{{ $role->id }}" {{ (string)($row['role_id'] ?? '') === (string)$role->id ? 'selected' : '' }}>
                                                            {{ strtoupper($role->name) }}
                                                        </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label>Min Amount</label>
                                            <input type="number" step="0.01" class="form-control" name="approval_levels[{{ $i }}][min_loan_amount]" form="loanProductForm" min="0" value="{{ $row['min_loan_amount'] ?? '' }}">
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label>Max Amount</label>
                                            <input type="number" step="0.01" class="form-control" name="approval_levels[{{ $i }}][max_loan_amount]" form="loanProductForm" min="0" value="{{ $row['max_loan_amount'] ?? '' }}">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <div class="custom-control custom-switch mt-4">
                                                <input type="checkbox" class="custom-control-input" id="mandatory_{{ $i }}" name="approval_levels[{{ $i }}][mandatory]" form="loanProductForm" value="1" {{ array_key_exists('mandatory', $row) ? (!empty($row['mandatory']) ? 'checked' : '') : 'checked' }}>
                                                <label class="custom-control-label" for="mandatory_{{ $i }}">Mandatory</label>
                                            </div>
                                            <div class="custom-control custom-switch mt-2">
                                                <input type="checkbox" class="custom-control-input" id="can_override_{{ $i }}" name="approval_levels[{{ $i }}][can_override_rules]" form="loanProductForm" value="1" {{ !empty($row['can_override_rules']) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="can_override_{{ $i }}">Can Override
                                                    Rules</label>
                                            </div>
                                            <div class="custom-control custom-switch mt-2">
                                                <input type="checkbox" class="custom-control-input" id="can_reject_{{ $i }}" name="approval_levels[{{ $i }}][can_reject]" form="loanProductForm" value="1" {{ array_key_exists('can_reject', $row) ? (!empty($row['can_reject']) ? 'checked' : '') : 'checked' }}>
                                                <label class="custom-control-label" for="can_reject_{{ $i }}">Can Reject</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-approval-level" {{ $i === 0 ? 'disabled' : '' }}>Remove Level</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body d-flex justify-content-end">
                <a href="{{ route('loans.loan_products.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>
                    {{ $isEdit ? 'Update Product' : 'Create Product' }}</button>
            </div>
        </div>
    </form>

    <div class="modal fade" id="loanProductConfirmModal" tabindex="-1" role="dialog" aria-labelledby="loanProductConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loanProductConfirmModalLabel">Confirm Loan Product Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="loanProductConfirmSummary"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Edit</button>
                    <button type="button" class="btn btn-primary" id="confirmLoanProductSubmitBtn">
                        <i class="fas fa-check mr-1"></i> Confirm & Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
    
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
.card-header i {
    opacity: 0.9;
}

.custom-switch {
    user-select: none;
}

select[multiple] {
    min-height: 120px;
}

.card+.card {
    margin-top: .75rem;
}

.form-group>label {
    font-weight: 500;
}

.card-header {
    font-weight: 600;
}

/* .card {
    border-radius: .5rem;
} */

.card-header {
    border-top-left-radius: .5rem;
    border-top-right-radius: .5rem;
}

.card-body {
    padding-top: 1rem;
}

.card-header.bg-warning {
    background-color: #ffc107 !important;
}

.card-header.bg-warning.text-dark {
    color: #212529 !important;
}

.card-header.bg-success {
    background-color: #28a745 !important;
}

.card-header.bg-info {
    background-color: #17a2b8 !important;
}
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    let loanProductConfirmed = false;

    // Data stores for dynamic selects
    window.loanFeesData = @json($loanFees);
    window.loanPenaltiesData = @json($loanPenalties);
    window.approvalRolesData = @json($roles);

    function getMaxDataIndex($container, itemSelector) {
        let max = -1;
        $container.find(itemSelector).each(function() {
            const v = parseInt($(this).attr('data-index') || '-1', 10);
            if (!isNaN(v) && v > max) max = v;
        });
        return max;
    }

    function normalizeRepeaterIndexes($container, itemSelector, baseName) {
        const re = new RegExp('^' + baseName.replace(/[\[\]]/g, '\\$&') + '\\[(\\d+)\\]');
        $container.find(itemSelector).each(function(newIndex) {
            const $item = $(this);
            $item.attr('data-index', String(newIndex));
            $item.find('input[name], select[name], textarea[name]').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                if (!name) return;
                if (!re.test(name)) return;
                $field.attr('name', name.replace(re, baseName + '[' + newIndex + ']'));
            });
        });
    }

    function textOrDash(val) {
        if (val === undefined || val === null) return '-';
        const t = String(val).trim();
        return t === '' ? '-' : t;
    }

    function getInputVal(selector) {
        return textOrDash($(selector).val());
    }

    function getSelectText(selector) {
        const $el = $(selector);
        if ($el.length === 0) return '-';
        const txt = $el.find('option:selected').text();
        return textOrDash(txt);
    }

    function yesNo(selector) {
        const $el = $(selector);
        if ($el.length === 0) return '-';
        return $el.is(':checked') ? 'Yes' : 'No';
    }

    function buildTable(rows) {
        const body = rows
            .filter(r => r && r.label)
            .map(r => {
                const label = textOrDash(r.label);
                const value = textOrDash(r.value);
                return `<tr><th style="width:45%;">${label}</th><td>${value}</td></tr>`;
            })
            .join('');
        return `<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><tbody>${body}</tbody></table></div>`;
    }

    function buildRepeaterList($items, titleBuilder, detailsBuilder) {
        if ($items.length === 0) return '<div class="text-muted">-</div>';
        let html = '<div class="list-group">';
        $items.each(function(idx) {
            const title = textOrDash(titleBuilder($(this), idx));
            const details = detailsBuilder($(this), idx);
            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <strong>${title}</strong>
                        <span class="text-muted">#${idx + 1}</span>
                    </div>
                    <div class="mt-2">${details}</div>
                </div>`;
        });
        html += '</div>';
        return html;
    }

    function buildConfirmationSummaryHtml() {
        const basic = buildTable([
            { label: 'Product Name', value: getInputVal('#name') },
            { label: 'Code', value: getInputVal('#code') },
            { label: 'Product Type', value: getSelectText('#loan_product_type_id') },
            { label: 'Description', value: getInputVal('#description') },
            { label: 'Supports Collateral', value: yesNo('#supports_collateral') },
            { label: 'Requires Approval', value: yesNo('#requires_approval') },
            { label: 'Active', value: yesNo('#is_active') },
            { label: 'Visible', value: yesNo('#is_visible') }
        ]);

        const collateral = buildTable([
            { label: 'Collateral Required', value: yesNo('#requires_collateral') },
            { label: 'Minimum Collateral Coverage (%)', value: getInputVal('#min_collateral_coverage_ratio') }
        ]);

        const interest = buildTable([
            { label: 'Interest Method', value: getSelectText('#interest_method_id') },
            { label: 'Interest Cycle', value: getSelectText('#interest_cycle_id') },
            { label: 'Repayment Frequency', value: getSelectText('#repayment_frequency_id') },
            { label: 'Default Installments', value: getInputVal('#default_installments') },
            { label: 'Min Installments', value: getInputVal('#min_installments') },
            { label: 'Max Installments', value: getInputVal('#max_installments') },
            { label: 'Default Loan Amount', value: getInputVal('#default_loan_amount') },
            { label: 'Min Interest Rate (%)', value: getInputVal('#min_interest_rate') },
            { label: 'Max Interest Rate (%)', value: getInputVal('#max_interest_rate') },
            { label: 'Default Interest Rate (%)', value: getInputVal('#default_interest_rate') }
        ]);

        const amountRules = buildTable([
            { label: 'Minimum Loan Amount', value: getInputVal('#min_loan_amount') },
            { label: 'Maximum Loan Amount', value: getInputVal('#max_loan_amount') }
        ]);

        const eligibility = buildTable([
            { label: 'Min Age', value: getInputVal('#min_age') },
            { label: 'Max Age', value: getInputVal('#max_age') },
            { label: 'Min Membership Days', value: getInputVal('#min_membership_days') },
            { label: 'Max Active Loans', value: getInputVal('#max_active_loans') },
            { label: 'Requires Active Savings', value: yesNo('#requires_active_savings') },
            { label: 'Min Savings Balance', value: getInputVal('#min_savings_balance') },
            { label: 'Loan to Savings Ratio', value: getInputVal('#loan_to_savings_ratio') },
            { label: 'Grace Period Days', value: getInputVal('#grace_period_days') },
            { label: 'Penalty Start Day', value: getInputVal('#penalty_start_day') },
            { label: 'Auto Apply Penalty', value: yesNo('#auto_apply_penalty') },
            { label: 'Allow Interest Override', value: yesNo('#allow_interest_override') },
            { label: 'Allow Top-Up', value: yesNo('#allow_top_up') },
            { label: 'Min Repayment Ratio for Top-Up (%)', value: getInputVal('#min_repayment_ratio_for_topup') },
            { label: 'Allow Restructure', value: yesNo('#allow_restructure') },
            { label: 'Requires Guarantor', value: yesNo('#requires_guarantor') },
            { label: 'Manual Override Allowed', value: yesNo('#manual_override_allowed') }
        ]);

        const accounting = buildTable([
            { label: 'Principal Account (Asset)', value: getSelectText('#principal_account_id') },
            { label: 'Interest Receivable (Asset)', value: getSelectText('#interest_receivable_account_id') },
            { label: 'Interest Income (Income)', value: getSelectText('#interest_income_account_id') },
            { label: 'Penalty Receivable (Asset)', value: getSelectText('#penalty_receivable_account_id') },
            { label: 'Penalty Income (Income)', value: getSelectText('#penalty_income_account_id') },
            { label: 'Fee Income (Income)', value: getSelectText('#fee_income_account_id') },
            { label: 'Write-off Expense (Expense)', value: getSelectText('#write_off_expense_account_id') },
            { label: 'Customer Savings Control (optional)', value: getSelectText('#customer_savings_control_account_id') },
            { label: 'Security Deposit Control (optional)', value: getSelectText('#security_deposit_control_account_id') },
            { label: 'Customer Savings Account (optional)', value: getSelectText('#customer_savings_account_id') },
            { label: 'Customer Security Deposit Account (optional)', value: getSelectText('#customer_security_deposit_account_id') }
        ]);

        const feesHtml = buildRepeaterList(
            $('#feesRepeater .fee-config'),
            ($item) => {
                const sel = $item.find('select[name^="fees_config"][name$="[loan_fee_id]"]');
                const name = sel.length ? sel.find('option:selected').text() : '';
                return name || 'Fee';
            },
            ($item) => buildTable([])
        );

        const penaltiesHtml = buildRepeaterList(
            $('#penaltiesRepeater .penalty-config'),
            ($item) => {
                const sel = $item.find('select[name^="penalties_config"][name$="[loan_penalty_id]"]');
                const name = sel.length ? sel.find('option:selected').text() : '';
                return name || 'Penalty';
            },
            ($item) => buildTable([
                { label: 'Max Applications', value: textOrDash($item.find('input[name^="penalties_config"][name$="[max_applications]"]').val()) },
                { label: 'Grace Period Override', value: textOrDash($item.find('input[name^="penalties_config"][name$="[grace_days_override]"]').val()) },
                { label: 'Auto Apply', value: $item.find('input[name^="penalties_config"][name$="[auto_apply]"]').is(':checked') ? 'Yes' : 'No' }
            ])
        );

        const approvalsHtml = buildRepeaterList(
            $('#approvalLevels .approval-level'),
            ($item) => {
                const roleText = textOrDash($item.find('select[name^="approval_levels"][name$="[role_id]"] option:selected').text());
                const orderVal = textOrDash($item.find('input[name^="approval_levels"][name$="[level_order]"]').val());
                return `Level ${orderVal} - ${roleText}`;
            },
            ($item) => buildTable([
                { label: 'Min Amount', value: textOrDash($item.find('input[name^="approval_levels"][name$="[min_loan_amount]"]').val()) },
                { label: 'Max Amount', value: textOrDash($item.find('input[name^="approval_levels"][name$="[max_loan_amount]"]').val()) },
                { label: 'Mandatory', value: $item.find('input[name^="approval_levels"][name$="[mandatory]"]').is(':checked') ? 'Yes' : 'No' },
                { label: 'Can Override Rules', value: $item.find('input[name^="approval_levels"][name$="[can_override_rules]"]').is(':checked') ? 'Yes' : 'No' },
                { label: 'Can Reject', value: $item.find('input[name^="approval_levels"][name$="[can_reject]"]').is(':checked') ? 'Yes' : 'No' }
            ])
        );

        const showCollateral = $('#supports_collateral').is(':checked');
        const showApprovals = $('#requires_approval').is(':checked');

        const card = (title, inner) => `
            <div class="card mb-2">
                <div class="card-header"><strong>${title}</strong></div>
                <div class="card-body p-2">${inner}</div>
            </div>`;

        let html = '';
        html += card('Basic Product Information', basic);
        if (showCollateral) html += card('Collateral Rules', collateral);
        html += card('Interest & Repayment / Tenure', interest);
        html += card('Loan Amount Rules', amountRules);
        html += card('Eligibility & Behavioral Rules', eligibility);
        html += card('Accounting Mapping', accounting);
        html += card('Default Fees (Optional)', feesHtml);
        html += card('Penalties (Optional)', penaltiesHtml);
        if (showApprovals) html += card('Approval Workflow', approvalsHtml);

        return html;
    }

    $('#confirmLoanProductSubmitBtn').on('click', function() {
        loanProductConfirmed = true;
        $('#loanProductConfirmModal').modal('hide');
        $('#loanProductForm').submit();
    });

    $('#loanProductConfirmModal').on('hidden.bs.modal', function() {
        $('#loanProductConfirmSummary').empty();
    });

    // Form-level validation
    $('#loanProductForm').on('submit', function(e) {
        // Always normalize repeater indexes before any other logic so newly appended rows
        // have contiguous indexes and are included in the payload.
        normalizeRepeaterIndexes($('#feesRepeater'), '.fee-config', 'fees_config');
        normalizeRepeaterIndexes($('#penaltiesRepeater'), '.penalty-config', 'penalties_config');
        normalizeRepeaterIndexes($('#approvalLevels'), '.approval-level', 'approval_levels');

        if (!loanProductConfirmed) {
            e.preventDefault();
        }

        // Installments min/max
        const minI = parseInt($('#min_installments').val() || '0', 10);
        const maxI = parseInt($('#max_installments').val() || '0', 10);
        if (minI && maxI && minI > maxI) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validation',
                text: 'Min installments cannot be greater than Max installments.'
            });
            return false;
        }

        // Age min/max
        const minA = parseInt($('#min_age').val() || '0', 10);
        const maxA = parseInt($('#max_age').val() || '0', 10);
        if (minA && maxA && minA > maxA) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validation',
                text: 'Min age cannot be greater than Max age.'
            });
            return false;
        }

        // Default interest rate must be between min and max (if provided)
        const minR = parseFloat($('#min_interest_rate').val());
        const maxR = parseFloat($('#max_interest_rate').val());
        const defR = parseFloat($('#default_interest_rate').val());
        if (!isNaN(defR)) {
            if (!isNaN(minR) && defR < minR) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation',
                    text: 'Default interest rate cannot be less than the minimum interest rate.'
                });
                return false;
            }
            if (!isNaN(maxR) && defR > maxR) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation',
                    text: 'Default interest rate cannot be greater than the maximum interest rate.'
                });
                return false;
            }
        }

        const minLA = parseFloat($('#min_loan_amount').val());
        const maxLA = parseFloat($('#max_loan_amount').val());
        const defLA = parseFloat($('#default_loan_amount').val());

        if (!isNaN(minLA) && !isNaN(maxLA) && minLA > maxLA) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validation',
                text: 'Minimum Loan Amount cannot be greater than Maximum Loan Amount.'
            });
            return false;
        }

        if (!isNaN(defLA)) {
            if (!isNaN(minLA) && defLA < minLA) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation',
                    text: 'Default Loan Amount cannot be less than Minimum Loan Amount.'
                });
                return false;
            }
            if (!isNaN(maxLA) && defLA > maxLA) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation',
                    text: 'Default Loan Amount cannot be greater than Maximum Loan Amount.'
                });
                return false;
            }
        }

        // Enforcements prior to submit
        const isRev = $('#loan_nature').val() === 'REVOLVING';
        if (isRev) {
            // force is_revolving true and disable installment fields
            $('#is_revolving').prop('checked', true);
            $('#default_installments, #min_installments, #max_installments')
                .val('')
                .prop('disabled', true);
            // force allow_top_up off
            $('#allow_top_up').prop('checked', false).prop('disabled', true);
        } else {
            // ensure installment fields enabled for non-revolving
            $('#default_installments, #min_installments, #max_installments').prop('disabled', false);
            $('#allow_top_up').prop('disabled', false);
        }

        // Collateral: if supports_collateral and requires_collateral , require coverage; otherwise clear it
        const supportsCol = $('#supports_collateral').is(':checked');
        const colRequired = $('#requires_collateral').is(':checked');
        if (supportsCol && colRequired) {
            const cov = $('#min_collateral_coverage_ratio').val();
            if (cov === '' || isNaN(parseFloat(cov))) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation',
                    text: 'Minimum Collateral Coverage is required when collateral is required.'
                });
                return false;
            }
        } else {
            // clear and disable coverage when not required
            $('#min_collateral_coverage_ratio').val('');
        }

        // Approval workflow: when not required, disable all approval level inputs so they are not submitted.
        // If required, make sure they are enabled (otherwise disabled fields will never submit).
        if (!$('#requires_approval').is(':checked')) {
            $('#approvalLevels').find('input, select, textarea').prop('disabled', true);
        } else {
            $('#approvalLevels').find('input, select, textarea').prop('disabled', false);

            const seenOrders = {};
            let anyApproval = false;
            let approvalOk = true;
            let approvalError = '';
            $('#approvalLevels .approval-level').each(function() {
                const $row = $(this);
                const roleId = String($row.find('select[name^="approval_levels"][name$="[role_id]"]').val() || '').trim();
                const orderVal = parseInt($row.find('input[name^="approval_levels"][name$="[level_order]"]').val() || '', 10);
                const rowMin = parseFloat($row.find('input[name^="approval_levels"][name$="[min_loan_amount]"]').val());
                const rowMax = parseFloat($row.find('input[name^="approval_levels"][name$="[max_loan_amount]"]').val());

                const hasAny = roleId !== '' || !isNaN(orderVal) || !isNaN(rowMin) || !isNaN(rowMax);
                if (!hasAny) return;
                anyApproval = true;

                if (!roleId) {
                    approvalOk = false;
                    approvalError = 'Approver Role is required for each Approval Level.';
                    return false;
                }
                if (isNaN(orderVal) || orderVal < 1) {
                    approvalOk = false;
                    approvalError = 'Level Order must be 1 or greater.';
                    return false;
                }
                if (seenOrders[orderVal]) {
                    approvalOk = false;
                    approvalError = 'Level Order must be unique (no duplicates).';
                    return false;
                }
                seenOrders[orderVal] = true;

                if (!isNaN(rowMin) && !isNaN(minLA) && rowMin < minLA) {
                    approvalOk = false;
                    approvalError = 'Approval Level Min Amount cannot be less than Product Minimum Loan Amount.';
                    return false;
                }
                if (!isNaN(rowMax) && !isNaN(maxLA) && rowMax > maxLA) {
                    approvalOk = false;
                    approvalError = 'Approval Level Max Amount cannot be greater than Product Maximum Loan Amount.';
                    return false;
                }
                if (!isNaN(rowMin) && !isNaN(rowMax) && rowMin > rowMax) {
                    approvalOk = false;
                    approvalError = 'Approval Level Min Amount cannot be greater than Max Amount.';
                    return false;
                }
            });

            if (!anyApproval) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation',
                    text: 'Requires Approval is enabled. Please add at least one Approval Level.'
                });
                return false;
            }

            if (!approvalOk) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation',
                    text: approvalError || 'Approval Levels contain invalid values. Check Role, Level Order uniqueness, and Min/Max Amount rules.'
                });
                return false;
            }
        }

        // If user hasn't confirmed, show preview modal and stop submit
        if (!loanProductConfirmed) {
            e.preventDefault();
            $('#loanProductConfirmSummary').html(buildConfirmationSummaryHtml());
            $('#loanProductConfirmModal').modal('show');
            return false;
        }

        return true;
    });

    // Session alerts
    @if(session('success'))
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: @json(session('success'))
    });
    @endif
    @if(session('error'))
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: @json(session('error'))
    });
    @endif

    // Dynamic behavior: loan nature -> is_revolving and dependent sections
    function syncRevolving() {
        const nature = $('#loan_nature').val();
        const isRev = nature === 'REVOLVING';
        $('#is_revolving').prop('checked', isRev);
        $('#interestTenureCard').toggle(!isRev);
        // disable installments inputs when revolving
        $('#default_installments, #min_installments, #max_installments').prop('disabled', isRev);
        // disable and uncheck allow_top_up when revolving
        $('#allow_top_up').prop('disabled', isRev).prop('checked', isRev ? false : $('#allow_top_up').is(
            ':checked'));
    }
    $('#loan_nature').on('change', syncRevolving);
    syncRevolving();

    // Conditional sections
    function toggleCollateral() {
        const show = $('#supports_collateral').is(':checked');
        $('#collateralRulesCard').toggle(show);
        // When hidden, also clear fields so nothing accidental is posted
        if (!show) {
            $('#requires_collateral').prop('checked', false);
            $('#min_collateral_coverage_ratio').val('');
        }
    }

    function toggleApproval() {
        const on = $('#requires_approval').is(':checked');
        $('#approvalWorkflowCard').toggle(on);
        $('#approvalWorkflowCard').find('input, select, textarea').prop('disabled', !on);
    }
    $('#supports_collateral').on('change', toggleCollateral);
    $('#requires_approval').on('change', toggleApproval);
    toggleCollateral();
    toggleApproval();

    function toggleSavingsRules() {
        const on = $('#requires_active_savings').is(':checked');
        $('.savings-rules-fields').toggle(on);
        $('#min_savings_balance').prop('disabled', !on);
        if (!on) {
            $('#min_savings_balance').val('');
        }
    }
    $('#requires_active_savings').on('change', toggleSavingsRules);
    toggleSavingsRules();

    function toggleTopUpRules() {
        const on = $('#allow_top_up').is(':checked');
        $('.top-up-section').toggle(on);
        $('#min_repayment_ratio_for_topup').prop('disabled', !on);
        if (!on) {
            $('#min_repayment_ratio_for_topup').val('');
        }
    }
    $('#allow_top_up').on('change', toggleTopUpRules);
    toggleTopUpRules();


    let feeIndex = getMaxDataIndex($('#feesRepeater'), '.fee-config');
    $('#addFeeRow').on('click', function() {
        feeIndex++;
        const feeOptions = window.loanFeesData.map(f => `<option value="${f.id}">${f.name}: Tsh ${f.amount}</option>`).join('');
        const block = `
                    <div class="border rounded p-2 mb-2 fee-config" data-index="${feeIndex}">
                <div class="form-row">
                    
                    <div class="form-group col-md-4">
                        <label>Fee Name</label>
                        <select class="form-control" id="loan_fee_id_${feeIndex}" name="fees_config[${feeIndex}][loan_fee_id]" form="loanProductForm">
                            <option value="">Select Loan Fee</option>
                            ${feeOptions}
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12 text-right">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-fee-row">Remove</button>
                    </div>
                </div>
            </div>
        `;
        $('#feesRepeater').append(block);
        $('.remove-fee-row').prop('disabled', $('.fee-config').length <= 1);
    });
    $(document).on('click', '.remove-fee-row', function() {
        $(this).closest('.fee-config').remove();
        $('.remove-fee-row').prop('disabled', $('.fee-config').length <= 1);
    });



    function toggleCollateralGuarantorFields() {
        const $wrap = $('.rules-collateral-fields');
        if ($wrap.length === 0) return;

        const $toggle = $('#enable_collateral_guarantor');
        if ($toggle.length === 0) {
            $wrap.show();
            return;
        }

        const on = $toggle.is(':checked');
        $wrap.toggle(on);
        if (!on) {
            $('#requires_guarantor').prop('checked', false);
        }
    }

    toggleCollateralGuarantorFields();
    $('#enable_collateral_guarantor').on('change', toggleCollateralGuarantorFields);

    let penaltyIndex = getMaxDataIndex($('#penaltiesRepeater'), '.penalty-config');
    $('#addPenaltyRow').on('click', function() {
        penaltyIndex++;
        const penaltyOptions = window.loanPenaltiesData.map(p => `<option value="${p.id}">${p.name}: Tsh ${p.amount}</option>`).join('');
        const block = `
                    <div class="border rounded p-2 mb-2 penalty-config" data-index="${penaltyIndex}">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Penalty Type</label>
                        <select class="form-control" id="loan_penalty_id_${penaltyIndex}" name="penalties_config[${penaltyIndex}][loan_penalty_id]"
                            >
                            <option value="">Select Loan Penalty</option>
                            ${penaltyOptions}
                        </select>
                    </div>

                    <div class="form-group col-md-4">
                        <label>Max Applications</label>
                        <input type="number" class="form-control" name="penalties_config[${penaltyIndex}][max_applications]" form="loanProductForm" min="0">
                    </div>

                    <div class="form-group col-md-4">
                        <label>Grace Period Override</label>
                        <input type="number" class="form-control" name="penalties_config[${penaltyIndex}][grace_days_override]" form="loanProductForm" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <div class="custom-control custom-switch mt-4">
                            <input type="checkbox" class="custom-control-input" id="pen_auto_apply_${penaltyIndex}" name="penalties_config[${penaltyIndex}][auto_apply]" form="loanProductForm" value="1" checked>
                            <label class="custom-control-label" for="pen_auto_apply_${penaltyIndex}">Auto Apply</label>
                        </div>
                    </div>
                    <div class="form-group col-md-9 text-right">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-penalty-config">Remove</button>
                    </div>
                </div>
            </div>`;
        $('#penaltiesRepeater').append(block);
        $('.remove-penalty-config').prop('disabled', $('.penalty-config').length <= 1);
    });
    $(document).on('click', '.remove-penalty-config', function() {
        $(this).closest('.penalty-config').remove();
        $('.remove-penalty-config').prop('disabled', $('.penalty-config').length <= 1);
    });

    $('.remove-fee-row').prop('disabled', $('.fee-config').length <= 1);
    $('.remove-penalty-config').prop('disabled', $('.penalty-config').length <= 1);



    // Approval levels repeater
    let approvalIndex = getMaxDataIndex($('#approvalLevels'), '.approval-level');
    $('#addApprovalLevel').on('click', function() {
        approvalIndex++;
        const roleOptions = window.approvalRolesData.map(r => `<option value="${r.id}">${r.name.toUpperCase()}</option>`).join('');
        const block = `
        <div class="border rounded p-2 mb-2 approval-level" data-index="${approvalIndex}">
            <div class="form-row">
                <div class="form-group col-md-2">
                    <input type="number" class="form-control" name="approval_levels[${approvalIndex}][level_order]" form="loanProductForm" min="1" value="${approvalIndex+1}">
                </div>
                <div class="form-group col-md-3">
                    <select name="approval_levels[${approvalIndex}][role_id]" class="form-control " form="loanProductForm" required>
                        <option value="">-- Select Role --</option>
                        ${roleOptions}
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <input type="number" step="0.01" class="form-control" name="approval_levels[${approvalIndex}][min_loan_amount]" form="loanProductForm" min="0" placeholder="Min Amount">
                </div>
                <div class="form-group col-md-2">
                    <input type="number" step="0.01" class="form-control" name="approval_levels[${approvalIndex}][max_loan_amount]" form="loanProductForm" min="0" placeholder="Max Amount">
                </div>
                <div class="form-group col-md-3">
                    <div class="custom-control custom-switch mt-2">
                        <input type="checkbox" class="custom-control-input" id="mandatory_${approvalIndex}" name="approval_levels[${approvalIndex}][mandatory]" form="loanProductForm" value="1" checked>
                        <label class="custom-control-label" for="mandatory_${approvalIndex}">Mandatory</label>
                    </div>
                    <div class="custom-control custom-switch mt-2">
                        <input type="checkbox" class="custom-control-input" id="can_override_${approvalIndex}" name="approval_levels[${approvalIndex}][can_override_rules]" form="loanProductForm" value="1">
                        <label class="custom-control-label" for="can_override_${approvalIndex}">Can Override Rules</label>
                    </div>
                    <div class="custom-control custom-switch mt-2">
                        <input type="checkbox" class="custom-control-input" id="can_reject_${approvalIndex}" name="approval_levels[${approvalIndex}][can_reject]" form="loanProductForm" value="1" checked>
                        <label class="custom-control-label" for="can_reject_${approvalIndex}">Can Reject</label>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <button type="button" class="btn btn-sm btn-outline-danger remove-approval-level">Remove Level</button>
            </div>
        </div>`;
        $('#approvalLevels').append(block);
    });
    $(document).on('click', '.remove-approval-level', function() {
        $(this).closest('.approval-level').remove();
    });
});
</script>
@endpush