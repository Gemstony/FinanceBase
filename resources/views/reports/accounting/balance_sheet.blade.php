@extends('adminlte::page')

@section('title', 'Balance Sheet Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-file-invoice-dollar"></i> Balance Sheet</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-file-invoice-dollar"></i> Balance Sheet</h1>
                <p class="mb-0 text-light">As-of: <strong>{{ $asOf ?? '' }}</strong></p>
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
        <li class="breadcrumb-item active" aria-current="page">Balance Sheet</li>
    </ol>
</nav>
@stop

@section('content')
@php
    $tree = $report['tree'] ?? [];
    $totals = $report['totals'] ?? [];
    $validation = $report['validation'] ?? [];

    $fmt = function ($v) {
        $n = (float) ($v ?? 0);
        $abs = number_format(abs($n), 2);
        return $n < 0 ? '(' . $abs . ')' : $abs;
    };
@endphp

<div class="container-fluid">
    <div class="card">
        <div class="card-body">

            <form method="get" action="{{ route('reports.accounting.balance_sheet.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="as_of">As-of Date</label>
                            <input type="date" class="form-control form-control-sm" id="as_of" name="as_of" value="{{ $asOf ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="compare_as_of">Compare As-of Date (optional)</label>
                            <input type="date" class="form-control form-control-sm" id="compare_as_of" name="compare_as_of" value="{{ $compareAsOf ?? '' }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="subshop_id">Branch</label>
                            <select class="form-control form-control-sm" id="subshop_id" name="subshop_id">
                                <option value="">All Accessible</option>
                                @foreach(($subshops ?? []) as $s)
                                    <option value="{{ $s->id }}" {{ ($selectedSubshopId ?? null) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="account_class_id">Account Class (optional)</label>
                            <select class="form-control form-control-sm" id="account_class_id" name="account_class_id">
                                <option value="">All Classes</option>
                                @foreach(($accountClasses ?? []) as $c)
                                    <option value="{{ $c->id }}" {{ request('account_class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
                            <a href="{{ route('reports.accounting.balance_sheet.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="row m-2">
                <div class="col-12 text-center">
                    <div class="btn-group" role="group">
                        <a href="{{ $exportUrl ?? '#' }}" class="btn btn-success"><i class="fas fa-file-excel"></i> Export to Excel</a>
                        <a href="{{ $pdfUrl ?? '#' }}" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Export to PDF</a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                    </div>
                </div>
            </div>

            @if(!empty($validation) && empty($validation['balanced']))
                <div class="alert alert-warning">
                    <strong>Warning:</strong> Balance Sheet is not balanced.
                    Difference: <strong>{{ $fmt($validation['difference'] ?? 0) }}</strong>
                </div>
            @else
                <div class="alert alert-success">
                    <strong>Balanced:</strong> Assets = Liabilities + Equity
                </div>
            @endif

            <div class="row">
                <div class="col-12 col-lg-6">
                    <div class="card">
                        <div class="card-header"><strong>Assets</strong></div>
                        <div class="card-body p-0">
                            @include('reports.accounting.partials.balance_sheet_section', [
                                'sectionTitle' => 'Current Assets',
                                'sectionTree' => $tree['assets']['current'] ?? [],
                                'fmt' => $fmt,
                                'asOf' => $asOf,
                                'selectedSubshopId' => $selectedSubshopId,
                                'compareAsOf' => $compareAsOf,
                            ])
                            @include('reports.accounting.partials.balance_sheet_section', [
                                'sectionTitle' => 'Non-Current Assets',
                                'sectionTree' => $tree['assets']['non_current'] ?? [],
                                'fmt' => $fmt,
                                'asOf' => $asOf,
                                'selectedSubshopId' => $selectedSubshopId,
                                'compareAsOf' => $compareAsOf,
                            ])
                            <div class="p-2 border-top d-flex justify-content-between">
                                <strong>Total Assets</strong>
                                <strong>{{ $fmt($totals['assets_total'] ?? 0) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card">
                        <div class="card-header"><strong>Liabilities &amp; Equity</strong></div>
                        <div class="card-body p-0">
                            @include('reports.accounting.partials.balance_sheet_section', [
                                'sectionTitle' => 'Current Liabilities',
                                'sectionTree' => $tree['liabilities']['current'] ?? [],
                                'fmt' => $fmt,
                                'asOf' => $asOf,
                                'selectedSubshopId' => $selectedSubshopId,
                                'compareAsOf' => $compareAsOf,
                            ])
                            @include('reports.accounting.partials.balance_sheet_section', [
                                'sectionTitle' => 'Non-Current Liabilities',
                                'sectionTree' => $tree['liabilities']['non_current'] ?? [],
                                'fmt' => $fmt,
                                'asOf' => $asOf,
                                'selectedSubshopId' => $selectedSubshopId,
                                'compareAsOf' => $compareAsOf,
                            ])
                            <div class="p-2 border-top d-flex justify-content-between">
                                <strong>Total Liabilities</strong>
                                <strong>{{ $fmt($totals['liabilities_total'] ?? 0) }}</strong>
                            </div>

                            @include('reports.accounting.partials.balance_sheet_section', [
                                'sectionTitle' => 'Equity',
                                'sectionTree' => $tree['equity']['items'] ?? [],
                                'fmt' => $fmt,
                                'asOf' => $asOf,
                                'selectedSubshopId' => $selectedSubshopId,
                                'compareAsOf' => $compareAsOf,
                            ])

                            <div class="p-2">
                                <div class="d-flex justify-content-between">
                                    <span>Retained Earnings</span>
                                    <span class="text-right">{{ $fmt($tree['equity']['computed_totals']['retained_earnings'] ?? 0) }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Current Year Profit</span>
                                    <span class="text-right">{{ $fmt($tree['equity']['computed_totals']['current_year_profit'] ?? 0) }}</span>
                                </div>
                            </div>

                            <div class="p-2 border-top d-flex justify-content-between">
                                <strong>Total Equity</strong>
                                <strong>{{ $fmt($totals['equity_total'] ?? 0) }}</strong>
                            </div>

                            <div class="p-2 border-top d-flex justify-content-between">
                                <strong>Total Liabilities + Equity</strong>
                                <strong>{{ $fmt(($totals['liabilities_total'] ?? 0) + ($totals['equity_total'] ?? 0)) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            @if(request()->boolean('debug'))
                <div class="card mt-3">
                    <div class="card-header"><strong>Debug: GL Account Totals &amp; Computed Balance</strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Class</th>
                                        <th>Category</th>
                                        <th class="text-right">Total Debit</th>
                                        <th class="text-right">Total Credit</th>
                                        <th class="text-right">Computed Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($report['debug']['rows'] ?? []) as $r)
                                        <tr>
                                            <td>{{ $r['account_code'] ?? '' }} - {{ $r['account_name'] ?? '' }}</td>
                                            <td>{{ $r['class_code'] ?? '' }} {{ $r['class'] ?? '' }}</td>
                                            <td>{{ $r['category'] ?? '' }}</td>
                                            <td class="text-right">{{ number_format((float) ($r['total_debit'] ?? 0), 2) }}</td>
                                            <td class="text-right">{{ number_format((float) ($r['total_credit'] ?? 0), 2) }}</td>
                                            <td class="text-right">{{ $fmt($r['balance'] ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                    @if(empty($report['debug']['rows'] ?? []))
                                        <tr>
                                            <td colspan="6" class="text-center text-muted p-3">No debug data</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
