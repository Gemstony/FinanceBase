@extends('adminlte::page')

@section('title', 'Write Off Loan')

@section('content_header')
 <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
     <div class="card-body">
         <div class="d-flex justify-content-between align-items-center">
             <div>
                 <h1 class="d-none d-md-block text-light"><i class="fas fa-ban"></i> Write Off Loan</h1>
                 <h1 class="d-md-none text-light"><i class="fas fa-ban"></i> Write Off Loan</h1>
                 <p class="mb-0 text-light">Loan Code: <strong>{{ $loan->loan_code }}</strong></p>
             </div>
             <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light">
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
             <li class="breadcrumb-item"><a href="{{ route('loans.loans.index') }}">Loans</a></li>
             <li class="breadcrumb-item"><a href="{{ route('loans.loans.show', $loan) }}">Loan</a></li>
             <li class="breadcrumb-item active" aria-current="page">Write Off</li>
         </ol>
     </nav>
     <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light border">
         <i class="fas fa-arrow-left"></i> Back
     </a>
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
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-warning">
        <strong>Warning:</strong> This action will close all remaining installments and move the loan to recovery status.
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Loan Information</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div><strong>Borrower:</strong>
                        @if($loan->borrower_type === 'group')
                            {{ $loan->loanGroup?->name }}
                        @else
                            {{ $loan->customer?->name }}
                        @endif
                    </div>
                    <div><strong>Loan Code:</strong> {{ $loan->loan_code }}</div>
                    <div><strong>Status:</strong> {{ $loan->status }}</div>
                </div>
                <div class="col-md-6">
                    <div><strong>Product:</strong> {{ $loan->loanProduct?->name }}</div>
                    <div><strong>Principal:</strong> {{ number_format((float) $loan->principal_amount, 2) }}</div>
                    <div><strong>Interest Rate:</strong> {{ number_format((float) $loan->interest_rate, 2) }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Outstanding Amounts</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Outstanding Principal</div>
                    <div><strong>{{ number_format((float)($balances['principal_written_off'] ?? 0), 2) }}</strong></div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Outstanding Interest</div>
                    <div><strong>{{ number_format((float)($balances['interest_written_off'] ?? 0), 2) }}</strong></div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Outstanding Fees</div>
                    <div><strong>{{ number_format((float)($balances['fees_written_off'] ?? 0), 2) }}</strong></div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Outstanding Penalties</div>
                    <div><strong>{{ number_format((float)($balances['penalties_written_off'] ?? 0), 2) }}</strong></div>
                </div>
            </div>
            <hr>
            <div class="d-flex justify-content-between">
                <div class="text-muted">Total Outstanding</div>
                <div><strong>{{ number_format((float)($balances['total_written_off'] ?? 0), 2) }}</strong></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Write-Off Details</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('loans.writeoff.store', $loan) }}">
                @csrf

                <div class="form-group">
                    <label for="writeoff_date">Write-Off Date</label>
                    <input type="date" class="form-control" id="writeoff_date" name="writeoff_date" value="{{ old('writeoff_date', $today ?? now()->toDateString()) }}" required>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Write-Off</label>
                    <textarea class="form-control" id="reason" name="reason" rows="3" required>{{ old('reason') }}</textarea>
                </div>

                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-check"></i> Confirm Write-Off
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush