@extends('adminlte::page')

@section('title', 'Write-Off Recoveries')

@section('content_header')
 <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
     <div class="card-body">
         <div class="d-flex justify-content-between align-items-center">
             <div>
                 <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Recoveries</h1>
                 <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Recoveries</h1>
                 <p class="mb-0 text-light">Write-Off #: <strong>{{ $writeoff->id }}</strong></p>
             </div>
             <div>
                 <a href="{{ route('writeoffs.show', $writeoff) }}" class="btn btn-light">
                     <i class="fas fa-arrow-left"></i> Details
                 </a>
                 @if($writeoff->loan)
                     <a href="{{ route('loans.loans.show', $writeoff->loan) }}" class="btn btn-light">
                         <i class="fas fa-external-link-alt"></i> Loan
                     </a>
                 @endif
             </div>
         </div>
     </div>
 </div>
 <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
             <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
             <li class="breadcrumb-item"><a href="{{ route('writeoffs.index') }}">Write-Offs</a></li>
             <li class="breadcrumb-item"><a href="{{ route('writeoffs.show', $writeoff) }}">Details</a></li>
             <li class="breadcrumb-item active" aria-current="page">Recoveries</li>
         </ol>
     </nav>
     <a href="{{ route('writeoffs.show', $writeoff) }}" class="btn btn-light border">
         <i class="fas fa-arrow-left"></i> Back
     </a>
 </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-header"><strong>Write-Off</strong></div>
        <div class="card-body">
            <div><strong>Loan ID:</strong> {{ $writeoff->loan_id }}</div>
            <div><strong>Borrower:</strong> {{ $borrowerName ?? '-' }}</div>
            <div><strong>Total Written-Off:</strong> {{ number_format((float) $writeoff->total_written_off, 2) }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Recovery History</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Recovery ID</th>
                            <th>Loan ID</th>
                            <th>Borrower</th>
                            <th>Date</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Fees</th>
                            <th>Penalties</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recoveries as $r)
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td>{{ $r->loan_id }}</td>
                                <td>
                                    @php
                                        $bn = $r->loan?->borrower_type === 'group' ? ($r->loan?->loanGroup?->name ?? '-') : ($r->loan?->customer?->name ?? '-');
                                    @endphp
                                    {{ $bn }}
                                </td>
                                <td>{{ optional($r->recovery_date)->format('Y-m-d') }}</td>
                                <td>{{ number_format((float) $r->recovered_principal, 2) }}</td>
                                <td>{{ number_format((float) $r->recovered_interest, 2) }}</td>
                                <td>{{ number_format((float) $r->recovered_fees, 2) }}</td>
                                <td>{{ number_format((float) $r->recovered_penalties, 2) }}</td>
                                <td><strong>{{ number_format((float) $r->total_recovered, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">No recoveries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-right">Totals</th>
                            <th>{{ number_format((float) ($totals['principal'] ?? 0), 2) }}</th>
                            <th>{{ number_format((float) ($totals['interest'] ?? 0), 2) }}</th>
                            <th>{{ number_format((float) ($totals['fees'] ?? 0), 2) }}</th>
                            <th>{{ number_format((float) ($totals['penalties'] ?? 0), 2) }}</th>
                            <th><strong>{{ number_format((float) ($totals['total'] ?? 0), 2) }}</strong></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                {{ $recoveries->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
