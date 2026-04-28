@extends('adminlte::page')

@section('title', 'Bank Account Dashboard - ' . ($bankAccount->account_name ?? ''))

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-university"></i> Bank Account Dashboard</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-university"></i> Dashboard</h1>
                <p class="mb-0 text-light">
                    {{ $bankAccount->account_name }}
                    @if(!empty($bankAccount->bank_name))
                        - <strong>{{ $bankAccount->bank_name }}</strong>
                    @endif
                </p>
            </div>
            <div class="mt-2 mt-md-0">
                <a href="{{ url()->previous() }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap">
    <nav aria-label="breadcrumb" class="mb-2 mb-md-0">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('accounting.bank_accounts.index') }}">Bank Accounts</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $bankAccount->account_name }}</li>
        </ol>
    </nav>

    <div class="btn-toolbar" role="toolbar">
        <div class="btn-group mr-2 mb-2" role="group">
            <a href="{{ route('bank-reconciliation.create') }}" class="btn btn-outline-success"><i class="fas fa-balance-scale"></i> Start Reconciliation</a>
            <a href="#" class="btn btn-outline-dark disabled"><i class="fas fa-file-export"></i> Export Bank Ledger</a>
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
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> Bank Account Information</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Account Name</dt>
                        <dd class="col-7">{{ $bankAccount->account_name }}</dd>

                        <dt class="col-5">Type</dt>
                        <dd class="col-7">{{ ucfirst($bankAccount->account_type ?? 'N/A') }}</dd>

                        <dt class="col-5">Bank Name</dt>
                        <dd class="col-7">{{ $bankAccount->bank_name ?? 'N/A' }}</dd>

                        <dt class="col-5">Account No.</dt>
                        <dd class="col-7">{{ $bankAccount->account_number ?? 'N/A' }}</dd>

                        <dt class="col-5">Branch</dt>
                        <dd class="col-7">{{ $bankAccount->branch ?? 'N/A' }}</dd>

                        <dt class="col-5">Currency</dt>
                        <dd class="col-7">{{ $bankAccount->currency_code ?? 'N/A' }}</dd>

                        <dt class="col-5">GL Account</dt>
                        <dd class="col-7">
                            @if($bankAccount->chartOfAccount)
                                {{ $bankAccount->chartOfAccount->account_name }}
                                <br>
                                <small class="text-muted">{{ $bankAccount->chartOfAccount->account_code ?? '' }}</small>
                            @else
                                N/A
                            @endif
                        </dd>

                        <dt class="col-5">Status</dt>
                        <dd class="col-7">
                            <span class="badge {{ $bankAccount->is_active ? 'badge-success' : 'badge-secondary' }}">
                                {{ $bankAccount->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </dd>

                        <dt class="col-5">Created</dt>
                        <dd class="col-7">{{ optional($bankAccount->created_at)->format('Y-m-d') ?? 'N/A' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-link"></i> Reconciliation Status</h3>
                </div>
                <div class="card-body">
                    @php
                        $diff = (float) ($reconciliationStatus['difference'] ?? 0);
                        $diffIsZero = round($diff, 2) == 0.0;
                    @endphp

                    <dl class="row mb-0">
                        <dt class="col-6">Last Reconciled</dt>
                        <dd class="col-6">
                            @if(!empty($reconciliationStatus['last_reconciled_at']))
                                {{ optional($reconciliationStatus['last_reconciled_at'])->format('Y-m-d') }}
                            @else
                                N/A
                            @endif
                        </dd>

                        <dt class="col-6">Statement Balance</dt>
                        <dd class="col-6">
                            {{ number_format((float) ($reconciliationStatus['last_statement_closing_balance'] ?? ($reconciliationStatus['latest_statement_closing_balance'] ?? 0)), 2) }}
                        </dd>

                        <dt class="col-6">Ledger Balance</dt>
                        <dd class="col-6">
                            {{ number_format((float) ($reconciliationStatus['ledger_balance'] ?? 0), 2) }}
                        </dd>

                        <dt class="col-6">Difference</dt>
                        <dd class="col-6">
                            <span class="{{ $diffIsZero ? 'text-success' : 'text-danger' }}">
                                {{ number_format($diff, 2) }}
                            </span>
                        </dd>

                        <dt class="col-6">Unmatched (Latest)</dt>
                        <dd class="col-6">{{ (int) ($reconciliationStatus['unmatched_statement_lines'] ?? 0) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small">Current Balance</div>
                                    <div class="h4 mb-0">{{ number_format((float) ($stats['ledger_balance'] ?? 0), 2) }}</div>
                                </div>
                                <i class="fas fa-wallet fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small">Total Inflows</div>
                                    <div class="h4 mb-0">{{ number_format((float) ($stats['total_inflows'] ?? 0), 2) }}</div>
                                </div>
                                <i class="fas fa-arrow-down fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small">Total Outflows</div>
                                    <div class="h4 mb-0">{{ number_format((float) ($stats['total_outflows'] ?? 0), 2) }}</div>
                                </div>
                                <i class="fas fa-arrow-up fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small">Total Transactions</div>
                                    <div class="h4 mb-0">{{ number_format((int) ($stats['total_transactions'] ?? 0)) }}</div>
                                </div>
                                <i class="fas fa-exchange-alt fa-2x text-muted"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small">Unreconciled Transactions</div>
                                    <div class="h4 mb-0">{{ number_format((int) ($stats['unreconciled_transactions'] ?? 0)) }}</div>
                                </div>
                                <i class="fas fa-unlink fa-2x text-muted"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-area"></i> Monthly Activity (Last 12 Months)</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Month</th>
                                    <th class="text-right">Inflows</th>
                                    <th class="text-right">Outflows</th>
                                    <th class="text-right">Net</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($monthlyActivity as $row)
                                    @php
                                        $net = (float) ($row['net'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td>{{ optional($row['month'])->format('M Y') }}</td>
                                        <td class="text-right">{{ number_format((float) ($row['inflows'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($row['outflows'] ?? 0), 2) }}</td>
                                        <td class="text-right {{ $net < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($net, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No monthly activity found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Recent Transactions</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Credit</th>
                                    <th class="text-right">Balance</th>
                                    <th>Reference Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentTransactions as $tx)
                                    <tr>
                                        <td>{{ optional($tx['date'])->format('Y-m-d') ?? 'N/A' }}</td>
                                        <td>{{ $tx['description'] ?? '' }}</td>
                                        <td class="text-right">{{ number_format((float) ($tx['debit'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($tx['credit'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($tx['balance'] ?? 0), 2) }}</td>
                                        <td>{{ $tx['reference_type'] ?? 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No transactions found for this account.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-invoice"></i> Bank Statement History</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Statement Date</th>
                                    <th class="text-right">Opening</th>
                                    <th class="text-right">Closing</th>
                                    <th>Status</th>
                                    <th style="width: 220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($statements as $st)
                                    <tr>
                                        <td>{{ optional($st->statement_date)->format('Y-m-d') ?? 'N/A' }}</td>
                                        <td class="text-right">{{ number_format((float) ($st->opening_balance ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($st->closing_balance ?? 0), 2) }}</td>
                                        <td>
                                            @php
                                                $status = (string) ($st->status ?? 'draft');
                                                $badge = 'badge-secondary';
                                                if ($status === 'reconciled') $badge = 'badge-success';
                                                elseif ($status === 'draft') $badge = 'badge-warning';
                                            @endphp
                                            <span class="badge {{ $badge }}">{{ ucfirst($status) }}</span>
                                        </td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('bank-reconciliation.show', $st->id) }}">
                                                <i class="fas fa-eye"></i> View Statement
                                            </a>
                                            @if((string) $st->status !== 'reconciled')
                                                <a class="btn btn-sm btn-outline-success" href="{{ route('bank-reconciliation.reconcile', $st->id) }}">
                                                    <i class="fas fa-balance-scale"></i> Start Reconciliation
                                                </a>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                                    <i class="fas fa-check"></i> Reconciled
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No bank statements found for this account.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-layer-group"></i> Transaction Source Breakdown</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Reference Type</th>
                                    <th class="text-right">Inflows</th>
                                    <th class="text-right">Outflows</th>
                                    <th class="text-right">Net</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sourceBreakdown as $row)
                                    @php
                                        $net = (float) ($row['net'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $row['reference_type'] ?? 'unknown' }}</td>
                                        <td class="text-right">{{ number_format((float) ($row['inflows'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($row['outflows'] ?? 0), 2) }}</td>
                                        <td class="text-right {{ $net < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($net, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No breakdown available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
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
