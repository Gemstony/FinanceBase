@extends('adminlte::page')

@section('title', 'Loan Approvals - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-user-check"></i> Loan Approvals</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-user-check"></i> Loan Approvals</h1>
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
        <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>

        <li class="breadcrumb-item active" aria-current="page">Loan Approvals</li>
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
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="approvalsTable">
                    <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                        <tr>
                            <th>Loan Code</th>
                            <th>Product</th>
                            <th>Borrower</th>
                            <th>Principal</th>
                            <th>Status</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loans as $loan)
                            <tr>
                                <td>{{ $loan->loan_code }}</td>
                                <td>{{ $loan->loanProduct?->name }}</td>
                                <td>
                                    @if($loan->borrower_type === 'group')
                                        Group: {{ $loan->loanGroup?->name }}
                                    @else
                                        {{ $loan->customer?->name }}
                                    @endif
                                </td>
                                <td>{{ number_format((float) $loan->principal_amount, 2) }}</td>
                                <td><span class="badge badge-warning">{{ $loan->status }}</span></td>
                                <td class="text-right">
                                    <a href="{{ route('loans.approvals.show', $loan->loan_code) }}" class="btn btn-sm btn-outline-primary">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No loans pending your approval.</td>
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

@push('js')
<script>
$(document).ready(function() {
    if ($('#approvalsTable').length) {
        $('#approvalsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [5] },
                { searchable: false, targets: [5] }
            ],
            order: [[0, 'desc']]
        });
    }
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
