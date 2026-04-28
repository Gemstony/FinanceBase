@extends('adminlte::page')

@section('title', 'Transaction Details')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-receipt"></i> Transaction Details</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-receipt"></i> Details</h1>
                <p class="mb-0 text-light">View transaction information and logs</p>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.payment_settings.index') }}">Payment Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('payments.transactions') }}">Payment Transactions</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">Transaction Details</li>
        </ol>
    </nav>
</div>
@stop

@section('content')
    <div class="row">
        <div class="col-md-8">
            <!-- Transaction Details -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Transaction Information</h3>
                </div>
                <div class="card-body">
                    
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tr>
                            <th width="200">Reference</th>
                            <td>{{ $transaction->reference }}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
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
                        </tr>
                        <tr>
                            <th>Amount</th>
                            <td>{{ number_format($transaction->amount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Provider</th>
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
                        </tr>
                        <tr>
                            <th>Channel</th>
                            <td>{{ strtoupper($transaction->channel) }}</td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td>{{ $transaction->phone }}</td>
                        </tr>
                        <tr>
                            <th>External ID</th>
                            <td>{{ $transaction->external_id ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Customer</th>
                            <td>
                                @if($transaction->customer)
                                    <a href="{{ route('customers.show', $transaction->customer_id) }}">
                                        {{ $transaction->customer->name }}
                                    </a>
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Loan</th>
                            <td>
                                @if($transaction->loan)
                                    <a href="{{ route('loans.loans.show', $transaction->loan->loan_code) }}">
                                        {{ $transaction->loan->loan_code }}
                                    </a>
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Initiated At</th>
                            <td>{{ $transaction->initiated_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Completed At</th>
                            <td>{{ $transaction->completed_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td>{{ $transaction->created_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    </table>
                </div>

                    @if($transaction->provider_response)
                        <div class="mt-4">
                            <h5>Provider Response</h5>
                            <pre class="bg-light p-3">{{ json_encode(json_decode($transaction->provider_response), JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif

                    @if($transaction->meta)
                        <div class="mt-4">
                            <h5>Meta Data</h5>
                            <pre class="bg-light p-3">{{ json_encode($transaction->meta, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Transaction Logs -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Transaction Logs</h3>
                </div>
                <div class="card-body">
                    @if($transaction->logs->count() > 0)
                        <div class="timeline">
                            @foreach($transaction->logs as $log)
                                <div class="time-label">
                                    <span class="bg-info">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                                </div>
                                <div>
                                    <i class="fas fa-{{ $log->status === 'success' ? 'check bg-success' : ($log->status === 'failed' ? 'times bg-danger' : 'clock bg-warning') }}"></i>
                                    <div class="timeline-item">
                                        <span class="time">
                                            <i class="fas fa-clock"></i> {{ $log->created_at->format('H:i:s') }}
                                        </span>
                                        <h3 class="timeline-header">
                                            <span class="badge badge-{{ $log->status === 'success' ? 'success' : ($log->status === 'failed' ? 'danger' : 'warning') }}">
                                                {{ ucfirst($log->status) }}
                                            </span>
                                        </h3>
                                        <div class="timeline-body">
                                            @if($log->request_payload)
                                                <strong>Request:</strong>
                                                <pre class="bg-light p-2 mt-1" style="font-size: 12px; max-height: 100px; overflow-y: auto;">{{ json_encode(json_decode($log->request_payload), JSON_PRETTY_PRINT) }}</pre>
                                            @endif
                                            @if($log->response_payload)
                                                <strong>Response:</strong>
                                                <pre class="bg-light p-2 mt-1" style="font-size: 12px; max-height: 100px; overflow-y: auto;">{{ json_encode(json_decode($log->response_payload), JSON_PRETTY_PRINT) }}</pre>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            <div>
                                <i class="fas fa-clock bg-gray"></i>
                            </div>
                        </div>
                    @else
                        <p class="text-muted">No logs available for this transaction.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 mb-3">
        <a href="{{ route('payments.transactions') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Transactions
        </a>
    </div>
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
