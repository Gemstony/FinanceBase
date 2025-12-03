@extends('adminlte::page')

@section('title', 'Admin Dashboard')

{{-- Optional: additional CSS --}}
@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@stop
<style>
    /* Header Styles - Unique & Original Design */
    .dashboard-header {
        background: var(--sidebar-bg);
        border-radius: 0;
        border-bottom: 4px solid #e94560;
        padding: 2rem 2.5rem;
        color: white;
        box-shadow: 0 10px 40px rgba(233, 69, 96, 0.15);
        position: relative;
        margin-bottom: 2rem;
    }

    .dashboard-header::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #e94560 0%, #ff6b9d 50%, #e94560 100%);
        animation: borderSlide 3s linear infinite;
        background-size: 200% 100%;
    }

    @keyframes borderSlide {
        0% {
            background-position: 0% 0%;
        }

        100% {
            background-position: 200% 0%;
        }
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 2rem;
    }

    .header-left {
        flex: 1;
    }

    .header-right {
        flex-shrink: 0;
    }

    .dashboard-title {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 0 0 0.5rem 0;
        color: #ffffff;
        display: flex;
        align-items: center;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .dashboard-title i {
        color: #e94560;
        font-size: 2rem;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }
    }

    .dashboard-subtitle {
        font-size: 1rem;
        margin: 0;
        color: #a8b2d1;
        font-weight: 400;
        letter-spacing: 0.5px;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .btn-refresh {
        background: transparent;
        border: 2px solid #e94560;
        color: #e94560;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-refresh::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: #e94560;
        transition: left 0.3s ease;
        z-index: -1;
    }

    .btn-refresh:hover::before {
        left: 0;
    }

    .btn-refresh:hover {
        color: white;
        border-color: #e94560;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(233, 69, 96, 0.4);
    }

    .btn-refresh i {
        transition: transform 0.3s ease;
    }

    .btn-refresh:hover i {
        transform: rotate(180deg);
    }

    .current-time {
        background: rgba(233, 69, 96, 0.1);
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-size: 0.9rem;
        border: 1px solid rgba(233, 69, 96, 0.3);
        color: #ffffff;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .current-time i {
        color: #e94560;
        font-size: 1rem;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-actions {
            width: 100%;
            justify-content: space-between;
        }

        .dashboard-title {
            font-size: 1.8rem;
        }

        .dashboard-subtitle {
            font-size: 0.9rem;
        }
    }

    /* Responsive KPI numbers (prevent overflow like items page) */
    .small-box .inner h3 {
        font-size: 2.2rem;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 5px;
    }

    @media (max-width: 576px) {
        .small-box .inner h3 {
            font-size: 1.5rem;
        }
        .small-box .inner p {
            font-size: 0.9rem;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }

    @media (min-width: 577px) and (max-width: 991px) {
        .small-box .inner h3 {
            font-size: 1.8rem;
        }
    }

</style>
<style>
    /* Smart Alerts scrolling */
    .alerts-scroll { max-height: 260px; overflow: auto; }
    @media (max-width: 576px) { .alerts-scroll { max-height: 200px; } }
    .filters-card { border: 1px solid rgba(233, 69, 96, 0.15); box-shadow: 0 6px 16px rgba(0,0,0,0.06); border-radius: 10px; }
    .filters-card .card-body { padding: 12px 14px; }
    .filters-title { font-weight: 700; letter-spacing: .3px; font-size: 0.95rem; }
    .filters-input { border-radius: 8px; }
    .filters-actions .btn { border-radius: 8px; }
    .preset-group .btn { border-radius: 9999px; padding-left: 12px; padding-right: 12px; }
    .preset-group .btn.active { color: #fff; background: #e94560; border-color: #e94560; }
    .filters-status { background: rgba(233,69,96,.08); border: 1px dashed rgba(233,69,96,.35); border-radius: 8px; padding: 6px 10px; display: inline-block; }

    /* Loading overlays */
    .position-relative { position: relative !important; }
    .loading-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.04);
        z-index: 5;
    }
    .loading-overlay.d-none { display: none !important; }
    .spinner {
        width: 32px;
        height: 32px;
        border: 3px solid rgba(233,69,96,0.25);
        border-top-color: #e94560;
        border-radius: 50%;
        animation: spin 0.9s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

@section('content_header')

<!-- Page Content -->
<div class="admin-dashboard">

    <!-- <h1>Dashboard</h1> -->
    @stop

    @section('content')
    <!-- Modern Header with Gradient -->
    <div class="row">
        <div class="col">
            <div class="dashboard-header mb-4">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="dashboard-title">
                            <i class="fas fa-store me-3"></i>
                            <div class="d-flex flex-column">
                                <div>{{ $shop ? $shop->name : 'Dashboard' }}</div>
                            </div>
                        </h1>

                        <div class="dashboard-title">
                                @php
                                    $activeFrom = request('date_from', session('dash_date_from'));
                                    $activeTo = request('date_to', session('dash_date_to'));
                                @endphp
                                @if($activeFrom && $activeTo)
                                    <div class="mt-1">
                                        <span class="badge badge-info text-wrap text-start" style="max-width: 100%; white-space: normal;" title="Active date range">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            <span class="d-inline d-md-inline-block">{{ $activeFrom }}</span>
                                            <span class="d-none d-md-inline"> to </span>
                                            <span class="d-inline d-md-inline-block">{{ $activeTo }}</span>
                                        </span>
                                    </div>
                                @endif
                        </div>
                        @if($shop)
                            <p class="dashboard-subtitle">{{ $shop->description ?? 'No description available' }}</p>
                            @if($activeSubshop)
                                <p class="dashboard-subtitle">
                                    <small class="text-muted">
                                        <i class="fas fa-store-alt me-1"></i>
                                        Viewing data for: <strong>{{ $activeSubshop->name }}</strong>
                                    </small>
                                </p>
                            @endif
                        @else
                            <p class="dashboard-subtitle">Welcome to your dashboard</p>
                        @endif
                    </div>
                    <div class="header-right">
                        <div class="header-actions">
                            <button class="btn btn-light btn-refresh" onclick="location.reload()"
                                title="Refresh Dashboard">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                            <div class="current-time">
                                <i class="bi bi-clock me-2"></i>
                                <span id="currentTime"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            {{-- Global Filters --}}
            <div class="card filters-card mb-3">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <div class="form-row">
                                <div class="col-sm-5 mb-2">
                                    <div class="filters-title mb-1"><i class="fas fa-calendar-day text-danger"></i> Date From</div>
                                    <input type="date" id="filterDateFrom" class="form-control form-control-sm filters-input" />
                                </div>
                                <div class="col-sm-5 mb-2">
                                    <div class="filters-title mb-1"><i class="fas fa-calendar text-danger"></i> Date To</div>
                                    <input type="date" id="filterDateTo" class="form-control form-control-sm filters-input" />
                                </div>
                                <div class="col-sm-2 mb-2 d-flex align-items-end filters-actions">
                                    <div class="btn-group btn-group-sm w-100">
                                        <button id="applyFiltersBtn" class="btn btn-primary w-50" data-toggle="tooltip" title="Apply filters and persist for your next visit."><i class="fas fa-filter"></i> Apply</button>
                                        <button id="clearFiltersBtn" class="btn btn-light border w-50" data-toggle="tooltip" title="Clear filters and reset to defaults."><i class="fas fa-times"></i> Clear</button>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 preset-group" role="group" aria-label="Quick Presets">
                                <button class="btn btn-outline-primary btn-sm" id="presetToday" data-toggle="tooltip" title="Today">Today</button>
                                <button class="btn btn-outline-primary btn-sm" id="preset7d" data-toggle="tooltip" title="Last 7 days">7d</button>
                                <button class="btn btn-outline-primary btn-sm" id="preset30d" data-toggle="tooltip" title="Last 30 days">30d</button>
                                <button class="btn btn-outline-primary btn-sm" id="presetMTD" data-toggle="tooltip" title="Month to date">MTD</button>
                                <button class="btn btn-outline-primary btn-sm" id="presetYTD" data-toggle="tooltip" title="Year to date">YTD</button>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-right mt-2 mt-md-0">
                            <small id="filtersStatus" class="text-muted filters-status"></small>
                        </div>
                    </div>
                </div>
            </div>

                        {{-- Quick Actions Panel --}}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bolt text-warning"></i>
                                Quick Actions <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Shortcuts to common tasks like adding products, processing sales, receiving stock, and accessing settings."></i>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                @can('add_items')
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                    <a href="{{ route('items.index') }}" class="btn btn-outline-primary btn-block btn-sm">
                                        <i class="fas fa-plus-circle"></i><br>
                                        <small>Add Product</small>
                                    </a>
                                </div>
                                @endcan
                                @can('view_pos')
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                    <a href="{{ route('pos.index') }}" class="btn btn-outline-success btn-block btn-sm">
                                        <i class="fas fa-cash-register"></i><br>
                                        <small>Process Sale</small>
                                    </a>
                                </div>
                                @endcan
                                @can('view_new_purchases')
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                    <a href="{{ route('purchases.index') }}" class="btn btn-outline-info btn-block btn-sm">
                                        <i class="fas fa-truck"></i><br>
                                        <small>Receive Stock</small>
                                    </a>
                                </div>
                                @endcan
                                @can('export_dashboard_reports')
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                    <button class="btn btn-outline-secondary btn-block btn-sm" onclick="generateReport()">
                                        <i class="fas fa-chart-bar"></i><br>
                                        <small>Quick Report</small>
                                    </button>
                                </div>
                                @endcan
                                @can('view_invoice_history')
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                    <a href="{{ route('invoices.index') }}" class="btn btn-outline-warning btn-block btn-sm">
                                        <i class="fas fa-file-invoice"></i><br>
                                        <small>View Sales</small>
                                    </a>
                                </div>
                                @endcan
                                @if($shop)
                                @can('view_shop')
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                    <a href="{{ route('shop.show')}}" class="btn btn-outline-danger btn-block btn-sm">
                                        <i class="fas fa-cogs"></i><br>
                                        <small>Shop Settings</small>
                                    </a>
                                </div>
                                @endcan
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Enhanced KPI Cards (moved to top) --}}
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info" data-toggle="tooltip" title="Payments collected today (cash basis). Compared against yesterday.">
                        <div class="inner">
                            <h3>{{ $kpis['today_revenue']['formatted'] }}</h3>
                            <p>{{ $kpis['today_revenue']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <a href="#" class="small-box-footer">
                            @if($kpis['today_revenue']['change'] != 0)
                                <span class="{{ $kpis['today_revenue']['change_type'] === 'positive' ? 'text-success' : 'text-danger' }}">
                                    <i class="fas fa-arrow-{{ $kpis['today_revenue']['change_type'] === 'positive' ? 'up' : 'down' }}"></i>
                                    {{ number_format(abs($kpis['today_revenue']['change']), 1) }}%
                                </span> vs yesterday
                            @else
                                No change
                            @endif
                            <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success" data-toggle="tooltip" title="Payments collected this month to date (cash basis).">
                        <div class="inner">
                            <h3>{{ $kpis['monthly_revenue']['formatted'] }}</h3>
                            <p>{{ $kpis['monthly_revenue']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <a href="#" class="small-box-footer">
                            @if($kpis['monthly_revenue']['change'] != 0)
                                <span class="{{ $kpis['monthly_revenue']['change_type'] === 'positive' ? 'text-success' : 'text-danger' }}">
                                    <i class="fas fa-arrow-{{ $kpis['monthly_revenue']['change_type'] === 'positive' ? 'up' : 'down' }}"></i>
                                    {{ number_format(abs($kpis['monthly_revenue']['change']), 1) }}%
                                </span> vs last month
                            @else
                                No change
                            @endif
                            <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning" data-toggle="tooltip" title="Current inventory value at cost across subshops.">
                        <div class="inner">
                            <h3>{{ $kpis['inventory_value']['formatted'] }}</h3>
                            <p>{{ $kpis['inventory_value']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <a href="{{ route('items.index') }}" class="small-box-footer">View Inventory <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger" data-toggle="tooltip" title="Number of items currently below their minimum stock level.">
                        <div class="inner">
                            <h3>{{ $kpis['low_stock_items']['formatted'] }}</h3>
                            <p>{{ $kpis['low_stock_items']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <a href="{{ route('items.index') }}?filter=low_stock" class="small-box-footer">
                            @if($kpis['low_stock_items']['value'] > 0)
                                <span class="text-warning">Action Required</span>
                            @else
                                All Good
                            @endif
                            <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Secondary KPI Row --}}
            <div class="row mb-4">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-secondary" data-toggle="tooltip" title="Number of sales orders created today.">
                        <div class="inner">
                            <h3>{{ $kpis['today_sales']['formatted'] }}</h3>
                            <p>{{ $kpis['today_sales']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <a href="{{ route('invoices.index') }}" class="small-box-footer">
                            @if($kpis['today_sales']['change'] != 0)
                                <span class="{{ $kpis['today_sales']['change_type'] === 'positive' ? 'text-success' : 'text-danger' }}">
                                    <i class="fas fa-arrow-{{ $kpis['today_sales']['change_type'] === 'positive' ? 'up' : 'down' }}"></i>
                                    {{ number_format(abs($kpis['today_sales']['change']), 1) }}%
                                </span> vs yesterday
                            @else
                                No change
                            @endif
                            <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $kpis['expenses_total']['formatted'] }}</h3>
                            <p>{{ $kpis['expenses_total']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <a href="{{ route('expenses.index') }}" class="small-box-footer">
                            View Expenses <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-olive" data-toggle="tooltip" title="Total write-offs affecting inventory in the selected period.">
                        <div class="inner">
                            <h3>{{ $kpis['writeoffs_total']['formatted'] }}</h3>
                            <p>{{ $kpis['writeoffs_total']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <a href="{{ route('writeoffs.index') }}" class="small-box-footer">
                            View Write-offs <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                @if($shop)
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary" data-toggle="tooltip" title="Number of active subscription plans for this shop.">
                        <div class="inner">
                            <h3>{{ $kpis['active_subscriptions']['formatted'] }}</h3>
                            <p>{{ $kpis['active_subscriptions']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-crown"></i>
                        </div>
                        <a href="{{ route('configure.shop', ['id' => $shop->id]) }}#plan-management" class="small-box-footer">Manage Plans <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                @endif
            </div>

            {{-- Additional KPI Row --}}
            <div class="row mb-4">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-dark" data-toggle="tooltip" title="Outstanding customer receivables (unpaid invoices).">
                        <div class="inner">
                            <h3>{{ $kpis['outstanding_receivables']['formatted'] }}</h3>
                            <p>{{ $kpis['outstanding_receivables']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <a href="{{ route('invoices.index') }}?status=pending" class="small-box-footer">
                            View Receivables <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-teal" data-toggle="tooltip" title="Net payments today = payments received minus refunds issued.">
                        <div class="inner">
                            <h3>{{ $kpis['net_payments_today']['formatted'] }}</h3>
                            <p>{{ $kpis['net_payments_today']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <a href="{{ route('sales.transactions.index') }}?date_from={{ now()->format('Y-m-d') }}&date_to={{ now()->format('Y-m-d') }}" class="small-box-footer">
                            @if($kpis['net_payments_today']['change'] != 0)
                                <span class="{{ $kpis['net_payments_today']['change_type'] === 'positive' ? 'text-success' : 'text-danger' }}">
                                    <i class="fas fa-arrow-{{ $kpis['net_payments_today']['change_type'] === 'positive' ? 'up' : 'down' }}"></i>
                                    {{ number_format(abs($kpis['net_payments_today']['change']), 1) }}%
                                </span> vs yesterday
                            @else
                                No change
                            @endif
                            <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-indigo" data-toggle="tooltip" title="Average amount per order today (Net Sales ÷ Orders).">
                        <div class="inner">
                            <h3>{{ $kpis['avg_order_value_today']['formatted'] }}</h3>
                            <p>{{ $kpis['avg_order_value_today']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <a href="{{ route('invoices.index') }}?date_from={{ now()->format('Y-m-d') }}&date_to={{ now()->format('Y-m-d') }}" class="small-box-footer">
                            @if($kpis['avg_order_value_today']['change'] != 0)
                                <span class="{{ $kpis['avg_order_value_today']['change_type'] === 'positive' ? 'text-success' : 'text-danger' }}">
                                    <i class="fas fa-arrow-{{ $kpis['avg_order_value_today']['change_type'] === 'positive' ? 'up' : 'down' }}"></i>
                                    {{ number_format(abs($kpis['avg_order_value_today']['change']), 1) }}%
                                </span> vs yesterday
                            @else
                                No change
                            @endif
                            <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-maroon" data-toggle="tooltip" title="Total refunds issued today.">
                        <div class="inner">
                            <h3>{{ $kpis['refunds_today']['formatted'] }}</h3>
                            <p>{{ $kpis['refunds_today']['label'] }}</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-undo"></i>
                        </div>
                        <a href="{{ route('invoices.index') }}?report=returns&date_from={{ now()->format('Y-m-d') }}&date_to={{ now()->format('Y-m-d') }}" class="small-box-footer">
                            @if($kpis['refunds_today']['change'] != 0)
                                <span class="{{ $kpis['refunds_today']['change_type'] === 'positive' ? 'text-danger' : 'text-success' }}">
                                    <i class="fas fa-arrow-{{ $kpis['refunds_today']['change_type'] === 'positive' ? 'up' : 'down' }}"></i>
                                    {{ number_format(abs($kpis['refunds_today']['change']), 1) }}%
                                </span> vs yesterday
                            @else
                                No change
                            @endif
                            <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Analytics Charts (moved below KPIs for cleaner priority) --}}
            <div class="row mb-4">
                <div class="col-md-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-dark elevation-1"><i class="fas fa-file-invoice-dollar"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Outstanding Sales (Invoices)</span>
                            <span class="info-box-number">{{ $kpis['outstanding_receivables']['formatted'] ?? 'TZS 0' }}</span>
                            <small><a href="{{ route('invoices.index') }}?status=pending" class="text-muted">View unpaid invoices</a></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-purple elevation-1"><i class="fas fa-file-invoice"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Outstanding Purchases</span>
                            <span class="info-box-number">{{ $kpis['outstanding_payables']['formatted'] ?? 'TZS 0' }}</span>
                            <small><a href="{{ route('purchase_orders.index') }}?status=pending" class="text-muted">View unpaid purchases</a></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-money-bill-wave text-info"></i> Payments Collected <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Daily payments collected in TZS for the selected range or last 30 days."></i></h5>
                            @can('export_dashboard_reports')
                            <div class="btn-group btn-group-sm" role="group" aria-label="Export Payments">
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'payments', 'format' => 'pdf']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="PDF"><i class="fas fa-file-pdf"></i></a>
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'payments', 'format' => 'excel']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="Excel"><i class="fas fa-file-excel"></i></a>
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'payments', 'format' => 'csv']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="CSV"><i class="fas fa-file-csv"></i></a>
                            </div>
                            @endcan
                        </div>
                        <div class="card-body position-relative">
                            <div class="loading-overlay" id="loader-payments"><div class="spinner"></div></div>
                            <canvas id="paymentsDailyChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-shopping-cart text-secondary"></i> Orders Created <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Orders created per day for the selected range or last 30 days."></i></h5>
                            @can('export_dashboard_reports')
                            <div class="btn-group btn-group-sm" role="group" aria-label="Export Orders">
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'orders', 'format' => 'pdf']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="PDF"><i class="fas fa-file-pdf"></i></a>
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'orders', 'format' => 'excel']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="Excel"><i class="fas fa-file-excel"></i></a>
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'orders', 'format' => 'csv']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="CSV"><i class="fas fa-file-csv"></i></a>
                            </div>
                            @endcan
                        </div>
                        <div class="card-body position-relative">
                            <div class="loading-overlay" id="loader-orders"><div class="spinner"></div></div>
                            <canvas id="ordersDailyChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-scale-balanced text-teal"></i> Net Payments vs Refunds <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Payments vs refunds per day for the selected range or last 14 days."></i></h5>
                           @can('export_dashboard_reports')
                            <div class="btn-group btn-group-sm" role="group" aria-label="Export Net">
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'net', 'format' => 'pdf']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="PDF"><i class="fas fa-file-pdf"></i></a>
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'net', 'format' => 'excel']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="Excel"><i class="fas fa-file-excel"></i></a>
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'net', 'format' => 'csv']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="CSV"><i class="fas fa-file-csv"></i></a>
                            </div>
                            @endcan
                        </div>
                        <div class="card-body position-relative">
                            <div class="loading-overlay" id="loader-net"><div class="spinner"></div></div>
                            <canvas id="netPayRefundChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-receipt text-indigo"></i> Average Order Value <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="Average amount per order per day for the selected range or last 30 days."></i></h5>
                            @can('export_dashboard_reports')
                            <div class="btn-group btn-group-sm" role="group" aria-label="Export AOV">
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'aov', 'format' => 'pdf']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="PDF"><i class="fas fa-file-pdf"></i></a>
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'aov', 'format' => 'excel']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="Excel"><i class="fas fa-file-excel"></i></a>
                                <a href="{{ route('dashboard.export.analytics', ['type' => 'aov', 'format' => 'csv']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="CSV"><i class="fas fa-file-csv"></i></a>
                            </div>
                            @endcan
                        </div>
                        <div class="card-body position-relative">
                            <div class="loading-overlay" id="loader-aov"><div class="spinner"></div></div>
                            <canvas id="aovDailyChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>
                        {{-- Outstanding Details --}}
            <div class="row mb-4">
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-file-invoice-dollar text-dark"></i> Outstanding Sales (Invoices)</h5>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('invoices.index') }}" title="Go to Invoices">View All</a>
                        </div>
                        <div class="card-body p-0 position-relative">
                            <div class="loading-overlay" id="loader-outstanding-sales"><div class="spinner"></div></div>
                            <div class="table-responsive" style="max-height: 320px;">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Customer</th>
                                            <th class="text-right">Grand</th>
                                            <th class="text-right">Paid</th>
                                            <th class="text-right text-danger">Remaining</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($outstandingSalesList ?? []) as $r)
                                            <tr>
                                                <td><a href="{{ route('invoices.index') }}?q={{ urlencode($r['order_no'] ?? $r['id']) }}" class="text-primary">{{ $r['order_no'] ?? ('#'.$r['id']) }}</a></td>
                                                <td>{{ $r['date'] }}</td>
                                                <td>{{ $r['customer'] }}</td>
                                                <td class="text-right">{{ number_format($r['grand_total'] ?? 0, 2) }}</td>
                                                <td class="text-right">{{ number_format($r['paid_total'] ?? 0, 2) }}</td>
                                                <td class="text-right text-danger font-weight-bold">{{ number_format($r['remaining'] ?? 0, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted p-3">No outstanding invoices.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 mt-3 mt-xl-0">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-file-invoice text-purple"></i> Outstanding Purchases</h5>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('purchase_orders.index') }}" title="Go to Purchases">View All</a>
                        </div>
                        <div class="card-body p-0 position-relative">
                            <div class="loading-overlay" id="loader-outstanding-purchases"><div class="spinner"></div></div>
                            <div class="table-responsive" style="max-height: 320px;">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Supplier</th>
                                            <th class="text-right">Grand</th>
                                            <th class="text-right">Paid</th>
                                            <th class="text-right text-danger">Remaining</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($outstandingPurchasesList ?? []) as $r)
                                            <tr>
                                                <td><a href="{{ route('purchase_orders.index') }}?q={{ urlencode($r['order_no'] ?? $r['id']) }}" class="text-primary">{{ $r['order_no'] ?? ('#'.$r['id']) }}</a></td>
                                                <td>{{ $r['date'] }}</td>
                                                <td>{{ $r['supplier'] }}</td>
                                                <td class="text-right">{{ number_format($r['grand_total'] ?? 0, 2) }}</td>
                                                <td class="text-right">{{ number_format($r['paid_total'] ?? 0, 2) }}</td>
                                                <td class="text-right text-danger font-weight-bold">{{ number_format($r['remaining'] ?? 0, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted p-3">No outstanding purchases.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>




            {{-- Smart Alerts (Critical / High / Medium) --}}
            <div class="row mb-2 align-items-center">
                <div class="col">
                    <h5 class="mb-0" data-toggle="tooltip" title="Automatic alerts for stock, expiries and receivables based on your active subshop.">
                        <i class="fas fa-bell text-danger"></i> Smart Alerts
                    </h5>
                </div>
                <div class="col-auto">
                    @can('export_dashboard_reports')
                    <div class="btn-group btn-group-sm" role="group" aria-label="Export Smart Alerts">
                        <a href="{{ route('dashboard.export.alerts', ['format' => 'pdf']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="Export alerts as PDF"><i class="fas fa-file-pdf"></i></a>
                        <a href="{{ route('dashboard.export.alerts', ['format' => 'excel']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="Export alerts as Excel"><i class="fas fa-file-excel"></i></a>
                        <a href="{{ route('dashboard.export.alerts', ['format' => 'csv']) }}" class="btn btn-outline-secondary" data-toggle="tooltip" title="Export alerts as CSV"><i class="fas fa-file-csv"></i></a>
                    </div>
                    @endcan
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-xl-4 col-lg-6 mb-3">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-radiation"></i> Critical Alerts <i class="fas fa-info-circle text-white-50" data-toggle="tooltip" title="Critical issues requiring immediate attention (e.g., expired batches, system-critical failures)."></i></h5>
                            <span class="badge badge-light" id="criticalCount">0</span>
                        </div>
                        <div class="card-body p-0 alerts-scroll position-relative">
                            <div class="loading-overlay" id="loader-alerts-critical"><div class="spinner"></div></div>
                            <ul class="list-group list-group-flush" id="alertsCritical">
                                <li class="list-group-item text-muted small">No critical alerts</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-6 mb-3">
                    <div class="card border-warning">
                        <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 text-dark"><i class="fas fa-triangle-exclamation"></i> High Alerts <i class="fas fa-info-circle text-muted" data-toggle="tooltip" title="High priority issues to address soon (e.g., low stock, approaching expiries)."></i></h5>
                            <span class="badge badge-dark" id="highCount">0</span>
                        </div>
                        <div class="card-body p-0 alerts-scroll position-relative">
                            <div class="loading-overlay" id="loader-alerts-high"><div class="spinner"></div></div>
                            <ul class="list-group list-group-flush" id="alertsHigh">
                                <li class="list-group-item text-muted small">No high alerts</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-12 mb-3">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle"></i> Medium Alerts <i class="fas fa-info-circle text-white-50" data-toggle="tooltip" title="Informational alerts and reminders (monitor but not urgent)."></i></h5>
                            <span class="badge badge-light" id="mediumCount">0</span>
                        </div>
                        <div class="card-body p-0 alerts-scroll position-relative">
                            <div class="loading-overlay" id="loader-alerts-medium"><div class="spinner"></div></div>
                            <ul class="list-group list-group-flush" id="alertsMedium">
                                <li class="list-group-item text-muted small">No medium alerts</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            




            {{-- Expiry Alerts Section --}}
            @php
                $hasInventoryAlerts = $expiryAlerts && $expiryAlerts->count() > 0;
                $hasSubscriptionAlerts = isset($subscriptionAlerts) && $subscriptionAlerts && $subscriptionAlerts->count() > 0;
                $bothAlertsExist = $hasInventoryAlerts && $hasSubscriptionAlerts;
                $alertColumnClass = $bothAlertsExist ? 'col-lg-6 col-12' : 'col-12';
            @endphp

            @if($hasInventoryAlerts || $hasSubscriptionAlerts)
            <div class="row mb-4">
                {{-- Inventory Expiry Alerts --}}
                @if($hasInventoryAlerts)
                <div class="{{ $alertColumnClass }}">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle"></i>
                                Inventory Expiry Alerts ({{ $expiryAlerts->count() }})
                                <i class="fas fa-info-circle text-white-50" data-toggle="tooltip" title="Batches expiring soon or already expired within your inventory."></i>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alerts-container">
                                @foreach($expiryAlerts as $alert)
                                <div class="alert alert-{{ $alert['priority'] === 'critical' ? 'danger' : ($alert['priority'] === 'high' ? 'warning' : 'info') }} alert-dismissible fade show" role="alert">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="alert-heading mb-1">
                                                <i class="fas fa-{{ $alert['type'] === 'expired' ? 'times-circle' : 'clock' }}"></i>
                                                {{ $alert['title'] }}
                                            </h6>
                                            <p class="mb-1">{{ $alert['message'] }}</p>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i> {{ $alert['subshop_name'] }}
                                                @if($alert['type'] === 'expired')
                                                    • <span class="text-danger">{{ $alert['days_overdue'] }} days overdue</span>
                                                @else
                                                    • <span class="text-warning">{{ $alert['days_remaining'] }} days remaining</span>
                                                @endif
                                            </small>
                                        </div>
                                        <div class="ml-3">
                                            <a href="{{ $alert['action_url'] }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-arrow-right"></i> View Items
                                            </a>
                                        </div>
                                    </div>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Subscription Expiry Alerts --}}
                @if($hasSubscriptionAlerts)
                <div class="{{ $alertColumnClass }}">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-calendar-times"></i>
                                Subscription Expiry Alerts ({{ $subscriptionAlerts->count() }})
                                <i class="fas fa-info-circle text-white-75" data-toggle="tooltip" title="Shop subscription plans nearing expiry; renew to avoid service interruption."></i>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alerts-container">
                                @foreach($subscriptionAlerts as $alert)
                                <div class="alert alert-{{ $alert['priority'] === 'critical' ? 'danger' : ($alert['priority'] === 'high' ? 'warning' : 'info') }} alert-dismissible fade show" role="alert">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="alert-heading mb-1">
                                                <i class="fas fa-{{ ($alert['type'] ?? '') === 'subscription_expired' ? 'times-circle' : 'clock' }}"></i>
                                                {{ $alert['title'] }}
                                            </h6>
                                            <p class="mb-1">{{ $alert['message'] }}</p>
                                            <small class="text-muted">
                                                <i class="fas fa-store"></i> {{ $alert['shop_name'] }}
                                                @if(($alert['type'] ?? '') === 'subscription_expired')
                                                    • <span class="text-danger">{{ $alert['days_overdue'] ?? 0 }} days overdue</span>
                                                @else
                                                    • <span class="text-{{ $alert['priority'] === 'critical' ? 'danger' : ($alert['priority'] === 'high' ? 'warning' : 'info') }}">{{ $alert['days_remaining'] ?? 0 }} days remaining</span>
                                                @endif
                                                @if(!empty($alert['plan_price']) && !empty($alert['plan_currency']) && !empty($alert['billing_cycle']))
                                                    • <span class="badge badge-secondary">{{ $alert['plan_price'] }} {{ $alert['plan_currency'] }}/{{ $alert['billing_cycle'] }}</span>
                                                @endif
                                            </small>
                                        </div>
                                        <!-- <div class="ml-3">
                                            <a href="{{ $alert['action_url'] }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-crown"></i> Manage Plan
                                            </a>
                                        </div> -->
                                    </div>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif



            @push('css')
                <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
            @endpush

            @push('js')
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const toggle = document.getElementById('darkmode-toggle');
                    const body = document.body;
                    const currentMode = localStorage.getItem('adminlte-darkmode');

                    // Weka mode ya mwisho iliyohifadhiwa
                    if (currentMode === 'dark') {
                        body.classList.add('dark-mode');
                    }

                    if (toggle) {
                        toggle.addEventListener('click', function(e) {
                            e.preventDefault();
                            body.classList.toggle('dark-mode');

                            // Hifadhi mode kwenye localStorage
                            if (body.classList.contains('dark-mode')) {
                                localStorage.setItem('adminlte-darkmode', 'dark');
                            } else {
                                localStorage.setItem('adminlte-darkmode', 'light');
                            }
                        });
                    }
                });
                </script>

            @endpush
            @stop

            @section('js')
            {{-- jQuery + DataTables --}}
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

            {{-- ChartJS --}}
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

            <script>

                // Update current time
                function updateTime() {
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('en-US', {
                        hour12: true,
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    document.getElementById('currentTime').textContent = timeString;
                }

                // Update time every second
                setInterval(updateTime, 1000);
                updateTime();

                $(function () {
                    // Enable Bootstrap tooltips
                    if (typeof $ !== 'undefined' && typeof $.fn.tooltip === 'function') {
                        $('[data-toggle="tooltip"]').tooltip();
                    }
                    // Initialize DataTable
                    $('#productsTable').DataTable();

                    // --- Global Filters (Date Range) ---
                    const lsFromKey = 'dash_date_from';
                    const lsToKey = 'dash_date_to';
                    const $from = $('#filterDateFrom');
                    const $to = $('#filterDateTo');
                    const $status = $('#filtersStatus');
                    const todayStr = new Date().toISOString().slice(0,10);
                    // Load saved
                    const savedFrom = localStorage.getItem(lsFromKey) || '';
                    const savedTo = localStorage.getItem(lsToKey) || '';
                    if (savedFrom) $from.val(savedFrom);
                    if (savedTo) $to.val(savedTo);
                    updateFiltersStatus();

                    function updateFiltersStatus() {
                        const df = $from.val();
                        const dt = $to.val();
                        if (df && dt) {
                            $status.text(`Filters: ${df} to ${dt}`);
                        } else {
                            $status.text('Filters: default period');
                        }
                        // Update export links with current filters
                        updateExportLinks(df, dt);
                    }

                    function getWithFilters(url) {
                        const df = $from.val();
                        const dt = $to.val();
                        if (df && dt) {
                            const u = new URL(url, window.location.origin);
                            u.searchParams.set('date_from', df);
                            u.searchParams.set('date_to', dt);
                            return u.toString();
                        }
                        return url;
                    }

                    function updateExportLinks(df, dt) {
                        // Alerts export buttons
                        document.querySelectorAll('[data-export="alerts"] a').forEach(a => {
                            const u = new URL(a.getAttribute('href'), window.location.origin);
                            if (df && dt) { u.searchParams.set('date_from', df); u.searchParams.set('date_to', dt); }
                            else { u.search = ''; }
                            a.setAttribute('href', u.toString());
                        });
                        // Analytics export buttons
                        document.querySelectorAll('[data-export-type]').forEach(a => {
                            const u = new URL(a.getAttribute('href'), window.location.origin);
                            if (df && dt) { u.searchParams.set('date_from', df); u.searchParams.set('date_to', dt); }
                            else { u.search = ''; }
                            a.setAttribute('href', u.toString());
                        });
                    }

                    $('#applyFiltersBtn').on('click', function(){
                        const df = $from.val();
                        const dt = $to.val();
                        if (df && dt && df > dt) {
                            alert('Date From must be before Date To');
                            return;
                        }
                        if (df && dt) {
                            localStorage.setItem(lsFromKey, df);
                            localStorage.setItem(lsToKey, dt);
                        } else {
                            localStorage.removeItem(lsFromKey);
                            localStorage.removeItem(lsToKey);
                        }
                        // Reload full page so KPIs and alerts reflect selected range
                        const u = new URL(window.location.href);
                        if (df && dt) { u.searchParams.set('date_from', df); u.searchParams.set('date_to', dt); }
                        else { u.searchParams.delete('date_from'); u.searchParams.delete('date_to'); }
                        window.location.href = u.toString();
                    });

                    $('#clearFiltersBtn').on('click', function(){
                        $from.val(''); $to.val('');
                        localStorage.removeItem(lsFromKey);
                        localStorage.removeItem(lsToKey);
                        const u = new URL(window.location.href);
                        u.searchParams.delete('date_from');
                        u.searchParams.delete('date_to');
                        u.searchParams.set('clear_filters', '1');
                        window.location.href = u.toString();
                    });

                    // Preset helpers
                    function setRange(df, dt) { $from.val(df); $to.val(dt); }
                    function format(d) { return d.toISOString().slice(0,10); }
                    function monthStart(d){ return new Date(d.getFullYear(), d.getMonth(), 1); }
                    function yearStart(d){ return new Date(d.getFullYear(), 0, 1); }

                    $('#presetToday').on('click', function(){
                        const now = new Date(); const f = format(now); setRange(f, f);
                        $('#applyFiltersBtn').click();
                    });
                    $('#preset7d').on('click', function(){
                        const now = new Date(); const past = new Date(now); past.setDate(now.getDate()-6);
                        setRange(format(past), format(now)); $('#applyFiltersBtn').click();
                    });
                    $('#preset30d').on('click', function(){
                        const now = new Date(); const past = new Date(now); past.setDate(now.getDate()-29);
                        setRange(format(past), format(now)); $('#applyFiltersBtn').click();
                    });
                    $('#presetMTD').on('click', function(){
                        const now = new Date(); setRange(format(monthStart(now)), format(now)); $('#applyFiltersBtn').click();
                    });
                    $('#presetYTD').on('click', function(){
                        const now = new Date(); setRange(format(yearStart(now)), format(now)); $('#applyFiltersBtn').click();
                    });

                    // --- Charts loaders (refactored to honor filters) ---
                    let paymentsChart, ordersChart, netChart, aovChart;
                    function destroyChart(chart){ if (chart) { chart.destroy(); } }
                    function showLoader(id){ const el = document.getElementById(id); if (el) el.classList.remove('d-none'); }
                    function hideLoader(id){ const el = document.getElementById(id); if (el) el.classList.add('d-none'); }

                    async function loadPayments(reset){
                        showLoader('loader-payments');
                        try {
                            const url = getWithFilters('{{ route('dashboard.analytics.payments') }}');
                            const data = await getJSON(url);
                            const ctx = document.getElementById('paymentsDailyChart');
                            destroyChart(paymentsChart);
                            paymentsChart = new Chart(ctx, { type: 'line', data: { labels: data.labels, datasets: [{ label: 'Payments (TZS)', data: data.values, borderColor: palette.infoBorder, backgroundColor: palette.info, fill: true, tension: 0.3, pointRadius: 2 }] }, options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } } });
                        } catch(e){ console.error(e); }
                        finally { hideLoader('loader-payments'); }
                    }
                    async function loadOrders(reset){
                        showLoader('loader-orders');
                        try {
                            const url = getWithFilters('{{ route('dashboard.analytics.orders') }}');
                            const data = await getJSON(url);
                            const ctx = document.getElementById('ordersDailyChart');
                            destroyChart(ordersChart);
                            ordersChart = new Chart(ctx, { type: 'bar', data: { labels: data.labels, datasets: [{ label: 'Orders', data: data.values, backgroundColor: palette.success, borderColor: palette.successBorder, borderWidth: 1 }] }, options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
                        } catch(e){ console.error(e); }
                        finally { hideLoader('loader-orders'); }
                    }
                    async function loadNet(reset){
                        showLoader('loader-net');
                        try {
                            const url = getWithFilters('{{ route('dashboard.analytics.net') }}');
                            const data = await getJSON(url);
                            const ctx = document.getElementById('netPayRefundChart');
                            destroyChart(netChart);
                            netChart = new Chart(ctx, { type: 'bar', data: { labels: data.labels, datasets: [ { label: 'Payments (TZS)', data: data.payments, backgroundColor: palette.teal, borderColor: palette.tealBorder, borderWidth: 1 }, { label: 'Refunds (TZS)', data: data.refunds, backgroundColor: palette.danger, borderColor: palette.dangerBorder, borderWidth: 1 } ] }, options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } } });
                        } catch(e){ console.error(e); }
                        finally { hideLoader('loader-net'); }
                    }
                    async function loadAov(reset){
                        showLoader('loader-aov');
                        try {
                            const url = getWithFilters('{{ route('dashboard.analytics.aov') }}');
                            const data = await getJSON(url);
                            const ctx = document.getElementById('aovDailyChart');
                            destroyChart(aovChart);
                            aovChart = new Chart(ctx, { type: 'line', data: { labels: data.labels, datasets: [{ label: 'AOV (TZS)', data: data.values, borderColor: palette.indigoBorder, backgroundColor: palette.indigo, fill: true, tension: 0.3, pointRadius: 2 }] }, options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } } });
                        } catch(e){ console.error(e); }
                        finally { hideLoader('loader-aov'); }
                    }

                    // Initialize export link grouping markers
                    // Alerts group wrapper
                    const alertsHeader = document.querySelector('.btn-group[aria-label="Export Smart Alerts"]');
                    if (alertsHeader && alertsHeader.parentElement) {
                        alertsHeader.parentElement.setAttribute('data-export', 'alerts');
                    }
                    // Add data-export-type marker to each analytics export anchor
                    document.querySelectorAll('[aria-label="Export Payments"] a').forEach(a => a.setAttribute('data-export-type','payments'));
                    document.querySelectorAll('[aria-label="Export Orders"] a').forEach(a => a.setAttribute('data-export-type','orders'));
                    document.querySelectorAll('[aria-label="Export Net"] a').forEach(a => a.setAttribute('data-export-type','net'));
                    document.querySelectorAll('[aria-label="Export AOV"] a').forEach(a => a.setAttribute('data-export-type','aov'));

                    // Initial load
                    loadPayments();
                    loadOrders();
                    loadNet();
                    loadAov();

                    // Helper to fetch JSON
                    async function getJSON(url) {
                        const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        const ct = res.headers.get('content-type') || '';
                        if (!res.ok) {
                            throw new Error(`HTTP ${res.status}`);
                        }
                        if (!ct.includes('application/json')) {
                            const text = await res.text();
                            console.error('Expected JSON, got:', text.slice(0, 200));
                            throw new Error('Non-JSON response');
                        }
                        return await res.json();
                    }

                    // Colors
                    const palette = {
                        info: 'rgba(23, 162, 184, 0.7)',
                        infoBorder: 'rgba(23, 162, 184, 1)',
                        success: 'rgba(40, 167, 69, 0.7)',
                        successBorder: 'rgba(40, 167, 69, 1)',
                        warning: 'rgba(255, 193, 7, 0.7)',
                        warningBorder: 'rgba(255, 193, 7, 1)',
                        danger: 'rgba(220, 53, 69, 0.7)',
                        dangerBorder: 'rgba(220, 53, 69, 1)',
                        indigo: 'rgba(102, 16, 242, 0.7)',
                        indigoBorder: 'rgba(102, 16, 242, 1)',
                        teal: 'rgba(32, 201, 151, 0.7)',
                        tealBorder: 'rgba(32, 201, 151, 1)'
                    };

                    // Smart Alerts
                    (async () => {
                        showLoader('loader-alerts-critical');
                        showLoader('loader-alerts-high');
                        showLoader('loader-alerts-medium');
                        try {
                            const data = await getJSON('{{ route('dashboard.alerts') }}');
                            console.log('Smart Alerts data:', data);
                            const map = [
                                {key: 'critical', el: '#alertsCritical', countEl: '#criticalCount', icon: 'fa-radiation', badgeClass: 'badge-danger'},
                                {key: 'high', el: '#alertsHigh', countEl: '#highCount', icon: 'fa-triangle-exclamation', badgeClass: 'badge-warning'},
                                {key: 'medium', el: '#alertsMedium', countEl: '#mediumCount', icon: 'fa-info-circle', badgeClass: 'badge-info'}
                            ];
                            map.forEach(({key, el, countEl, icon}) => {
                                const list = document.querySelector(el);
                                const items = (data && data[key]) ? data[key] : [];
                                document.querySelector(countEl).innerText = items.length;
                                list.innerHTML = '';
                                if (!items.length) {
                                    const none = document.createElement('li');
                                    none.className = 'list-group-item text-muted small';
                                    none.textContent = `No ${key} alerts`;
                                    list.appendChild(none);
                                } else {
                                    items.forEach(a => {
                                        const li = document.createElement('li');
                                        li.className = 'list-group-item d-flex justify-content-between align-items-center';
                                        const left = document.createElement('div');
                                        left.innerHTML = `<i class="fas ${icon} mr-2"></i><strong>${a.title}</strong><br><small class="text-muted">${a.message}</small>`;
                                        const right = document.createElement('div');
                                        if (a.action_url) {
                                            right.innerHTML = `<a href="${a.action_url}" class="btn btn-sm btn-outline-primary">View</a>`;
                                        }
                                        li.appendChild(left);
                                        li.appendChild(right);
                                        list.appendChild(li);
                                    });
                                }
                            });
                        } catch (e) {
                            console.error('Failed to load smart alerts:', e);
                            // Show a single-row inline error on each list
                            ['#alertsCritical', '#alertsHigh', '#alertsMedium'].forEach(sel => {
                                const list = document.querySelector(sel);
                                if (list) {
                                    list.innerHTML = '';
                                    const errLi = document.createElement('li');
                                    errLi.className = 'list-group-item text-danger small';
                                    errLi.textContent = 'Failed to load alerts';
                                    list.appendChild(errLi);
                                }
                            });
                        } finally {
                            hideLoader('loader-alerts-critical');
                            hideLoader('loader-alerts-high');
                            hideLoader('loader-alerts-medium');
                        }
                    })();

                    // Charts are initialized above via loadPayments/loadOrders/loadNet/loadAov to avoid duplicate canvas usage
                    // Hide loaders for static server-rendered lists once DOM is ready
                    hideLoader('loader-outstanding-sales');
                    hideLoader('loader-outstanding-purchases');
                });


                // Quick Actions Functions
                function generateReport() {
                    try {
                        var df = document.getElementById('filterDateFrom') ? document.getElementById('filterDateFrom').value : '';
                        var dt = document.getElementById('filterDateTo') ? document.getElementById('filterDateTo').value : '';
                        var url = new URL('{{ route('dashboard.export.quick') }}', window.location.origin);
                        if (df && dt) { url.searchParams.set('date_from', df); url.searchParams.set('date_to', dt); }
                        window.location.href = url.toString();
                    } catch (e) {
                        window.location.href = '{{ route('dashboard.export.quick') }}';
                    }
                }
            </script>
            @stop