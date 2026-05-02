@extends('adminlte::page')

@section('title', 'Fee Payment History - ' . $loan->loan_code)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-history"></i> Fee Payment History</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-history"></i> Fee Payment History</h1>
                    <p class="mb-0 text-light">Loan: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Loan
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
                <li class="breadcrumb-item"><a href="{{ route('loans.loans.show', $loan) }}">{{ $loan->loan_code }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Fee Payment History</li>
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

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong><i class="fas fa-history"></i> Paid Fees</strong>
                    @if($totalPaid > 0)
                        <span class="badge badge-success">{{ number_format((float)$totalPaid, 2) }} Paid</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($paidFees->isEmpty())
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No fee payments recorded yet.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Fee Name</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Paid By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($paidFees as $fee)
                                        <tr>
                                            <td>{{ $fee->payment_date ? \Carbon\Carbon::parse($fee->payment_date)->format('Y-m-d') : '-' }}</td>
                                            <td>{{ $fee->loanProductFee?->loanFee?->name ?? 'Fee' }}</td>
                                            <td>{{ number_format((float)$fee->paid_amount, 2) }}</td>
                                            <td>{{ $fee->payment_method ?? '-' }}</td>
                                            <td>{{ $fee->paidBy?->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <td colspan="2"><strong>Total Paid:</strong></td>
                                        <td colspan="3"><strong>{{ number_format((float)$totalPaid, 2) }}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white">
                    <strong><i class="fas fa-info-circle"></i> Loan Summary</strong>
                </div>
                <div class="card-body">
                    <div class="mb-2"><strong>Loan Code:</strong> {{ $loan->loan_code }}</div>
                    <div class="mb-2"><strong>{{ $loan->borrower_type === 'group' ? 'Group' : 'Customer' }}:</strong> {{ $loan->borrower_type === 'group' ? ($loan->loanGroup?->name ?? '-') : ($loan->customer?->name ?? '-') }}</div>
                    <div class="mb-2"><strong>Amount:</strong> {{ number_format((float)$loan->principal_amount, 2) }}</div>
                    <div class="mb-2"><strong>Status:</strong>
                        @php
                            $statusBadgeClass = match($loan->status) {
                                'pending' => 'badge-warning',
                                'approved' => 'badge-info',
                                'disbursed' => 'badge-success',
                                'partially_paid' => 'badge-primary',
                                'paid' => 'badge-success',
                                'defaulted' => 'badge-danger',
                                'written_off' => 'badge-secondary',
                                'restructured' => 'badge-dark',
                                default => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $statusBadgeClass }}">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span>
                    </div>
                    <hr>
                    <a href="{{ route('loans.fees.payment-form', $loan) }}" class="btn btn-sm btn-outline-primary btn-block">
                        <i class="fas fa-file-invoice-dollar"></i> Pay Fees
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
