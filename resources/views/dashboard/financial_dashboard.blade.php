@extends('adminlte::page')

@section('title', 'Financial Dashboard')

@section('content_header')
<div class="card border-0 shadow-lg mb-4" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body py-4">
        <div class="row align-items-center">
            <!-- Dashboard Title & Welcome -->
            <div class="col-md-8">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <div class="rounded-circle bg-opacity-25 p-3 d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-chart-line fa-2x text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h1 class="mb-1 fw-bold text-white" style="font-size: 2rem;">Financial Dashboard</h1>
                        <p class="mb-0 text-white-50">
                            <i class="fas fa-calendar-alt me-1"></i>
                            {{ now()->format('l, F j, Y') }}
                        </p>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-white text-primary me-2 px-3 py-2">
                        <i class="fas fa-building me-1"></i>
                        {{ $shop->name ?? 'System' }}
                    </span>
                    <span class="badge bg-white bg-opacity-25 text-white px-3 py-2">
                        <i class="fas fa-clock me-1"></i>
                        {{ now()->format('g:i A') }}
                    </span>
                </div>
            </div>
            
            <!-- User Profile Section -->
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="d-flex align-items-center justify-content-md-end">
                    <div class="text-end me-3">
                        <h6 class="mb-0 text-white fw-semibold">{{ auth()->user()->name }}</h6>
                        <small class="text-white-50">
                            @if(auth()->user()->roles->first())
                                <i class="fas fa-user-shield me-1"></i>
                                {{ auth()->user()->roles->first()->name }}
                            @else
                                <i class="fas fa-user me-1"></i>
                                User
                            @endif
                        </small>
                    </div>
                    <div class="position-relative">
                        @if(auth()->user()->profile_image)
                            <img src="{{ asset('storage/' . auth()->user()->profile_image) }}" 
                                 alt="{{ auth()->user()->name }}" 
                                 class="rounded-circle border border-3 border-white shadow" 
                                 style="width: 55px; height: 55px; object-fit: cover;">
                        @else
                            <div class="rounded-circle bg-opacity-25 d-flex align-items-center justify-content-center border border-3 border-white shadow" 
                                 style="width: 55px; height: 55px;">
                                <span class="text-white fw-bold" style="font-size: 1.25rem;">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                                </span>
                            </div>
                        @endif
                        <span class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-white" 
                              style="width: 14px; height: 14px;" 
                              title="Online"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-light rounded px-3 py-2 mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard.financial') }}" class="text-decoration-none">
                <i class="fas fa-home me-1"></i>Home
            </a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">Financial Dashboard</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
    <!-- Filters Section -->
<div class="card  border-0 mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-1 text-warning"></i>
            Filters <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Filter dashboard data based on date range and branch"></i>
        </h5>
    </div>
    <div class="card-body py-3">
        <form method="GET" class="row g-3 align-items-end">
            @csrf

            <!-- Branch -->
            <div class="col-md-3">
                <label class="form-label fw-semibold text-muted small">Branch</label>
                <select name="subshop_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Branches</option>
                    @foreach($subshops as $subshop)
                        <option value="{{ $subshop->id }}" {{ $selectedSubshopId == $subshop->id ? 'selected' : '' }}>
                            {{ $subshop->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- From Date -->
            <div class="col-md-2">
                <label class="form-label fw-semibold text-muted small"><i class="fas fa-calendar text-danger"></i> From</label>
                <input type="date" name="from_date" class="form-control" value="{{ $dateFrom }}">
            </div>

            <!-- To Date -->
            <div class="col-md-2">
                <label class="form-label fw-semibold text-muted small"><i class="fas fa-calendar text-danger"></i> To</label>
                <input type="date" name="to_date" class="form-control" value="{{ $dateTo }}">
            </div>

            <!-- Buttons -->
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-filter me-1"></i> Apply Filters
                </button>

                <a href="{{ route('dashboard.financial', ['clear_filters' => 1]) }}"
                   class="btn btn-outline-secondary px-4">
                    <i class="fas fa-undo me-1"></i> Reset
                </a>
            </div>

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
                        {{-- Quick Actions Panel --}}
    <div class="row mb-2">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt text-warning"></i>
                        Quick Actions <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Shortcuts to common tasks"></i>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                            <a href="{{ route('customers.create') }}" class="btn btn-outline-primary btn-block btn-sm">
                                <i class="fas fa-user"></i><br>
                                <small>Add Customer</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                            <a href="{{ route('loans.loans.create') }}" class="btn btn-outline-success btn-block btn-sm">
                                 <i class="fas fa-hand-holding-usd"></i><br>
                                <small>Add Loan</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                            <a href="{{ route('loan.repayments.index') }}" class="btn btn-outline-info btn-block btn-sm">
                               <i class="fas fa-money-bill-wave"></i><br>
                                <small>Record Payment</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                            <a href="{{ route('deposits.index') }}" class="btn btn-outline-secondary btn-block btn-sm">
                                <i class="fas fa-credit-card"></i><br>
                                <small>Customers Deposits</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                            <a href="{{ route('reports.loan_reports.index') }}" class="btn btn-outline-warning btn-block btn-sm">
                                <i class="fas fa-file-alt"></i><br>
                                <small>Loans Reports</small>
                            </a>
                        </div>
                       
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                            <a href="{{ route('reports.accounting_reports.index')}}" class="btn btn-outline-danger btn-block btn-sm">
                                <i class="fas fa-tags"></i><br>
                                <small>Accounting Reports</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Collections & Promises Alert Card -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">
                                <i class="fas fa-handshake text-info me-2"></i>
                                Promises Due Today
                            </h6>
                            <div class="h3 mb-0 font-weight-bold {{ $pendingPromisesTodayCount > 0 ? 'text-warning' : 'text-success' }}">
                                {{ $pendingPromisesTodayCount }}
                                <small class="text-muted" style="font-size: 0.5em;">pending</small>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('collections.promises') }}" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-eye me-1"></i> View Promises
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Loan Approvals Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">
                                <i class="fas fa-user-check text-primary me-2"></i>
                                Pending Loan Approvals
                            </h6>
                            <div class="h3 mb-0 font-weight-bold {{ $pendingApprovalsCount > 0 ? 'text-warning' : 'text-success' }}">
                                {{ $pendingApprovalsCount }}
                                <small class="text-muted" style="font-size: 0.5em;">for you</small>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('loans.approvals.index') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i> View Approvals
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Disbursement Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">
                                <i class="fas fa-money-bill-wave text-success me-2"></i>
                                Pending Disbursement
                            </h6>
                            <div class="h3 mb-0 font-weight-bold {{ $pendingDisburseCount > 0 ? 'text-warning' : 'text-success' }}">
                                {{ $pendingDisburseCount }}
                                <small class="text-muted" style="font-size: 0.5em;">Pending</small>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('loans.disbursement.index') }}" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-eye me-1"></i> View Pending
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Restructure Approvals Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">
                                <i class="fas fa-random text-warning me-2"></i>
                                Pending Restructures
                            </h6>
                            <div class="h3 mb-0 font-weight-bold {{ $pendingRestructureCount > 0 ? 'text-warning' : 'text-success' }}">
                                {{ $pendingRestructureCount }}
                                <small class="text-muted" style="font-size: 0.5em;">Pending</small>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('loan.restructures.index') }}" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-eye me-1"></i> View Restructures
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top KPI Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <a href="{{ route('reports.loan_portfolio.index') }}" class="text-decoration-none">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Loan Portfolio</div>
                                <div class="h4 mb-0">{{ number_format($dashboardData['kpis']['loan_portfolio'] ?? 0, 0) }}</div>
                            </div>
                            <i class="fas fa-money-check-alt fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.loan_outstanding.index') }}" class="text-decoration-none">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Outstanding</div>
                                <div class="h4 mb-0">{{ number_format($dashboardData['kpis']['total_outstanding'] ?? 0, 0) }}</div>
                            </div>
                            <i class="fas fa-file-invoice-dollar fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.accounting.income_summary.index') }}" class="text-decoration-none">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Total Income</div>
                                <div class="h4 mb-0">{{ number_format($dashboardData['kpis']['total_income'] ?? 0, 0) }}</div>
                            </div>
                            <i class="fas fa-arrow-trend-up fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-4">
            <a href="{{ route('reports.accounting.expenses_summary.index') }}" class="text-decoration-none">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Total Expenses</div>
                                <div class="h4 mb-0">{{ number_format($dashboardData['kpis']['total_expenses'] ?? 0, 0) }}</div>
                            </div>
                            <i class="fas fa-arrow-trend-down fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.accounting.profit_loss.index') }}" class="text-decoration-none">
                <div class="card text-white {{ ($dashboardData['kpis']['net_profit'] ?? 0) >= 0 ? 'bg-success' : 'bg-danger' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Net Profit</div>
                                <div class="h4 mb-0">{{ number_format($dashboardData['kpis']['net_profit'] ?? 0, 0) }}</div>
                            </div>
                            <i class="fas fa-chart-line fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.accounting.cash_flow.index') }}" class="text-decoration-none">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Cash Balance</div>
                                <div class="h4 mb-0">{{ number_format($dashboardData['kpis']['cash_balance'] ?? 0, 0) }}</div>
                            </div>
                            <i class="fas fa-wallet fa-2x text-muted"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- End Top KPI Summary Cards Row -->

    <!-- Bottom Summary Cards Row -->
         <div class="row mb-4">
        <div class="col-md-4">
            <a href="{{ route('customers.index') }}" class="text-decoration-none">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Registered Customers</div>
                                <div class="h4 mb-0">{{ number_format((int) ($dashboardData['customer_stats']['total_registered_customers'] ?? 0)) }}</div>
                            </div>
                            <i class="fas fa-users fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('deposits.index') }}" class="text-decoration-none">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Customer Deposits</div>
                                <div class="h5 mb-0">{{ number_format((float) ($dashboardData['customer_stats']['total_customer_deposits'] ?? 0), 0) }}</div>
                            </div>
                            <i class="fas fa-piggy-bank fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('loans.loans.index') }}" class="text-decoration-none">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Released Loans</div>
                                <div class="h4 mb-0">{{ number_format((int) ($dashboardData['loan_portfolio']['total_released_loans'] ?? 0)) }}</div>
                            </div>
                            <i class="fas fa-hand-holding-usd fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-4">
            <a href="{{ route('loan.repayments.index') }}" class="text-decoration-none">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Active Loans</div>
                                <div class="h4 mb-0">{{ number_format((int) ($dashboardData['loan_portfolio']['total_active_loans'] ?? 0)) }}</div>
                            </div>
                            <i class="fas fa-credit-card fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('loans.completed.index') }}" class="text-decoration-none">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Completed Loans</div>
                                <div class="h4 mb-0">{{ number_format((int) ($dashboardData['loan_portfolio']['total_completed_loans'] ?? 0)) }}</div>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('loan.restructures.managed') }}" class="text-decoration-none">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Restructured Loans</div>
                                <div class="h4 mb-0">{{ number_format((int) ($dashboardData['loan_portfolio']['total_restructured_loans'] ?? 0)) }}</div>
                            </div>
                            <i class="fas fa-retweet fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- PAR Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card ">
                <div class="card-header">
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

    <!-- New Loan Charts Row 1 -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2"></i>Loans Released</h5>
                </div>
                <div class="card-body">
                    @if(!empty($dashboardData['loan_charts']['loans_released']['labels']))
                    <div style="height: 300px; position: relative;">
                        <canvas id="loansReleasedChart"></canvas>
                    </div>
                    @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <p>No loans released data available</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Loans Collections</h5>
                </div>
                <div class="card-body">
                    @if(!empty($dashboardData['loan_charts']['loans_collections']['labels']))
                    <div style="height: 300px; position: relative;">
                        <canvas id="loansCollectionsChart"></canvas>
                    </div>
                    @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <p>No collections data available</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- New Loan Charts Row 2 -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Active vs Inactive Customers</h5>
                </div>
                <div class="card-body">
                    @if(($dashboardData['loan_charts']['customer_status']['active'] ?? 0) > 0 || ($dashboardData['loan_charts']['customer_status']['inactive'] ?? 0) > 0)
                    <div style="height: 300px; position: relative;">
                        <canvas id="customerStatusChart"></canvas>
                    </div>
                    @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-pie fa-3x mb-3"></i>
                        <p>No customer data available</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-percentage me-2"></i>Paid vs Due Loan Amount</h5>
                </div>
                <div class="card-body">
                    @if(($dashboardData['loan_charts']['loan_amount_status']['paid'] ?? 0) > 0 || ($dashboardData['loan_charts']['loan_amount_status']['due'] ?? 0) > 0)
                    <div style="height: 300px; position: relative;">
                        <canvas id="loanAmountStatusChart"></canvas>
                    </div>
                    @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-pie fa-3x mb-3"></i>
                        <p>No loan amount data available</p>
                    </div>
                    @endif
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

    // Loans Released Line Chart
    const loansReleasedCtx = document.getElementById('loansReleasedChart');
    if (loansReleasedCtx) {
        new Chart(loansReleasedCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dashboardData['loan_charts']['loans_released']['labels'] ?? []) !!},
                datasets: [{
                    label: 'Loans Released',
                    data: {!! json_encode($dashboardData['loan_charts']['loans_released']['values'] ?? []) !!},
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

    // Loans Collections Line Chart
    const loansCollectionsCtx = document.getElementById('loansCollectionsChart');
    if (loansCollectionsCtx) {
        new Chart(loansCollectionsCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dashboardData['loan_charts']['loans_collections']['labels'] ?? []) !!},
                datasets: [{
                    label: 'Collections',
                    data: {!! json_encode($dashboardData['loan_charts']['loans_collections']['values'] ?? []) !!},
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
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

    // Customer Status Pie Chart
    const customerStatusCtx = document.getElementById('customerStatusChart');
    if (customerStatusCtx) {
        new Chart(customerStatusCtx, {
            type: 'pie',
            data: {
                labels: ['Active', 'Inactive'],
                datasets: [{
                    data: [
                        {!! $dashboardData['loan_charts']['customer_status']['active'] ?? 0 !!},
                        {!! $dashboardData['loan_charts']['customer_status']['inactive'] ?? 0 !!}
                    ],
                    backgroundColor: ['#10b981', '#ef4444']
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

    // Loan Amount Status Pie Chart
    const loanAmountStatusCtx = document.getElementById('loanAmountStatusChart');
    if (loanAmountStatusCtx) {
        new Chart(loanAmountStatusCtx, {
            type: 'pie',
            data: {
                labels: ['Paid', 'Due'],
                datasets: [{
                    data: [
                        {!! $dashboardData['loan_charts']['loan_amount_status']['paid'] ?? 0 !!},
                        {!! $dashboardData['loan_charts']['loan_amount_status']['due'] ?? 0 !!}
                    ],
                    backgroundColor: ['#3b82f6', '#f59e0b']
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
});
</script>

@endsection



