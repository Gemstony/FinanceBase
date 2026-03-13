@extends('adminlte::page')

@section('title', 'Create Journal Entry')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-pen"></i> Create Journal Entry</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-pen"></i> Create Journal</h1>
                    <p class="mb-0 text-light">Statement #{{ $statement->id }} • Line #{{ $line->id }}</p>
                </div>
                <div>
                    <a href="{{ route('bank-reconciliation.reconcile', $statement->id) }}" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
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
            <div class="col-md-6">
                <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                    <div class="card-header">
                        <strong>Bank Statement Transaction</strong>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Date</dt>
                            <dd class="col-sm-8">{{ $line->transaction_date?->format('Y-m-d') ?? '—' }}</dd>

                            <dt class="col-sm-4">Reference</dt>
                            <dd class="col-sm-8">{{ $line->reference ?? '—' }}</dd>

                            <dt class="col-sm-4">Description</dt>
                            <dd class="col-sm-8">{{ $line->description ?? '—' }}</dd>

                            <dt class="col-sm-4">Debit</dt>
                            <dd class="col-sm-8">{{ number_format((float) $line->debit, 2) }}</dd>

                            <dt class="col-sm-4">Credit</dt>
                            <dd class="col-sm-8">{{ number_format((float) $line->credit, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                    <div class="card-header">
                        <strong>Journal Entry Details</strong>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('bank-reconciliation.lines.store-journal', [$statement->id, $line->id]) }}">
                            @csrf

                            <div class="form-group">
                                <label for="account_id">Select Expense/Income Account</label>
                                <select name="account_id" id="account_id" class="form-control @error('account_id') is-invalid @enderror" required>
                                    <option value="">-- Select Account --</option>
                                    @foreach($accounts as $a)
                                        <option value="{{ $a->id }}"
                                            @if((int) old('account_id', $suggestedAccountId ?? 0) === (int) $a->id) selected @endif>
                                            {{ $a->account_code }} - {{ $a->account_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('account_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if(!empty($suggestedAccountId))
                                    <small class="text-muted">A suggested account was pre-selected based on the description.</small>
                                @endif
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Journal Entry
                            </button>
                        </form>

                        <hr>

                        <small class="text-muted">
                            The system will automatically build a balanced double-entry journal based on whether the line is a debit (money leaving bank) or credit (money entering bank).
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop


@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
