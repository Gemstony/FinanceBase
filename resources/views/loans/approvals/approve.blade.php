@extends('adminlte::page')

@section('title', 'Loan Approval - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-user-check"></i> Loan Approval</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-user-check"></i> Loan Approval</h1>
                <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('loans.approvals.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to Approvals
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
        <li class="breadcrumb-item"><a href="{{ route('loans.approvals.index') }}">Loan Approvals</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $loan->loan_code }}</li>
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

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
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
                                        
                    @if($loan->borrower_type === 'group')
                        <div class="text-muted mb-2">
                            <strong>Group Members:</strong><br>
                            @forelse($loan->loanGroup?->members()->with('customer')->where('is_active', true)->get() ?? [] as $member)
                                {{ $member->customer?->name ?? '-' }} ({{ ucfirst($member->role) }})<br>
                                <small class="ml-3">Phone: {{ $member->customer?->phone ?? '-' }}</small><br>
                            @empty
                                <span class="text-warning">No active members</span>
                            @endforelse
                        </div>
                    @else
                        <div class="text-muted">
                            Phone: {{ $loan->customer?->phone ?? '-' }} <br>
                            Email: {{ $loan->customer?->email ?? '-' }} <br>
                            Code: {{ $loan->customer?->customer_code ?? '-' }}
                        </div>
                    @endif
                    <div class="text-muted">
                        Product: {{ $loan->loanProduct?->name ?? 'Loan Product' }}
                        @if($loan->borrower_type)
                            &middot; {{ ucfirst($loan->borrower_type) }}
                        @endif
                        <br> Loan Code: <strong>{{ $loan->loan_code }}</strong>
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
                        Principal: <strong>{{ number_format((float) $loan->principal_amount, 2) }}</strong>
                    </div>
                    <div class="mt-2">
                        <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-eye"></i> View Loan
                        </a>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Interest Rate</div>
                    <div><strong>{{ number_format((float) $loan->interest_rate, 2) }}%</strong></div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Installments</div>
                    <div><strong>{{ (int) $loan->installments }}</strong></div>
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
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white">
                    <strong>Loan Details</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2"><strong>Loan Code:</strong> {{ $loan->loan_code }}</div>
                            <div class="mb-2"><strong>Product:</strong> {{ $loan->loanProduct?->name }}</div>
                            @php
                                $loanStatusBadgeClass = match ((string) $loan->status) {
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
                            <div class="mb-2"><strong>Status:</strong> <span class="badge {{ $loanStatusBadgeClass }}">{{ $loan->status }}</span></div>
                            <div class="mb-2"><strong>Borrower Type:</strong> {{ ucfirst($loan->borrower_type) }}</div>
                            <div class="mb-2">
                                <strong>Borrower:</strong>
                                @if($loan->borrower_type === 'group')
                                    {{ $loan->loanGroup?->name }}
                                @else
                                    {{ $loan->customer?->name }}
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2"><strong>Principal:</strong> {{ number_format((float) $loan->principal_amount, 2) }}</div>
                            <div class="mb-2"><strong>Interest Rate:</strong> {{ number_format((float) $loan->interest_rate, 2) }}%</div>
                            <div class="mb-2"><strong>Installments:</strong> {{ (int) $loan->installments }}</div>
                            <div class="mb-2"><strong>Disbursement Date:</strong> {{ $loan->disbursement_date ? \Carbon\Carbon::parse($loan->disbursement_date)->format('Y-m-d') : '-' }}</div>
                            <div class="mb-2"><strong>Maturity Date:</strong> {{ $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('Y-m-d') : '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Approval Levels</strong>
                    @if($nextPending)
                        <span class="badge badge-info">Next Level: {{ (int) $nextPending->level_order }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Approved By</th>
                                    <th>Approved At</th>
                                    <th>Comments</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($approvals as $a)
                                    <tr @if($nextPending && (int) $a->id === (int) $nextPending->id) style="background: #f0f7ff;" @endif>
                                        <td>{{ (int) $a->level_order }}</td>
                                        <td>{{ $a->loanProductApprovalLevel?->role?->name ?? $a->loanProductApprovalLevel?->role_id }}</td>
                                        <td>
                                            @if($a->status === 'approved')
                                                <span class="badge badge-success">approved</span>
                                            @elseif($a->status === 'rejected')
                                                <span class="badge badge-danger">rejected</span>
                                            @elseif($a->status === 'pending')
                                                <span class="badge badge-warning">pending</span>
                                            @else
                                                <span class="badge badge-secondary">{{ $a->status }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $a->approver?->name ?? '-' }}</td>
                                        <td>{{ $a->approved_at ? \Carbon\Carbon::parse($a->approved_at)->format('Y-m-d H:i') : '-' }}</td>
                                        <td>{{ $a->comments ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($nextPending)
                        <div class="mt-3">
                            @if($canAct)
                                <div class="alert alert-info mb-3">
                                    You can approve/reject this loan at level <strong>{{ (int) $nextPending->level_order }}</strong>.
                                </div>

                                <form method="post" action="{{ route('loans.approvals.approve', $loan->loan_code) }}" class="mb-2 js-loan-approve-form">
                                    @csrf
                                    <div class="form-group">
                                        <label class="small mb-1">Comment (optional)</label>
                                        <textarea name="comments" class="form-control" rows="3" placeholder="Add an approval note (optional)">{{ old('comments') }}</textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>

                                <form method="post" action="{{ route('loans.approvals.reject', $loan->loan_code) }}" class="js-loan-reject-form">
                                    @csrf
                                    <div class="form-group">
                                        <label class="small mb-1">Rejection comment (required)</label>
                                        <textarea name="comments" class="form-control" rows="3" placeholder="Provide a reason for rejection" required>{{ old('comments') }}</textarea>
                                    </div>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            @else
                                <div class="alert alert-warning mb-0">
                                    This loan is pending at level <strong>{{ (int) $nextPending->level_order }}</strong>, but your role is not authorized to act on the current pending level.
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-success mb-0">
                            No pending approval levels found.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white">
                    <strong>Installments</strong>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Due Date</th>
                                    <th>Principal Due</th>
                                    <th>Interest Due</th>
                                    <th>Total Due</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($installments as $ins)
                                    <tr>
                                        <td>{{ (int) $ins->installment_number }}</td>
                                        <td>{{ $ins->due_date ? \Carbon\Carbon::parse($ins->due_date)->format('Y-m-d') : '-' }}</td>
                                        <td>{{ number_format((float) $ins->principal_due, 2) }}</td>
                                        <td>{{ number_format((float) $ins->interest_due, 2) }}</td>
                                        <td>{{ number_format((float) $ins->total_due, 2) }}</td>
                                        <td><span class="badge badge-secondary">{{ $ins->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No installments found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white">
                    <strong>Collaterals</strong>
                </div>
                <div class="card-body">
                    @forelse($collaterals as $c)
                        <div class="border rounded p-2 mb-2">
                            <div class="small text-muted">Collateral</div>
                            <div><strong>{{ $c->customerCollateral?->collateral_type ?? 'Collateral' }}</strong></div>
                            <div class="small text-muted">Estimated Value: {{ number_format((float) $c->collateral_value, 2) }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No collaterals recorded.</div>
                    @endforelse
                </div>
            </div>

            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white">
                    <strong>Guarantors</strong>
                </div>
                <div class="card-body">
                    @forelse($guarantors as $g)
                        <div class="border rounded p-2 mb-2">
                            <div><strong>{{ $g->guarantor?->name }}</strong></div>
                            <div class="small text-muted">Joint Liability: {{ $g->is_joint_liability ? 'Yes' : 'No' }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No guarantors recorded.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-loan-approve-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Approve this loan?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Approve',
                confirmButtonColor: '#28a745',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) return;
                form.submit();
            });
        });
    });

    document.querySelectorAll('.js-loan-reject-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var textarea = form.querySelector('textarea[name="comments"]');
            var reason = textarea ? textarea.value.trim() : '';

            if (!reason) {
                Swal.fire({
                    title: 'Rejection reason required',
                    text: 'Please enter a reason before rejecting.',
                    icon: 'warning'
                });
                if (textarea) textarea.focus();
                return;
            }

            Swal.fire({
                title: 'Reject this loan?',
                text: 'This will mark the loan as rejected at the current approval level.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Reject',
                confirmButtonColor: '#dc3545',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) return;
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
