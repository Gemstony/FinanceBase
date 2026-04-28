@extends('adminlte::page')

@section('title', 'Changes in Equity Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exchange-alt"></i> Changes in Equity</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exchange-alt"></i> Changes in Equity</h1>
                <p class="mb-0 text-light">Statement of changes in equity</p>
            </div>
            <a href="{{ route('reports.accounting_reports.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('reports.accounting_reports.index') }}">Accounting Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Changes in Equity</li>
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

    $report = $report ?? null;
    $hasData = $report['has_data'] ?? false;
    $filters = $report['filters'] ?? null;
@endphp

<div class="container-fluid">
    <div class="card">
        <div class="card-body">

            <form method="get" action="{{ route('reports.accounting.changes_in_equity.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="from_date">From Date</label>
                            <input type="date" class="form-control form-control-sm" id="from_date" name="from_date" value="{{ request('from_date', $dateFrom ?? '') }}">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="to_date">To Date</label>
                            <input type="date" class="form-control form-control-sm" id="to_date" name="to_date" value="{{ request('to_date', $dateTo ?? '') }}">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="subshop_id">Branch</label>
                            <select class="form-control form-control-sm" id="subshop_id" name="subshop_id">
                                <option value="">All Accessible</option>
                                @foreach(($subshops ?? []) as $s)
                                    <option value="{{ $s->id }}" {{ ($selectedSubshopId ?? null) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
                            <a href="{{ route('reports.accounting.changes_in_equity.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            @if(!empty($report))
                <div class="row m-2">
                    <div class="col-12 text-center">
                        <div class="btn-group" role="group">
                            <a href="{{ $exportUrl ?? '#' }}" class="btn btn-success"><i class="fas fa-file-excel"></i> Export to Excel</a>
                            <a href="{{ $pdfUrl ?? '#' }}" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Export to PDF</a>
                            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                        </div>
                    </div>
                </div>

                @if(!$hasData)
                    <div class="alert alert-info">No equity changes found for selected period.</div>
                @else
                    {{-- Summary Cards --}}
                    <div class="row mb-3">
                        <div class="col-md-3 mb-3">
                            <div class="card text-white bg-primary">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-uppercase small">Opening Equity</div>
                                            <div class="h4 mb-0">{{ $fmt($report['opening_equity'] ?? 0) }}</div>
                                        </div>
                                        <i class="fas fa-play-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card text-white bg-success">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-uppercase small">Capital Contributions</div>
                                            <div class="h4 mb-0">{{ $fmt($report['capital_contributions'] ?? 0) }}</div>
                                        </div>
                                        <i class="fas fa-arrow-circle-up fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card text-white bg-info">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-uppercase small">Net Profit</div>
                                            <div class="h4 mb-0">{{ $fmt($report['net_profit'] ?? 0) }}</div>
                                        </div>
                                        <i class="fas fa-chart-line fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card text-white bg-warning">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-uppercase small">Withdrawals</div>
                                            <div class="h4 mb-0">{{ $fmt($report['withdrawals'] ?? 0) }}</div>
                                        </div>
                                        <i class="fas fa-arrow-circle-down fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Validation Warning --}}
                    @if(!($report['validation']['balanced'] ?? true))
                        <div class="alert alert-warning">
                            <strong><i class="fas fa-exclamation-triangle"></i> Warning:</strong> 
                            Equity does not match Balance Sheet. Difference: {{ $fmt($report['validation']['difference'] ?? 0) }}
                        </div>
                    @endif

                    {{-- Main Statement Table --}}
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Statement of Changes in Equity</strong>
                            <span class="text-muted ml-2">
                                ({{ $filters['from_date'] ?? '' }} to {{ $filters['to_date'] ?? '' }})
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" style="font-size: 14px;">
                                    <thead>
                                        <tr class="bg-light">
                                            <th style="width: 70%;">Description</th>
                                            <th class="text-right" style="width: 30%;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Opening Equity</strong></td>
                                            <td class="text-right"><strong>{{ $fmt($report['opening_equity'] ?? 0) }}</strong></td>
                                        </tr>
                                        <tr class="table-success">
                                            <td>&nbsp;&nbsp;&nbsp;+ Capital Contributions</td>
                                            <td class="text-right">{{ $fmt($report['capital_contributions'] ?? 0) }}</td>
                                        </tr>
                                        <tr class="table-info">
                                            <td>&nbsp;&nbsp;&nbsp;+ Net Profit</td>
                                            <td class="text-right">{{ $fmt($report['net_profit'] ?? 0) }}</td>
                                        </tr>
                                        <tr class="table-warning">
                                            <td>&nbsp;&nbsp;&nbsp;- Withdrawals</td>
                                            <td class="text-right">({{ number_format((float) ($report['withdrawals'] ?? 0), 2) }})</td>
                                        </tr>
                                        <tr class="bg-light">
                                            <td><strong>= Closing Equity</strong></td>
                                            <td class="text-right"><strong>{{ $fmt($report['closing_equity'] ?? 0) }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Balance Sheet Reconciliation --}}
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Balance Sheet Reconciliation</strong>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" style="font-size: 14px;">
                                    <tbody>
                                        <tr>
                                            <td style="width: 70%;">Equity from Changes in Equity (Closing)</td>
                                            <td class="text-right">{{ $fmt($report['closing_equity'] ?? 0) }}</td>
                                        </tr>
                                        <tr>
                                            <td style="width: 70%;">Equity from Balance Sheet (as of {{ $filters['to_date'] ?? '' }})</td>
                                            <td class="text-right">{{ $fmt($report['balance_sheet_equity'] ?? 0) }}</td>
                                        </tr>
                                        <tr class="{{ ($report['validation']['balanced'] ?? true) ? 'table-success' : 'table-danger' }}">
                                            <td><strong>Difference</strong></td>
                                            <td class="text-right"><strong>{{ $fmt($report['validation']['difference'] ?? 0) }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Equity Breakdown --}}
                    @if(!empty($report['equity_breakdown']))
                        <div class="card mb-3">
                            <div class="card-header">
                                <strong>Equity Accounts Breakdown</strong>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0" style="font-size: 14px;">
                                        <thead>
                                            <tr class="bg-light">
                                                <th>Account Code</th>
                                                <th>Account Name</th>
                                                <th class="text-right">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($report['equity_breakdown'] as $equity)
                                                <tr>
                                                    <td>{{ $equity['account_code'] ?? '' }}</td>
                                                    <td>{{ $equity['account_name'] ?? '' }}</td>
                                                    <td class="text-right">{{ $fmt($equity['balance'] ?? 0) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Retained Earnings --}}
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Retained Earnings</strong>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">
                                Cumulative retained earnings as of {{ $filters['to_date'] ?? '' }}: 
                                <strong>{{ $fmt($report['retained_earnings'] ?? 0) }}</strong>
                            </p>
                        </div>
                    </div>

                    {{-- Drill-down Links --}}
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Drill-Down</strong>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <a href="{{ route('reports.accounting.profit_loss.index', ['from_date' => $filters['from_date'] ?? '', 'to_date' => $filters['to_date'] ?? '', 'subshop_id' => $selectedSubshopId]) }}" class="btn btn-outline-primary btn-block">
                                        <i class="fas fa-chart-line"></i> View Profit & Loss
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="{{ route('reports.accounting.general_ledger.index', ['from_date' => $filters['from_date'] ?? '', 'to_date' => $filters['to_date'] ?? '', 'subshop_id' => $selectedSubshopId]) }}" class="btn btn-outline-secondary btn-block">
                                        <i class="fas fa-book"></i> View General Ledger
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="{{ route('reports.accounting.balance_sheet.index', ['as_of' => $filters['to_date'] ?? '', 'subshop_id' => $selectedSubshopId]) }}" class="btn btn-outline-info btn-block">
                                        <i class="fas fa-balance-scale"></i> View Balance Sheet
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <div class="alert alert-info">
                    Select a date range and apply filters to view the Changes in Equity report.
                </div>
            @endif

        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
