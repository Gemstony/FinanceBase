@extends('adminlte::page')

@section('title', 'Record Loan Recovery')

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const paymentMethodEl = document.querySelector('[data-recovery-payment-method]');
            const bankAccountWrap = document.getElementById('bank_account_wrap');
            const bankAccountSelect = document.querySelector('select[name="bank_account_id"]');
            const amountInput = document.getElementById('amount');
            
            // Outstanding balances from server
            const outstanding = {
                penalties: {{ $outstandingBalances['penalties'] }},
                fees: {{ $outstandingBalances['fees'] }},
                interest: {{ $outstandingBalances['interest'] }},
                principal: {{ $outstandingBalances['principal'] }},
                total: {{ $outstandingBalances['total'] }}
            };

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

            function updateAllocationPreview() {
                if (!amountInput) return;
                
                const amount = parseFloat(amountInput.value) || 0;
                let remaining = amount;
                
                const allocPenalties = Math.min(outstanding.penalties, remaining);
                remaining = Math.max(0, remaining - allocPenalties);
                
                const allocFees = Math.min(outstanding.fees, remaining);
                remaining = Math.max(0, remaining - allocFees);
                
                const allocInterest = Math.min(outstanding.interest, remaining);
                remaining = Math.max(0, remaining - allocInterest);
                
                const allocPrincipal = Math.min(outstanding.principal, remaining);
                
                // Update preview if element exists
                const previewEl = document.getElementById('allocation-preview');
                if (previewEl) {
                    if (amount > 0 && amount <= outstanding.total) {
                        previewEl.innerHTML = `
                            <div class="row">
                                <div class="col-6"><small>Penalties:</small></div>
                                <div class="col-6 text-right"><small>${allocPenalties.toFixed(2)}</small></div>
                                <div class="col-6"><small>Fees:</small></div>
                                <div class="col-6 text-right"><small>${allocFees.toFixed(2)}</small></div>
                                <div class="col-6"><small>Interest:</small></div>
                                <div class="col-6 text-right"><small>${allocInterest.toFixed(2)}</small></div>
                                <div class="col-6"><small>Principal:</small></div>
                                <div class="col-6 text-right"><small>${allocPrincipal.toFixed(2)}</small></div>
                            </div>
                        `;
                        previewEl.style.display = 'block';
                    } else {
                        previewEl.style.display = 'none';
                    }
                }
                
                // Validate amount
                if (amount > outstanding.total) {
                    amountInput.setCustomValidity(`Amount cannot exceed outstanding balance of ${outstanding.total.toFixed(2)}`);
                } else {
                    amountInput.setCustomValidity('');
                }
            }

            if (paymentMethodEl) {
                paymentMethodEl.addEventListener('change', updateBankAccountVisibility);
                updateBankAccountVisibility();
            }
            
            if (amountInput) {
                amountInput.addEventListener('input', updateAllocationPreview);
                updateAllocationPreview();
            }
        });
    </script>
@stop

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-money-bill-wave"></i> Record Recovery</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-money-bill-wave"></i> Record Recovery</h1>
                    <p class="mb-0 text-light">Loan Code: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ url()->previous() }}" class="btn btn-light">
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
                <li class="breadcrumb-item"><a href="{{ route('loans.loans.index') }}">Loans</a></li>
                <li class="breadcrumb-item"><a href="{{ route('loans.loans.show', $loan) }}">Loan</a></li>
                <li class="breadcrumb-item active" aria-current="page">Record Recovery</li>
            </ol>
        </nav>
        <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light border">
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
        <div class="card-header"><strong>Loan Information</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div><strong>Borrower:</strong>
                        @if($loan->borrower_type === 'group')
                            {{ $loan->loanGroup?->name }}
                        @else
                            {{ $loan->customer?->name }}
                        @endif
                    </div>
                    <div><strong>Loan Code:</strong> {{ $loan->loan_code }}</div>
                    <div><strong>Status:</strong> {{ $loan->status }}</div>
                </div>
                <div class="col-md-6">
                    <div><strong>Product:</strong> {{ $loan->loanProduct?->name }}</div>
                    <div><strong>Principal:</strong> {{ number_format((float) $loan->principal_amount, 2) }}</div>
                    <div><strong>Interest Rate:</strong> {{ number_format((float) $loan->interest_rate, 2) }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-light"><strong>Outstanding Balances</strong></div>
        <div class="card-body">
            @if($outstandingBalances['total'] > 0)
                <div class="row">
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted small">Outstanding Penalties</div>
                        <div><strong class="text-danger">{{ number_format($outstandingBalances['penalties'], 2) }}</strong></div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted small">Outstanding Fees</div>
                        <div><strong class="text-warning">{{ number_format($outstandingBalances['fees'], 2) }}</strong></div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted small">Outstanding Interest</div>
                        <div><strong class="text-info">{{ number_format($outstandingBalances['interest'], 2) }}</strong></div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="text-muted small">Outstanding Principal</div>
                        <div><strong class="text-primary">{{ number_format($outstandingBalances['principal'], 2) }}</strong></div>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted">Total Outstanding</div>
                    <div><strong class="text-success">{{ number_format($outstandingBalances['total'], 2) }}</strong></div>
                </div>
                <div class="mt-2">
                    <small class="text-info">
                        <i class="fas fa-info-circle"></i> Recovery will be allocated in priority order: Penalties → Fees → Interest → Principal
                    </small>
                </div>
            @else
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle"></i> No outstanding balances to recover.
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Recovery Details</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('loans.recovery.store', $loan) }}">
                @csrf

                <div class="form-group">
                    <label for="recovery_date">Recovery Date</label>
                    <input type="date" class="form-control" id="recovery_date" name="recovery_date" value="{{ old('recovery_date', $today ?? now()->toDateString()) }}" required>
                </div>

                <div class="form-group">
                    <label for="amount">Total Recovery Amount</label>
                    <input type="number" step="0.01" min="0.01" max="{{ $outstandingBalances['total'] }}" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" required>
                    <small class="text-muted">The system will automatically allocate this amount to penalties, fees, interest, then principal.</small>
                    <div id="allocation-preview" class="mt-2 p-2 bg-light rounded" style="display: none;">
                        <small class="text-muted d-block mb-1">Allocation Preview:</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" class="form-control" data-recovery-payment-method required>
                        @foreach($globalPaymentMethods as $method)
                            <option value="{{ $method->code }}" data-requires-bank="{{ $method->requires_bank_account ? 'true' : 'false' }}">
                                {{ $method->name }}
                            </option>
                        @endforeach
                    </select>
                    @if($globalPaymentMethods->isEmpty())
                        <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> No active recovery payment methods configured.</small>
                    @endif
                </div>

                <div class="form-group" id="bank_account_wrap" style="display:none;">
                    <label for="bank_account_id">Bank Account</label>
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
                    <label for="reference_number">Transaction Reference</label>
                    <input type="text" class="form-control" id="reference_number" name="reference_number" value="{{ old('reference_number') }}" placeholder="Optional">
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Record Recovery
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush