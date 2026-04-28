@extends('adminlte::page')

@section('title', 'Cash Flow Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-coins"></i> Cash Flow</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-coins"></i> Cash Flow</h1>
                <p class="mb-0 text-light">Cash inflows and outflows, with activity classification</p>
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
        <li class="breadcrumb-item active" aria-current="page">Cash Flow</li>
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

    $cash = $report['cash_account'] ?? null;
    $opening = (float) ($report['opening_balance'] ?? 0);
    $totals = $report['totals'] ?? null;
    $sections = $report['sections'] ?? null;
    $transactions = $report['transactions'] ?? null;
@endphp

<div class="container-fluid">
    <div class="card">
        <div class="card-body">

            <form method="get" action="{{ route('reports.accounting.cash_flow.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="cash_account_id">Cash / Bank Account <span class="text-danger">*</span></label>
                            <select class="form-control form-control-sm" id="cash_account_id" name="cash_account_id" required>
                                <option value="">Select cash/bank account</option>
                                @foreach(($cashAccounts ?? []) as $a)
                                    <option value="{{ $a->id }}" {{ ($selectedCashAccountId ?? null) == $a->id ? 'selected' : '' }}>
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
                        <div class="form-group col-md-2">
                            <label for="per_page">Per Page</label>
                            <select class="form-control form-control-sm" id="per_page" name="per_page">
                                @foreach([25,50,100,200] as $pp)
                                    <option value="{{ $pp }}" {{ (int) request('per_page', 50) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-10 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
                            <a href="{{ route('reports.accounting.cash_flow.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            @if(!empty($selectedCashAccountId) && !empty($report))
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
                    <div class="card-header"><strong>Summary</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="small text-muted">Cash/Bank Account</div>
                                <div><strong>{{ $cash['account_code'] ?? '' }} - {{ $cash['account_name'] ?? '' }}</strong></div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted">Opening Balance</div>
                                <div><strong>{{ $fmt($opening) }}</strong></div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted">Net Cash Flow</div>
                                <div><strong>{{ $fmt($totals['net_cash_flow'] ?? 0) }}</strong></div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted">Closing Balance</div>
                                <div><strong>{{ $fmt($totals['closing_balance'] ?? 0) }}</strong></div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="small text-muted">Operating Activities</div>
                                <div><strong>In:</strong> {{ $fmt($sections['OPERATING']['inflow'] ?? 0) }}</div>
                                <div><strong>Out:</strong> {{ $fmt($sections['OPERATING']['outflow'] ?? 0) }}</div>
                                <div><strong>Net:</strong> {{ $fmt($sections['OPERATING']['net'] ?? 0) }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">Investing Activities</div>
                                <div><strong>In:</strong> {{ $fmt($sections['INVESTING']['inflow'] ?? 0) }}</div>
                                <div><strong>Out:</strong> {{ $fmt($sections['INVESTING']['outflow'] ?? 0) }}</div>
                                <div><strong>Net:</strong> {{ $fmt($sections['INVESTING']['net'] ?? 0) }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">Financing Activities</div>
                                <div><strong>In:</strong> {{ $fmt($sections['FINANCING']['inflow'] ?? 0) }}</div>
                                <div><strong>Out:</strong> {{ $fmt($sections['FINANCING']['outflow'] ?? 0) }}</div>
                                <div><strong>Net:</strong> {{ $fmt($sections['FINANCING']['net'] ?? 0) }}</div>
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
                                <th class="text-right" style="width: 10%">Inflow (Debit)</th>
                                <th class="text-right" style="width: 10%">Outflow (Credit)</th>
                                <th class="text-right" style="width: 14%">Running Balance</th>
                                <th style="width: 12%">Activity Type</th>
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
                                        $counter = trim((string) ($t['counter_account_code'] ?? '') . ' - ' . (string) ($t['counter_account_name'] ?? ''));
                                    @endphp
                                    <tr>
                                        <td>{{ $t['transaction_date'] ?? '' }}</td>
                                        <td>
                                            <a href="{{ route('reports.accounting.general_ledger.journal_entry', ['journalEntryId' => $t['journal_entry_id'] ?? 0, 'subshop_id' => $selectedSubshopId]) }}" class="btn btn-xs btn-outline-primary">
                                                <i class="fas fa-external-link-alt mr-1"></i>{{ $ref }}
                                            </a>
                                            <div class="small text-muted">{{ $t['reference_type'] ?? '' }}{{ !empty($t['reference_id']) ? (' #' . (int) $t['reference_id']) : '' }}</div>
                                        </td>
                                        <td>
                                            {{ $fullDesc !== '' ? $fullDesc : '—' }}
                                            @if($counter !== '-')
                                                <div class="small text-muted">Counter: {{ $counter }}</div>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ $fmt($t['debit'] ?? 0) }}</td>
                                        <td class="text-right">{{ $fmt($t['credit'] ?? 0) }}</td>
                                        <td class="text-right"><strong>{{ $fmt($t['running_balance'] ?? 0) }}</strong></td>
                                        <td>{{ $t['activity_type'] ?? 'OPERATING' }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="7" class="text-center text-muted p-3">
                                        No transactions in selected period.
                                        Opening Balance: <strong>{{ $fmt($opening) }}</strong>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr class="bg-light">
                                <th colspan="3" class="text-right">Totals (Selected Period)</th>
                                <th class="text-right">{{ $fmt($totals['total_inflow'] ?? 0) }}</th>
                                <th class="text-right">{{ $fmt($totals['total_outflow'] ?? 0) }}</th>
                                <th class="text-right">{{ $fmt($totals['closing_balance'] ?? 0) }}</th>
                                <th></th>
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
                    Select a cash/bank account and apply filters to view the Cash Flow report.
                </div>
            @endif

        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
