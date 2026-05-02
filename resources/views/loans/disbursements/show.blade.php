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

    {{-- Security Deposit and Applied Fees Cards --}}
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Security Deposit</strong></div>
                <div class="card-body">
                    <div class="mb-1"><strong>Required:</strong> {{ number_format((float) ($securityDepositRequired ?? 0), 2) }}</div>
                    <div class="mb-1"><strong>Paid:</strong> {{ number_format((float) ($securityDepositPaid ?? 0), 2) }}</div>
                    <div class="mb-2">
                        <strong>Status:</strong>
                        @php
                            $sdStatus = $securityDepositStatus['status'] ?? 'Not Required';
                            $sdCls = $securityDepositStatus['class'] ?? 'badge-secondary';
                        @endphp
                        <span class="badge {{ $sdCls }}">{{ $sdStatus }}</span>
                    </div>

                    @if((bool) ($loan->requires_security_deposit ?? false) && $loan->getBorrowerId())
                        @if(!$isHeld)
                            <form method="POST" action="{{ route('security-deposits.collect', $loan) }}" class="border rounded p-2 bg-light mb-3" id="collectDepositForm">
                                @csrf
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label class="small mb-1">Amount</label>
                                        <input type="number" name="amount" step="0.01" min="0" class="form-control" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="small mb-1">Payment Method</label>
                                        <select name="payment_method" class="form-control" id="security_deposit_payment_method" required>
                                            @foreach($globalPaymentMethods->where('is_deposit_method', true) as $method)
                                                <option value="{{ $method->code }}" data-requires-bank="{{ $method->requires_bank_account ? 'true' : 'false' }}">
                                                    {{ $method->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($globalPaymentMethods->where('is_deposit_method', true)->isEmpty())
                                            <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> No active payment methods configured.</small>
                                        @endif
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="small mb-1">Payment Bank Account</label>
                                        <select name="payment_bank_account_id" class="form-control" data-security-deposit-bank-select required>
                                            <option value="">-- Select Bank Account --</option>
                                            @foreach(($bankAccounts ?? collect()) as $ba)
                                                <option value="{{ $ba->id }}">
                                                    {{ $ba->account_name }}{{ !empty($ba->account_number) ? ' - ' . $ba->account_number : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Required for Bank Transfer / Mobile Money.</small>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-12 mb-2">
                                        <label class="small mb-1">Notes</label>
                                        <input type="text" name="notes" class="form-control" placeholder="Optional">
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-primary" type="button" id="collectDepositBtn">
                                    <i class="fas fa-plus"></i> Collect Deposit
                                </button>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('security-deposits.loan', $loan) }}">View Deposits</a>
                            </form>
                        @else
                            <div class="alert alert-success mb-3">
                                <i class="fas fa-check-circle"></i> Security deposit has been fully collected.
                            </div>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('security-deposits.loan', $loan) }}">View Deposits</a>
                        @endif
                    @else
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('security-deposits.loan', $loan) }}">View Deposits</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <strong><i class="fas fa-file-invoice-dollar"></i> Applied Fees</strong>
                    @php
                        $totalFees = $loanFees->sum('amount');
                        $paidFees = $loanFees->where('is_paid', true)->sum('amount');
                        $pendingFees = $totalFees - $paidFees;
                        $allFeesPaid = $pendingFees <= 0 && $totalFees > 0;
                    @endphp
                    @if($totalFees > 0)
                        <span class="badge badge-info float-right">{{ number_format((float)$totalFees, 2) }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($totalFees > 0)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Total Fees:</span>
                                <strong>{{ number_format((float)$totalFees, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Paid:</span>
                                <span class="text-success">{{ number_format((float)$paidFees, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Pending:</span>
                                <span class="text-{{ $pendingFees > 0 ? 'warning' : 'success' }}">{{ number_format((float)$pendingFees, 2) }}</span>
                            </div>
                        </div>
                        <hr>
                    @endif

                    {{-- Fee Payment Form --}}
                    @if($pendingFees > 0)
                        <form method="POST" action="{{ route('loans.fees.pay-all', $loan) }}" class="border rounded p-2 bg-light mb-3" id="payAllFeesForm">
                            @csrf
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="small mb-1">Payment Method</label>
                                    <select name="payment_method" class="form-control" id="fee_payment_method" required>
                                        @foreach($globalPaymentMethods->where('is_deposit_method', true) as $method)
                                            <option value="{{ $method->code }}" data-requires-bank="{{ $method->requires_bank_account ? 'true' : 'false' }}">
                                                {{ $method->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if($globalPaymentMethods->where('is_deposit_method', true)->isEmpty())
                                        <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> No active payment methods configured.</small>
                                    @endif
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="small mb-1">Bank Account</label>
                                    <select name="payment_bank_account_id" class="form-control" data-fee-bank-select>
                                        <option value="">-- Select Bank Account --</option>
                                        @foreach(($bankAccounts ?? collect()) as $ba)
                                            <option value="{{ $ba->id }}">
                                                {{ $ba->account_name }}{{ !empty($ba->account_number) ? ' - ' . $ba->account_number : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Required for Bank Transfer / Mobile Money.</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12 mb-2">
                                    <label class="small mb-1">Notes</label>
                                    <input type="text" name="notes" class="form-control" placeholder="Optional payment reference">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <button class="btn btn-sm btn-primary" type="button" id="payAllFeesBtn">
                                    <i class="fas fa-money-bill-wave"></i> Pay All Fees ({{ number_format((float)$pendingFees, 2) }})
                                </button>
                                <a class="btn btn-sm btn-outline-info" href="{{ route('loans.fees.payment-form', $loan) }}">
                                    <i class="fas fa-list"></i> Pay Individual
                                </a>
                            </div>
                        </form>
                    @elseif($allFeesPaid)
                        <div class="alert alert-success mb-3">
                            <i class="fas fa-check-circle"></i> All fees have been paid.
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Fee Name</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($loanFees as $lf)
                                    <tr>
                                        <td>{{ $lf->loanProductFee?->loanFee?->name ?? 'Fee' }}</td>
                                        <td>{{ number_format((float)$lf->amount, 2) }}</td>
                                        <td>
                                            @if($lf->is_paid)
                                                <span class="badge badge-success">Paid</span>
                                            @else
                                                <span class="badge badge-warning">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">No fees applied.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
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

                
        <div class="col-md-6">
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-calendar-alt"></i> Installments</strong>
                    <a href="{{ route('loans.loans.schedule.export', $loan) }}" class="btn btn-sm btn-info">
                        <i class="fas fa-file-pdf"></i> Export Schedule
                    </a>
                </div>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Security Deposit Form
    const methodSelect = document.getElementById('security_deposit_payment_method');
    const bankSelect = document.querySelector('[data-security-deposit-bank-select]');
    
    if (methodSelect && bankSelect) {
        function syncBankRequired() {
            const selectedOption = methodSelect.options[methodSelect.selectedIndex];
            const needsBank = selectedOption && selectedOption.getAttribute('data-requires-bank') === 'true';
            bankSelect.required = needsBank;
            if (needsBank && !bankSelect.value) {
                bankSelect.classList.add('is-invalid');
            } else {
                bankSelect.classList.remove('is-invalid');
            }
        }

        methodSelect.addEventListener('change', syncBankRequired);
        syncBankRequired();

        const collectDepositForm = document.querySelector('#collectDepositForm');
        const collectDepositBtn = document.querySelector('#collectDepositBtn');
        if (collectDepositBtn && collectDepositForm) {
            collectDepositBtn.addEventListener('click', function (e) {
                const selectedOption = methodSelect.options[methodSelect.selectedIndex];
                const needsBank = selectedOption && selectedOption.getAttribute('data-requires-bank') === 'true';
                if (needsBank && !bankSelect.value) {
                    bankSelect.classList.add('is-invalid');
                    bankSelect.focus();
                    return;
                }

                const amount = collectDepositForm.querySelector('input[name="amount"]').value;
                const paymentMethod = collectDepositForm.querySelector('select[name="payment_method"]').value;
                
                Swal.fire({
                    title: 'Confirm Deposit Collection',
                    html: `
                        <div class="text-left">
                            <p><strong>Amount:</strong> ${parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                            <p><strong>Payment Method:</strong> ${paymentMethod.replace('_', ' ').toUpperCase()}</p>
                        </div>
                        <p class="mt-3">Are you sure you want to collect this security deposit?</p>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Collect Deposit',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Processing Deposit...',
                            text: 'Please wait while we process the deposit.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        collectDepositForm.submit();
                    }
                });
            });
        }
    }

    // Fee Payment Form
    const feeMethodSelect = document.getElementById('fee_payment_method');
    const feeBankSelect = document.querySelector('[data-fee-bank-select]');
    if (feeMethodSelect && feeBankSelect) {
        function syncFeeBankRequired() {
            const selectedOption = feeMethodSelect.options[feeMethodSelect.selectedIndex];
            const needsBank = selectedOption && selectedOption.getAttribute('data-requires-bank') === 'true';
            feeBankSelect.required = needsBank;
            if (needsBank && !feeBankSelect.value) {
                feeBankSelect.classList.add('is-invalid');
            } else {
                feeBankSelect.classList.remove('is-invalid');
            }
        }

        feeMethodSelect.addEventListener('change', syncFeeBankRequired);
        syncFeeBankRequired();

        const payAllFeesForm = document.querySelector('#payAllFeesForm');
        const payAllFeesBtn = document.querySelector('#payAllFeesBtn');
        if (payAllFeesBtn && payAllFeesForm) {
            payAllFeesBtn.addEventListener('click', function (e) {
                const selectedOption = feeMethodSelect.options[feeMethodSelect.selectedIndex];
                const needsBank = selectedOption && selectedOption.getAttribute('data-requires-bank') === 'true';
                if (needsBank && !feeBankSelect.value) {
                    feeBankSelect.classList.add('is-invalid');
                    feeBankSelect.focus();
                    return;
                }
                
                const paymentMethod = feeMethodSelect.value;
                const amount = {{ (float) $pendingFees }};
                
                Swal.fire({
                    title: 'Confirm Fee Payment',
                    html: `
                        <div class="text-left">
                            <p><strong>Total Fees:</strong> ${amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                            <p><strong>Payment Method:</strong> ${paymentMethod.replace('_', ' ').toUpperCase()}</p>
                        </div>
                        <p class="mt-3">Are you sure you want to pay all pending fees?</p>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Pay All Fees',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Processing Payment...',
                            text: 'Please wait while we process the fee payment.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        payAllFeesForm.submit();
                    }
                });
            });
        }
    }
});
</script>
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
