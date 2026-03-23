@extends('adminlte::page')

@section('title', 'Payment History - ' . $loan->loan_code)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-receipt"></i> Payment History</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-receipt"></i> Payment History</h1>
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
                <li class="breadcrumb-item active" aria-current="page">History</li>
            </ol>
        </nav>
        <a href="{{ route('loan.repayments.create', $loan) }}" class="btn btn-success">
            <i class="fas fa-plus"></i> Make Payment
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
                    </div>
                </div>

                @isset($summary)
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
                            <div class="text-muted">Total Balance</div>
                            <div><strong>{{ number_format((float) ($summary['total_balance'] ?? 0), 2) }}</strong></div>
                        </div>
                    </div>
                @endisset
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Penalty</th>
                            <th>Fee</th>
                            <th>Method</th>
                            <th>Officer</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $p)
                            @php
                                $principal = (float) $p->allocations->sum('principal_amount');
                                $interest = (float) $p->allocations->sum('interest_amount');
                                $fee = (float) $p->allocations->sum('fee_amount');
                                $penalty = (float) $p->allocations->sum('penalty_amount');

                                $badge = match ((string) $p->status) {
                                    'confirmed' => 'badge-success',
                                    'reversed' => 'badge-secondary',
                                    'failed' => 'badge-danger',
                                    default => 'badge-info',
                                };
                            @endphp
                            <tr>
                                <td>{{ $p->payment_date?->format('Y-m-d') }}</td>
                                <td>{{ number_format((float) $p->amount, 2) }}</td>
                                <td>{{ number_format($principal, 2) }}</td>
                                <td>{{ number_format($interest, 2) }}</td>
                                <td>{{ number_format($penalty, 2) }}</td>
                                <td>{{ number_format($fee, 2) }}</td>
                                <td>{{ $p->payment_method ?? '—' }}</td>
                                <td>{{ $p->user?->name ?? '—' }}</td>
                                <td><span class="badge {{ $badge }}">{{ $p->status }}</span></td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('loan.repayments.receipt', $p->id) }}">Receipt</a>
                                    @if((string) $p->status === 'confirmed')
                                        <form method="POST" action="{{ route('loan.repayments.reverse', $p->id) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Reverse</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">No payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex justify-content-end">
                {{ $payments->links() }}
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Replace browser confirm with SweetAlert for Reverse button
        const reverseForms = document.querySelectorAll('form[action*="/reverse"]');
        reverseForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Reverse Payment',
                    text: 'Are you sure you want to reverse this payment?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Reverse',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Reversing Payment...',
                            text: 'Please wait while we reverse the payment.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit the form
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush

