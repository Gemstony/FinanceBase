@extends('adminlte::page')

@section('title', 'Account Ledger – ' . $account->account_number)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-list"></i> Account Ledger</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-list"></i> Account Ledger</h1>
                    <p class="mb-0 text-light">Account: <strong>{{ $account->account_number }}</strong> – {{ $account->customer?->name ?? '—' }}</p>
                </div>
                <div>
                    <a href="{{ route('deposits.show', $account->customer) }}" class="btn btn-light border">
                        <i class="fas fa-arrow-left"></i> Back to Accounts
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('deposits.index') }}">Customer Deposit Accounts</a></li>
                <li class="breadcrumb-item"><a href="{{ route('deposits.show', $account->customer) }}">{{ $account->customer?->name }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Ledger – {{ $account->account_number }}</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><strong>Account Summary</strong></div>
                    <div class="card-body">
                        <div class="mb-2"><strong>Account:</strong> {{ $account->account_number }}</div>
                        <div class="mb-2"><strong>Product:</strong> {{ $account->depositProduct?->name ?? '—' }}</div>
                        <div class="mb-2"><strong>Status:</strong>
                            @php
                                $cls = match((string) $account->status) {
                                    'active' => 'badge-success',
                                    'frozen' => 'badge-warning',
                                    'dormant' => 'badge-secondary',
                                    'closed' => 'badge-dark',
                                    default => 'badge-light',
                                };
                            @endphp
                            <span class="badge {{ $cls }}">{{ ucfirst($account->status) }}</span>
                        </div>
                        <div class="mb-2"><strong>Current Balance:</strong> <strong>{{ number_format((float) $account->balance, 2) }}</strong></div>
                        <div class="mb-2"><strong>Opened:</strong> {{ $account->opened_at?->format('Y-m-d') ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><strong>Transaction History</strong></div>
                    <div class="card-body table-responsive">
                        <table class="table table-striped table-hover" id="transactionsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Type</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Balance After</th>
                                    <th>Reference</th>
                                    <th>Notes</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $t)
                                    <tr>
                                        <td>{{ $t->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                        <td>
                                            @php
                                                $badge = match((string) $t->transaction_type) {
                                                    'deposit' => 'badge-success',
                                                    'withdrawal' => 'badge-danger',
                                                    'transfer' => 'badge-info',
                                                    'loan_payment' => 'badge-warning',
                                                    'interest' => 'badge-primary',
                                                    'fee' => 'badge-secondary',
                                                    'adjustment' => 'badge-dark',
                                                    default => 'badge-light',
                                                };
                                            @endphp
                                            <span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $t->transaction_type)) }}</span>
                                        </td>
                                        <td class="text-right">{{ number_format((float) $t->amount, 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $t->balance_after, 2) }}</td>
                                        <td>{{ $t->reference ?? '—' }}</td>
                                        <td>{{ $t->notes ? Str::limit($t->notes, 50) : '—' }}</td>
                                        <td>{{ $t->createdBy?->name ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No transactions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="mt-3">
                            {{ $transactions->links() }}
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

@push('js')
<script>
$(document).ready(function() {
    if ($('#transactionsTable').length) {
        $('#transactionsTable').DataTable({
            responsive: true,
            order: [[0, 'desc']],
            pageLength: 50
        });
    }
});
</script>
@endpush
