@extends('adminlte::page')

@section('title', 'Make Payment - ' . $loan->loan_code)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-cash-register"></i> Make Payment</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-cash-register"></i> Make Payment</h1>
                    <p class="mb-0 text-light">Loan Code: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ route('loan.repayments.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
                <li class="breadcrumb-item"><a href="{{ route('loan.repayments.index') }}">Repayments</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $loan->loan_code }}</li>
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

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div class="mb-2">
                        <h4 class="mb-1">
                            {{ $loan->loanGroup?->name ?? $loan->customer?->name ?? '—' }} Loan
                        </h4>
                        <div class="text-muted">
                            {{ $loan->loanProduct?->name ?? 'Loan Product' }}
                            &middot; Loan Code: <strong>{{ $loan->loan_code }}</strong>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="mb-1">
                            @php
                                $statusBadgeClass = match ((string) $loan->status) {
                                    'disbursed' => 'badge-primary',
                                    'partially_paid' => 'badge-info',
                                    'paid_off' => 'badge-success',
                                    default => 'badge-secondary',
                                };
                            @endphp
                            <span class="badge {{ $statusBadgeClass }}">{{ $loan->status }}</span>
                        </div>
                        <div class="text-muted">
                            Balance: <strong>{{ number_format((float) ($summary['total_balance'] ?? 0), 2) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted">Principal Outstanding</div>
                        <div><strong>{{ number_format((float) ($summary['principal_outstanding'] ?? 0), 2) }}</strong></div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted">Interest Outstanding</div>
                        <div><strong>{{ number_format((float) ($summary['interest_outstanding'] ?? 0), 2) }}</strong></div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted">Penalty Outstanding</div>
                        <div><strong>{{ number_format((float) ($summary['penalties_outstanding'] ?? 0), 2) }}</strong></div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted">Loan Product</div>
                        <div><strong>{{ $loan->loanProduct?->name ?? '—' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><strong>Loan Summary</strong></div>
                    <div class="card-body">
                        <div class="mb-2"><strong>Borrower:</strong> {{ $loan->loanGroup?->name ?? $loan->customer?->name ?? '—' }}</div>
                        <div class="mb-2"><strong>Principal Outstanding:</strong> {{ number_format((float) ($summary['principal_outstanding'] ?? 0), 2) }}</div>
                        <div class="mb-2"><strong>Interest Outstanding:</strong> {{ number_format((float) ($summary['interest_outstanding'] ?? 0), 2) }}</div>
                        <div class="mb-2"><strong>Penalty Outstanding:</strong> {{ number_format((float) ($summary['penalties_outstanding'] ?? 0), 2) }}</div>
                        <div class="mb-2"><strong>Total Balance:</strong> {{ number_format((float) ($summary['total_balance'] ?? 0), 2) }}</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Payment Form</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('loan.repayments.store') }}">
                            @csrf

                            <input type="hidden" name="loan_id" value="{{ $loan->id }}">

                            @if(!$loan->customer_id)
                                <div class="form-group">
                                    <label>Payer</label>
                                    <select name="payer_customer_id" class="form-control" required>
                                        <option value="">Select Payer</option>
                                        @foreach(($payers ?? collect()) as $payer)
                                            <option value="{{ $payer->id }}" @selected((string) old('payer_customer_id') === (string) $payer->id)>
                                                {{ $payer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="form-group">
                                <label>Payment Date</label>
                                <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
                            </div>

                            <div class="form-group">
                                <label>Payment Amount</label>
                                <input type="number" step="0.01" name="payment_amount" class="form-control" value="{{ old('payment_amount') }}" required>
                            </div>

                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="payment_method" class="form-control" required>
                                    @php $pm = old('payment_method', 'cash'); @endphp
                                    <option value="cash" @selected($pm === 'cash')>Cash</option>
                                    <option value="bank_transfer" @selected($pm === 'bank_transfer')>Bank Transfer</option>
                                    <option value="mobile_money" @selected($pm === 'mobile_money')>Mobile Money</option>
                                    <option value="other" @selected($pm === 'other')>Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Transaction Reference</label>
                                <input type="text" name="transaction_reference" class="form-control" value="{{ old('transaction_reference') }}" placeholder="Optional">
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Optional">{{ old('notes') }}</textarea>
                            </div>

                            <button class="btn btn-success btn-block" type="submit">
                                <i class="fas fa-check"></i> Process Payment
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><strong>Installments</strong></div>
                    <div class="card-body table-responsive">
                        @php
                            $versions = isset($installmentsByVersion) ? $installmentsByVersion->keys()->sortDesc()->values() : collect();
                        @endphp

                        @forelse($versions as $ver)
                            @php
                                $rows = $installmentsByVersion->get($ver, collect());
                                $title = ((int) $ver === (int) ($latestScheduleVersion ?? 1)) ? 'Current Schedule' : 'Previous Schedule (Restructured)';
                                $badgeClass = ((int) $ver === (int) ($latestScheduleVersion ?? 1)) ? 'badge-success' : 'badge-secondary';
                            @endphp

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>{{ $title }}</strong>
                                    <span class="badge {{ $badgeClass }}">Version {{ (int) $ver }}</span>
                                </div>

                                <table class="table table-striped table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Due Date</th>
                                            <th>Principal</th>
                                            <th>Interest</th>
                                            <th>Fee</th>
                                            <th>Penalty</th>
                                            <th>Status</th>
                                            <th class="text-right">Outstanding</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rows as $ins)
                                            @php
                                                $badge = match ((string) $ins->status) {
                                                    'paid' => 'badge-success',
                                                    'partial' => 'badge-warning',
                                                    'overdue' => 'badge-danger',
                                                    'restructured' => 'badge-secondary',
                                                    default => 'badge-secondary',
                                                };
                                            @endphp
                                            <tr>
                                                <td>{{ $ins->installment_number }}</td>
                                                <td>{{ $ins->due_date ? \Carbon\Carbon::parse($ins->due_date)->format('Y-m-d') : '-' }}</td>
                                                <td>{{ number_format((float) $ins->principal_due, 2) }}</td>
                                                <td>{{ number_format((float) $ins->interest_due, 2) }}</td>
                                                <td>{{ number_format((float) $ins->fees_due, 2) }}</td>
                                                <td>{{ number_format((float) $ins->penalty_due, 2) }}</td>
                                                <td><span class="badge {{ $badge }}">{{ $ins->status }}</span></td>
                                                <td class="text-right">{{ number_format((float) $ins->outstanding_amount, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="8" class="text-center text-muted">No installments found.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @empty
                            <div class="text-muted">No installments found.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
