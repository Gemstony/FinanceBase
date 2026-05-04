@extends('adminlte::page')

@section('title', $title)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exclamation-triangle"></i> {{ $title }}</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exclamation-triangle"></i> Delinquent</h1>
                <div class="small text-light-50">Managing overdue loan accounts</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.portfolio') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-chart-line"></i> Portfolio</a>
                <a href="{{ route('risk.collections') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-hand-holding-usd"></i> Collections</a>
            </div>
        </div>
    </div>
     
    <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
             <li class="breadcrumb-item active" aria-current="page">Delinquent</li>
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
        @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif
        
        <!-- Filter Panel -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter"></i> Filters</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-2">
                                <select name="risk_category" class="form-control">
                                    <option value="">All Risk Categories</option>
                                    <option value="par30" {{ request('risk_category') == 'par30' ? 'selected' : '' }}>PAR30</option>
                                    <option value="par60" {{ request('risk_category') == 'par60' ? 'selected' : '' }}>PAR60</option>
                                    <option value="par90" {{ request('risk_category') == 'par90' ? 'selected' : '' }}>PAR90</option>
                                    <option value="default" {{ request('risk_category') == 'default' ? 'selected' : '' }}>Default</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="borrower_type" class="form-control">
                                    <option value="">All Borrowers</option>
                                    <option value="individual" {{ request('borrower_type') == 'individual' ? 'selected' : '' }}>Individual</option>
                                    <option value="group" {{ request('borrower_type') == 'group' ? 'selected' : '' }}>Group</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="min_dpd" class="form-control" value="{{ request('min_dpd') }}" placeholder="Min Days Overdue">
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="max_dpd" class="form-control" value="{{ request('max_dpd') }}" placeholder="Max Days Overdue">
                            </div>
                            <div class="col-md-2">
                                <select name="officer" class="form-control">
                                    <option value="">All Officers</option>
                                    @foreach($officers ?? [] as $officer)
                                        <option value="{{ $officer->id }}" {{ request('officer') == $officer->id ? 'selected' : '' }}>{{ $officer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter"></i> Apply</button>
                                <a class="btn btn-light border" href="{{ route('risk.delinquent.index') }}"><i class="fas fa-undo"></i> Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title">Loans Overdue > {{ $days }} Days</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="delinquentLoansTable">
                        <thead>
                            <tr>
                                <th>Loan ID</th>
                                <th>Borrower</th>
                                <th>Loan Officer</th>
                                <th>Days Overdue</th>
                                <th>Outstanding Amount</th>
                                <th>Risk Category</th>
                                <th>Last Payment Date</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loans as $loan)
                                @php
                                    // Use pre-computed data from controller to avoid N+1 queries
                                    $data = $loanData[$loan->id] ?? [];
                                    $risk = $data['risk_category'] ?? 'current';
                                    $outstanding = $data['outstanding_balance'] ?? 0;
                                    $maxOverdue = $data['max_days_overdue'] ?? 0;
                                    $lastPayment = $loan->repayments()->latest('payment_date')->first();
                                @endphp
                                <tr>
                                    <td>{{ $loan->loan_code }}</td>
                                    <td>
                                        @if($loan->borrower_type === 'group')
                                            <i class="fas fa-users text-muted mr-1"></i> {{ $loan->loanGroup?->name }}
                                        @else
                                            <i class="fas fa-user text-muted mr-1"></i> {{ $loan->customer?->name }}
                                        @endif
                                    </td>
                                    <td>{{ $loan->latestDisbursement?->processor?->name ?? 'N/A' }}</td>
                                    <td>{{ $maxOverdue }}</td>
                                    <td>{{ number_format($outstanding, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $risk === 'default' ? 'bg-dark' : ($risk === 'par90' ? 'bg-danger' : ($risk === 'par60' ? 'bg-orange' : 'bg-warning')) }}">
                                            {{ strtoupper($risk) }}
                                        </span>
                                    </td>
                                    <td>{{ $lastPayment ? $lastPayment->payment_date->format('Y-m-d') : 'Never' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View Loan
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No delinquent loans found for this criteria.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($loans->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">Total Delinquent Outstanding:</th>
                                <th colspan="4">{{ number_format(array_sum(array_column($loanData, 'outstanding_balance')), 2) }}</th>
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
    if ($('#delinquentLoansTable').length) {
        $('#delinquentLoansTable').DataTable({
            responsive: true,
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            order: [[3, 'desc']],
            pageLength: 10,
            language: {
                searchPlaceholder: "Search loans...",
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

