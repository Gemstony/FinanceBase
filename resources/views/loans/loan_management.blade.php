@extends('adminlte::page')

@section('title', 'Loan Management - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Loan Management</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Loan Management</h1>
                <p class="mb-0 text-light">Managing Loans for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('categories.subshops') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Change Branch
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i>
                    Dashboard</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Loan Management
            </li>
        </ol>
    </nav>
</div>
@stop



@section('content')
<div class="container-fluid">


<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Loan Management Center</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-info elevation-1">
                        <i class="fas fa-cash-register"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Loans </span>
                        <span class="info-box-number">
                            Process loan repayments
                        </span>
                        <div class="mt-1">
                            <span class="badge badge-success">Repayments: {{ (int) ($repaymentsCount ?? 0) }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('loan.repayments.index') }}" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-arrow-right mr-1"></i> Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-primary elevation-1">
                        <i class="fas fa-list"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Applied Loans</span>
                        <span class="info-box-number">
                            View, manage and create loans
                        </span>
                        <div class="mt-1">
                            <span class="badge badge-warning">Pending: {{ (int) ($pendingLoansCount ?? 0) }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('loans.loans.index') }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-arrow-right mr-1"></i> Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-secondary elevation-1">
                        <i class="fas fa-calculator"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Loan Calculator</span>
                        <span class="info-box-number">
                            Simulate installments and interest
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('loans.loans.calculator.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-right mr-1"></i> Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-success elevation-1">
                        <i class="fas fa-user-check"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pending Loan Approvals</span>
                        <span class="info-box-number">
                            Review pending approvals
                        </span>
                        <div class="mt-1">
                            <span class="badge badge-danger">Pending for you: {{ (int) ($pendingApprovalsCount ?? 0) }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('loans.approvals.index') }}" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-arrow-right mr-1"></i> Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-info elevation-1">
                        <i class="fas fa-hand-holding-usd"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Disburse Approved Loans</span>
                        <span class="info-box-number">
                            Pending Disburse Approved Loans
                        </span>
                        <div class="mt-1">
                            <span class="badge badge-warning">Pending: {{ (int) ($pendingDisburseCount ?? 0) }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('loans.disbursement.index') }}" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-arrow-right mr-1"></i> Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-warning elevation-1">
                        <i class="fas fa-random"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pending Restructure Approvals</span>
                        <span class="info-box-number">
                            Review and approve loan restructures
                        </span>
                        <div class="mt-1">
                            <span class="badge badge-danger">Pending: {{ (int) ($pendingRestructureApprovalsCount ?? 0) }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('loan.restructures.index') }}" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-arrow-right mr-1"></i> Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-teal elevation-1 text-white">
                        <i class="fas fa-sync-alt"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Restructured Loans</span>
                        <span class="info-box-number">
                            Manage all restructured loans
                        </span>
                        <div class="mt-1">
                            <span class="badge badge-info">Count: {{ (int) ($restructuredLoansCount ?? 0) }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('loan.restructures.managed') }}" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-arrow-right mr-1"></i> Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-success elevation-1">
                        <i class="fas fa-check-circle"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Completed Loans</span>
                        <span class="info-box-number">
                            Manage all paid off loans
                        </span>
                        <div class="mt-1">
                            <span class="badge badge-success">Count: {{ (int) ($completedLoansCount ?? 0) }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('loans.completed.index') }}" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-arrow-right mr-1"></i> Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-danger elevation-1">
                        <i class="fas fa-times-circle"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Rejected Loans</span>
                        <span class="info-box-number">
                            View all rejected applications
                        </span>
                        <div class="mt-1">
                            <span class="badge badge-danger">Count: {{ (int) ($rejectedLoansCount ?? 0) }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('loans.rejected.index') }}" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-arrow-right mr-1"></i> Open
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-danger elevation-1">
                        <i class="fas fa-ban"></i> 
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Written Off Loans</span>
                        <span class="info-box-number">
                            Review and manage written-off loans
                        </span>
                        <div class="mt-1">
                            <span class="badge badge-danger">Count: {{ (int) ($pendingWriteOffApprovalsCount ?? 0) }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('writeoffs.index') }}" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-arrow-right mr-1"></i> Open
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
    
    .btn-outline-primary {
        --bs-btn-hover-color: #fff;
    }
    
    .btn-outline-success {
        --bs-btn-hover-color: #fff;
    }
    
    .btn-outline-info {
        --bs-btn-hover-color: #fff;
    }
    
    .card {
        border-top: 3px solid #007bff;
        box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    }
</style>
@endpush

    </div>


    @push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    @endpush
    @stop

    @section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    @if(session('success'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: "{{ session('success') }}",
            showConfirmButton: true,
            timerProgressBar: true,
            timer: 2500
        });
    });
    @endif

    @if(session('error'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: "{{ session('error') }}",
            showConfirmButton: true

        });
    });
    @endif
    </script>
    @stop