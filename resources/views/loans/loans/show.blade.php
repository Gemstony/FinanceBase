@extends('adminlte::page')

@section('title', 'Loan #' . $loan->id . ' - ' . $subshop->name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="mb-0">Loan #{{ $loan->id }}</h1>
            <div class="text-muted">Branch: <strong>{{ $subshop->name }}</strong></div>
        </div>
        <a href="{{ route('loans.loans.index') }}" class="btn btn-light border">
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
            <div class="card">
                <div class="card-header"><strong>Summary</strong></div>
                <div class="card-body">
                    <div><strong>Product:</strong> {{ $loan->loanProduct?->name }}</div>
                    <div><strong>Borrower Type:</strong> {{ $loan->borrower_type }}</div>
                    <div>
                        <strong>Borrower:</strong>
                        @if($loan->borrower_type === 'group')
                            {{ $loan->loanGroup?->name }}
                        @else
                            {{ $loan->customer?->name }}
                        @endif
                    </div>
                    <div><strong>Principal:</strong> {{ number_format((float)$loan->principal_amount, 2) }}</div>
                    <div><strong>Interest Rate (%):</strong> {{ number_format((float)$loan->interest_rate, 2) }}</div>
                    <div><strong>Installments:</strong> {{ (int)$loan->installments }}</div>
                    <div><strong>Status:</strong> {{ $loan->status }}</div>
                    <div><strong>Disbursement Date:</strong> {{ $loan->disbursement_date ? \Carbon\Carbon::parse($loan->disbursement_date)->format('Y-m-d') : '-' }}</div>
                    <div><strong>Maturity Date:</strong> {{ $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('Y-m-d') : '-' }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Approvals</strong></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Approved At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($approvals as $a)
                                <tr>
                                    <td>{{ $a->level_order }}</td>
                                    <td>{{ $a->status }}</td>
                                    <td>{{ $a->approver?->name ?? '-' }}</td>
                                    <td>{{ $a->approved_at ? \Carbon\Carbon::parse($a->approved_at)->format('Y-m-d H:i') : '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No approval levels.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Collaterals</strong></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Collateral</th>
                                <th>Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($collaterals as $c)
                                <tr>
                                    <td>{{ $c->customerCollateral?->description }}</td>
                                    <td>{{ number_format((float)$c->collateral_value, 2) }}</td>
                                    <td>{{ $c->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">No collaterals attached.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Guarantors</strong></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Joint Liability</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($guarantors as $g)
                                <tr>
                                    <td>{{ $g->guarantor?->name }}</td>
                                    <td>{{ $g->is_joint_liability ? 'Yes' : 'No' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">No guarantors attached.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Installment Schedule</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Due Date</th>
                        <th>Principal</th>
                        <th>Interest</th>
                        <th>Fees</th>
                        <th>Penalty</th>
                        <th>Total</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($installments as $i)
                        <tr>
                            <td>{{ $i->installment_number }}</td>
                            <td>{{ $i->due_date ? \Carbon\Carbon::parse($i->due_date)->format('Y-m-d') : '-' }}</td>
                            <td>{{ number_format((float)$i->principal_due, 2) }}</td>
                            <td>{{ number_format((float)$i->interest_due, 2) }}</td>
                            <td>{{ number_format((float)$i->fees_due, 2) }}</td>
                            <td>{{ number_format((float)$i->penalty_due, 2) }}</td>
                            <td>{{ number_format((float)$i->total_due, 2) }}</td>
                            <td>{{ number_format((float)$i->outstanding_amount, 2) }}</td>
                            <td>{{ $i->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">No installments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
