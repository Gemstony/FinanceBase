@extends('adminlte::page')

@section('title', 'General Ledger Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-book"></i> General Ledger</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-book"></i> General Ledger</h1>
                <p class="mb-0 text-light">Detailed transactions and running balance by account</p>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('reports.accounting_reports.index') }}">Accounting Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">General Ledger</li>
    </ol>
</nav>
@stop

@section('content')
@php
    $fmt = function ($v) {
        $n = (float) ($v ?? 0);
        $abs = number_format(abs($n), 2);
        return $n < 0 ? '(' . $abs . ')' : $abs;
    };

    $account = $report['account'] ?? null;
    $opening = $report['opening'] ?? null;
    $totals = $report['totals'] ?? null;
    $transactions = $report['transactions'] ?? null;
@endphp

<div class="container-fluid">
    <div class="card">
        <div class="card-body">

            <form method="get" action="{{ route('reports.accounting.general_ledger.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="account_id">Account <span class="text-danger">*</span></label>
                            <select class="form-control form-control-sm" id="account_id" name="account_id" required>
                                <option value="">Select account</option>
                                @foreach(($accounts ?? []) as $a)
                                    <option value="{{ $a->id }}" {{ ($selectedAccountId ?? null) == $a->id ? 'selected' : '' }}>
                                        {{ $a->account_code }} - {{ $a->account_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="from_date">From Date</label>
                            <input type="date" class="form-control form-control-sm" id="from_date" name="from_date" value="{{ request('from_date') }}">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="to_date">To Date</label>
                            <input type="date" class="form-control form-control-sm" id="to_date" name="to_date" value="{{ request('to_date') }}">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="subshop_id">Branch</label>
                            <select class="form-control form-control-sm" id="subshop_id" name="subshop_id">
                                <option value="">All Accessible</option>
                                @foreach(($subshops ?? []) as $s)
                                    <option value="{{ $s->id }}" {{ ($selectedSubshopId ?? null) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="reference_type">Transaction Type</label>
                            <select class="form-control form-control-sm" id="reference_type" name="reference_type">
                                <option value="">All</option>
                                @foreach(($referenceTypes ?? []) as $rt)
                                    <option value="{{ $rt }}" {{ request('reference_type') == $rt ? 'selected' : '' }}>{{ $rt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="reference_search">Reference / Description Search (optional)</label>
                            <input type="text" class="form-control form-control-sm" id="reference_search" name="reference_search" value="{{ request('reference_search') }}" placeholder="e.g. #120, loan_payment, narration text">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="per_page">Per Page</label>
                            <select class="form-control form-control-sm" id="per_page" name="per_page">
                                @foreach([25,50,100,200] as $pp)
                                    <option value="{{ $pp }}" {{ (int) request('per_page', 50) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
                            <a href="{{ route('reports.accounting.general_ledger.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            @if(!empty($selectedAccountId) && !empty($report))
                <div class="row m-2">
                    <div class="col-12 text-center">
                        <div class="btn-group" role="group">
                            <a href="{{ $exportUrl ?? '#' }}" class="btn btn-success"><i class="fas fa-file-excel"></i> Export to Excel</a>
                            <a href="{{ $pdfUrl ?? '#' }}" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Export to PDF</a>
                            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Account Summary</strong></div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="small text-muted">Account</div>
                                <div><strong>{{ $account['account_code'] ?? '' }} - {{ $account['account_name'] ?? '' }}</strong></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card text-white bg-info">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="text-uppercase small">Opening Balance</div>
                                                <div class="h5 mb-0">{{ $fmt($opening['balance'] ?? 0) }}</div>
                                            </div>
                                            <i class="fas fa-door-open fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card text-white bg-success">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="text-uppercase small">Period Debit</div>
                                                <div class="h5 mb-0">{{ $fmt($totals['period_debit'] ?? 0) }}</div>
                                            </div>
                                            <i class="fas fa-arrow-circle-down fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card text-white bg-danger">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="text-uppercase small">Period Credit</div>
                                                <div class="h5 mb-0">{{ $fmt($totals['period_credit'] ?? 0) }}</div>
                                            </div>
                                            <i class="fas fa-arrow-circle-up fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card text-white bg-dark">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="text-uppercase small">Closing Balance</div>
                                                <div class="h5 mb-0">{{ $fmt($totals['closing_balance'] ?? 0) }}</div>
                                            </div>
                                            <i class="fas fa-flag-checkered fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th style="width: 12%">Date</th>
                                <th style="width: 18%">Reference</th>
                                <th>Description</th>
                                <th class="text-right" style="width: 10%">Debit</th>
                                <th class="text-right" style="width: 10%">Credit</th>
                                <th class="text-right" style="width: 14%">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(!empty($transactions) && $transactions->count() > 0)
                                @foreach($transactions as $t)
                                    @php
                                        $ref = '#' . (int) ($t['journal_entry_id'] ?? 0);
                                        $desc = trim((string) ($t['journal_description'] ?? ''));
                                        $lineDesc = trim((string) ($t['line_description'] ?? ''));
                                        $fullDesc = $desc;
                                        if($lineDesc !== '' && $lineDesc !== $desc) { $fullDesc = $desc !== '' ? ($desc . ' | ' . $lineDesc) : $lineDesc; }
                                    @endphp
                                    <tr>
                                        <td>{{ $t['transaction_date'] ?? '' }}</td>
                                        <td>
                                            <a href="{{ route('reports.accounting.general_ledger.journal_entry', ['journalEntryId' => $t['journal_entry_id'] ?? 0, 'subshop_id' => $selectedSubshopId]) }}" class="btn btn-xs btn-outline-primary gl-ref-link">
                                                <i class="fas fa-external-link-alt mr-1"></i>{{ $ref }}
                                            </a>
                                            <div class="small text-muted">{{ $t['reference_type'] ?? '' }}{{ !empty($t['reference_id']) ? (' #' . (int) $t['reference_id']) : '' }}</div>
                                        </td>
                                        <td>
                                            {{ $fullDesc !== '' ? $fullDesc : '—' }}
                                            @if(!empty($t['created_by_name']))
                                                <div class="small text-muted">By: {{ $t['created_by_name'] }}</div>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ $fmt($t['debit'] ?? 0) }}</td>
                                        <td class="text-right">{{ $fmt($t['credit'] ?? 0) }}</td>
                                        <td class="text-right"><strong>{{ $fmt($t['running_balance'] ?? 0) }}</strong></td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center text-muted p-3">
                                        No transactions in selected period.
                                        @if(!empty($opening))
                                            Opening Balance: <strong>{{ $fmt($opening['balance'] ?? 0) }}</strong>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr class="bg-light">
                                <th colspan="3" class="text-right">Totals (Selected Period)</th>
                                <th class="text-right">{{ $fmt($totals['period_debit'] ?? 0) }}</th>
                                <th class="text-right">{{ $fmt($totals['period_credit'] ?? 0) }}</th>
                                <th class="text-right">{{ $fmt($totals['closing_balance'] ?? 0) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if(!empty($transactions))
                    <div class="mt-2">
                        {{ $transactions->links() }}
                    </div>
                @endif
            @else
                <div class="alert alert-info">
                    Select an account and apply filters to view the General Ledger.
                </div>
            @endif

        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
    .gl-ref-link { cursor: pointer; text-decoration: none; }
    .gl-ref-link:hover { text-decoration: underline; }
</style>
@endpush
