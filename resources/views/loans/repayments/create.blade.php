@extends('adminlte::page')

@section('title', 'Make Payment - ' . $loan->loan_code)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-cash-register"></i> Make Payment</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-cash-register"></i> Make Payment</h1>
                    <p class="mb-0 text-light">Loan Code: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ route('loan.repayments.index') }}" class="btn btn-light btn-sm border">
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
                <li class="breadcrumb-item"><a href="{{ route('loan.repayments.index') }}">Repayments</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $loan->loan_code }}</li>
            </ol>
        </nav>
    </div>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const outstanding = Number({{ (float) ($summary['total_balance'] ?? 0) }});
            const input = document.querySelector('input[name="payment_amount"]');
            const box = document.getElementById('overpayment_warning');
            const amountEl = document.getElementById('overpayment_amount');
            const paymentMethodEl = document.querySelector('select[name="payment_method"]');
            const bankAccountWrap = document.getElementById('bank_account_wrap');
            const bankAccountSelect = document.querySelector('select[name="bank_account_id"]');
            const azampayFields = document.getElementById('azampay_fields');
            const phoneInput = document.querySelector('input[name="phone_number"]');
            const providerSelect = document.querySelector('select[name="provider"]');

            function update() {
                if (!input || !box || !amountEl) return;
                const val = Number(input.value || 0);
                const over = Math.round(Math.max(0, val - outstanding) * 100) / 100;
                if (over > 0) {
                    amountEl.textContent = over.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    box.style.display = '';
                } else {
                    box.style.display = 'none';
                }
            }

            function updateBankAccountVisibility() {
                if (!paymentMethodEl || !bankAccountWrap || !bankAccountSelect) return;
                const selectedOption = paymentMethodEl.options[paymentMethodEl.selectedIndex];
                const requiresBank = selectedOption && selectedOption.getAttribute('data-requires-bank') === 'true';

                if (requiresBank) {
                    bankAccountWrap.style.display = '';
                    bankAccountSelect.required = true;
                } else {
                    bankAccountWrap.style.display = 'none';
                    bankAccountSelect.required = false;
                    bankAccountSelect.value = '';
                }
            }

            function updateAzamPayFields() {
                if (!paymentMethodEl || !azampayFields) return;
                const selectedOption = paymentMethodEl.options[paymentMethodEl.selectedIndex];
                const requiresPhone = selectedOption && selectedOption.getAttribute('data-requires-phone') === 'true';

                if (requiresPhone) {
                    azampayFields.style.display = '';
                    if (phoneInput) phoneInput.required = true;
                    if (providerSelect) providerSelect.required = true;
                } else {
                    azampayFields.style.display = 'none';
                    if (phoneInput) {
                        phoneInput.required = false;
                        phoneInput.value = '';
                    }
                    if (providerSelect) {
                        providerSelect.required = false;
                        providerSelect.value = '';
                    }
                }
            }

            if (input) {
                input.addEventListener('input', update);
                update();
            }

            if (paymentMethodEl) {
                paymentMethodEl.addEventListener('change', function() {
                    updateBankAccountVisibility();
                    updateAzamPayFields();
                });
                updateBankAccountVisibility();
                updateAzamPayFields();
            }

            // SweetAlert confirmation for Process Payment button
            const paymentForm = document.querySelector('form[action="{{ route('loan.repayments.store') }}"]');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const paymentAmount = document.querySelector('input[name="payment_amount"]').value;
                    const paymentMethod = document.querySelector('select[name="payment_method"]').value;
                    const paymentDate = document.querySelector('input[name="payment_date"]').value;
                    
                    Swal.fire({
                        title: 'Confirm Payment',
                        html: `
                            <div class="text-left">
                                <p><strong>Amount:</strong> ${parseFloat(paymentAmount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                <p><strong>Method:</strong> ${paymentMethod.replace('_', ' ').toUpperCase()}</p>
                                <p><strong>Date:</strong> ${paymentDate}</p>
                            </div>
                            <p class="mt-3">Are you sure you want to process this payment?</p>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Process Payment',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Show loading state
                            Swal.fire({
                                title: 'Processing Payment...',
                                text: 'Please wait while we process your payment.',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            // Submit the form
                            paymentForm.submit();
                        }
                    });
                });
            }
        });
    </script>
@stop

@section('content')
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif
        @if(session('info'))
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> {{ session('info') }}
            </div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
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
                            {{ $loan->loanGroup?->name ?? $loan->customer?->name ?? '—' }} Loan
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
                                    'disbursed' => 'badge-primary',
                                    'partially_paid' => 'badge-info',
                                    'paid_off' => 'badge-success',
                                    default => 'badge-secondary',
                                };
                            @endphp
                            <span class="badge {{ $statusBadgeClass }}">{{ $loan->status }}</span>
                        </div>
                        <div class="text-muted">
                            Balance: <strong>{{ number_format((float) ($summary['total_balance'] ?? 0), 2) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted">Principal Outstanding</div>
                        <div><strong>{{ number_format((float) ($summary['principal_outstanding'] ?? 0), 2) }}</strong></div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted">Interest Outstanding</div>
                        <div><strong>{{ number_format((float) ($summary['interest_outstanding'] ?? 0), 2) }}</strong></div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted">Penalty Outstanding</div>
                        <div><strong>{{ number_format((float) ($summary['penalties_outstanding'] ?? 0), 2) }}</strong></div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted">Loan Product</div>
                        <div><strong>{{ $loan->loanProduct?->name ?? '—' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><strong>Loan Summary</strong></div>
                    <div class="card-body">
                        <div class="mb-2"><strong>Borrower:</strong> {{ $loan->loanGroup?->name ?? $loan->customer?->name ?? '—' }}</div>
                        <div class="mb-2"><strong>Principal Outstanding:</strong> {{ number_format((float) ($summary['principal_outstanding'] ?? 0), 2) }}</div>
                        <div class="mb-2"><strong>Interest Outstanding:</strong> {{ number_format((float) ($summary['interest_outstanding'] ?? 0), 2) }}</div>
                        <div class="mb-2"><strong>Penalty Outstanding:</strong> {{ number_format((float) ($summary['penalties_outstanding'] ?? 0), 2) }}</div>
                        <div class="mb-2"><strong>Total Balance:</strong> {{ number_format((float) ($summary['total_balance'] ?? 0), 2) }}</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Payment Form</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('loan.repayments.store') }}">
                            @csrf

                            <input type="hidden" name="loan_code" value="{{ $loan->loan_code }}">

                            @if(!$loan->customer_id)
                                <div class="form-group">
                                    <label>Payer</label>
                                    <select name="payer_customer_id" class="form-control" required>
                                        <option value="">Select Payer</option>
                                        @foreach(($payers ?? collect()) as $payer)
                                            <option value="{{ $payer->id }}" @selected((string) old('payer_customer_id') === (string) $payer->id)>
                                                {{ $payer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="form-group">
                                <label>Payment Date</label>
                                <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
                            </div>

                            <div class="form-group">
                                <label>Payment Amount</label>
                                <input type="number" step="0.01" name="payment_amount" class="form-control" value="{{ old('payment_amount') }}" required>
                                <div id="overpayment_warning" class="alert alert-warning mt-2" style="display:none;">
                                    Payment exceeds remaining balance.
                                    Extra amount (<strong id="overpayment_amount"></strong>) will be stored as customer credit.
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="payment_method" class="form-control" id="payment_method" required>
                                    @php $pm = old('payment_method', 'cash'); @endphp
                                    @foreach($globalPaymentMethods->where('is_repayment_method', true) as $method)
                                        <option value="{{ $method->code }}" @selected($pm === $method->code) data-requires-bank="{{ $method->requires_bank_account ? 'true' : 'false' }}" data-requires-phone="{{ $method->requires_phone ? 'true' : 'false' }}">
                                            {{ $method->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($globalPaymentMethods->where('is_repayment_method', true)->isEmpty())
                                    <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> No active payment methods configured. Please contact administrator.</small>
                                @endif
                            </div>

                            <!-- AzamPay Dynamic Fields -->
                            <div id="azampay_fields" style="display: none;">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" name="phone_number" id="phone_number" class="form-control" 
                                           value="{{ old('phone_number') }}" 
                                           placeholder="2557XXXXXXX">
                                    <small class="text-muted">Enter mobile number registered with mobile money</small>
                                </div>

                                <div class="form-group">
                                    <label>Network Provider</label>
                                    <select name="provider" id="provider" class="form-control">
                                        <option value="">Select Provider</option>
                                        <option value="Mpesa" @selected(old('provider') === 'Mpesa')>Mpesa</option>
                                        <option value="Airtel" @selected(old('provider') === 'Airtel')>Airtel</option>
                                        <option value="Tigo" @selected(old('provider') === 'Tigo')>Tigo</option>
                                        <option value="Halopesa" @selected(old('provider') === 'Halopesa')>Halopesa</option>
                                        <option value="Azampesa" @selected(old('provider') === 'Azampesa')>Azampesa</option>
                                    </select>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    You will receive a payment prompt on your phone. Please complete the payment to finalize this transaction.
                                </div>
                            </div>

                            <div class="form-group" id="bank_account_wrap" style="display:none;">
                                <label>Bank Account</label>
                                <select name="bank_account_id" class="form-control">
                                    <option value="">Select Bank Account</option>
                                    @foreach(($bankAccounts ?? collect()) as $account)
                                        <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>
                                            {{ $account->account_name }} - {{ $account->account_number }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Required for non-cash payments.</small>
                            </div>

                            <div class="form-group">
                                <label>Transaction Reference</label>
                                <input type="text" name="transaction_reference" class="form-control" value="{{ old('transaction_reference') }}" placeholder="Optional">
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Optional">{{ old('notes') }}</textarea>
                            </div>

                            <button class="btn btn-success btn-block" type="submit">
                                <i class="fas fa-check"></i> Process Payment
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><i class="fas fa-calendar-alt"></i> Installments</strong>
                        <a href="{{ route('loans.loans.schedule.export', $loan) }}" class="btn btn-sm btn-info">
                            <i class="fas fa-file-pdf"></i> Export Schedule
                        </a>
                    </div>
                    <div class="card-body table-responsive">
                        @php
                            $versions = isset($installmentsByVersion) ? $installmentsByVersion->keys()->sortDesc()->values() : collect();
                        @endphp

                        @forelse($versions as $ver)
                            @php
                                $rows = $installmentsByVersion->get($ver, collect());
                                $title = ((int) $ver === (int) ($latestScheduleVersion ?? 1)) ? 'Current Schedule' : 'Previous Schedule (Restructured)';
                                $badgeClass = ((int) $ver === (int) ($latestScheduleVersion ?? 1)) ? 'badge-success' : 'badge-secondary';
                            @endphp

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>{{ $title }}</strong>
                                    <span class="badge {{ $badgeClass }}">Version {{ (int) $ver }}</span>
                                </div>

                                <table class="table table-striped table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Due Date</th>
                                            <th>Principal Due</th>
                                            <th>Interest Due</th>
                                            <th>Penalty Due</th>
                                            <th>Total Due</th>
                                            <th>Principal Paid</th>
                                            <th>Interest Paid</th>
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
                                                <td>{{ number_format((float)$i->penalty_due, 2) }}</td>
                                                <td>{{ number_format((float)$i->total_due, 2) }}</td>
                                                <td>{{ number_format((float)$i->principal_paid, 2) }}</td>
                                                <td>{{ number_format((float)$i->interest_paid, 2) }}</td>
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
                                            <tr>
                                                <td colspan="11" class="text-center text-muted">No installments found.</td>
                                            </tr>
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
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
