@extends('adminlte::page')

@section('title', 'Create Loan - ' . $subshop->name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="mb-0">Create Loan</h1>
            <div class="text-muted">Branch: <strong>{{ $subshop->name }}</strong></div>
        </div>
        <a href="{{ route('loans.loans.index') }}" class="btn btn-light border">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
@stop

@section('content')
<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('loans.loans.store') }}">
        @csrf

        <div class="card">
            <div class="card-header"><strong>Loan Setup</strong></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="loan_product_id">Loan Product</label>
                        <select id="loan_product_id" name="loan_product_id" class="form-control" required>
                            <option value="">-- Select --</option>
                            @foreach($loanProducts as $p)
                                <option
                                    value="{{ $p->id }}"
                                    data-requires-collateral="{{ (int)($p->rules?->requires_collateral ?? 0) }}"
                                    data-requires-guarantor="{{ (int)($p->rules?->requires_guarantor ?? 0) }}"
                                    data-requires-security-deposit="{{ (int)($p->rules?->requires_security_deposit ?? 0) }}"
                                    data-min-loan="{{ $p->rules?->min_loan_amount ?? '' }}"
                                    data-max-loan="{{ $p->rules?->max_loan_amount ?? '' }}"
                                    data-min-installments="{{ $p->rules?->min_installments ?? '' }}"
                                    data-max-installments="{{ $p->rules?->max_installments ?? '' }}"
                                    data-min-rate="{{ $p->rules?->min_interest_rate ?? '' }}"
                                    data-max-rate="{{ $p->rules?->max_interest_rate ?? '' }}"
                                    data-default-installments="{{ $p->default_installments ?? '' }}"
                                    {{ (string)old('loan_product_id') === (string)$p->id ? 'selected' : '' }}
                                >
                                    {{ $p->name }} ({{ $p->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="loan_type">Loan Type</label>
                        <select id="loan_type" name="loan_type" class="form-control" required>
                            <option value="individual" {{ old('loan_type','individual')==='individual' ? 'selected' : '' }}>Individual</option>
                            <option value="group" {{ old('loan_type')==='group' ? 'selected' : '' }}>Group</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" id="individualRow">
                    <div class="form-group col-md-6">
                        <label for="customer_id">Customer</label>
                        <select id="customer_id" name="customer_id" class="form-control">
                            <option value="">-- Select --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ (string)old('customer_id')===(string)$c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row" id="groupRow" style="display:none;">
                    <div class="form-group col-md-6">
                        <label for="loan_group_id">Loan Group</label>
                        <select id="loan_group_id" name="loan_group_id" class="form-control">
                            <option value="">-- Select --</option>
                            @foreach($loanGroups as $g)
                                <option value="{{ $g->id }}" {{ (string)old('loan_group_id')===(string)$g->id ? 'selected' : '' }}>
                                    {{ $g->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="principal_amount">Principal Amount</label>
                        <input id="principal_amount" name="principal_amount" type="number" step="0.01" min="0" class="form-control" value="{{ old('principal_amount') }}" required>
                        <small class="text-muted" id="principalHint"></small>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="interest_rate">Interest Rate (%)</label>
                        <input id="interest_rate" name="interest_rate" type="number" step="0.01" min="0" max="100" class="form-control" value="{{ old('interest_rate') }}" required>
                        <small class="text-muted" id="rateHint"></small>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="installments">Installments</label>
                        <input id="installments" name="installments" type="number" min="1" class="form-control" value="{{ old('installments') }}" required>
                        <small class="text-muted" id="installmentsHint"></small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="disbursement_date">Disbursement Date</label>
                        <input id="disbursement_date" name="disbursement_date" type="date" class="form-control" value="{{ old('disbursement_date') }}">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="repayment_start_date">Repayment Start Date (optional)</label>
                        <input id="repayment_start_date" name="repayment_start_date" type="date" class="form-control" value="{{ old('repayment_start_date') }}">
                        <small class="text-muted">If set, schedule generation will start from this date.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" id="securityDepositCard" style="display:none;">
            <div class="card-header"><strong>Security Deposit</strong></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="security_deposit_amount">Security Deposit Amount</label>
                        <input id="security_deposit_amount" name="security_deposit_amount" type="number" step="0.01" min="0" class="form-control" value="{{ old('security_deposit_amount') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card" id="guarantorCard" style="display:none;">
            <div class="card-header"><strong>Guarantors</strong></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label for="guarantor_ids">Select Guarantors</label>
                        <select id="guarantor_ids" name="guarantor_ids[]" class="form-control" multiple>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_joint_liability" name="is_joint_liability" value="1" {{ old('is_joint_liability') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_joint_liability">Joint Liability</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" id="collateralCard" style="display:none;">
            <div class="card-header"><strong>Collaterals</strong></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="collateral_ids">Select Collaterals</label>
                        <select id="collateral_ids" name="collateral_ids[]" class="form-control" multiple>
                            @foreach($customerCollaterals as $cc)
                                <option value="{{ $cc->id }}" data-customer-id="{{ $cc->customer_id }}">
                                    {{ $cc->description }} (Value: {{ number_format((float)$cc->estimated_value, 2) }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">For individual loans, collaterals will be filtered to the selected customer.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-right">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Create Loan
            </button>
        </div>
    </form>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@section('js')
<script>
(function () {
    function selectedProductOption() {
        const sel = document.getElementById('loan_product_id');
        return sel && sel.selectedOptions && sel.selectedOptions.length ? sel.selectedOptions[0] : null;
    }

    function updateVisibility() {
        const loanType = document.getElementById('loan_type').value;
        const individualRow = document.getElementById('individualRow');
        const groupRow = document.getElementById('groupRow');

        if (loanType === 'group') {
            individualRow.style.display = 'none';
            groupRow.style.display = '';
        } else {
            individualRow.style.display = '';
            groupRow.style.display = 'none';
        }

        const opt = selectedProductOption();
        const reqCollateral = opt ? (opt.getAttribute('data-requires-collateral') === '1') : false;
        const reqGuarantor = opt ? (opt.getAttribute('data-requires-guarantor') === '1') : false;
        const reqDeposit = opt ? (opt.getAttribute('data-requires-security-deposit') === '1') : false;

        document.getElementById('collateralCard').style.display = reqCollateral ? '' : 'none';
        document.getElementById('guarantorCard').style.display = reqGuarantor ? '' : 'none';
        document.getElementById('securityDepositCard').style.display = reqDeposit ? '' : 'none';

        document.getElementById('collateral_ids').required = reqCollateral;
        document.getElementById('guarantor_ids').required = reqGuarantor;
        document.getElementById('security_deposit_amount').required = reqDeposit;

        updateHints();
        filterCollateralOptions();
    }

    function updateHints() {
        const opt = selectedProductOption();
        const principalHint = document.getElementById('principalHint');
        const rateHint = document.getElementById('rateHint');
        const installmentsHint = document.getElementById('installmentsHint');

        const minLoan = opt ? opt.getAttribute('data-min-loan') : '';
        const maxLoan = opt ? opt.getAttribute('data-max-loan') : '';
        const minRate = opt ? opt.getAttribute('data-min-rate') : '';
        const maxRate = opt ? opt.getAttribute('data-max-rate') : '';
        const minInst = opt ? opt.getAttribute('data-min-installments') : '';
        const maxInst = opt ? opt.getAttribute('data-max-installments') : '';
        const defInst = opt ? opt.getAttribute('data-default-installments') : '';

        principalHint.textContent = (minLoan || maxLoan) ? ('Allowed: ' + (minLoan || '-') + ' to ' + (maxLoan || '-') ) : '';
        rateHint.textContent = (minRate || maxRate) ? ('Allowed: ' + (minRate || '-') + '% to ' + (maxRate || '-') + '%') : '';
        installmentsHint.textContent = (minInst || maxInst) ? ('Allowed: ' + (minInst || '-') + ' to ' + (maxInst || '-') ) : '';

        const installmentsInput = document.getElementById('installments');
        if (installmentsInput && !installmentsInput.value && defInst) {
            installmentsInput.value = defInst;
        }
    }

    function filterCollateralOptions() {
        const loanType = document.getElementById('loan_type').value;
        const customerId = document.getElementById('customer_id').value;
        const select = document.getElementById('collateral_ids');

        if (!select) {
            return;
        }

        const opts = Array.from(select.options);

        // Reset all
        opts.forEach(o => { o.hidden = false; });

        if (loanType !== 'individual' || !customerId) {
            return;
        }

        opts.forEach(o => {
            const cid = o.getAttribute('data-customer-id');
            if (cid && cid !== customerId) {
                o.hidden = true;
                o.selected = false;
            }
        });
    }

    document.getElementById('loan_type').addEventListener('change', updateVisibility);
    document.getElementById('loan_product_id').addEventListener('change', updateVisibility);
    document.getElementById('customer_id').addEventListener('change', filterCollateralOptions);

    updateVisibility();
})();
</script>
@stop
