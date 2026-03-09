@extends('adminlte::page')

@section('title', 'Managed Restructured Loans - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-sync-alt"></i> Managed Restructured Loans</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-sync-alt"></i> Managed Restructures</h1>
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
        <li class="breadcrumb-item active" aria-current="page">Managed Restructures</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h3 class="card-title"><strong>Restructured Loans List</strong></h3>
            <div class="card-tools">
                <a href="{{ route('loan.restructures.index') }}" class="btn btn-sm btn-info">
                    <i class="fas fa-clock"></i> View Approval Queue
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>Loan Code</th>
                            <th>Customer</th>
                            <th>Old Term/Rate</th>
                            <th>New Term/Rate</th>
                            <th>Status</th>
                            <th>Execution Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($restructures as $r)
                            @php
                                $loan = $r->loan;
                            @endphp
                            <tr>
                                <td>
                                    @if($loan)
                                        <a href="{{ route('loans.loans.show', $loan) }}" class="font-weight-bold">
                                            {{ $loan->loan_code }}
                                        </a>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if($loan)
                                        @if($loan->borrower_type === 'group')
                                            <i class="fas fa-users text-muted mr-1"></i> {{ $loan->loanGroup?->name }}
                                        @else
                                            <i class="fas fa-user text-muted mr-1"></i> {{ $loan->customer?->name }}
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ (int) $r->old_term_months }} months / {{ number_format((float) $r->old_interest_rate, 2) }}%
                                    </small>
                                </td>
                                <td>
                                    <span class="text-primary font-weight-bold">
                                        {{ (int) $r->new_term }} months / {{ number_format((float) $r->new_interest_rate, 2) }}%
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = 'secondary';
                                        switch($r->status) {
                                            case 'pending': $badgeClass = 'warning'; break;
                                            case 'approved': $badgeClass = 'info'; break;
                                            case 'executed': $badgeClass = 'success'; break;
                                            case 'rejected': $badgeClass = 'danger'; break;
                                        }
                                    @endphp
                                    <span class="badge badge-{{ $badgeClass }} text-uppercase">{{ $r->status }}</span>
                                </td>
                                <td>
                                    {{ $r->executed_at ? \Carbon\Carbon::parse($r->executed_at)->format('Y-m-d') : '-' }}
                                </td>
                                <td class="text-right">
                                    <div class="btn-group">
                                        <a href="{{ route('loan.restructures.history', $loan) }}" class="btn btn-sm btn-outline-secondary" title="View History">
                                            <i class="fas fa-history"></i> History
                                        </a>
                                        @if($r->status === 'approved')
                                            <form action="{{ route('loan.restructures.execute', $r) }}" method="POST" class="d-inline js-execute-form">
                                                @csrf
                                                <button type="button" class="btn btn-sm btn-success js-execute-btn" title="Execute Restructure">
                                                    <i class="fas fa-play"></i> Execute
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-sm btn-outline-primary" title="View Loan">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle fa-2x mb-2"></i><br>
                                    No restructured loans found for this branch.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 d-flex justify-content-center">
                {{ $restructures->links() }}
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-execute-form').forEach(function (form) {
        var btn = form.querySelector('.js-execute-btn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            Swal.fire({
                title: 'Execute Restructure?',
                text: 'Are you sure you want to execute this restructure? This will update the loan schedule and terms.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, execute it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
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

