@extends('adminlte::page')

@section('title', 'Fees Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-file-invoice-dollar"></i> Fees Report</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-file-invoice-dollar"></i> Fees</h1>
                <p class="mb-0 text-light">Loan fees analytics and collection efficiency</p>
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
        <li class="breadcrumb-item active" aria-current="page">Fees Report</li>
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
    $summaryByFeeType = $report['summary_by_fee_type'] ?? collect();
    $metrics = $report['metrics'] ?? [];
    $glValidation = $report['gl_validation'] ?? [];
    $topFees = $report['top_fees'] ?? collect();
    $trendData = $report['trend_data'] ?? collect();
    $chartData = $report['chart_data'] ?? [];
@endphp

<div class="container-fluid">
    <!-- Filters -->
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <form method="get" action="{{ route('reports.accounting.fees.index') }}" class="mb-3">
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
                            <label for="fee_type_id">Fee Type</label>
                            <select class="form-control form-control-sm" id="fee_type_id" name="fee_type_id">
                                <option value="">All Fee Types</option>
                                @foreach($feeTypes as $ft)
                                    <option value="{{ $ft->id }}" {{ (request('fee_type_id') == $ft->id) ? 'selected' : '' }}>{{ $ft->name }} ({{ $ft->code }})</option>
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

                    <div class="form-row">
                        <div class="form-group col-md-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
                            <a href="{{ route('reports.accounting.fees.index') }}" class="btn btn-outline-secondary btn-sm mr-2"><i class="fas fa-times"></i> Clear</a>

                            <a href="{{ $exportUrl ?? '#' }}" class="btn btn-success btn-sm mr-2" {{ empty($exportUrl) ? 'aria-disabled=true' : '' }}>
                                <i class="fas fa-file-excel"></i> Excel
                            </a>
                            <a href="{{ $pdfUrl ?? '#' }}" class="btn btn-danger btn-sm" {{ empty($pdfUrl) ? 'aria-disabled=true' : '' }}>
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="row mt-3">
        <div class="col-md-3">
            <div class="info-box bg-gradient-white shadow-sm border">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-dollar-sign"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Charged</span>
                    <span class="info-box-number">{{ $fmt($metrics['total_charged'] ?? 0) }}</span>
                    <span class="progress-description">
                        {{ $metrics['total_transactions'] ?? 0 }} transactions
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-gradient-white shadow-sm border">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Paid</span>
                    <span class="info-box-number">{{ $fmt($metrics['total_paid'] ?? 0) }}</span>
                    <span class="progress-description">
                        {{ $metrics['paid_count'] ?? 0 }} paid
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-gradient-white shadow-sm border">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-exclamation-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Outstanding</span>
                    <span class="info-box-number">{{ $fmt($metrics['total_outstanding'] ?? 0) }}</span>
                    <span class="progress-description">
                        {{ $metrics['outstanding_count'] ?? 0 }} outstanding
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-gradient-white shadow-sm border">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-percentage"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Collection Rate</span>
                    <span class="info-box-number">{{ number_format($metrics['collection_rate'] ?? 0, 1) }}%</span>
                    <span class="progress-description">
                        Fee collection efficiency
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mt-3">
        <!-- Pie Chart - Fee Distribution -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Fee Distribution</h6>
                </div>
                <div class="card-body">
                    @if(count($chartData['pie_chart']['labels'] ?? []) > 0)
                        <canvas id="pieChart"></canvas>
                    @else
                        <div class="text-center text-muted py-5">No data available</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Bar Chart - Top Fees -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Top Fees</h6>
                </div>
                <div class="card-body">
                    @if(count($chartData['bar_chart']['labels'] ?? []) > 0)
                        <canvas id="barChart"></canvas>
                    @else
                        <div class="text-center text-muted py-5">No data available</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Line Chart - Trend -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="fas fa-chart-line"></i> Monthly Trend</h6>
                </div>
                <div class="card-body">
                    @if(count($chartData['line_chart']['labels'] ?? []) > 0)
                        <canvas id="lineChart"></canvas>
                    @else
                        <div class="text-center text-muted py-5">No data available</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Summary by Fee Type -->
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="fas fa-table"></i> Summary by Fee Type</h6>
                </div>
                <div class="card-body">
                    @if($summaryByFeeType->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Fee Type</th>
                                        <th class="text-right">Charged</th>
                                        <th class="text-right">Paid</th>
                                        <th class="text-right">Outstanding</th>
                                        <th class="text-right">Collection %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($summaryByFeeType as $fee)
                                        <tr>
                                            <td>
                                                <a href="{{ route('reports.accounting.fees.index', array_merge(request()->query(), ['fee_type_id' => $fee->fee_id])) }}">
                                                    {{ $fee->fee_name }}
                                                </a>
                                                <small class="text-muted d-block">{{ $fee->fee_code }}</small>
                                            </td>
                                            <td class="text-right">{{ $fmt($fee->total_charged) }}</td>
                                            <td class="text-right">{{ $fmt($fee->total_paid) }}</td>
                                            <td class="text-right {{ $fee->total_outstanding > 0 ? 'text-danger' : '' }}">{{ $fmt($fee->total_outstanding) }}</td>
                                            <td class="text-right">
                                                <div class="progress" style="height: 20px; width: 100px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $fee->collection_rate }}%">
                                                        {{ $fee->collection_rate }}%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td>Total</td>
                                        <td class="text-right">{{ $fmt($metrics['total_charged'] ?? 0) }}</td>
                                        <td class="text-right">{{ $fmt($metrics['total_paid'] ?? 0) }}</td>
                                        <td class="text-right {{ ($metrics['total_outstanding'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $fmt($metrics['total_outstanding'] ?? 0) }}</td>
                                        <td class="text-right">{{ number_format($metrics['collection_rate'] ?? 0, 1) }}%</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">No fee data found for selected period</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- GL Validation -->
    @if(count($glValidation) > 0)
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="fas fa-calculator"></i> GL Validation</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">Fee Income (GL)</small>
                            <h5>{{ $fmt($glValidation['fee_income_gl'] ?? 0) }}</h5>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Fee Income Accounts</small>
                            <h5>{{ $glValidation['fee_income_accounts'] ?? 0 }}</h5>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Variance</small>
                            <h5 class="{{ ($glValidation['fee_income_gl'] ?? 0) != ($metrics['total_paid'] ?? 0) ? 'text-warning' : 'text-success' }}">
                                {{ $fmt(abs(($glValidation['fee_income_gl'] ?? 0) - ($metrics['total_paid'] ?? 0))) }}
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Detailed Table -->
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="fas fa-list"></i> Detailed Fee Records</h6>
                </div>
                <div class="card-body">
                    @if($details->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Loan</th>
                                        <th>Customer</th>
                                        <th>Product</th>
                                        <th>Fee Type</th>
                                        <th class="text-right">Charged</th>
                                        <th class="text-right">Paid</th>
                                        <th class="text-right">Outstanding</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($details as $detail)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($detail->applied_on)->format('d/m/Y') }}</td>
                                            <td>
                                                <a href="{{ route('loans.loans.show', $detail->loan_code) }}" target="_blank">
                                                    {{ $detail->loan_code }}
                                                </a>
                                            </td>
                                            <td>
                                                <a href="{{ route('customers.show', $detail->customer_id) }}">
                                                    {{ $detail->customer_name }}
                                                </a>
                                                <small class="text-muted d-block">{{ $detail->customer_phone }}</small>
                                            </td>
                                            <td>{{ $detail->loan_product_name }}</td>
                                            <td>
                                                <a href="{{ route('reports.accounting.fees.index', array_merge(request()->query(), ['fee_type_id' => $detail->fee_id])) }}">
                                                    {{ $detail->fee_name }}
                                                </a>
                                            </td>
                                            <td class="text-right">{{ $fmt($detail->amount) }}</td>
                                            <td class="text-right">{{ $fmt($detail->paid_amount) }}</td>
                                            <td class="text-right {{ $detail->outstanding_amount > 0 ? 'text-danger font-weight-bold' : 'text-success' }}">
                                                {{ $fmt($detail->outstanding_amount) }}
                                            </td>
                                            <td>
                                                @if($detail->outstanding_amount > 0)
                                                    <span class="badge badge-warning">Outstanding</span>
                                                @else
                                                    <span class="badge badge-success">Paid</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">No fee data found for selected period</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pie Chart - Fee Distribution
    const pieData = @json($chartData['pie_chart'] ?? []);
    if (pieData.labels && pieData.labels.length > 0) {
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: pieData.labels,
                datasets: [{
                    data: pieData.values,
                    backgroundColor: pieData.colors || [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 10 }
                        }
                    }
                }
            }
        });
    }

    // Bar Chart - Top Fees
    const barData = @json($chartData['bar_chart'] ?? []);
    if (barData.labels && barData.labels.length > 0) {
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: barData.labels,
                datasets: [{
                    label: 'Total Charged',
                    data: barData.values,
                    backgroundColor: barData.colors || '#36A2EB'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('en-US').format(value);
                            }
                        }
                    }
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
                    label: 'Total Fees',
                    data: lineData.values,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('en-US').format(value);
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
@stop

@section('css')

<style>
    .table .text-right {
        text-align: right !important;
    }
    .badge {
        padding: 5px 10px;
    }
</style>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">

@endpush
