@extends('adminlte::page')

@section('title', 'Collect Security Deposit')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-plus"></i> Collect Security Deposit</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-plus"></i> Collect Deposit</h1>
                    <p class="mb-0 text-light">Loan: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><strong>Deposit Collection</strong></div>
            <div class="card-body">
                <div class="mb-2"><strong>Required:</strong> {{ number_format((float) ($loan->security_deposit_amount ?? 0), 2) }}</div>

                <form method="POST" action="{{ route('security-deposits.collect', $loan) }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Amount</label>
                            <input type="number" name="amount" step="0.01" min="0" class="form-control" value="{{ old('amount') }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Payment Method</label>
                            <select name="payment_method" class="form-control" required>
                                @php $pm = old('payment_method', 'cash'); @endphp
                                <option value="cash" @selected($pm === 'cash')>Cash</option>
                                <option value="bank_transfer" @selected($pm === 'bank_transfer')>Bank Transfer</option>
                                <option value="mobile_money" @selected($pm === 'mobile_money')>Mobile Money</option>
                                <option value="other" @selected($pm === 'other')>Other</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Notes</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Collect
                    </button>
                </form>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
