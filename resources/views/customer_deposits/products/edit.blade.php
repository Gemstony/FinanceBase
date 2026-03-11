@extends('adminlte::page')

@section('title', 'Edit Deposit Product – ' . $product->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-edit"></i> Edit Deposit Product</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-edit"></i> Edit Deposit Product</h1>
                    <p class="mb-0 text-light">{{ $product->name }}</p>
                </div>
                <a href="{{ route('deposits.products.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('deposits.index') }}">Customer Deposit Accounts</a></li>
                <li class="breadcrumb-item"><a href="{{ route('deposits.products.index') }}">Products</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><strong>Edit Deposit Product</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('deposits.products.update', $product) }}">
                            @csrf
                            @method('PUT')

                            <div class="form-group">
                                <label for="name">Product Name</label>
                                <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                            </div>

                            <div class="form-group">
                                <label for="type">Product Type</label>
                                <select name="type" id="type" class="form-control" required>
                                    <option value="">Select type</option>
                                    <option value="savings" @selected(old('type', $product->type) === 'savings')>Savings</option>
                                    <option value="current" @selected(old('type', $product->type) === 'current')>Current</option>
                                    <option value="term_deposit" @selected(old('type', $product->type) === 'term_deposit')>Term Deposit</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="interest_rate">Interest Rate (%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="interest_rate" id="interest_rate" class="form-control" value="{{ old('interest_rate', $product->interest_rate) }}" required>
                            rate</div>

                            <div class="form-group">
                                <label for="minimum_balance">Minimum Balance</label>
                                <input type="number" step="0.01" min="0" name="minimum_balance" id="minimum_balance" class="form-control" value="{{ old('minimum_balance', $product->minimum_balance) }}" required>
                            </div>

                            <div class="form-group">
                                <label for="withdrawal_fee">Withdrawal Fee</label>
                                <input type="number" step="0.01" min="0" name="withdrawal_fee" id="withdrawal_fee" class="form-control" value="{{ old('withdrawal_fee', $product->withdrawal_fee) }}" required>
                            </div>

                            <div class="form-group">
                                <label for="description">Description (optional)</label>
                                <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $product->description) }}</textarea>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $product->is_active))>
                                    <label class="form-check-label" for="is_active">
                                        Active
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Product
                                </button>
                                <a href="{{ route('deposits.products.index') }}" class="btn btn-light ml-2">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script>
$(document).ready(function() {
    // Optional: Add any client-side validation or helpers here
});
</script>
@endpush
