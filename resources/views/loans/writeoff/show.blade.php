@extends('adminlte::page')

@section('title', 'Write-Off Details')

@section('content_header')
 <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
     <div class="card-body">
         <div class="d-flex justify-content-between align-items-center">
             <div>
                 <h1 class="d-none d-md-block text-light"><i class="fas fa-ban"></i> Write-Off Details</h1>
                 <h1 class="d-md-none text-light"><i class="fas fa-ban"></i> Write-Off Details</h1>
                 <p class="mb-0 text-light">Write-Off #: <strong>{{ $writeoff->id }}</strong></p>
             </div>
             <div>
                 <a href="{{ route('writeoffs.recoveries', $writeoff) }}" class="btn btn-light">
                     <i class="fas fa-list"></i> Recoveries
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
             <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loans Management</a></li>
             <li class="breadcrumb-item"><a href="{{ route('writeoffs.index') }}">Write-Offs</a></li>
             <li class="breadcrumb-item active" aria-current="page">Details</li>
         </ol>
     </nav>
     <a href="{{ route('writeoffs.index') }}" class="btn btn-light border">
         <i class="fas fa-arrow-left"></i> Back
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

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><strong>Loan Information</strong></div>
                <div class="card-body">
                    <div><strong>Loan ID:</strong> {{ $writeoff->loan?->id ?? '-' }}</div>
                    <div><strong>Borrower:</strong> {{ $borrowerName ?? '-' }}</div>
                    <div><strong>Loan Amount:</strong> {{ number_format((float) ($writeoff->loan?->principal_amount ?? 0), 2) }}</div>
                    <div><strong>Disbursement Date:</strong> {{ optional($writeoff->loan?->disbursement_date)->format('Y-m-d') }}</div>
                    <div><strong>Loan Status:</strong> {{ $writeoff->loan?->status ?? '-' }}</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>Write-Off Details</strong></div>
                <div class="card-body">
                    <div><strong>Write-Off Date:</strong> {{ optional($writeoff->writeoff_date)->format('Y-m-d') }}</div>
                    <div><strong>Reason:</strong> {{ $writeoff->reason ?? '-' }}</div>
                    <div><strong>Approved By:</strong> {{ $writeoff->approvedBy?->name ?? '-' }}</div>
                    <div><strong>Approved At:</strong> {{ optional($writeoff->approved_at)->format('Y-m-d H:i') }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><strong>Written-Off Amount Breakdown</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span>Principal</span><strong>{{ number_format((float) $writeoff->principal_written_off, 2) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Interest</span><strong>{{ number_format((float) $writeoff->interest_written_off, 2) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Penalties</span><strong>{{ number_format((float) $writeoff->penalties_written_off, 2) }}</strong></div>
                    <hr>
                    <div class="d-flex justify-content-between"><span>Total</span><strong>{{ number_format((float) $writeoff->total_written_off, 2) }}</strong></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>Recovery Summary</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span>Total Recovered</span><strong>{{ number_format((float) $totalRecovered, 2) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Remaining Written-Off Balance</span><strong>{{ number_format((float) $remainingBalance, 2) }}</strong></div>
                    <hr>
                    @if($canRecordRecovery && $writeoff->loan)
                        <a href="{{ route('loans.recovery.create', $writeoff->loan) }}" class="btn btn-success">
                            <i class="fas fa-plus"></i> Record Recovery
                        </a>
                    @else
                        <button class="btn btn-secondary" disabled>
                            <i class="fas fa-check"></i> Fully Recovered
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Recent Recoveries</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentRecoveries as $r)
                            <tr>
                                <td>{{ optional($r->recovery_date)->format('Y-m-d') }}</td>
                                <td>{{ number_format((float) $r->recovered_principal, 2) }}</td>
                                <td>{{ number_format((float) $r->recovered_interest, 2) }}</td>
                                <td><strong>{{ number_format((float) $r->total_recovered, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No recoveries recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
