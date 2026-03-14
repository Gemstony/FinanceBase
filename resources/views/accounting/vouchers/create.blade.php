@extends('adminlte::page')

@section('title', 'Create Voucher')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-plus"></i> Create Voucher</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-plus"></i> Create Voucher</h1>
                    <p class="mb-0 text-light">Manual receipt or payment voucher</p>
                </div>
                <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('accounting.vouchers.index') }}">Vouchers</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create</li>
            </ol>
        </nav>
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

        <form method="POST" action="{{ route('accounting.vouchers.store') }}" id="voucherForm">
            @csrf

            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header"><strong>Voucher Header</strong></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="voucher_type">Voucher Type</label>
                            <select name="voucher_type" id="voucher_type" class="form-control" required>
                                <option value="receipt" @selected(old('voucher_type') === 'receipt')>Receipt Voucher</option>
                                <option value="payment" @selected(old('voucher_type') === 'payment')>Payment Voucher</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="voucher_date">Voucher Date</label>
                            <input type="date" name="voucher_date" id="voucher_date" class="form-control" value="{{ old('voucher_date', now()->toDateString()) }}" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="payment_method">Payment Method</label>
                            <select name="payment_method" id="refund_method" class="form-control" required>
                                <option value="">Select Method</option>
                                <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                                <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                                <option value="mobile_money" @selected(old('payment_method') === 'mobile_money')>Mobile Money</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="bank_account_id">Bank Account</label>
                            <select name="bank_account_id" id="bank_account_id" class="form-control">
                                <option value="">-- None --</option>
                                @foreach($bankAccounts as $b)
                                    <option value="{{ $b->id }}" @selected((string) old('bank_account_id') === (string) $b->id)>
                                        {{ $b->account_name }}{{ $b->bank_name ? ' - ' . $b->bank_name : '' }}{{ $b->account_number ? ' (' . $b->account_number . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="2" placeholder="Voucher description">{{ old('description') }}</textarea>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Voucher Lines</strong>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                            <i class="fas fa-plus"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered" id="voucherLinesTable">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 30%">Account</th>
                                    <th style="width: 15%" class="text-right">Debit</th>
                                    <th style="width: 15%" class="text-right">Credit</th>
                                    <th>Description</th>
                                    <th style="width: 1%">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <th class="text-right">Totals</th>
                                    <th class="text-right"><span id="totalDebit">0.00</span></th>
                                    <th class="text-right"><span id="totalCredit">0.00</span></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-success" id="saveBtn">
                            <i class="fas fa-save"></i> Save Voucher
                        </button>
                        <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-light ml-2">Cancel</a>
                    </div>

                    <small class="text-muted d-block mt-2">
                        Rule: Total debit must equal total credit.
                    </small>
                </div>
            </div>
        </form>
    </div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script>
(function () {
    const accounts = @json($accounts);

    function formatNumber(n) {
        const x = parseFloat(n);
        if (isNaN(x)) return 0.0;
        return Math.round(x * 100) / 100;
    }

    function recalcTotals() {
        let totalDebit = 0.0;
        let totalCredit = 0.0;

        $('#voucherLinesTable tbody tr').each(function () {
            const debit = formatNumber($(this).find('.js-debit').val());
            const credit = formatNumber($(this).find('.js-credit').val());
            totalDebit += debit;
            totalCredit += credit;
        });

        totalDebit = Math.round(totalDebit * 100) / 100;
        totalCredit = Math.round(totalCredit * 100) / 100;

        $('#totalDebit').text(totalDebit.toFixed(2));
        $('#totalCredit').text(totalCredit.toFixed(2));

        const valid = (totalDebit > 0 || totalCredit > 0) && (totalDebit.toFixed(2) === totalCredit.toFixed(2)) && ($('#voucherLinesTable tbody tr').length >= 2);
        $('#saveBtn').prop('disabled', !valid);
    }

    function buildAccountOptions() {
        let html = '<option value="">-- Select Account --</option>';
        for (const a of accounts) {
            const label = (a.account_code ? a.account_code + ' - ' : '') + a.account_name;
            html += `<option value="${a.id}">${label}</option>`;
        }
        return html;
    }

    function addRow() {
        const idx = $('#voucherLinesTable tbody tr').length;
        const row = `
            <tr>
                <td>
                    <select name="lines[${idx}][account_id]" class="form-control js-account" required>
                        ${buildAccountOptions()}
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" min="0" name="lines[${idx}][debit]" class="form-control text-right js-debit" placeholder="0.00">
                </td>
                <td>
                    <input type="number" step="0.01" min="0" name="lines[${idx}][credit]" class="form-control text-right js-credit" placeholder="0.00">
                </td>
                <td>
                    <input type="text" name="lines[${idx}][description]" class="form-control" placeholder="Line description">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-line" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#voucherLinesTable tbody').append(row);
        recalcTotals();
    }

    function reindex() {
        $('#voucherLinesTable tbody tr').each(function (i) {
            $(this).find('select, input').each(function () {
                const name = $(this).attr('name');
                if (!name) return;
                $(this).attr('name', name.replace(/lines\[\d+\]/, `lines[${i}]`));
            });
        });
    }

    $(document).on('input', '.js-debit', function () {
        const v = formatNumber($(this).val());
        if (v > 0) {
            $(this).closest('tr').find('.js-credit').val('');
        }
        recalcTotals();
    });

    $(document).on('input', '.js-credit', function () {
        const v = formatNumber($(this).val());
        if (v > 0) {
            $(this).closest('tr').find('.js-debit').val('');
        }
        recalcTotals();
    });

    $(document).on('click', '.js-remove-line', function () {
        $(this).closest('tr').remove();
        reindex();
        recalcTotals();
    });

    $('#addLineBtn').on('click', function () {
        addRow();
    });

    addRow();
    addRow();
})();
</script>
@endpush
