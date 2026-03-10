@extends('adminlte::page')

@section('title', 'Customer Credit History')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-wallet"></i> Credit History</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-wallet"></i> Credit History</h1>
                    <p class="mb-0 text-light">Borrower: <strong>{{ $customer->name }}</strong></p>
                </div>
                <a href="{{ route('credits.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('credits.index') }}">Customer Credits</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $customer->name }}</li>
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
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><strong>Totals</strong></div>
                    <div class="card-body">
                        <div class="mb-2"><strong>Available:</strong> {{ number_format((float) $availableTotal, 2) }}</div>
                        <div class="mb-2"><strong>Applied:</strong> {{ number_format((float) $appliedTotal, 2) }}</div>
                        <div class="mb-2"><strong>Refunded:</strong> {{ number_format((float) $refundedTotal, 2) }}</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Apply / Refund</strong></div>
                    <div class="card-body">
                        <div class="text-muted mb-2">
                            Apply credit will create a repayment using payment method <strong>customer_credit</strong>.
                        </div>

                        <form method="POST" action="{{ route('credits.apply') }}" class="mb-3">
                            @csrf
                            <div class="form-group">
                                <label>Available Credit</label>
                                <select name="credit_id" class="form-control" required>
                                    <option value="">Select credit</option>
                                    @foreach(($availableCredits ?? collect()) as $c)
                                        <option value="{{ $c->id }}">#{{ $c->id }} - {{ number_format((float) $c->amount, 2) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Target Loan</label>
                                <select name="loan_id" class="form-control" required>
                                    <option value="">Select active loan</option>
                                    @foreach(($activeLoans ?? collect()) as $l)
                                        <option value="{{ $l->id }}">{{ $l->loan_code }} - Balance: {{ number_format((float) $l->outstanding_balance, 2) }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Only active loans with outstanding balance are shown.</small>
                            </div>
                            <button class="btn btn-primary btn-block" type="submit">
                                <i class="fas fa-check"></i> Apply Credit
                            </button>
                        </form>

                        <form method="POST" action="{{ route('credits.refund') }}">
                            @csrf
                            <div class="form-group">
                                <label>Available Credit</label>
                                <select name="credit_id" class="form-control" required>
                                    <option value="">Select credit</option>
                                    @foreach(($availableCredits ?? collect()) as $c)
                                        <option value="{{ $c->id }}">#{{ $c->id }} - {{ number_format((float) $c->amount, 2) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Refund Reason</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Optional"></textarea>
                            </div>
                            <button class="btn btn-outline-danger btn-block" type="submit">
                                <i class="fas fa-undo"></i> Refund Credit
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><strong>Credit History</strong></div>
                    <div class="card-body table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Amount</th>
                                    <th>Source Loan</th>
                                    <th>Status</th>
                                    <th>Applied Loan</th>
                                    <th>Refunded</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($credits as $credit)
                                    <tr>
                                        <td>{{ number_format((float) $credit->amount, 2) }}</td>
                                        <td>{{ $credit->loan?->loan_code ?? '—' }}</td>
                                        <td>
                                            @php
                                                $cls = match((string) $credit->status) {
                                                    'available' => 'badge-success',
                                                    'applied' => 'badge-info',
                                                    'refunded' => 'badge-secondary',
                                                    default => 'badge-light',
                                                };
                                            @endphp
                                            <span class="badge {{ $cls }}">{{ ucfirst($credit->status) }}</span>
                                        </td>
                                        <td>{{ $credit->appliedToLoan?->loan_code ?? '—' }}</td>
                                        <td>{{ $credit->refunded_at?->format('Y-m-d') ?? '—' }}</td>
                                        <td>{{ $credit->created_at?->format('Y-m-d') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No credits found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="mt-3">
                            {{ $credits->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
