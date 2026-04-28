@extends('adminlte::page')

@section('title', 'Customer Performance Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-line"></i> Customer Performance Report</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-chart-line"></i> Performance Report</h1>
                <p class="mb-0 text-light">Customer performance scoring and analysis dashboard</p>
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
        <li class="breadcrumb-item"><a href="{{ route('reports.customers_reports.index') }}">Customers Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Customer Performance</li>
    </ol>
</nav>
@stop

@section('content')
@php
    $fmt = function ($v) {
        return number_format((float) ($v ?? 0), 2);
    };
    
    $pct = function ($v) {
        return number_format((float) ($v ?? 0) * 100, 1) . '%';
    };
    
    $report = $report ?? [];
    $customers = $report['customers'] ?? collect();
    $metrics = $report['metrics'] ?? [];
    $topPerformers = $report['top_performers'] ?? collect();
    $worstPerformers = $report['worst_performers'] ?? collect();
    $chartData = $report['chart_data'] ?? [];
    
    $hasData = $customers->count() > 0;
    
    $performanceColors = [
        'Excellent' => 'success',
        'Good' => 'info',
        'Average' => 'warning',
        'Poor' => 'orange',
        'Defaulted' => 'danger',
    ];
    
    $performanceBgColors = [
        'Excellent' => '#28a745',
        'Good' => '#17a2b8',
        'Average' => '#ffc107',
        'Poor' => '#fd7e14',
        'Defaulted' => '#dc3545',
    ];
@endphp

<div class="container-fluid">
    <!-- Filters -->
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <form method="get" action="{{ route('reports.customers.customer_performance.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label for="from_date">From Date</label>
                            <input type="date" class="form-control form-control-sm" id="from_date" name="from_date" value="{{ request('from_date', $filters['from_date'] ?? '') }}">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="to_date">To Date</label>
                            <input type="date" class="form-control form-control-sm" id="to_date" name="to_date" value="{{ request('to_date', $filters['to_date'] ?? '') }}">
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
                            <label for="performance_level">Performance Level</label>
                            <select class="form-control form-control-sm" id="performance_level" name="performance_level">
                                <option value="">All Levels</option>
                                <option value="Excellent" {{ request('performance_level') == 'Excellent' ? 'selected' : '' }}>Excellent</option>
                                <option value="Good" {{ request('performance_level') == 'Good' ? 'selected' : '' }}>Good</option>
                                <option value="Average" {{ request('performance_level') == 'Average' ? 'selected' : '' }}>Average</option>
                                <option value="Poor" {{ request('performance_level') == 'Poor' ? 'selected' : '' }}>Poor</option>
                                <option value="Defaulted" {{ request('performance_level') == 'Defaulted' ? 'selected' : '' }}>Defaulted</option>
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

                        <div class="form-group col-md-1">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm btn-block">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                        <div class="form-group col-md-1">
                            <label>&nbsp;</label>
                            <a href="{{ route('reports.customers.customer_performance.index') }}" class="btn btn-secondary btn-sm btn-block">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Export Buttons -->
            <div class="d-flex justify-content-end mt-2">
                <a href="{{ $pdfUrl }}" class="btn btn-danger btn-sm mr-2" target="_blank">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="{{ $exportUrl }}" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
            </div>
        </div>
    </div>

    @if($hasData)
        <!-- Summary Metrics -->
        <div class="row mt-4">
            <div class="col-md-2">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Total Customers</h6>
                        <h2 class="mb-0">{{ $metrics['total_customers'] ?? 0 }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title">Excellent</h6>
                        <h2 class="mb-0">{{ $metrics['excellent_count'] ?? 0 }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6 class="card-title">Good</h6>
                        <h2 class="mb-0">{{ $metrics['good_count'] ?? 0 }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="card-title">Average</h6>
                        <h2 class="mb-0">{{ $metrics['average_count'] ?? 0 }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white" style="background-color: #fd7e14;">
                    <div class="card-body">
                        <h6 class="card-title">Poor</h6>
                        <h2 class="mb-0">{{ $metrics['poor_count'] ?? 0 }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="card-title">Defaulted</h6>
                        <h2 class="mb-0">{{ $metrics['defaulted_count'] ?? 0 }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="row mt-2">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Average Score</h6>
                        <h4 class="text-primary">{{ $metrics['average_score'] ?? 0 }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Disbursed</h6>
                        <h4 class="text-success">{{ $fmt($metrics['total_disbursed'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Paid</h6>
                        <h4 class="text-info">{{ $fmt($metrics['total_paid'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Outstanding</h6>
                        <h4 class="text-danger">{{ $fmt($metrics['total_outstanding'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mt-4">
            <!-- Performance Distribution Pie Chart -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Performance Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceDistributionChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Performers Bar Chart -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Top 10 Performers</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="topPerformersChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Performance Trend Line Chart -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Performance Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceTrendChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top & Worst Performers -->
        <div class="row mt-4">
            <!-- Top Performers -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-trophy"></i> Top 10 Performers</h5>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Customer</th>
                                    <th class="text-center">Score</th>
                                    <th class="text-center">Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topPerformers as $index => $customer)
                                    <tr>
                                        <td><span class="badge badge-success">{{ $index + 1 }}</span></td>
                                        <td>
                                            <a href="{{ route('customers.show', $customer->id) }}">
                                                {{ $customer->name ?? 'N/A' }}
                                            </a>
                                        </td>
                                        <td class="text-center"><strong>{{ $customer->performance_score ?? 0 }}</strong></td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $performanceColors[$customer->performance_level] ?? 'secondary' }}">
                                                {{ $customer->performance_level ?? 'N/A' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Worst Performers -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle"></i> Bottom 10 Performers</h5>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Customer</th>
                                    <th class="text-center">Score</th>
                                    <th class="text-center">Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($worstPerformers as $index => $customer)
                                    <tr>
                                        <td><span class="badge badge-danger">{{ $customers->count() - $worstPerformers->count() + $index + 1 }}</span></td>
                                        <td>
                                            <a href="{{ route('customers.show', $customer->id) }}">
                                                {{ $customer->name ?? 'N/A' }}
                                            </a>
                                        </td>
                                        <td class="text-center"><strong>{{ $customer->performance_score ?? 0 }}</strong></td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $performanceColors[$customer->performance_level] ?? 'secondary' }}">
                                                {{ $customer->performance_level ?? 'N/A' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Performance Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Customer Performance Details</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped table-hover" id="performanceTable">
                    <thead class="thead-dark">
                        <tr>
                            <th>Rank</th>
                            <th>Customer</th>
                            <th class="text-center">Loans</th>
                            <th class="text-right">Disbursed</th>
                            <th class="text-right">Paid</th>
                            <th class="text-right">Due</th>
                            <th class="text-center">Repayment Rate</th>
                            <th class="text-center">On-Time</th>
                            <th class="text-center">Late</th>
                            <th class="text-center">Missed</th>
                            <th class="text-right">Penalties</th>
                            <th class="text-center">Score</th>
                            <th class="text-center">Performance</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $index => $customer)
                            @php
                                $performanceLevel = $customer->performance_level ?? 'Average';
                                $rowClass = '';
                                if ($performanceLevel === 'Excellent') {
                                    $rowClass = 'table-success';
                                } elseif ($performanceLevel === 'Good') {
                                    $rowClass = 'table-info';
                                } elseif ($performanceLevel === 'Poor') {
                                    $rowClass = 'table-warning';
                                } elseif ($performanceLevel === 'Defaulted') {
                                    $rowClass = 'table-danger';
                                }
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td><span class="badge badge-secondary">{{ $index + 1 }}</span></td>
                                <td>
                                    <strong>{{ $customer->name ?? 'N/A' }}</strong><br>
                                    <small class="text-muted">{{ $customer->phone ?? '' }}</small>
                                </td>
                                <td class="text-center">{{ $customer->total_loans ?? 0 }}</td>
                                <td class="text-right">{{ $fmt($customer->total_disbursed ?? 0) }}</td>
                                <td class="text-right">{{ $fmt($customer->total_paid ?? 0) }}</td>
                                <td class="text-right">{{ $fmt($customer->total_due ?? 0) }}</td>
                                <td class="text-center">
                                    <span class="badge {{ ($customer->repayment_rate ?? 0) >= 0.8 ? 'badge-success' : (($customer->repayment_rate ?? 0) >= 0.5 ? 'badge-warning' : 'badge-danger') }}">
                                        {{ $pct($customer->repayment_rate ?? 0) }}
                                    </span>
                                </td>
                                <td class="text-center text-success">{{ $customer->on_time_payments ?? 0 }}</td>
                                <td class="text-center text-warning">{{ $customer->late_payments ?? 0 }}</td>
                                <td class="text-center text-danger">{{ $customer->missed_payments ?? 0 }}</td>
                                <td class="text-right text-warning">{{ $fmt($customer->total_penalties ?? 0) }}</td>
                                <td class="text-center">
                                    <span class="badge {{ ($customer->performance_score ?? 0) >= 80 ? 'badge-success' : (($customer->performance_score ?? 0) >= 60 ? 'badge-info' : (($customer->performance_score ?? 0) >= 40 ? 'badge-warning' : 'badge-danger')) }}">
                                        {{ $customer->performance_score ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-{{ $performanceColors[$performanceLevel] ?? 'secondary' }}">
                                        {{ $performanceLevel }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if(isset($customer->id))
                                        <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-sm btn-primary" title="View Profile">
                                            <i class="fas fa-user"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted">No performance data available</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- Empty State -->
        <div class="card mt-4">
            <div class="card-body text-center py-5">
                <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                <h4>No Performance Data Available</h4>
                <p class="text-muted">There are no customers matching your current filters.</p>
            </div>
        </div>
    @endif
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

@if($hasData)
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Performance Distribution Pie Chart
    const perfDistCtx = document.getElementById('performanceDistributionChart').getContext('2d');
    new Chart(perfDistCtx, {
        type: 'pie',
        data: {
            labels: {!! json_encode($chartData['performance_distribution']['labels'] ?? []) !!},
            datasets: [{
                data: {!! json_encode($chartData['performance_distribution']['data'] ?? []) !!},
                backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Top Performers Bar Chart
    const topPerfCtx = document.getElementById('topPerformersChart').getContext('2d');
    new Chart(topPerfCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($chartData['top_performers']['labels'] ?? []) !!},
            datasets: [{
                label: 'Performance Score',
                data: {!! json_encode($chartData['top_performers']['data'] ?? []) !!},
                backgroundColor: function(context) {
                    const value = context.raw;
                    if (value >= 80) return '#28a745';
                    if (value >= 60) return '#17a2b8';
                    if (value >= 40) return '#ffc107';
                    if (value >= 20) return '#fd7e14';
                    return '#dc3545';
                }
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { 
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    // Performance Trend Line Chart
    const trendCtx = document.getElementById('performanceTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($chartData['trend']['labels'] ?? []) !!},
            datasets: [{
                label: 'Average Score',
                data: {!! json_encode($chartData['trend']['data'] ?? []) !!},
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                fill: true,
                tension: 0.4
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
                    max: 100
                }
            }
        }
    });
});
</script>
@endif

@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
    .badge-orange {
        background-color: #fd7e14;
        color: white;
    }
</style>
@endpush
