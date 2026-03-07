@extends('adminlte::page')

@section('title', 'Loans - ' . $subshop->name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="mb-0">Loans</h1>
            <div class="text-muted">Branch: <strong>{{ $subshop->name }}</strong></div>
        </div>
        <a href="{{ route('loans.loans.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Loan
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

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Borrower</th>
                        <th>Principal</th>
                        <th>Status</th>
                        <th>Disbursement</th>
                        <th>Maturity</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                        <tr>
                            <td>{{ $loan->id }}</td>
                            <td>{{ $loan->loanProduct?->name }}</td>
                            <td>
                                @if($loan->borrower_type === 'group')
                                    Group: {{ $loan->loanGroup?->name }}
                                @else
                                    {{ $loan->customer?->name }}
                                @endif
                            </td>
                            <td>{{ number_format((float)$loan->principal_amount, 2) }}</td>
                            <td><span class="badge badge-secondary">{{ $loan->status }}</span></td>
                            <td>{{ $loan->disbursement_date ? \Carbon\Carbon::parse($loan->disbursement_date)->format('Y-m-d') : '-' }}</td>
                            <td>{{ $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('Y-m-d') : '-' }}</td>
                            <td class="text-right">
                                <a href="{{ route('loans.loans.show', $loan->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No loans found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($loans, 'links'))
            <div class="card-footer">
                {{ $loans->links() }}
            </div>
        @endif
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

