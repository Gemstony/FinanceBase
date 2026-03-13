@extends('adminlte::page')

@section('title', 'Create Bank Statement')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-file-invoice"></i> New Bank Statement</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-file-invoice"></i> New Statement</h1>
                    <p class="mb-0 text-light">Create a statement header then import CSV transactions</p>
                </div>
                <div>
                    <a href="{{ route('bank-reconciliation.index') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('bank-reconciliation.index') }}">Bank Reconciliation</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <form method="POST" action="{{ route('bank-reconciliation.store') }}">
                    @csrf

                    <div class="form-group">
                        <label>Bank Account <span class="text-danger">*</span></label>
                        <select class="form-control" name="bank_account_id" required>
                            <option value="">Select Bank Account</option>
                            @foreach($bankAccounts as $b)
                                <option value="{{ $b->id }}" @selected(old('bank_account_id') == $b->id)>{{ $b->account_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Statement Date <span class="text-danger">*</span></label>
                                <input type="date" name="statement_date" class="form-control" required value="{{ old('statement_date') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Opening Balance <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="opening_balance" class="form-control" required value="{{ old('opening_balance', '0.00') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Closing Balance <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="closing_balance" class="form-control" required value="{{ old('closing_balance', '0.00') }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Reference Number</label>
                                <input type="text" name="reference_number" class="form-control" value="{{ old('reference_number') }}" placeholder="Optional">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Notes</label>
                                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Statement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
