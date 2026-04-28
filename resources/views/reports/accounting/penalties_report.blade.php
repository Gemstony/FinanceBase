@extends('adminlte::page')

@section('title', 'Penalties Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exclamation-triangle"></i> Penalties Report</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exclamation-triangle"></i> Penalties</h1>
                <p class="mb-0 text-light">Loan penalties analytics and collection efficiency</p>
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
        <li class="breadcrumb-item active" aria-current="page">Penalties Report</li>
    </ol>
</nav>
@stop

@section('content')
@php
    $fmt = function ($v) {
        return number_format((float) ($v ?? 0), 2);
    };
    
    $report = $report ?? [];
    $details = $report['details'] ?? collect();
    $summaryByPenaltyType = $report['summary_by_penalty_type'] ?? collect();
    $metrics = $report['metrics'] ?? [];
    $glValidation = $report['gl_validation'] ?? [];
    $topDefaulters = $report['top_defaulters'] ?? collect();
    $trendData = $report['trend_data'] ?? collect();
    $agingAnalysis = $report['aging_analysis'] ?? [];
    $chartData = $report['chart_data'] ?? [];
    
    $hasData = $details->count() > 0 || $metrics['total_applied'] > 0;
@endphp

<div class="container-fluid">
    <!-- Filters -->
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <form method="get" action="{{ route('reports.accounting.penalties.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label for="from_date">From Date</label>
                            <input type="date" class="form-control form-control-sm" id="from_date" name="date_from" value="{{ request('date_from', $dateFrom ?? '') }}">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="to_date">To Date</label>
                            <input type="date" class="form-control form-control-sm" id="to_date" name="date_to" value="{{ request('date_to', $dateTo ?? '') }}">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="subshop_id">Branch</label>
                            <select class="form-control form-control-sm" id="subshop_id" name="subshop_id">
                                <option value="">All Branches</option>
                                @foreach($subshops as $s)
                                    <option value="{{ $s->id }}" {{ ($selectedSubshopId ?? null) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="penalty_type_id">Penalty Type</label>
                            <select class="form-control form-control-sm" id="penalty_type_id" name="penalty_type_id">
                                <option value="">All Penalty Types</option>
                                @foreach($penaltyTypes as $pt)
                                    <option value="{{ $pt->id }}" {{ (request('penalty_type_id') == $pt->id) ? 'selected' : '' }}>{{ $pt->name }} ({{ $pt->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="loan_product_id">Loan Product</label>
                            <select class="form-control form-control-sm" id="loan_product_id" name="loan_product_id">
                                <option value="">All Products</option>
                                @foreach($loanProducts as $lp)
                                    <option value="{{ $lp->id }}" {{ (request('loan_product_id') == $lp->id) ? 'selected' : '' }}>{{ $lp->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="status">Status</label>
                            <select class="form-control form-control-sm" id="status" name="status">
                                <option value="">All</option>
                                <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="outstanding" {{ request('status') == 'outstanding' ? 'selected' : '' }}>Outstanding</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row mt-2">
                        <div class="form-group col-md-12">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="{{ route('reports.accounting.penalties.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-reset"></i> Reset
                            </a>
                            <div class="btn-group float-right" role="group">
                                <a href="{{ $exportUrl }}" class="btn btn-success btn-sm" target="_blank">
                                    <i class="fas fa-file-excel"></i> Excel
                                </a>
                                <a href="{{ $pdfUrl }}" class="btn btn-danger btn-sm" target="_blank">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </a>
                                <button type="button" class="btn btn-info btn-sm" onclick="window.print()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($hasData)
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #dc3545 !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Applied</h6>
                    <h4 class="mb-0 font-weight-bold">{{ $fmt($metrics['total_applied']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #28a745 !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Paid</h6>
                    <h4 class="mb-0 font-weight-bold text-success">{{ $fmt($metrics['total_paid']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #ffc107 !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Outstanding</h6>
                    <h4 class="mb-0 font-weight-bold text-warning">{{ $fmt($metrics['total_outstanding']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #17a2b8 !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Collection Rate</h6>
                    <h4 class="mb-0 font-weight-bold">{{ number_format($metrics['collection_rate'] ?? 0, 1) }}%</h4>
                    <small class="text-muted">{{ $metrics['paid_count'] ?? 0 }} paid / {{ $metrics['outstanding_count'] ?? 0 }} outstanding</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Pie Chart - Penalty Distribution -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Penalty Distribution by Type</h5>
                </div>
                <div class="card-body">
                    <canvas id="pieChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Bar Chart - Top Defaulters -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-user-clock"></i> Top Defaulters</h5>
                </div>
                <div class="card-body">
                    <canvas id="barChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Line Chart - Trend -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i fa-chart-line"></i> Penalty Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="lineChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Bar Chart - Aging Analysis -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Aging Analysis</h5>
                </div>
                <div class="card-body">
                    <canvas id="agingChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary by Penalty Type Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-list"></i> Summary by Penalty Type</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>Penalty Type</th>
                        <th class="text-right">Applied</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-center">Collection %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaryByPenaltyType as $penalty)
                    <tr>
                        <td>
                            <strong>{{ $penalty->penalty_name }}</strong>
                            <br><small class="text-muted">{{ $penalty->penalty_code }} ({{ $penalty->penalty_type }})</small>
                        </td>
                        <td class="text-right">{{ $fmt($penalty->total_applied) }}</td>
                        <td class="text-right">{{ $fmt($penalty->total_paid) }}</td>
                        <td class="text-right">{{ $fmt($penalty->total_outstanding) }}</td>
                        <td class="text-center">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $penalty->collection_rate }}%">
                                    {{ $penalty->collection_rate }}%
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">No penalty data found</td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot class="font-weight-bold">
                    <tr class="table-secondary">
                        <td>Total</td>
                        <td class="text-right">{{ $fmt($metrics['total_applied']) }}</td>
                        <td class="text-right">{{ $fmt($metrics['total_paid']) }}</td>
                        <td class="text-right">{{ $fmt($metrics['total_outstanding']) }}</td>
                        <td class="text-center">{{ number_format($metrics['collection_rate'] ?? 0, 1) }}%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Detailed Records Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-list"></i> Detailed Penalty Records</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped table-hover table-sm" id="penaltyTable">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        <th>Loan</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Product</th>
                        <th>Penalty Type</th>
                        <th class="text-right">Applied</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-center">Days Past Due</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($details as $detail)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($detail->applied_on)->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('loans.loans.show', $detail->loan_code) }}" target="_blank">{{ $detail->loan_code }}</a>
                        </td>
                        <td>
                            <a href="{{ route('customers.show', $detail->customer_id) }}" target="_blank">{{ $detail->customer_name }}</a>
                        </td>
                        <td>{{ $detail->customer_phone ?? '-' }}</td>
                        <td>{{ $detail->loan_product_name ?? '-' }}</td>
                        <td>{{ $detail->penalty_name ?? '-' }}</td>
                        <td class="text-right">{{ $fmt($detail->amount) }}</td>
                        <td class="text-right">{{ $fmt($detail->paid_amount) }}</td>
                        <td class="text-right {{ $detail->outstanding_amount > 0 ? 'text-danger font-weight-bold' : 'text-success' }}">
                            {{ $fmt($detail->outstanding_amount) }}
                        </td>
                        <td class="text-center">
                            @if($detail->days_past_due > 0)
                                <span class="badge badge-{{ $detail->days_past_due > 90 ? 'danger' : ($detail->days_past_due > 60 ? 'warning' : ($detail->days_past_due > 30 ? 'info' : 'secondary')) }}">
                                    {{ $detail->days_past_due }} days
                                </span>
                            @else
                                <span class="badge badge-success">Current</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($detail->outstanding_amount > 0)
                                <span class="badge badge-danger">Outstanding</span>
                            @else
                                <span class="badge badge-success">Paid</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted">No penalty records found for the selected period</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            
            <!-- Pagination -->
            @if($details->count() > 0)
            <div class="mt-3">
                <small class="text-muted">Showing {{ $details->count() }} records</small>
            </div>
            @endif
        </div>
    </div>

    <!-- GL Validation -->
    @if(!empty($glValidation))
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-balance-scale"></i> Accounting Validation</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Penalty Income (GL):</strong></p>
                    <h4>{{ $fmt($glValidation['penalty_income_gl']) }}</h4>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Income Accounts Found:</strong></p>
                    <h4>{{ $glValidation['penalty_income_accounts'] ?? 0 }}</h4>
                </div>
            </div>
            <small class="text-muted">Cross-check with Journal Entry Lines for penalty income accounts</small>
        </div>
    </div>
    @endif

    @else
    <!-- Empty State -->
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-exclamation-triangle fa-4x text-muted mb-3"></i>
            <h4>No Penalty Data Found</h4>
            <p class="text-muted">No penalty data found for the selected period.</p>
            <a href="{{ route('reports.accounting.penalties.index') }}" class="btn btn-primary">
                <i class="fas fa-refresh"></i> Reset Filters
            </a>
        </div>
    </div>
    @endif
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

@if($hasData)
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pie Chart - Penalty Distribution
    const pieData = @json($chartData['pie_chart'] ?? []);
    if (pieData.labels && pieData.labels.length > 0) {
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: pieData.labels,
                datasets: [{
                    data: pieData.values,
                    backgroundColor: pieData.colors || ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    // Bar Chart - Top Defaulters
    const barData = @json($chartData['bar_chart'] ?? []);
    if (barData.labels && barData.labels.length > 0) {
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: barData.labels,
                datasets: [{
                    label: 'Outstanding Penalties',
                    data: barData.values,
                    backgroundColor: barData.colors || ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Line Chart - Trend
    const lineData = @json($chartData['line_chart'] ?? []);
    if (lineData.labels && lineData.labels.length > 0) {
        const lineCtx = document.getElementById('lineChart').getContext('2d');
        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: lineData.labels,
                datasets: [{
                    label: 'Total Penalties',
                    data: lineData.values,
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Aging Chart
    const agingData = @json($chartData['aging_chart'] ?? []);
    if (agingData.labels && agingData.labels.length > 0) {
        const agingCtx = document.getElementById('agingChart').getContext('2d');
        new Chart(agingCtx, {
            type: 'bar',
            data: {
                labels: agingData.labels,
                datasets: [{
                    label: 'Outstanding Amount',
                    data: agingData.values,
                    backgroundColor: agingData.colors || ['#4BC0C0', '#FFCE56', '#FF9F40', '#FF6384']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>
@endif

@stop

@section('css')
<style>
@media print {
    .btn-group, .breadcrumb, .card-header { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    body { font-size: 12px; }
}
</style>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
