@extends('adminlte::page')

@section('title', 'Financial Dashboard')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-line"></i> Financial Dashboard</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-chart-line"></i> Financial</h1>
                <p class="mb-0 text-light">Executive Overview - {{ $shop->name ?? 'All Branches' }}</p>
            </div>
            <a href="{{ route('dashboard') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Financial Dashboard</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
    <!-- Filters Section -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                @csrf
                <select name="subshop_id" class="form-select form-select-sm" style="width: 180px;" onchange="this.form.submit()">
                    <option value="">All Branches</option>
                    @foreach($subshops as $subshop)
                        <option value="{{ $subshop->id }}" {{ $selectedSubshopId == $subshop->id ? 'selected' : '' }}>
                            {{ $subshop->name }}
                        </option>
                    @endforeach
                </select>
                <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $dateFrom }}" style="width: 140px;">
                <span class="text-muted">to</span>
                <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $dateTo }}" style="width: 140px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="{{ route('dashboard.financial', array_merge(request()->query(), ['clear_filters' => 1])) }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>
        </div>
    </div>

    <!-- Alerts Section -->
    @if(!empty($dashboardData['alerts']))
    <div class="row mb-4">
        <div class="col-12">
            @foreach($dashboardData['alerts'] as $alert)
            <div class="alert alert-{{ $alert['type'] }} alert-dismissible fade show" role="alert">
                <strong><i class="fas fa-exclamation-triangle"></i> {{ $alert['title'] }}</strong>
                {{ $alert['message'] }}
                @if(isset($alert['action_url']))
                <a href="{{ $alert['action_url'] }}" class="btn btn-sm btn-{{ $alert['type'] == 'danger' ? 'outline-danger' : 'outline-warning' }}">
                    View Details
                </a>
                @endif
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- KPI Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-2 col-sm-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Loan Portfolio</h6>
                            <h4 class="mb-0">{{ number_format($dashboardData['kpis']['loan_portfolio'] ?? 0, 0) }}</h4>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                    </div>
                    <small class="opacity-75">Total Disbursed</small>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('reports.loan_portfolio.index') }}" class="text-white text-decoration-none small">
                        View Report <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Outstanding</h6>
                            <h4 class="mb-0">{{ number_format($dashboardData['kpis']['total_outstanding'] ?? 0, 0) }}</h4>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                    <small class="opacity-75">Total Receivable</small>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('reports.loan_outstanding.index') }}" class="text-white text-decoration-none small">
                        View Report <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Income</h6>
                            <h4 class="mb-0">{{ number_format($dashboardData['kpis']['total_income'] ?? 0, 0) }}</h4>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-arrow-trend-up"></i>
                        </div>
                    </div>
                    <small class="opacity-75">Revenue</small>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('reports.accounting.income_summary.index') }}" class="text-white text-decoration-none small">
                        View Report <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Expenses</h6>
                            <h4 class="mb-0">{{ number_format($dashboardData['kpis']['total_expenses'] ?? 0, 0) }}</h4>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-arrow-trend-down"></i>
                        </div>
                    </div>
                    <small class="opacity-75">Costs</small>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('reports.accounting.expenses_summary.index') }}" class="text-white text-decoration-none small">
                        View Report <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6">
            <div class="card {{ ($dashboardData['kpis']['net_profit'] ?? 0) >= 0 ? 'bg-success' : 'bg-danger' }} text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Net Profit</h6>
                            <h4 class="mb-0">{{ number_format($dashboardData['kpis']['net_profit'] ?? 0, 0) }}</h4>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <small class="opacity-75">{{ ($dashboardData['kpis']['net_profit'] ?? 0) >= 0 ? 'Surplus' : 'Deficit' }}</small>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('reports.accounting.profit_loss.index') }}" class="text-white text-decoration-none small">
                        View Report <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Cash Balance</h6>
                            <h4 class="mb-0">{{ number_format($dashboardData['kpis']['cash_balance'] ?? 0, 0) }}</h4>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <small class="opacity-75">Available Funds</small>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('reports.accounting.cash_flow.index') }}" class="text-dark text-decoration-none small">
                        View Report <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- PAR Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Portfolio at Risk (PAR)</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="display-4 {{ ($dashboardData['par_data']['par30'] ?? 0) > 10 ? 'text-danger' : (($dashboardData['par_data']['par30'] ?? 0) > 5 ? 'text-warning' : 'text-success') }}">
                                {{ $dashboardData['par_data']['par30'] ?? 0 }}%
                            </div>
                            <h6>PAR 30</h6>
                            <small class="text-muted">1-30 days overdue</small>
                        </div>
                        <div class="col-md-4">
                            <div class="display-4 {{ ($dashboardData['par_data']['par60'] ?? 0) > 10 ? 'text-danger' : (($dashboardData['par_data']['par60'] ?? 0) > 5 ? 'text-warning' : 'text-success') }}">
                                {{ $dashboardData['par_data']['par60'] ?? 0 }}%
                            </div>
                            <h6>PAR 60</h6>
                            <small class="text-muted">31-60 days overdue</small>
                        </div>
                        <div class="col-md-4">
                            <div class="display-4 {{ ($dashboardData['par_data']['par90'] ?? 0) > 10 ? 'text-danger' : (($dashboardData['par_data']['par90'] ?? 0) > 5 ? 'text-warning' : 'text-success') }}">
                                {{ $dashboardData['par_data']['par90'] ?? 0 }}%
                            </div>
                            <h6>PAR 90</h6>
                            <small class="text-muted">61-90 days overdue</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('reports.par.index') }}" class="btn btn-danger btn-sm">
                        <i class="fas fa-chart-pie me-1"></i> View PAR Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Loan Portfolio Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-coins me-2"></i>Loan Portfolio Overview</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="display-6 text-primary">
                                {{ number_format($dashboardData['loan_portfolio']['total_disbursed'] ?? 0, 0) }}
                            </div>
                            <h6>Total Disbursed</h6>
                        </div>
                        <div class="col-md-3">
                            <div class="display-6 text-info">
                                {{ number_format($dashboardData['loan_portfolio']['total_outstanding'] ?? 0, 0) }}
                            </div>
                            <h6>Outstanding Balance</h6>
                        </div>
                        <div class="col-md-3">
                            <div class="display-6 text-success">
                                {{ number_format($dashboardData['loan_portfolio']['active_loans_count'] ?? 0, 0) }}
                            </div>
                            <h6>Active Loans</h6>
                        </div>
                        <div class="col-md-3">
                            <div class="display-6 text-secondary">
                                {{ number_format($dashboardData['loan_portfolio']['paid_off_count'] ?? 0, 0) }}
                            </div>
                            <h6>Paid Off Loans</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1: Profitability & Cash Flow -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Income vs Expenses</h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="incomeVsExpensesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Cash Flow Trend</h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="cashFlowChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2: Distribution Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Income Distribution</h5>
                </div>
                <div class="card-body">
                    @if(!empty($dashboardData['income_distribution']['labels']))
                    <div style="height: 300px; position: relative;">
                        <canvas id="incomeDistributionChart"></canvas>
                    </div>
                    @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-pie fa-3x mb-3"></i>
                        <p>No income data available</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Expense Distribution</h5>
                </div>
                <div class="card-body">
                    @if(!empty($dashboardData['expense_distribution']['labels']))
                    <div style="height: 300px; position: relative;">
                        <canvas id="expenseDistributionChart"></canvas>
                    </div>
                    @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-pie fa-3x mb-3"></i>
                        <p>No expense data available</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 3: Trends & Branch Performance -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Profit Trend</h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="profitTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Branch Performance</h5>
                </div>
                <div class="card-body">
                    @if(!empty($dashboardData['branch_performance']['labels']))
                    <div style="height: 300px; position: relative;">
                        <canvas id="branchPerformanceChart"></canvas>
                    </div>
                    @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-bar fa-3x mb-3"></i>
                        <p>No branch data available</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                    <th>Type</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dashboardData['recent_transactions'] ?? [] as $transaction)
                                <tr>
                                    <td>{{ $transaction['date'] }}</td>
                                    <td><code>{{ $transaction['reference'] }}</code></td>
                                    <td>{{ $transaction['description'] }}</td>
                                    <td class="text-end">{{ number_format($transaction['amount'], 2) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $transaction['type'] == 'debit' ? 'success' : 'danger' }}">
                                            {{ ucfirst($transaction['type']) }}
                                        </span>
                                    </td>
                                    <td>{{ $transaction['created_by'] ?? 'System' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No recent transactions found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@endsection


@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart.js default configuration
    Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
    Chart.defaults.color = '#6c757d';

    // Debug: Log dashboard data
    console.log('Dashboard Data:', {!! json_encode($dashboardData) !!});

    // Income vs Expenses Bar Chart
    const incomeVsExpensesCtx = document.getElementById('incomeVsExpensesChart');
    if (incomeVsExpensesCtx) {
        new Chart(incomeVsExpensesCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($dashboardData['profitability']['labels'] ?? []) !!},
                datasets: [
                    {
                        label: 'Income',
                        data: {!! json_encode($dashboardData['profitability']['income'] ?? []) !!},
                        backgroundColor: '#10b981',
                    },
                    {
                        label: 'Expenses',
                        data: {!! json_encode($dashboardData['profitability']['expenses'] ?? []) !!},
                        backgroundColor: '#ef4444',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Cash Flow Line Chart
    const cashFlowCtx = document.getElementById('cashFlowChart');
    if (cashFlowCtx) {
        new Chart(cashFlowCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dashboardData['cash_flow']['labels'] ?? []) !!},
                datasets: [
                    {
                        label: 'Cash Inflows',
                        data: {!! json_encode($dashboardData['cash_flow']['inflows'] ?? []) !!},
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Cash Outflows',
                        data: {!! json_encode($dashboardData['cash_flow']['outflows'] ?? []) !!},
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Income Distribution Pie Chart
    const incomeDistCtx = document.getElementById('incomeDistributionChart');
    if (incomeDistCtx) {
        new Chart(incomeDistCtx, {
            type: 'pie',
            data: {
                labels: {!! json_encode($dashboardData['income_distribution']['labels'] ?? []) !!},
                datasets: [{
                    data: {!! json_encode($dashboardData['income_distribution']['values'] ?? []) !!},
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    // Expense Distribution Pie Chart
    const expenseDistCtx = document.getElementById('expenseDistributionChart');
    if (expenseDistCtx) {
        new Chart(expenseDistCtx, {
            type: 'pie',
            data: {
                labels: {!! json_encode($dashboardData['expense_distribution']['labels'] ?? []) !!},
                datasets: [{
                    data: {!! json_encode($dashboardData['expense_distribution']['values'] ?? []) !!},
                    backgroundColor: [
                        '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#06b6d4',
                        '#3b82f6', '#8b5cf6', '#ec4899', '#6366f1', '#14b8a6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    // Profit Trend Line Chart
    const profitTrendCtx = document.getElementById('profitTrendChart');
    if (profitTrendCtx) {
        new Chart(profitTrendCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dashboardData['monthly_trends']['labels'] ?? []) !!},
                datasets: [{
                    label: 'Net Profit',
                    data: {!! json_encode($dashboardData['monthly_trends']['profit'] ?? []) !!},
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Branch Performance Bar Chart
    const branchPerfCtx = document.getElementById('branchPerformanceChart');
    if (branchPerfCtx) {
        new Chart(branchPerfCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($dashboardData['branch_performance']['labels'] ?? []) !!},
                datasets: [
                    {
                        label: 'Income',
                        data: {!! json_encode($dashboardData['branch_performance']['income'] ?? []) !!},
                        backgroundColor: '#10b981'
                    },
                    {
                        label: 'Expenses',
                        data: {!! json_encode($dashboardData['branch_performance']['expenses'] ?? []) !!},
                        backgroundColor: '#ef4444'
                    },
                    {
                        label: 'Profit',
                        data: {!! json_encode($dashboardData['branch_performance']['profit'] ?? []) !!},
                        backgroundColor: '#3b82f6'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
</script>

@endsection



