@extends('adminlte::page')

@section('title', 'Collections Worklist')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Collections Worklist</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Collections</h1>
                <div class="small text-light-50">Prioritizing recoveries and repayment follow-ups</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.portfolio') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-chart-line"></i> Portfolio</a>
                <a href="{{ route('risk.delinquent.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-exclamation-triangle"></i> Delinquent</a>
            </div>
        </div>
    </div>

    
    <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
             <li class="breadcrumb-item active" aria-current="page">Collections</li>
         </ol>
     </nav>

 </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title">Prioritized Collections Worklist</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="collectionsTable">
                        <thead>
                            <tr>
                                <th>Loan ID</th>
                                <th>Borrower</th>
                                <th>Phone</th>
                                <th>Days Overdue</th>
                                <th>Outstanding Balance</th>
                                <th>Risk Category</th>
                                <th>Loan Officer</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loans as $loan)
                                <tr @if($loan->max_days_overdue >= 90) style="background-color: #fff5f5;" @endif>
                                    <td>{{ $loan->loan_code }}</td>
                                    <td>
                                        @if($loan->borrower_type === 'group')
                                            <i class="fas fa-users text-muted mr-1"></i> {{ $loan->loanGroup?->name }}
                                        @else
                                            <i class="fas fa-user text-muted mr-1"></i> {{ $loan->customer?->name }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($loan->borrower_type === 'group')
                                            {{ $loan->loanGroup?->phone ?? 'N/A' }}
                                        @else
                                            {{ $loan->customer?->phone ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-bold {{ $loan->max_days_overdue >= 30 ? 'text-danger' : 'text-warning' }}">
                                            {{ $loan->max_days_overdue }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($loan->outstanding_balance, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $loan->risk_category === 'default' ? 'bg-dark' : ($loan->risk_category === 'par90' ? 'bg-danger' : ($loan->risk_category === 'par60' ? 'bg-orange' : 'bg-warning')) }}">
                                            {{ strtoupper($loan->risk_category) }}
                                        </span>
                                    </td>
                                    <td>{{ $loan->latestDisbursement?->processor?->name ?? 'Unassigned' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View Loan
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No collections needed at this time.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($loans->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">Total Collections Required:</th>
                                <th colspan="4">{{ number_format($loans->sum('outstanding_balance'), 2) }}</th>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(document).ready(function() {
    if ($('#collectionsTable').length) {
        $('#collectionsTable').DataTable({
            responsive: true,
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            order: [[3, 'desc']],
            pageLength: 10,
            language: {
                searchPlaceholder: "Search collections...",
                search: ""
            }
        });
    }
});
</script>
@endpush
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
