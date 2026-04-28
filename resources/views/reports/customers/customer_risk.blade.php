@extends('adminlte::page')

@section('title', 'Customer Risk Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exclamation-triangle"></i> Customer Risk Report</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exclamation-triangle"></i> Risk Report</h1>
                <p class="mb-0 text-light">Customer risk analysis and scoring dashboard</p>
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
        <li class="breadcrumb-item active" aria-current="page">Customer Risk</li>
    </ol>
</nav>
@stop

@section('content')
@php
    $fmt = function ($v) {
        return number_format((float) ($v ?? 0), 2);
    };
    
    $report = $report ?? [];
    $customers = $report['customers'] ?? collect();
    $metrics = $report['metrics'] ?? [];
    $chartData = $report['chart_data'] ?? [];
    $topRiskCustomers = $report['top_risk_customers'] ?? collect();
    
    $hasData = $customers->count() > 0;
@endphp

<div class="container-fluid">
    <!-- Filters -->
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <form method="get" action="{{ route('reports.customers.customer_risk.index') }}" class="mb-3">
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
                            <label for="risk_level">Risk Level</label>
                            <select class="form-control form-control-sm" id="risk_level" name="risk_level">
                                <option value="">All Levels</option>
                                <option value="Low Risk" {{ request('risk_level') == 'Low Risk' ? 'selected' : '' }}>Low Risk</option>
                                <option value="Medium Risk" {{ request('risk_level') == 'Medium Risk' ? 'selected' : '' }}>Medium Risk</option>
                                <option value="High Risk" {{ request('risk_level') == 'High Risk' ? 'selected' : '' }}>High Risk</option>
                                <option value="Defaulted" {{ request('risk_level') == 'Defaulted' ? 'selected' : '' }}>Defaulted</option>
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
                            <a href="{{ route('reports.customers.customer_risk.index') }}" class="btn btn-secondary btn-sm btn-block">
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
                        <h6 class="card-title">Low Risk</h6>
                        <h2 class="mb-0">{{ $metrics['low_risk_count'] ?? 0 }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="card-title">Medium Risk</h6>
                        <h2 class="mb-0">{{ $metrics['medium_risk_count'] ?? 0 }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-orange text-white" style="background-color: #fd7e14;">
                    <div class="card-body">
                        <h6 class="card-title">High Risk</h6>
                        <h2 class="mb-0">{{ $metrics['high_risk_count'] ?? 0 }}</h2>
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
            <div class="col-md-2">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6 class="card-title">Avg Risk Score</h6>
                        <h2 class="mb-0">{{ $metrics['average_risk_score'] ?? 0 }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="row mt-2">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Outstanding</h6>
                        <h4 class="text-primary">{{ $fmt($metrics['total_outstanding'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Overdue</h6>
                        <h4 class="text-danger">{{ $fmt($metrics['total_overdue'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Outstanding Penalties</h6>
                        <h4 class="text-warning">{{ $fmt($metrics['total_penalties'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mt-4">
            <!-- Risk Distribution Pie Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Risk Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="riskDistributionChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Aging Distribution Bar Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Aging Analysis</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="agingDistributionChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Trend Line Chart -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Risk Trend (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="riskTrendChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Risk Customers -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Top 10 Risk Customers</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="topRiskCustomersChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Risk Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Customer Risk Details</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped table-hover" id="riskTable">
                    <thead class="thead-dark">
                        <tr>
                            <th>Customer</th>
                            <th class="text-center">Loans</th>
                            <th class="text-right">Outstanding</th>
                            <th class="text-right">Overdue</th>
                            <th class="text-center">DPD</th>
                            <th class="text-right">Penalties</th>
                            <th class="text-center">Risk Score</th>
                            <th class="text-center">Risk Level</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                            @php
                                $riskLevel = $customer->risk_level ?? 'Low Risk';
                                $rowClass = '';
                                if ($riskLevel === 'Defaulted') {
                                    $rowClass = 'table-danger';
                                } elseif ($riskLevel === 'High Risk') {
                                    $rowClass = 'table-warning';
                                } elseif ($riskLevel === 'Medium Risk') {
                                    $rowClass = 'table-info';
                                }
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>
                                    <strong>{{ $customer->name ?? 'N/A' }}</strong><br>
                                    <small class="text-muted">{{ $customer->phone ?? '' }}</small>
                                </td>
                                <td class="text-center">{{ $customer->total_loans ?? 0 }}</td>
                                <td class="text-right">{{ $fmt($customer->outstanding_balance ?? 0) }}</td>
                                <td class="text-right text-danger">{{ $fmt($customer->overdue_amount ?? 0) }}</td>
                                <td class="text-center">{{ $customer->days_past_due ?? 0 }}</td>
                                <td class="text-right text-warning">{{ $fmt($customer->outstanding_penalties ?? 0) }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $customer->risk_score > 75 ? 'badge-danger' : ($customer->risk_score > 50 ? 'badge-warning' : ($customer->risk_score > 25 ? 'badge-info' : 'badge-success')) }}">
                                        {{ $customer->risk_score ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($riskLevel === 'Defaulted')
                                        <span class="badge badge-danger">Defaulted</span>
                                    @elseif($riskLevel === 'High Risk')
                                        <span class="badge badge-warning">High Risk</span>
                                    @elseif($riskLevel === 'Medium Risk')
                                        <span class="badge badge-info">Medium Risk</span>
                                    @else
                                        <span class="badge badge-success">Low Risk</span>
                                    @endif
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
                                <td colspan="9" class="text-center text-muted">No risk data available</td>
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
                <i class="fas fa-exclamation-circle fa-4x text-muted mb-3"></i>
                <h4>No Risk Data Available</h4>
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
    // Risk Distribution Pie Chart
    const riskDistCtx = document.getElementById('riskDistributionChart').getContext('2d');
    new Chart(riskDistCtx, {
        type: 'pie',
        data: {
            labels: {!! json_encode($chartData['risk_distribution']['labels'] ?? []) !!},
            datasets: [{
                data: {!! json_encode($chartData['risk_distribution']['data'] ?? []) !!},
                backgroundColor: ['#28a745', '#17a2b8', '#fd7e14', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Aging Distribution Bar Chart
    const agingDistCtx = document.getElementById('agingDistributionChart').getContext('2d');
    new Chart(agingDistCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($chartData['aging_distribution']['labels'] ?? []) !!},
            datasets: [{
                label: 'Overdue Amount',
                data: {!! json_encode($chartData['aging_distribution']['data'] ?? []) !!},
                backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Risk Trend Line Chart
    const riskTrendCtx = document.getElementById('riskTrendChart').getContext('2d');
    new Chart(riskTrendCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($chartData['risk_trend']['labels'] ?? []) !!},
            datasets: [{
                label: 'Average Risk Score',
                data: {!! json_encode($chartData['risk_trend']['data'] ?? []) !!},
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

    // Top Risk Customers Bar Chart
    const topRiskCtx = document.getElementById('topRiskCustomersChart').getContext('2d');
    new Chart(topRiskCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($chartData['top_risk_customers']['labels'] ?? []) !!},
            datasets: [{
                label: 'Risk Score',
                data: {!! json_encode($chartData['top_risk_customers']['data'] ?? []) !!},
                backgroundColor: function(context) {
                    const value = context.raw;
                    if (value > 75) return '#dc3545';
                    if (value > 50) return '#fd7e14';
                    if (value > 25) return '#17a2b8';
                    return '#28a745';
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
});
</script>
@endif

@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
