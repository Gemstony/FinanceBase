@extends('adminlte::page')

@section('title', 'Disburse Loan - ' . $loan->loan_code)

@section('content_header')
 <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
     <div class="card-body">
         <div class="d-flex justify-content-between align-items-center">
             <div>
                 <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Disburse Loan</h1>
                 <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Disburse Loan</h1>
                 <p class="mb-0 text-light">Loan Code: <strong>{{ $loan->loan_code }}</strong></p>
             </div>
             <a href="{{ route('loans.disbursement.index') }}" class="btn btn-light border">
                 <i class="fas fa-arrow-left"></i> Back
             </a>
         </div>
     </div>
 </div>
 <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
             <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
             <li class="breadcrumb-item"><a href="{{ route('loans.disbursement.index') }}">Disbursement</a></li>
             <li class="breadcrumb-item active" aria-current="page">{{ $loan->loan_code }}</li>
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
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
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
                            {{ $loan->loanGroup?->name ?? 'Group' }} Loan
                        @else
                            {{ $loan->customer?->name ?? 'Customer' }} Loan
                        @endif
                    </h4>
                    <div class="text-muted">
                        {{ $loan->loanProduct?->name ?? 'Loan Product' }}
                        @if($loan->borrower_type)
                            &middot; {{ ucfirst($loan->borrower_type) }}
                        @endif
                    </div>                                         
                    <div class="text-muted">
                        Phone: {{ $loan->customer?->phone ?? '-' }} <br>
                        Email: {{ $loan->customer?->email ?? '-' }} <br>
                        Code: {{ $loan->customer?->customer_code ?? '-' }}
                    </div>
                    <div class="text-muted">
                        Peoduct: {{ $loan->loanProduct?->name ?? 'Loan Product' }}
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
                        Principal: <strong>{{ number_format((float)$loan->principal_amount, 2) }}</strong>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Interest Rate</div>
                    <div><strong>{{ number_format((float)$loan->interest_rate, 2) }}%</strong></div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">Approval Date</div>
                    <div><strong>{{ $approvalDate ? $approvalDate->format('Y-m-d') : '-' }}</strong></div>
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
                <div class="card-header"><strong>Loan Summary</strong></div>
                <div class="card-body">
                    <table class="table table-sm table-striped mb-0">
                        <tr>
                            <th>Loan Code</th>
                            <td>{{ $loan->loan_code }}</td>
                        </tr>
                        <tr>
                            <th>Borrower/Group</th>
                            <td>
                                @if($loan->customer)
                                    {{ $loan->customer->name }}
                                @elseif($loan->loanGroup)
                                    <i class="fa fa-users"></i> {{ $loan->loanGroup->name }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Loan Product</th>
                            <td>{{ $loan->loanProduct->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Principal Amount</th>
                            <td>{{ number_format($loan->principal_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Interest Method</th>
                            <td>{{ $loan->loanProduct?->interestMethod?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Interest Rate</th>
                            <td>{{ $loan->interest_rate }}%</td>
                        </tr>
                        <tr>
                            <th>Approval Date</th>
                            <td>{{ $approvalDate ? $approvalDate->format('M d, Y') : '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Readiness Checks</strong></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <tr>
                            <th>Collateral</th>
                            <td><span class="badge {{ $collateralStatus['class'] }}">{{ $collateralStatus['status'] }}</span></td>
                        </tr>
                        <tr>
                            <th>Guarantors</th>
                            <td><span class="badge {{ $guarantorStatus['class'] }}">{{ $guarantorStatus['status'] }}</span></td>
                        </tr>
                        <tr>
                            <th>Fees</th>
                            <td><span class="badge {{ $feesStatus['class'] }}">{{ $feesStatus['status'] }}</span></td>
                        </tr>
                        <tr>
                            <th>Security Deposit</th>
                            <td><span class="badge {{ $securityDepositStatus['class'] }}">{{ $securityDepositStatus['status'] }}</span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header"><strong>Collateral</strong></div>
                <div class="card-body table-responsive">
                    @if($loan->collaterals->isNotEmpty())
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Value</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loan->collaterals as $collateral)
                                    <tr>
                                        <td>{{ $collateral->customerCollateral?->collateralType?->name ?? '—' }}</td>
                                        <td>{{ $collateral->customerCollateral?->description ?? '—' }}</td>
                                        <td>{{ number_format($collateral->collateral_value, 2) }}</td>
                                        <td>{{ $collateral->status }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center text-muted">No collateral recorded.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header"><strong>Guarantors</strong></div>
                <div class="card-body table-responsive">
                    @if($loan->guarantors->isNotEmpty())
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Relationship</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loan->guarantors as $guarantor)
                                    <tr>
                                        <td>{{ $guarantor->guarantor?->name ?? '—' }}</td>
                                        <td>{{ $guarantor->guarantor?->phone ?? '—' }}</td>
                                        <td>{{ $guarantor->is_joint_liability ? 'Joint Liability' : 'Individual' }}</td>
                                        <td>—</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center text-muted">No guarantors recorded.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header"><strong>Installments</strong></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped mb-0">
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
                            @foreach($installments as $installment)
                                <tr>
                                    <td>{{ $installment->installment_number }}</td>
                                    <td>{{ $installment->due_date?->format('M d, Y') }}</td>
                                    <td>{{ number_format($installment->principal_due, 2) }}</td>
                                    <td>{{ number_format($installment->interest_due, 2) }}</td>
                                    <td>{{ number_format($installment->total_due, 2) }}</td>
                                    <td>
                                        @php
                                            $statusClass = match((string) $installment->status) {
                                                'pending' => 'badge-warning',
                                                'paid' => 'badge-success',
                                                'partial' => 'badge-info',
                                                'overdue' => 'badge-danger',
                                                default => 'badge-secondary'
                                            };
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ $installment->status }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card border-success">
                <div class="card-header bg-success" style="color: white;"><strong>Confirm Disbursement</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('loans.disbursement.disburse', $loan) }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="disbursement_date">Disbursement Date</label>
                                    <input type="date" name="disbursement_date" id="disbursement_date" class="form-control" required value="{{ now()->format('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="reference_number">Reference Number</label>
                                    <input type="text" name="reference_number" id="reference_number" class="form-control" placeholder="Optional">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="disbursement_method_id">Disbursement Method</label>
                                    <select name="disbursement_method_id" id="disbursement_method_id" class="form-control" required>
                                        <option value="">Select Method</option>
                                        @foreach(($disbursementMethods ?? collect()) as $method)
                                            <option value="{{ $method->id }}" @selected(old('disbursement_method_id') == $method->id)>
                                                {{ $method->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <input type="text" name="notes" id="notes" class="form-control" placeholder="Optional">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bank_account_id">Bank Account</label>
                                    <select name="bank_account_id" id="bank_account_id" class="form-control" required>
                                        <option value="">Select Bank Account</option>
                                        @foreach(($bankAccounts ?? collect()) as $account)
                                            <option value="{{ $account->id }}" @selected(old('bank_account_id') == $account->id)>
                                                {{ $account->account_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                           
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Confirm Disbursement
                            </button>
                            <a href="{{ route('loans.disbursement.index') }}" class="btn btn-light border">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const disbursementForm = document.querySelector('form[action*="disburse"]');
    if (disbursementForm) {
        disbursementForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const disbursementDate = document.querySelector('input[name="disbursement_date"]').value;
            const disbursementMethod = document.querySelector('select[name="disbursement_method_id"]');
            const disbursementMethodName = disbursementMethod.options[disbursementMethod.selectedIndex]?.text || 'N/A';
            const bankAccount = document.querySelector('select[name="bank_account_id"]');
            const bankAccountName = bankAccount.options[bankAccount.selectedIndex]?.text || 'N/A';
            
            Swal.fire({
                title: 'Confirm Disbursement',
                html: `
                    <div class="text-left">
                        <p><strong>Disbursement Date:</strong> ${disbursementDate}</p>
                        <p><strong>Disbursement Method:</strong> ${disbursementMethodName}</p>
                        <p><strong>Bank Account:</strong> ${bankAccountName}</p>
                    </div>
                    <p class="mt-3">Are you sure you want to confirm this disbursement?</p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Confirm Disbursement',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Processing Disbursement...',
                        text: 'Please wait while we process the disbursement.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    disbursementForm.submit();
                }
            });
        });
    }
});
</script>
@endpush
