@extends('adminlte::page')

@section('title', 'Loan Repayments')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-cash-register"></i> Loan Repayments</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-cash-register"></i> Loan Repayments</h1>
                    <p class="mb-0 text-light">Eligible loans for repayment</p>
                </div>
                <a href="{{ route('loans.management') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Repayments</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">

                <form method="GET" action="{{ route('loan.repayments.index') }}" class="mb-3">
                    <div class="bg-light p-2 rounded border">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-4">
                                <label class="small mb-1">Borrower</label>
                                <input type="text" name="borrower" class="form-control" value="{{ request('borrower') }}" placeholder="Borrower name">
                            </div>

                            <div class="form-group col-md-4">
                                <label class="small mb-1">Loan Number</label>
                                <input type="text" name="loan_code" class="form-control" value="{{ request('loan_code') }}" placeholder="LN-...">
                            </div>

                            <div class="form-group col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                            </div>

                            <div class="form-group col-md-2">
                                <a href="{{ route('loan.repayments.index') }}" class="btn btn-light border btn-block">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                            <tr>
                                <th>Loan Number</th>
                                <th>Borrower</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loans as $loan)
                                @php
                                    $statusBadgeClass = match ((string) $loan->status) {
                                        'disbursed' => 'badge-primary',
                                        'partially_paid' => 'badge-info',
                                        default => 'badge-secondary',
                                    };
                                @endphp
                                <tr>
                                    <td><strong>{{ $loan->loan_code }}</strong></td>
                                    <td>{{ $loan->loanGroup?->name ?? $loan->customer?->name ?? '—' }}</td>
                                    <td>{{ $loan->loanProduct?->name ?? '—' }}</td>
                                    <td><span class="badge {{ $statusBadgeClass }}">{{ $loan->status }}</span></td>
                                    <td class="text-right">
                                        <a href="{{ route('loan.repayments.create', $loan) }}" class="btn btn-sm btn-success">
                                            Make Payment
                                        </a>
                                        <a href="{{ route('loan.repayments.history', $loan) }}" class="btn btn-sm btn-outline-primary">
                                            History
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No loans eligible for repayment.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $loans->links() }}
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
