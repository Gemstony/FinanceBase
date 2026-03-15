@extends('adminlte::page')

@section('title', 'Loan Calculator')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-calculator"></i> Loan Calculator</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-calculator"></i> Calculator</h1>
                    <p class="mb-0 text-light">Simulate loan schedule without saving anything.</p>
                </div>
                <a href="{{ route('loans.management') }}" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="alert alert-success" id="calc_success_alert" style="display:none;"></div>
    <div class="card">
        <div class="card-header"><strong>Simulation Inputs</strong></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="calc_loan_product_id">Loan Product</label>
                    <select id="calc_loan_product_id" class="form-control select2" required>
                        <option value="">-- Select --</option>
                        @foreach($loanProducts as $p)
                            <option
                                value="{{ $p->id }}"
                                data-min-loan="{{ $p->rules?->min_loan_amount ?? '' }}"
                                data-max-loan="{{ $p->rules?->max_loan_amount ?? '' }}"
                                data-min-installments="{{ $p->rules?->min_installments ?? '' }}"
                                data-max-installments="{{ $p->rules?->max_installments ?? '' }}"
                                data-min-rate="{{ $p->rules?->min_interest_rate ?? '' }}"
                                data-max-rate="{{ $p->rules?->max_interest_rate ?? '' }}"
                                data-default-installments="{{ $p->default_installments ?? '' }}"
                                data-repayment-frequency-code="{{ $p->repaymentFrequency?->code ?? '' }}"
                                data-interest-method-code="{{ $p->interestMethod?->code ?? '' }}"
                            >
                                {{ $p->name }} ({{ $p->code }})
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted" id="calc_product_hints"></small>
                </div>

                <div class="form-group col-md-6">
                    <label for="calc_borrower_type">Borrower Type</label>
                    <select id="calc_borrower_type" class="form-control" required>
                        <option value="individual">Individual</option>
                        <option value="group">Group</option>
                    </select>
                    <small class="text-muted">For schedule simulation only.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="calc_principal_amount">Principal Amount</label>
                    <input id="calc_principal_amount" type="number" step="0.01" min="0" class="form-control" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="calc_interest_rate">Interest Rate (%)</label>
                    <input id="calc_interest_rate" type="number" step="0.01" min="0" max="100" class="form-control" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="calc_installments">Installments</label>
                    <input id="calc_installments" type="number" min="1" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="calc_disbursement_date">Disbursement Date</label>
                    <input id="calc_disbursement_date" type="date" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="form-group col-md-6">
                    <label for="calc_repayment_start_date">Repayment Start Date (optional)</label>
                    <input id="calc_repayment_start_date" type="date" class="form-control">
                    <small class="text-muted">If set, schedule generation will start from this date.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Repayment Frequency</label>
                    <input id="calc_repayment_frequency" type="text" class="form-control" readonly>
                </div>
                <div class="form-group col-md-6">
                    <label>Interest Method</label>
                    <input id="calc_interest_method" type="text" class="form-control" readonly>
                </div>
            </div>

            <button type="button" class="btn btn-primary" id="btnCalculateLoan">
                <i class="fas fa-calculator"></i> Calculate
            </button>
        </div>
    </div>

    <div class="card" id="calc_results_card" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Results</strong>
            <div id="calc_status" class="text-muted small"></div>
        </div>
        <div class="card-body">
            <div class="row" id="calc_summary_row"></div>

            <div class="table-responsive mt-3">
                <table class="table table-sm table-striped" id="calc_schedule_table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Due Date</th>
                            <th class="text-right">Principal</th>
                            <th class="text-right">Interest</th>
                            <th class="text-right">Total Due</th>
                            <th class="text-right">Remaining Balance</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
    $('#calc_loan_product_id').select2({ width: '100%', placeholder: '-- Select --' });

    function fmt(v) {
        const n = Number(v || 0);
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setProductHints() {
        const $opt = $('#calc_loan_product_id').find(':selected');
        const minLoan = $opt.data('min-loan');
        const maxLoan = $opt.data('max-loan');
        const minInst = $opt.data('min-installments');
        const maxInst = $opt.data('max-installments');
        const minRate = $opt.data('min-rate');
        const maxRate = $opt.data('max-rate');
        const defInst = $opt.data('default-installments');

        const rf = ($opt.data('repayment-frequency-code') || '').toString();
        const im = ($opt.data('interest-method-code') || '').toString();

        $('#calc_repayment_frequency').val(rf);
        $('#calc_interest_method').val(im);

        let parts = [];
        if (minLoan !== undefined && minLoan !== '' && maxLoan !== undefined && maxLoan !== '') parts.push('Principal: ' + fmt(minLoan) + ' - ' + fmt(maxLoan));
        if (minInst !== undefined && minInst !== '' && maxInst !== undefined && maxInst !== '') parts.push('Installments: ' + minInst + ' - ' + maxInst);
        if (minRate !== undefined && minRate !== '' && maxRate !== undefined && maxRate !== '') parts.push('Rate: ' + minRate + '% - ' + maxRate + '%');

        $('#calc_product_hints').text(parts.join(' | '));

        if (defInst !== undefined && defInst !== '' && !$('#calc_installments').val()) {
            $('#calc_installments').val(defInst);
        }
    }

    $('#calc_loan_product_id').on('change', setProductHints);

    function renderSummary(data) {
        const html = `
            <div class="col-md-3 mb-2"><div class="border rounded p-2"><div class="text-muted small">Principal</div><div><strong>${fmt(data.totals.principal)}</strong></div></div></div>
            <div class="col-md-3 mb-2"><div class="border rounded p-2"><div class="text-muted small">Total Interest</div><div><strong>${fmt(data.totals.interest)}</strong></div></div></div>
            <div class="col-md-3 mb-2"><div class="border rounded p-2"><div class="text-muted small">Total Payable</div><div><strong>${fmt(data.totals.total_payable)}</strong></div></div></div>
            <div class="col-md-3 mb-2"><div class="border rounded p-2"><div class="text-muted small">Maturity Date</div><div><strong>${data.maturity_date || '-'}</strong></div></div></div>
        `;
        $('#calc_summary_row').html(html);
    }

    function renderSchedule(rows) {
        const $tbody = $('#calc_schedule_table tbody');
        $tbody.empty();
        rows.forEach(function(r){
            $tbody.append(`
                <tr>
                    <td>${r.installment_number}</td>
                    <td>${r.due_date}</td>
                    <td class="text-right">${fmt(r.principal_amount)}</td>
                    <td class="text-right">${fmt(r.interest_amount)}</td>
                    <td class="text-right">${fmt(r.total_due)}</td>
                    <td class="text-right">${fmt(r.remaining_balance)}</td>
                </tr>
            `);
        });
    }

    $('#btnCalculateLoan').on('click', function(){
        const loanProductId = $('#calc_loan_product_id').val();
        if (!loanProductId) {
            alert('Please select a loan product.');
            return;
        }

        $('#calc_status').text('Calculating...');

        $.ajax({
            url: '{{ route('loans.loans.calculator.calculate') }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                loan_product_id: loanProductId,
                borrower_type: $('#calc_borrower_type').val(),
                principal_amount: $('#calc_principal_amount').val(),
                interest_rate: $('#calc_interest_rate').val(),
                installments: $('#calc_installments').val(),
                disbursement_date: $('#calc_disbursement_date').val(),
                repayment_start_date: $('#calc_repayment_start_date').val(),
            }
        }).done(function(resp){
            $('#calc_results_card').show();
            $('#calc_status').text('Simulation generated successfully.');
            $('#calc_success_alert').text('Simulation generated successfully. Scroll down to view the results.').show();
            renderSummary(resp);
            renderSchedule(resp.schedule || []);

            const el = document.getElementById('calc_results_card');
            if (el && el.scrollIntoView) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }).fail(function(xhr){
            $('#calc_status').text('');
            $('#calc_success_alert').hide().text('');
            let msg = 'Failed to calculate.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                const firstKey = Object.keys(xhr.responseJSON.errors)[0];
                if (firstKey) msg = xhr.responseJSON.errors[firstKey][0];
            }
            alert(msg);
        });
    });
});
</script>
@endpush
