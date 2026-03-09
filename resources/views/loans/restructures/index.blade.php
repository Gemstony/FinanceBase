@extends('adminlte::page')

@section('title', 'Loan Restructure Approvals - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-random"></i> Loan Restructure Approvals</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-random"></i> Loan Restructure Approvals</h1>
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
        <li class="breadcrumb-item active" aria-current="page">Restructure Approvals</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-header bg-white">
            <strong>Pending Restructure Requests</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Loan</th>
                            <th>Borrower</th>
                            <th>New Term</th>
                            <th>New Rate</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $r)
                            @php
                                $loan = $r->loan;
                            @endphp
                            <tr>
                                <td>{{ (int) $r->id }}</td>
                                <td>
                                    @if($loan)
                                        <a href="{{ route('loans.loans.show', $loan) }}">{{ $loan->loan_code }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($loan)
                                        @if($loan->borrower_type === 'group')
                                            {{ $loan->loanGroup?->name }}
                                        @else
                                            {{ $loan->customer?->name }}
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ (int) ($r->new_term ?? $r->new_term_months ?? 0) }}</td>
                                <td>{{ number_format((float) ($r->new_interest_rate ?? 0), 2) }}%</td>
                                <td>{{ $r->created_at ? $r->created_at->format('Y-m-d H:i') : '-' }}</td>
                                <td><span class="badge badge-warning">{{ $r->status }}</span></td>
                                <td class="text-right">
                                    <form action="{{ route('loan.restructures.approve', $r) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>

                                    <form action="{{ route('loan.restructures.reject', $r) }}" method="POST" class="d-inline js-restructure-reject-form">
                                        @csrf
                                        <input type="hidden" name="reason" value="">
                                        <button type="button" class="btn btn-sm btn-danger js-restructure-reject-btn">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">No pending requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $requests->links() }}
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-restructure-reject-form').forEach(function (form) {
        var btn = form.querySelector('.js-restructure-reject-btn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            Swal.fire({
                title: 'Reject restructure request?',
                text: 'Please provide a reason for rejection.',
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Enter rejection reason...',
                inputAttributes: {
                    'aria-label': 'Rejection reason'
                },
                showCancelButton: true,
                confirmButtonText: 'Reject',
                confirmButtonColor: '#dc3545',
                cancelButtonText: 'Cancel',
                preConfirm: (value) => {
                    if (!value || !value.trim()) {
                        Swal.showValidationMessage('Rejection reason is required');
                        return false;
                    }
                    return value.trim();
                }
            }).then((result) => {
                if (!result.isConfirmed) return;
                form.querySelector('input[name="reason"]').value = result.value;
                form.submit();
            });
        });
    });
});
</script>
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
