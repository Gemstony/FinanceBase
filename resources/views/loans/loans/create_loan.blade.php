@extends('adminlte::page')

@section('title', 'Create Loan - ' . $subshop->name)

@section('content_header')
 <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
     <div class="card-body">
         <div class="d-flex justify-content-between align-items-center">
             <div>
                 <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Create Loan</h1>
                 <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Create</h1>
                 <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
             </div>
            <a href="{{ route('loans.loans.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
         </div>
     </div>
 </div>
 <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
             <li class="breadcrumb-item"><a href="{{ route('loans.loans.index') }}">Loans</a></li>
             <li class="breadcrumb-item active" aria-current="page">Create Loan</li>
         </ol>
     </nav>
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

        <div id="productRulesCard" class="card mb-3" style="display: none;">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Product Rules & Requirements</h5>
            </div>
            <div class="card-body">
                <div class="row" id="rulesContent"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Loan Setup</strong></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="loan_product_id">Loan Product</label>
                        <select id="loan_product_id" name="loan_product_id" class="form-control select2" required>
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
                                    data-min-age="{{ $p->rules?->min_age ?? '' }}"
                                    data-max-age="{{ $p->rules?->max_age ?? '' }}"
                                    data-min-membership-days="{{ $p->rules?->min_membership_days ?? '' }}"
                                    data-max-active-loans="{{ $p->rules?->max_active_loans ?? '' }}"
                                    data-min-collateral-coverage-ratio="{{ $p->rules?->min_collateral_coverage_ratio ?? '' }}"
                                    data-default-installments="{{ $p->default_installments ?? '' }}"
                                    data-repayment-frequency-code="{{ $p->repaymentFrequency?->code ?? '' }}"
                                    data-interest-method-code="{{ $p->interestMethod?->code ?? '' }}"
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
                        <select id="customer_id" name="customer_id" class="form-control select2">
                            @if(old('customer_id'))
                                <option value="{{ old('customer_id') }}" selected>Selected customer</option>
                            @endif
                        </select>
                    </div>
                </div>

                <div class="form-row" id="groupRow" style="display:none;">
                    <div class="form-group col-md-6">
                        <label for="loan_group_id">Loan Group</label>
                        <select id="loan_group_id" name="loan_group_id" class="form-control select2">
                            @if(old('loan_group_id'))
                                <option value="{{ old('loan_group_id') }}" selected>Selected group</option>
                            @endif
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="principal_amount">Principal Amount</label>
                        <input id="principal_amount" name="principal_amount" type="number" step="0.01" min="0" class="form-control" value="{{ old('principal_amount') }}" required>
                        <small class="text-muted" id="principalHint"></small>
                        <div class="invalid-feedback" id="principalError"></div>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="interest_rate">Interest Rate (%)</label>
                        <input id="interest_rate" name="interest_rate" type="number" step="0.01" min="0" max="100" class="form-control" value="{{ old('interest_rate') }}" required>
                        <small class="text-muted" id="rateHint"></small>
                        <div class="invalid-feedback" id="rateError"></div>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="installments">Installments</label>
                        <input id="installments" name="installments" type="number" min="1" class="form-control" value="{{ old('installments') }}" required>
                        <small class="text-muted" id="installmentsHint"></small>
                        <div class="invalid-feedback" id="installmentsError"></div>
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

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Repayment Frequency</label>
                        <input id="repayment_frequency" type="text" class="form-control" readonly>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Interest Method</label>
                        <input id="interest_method" type="text" class="form-control" readonly>
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Guarantors</strong>
                <button type="button" class="btn btn-sm btn-primary ml-auto" id="addGuarantor">
                    <i class="fas fa-plus"></i> Add Guarantor
                </button>
            </div>
            <div class="card-body">
                <div id="guarantorsContainer">
                    <!-- Dynamic rows -->
                </div>
                <div class="mt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_joint_liability" name="is_joint_liability" value="1" {{ old('is_joint_liability') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_joint_liability">Joint Liability (All guarantors are equally responsible)</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" id="collateralCard" style="display:none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Collaterals</strong>
                <button type="button" class="btn btn-sm btn-primary ml-auto" id="addCollateral">
                    <i class="fas fa-plus"></i> Add Collateral
                </button>
            </div>
            <div class="card-body">
                <div id="collateralsContainer">
                    <!-- Dynamic rows -->
                </div>
                <small class="text-muted">Only collaterals belonging to the selected customer will be available for search in individual loans.</small>
            </div>
        </div>

        <div class="text-right mb-3">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Create Loan
            </button>
        </div>
    </form>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.css') }}">
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
    .select2-container--default .select2-selection--single {
        height: calc(2.25rem + 2px);
        padding: .375rem .75rem;
        border: 1px solid #ced4da;
        border-radius: .25rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
        padding-left: 0;
        padding-right: 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem + 2px);
        top: 0;
        right: 4px;
    }
    .select2-container--default .select2-selection--multiple {
        min-height: calc(2.25rem + 2px);
        border: 1px solid #ced4da;
        border-radius: .25rem;
    }
    .repeater-row {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: .25rem;
        padding: 10px;
        margin-bottom: 10px;
        position: relative;
    }
    .repeater-row .remove-btn {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
    }
</style>
@endpush

@section('js')
<script src="{{ asset('vendor/select2/js/select2.min.js') }}"></script>
<script>
(function () {
    const $form = $('form[action="{{ route('loans.loans.store') }}"]');

    function initSelect2() {
        if (!(window.jQuery && $.fn && $.fn.select2)) {
            return;
        }

        const subshopId = '{{ $subshop->id }}';

        function hydrateSelectedOption($el, url, buildText) {
            const val = $el.val();
            if (!val) return;

            $.ajax({
                url: url,
                dataType: 'json',
                data: { id: val, subshop_id: subshopId }
            }).done(function (data) {
                const row = Array.isArray(data) && data.length ? data[0] : null;
                if (!row) return;

                const text = buildText(row);
                const option = new Option(text, row.id, true, true);
                $el.empty().append(option).trigger('change');
            });
        }

        $('#loan_product_id').select2({
            width: '100%',
            placeholder: 'Search loan product',
            allowClear: true
        });

        $('#loan_product_id').on('change select2:select select2:clear', function () {
            updateVisibility();
        });

        $('#customer_id').select2({
            width: '100%',
            placeholder: 'Search customer by name, phone, email',
            allowClear: true,
            ajax: {
                url: '{{ route('api.loans.customers') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term, subshop_id: subshopId };
                },
                processResults: function (data) {
                    return {
                        results: data.map(c => ({
                            id: c.id,
                            text: c.name + (c.phone ? ' - ' + c.phone : '')
                        }))
                    };
                },
                cache: true
            }
        });

        hydrateSelectedOption($('#customer_id'), '{{ route('api.loans.customers') }}', function (c) {
            return c.name + (c.phone ? ' - ' + c.phone : '');
        });

        $('#loan_group_id').select2({
            width: '100%',
            placeholder: 'Search loan group',
            allowClear: true,
            ajax: {
                url: '{{ route('api.loans.loan-groups') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term, subshop_id: subshopId };
                },
                processResults: function (data) {
                    return {
                        results: data.map(g => ({ id: g.id, text: g.name }))
                    };
                },
                cache: true
            }
        });

        hydrateSelectedOption($('#loan_group_id'), '{{ route('api.loans.loan-groups') }}', function (g) {
            return g.name;
        });

        // Guarantor Repeater Logic
        let guarantorIndex = 0;
        function addGuarantorRow(val = '', text = '') {
            const container = $('#guarantorsContainer');
            const rowId = 'guarantor_row_' + guarantorIndex++;
            const html = `
                <div class="repeater-row pr-5" id="${rowId}">
                    <div class="form-row">
                        <div class="form-group col-md-11 mb-0">
                            <select name="guarantor_ids[]" class="form-control select2-guarantor" required>
                                ${val ? `<option value="${val}" selected>${text}</option>` : ''}
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="$('#${rowId}').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.append(html);
            const $newSelect = container.find(`#${rowId} .select2-guarantor`);
            $newSelect.select2({
                width: '100%',
                placeholder: 'Search guarantor',
                allowClear: true,
                ajax: {
                    url: '{{ route('api.loans.customers') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term, subshop_id: subshopId };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(c => ({
                                id: c.id,
                                text: c.name + (c.phone ? ' - ' + c.phone : '')
                            }))
                        };
                    },
                    cache: true
                }
            });
        }

        // Collateral Repeater Logic
        let collateralIndex = 0;
        function addCollateralRow(val = '', text = '') {
            const container = $('#collateralsContainer');
            const rowId = 'collateral_row_' + collateralIndex++;
            const customerId = $('#customer_id').val();
            const html = `
                <div class="repeater-row pr-5" id="${rowId}">
                    <div class="form-row">
                        <div class="form-group col-md-11 mb-0">
                            <select name="collateral_ids[]" class="form-control select2-collateral" required>
                                ${val ? `<option value="${val}" selected>${text}</option>` : ''}
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="$('#${rowId}').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.append(html);
            const $newSelect = container.find(`#${rowId} .select2-collateral`);
            $newSelect.select2({
                width: '100%',
                placeholder: 'Search collateral',
                allowClear: true,
                ajax: {
                    url: '{{ route('api.loans.collaterals') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        const currentCustomerId = $('#customer_id').val();
                        const loanType = $('#loan_type').val();
                        return { 
                            q: params.term, 
                            subshop_id: subshopId,
                            customer_id: (loanType === 'individual') ? currentCustomerId : ''
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(c => ({
                                id: c.id,
                                text: c.description + ' (Value: ' + parseFloat(c.estimated_value).toLocaleString() + ')'
                            }))
                        };
                    },
                    cache: true
                }
            });
        }

        $('#addGuarantor').on('click', function() { addGuarantorRow(); });
        $('#addCollateral').on('click', function() { addCollateralRow(); });

        // Handle Old Values for Guarantors
        @if(old('guarantor_ids'))
            @foreach(old('guarantor_ids') as $gid)
                $.ajax({
                    url: '{{ route('api.loans.customers') }}',
                    data: { id: '{{ $gid }}', subshop_id: subshopId }
                }).done(function(data) {
                    if (data && data.length) {
                        addGuarantorRow(data[0].id, data[0].name + (data[0].phone ? ' - ' + data[0].phone : ''));
                    }
                });
            @endforeach
        @endif

        // Handle Old Values for Collaterals
        @if(old('collateral_ids'))
            @foreach(old('collateral_ids') as $cid)
                $.ajax({
                    url: '{{ route('api.loans.collaterals') }}',
                    data: { id: '{{ $cid }}', subshop_id: subshopId }
                }).done(function(data) {
                    if (data && data.length) {
                        addCollateralRow(data[0].id, data[0].description + ' (Value: ' + parseFloat(data[0].estimated_value).toLocaleString() + ')');
                    }
                });
            @endforeach
        @endif

        // Clear collaterals if customer changes (since they are filtered)
        $('#customer_id').on('change', function() {
            if ($('#loan_type').val() === 'individual') {
                $('#collateralsContainer').empty();
            }
        });

        $('#loan_type').on('change', function() {
            $('#collateralsContainer').empty();
        });
    }

    function selectedProductOption() {
        const $sel = $('#loan_product_id');
        const val = $sel.val();
        console.log('selectedProductOption val:', val);
        if (!val) return null;
        const opt = $sel.find('option[value="' + val + '"]')[0];
        console.log('selectedProductOption found opt:', opt);
        return opt || null;
    }

    function toNumberOrNull(v) {
        if (v === undefined || v === null) return null;
        const s = String(v).trim();
        if (s === '') return null;
        const n = Number(s);
        return Number.isFinite(n) ? n : null;
    }

    function setInvalid(el, msg, errorEl) {
        if (!el) return;
        el.classList.add('is-invalid');
        if (errorEl) errorEl.textContent = msg || 'Invalid value.';
    }

    function clearInvalid(el, errorEl) {
        if (!el) return;
        el.classList.remove('is-invalid');
        if (errorEl) errorEl.textContent = '';
    }

    function validateAgainstRange(el, min, max, label, errorEl) {
        if (!el) return true;
        const raw = el.value;
        const val = toNumberOrNull(raw);

        if (raw === '' || val === null) {
            setInvalid(el, label + ' is required.', errorEl);
            return false;
        }

        if (min !== null && val < min) {
            setInvalid(el, label + ' must be at least ' + min + '.', errorEl);
            return false;
        }

        if (max !== null && val > max) {
            setInvalid(el, label + ' must be at most ' + max + '.', errorEl);
            return false;
        }

        clearInvalid(el, errorEl);
        return true;
    }

    function validateFormLive() {
        const opt = selectedProductOption();

        const minLoan = opt ? toNumberOrNull(opt.getAttribute('data-min-loan')) : null;
        const maxLoan = opt ? toNumberOrNull(opt.getAttribute('data-max-loan')) : null;
        const minRate = opt ? toNumberOrNull(opt.getAttribute('data-min-rate')) : null;
        const maxRate = opt ? toNumberOrNull(opt.getAttribute('data-max-rate')) : null;
        const minInst = opt ? toNumberOrNull(opt.getAttribute('data-min-installments')) : null;
        const maxInst = opt ? toNumberOrNull(opt.getAttribute('data-max-installments')) : null;

        const principalEl = document.getElementById('principal_amount');
        const rateEl = document.getElementById('interest_rate');
        const instEl = document.getElementById('installments');

        const principalOk = validateAgainstRange(principalEl, minLoan, maxLoan, 'Principal Amount', document.getElementById('principalError'));
        const rateOk = validateAgainstRange(rateEl, minRate, maxRate, 'Interest Rate', document.getElementById('rateError'));
        const instOk = validateAgainstRange(instEl, minInst, maxInst, 'Installments', document.getElementById('installmentsError'));

        const allOk = principalOk && rateOk && instOk;
        const submitBtn = $form.find('button[type="submit"]').get(0);
        if (submitBtn) {
            submitBtn.disabled = !allOk;
        }
        return allOk;
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

        // Update required attributes for repeater containers or logic if needed
        // For security deposit amount (simple input)
        const depositInput = document.getElementById('security_deposit_amount');
        if (depositInput) {
            depositInput.required = reqDeposit;
        }

        updateHints();
        validateFormLive();
        filterCollateralOptions();
    }

    function updateHints() {
        // console.log('updateHints triggered');
        const opt = selectedProductOption();
        // console.log('Selected option:', opt);
        const principalHint = document.getElementById('principalHint');
        const rateHint = document.getElementById('rateHint');
        const installmentsHint = document.getElementById('installmentsHint');
        const repaymentFrequency = document.getElementById('repayment_frequency');
        const interestMethod = document.getElementById('interest_method');

        if (!opt) {
            // console.log('No option selected, clearing hints');
            if (principalHint) principalHint.textContent = '';
            if (rateHint) rateHint.textContent = '';
            if (installmentsHint) installmentsHint.textContent = '';
            if (repaymentFrequency) repaymentFrequency.value = '';
            if (interestMethod) interestMethod.value = '';
            document.getElementById('productRulesCard').style.display = 'none';
            validateFormLive();
            return;
        }

        const minLoan = opt.getAttribute('data-min-loan') || '';
        const maxLoan = opt.getAttribute('data-max-loan') || '';
        const minRate = opt.getAttribute('data-min-rate') || '';
        const maxRate = opt.getAttribute('data-max-rate') || '';
        const minInst = opt.getAttribute('data-min-installments') || '';
        const maxInst = opt.getAttribute('data-max-installments') || '';
        const defInst = opt.getAttribute('data-default-installments') || '';

        const rf = opt.getAttribute('data-repayment-frequency-code') || '';
        const im = opt.getAttribute('data-interest-method-code') || '';

        if (repaymentFrequency) repaymentFrequency.value = rf;
        if (interestMethod) interestMethod.value = im;

        // console.log('Attributes found:', {minLoan, maxLoan, minRate, maxRate, minInst, maxInst, defInst});

        if (principalHint) principalHint.textContent = (minLoan || maxLoan) ? ('Allowed: ' + (minLoan || '-') + ' to ' + (maxLoan || '-') ) : '';
        if (rateHint) rateHint.textContent = (minRate || maxRate) ? ('Allowed: ' + (minRate || '-') + '% to ' + (maxRate || '-') + '%') : '';
        if (installmentsHint) installmentsHint.textContent = (minInst || maxInst) ? ('Allowed: ' + (minInst || '-') + ' to ' + (maxInst || '-') ) : '';

        const installmentsInput = document.getElementById('installments');
        if (installmentsInput && !installmentsInput.value && defInst) {
            installmentsInput.value = defInst;
        }

        updateProductRulesCard(opt);
        validateFormLive();
    }

    function updateProductRulesCard(opt) {
        const card = document.getElementById('productRulesCard');
        const content = document.getElementById('rulesContent');
        
        if (!card || !content) return;

        const minAge = opt.getAttribute('data-min-age');
        const maxAge = opt.getAttribute('data-max-age');
        const minMembership = opt.getAttribute('data-min-membership-days');
        const maxActiveLoans = opt.getAttribute('data-max-active-loans');
        const minCollateralRatio = opt.getAttribute('data-min-collateral-coverage-ratio');
        const minLoan = opt.getAttribute('data-min-loan');
        const maxLoan = opt.getAttribute('data-max-loan');
        const minInst = opt.getAttribute('data-min-installments');
        const maxInst = opt.getAttribute('data-max-installments');
        const minRate = opt.getAttribute('data-min-rate');
        const maxRate = opt.getAttribute('data-max-rate');
        const reqCollateral = opt.getAttribute('data-requires-collateral') === '1';
        const reqGuarantor = opt.getAttribute('data-requires-guarantor') === '1';
        const reqDeposit = opt.getAttribute('data-requires-security-deposit') === '1';

        const formatNumber = (val) => {
            if (!val) return '-';
            return parseFloat(val).toLocaleString();
        };

        const formatPercent = (val) => {
            if (!val) return '-';
            return val + '%';
        };

        let html = '';

        if (minAge || maxAge || minMembership || maxActiveLoans || minLoan || maxLoan || minInst || maxInst || minRate || maxRate || reqCollateral || reqGuarantor || reqDeposit || minCollateralRatio) {
            html += '<div class="col-12"><h6 class="text-muted mb-3"><i class="fas fa-user-check"></i> Customer Eligibility</h6></div>';
            
            if (minAge || maxAge) {
                html += `<div class="col-md-3 col-sm-6 mb-2">
                    <div class="small text-muted">Age Range</div>
                    <div class="font-weight-bold">${minAge || '-'} - ${maxAge || '-'}</div>
                </div>`;
            }
            if (minMembership) {
                html += `<div class="col-md-3 col-sm-6 mb-2">
                    <div class="small text-muted">Min. Membership</div>
                    <div class="font-weight-bold">${minMembership} days</div>
                </div>`;
            }
            if (maxActiveLoans) {
                html += `<div class="col-md-3 col-sm-6 mb-2">
                    <div class="small text-muted">Max. Active Loans</div>
                    <div class="font-weight-bold">${maxActiveLoans}</div>
                </div>`;
            }

            html += '<div class="col-12 mt-2"><h6 class="text-muted mb-3"><i class="fas fa-dollar-sign"></i> Loan Amount & Terms</h6></div>';

            if (minLoan || maxLoan) {
                html += `<div class="col-md-3 col-sm-6 mb-2">
                    <div class="small text-muted">Principal Amount</div>
                    <div class="font-weight-bold">${formatNumber(minLoan)} - ${formatNumber(maxLoan)}</div>
                </div>`;
            }
            if (minInst || maxInst) {
                html += `<div class="col-md-3 col-sm-6 mb-2">
                    <div class="small text-muted">Installments</div>
                    <div class="font-weight-bold">${minInst || '-'} - ${maxInst || '-'}</div>
                </div>`;
            }
            if (minRate || maxRate) {
                html += `<div class="col-md-3 col-sm-6 mb-2">
                    <div class="small text-muted">Interest Rate</div>
                    <div class="font-weight-bold">${formatPercent(minRate)} - ${formatPercent(maxRate)}</div>
                </div>`;
            }

            html += '<div class="col-12 mt-2"><h6 class="text-muted mb-3"><i class="fas fa-shield-alt"></i> Requirements</h6></div>';

            const reqs = [];
            if (reqCollateral) reqs.push('<span class="badge badge-warning">Collateral Required</span>');
            if (reqGuarantor) reqs.push('<span class="badge badge-info">Guarantor Required</span>');
            if (reqDeposit) reqs.push('<span class="badge badge-secondary">Security Deposit Required</span>');
            if (minCollateralRatio) reqs.push(`<span class="badge badge-success">Min. Collateral Coverage: ${minCollateralRatio}%</span>`);

            if (reqs.length > 0) {
                html += `<div class="col-12 mb-2">${reqs.join(' ')}</div>`;
            } else {
                html += '<div class="col-12 mb-2"><span class="text-muted">No special requirements</span></div>';
            }

            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }

        content.innerHTML = html;
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
        opts.forEach(o => { o.disabled = false; });

        if (loanType !== 'individual' || !customerId) {
            return;
        }

        opts.forEach(o => {
            const cid = o.getAttribute('data-customer-id');
            if (cid && cid !== customerId) {
                o.disabled = true;
                o.selected = false;
            }
        });

        if (window.jQuery && $('#collateral_ids').data('select2')) {
            $('#collateral_ids').trigger('change.select2');
        }
    }

    initSelect2();

    const principalEl = document.getElementById('principal_amount');
    const rateEl = document.getElementById('interest_rate');
    const instEl = document.getElementById('installments');
    if (principalEl) principalEl.addEventListener('input', validateFormLive);
    if (rateEl) rateEl.addEventListener('input', validateFormLive);
    if (instEl) instEl.addEventListener('input', validateFormLive);

    document.getElementById('loan_type').addEventListener('change', updateVisibility);
    document.getElementById('loan_product_id').addEventListener('change', updateVisibility);
    document.getElementById('customer_id').addEventListener('change', filterCollateralOptions);

    if (window.jQuery && $('#loan_product_id').data('select2')) {
        // Select2 sometimes does not reliably trigger the native change listener for derived UI state.
        $('#loan_product_id').on('change select2:select select2:clear', updateVisibility);
    }

    if (window.jQuery && $('#customer_id').data('select2')) {
        $('#customer_id').on('change', filterCollateralOptions);
    }

    // Run after Select2 finishes wiring and any hydration requests resolve.
    setTimeout(function () {
        updateVisibility();
        validateFormLive();
        const opt = selectedProductOption();
        if (opt) {
            updateProductRulesCard(opt);
        }
    }, 0);

    if ($form && $form.length) {
        $form.on('submit', function (e) {
            if (!validateFormLive()) {
                e.preventDefault();
            }
        });
    }
})();
</script>
@stop
