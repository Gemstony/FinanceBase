@extends('adminlte::page')

@section('title', 'Create Manual Journal')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-plus"></i> Create Manual Journal</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-plus"></i> Create Manual Journal</h1>
                    <p class="mb-0 text-light">Draft a balanced journal entry</p>
                </div>
                <a href="{{ route('accounting.manual-journals.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('accounting.manual-journals.index') }}">Manual Journals</a></li>
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

        <form method="POST" action="{{ route('accounting.manual-journals.store') }}" id="manualJournalForm">
            @csrf

            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header"><strong>Journal Header</strong></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="transaction_date">Transaction Date</label>
                            <input type="date" name="transaction_date" id="transaction_date" class="form-control" value="{{ old('transaction_date', now()->toDateString()) }}" required>
                        </div>
                        <div class="form-group col-md-8">
                            <label for="description">Description</label>
                            <input type="text" name="description" id="description" class="form-control" value="{{ old('description') }}" placeholder="Journal description">
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Journal Lines</strong>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                            <i class="fas fa-plus"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered" id="journalLinesTable">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 30%">Account</th>
                                    <th style="width: 15%" class="text-right">Debit</th>
                                    <th style="width: 15%" class="text-right">Credit</th>
                                    <th>Description</th>
                                    <th style="width: 1%">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
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
                            <i class="fas fa-save"></i> Save Draft
                        </button>
                        <a href="{{ route('accounting.manual-journals.index') }}" class="btn btn-light ml-2">
                            Cancel
                        </a>
                    </div>

                    <small class="text-muted d-block mt-2">
                        Rules:
                        - At least 2 lines
                        - Total debit must equal total credit
                        - One of debit/credit per line
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

        $('#journalLinesTable tbody tr').each(function () {
            const debit = formatNumber($(this).find('.js-debit').val());
            const credit = formatNumber($(this).find('.js-credit').val());
            totalDebit += debit;
            totalCredit += credit;
        });

        totalDebit = Math.round(totalDebit * 100) / 100;
        totalCredit = Math.round(totalCredit * 100) / 100;

        $('#totalDebit').text(totalDebit.toFixed(2));
        $('#totalCredit').text(totalCredit.toFixed(2));

        const valid = (totalDebit > 0 || totalCredit > 0) && (totalDebit.toFixed(2) === totalCredit.toFixed(2)) && ($('#journalLinesTable tbody tr').length >= 2);
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
        const idx = $('#journalLinesTable tbody tr').length;
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

        $('#journalLinesTable tbody').append(row);
        recalcTotals();
    }

    function reindex() {
        $('#journalLinesTable tbody tr').each(function (i) {
            $(this).find('select, input').each(function () {
                const name = $(this).attr('name');
                if (!name) return;
                $(this).attr('name', name.replace(/lines\[\d+\]/, `lines[${i}]`));
            });
        });
    }

    $(document).ready(function () {
        $('#addLineBtn').on('click', addRow);

        $('#journalLinesTable').on('input', '.js-debit', function () {
            const $row = $(this).closest('tr');
            if (formatNumber($(this).val()) > 0) {
                $row.find('.js-credit').val('');
            }
            recalcTotals();
        });

        $('#journalLinesTable').on('input', '.js-credit', function () {
            const $row = $(this).closest('tr');
            if (formatNumber($(this).val()) > 0) {
                $row.find('.js-debit').val('');
            }
            recalcTotals();
        });

        $('#journalLinesTable').on('click', '.js-remove-line', function () {
            $(this).closest('tr').remove();
            reindex();
            recalcTotals();
        });

        $('#manualJournalForm').on('submit', function (e) {
            const totalDebit = $('#totalDebit').text();
            const totalCredit = $('#totalCredit').text();
            if (totalDebit !== totalCredit || $('#journalLinesTable tbody tr').length < 2) {
                e.preventDefault();
                alert('Journal must have at least 2 lines and total debit must equal total credit.');
            }
        });

        addRow();
        addRow();
    });
})();
</script>
@endpush
