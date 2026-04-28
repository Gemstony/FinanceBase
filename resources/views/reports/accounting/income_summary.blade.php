@extends('adminlte::page')

@section('title', 'Income Summary Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-pie"></i> Income Summary</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-chart-pie"></i> Income</h1>
                <p class="mb-0 text-light">Accrual-based income analytics</p>
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
        <li class="breadcrumb-item active" aria-current="page">Income Summary</li>
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

    $tree = $report['tree'] ?? [];
    $totals = $report['totals'] ?? [];
    $prev = $report['previous_period'] ?? null;
    $top = $report['top_income'] ?? [];
    $charts = $report['charts'] ?? [];
@endphp

<div class="container-fluid">
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">

            <form method="get" action="{{ route('reports.accounting.income_summary.index') }}" class="mb-3">
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
                                @foreach(($accountGroups ?? []) as $g)
                                    <option value="{{ $g?->id }}" {{ (string) request('account_group_id') === (string) $g?->id ? 'selected' : '' }}>{{ $g?->code }} - {{ $g?->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="income_account_id">Income Account</label>
                            <select class="form-control form-control-sm" id="income_account_id" name="income_account_id">
                                <option value="">All</option>
                                @foreach(($incomeAccounts ?? []) as $a)
                                    <option value="{{ $a->id }}" {{ (string) request('income_account_id') === (string) $a->id ? 'selected' : '' }}>{{ $a->account_code }} - {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
                            <a href="{{ route('reports.accounting.income_summary.index') }}" class="btn btn-outline-secondary btn-sm mr-2"><i class="fas fa-times"></i> Clear</a>

                            <a href="{{ $exportUrl ?? '#' }}" class="btn btn-success btn-sm mr-2" {{ empty($exportUrl) ? 'aria-disabled=true' : '' }}>
                                <i class="fas fa-file-excel"></i> Excel
                            </a>
                            <a href="{{ $pdfUrl ?? '#' }}" class="btn btn-danger btn-sm mr-2" {{ empty($pdfUrl) ? 'aria-disabled=true' : '' }}>
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                            <button type="button" onclick="window.print()" class="btn btn-outline-dark btn-sm">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if(empty($tree))
                <div class="alert alert-info mb-0">No income data found for selected period</div>
            @else
                <div class="row mb-3">
                    <div class="col-md-4 mb-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-uppercase small">Total Income</div>
                                        <div class="h4 mb-0">{{ $fmt($totals['total_income'] ?? 0) }}</div>
                                    </div>
                                    <i class="fas fa-coins fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card text-white bg-secondary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-uppercase small">Previous Period</div>
                                        <div class="h4 mb-0">{{ $fmt($totals['previous_total_income'] ?? 0) }}</div>
                                        @if(!empty($prev))
                                            <div class="small text-white-50">{{ $prev['from_date'] ?? '' }} to {{ $prev['to_date'] ?? '' }}</div>
                                        @endif
                                    </div>
                                    <i class="fas fa-history fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-uppercase small">Difference</div>
                                        <div class="h4 mb-0">{{ $fmt($totals['difference_total_income'] ?? 0) }}</div>
                                    </div>
                                    <i class="fas fa-exchange-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header"><strong>Income Distribution</strong></div>
                            <div class="card-body">
                                <canvas id="pieChart" height="180"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header"><strong>Top Income Sources</strong></div>
                            <div class="card-body">
                                <canvas id="barChart" height="180"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-header"><strong>Monthly Trend</strong></div>
                            <div class="card-body">
                                <canvas id="lineChart" height="90"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Top 5 Income Accounts</strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th class="text-right">Amount</th>
                                        <th class="text-right">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($top as $t)
                                        <tr>
                                            <td>
                                                <a href="{{ route('reports.accounting.general_ledger.index', ['account_id' => $t['account_id'] ?? 0, 'from_date' => request('from_date', $dateFrom ?? ''), 'to_date' => request('to_date', $dateTo ?? ''), 'subshop_id' => request('subshop_id')]) }}">
                                                    {{ $t['account_code'] ?? '' }} - {{ $t['account_name'] ?? '' }}
                                                </a>
                                                <div class="small text-muted">{{ $t['group_name'] ?? '' }}</div>
                                            </td>
                                            <td class="text-right">{{ $fmt($t['amount'] ?? 0) }}</td>
                                            <td class="text-right">{{ number_format((float) ($t['percentage'] ?? 0), 2) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Income Breakdown</strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Account Group</th>
                                        <th>Account</th>
                                        <th class="text-right">Amount</th>
                                        <th class="text-right">%</th>
                                        <th class="text-right">Previous</th>
                                        <th class="text-right">Difference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tree as $g)
                                        <tr class="table-secondary">
                                            <td colspan="6"><strong>{{ $g['group_code'] ?? '' }} - {{ $g['group_name'] ?? '' }}</strong></td>
                                        </tr>

                                        @foreach(($g['accounts'] ?? []) as $a)
                                            <tr>
                                                <td></td>
                                                <td>
                                                    <a href="{{ route('reports.accounting.general_ledger.index', ['account_id' => $a['account_id'] ?? 0, 'from_date' => request('from_date', $dateFrom ?? ''), 'to_date' => request('to_date', $dateTo ?? ''), 'subshop_id' => request('subshop_id')]) }}">
                                                        {{ $a['account_code'] ?? '' }} - {{ $a['account_name'] ?? '' }}
                                                    </a>
                                                </td>
                                                <td class="text-right">{{ $fmt($a['amount'] ?? 0) }}</td>
                                                <td class="text-right">{{ number_format((float) ($a['percentage'] ?? 0), 2) }}%</td>
                                                <td class="text-right">{{ $fmt($a['previous_amount'] ?? 0) }}</td>
                                                <td class="text-right">{{ $fmt($a['difference'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach

                                        <tr>
                                            <td colspan="2" class="text-right"><strong>Group Total</strong></td>
                                            <td class="text-right"><strong>{{ $fmt($g['subtotal'] ?? 0) }}</strong></td>
                                            <td class="text-right"></td>
                                            <td class="text-right"><strong>{{ $fmt($g['previous_subtotal'] ?? 0) }}</strong></td>
                                            <td class="text-right"><strong>{{ $fmt($g['difference_subtotal'] ?? 0) }}</strong></td>
                                        </tr>
                                    @endforeach
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

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const pieLabels = @json($charts['pie']['labels'] ?? []);
    const pieValues = @json($charts['pie']['values'] ?? []);
    const barLabels = @json($charts['bar']['labels'] ?? []);
    const barValues = @json($charts['bar']['values'] ?? []);
    const lineLabels = @json($charts['line']['labels'] ?? []);
    const lineValues = @json($charts['line']['values'] ?? []);

    if (document.getElementById('pieChart')) {
        new Chart(document.getElementById('pieChart'), {
            type: 'pie',
            data: { labels: pieLabels, datasets: [{ data: pieValues }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }

    if (document.getElementById('barChart')) {
        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: { labels: barLabels, datasets: [{ label: 'Amount', data: barValues, backgroundColor: '#28a745' }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    if (document.getElementById('lineChart')) {
        new Chart(document.getElementById('lineChart'), {
            type: 'line',
            data: { labels: lineLabels, datasets: [{ label: 'Total Income', data: lineValues, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.15)', fill: true, tension: 0.2 }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
        });
    }
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
