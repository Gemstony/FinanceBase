@extends('adminlte::page')

@section('title', 'Rejected Loans - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-times-circle"></i> Rejected Loans</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-times-circle"></i> Rejected</h1>
                <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('loans.management') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loans Management</a></li>
        <li class="breadcrumb-item active" aria-current="page">Rejected Loans</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title"><strong>List of Rejected Loans</strong></h3>
                <div class="card-tools">
                    <form action="{{ route('loans.rejected.index') }}" method="GET" class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" name="q" class="form-control float-right" placeholder="Search code or name..." value="{{ request('q') }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="rejectedLoansTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Loan Code</th>
                            <th>Customer/Group</th>
                            <th>Product</th>
                            <th>Principal</th>
                            <th>Requested At</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $counter = 1;
                        @endphp
                        @forelse($loans as $loan)
                            <tr>
                                <td>{{ $counter++ }}</td>
                                <td>
                                    <a href="{{ route('loans.loans.show', $loan) }}" class="font-weight-bold text-danger">
                                        {{ $loan->loan_code }}
                                    </a>
                                </td>
                                <td>
                                    @if($loan->borrower_type === 'group')
                                        <i class="fas fa-users text-muted mr-1"></i> {{ $loan->loanGroup?->name }}
                                    @else
                                        <i class="fas fa-user text-muted mr-1"></i> {{ $loan->customer?->name }}
                                    @endif
                                </td>
                                <td>{{ $loan->loanProduct?->name }}</td>
                                <td>{{ number_format((float) $loan->principal_amount, 2) }}</td>
                                <td>{{ $loan->created_at ? $loan->created_at->format('Y-m-d') : '-' }}</td>
                                <td>
                                    <span class="badge badge-danger text-uppercase">Rejected</span>
                                </td>
                                <td class="text-right">
                                    <div class="btn-group">
                                        <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="{{ route('loan.restructures.history', $loan) }}" class="btn btn-sm btn-outline-secondary" title="View History">
                                            <i class="fas fa-history"></i> History
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle fa-2x mb-2"></i><br>
                                    No rejected loans found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($loans, 'links'))
                <div class="mt-4 d-flex justify-content-center">
                    {{ $loans->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    if ($('#rejectedLoansTable').length) {
        $('#rejectedLoansTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [6] },
                { searchable: false, targets: [6] }
            ],
            order: [[1, 'desc']]
        });
    }
});
</script>
@endsection

@section('css')
<style>
    .table td, .table th {
        vertical-align: middle;
    }
    .badge {
        padding: 0.5em 0.75em;
    }
</style>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
