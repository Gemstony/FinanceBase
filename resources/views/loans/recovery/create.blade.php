@extends('adminlte::page')

@section('title', 'Record Loan Recovery')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">Record Recovery</h1>
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

    <div class="card mb-3">
        <div class="card-header"><strong>Loan Information</strong></div>
        <div class="card-body">
            <div><strong>Loan Code:</strong> {{ $loan->loan_code }}</div>
            <div><strong>Status:</strong> {{ $loan->status }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Recovery Details</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('loans.recovery.store', $loan) }}">
                @csrf

                <div class="form-group">
                    <label for="recovery_date">Recovery Date</label>
                    <input type="date" class="form-control" id="recovery_date" name="recovery_date" value="{{ old('recovery_date', $today ?? now()->toDateString()) }}" required>
                </div>

                <div class="form-group">
                    <label for="amount">Total Recovery Amount</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" required>
                    <small class="text-muted">The system will automatically allocate this amount to penalties, fees, interest, then principal.</small>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Record Recovery
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush