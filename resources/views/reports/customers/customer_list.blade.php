@extends('adminlte::page')

@section('title', 'Customer List Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-users"></i> Customer List Report</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-users"></i> Customers</h1>
                <p class="mb-0 text-light">Customer overview and loan portfolio analysis</p>
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
        <li class="breadcrumb-item active" aria-current="page">Customer List</li>
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
    
    $hasData = $customers->count() > 0;
@endphp

<div class="container-fluid">
    <!-- Filters -->
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <form method="get" action="{{ route('reports.customers.customer_list.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="search">Search</label>
                            <input type="text" class="form-control form-control-sm" id="search" name="search" value="{{ request('search', $filters['search'] ?? '') }}" placeholder="Name, Phone, Email">
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
                            <label for="customer_status">Customer Status</label>
                            <select class="form-control form-control-sm" id="customer_status" name="customer_status">
                                <option value="">All</option>
                                <option value="active" {{ request('customer_status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('customer_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="loan_status">Loan Status</label>
                            <select class="form-control form-control-sm" id="loan_status" name="loan_status">
                                <option value="">All</option>
                                <option value="active" {{ request('loan_status') == 'active' ? 'selected' : '' }}>With Active Loans</option>
                                <option value="closed" {{ request('loan_status') == 'closed' ? 'selected' : '' }}>With Closed Loans</option>
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
                    </div>
                    <div class="form-row mt-2">
                        <div class="form-group col-md-12">
                            <a href="{{ route('reports.customers.customer_list.index') }}" class="btn btn-secondary btn-sm">
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
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #007BFF !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Customers</h6>
                    <h4 class="mb-0 font-weight-bold">{{ $metrics['total_customers'] ?? 0 }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #28a745 !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Active Customers</h6>
                    <h4 class="mb-0 font-weight-bold text-success">{{ $metrics['active_customers'] ?? 0 }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #17a2b8 !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-1">With Loans</h6>
                    <h4 class="mb-0 font-weight-bold text-info">{{ $metrics['customers_with_loans'] ?? 0 }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #dc3545 !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Defaulted</h6>
                    <h4 class="mb-0 font-weight-bold text-danger">{{ $metrics['defaulted_customers'] ?? 0 }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Pie Chart - Customer Status Distribution -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Customer Distribution by Risk Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="pieChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Bar Chart - Top Customers by Loan Size -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Top Customers by Loan Amount</h5>
                </div>
                <div class="card-body">
                    <canvas id="barChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-list"></i> Customer List</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped table-hover table-sm" id="customerTable">
                <thead class="thead-light">
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th class="text-center">Loans</th>
                        <th class="text-right">Disbursed</th>
                        <th class="text-right">Repaid</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-right">Overdue</th>
                        <th>Risk Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr class="{{ $customer->risk_status == 'Defaulted' ? 'table-danger' : ($customer->risk_status == 'At Risk' ? 'table-warning' : '') }}">
                        <td>
                            <a href="{{ route('customers.show', $customer->id) }}" target="_blank">
                                {{ $customer->name }}
                            </a>
                        </td>
                        <td>{{ $customer->phone ?? '-' }}</td>
                        <td>{{ $customer->email ?? '-' }}</td>
                        <td>
                            @if($customer->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-center">
                            {{ $customer->total_loans ?? 0 }} 
                            <small class="text-muted">({{ $customer->active_loans ?? 0 }} active)</small>
                        </td>
                        <td class="text-right">{{ $fmt($customer->total_disbursed) }}</td>
                        <td class="text-right">{{ $fmt($customer->total_repaid) }}</td>
                        <td class="text-right {{ $customer->outstanding_balance > 0 ? 'font-weight-bold' : 'text-success' }}">
                            {{ $fmt($customer->outstanding_balance) }}
                        </td>
                        <td class="text-right {{ $customer->overdue_amount > 0 ? 'text-danger font-weight-bold' : '' }}">
                            {{ $fmt($customer->overdue_amount) }}
                        </td>
                        <td>
                            @switch($customer->risk_status)
                                @case('Defaulted')
                                    <span class="badge badge-danger">Defaulted</span>
                                    @break
                                @case('At Risk')
                                    <span class="badge badge-warning">At Risk</span>
                                    @break
                                @case('Good')
                                    <span class="badge badge-success">Good</span>
                                    @break
                                @default
                                    <span class="badge badge-secondary">{{ $customer->risk_status }}</span>
                            @endswitch
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted">No customers found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            
            @if($customers->count() > 0)
            <div class="mt-3">
                <small class="text-muted">Showing {{ $customers->count() }} customers</small>
            </div>
            @endif
        </div>
    </div>

    @else
    <!-- Empty State -->
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-users fa-4x text-muted mb-3"></i>
            <h4>No Customers Found</h4>
            <p class="text-muted">No customer data found for the selected criteria.</p>
            <a href="{{ route('reports.customers.customer_list.index') }}" class="btn btn-primary">
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
    // Pie Chart - Customer Distribution by Status
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

    // Bar Chart - Top Customers
    const barData = @json($chartData['bar_chart'] ?? []);
    if (barData.labels && barData.labels.length > 0) {
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: barData.labels,
                datasets: [{
                    label: 'Total Disbursed',
                    data: barData.values,
                    backgroundColor: barData.colors || ['#007BFF', '#28a745', '#17a2b8', '#ffc107', '#dc3545']
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