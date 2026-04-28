@extends('adminlte::page')

@section('title', 'Payment Transactions')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exchange-alt"></i> Payment Transactions</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exchange-alt"></i> Transactions</h1>
                <p class="mb-0 text-light">View and manage payment transactions</p>
            </div>
            <a href="{{ route('settings.payment_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.payment_settings.index') }}">Payment Settings</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">Payment Transactions</li>
        </ol>
    </nav>
</div>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Transactions</h3>
            <div class="card-tools">
                <a href="{{ route('payments.transactions.export', request()->query()) }}" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    {{ session('success') }}
                </div>
            @endif

            <!-- Filters -->
            <form method="GET" action="{{ route('payments.transactions') }}" class="mb-4">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="initiated" {{ request('status') === 'initiated' ? 'selected' : '' }}>Initiated</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                                <option value="reversed" {{ request('status') === 'reversed' ? 'selected' : '' }}>Reversed</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="provider">Provider</label>
                            <select name="provider" id="provider" class="form-control">
                                <option value="">All Providers</option>
                                <option value="clickpesa" {{ request('provider') === 'clickpesa' ? 'selected' : '' }}>ClickPesa</option>
                                <option value="azampay" {{ request('provider') === 'azampay' ? 'selected' : '' }}>AzamPay</option>
                                <option value="mpesa" {{ request('provider') === 'mpesa' ? 'selected' : '' }}>M-Pesa</option>
                                <option value="airtel" {{ request('provider') === 'airtel' ? 'selected' : '' }}>Airtel Money</option>
                                <option value="tigo" {{ request('provider') === 'tigo' ? 'selected' : '' }}>Tigo Pesa</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="{{ route('payments.transactions') }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Transactions Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Amount</th>
                            <th>Provider</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->reference }}</td>
                                <td>{{ $transaction->customer?->name ?? 'N/A' }}</td>
                                <td>{{ $transaction->phone }}</td>
                                <td>{{ number_format($transaction->amount, 2) }}</td>
                                <td>
                                    @php
                                        $badgeClass = match($transaction->provider) {
                                            'mpesa' => 'success',
                                            'airtel' => 'danger',
                                            'tigo' => 'info',
                                            'clickpesa' => 'primary',
                                            'azampay' => 'warning',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $badgeClass }}">
                                        {{ ucfirst($transaction->provider) }}
                                    </span>
                                </td>
                                <td>{{ strtoupper($transaction->channel) }}</td>
                                <td>
                                    @if($transaction->status === 'success')
                                        <span class="badge badge-success">Success</span>
                                    @elseif($transaction->status === 'failed')
                                        <span class="badge badge-danger">Failed</span>
                                    @elseif($transaction->status === 'pending')
                                        <span class="badge badge-warning">Pending</span>
                                    @elseif($transaction->status === 'reversed')
                                        <span class="badge badge-secondary">Reversed</span>
                                    @else
                                        <span class="badge badge-info">Initiated</span>
                                    @endif
                                </td>
                                <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('payments.transactions.show', $transaction->id) }}" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-center">
                {{ $transactions->withQueryString()->links() }}
            </div>
        </div>
    </div>
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush