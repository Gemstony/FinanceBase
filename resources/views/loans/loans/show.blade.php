@extends('adminlte::page')

@section('title', 'Loan - ' . $subshop->name)

@section('content_header')
 <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
     <div class="card-body">
         <div class="d-flex justify-content-between align-items-center">
             <div>
                 <h1 class="d-none d-md-block text-light"><i class="fas fa-file-invoice-dollar"></i> View Loan </h1>
                 <h1 class="d-md-none text-light"><i class="fas fa-file-invoice-dollar"></i> View Loan</h1>
                 <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
             </div>
             <a href="{{ route('categories.subshops') }}" class="btn btn-light">
                 <i class="fas fa-arrow-left"></i> Change Branch
             </a>
         </div>
     </div>
 </div>
 <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
             <li class="breadcrumb-item"><a href="{{ route('loans.loans.index') }}">Loans</a></li>
             <li class="breadcrumb-item active" aria-current="page">Loan</li>
         </ol>
     </nav>
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

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div class="mb-2">
                    <h4 class="mb-1">
                        @if($loan->borrower_type === 'group')
                            {{ $loan->loanGroup?->name }} Loan
                        @else
                            {{ $loan->customer?->name }} Loan
                        @endif
                    </h4>
                    <div class="text-muted">
                        {{ $loan->loanProduct?->name ?? 'Loan Product' }}
                        @if($loan->borrower_type)
                            &middot; {{ ucfirst($loan->borrower_type) }}
                        @endif
                    </div>
                </div>
                <div class="text-right">
                    <div class="mb-1">
                        @php
                            $statusBadgeClass = match ((string) $loan->status) {
                                'pending' => 'badge-warning',
                                'approved' => 'badge-success',
                                'rejected' => 'badge-danger',
                                'disbursed' => 'badge-primary',
                                'partially_paid' => 'badge-info',
                                'paid_off' => 'badge-success',
                                'defaulted' => 'badge-dark',
                                'written_off' => 'badge-secondary',
                                default => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $statusBadgeClass }}">{{ $loan->status }}</span>
                    </div>
                    <div class="text-muted">
                        Principal: <strong>{{ number_format((float)$loan->principal_amount, 2) }}</strong>
                    </div>
                    <div class="mt-2">
                        @if(!(bool) ($loan->is_written_off ?? false) && (string) $loan->status !== 'written_off')
                            <a href="{{ route('loan.restructures.create', $loan) }}" class="btn btn-sm btn-warning">
                                <i class="fas fa-random"></i> Restructure Loan
                            </a>
                            <a href="{{ route('loans.writeoff.create', $loan) }}" class="btn btn-sm btn-danger">
                                <i class="fas fa-ban"></i> Write Off
                            </a>
                        @else
                            <a href="{{ route('loans.recovery.create', $loan) }}" class="btn btn-sm btn-success">
                                <i class="fas fa-hand-holding-usd"></i> Record Recovery
                            </a>
                        @endif
                        <a href="{{ route('loan.restructures.history', $loan) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-history"></i> History
                        </a>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Interest Rate</div>
                    <div><strong>{{ number_format((float)$loan->interest_rate, 2) }}%</strong></div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Installments</div>
                    <div><strong>{{ (int)$loan->installments }}</strong></div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Disbursement</div>
                    <div><strong>{{ $loan->disbursement_date ? \Carbon\Carbon::parse($loan->disbursement_date)->format('Y-m-d') : '-' }}</strong></div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Maturity</div>
                    <div><strong>{{ $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('Y-m-d') : '-' }}</strong></div>
                </div>
            </div>
        </div>
    </div>

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
                                <th>Role</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Approved At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($approvals as $a)
                                <tr>
                                    <td>{{ $a->level_order }}</td>
                                    <td>{{ $a->loanProductApprovalLevel?->role?->name ?? $a->loanProductApprovalLevel?->role_id ?? '-' }}</td>
                                    @php
                                        $approvalBadgeClass = match ((string) $a->status) {
                                            'pending' => 'badge-warning',
                                            'approved' => 'badge-success',
                                            'rejected' => 'badge-danger',
                                            'skipped' => 'badge-secondary',
                                            default => 'badge-secondary',
                                        };
                                    @endphp
                                    <td><span class="badge {{ $approvalBadgeClass }}">{{ $a->status }}</span></td>
                                    <td>{{ $a->approver?->name ?? '-' }}</td>
                                    <td>{{ $a->approved_at ? \Carbon\Carbon::parse($a->approved_at)->format('Y-m-d H:i') : '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No approval levels.</td></tr>
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
            @php
                $versions = isset($installmentsByVersion) ? $installmentsByVersion->keys()->sortDesc()->values() : collect();
            @endphp

            @forelse($versions as $ver)
                @php
                    $rows = $installmentsByVersion->get($ver, collect());
                    $title = ((int) $ver === (int) $latestScheduleVersion) ? 'Current Schedule' : 'Previous Schedule (Restructured)';
                    $badgeClass = ((int) $ver === (int) $latestScheduleVersion) ? 'badge-success' : 'badge-secondary';
                @endphp

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>{{ $title }}</strong>
                        <span class="badge {{ $badgeClass }}">Version {{ (int) $ver }}</span>
                    </div>

                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Due Date</th>
                                <th>Principal Due</th>
                                <th>Interest Due</th>
                                <th>Fees Due</th>
                                <th>Penalty Due</th>
                                <th>Total Due</th>
                                <th>Principal Paid</th>
                                <th>Interest Paid</th>
                                <th>Fees Paid</th>
                                <th>Penalty Paid</th>
                                <th>Outstanding</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $i)
                                <tr>
                                    <td>{{ $i->installment_number }}</td>
                                    <td>{{ $i->due_date ? \Carbon\Carbon::parse($i->due_date)->format('Y-m-d') : '-' }}</td>
                                    <td>{{ number_format((float)$i->principal_due, 2) }}</td>
                                    <td>{{ number_format((float)$i->interest_due, 2) }}</td>
                                    <td>{{ number_format((float)$i->fees_due, 2) }}</td>
                                    <td>{{ number_format((float)$i->penalty_due, 2) }}</td>
                                    <td>{{ number_format((float)$i->total_due, 2) }}</td>
                                    <td>{{ number_format((float)$i->principal_paid, 2) }}</td>
                                    <td>{{ number_format((float)$i->interest_paid, 2) }}</td>
                                    <td>{{ number_format((float)$i->fees_paid, 2) }}</td>
                                    <td>{{ number_format((float)$i->penalty_paid, 2) }}</td>
                                    <td>{{ number_format((float)$i->total_outstanding, 2) }}</td>
                                    @php
                                        $installmentBadgeClass = match ((string) $i->status) {
                                            'paid' => 'badge-success',
                                            'partial' => 'badge-info',
                                            'pending' => 'badge-warning',
                                            'overdue' => 'badge-danger',
                                            'restructured' => 'badge-secondary',
                                            default => 'badge-secondary',
                                        };
                                    @endphp
                                    <td><span class="badge {{ $installmentBadgeClass }}">{{ $i->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="13" class="text-center text-muted">No installments found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="text-muted">No installments found.</div>
            @endforelse
        </div>
    </div>
</div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
