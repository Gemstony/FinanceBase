@extends('adminlte::page')

@section('title', 'Profit & Loss Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-line"></i> Profit &amp; Loss</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-chart-line"></i> Profit &amp; Loss</h1>
                <p class="mb-0 text-light">Accrual-based income statement</p>
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
        <li class="breadcrumb-item active" aria-current="page">Profit &amp; Loss</li>
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

    $tree = $report['tree'] ?? null;
    $totals = $report['totals'] ?? null;
    $filters = $report['filters'] ?? null;

    $compare = (string) (request('compare', 'none'));
    $hasCompare = $compare !== '' && strtolower($compare) !== 'none';
    $showPct = (bool) request()->boolean('show_pct', false);

    $prevPeriod = $report['previous_period'] ?? null;
@endphp

<div class="container-fluid">
    <div class="card">
        <div class="card-body">

            <form method="get" action="{{ route('reports.accounting.profit_loss.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label for="from_date">From Date</label>
                            <input type="date" class="form-control form-control-sm" id="from_date" name="from_date" value="{{ request('from_date', $dateFrom ?? '') }}">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="to_date">To Date</label>
                            <input type="date" class="form-control form-control-sm" id="to_date" name="to_date" value="{{ request('to_date', $dateTo ?? '') }}">
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
                            <label for="account_group_id">Account Group</label>
                            <select class="form-control form-control-sm" id="account_group_id" name="account_group_id">
                                <option value="">All</option>
                                @if(!empty($accountGroups))
                                    @foreach($accountGroups as $g)
                                        <option value="{{ $g?->id ?? '' }}" {{ ($selectedAccountGroupId ?? null) == ($g?->id ?? null) ? 'selected' : '' }}>{{ $g?->code ?? '' }} - {{ $g?->name ?? '' }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="compare">Compare</label>
                            <select class="form-control form-control-sm" id="compare" name="compare">
                                @foreach(['none' => 'None', 'prev_period' => 'Previous Period', 'prev_year' => 'Previous Year'] as $k => $lbl)
                                    <option value="{{ $k }}" {{ request('compare', 'none') === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3 d-flex align-items-end">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="show_pct" name="show_pct" value="1" {{ request()->boolean('show_pct') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="show_pct">Show %</label>
                            </div>
                        </div>
                        <div class="form-group col-md-9 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
                            <a href="{{ route('reports.accounting.profit_loss.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
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

                @if($prevPeriod)
                    <div class="alert alert-secondary">
                        Previous period: <strong>{{ $prevPeriod['from_date'] ?? '' }}</strong> to <strong>{{ $prevPeriod['to_date'] ?? '' }}</strong>
                    </div>
                @endif

                @php
                    $hasAny = abs((float) ($totals['total_income'] ?? 0)) > 0.00001 || abs((float) ($totals['total_expenses'] ?? 0)) > 0.00001;
                @endphp

                @if(!$hasAny)
                    <div class="alert alert-info">No income or expenses found for selected period.</div>
                @else
                    <div class="card mb-3">
                        <div class="card-header"><strong>Totals</strong></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="card text-white bg-success">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="text-uppercase small">Total Income</div>
                                                    <div class="h4 mb-0">{{ $fmt($totals['total_income'] ?? 0) }}</div>
                                                </div>
                                                <i class="fas fa-arrow-circle-up fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <div class="card text-white bg-danger">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="text-uppercase small">Total Expenses</div>
                                                    <div class="h4 mb-0">{{ $fmt($totals['total_expenses'] ?? 0) }}</div>
                                                </div>
                                                <i class="fas fa-arrow-circle-down fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <div class="card text-white bg-info">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="text-uppercase small">{{ $totals['net_label'] ?? 'Net Profit' }}</div>
                                                    <div class="h4 mb-0">{{ $fmt($totals['net_profit'] ?? 0) }}</div>
                                                </div>
                                                <i class="fas fa-balance-scale fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @foreach(['income' => 'Income', 'expense' => 'Expenses'] as $sectionKey => $sectionLabel)
                        @php
                            $section = $tree[$sectionKey] ?? null;
                            $groups = $section['groups'] ?? [];
                        @endphp

                        <div class="card mb-3">
                            <div class="card-header"><strong>{{ $sectionLabel }}</strong></div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Account</th>
                                                <th class="text-right" style="width: 18%">Current</th>
                                                @if($hasCompare)
                                                    <th class="text-right" style="width: 18%">Previous</th>
                                                    <th class="text-right" style="width: 18%">Difference</th>
                                                @endif
                                                @if($showPct)
                                                    <th class="text-right" style="width: 10%">%</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach(($groups ?? []) as $g)
                                                <tr class="bg-light">
                                                    <td colspan="{{ 2 + ($hasCompare ? 2 : 0) + ($showPct ? 1 : 0) }}">
                                                        <strong>{{ $g['group_code'] ?? '' }} - {{ $g['group_name'] ?? '' }}</strong>
                                                    </td>
                                                </tr>

                                                @foreach(($g['accounts'] ?? []) as $a)
                                                    @php
                                                        $glUrl = route('reports.accounting.general_ledger.index', [
                                                            'account_id' => $a['account_id'] ?? 0,
                                                            'from_date' => request('from_date'),
                                                            'to_date' => request('to_date'),
                                                            'subshop_id' => $selectedSubshopId,
                                                        ]);
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <a href="{{ $glUrl }}">{{ $a['account_code'] ?? '' }} - {{ $a['account_name'] ?? '' }}</a>
                                                        </td>
                                                        <td class="text-right"><strong>{{ $fmt($a['amount'] ?? 0) }}</strong></td>
                                                        @if($hasCompare)
                                                            <td class="text-right">{{ $fmt($a['previous_amount'] ?? 0) }}</td>
                                                            <td class="text-right">{{ $fmt($a['difference'] ?? 0) }}</td>
                                                        @endif
                                                        @if($showPct)
                                                            <td class="text-right">{{ number_format((float) ($a['pct'] ?? 0), 2) }}</td>
                                                        @endif
                                                    </tr>
                                                @endforeach

                                                <tr class="bg-white">
                                                    <td class="text-right"><strong>Subtotal</strong></td>
                                                    <td class="text-right"><strong>{{ $fmt($g['subtotal'] ?? 0) }}</strong></td>
                                                    @if($hasCompare)
                                                        <td class="text-right"><strong>{{ $fmt($g['previous_subtotal'] ?? 0) }}</strong></td>
                                                        <td class="text-right"><strong>{{ $fmt($g['difference_subtotal'] ?? 0) }}</strong></td>
                                                    @endif
                                                    @if($showPct)
                                                        <td class="text-right"><strong>{{ number_format((float) ($g['pct'] ?? 0), 2) }}</strong></td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-light">
                                                <th class="text-right">Total {{ $sectionLabel }}</th>
                                                <th class="text-right">{{ $fmt($section['total'] ?? 0) }}</th>
                                                @if($hasCompare)
                                                    <th class="text-right">{{ $fmt($section['previous_total'] ?? 0) }}</th>
                                                    <th class="text-right">{{ $fmt($section['difference_total'] ?? 0) }}</th>
                                                @endif
                                                @if($showPct)
                                                    <th class="text-right">{{ number_format(100, 2) }}</th>
                                                @endif
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            @else
                <div class="alert alert-info">
                    Select a date range and apply filters to view the Profit &amp; Loss report.
                </div>
            @endif

        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
