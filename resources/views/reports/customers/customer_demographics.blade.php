@extends('adminlte::page')

@section('title', 'Customer Demographics Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-pie"></i> Customer Demographics Report</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-chart-pie"></i> Demographics</h1>
                <p class="mb-0 text-light">Customer population characteristics and segmentation analysis</p>
            </div>
            <a href="{{ route('reports.customers_reports.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('reports.customers_reports.index') }}">Customers Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Demographics</li>
    </ol>
</nav>
@stop

@section('content')
@php
    $report = $report ?? [];
    $metrics = $report['metrics'] ?? [];
    $genderDistribution = $report['gender_distribution'] ?? [];
    $ageDistribution = $report['age_distribution'] ?? [];
    $regionDistribution = $report['region_distribution'] ?? [];
    $occupationDistribution = $report['occupation_distribution'] ?? [];
    $categoryDistribution = $report['category_distribution'] ?? [];
    $idTypeDistribution = $report['id_type_distribution'] ?? [];
    $registrationTrends = $report['registration_trends'] ?? [];
    $topRegions = $report['top_regions'] ?? [];
    $chartData = $report['chart_data'] ?? [];
    
    $hasData = ($metrics['total_customers'] ?? 0) > 0;
    
    $genderChart = $chartData['gender_chart'] ?? [];
    $ageChart = $chartData['age_chart'] ?? [];
    $regionChart = $chartData['region_chart'] ?? [];
    $trendChart = $chartData['trend_chart'] ?? [];
@endphp

<div class="container-fluid">
    <!-- Filters -->
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <form method="get" action="{{ route('reports.customers.customer_demographics.index') }}" class="mb-3">
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
                            <label for="gender">Gender</label>
                            <select class="form-control form-control-sm" id="gender" name="gender">
                                <option value="">All Genders</option>
                                <option value="M" {{ request('gender') == 'M' ? 'selected' : '' }}>Male</option>
                                <option value="F" {{ request('gender') == 'F' ? 'selected' : '' }}>Female</option>
                                <option value="O" {{ request('gender') == 'O' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="region">Region</label>
                            <select class="form-control form-control-sm" id="region" name="region">
                                <option value="">All Regions</option>
                                @foreach($report['regions'] ?? [] as $r)
                                    <option value="{{ $r }}" {{ request('region') == $r ? 'selected' : '' }}>{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="category">Category</label>
                            <select class="form-control form-control-sm" id="category" name="category">
                                <option value="">All Categories</option>
                                @foreach($report['categories'] ?? [] as $c)
                                    <option value="{{ $c }}" {{ request('category') == $c ? 'selected' : '' }}>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label for="is_active">Status</label>
                            <select class="form-control form-control-sm" id="is_active" name="is_active">
                                <option value="">All Statuses</option>
                                <option value="active" {{ request('is_active') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('is_active') == 'inactive' ? 'selected' : '' }}>Inactive</option>
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
                            <a href="{{ route('reports.customers.customer_demographics.index') }}" class="btn btn-secondary btn-sm btn-block">
                                <i class="fas fa-reset"></i> Clear
                            </a>
                        </div>
                        <div class="form-group col-md-2">
                            <label>&nbsp;</label>
                            <div class="btn-group btn-block" role="group">
                                <a href="{{ $pdfUrl }}" class="btn btn-danger btn-sm" target="_blank">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </a>
                                <a href="{{ $exportUrl }}" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel"></i> Excel
                                </a>
                            </div>
                        </div>
                        <div class="form-group col-md-2">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-info btn-sm btn-block" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($hasData)
        <!-- Summary Metrics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Customers</h5>
                        <h2 class="mb-0">{{ number_format($metrics['total_customers'] ?? 0) }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Active Customers</h5>
                        <h2 class="mb-0">{{ number_format($metrics['active_customers'] ?? 0) }}</h2>
                        <small>{{ $metrics['total_customers'] > 0 ? round(($metrics['active_customers'] / $metrics['total_customers']) * 100, 1) : 0 }}% of total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Inactive Customers</h5>
                        <h2 class="mb-0">{{ number_format($metrics['inactive_customers'] ?? 0) }}</h2>
                        <small>{{ $metrics['total_customers'] > 0 ? round(($metrics['inactive_customers'] / $metrics['total_customers']) * 100, 1) : 0 }}% of total</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row mb-4">
            <!-- Gender Distribution Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Gender Distribution</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="genderChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Age Distribution Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-bar"></i> Age Distribution</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="ageChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row mb-4">
            <!-- Region Distribution Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-bar"></i> Top Regions</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="regionChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Registration Trends Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-line"></i> Customer Registration Trend</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Tables -->
        <div class="row">
            <!-- Gender Distribution Table -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Gender Distribution</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Gender</th>
                                    <th class="text-right">Count</th>
                                    <th class="text-right">Percentage</th>
                                    <!-- <th></th> -->
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($genderDistribution as $item)
                                    <tr>
                                        <td>{{ $item['gender'] }}</td>
                                        <td class="text-right">{{ number_format($item['count']) }}</td>
                                        <td class="text-right">{{ $item['percentage'] }}%</td>
                                        <!-- <td class="text-right">
                                            <a href="{{ route('reports.customers.customer_demographics.by_gender', $item['gender']) }}" class="btn btn-xs btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td> -->
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No data available</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Age Distribution Table -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Age Group Distribution</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Age Group</th>
                                    <th class="text-right">Count</th>
                                    <th class="text-right">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ageDistribution as $item)
                                    <tr>
                                        <td>{{ $item['age_group'] }}</td>
                                        <td class="text-right">{{ number_format($item['count']) }}</td>
                                        <td class="text-right">{{ $item['percentage'] }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No data available</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Region Distribution Table -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Geographic Distribution (by Region)</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Region</th>
                                    <th class="text-right">Count</th>
                                    <th class="text-right">Percentage</th>
                                    <!-- <th></th> -->
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($regionDistribution as $item)
                                    <tr>
                                        <td>{{ $item['region'] }}</td>
                                        <td class="text-right">{{ number_format($item['count']) }}</td>
                                        <td class="text-right">{{ $item['percentage'] }}%</td>
                                        <!-- <td class="text-right">
                                            <a href="{{ route('reports.customers.customer_demographics.by_region', $item['region']) }}" class="btn btn-xs btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td> -->
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No data available</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Occupation Distribution Table -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Occupation Distribution</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Occupation</th>
                                    <th class="text-right">Count</th>
                                    <th class="text-right">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($occupationDistribution as $item)
                                    <tr>
                                        <td>{{ $item['occupation'] }}</td>
                                        <td class="text-right">{{ number_format($item['count']) }}</td>
                                        <td class="text-right">{{ $item['percentage'] }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No data available</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Category Distribution Table -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Customer Category Distribution</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-right">Count</th>
                                    <th class="text-right">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categoryDistribution as $item)
                                    <tr>
                                        <td>{{ $item['category'] }}</td>
                                        <td class="text-right">{{ number_format($item['count']) }}</td>
                                        <td class="text-right">{{ $item['percentage'] }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No data available</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ID Type Distribution Table -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">ID Type Distribution</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>ID Type</th>
                                    <th class="text-right">Count</th>
                                    <th class="text-right">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($idTypeDistribution as $item)
                                    <tr>
                                        <td>{{ $item['id_type'] }}</td>
                                        <td class="text-right">{{ number_format($item['count']) }}</td>
                                        <td class="text-right">{{ $item['percentage'] }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No data available</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Trends Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Monthly Registration Trends</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th class="text-right">New Customers</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($registrationTrends as $trend)
                                    <tr>
                                        <td>{{ $trend['month'] }}</td>
                                        <td class="text-right">{{ number_format($trend['count']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center">No data available</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    @else
        <!-- Empty State -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-chart-pie fa-4x text-muted mb-3"></i>
                <h4>No Demographic Data Available</h4>
                <p class="text-muted">There are no customers matching the selected criteria.</p>
                <a href="{{ route('reports.customers.customer_demographics.index') }}" class="btn btn-primary">
                    <i class="fas fa-reset"></i> Reset Filters
                </a>
            </div>
        </div>
    @endif
</div>

@push('css')
<style>
    @media print {
        .btn, .breadcrumb, .card-header, .form-inline {
            display: none !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        .card-body {
            padding: 0 !important;
        }
    }
</style>
@endpush
@stop


@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@if($hasData)
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gender Distribution Pie Chart
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    new Chart(genderCtx, {
        type: 'pie',
        data: {
            labels: {!! json_encode($genderChart['labels'] ?? []) !!},
            datasets: [{
                data: {!! json_encode($genderChart['values'] ?? []) !!},
                backgroundColor: {!! json_encode($genderChart['colors'] ?? []) !!}
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });

    // Age Distribution Bar Chart
    const ageCtx = document.getElementById('ageChart').getContext('2d');
    new Chart(ageCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($ageChart['labels'] ?? []) !!},
            datasets: [{
                label: 'Customers',
                data: {!! json_encode($ageChart['values'] ?? []) !!},
                backgroundColor: {!! json_encode($ageChart['colors'] ?? []) !!}
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Region Distribution Bar Chart
    const regionCtx = document.getElementById('regionChart').getContext('2d');
    new Chart(regionCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($regionChart['labels'] ?? []) !!},
            datasets: [{
                label: 'Customers',
                data: {!! json_encode($regionChart['values'] ?? []) !!},
                backgroundColor: {!! json_encode($regionChart['colors'] ?? []) !!}
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });

    // Registration Trend Line Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($trendChart['labels'] ?? []) !!},
            datasets: [{
                label: 'New Customers',
                data: {!! json_encode($trendChart['values'] ?? []) !!},
                borderColor: '#36A2EB',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
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
