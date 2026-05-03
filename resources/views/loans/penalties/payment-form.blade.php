@extends('adminlte::page')

@section('title', 'Pay Penalties - ' . $loan->loan_code)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-money-bill-wave"></i> Pay Penalties</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-money-bill-wave"></i> Pay Penalties</h1>
                    <p class="mb-0 text-light">Loan Code: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light btn-sm border">
                    <i class="fas fa-arrow-left"></i> Back to Loan
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
                <li class="breadcrumb-item"><a href="{{ route('loans.loans.show', $loan) }}">{{ $loan->loan_code }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Pay Penalties</li>
            </ol>
        </nav>
    </div>
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

        <div class="row">
            <!-- Penalty Summary Card -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-gradient-warning">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i> Penalty Summary
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Charged:</span>
                            <strong>{{ number_format($penaltySummary['total_charged'], 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Paid:</span>
                            <strong class="text-success">{{ number_format($penaltySummary['total_paid'], 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Forgiven:</span>
                            <strong class="text-info">{{ number_format($penaltySummary['total_forgiven'], 2) }}</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Outstanding:</span>
                            <strong class="text-danger">{{ number_format($penaltySummary['total_outstanding'], 2) }}</strong>
                        </div>
                        <div class="mt-3">
                            <span class="badge {{ $penaltySummary['status_class'] }} badge-lg">
                                {{ $penaltySummary['status_label'] }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-gradient-primary">
                        <h3 class="card-title">
                            <i class="fas fa-credit-card"></i> Make Penalty Payment
                        </h3>
                    </div>
                    <div class="card-body">
                        @if($penaltyData['total_pending'] <= 0)
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> All penalties have been paid. No outstanding penalties.
                            </div>
                        @else
                            <form id="penaltyPaymentForm" action="{{ route('loan.penalties.pay', $loan) }}" method="POST">
                                @csrf

                                <!-- Penalty Selection -->
                                <div class="form-group">
                                    <label for="penalty_application_id">Select Penalty <span class="text-danger">*</span></label>
                                    <select name="penalty_application_id" id="penalty_application_id" class="form-control @error('penalty_application_id') is-invalid @enderror" required>
                                        <option value="">-- Select a penalty --</option>
                                        @foreach($penaltyData['items'] as $item)
                                            <option value="{{ $item['id'] }}" data-amount="{{ $item['outstanding'] }}">
                                                {{ $item['penalty_name'] }} - {{ $item['applied_on']->format('Y-m-d') }}
                                                (Outstanding: {{ number_format($item['outstanding'], 2) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('penalty_application_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Amount -->
                                <div class="form-group">
                                    <label for="amount">Payment Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">{{ config('app.currency', 'TZS') }}</span>
                                        </div>
                                        <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror"
                                               step="0.01" min="0.01" required placeholder="0.00">
                                    </div>
                                    <small class="form-text text-muted">Maximum: <span id="maxAmount">0.00</span></small>
                                    @error('amount')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Payment Method -->
                                <div class="form-group">
                                    <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                    <select name="payment_method" id="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                                        <option value="">-- Select payment method --</option>
                                        @foreach($globalPaymentMethods->where('is_deposit_method', true) as $method)
                                            <option value="{{ $method->code }}" data-requires-bank="{{ $method->requires_bank_account ? 'true' : 'false' }}">
                                                {{ $method->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('payment_method')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    @if($globalPaymentMethods->where('is_deposit_method', true)->isEmpty())
                                        <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> No active payment methods configured.</small>
                                    @endif
                                </div>

                                <!-- Bank Account (conditional) -->
                                <div class="form-group" id="bankAccountField" style="display: none;">
                                    <label for="payment_bank_account_id">Bank Account <span class="text-danger">*</span></label>
                                    <select name="payment_bank_account_id" id="payment_bank_account_id" class="form-control @error('payment_bank_account_id') is-invalid @enderror">
                                        <option value="">-- Select bank account --</option>
                                        @foreach($bankAccounts as $account)
                                            <option value="{{ $account->id }}">
                                                {{ $account->account_name }} ({{ $account->account_number }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('payment_bank_account_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    @if($bankAccounts->isEmpty())
                                        <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> No active bank accounts found.</small>
                                    @endif
                                </div>

                                <!-- Reference Number -->
                                <div class="form-group">
                                    <label for="reference_number">Reference Number</label>
                                    <input type="text" name="reference_number" id="reference_number" class="form-control @error('reference_number') is-invalid @enderror"
                                           maxlength="255" placeholder="e.g., Transaction ID, Check Number">
                                    @error('reference_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Notes -->
                                <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror"
                                              rows="2" maxlength="500" placeholder="Optional notes..."></textarea>
                                    @error('notes')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Process Payment
                                    </button>
                                    <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Penalties Table -->
        @if($penaltyData['count'] > 0)
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list"></i> Pending Penalties ({{ $penaltyData['count'] }})
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date Applied</th>
                                        <th>Penalty Type</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Forgiven</th>
                                        <th>Outstanding</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($penaltyData['items'] as $item)
                                        <tr>
                                            <td>{{ $item['applied_on']->format('Y-m-d') }}</td>
                                            <td>{{ $item['penalty_name'] }}</td>
                                            <td>{{ number_format($item['amount'], 2) }}</td>
                                            <td class="text-success">{{ number_format($item['paid_amount'], 2) }}</td>
                                            <td class="text-info">{{ number_format($item['forgiven_amount'], 2) }}</td>
                                            <td class="text-danger font-weight-bold">{{ number_format($item['outstanding'], 2) }}</td>
                                            <td>
                                                <span class="badge {{ $item['status_badge_class'] }}">
                                                    {{ $item['status_label'] }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($item['outstanding'] > 0)
                                                    <a href="{{ route('loan.penalties.forgive.form', [$loan, $item['id']]) }}"
                                                       class="btn btn-sm btn-info" title="Forgive Penalty">
                                                        <i class="fas fa-hand-holding-heart"></i> Forgive
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const penaltySelect = document.getElementById('penalty_application_id');
            const amountInput = document.getElementById('amount');
            const maxAmountSpan = document.getElementById('maxAmount');
            const paymentMethodSelect = document.getElementById('payment_method');
            const bankAccountField = document.getElementById('bankAccountField');
            const form = document.getElementById('penaltyPaymentForm');

            // Update max amount when penalty is selected
            if (penaltySelect) {
                penaltySelect.addEventListener('change', function () {
                    const selected = this.options[this.selectedIndex];
                    const maxAmount = selected.dataset.amount || 0;
                    maxAmountSpan.textContent = parseFloat(maxAmount).toFixed(2);
                    amountInput.max = maxAmount;
                    amountInput.placeholder = 'Max: ' + parseFloat(maxAmount).toFixed(2);
                });
            }

            // Show/hide bank account field
            if (paymentMethodSelect) {
                paymentMethodSelect.addEventListener('change', function () {
                    const selected = this.options[this.selectedIndex];
                    const requiresBank = selected.dataset.requiresBank === 'true';
                    bankAccountField.style.display = requiresBank ? 'block' : 'none';
                });
            }

            // SweetAlert confirmation
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const amount = amountInput.value;
                    const method = paymentMethodSelect.options[paymentMethodSelect.selectedIndex].text;

                    Swal.fire({
                        title: 'Confirm Penalty Payment',
                        html: `
                            <div class="text-left">
                                <p><strong>Amount:</strong> ${parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                <p><strong>Method:</strong> ${method}</p>
                            </div>
                            <p class="mt-3">Are you sure you want to process this penalty payment?</p>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Process Payment',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Processing...',
                                text: 'Please wait while we process the payment.',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            form.submit();
                        }
                    });
                });
            }
        });
    </script>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
