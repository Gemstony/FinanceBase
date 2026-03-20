@extends('adminlte::page')

@section('title', 'Accounting Reports - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-tags"></i> Accounting Reports</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-tags"></i> Accounting Reports</h1>
                <p class="mb-0 text-light">Access accounting reports for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('categories.subshops') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Change Brunch
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i>
                    Dashboard</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Accounting Reports
            </li>
        </ol>
    </nav>
</div>
@stop



@section('content')
<div class="container-fluid">


<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Accounting Reports</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Trial Balance Report Card -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-primary elevation-1">
                        <i class="fas fa-balance-scale"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Trial Balance Report</span>
                        <span class="info-box-number">
                            Summary of debit and credit balances
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('reports.accounting.trial_balance.index') }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-arrow-right mr-1"></i> Open Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- General Ledger Report Card -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-success elevation-1">
                        <i class="fas fa-book"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">General Ledger Report</span>
                        <span class="info-box-number">
                            Account transactions and running balances
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('reports.accounting.general_ledger.index') }}" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-arrow-right mr-1"></i> Open Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profit & Loss Report Card -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-info elevation-1">
                        <i class="fas fa-chart-line"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Profit &amp; Loss Report</span>
                        <span class="info-box-number">
                            Income, expenses and net profit
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('reports.accounting.profit_loss.index') }}" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-arrow-right mr-1"></i> Open Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expenses Summary Report Card -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-maroon elevation-1">
                        <i class="fas fa-receipt"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Expenses Summary</span>
                        <span class="info-box-number">
                            Expense breakdown and analytics
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('reports.accounting.expenses_summary.index') }}" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-arrow-right mr-1"></i> Open Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Sheet Report Card -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-dark elevation-1">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Balance Sheet Report</span>
                        <span class="info-box-number">
                            Assets, liabilities and equity
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('reports.accounting.balance_sheet.index') }}" class="btn btn-sm btn-outline-dark">
                                <i class="fas fa-arrow-right mr-1"></i> Open Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash Flow Report Card -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-warning elevation-1">
                        <i class="fas fa-coins"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Cash Flow Report</span>
                        <span class="info-box-number">
                            Cash inflows and outflows
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('reports.accounting.cash_flow.index') }}" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-arrow-right mr-1"></i> Open Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash Book Report Card -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-secondary elevation-1">
                        <i class="fas fa-cash-register"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Cash Book Report</span>
                        <span class="info-box-number">
                            Receipts, payments and running balance
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('reports.accounting.cash_book.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-right mr-1"></i> Open Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Journal Report Card -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-danger elevation-1">
                        <i class="fas fa-clipboard-list"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Journal Report</span>
                        <span class="info-box-number">
                            Journal entries listing and summaries
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('reports.accounting.journal_report.index') }}" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-arrow-right mr-1"></i> Open Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>
</div>

@push('css')
<style>
    .info-box {
        border-radius: 0.50rem;
        min-height: 120px;
        transition: all 0.3s ease;
    }

    .info-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
    }

    .info-box-icon {
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        border-radius: 0.50rem;
        margin: 15px;
    }

    .info-box-content {
        padding: 10px 15px 10px 0;
    }

    .info-box-text {
        display: block;
        font-size: 1rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.25rem;
    }

    .info-box-number {
        display: block;
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }

    .card {
        border-top: 3px solid #007bff;
        box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    }
</style>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@stop