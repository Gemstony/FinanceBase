@extends('adminlte::page')

@section('title', 'Deposit Accounts – ' . $customer->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-wallet"></i> Deposit Accounts</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-wallet"></i> Deposit Accounts</h1>
                    <p class="mb-0 text-light">Borrower: <strong>{{ $customer->name }}</strong></p>
                </div>
                <div>
                    <a href="{{ route('deposits.create') }}" class="btn btn-success border">
                        <i class="fas fa-plus"></i> New Account
                    </a>
                    <a href="{{ route('deposits.index') }}" class="btn btn-light border ml-2">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('deposits.index') }}">Customer Deposit Accounts</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $customer->name }}</li>
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

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><strong>Summary</strong></div>
                    <div class="card-body">
                        <div class="mb-2"><strong>Total Balance:</strong> {{ number_format((float) $accounts->sum('balance'), 2) }}</div>
                        <div class="mb-2"><strong>Accounts:</strong> {{ $accounts->count() }}</div>
                        <div class="mb-2"><strong>Active:</strong> {{ $accounts->where('status', 'active')->count() }}</div>
                    </div>
                </div>

                <!-- Liability Configuration Status Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Liability Account</strong>
                        @if($liabilityConfigured)
                            <span class="badge badge-success">Configured</span>
                        @else
                            <span class="badge badge-danger">Not Configured</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($liabilityConfigured)
                            <div class="mb-2">
                                <strong>Account:</strong> {{ $liabilityConfig->chartOfAccount->account_name }}
                            </div>
                            @if($liabilityConfig->notes)
                                <div class="text-muted small">{{ $liabilityConfig->notes }}</div>
                            @endif
                        @else
                            <div class="alert alert-warning mb-2">
                                <i class="fas fa-exclamation-triangle"></i> Please configure the liability account before processing deposits or withdrawals.
                            </div>
                        @endif
                        <button class="btn btn-sm btn-outline-primary btn-block" data-toggle="modal" data-target="#liabilityConfigModal">
                            <i class="fas fa-cog"></i> {{ $liabilityConfigured ? 'Change' : 'Configure' }}
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Quick Actions</strong></div>
                    <div class="card-body">
                        <div class="text-muted mb-2">
                            Deposit, withdraw, transfer, or pay a loan from any active account below.
                        </div>

                        @if($accounts->where('status', 'active')->isNotEmpty())
                            <!-- Deposit Form -->
                            <form method="POST" action="{{ route('deposits.deposit') }}" class="mb-3">
                                @csrf
                                <div class="form-group">
                                    <label>Account</label>
                                    <select name="deposit_account_id" class="form-control" required>
                                        <option value="">Select account</option>
                                        @foreach($accounts->where('status', 'active') as $a)
                                            <option value="{{ $a->id }}">{{ $a->account_number }} – {{ number_format((float) $a->balance, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Amount</label>
                                    <input type="number" step="0.01" name="amount" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Payment Method</label>
                                    <select name="payment_method" id="deposit_payment_method" class="form-control" required>
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="mobile_money">Mobile Money</option>
                                    </select>
                                </div>
                                <div class="form-group" id="deposit_bank_account_group" style="display:none;">
                                    <label>Bank Account</label>
                                    <select name="bank_account_id" id="deposit_bank_account_id" class="form-control">
                                        <option value="">Select Bank Account</option>
                                        @foreach(($bankAccounts ?? collect()) as $account)
                                            <option value="{{ $account->id }}" data-account-type="{{ (string) $account->account_type }}">
                                                {{ $account->account_name }} - {{ $account->account_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Reference</label>
                                    <input type="text" name="reference" class="form-control" placeholder="Optional">
                                </div>
                                @if(!$liabilityConfigured)
                                    <div class="alert alert-danger mb-2">
                                        <i class="fas fa-ban"></i> Cannot deposit: liability account not configured.
                                    </div>
                                    <button class="btn btn-primary btn-block" type="button" disabled>
                                        <i class="fas fa-ban"></i> Deposit Unavailable
                                    </button>
                                @else
                                    <button class="btn btn-primary btn-block" type="submit">
                                        <i class="fas fa-plus"></i> Deposit
                                    </button>
                                @endif
                            </form>

                            <!-- Withdrawal Form -->
                            <form method="POST" action="{{ route('deposits.withdraw') }}" class="mb-3">
                                @csrf
                                <div class="form-group">
                                    <label>Account</label>
                                    <select name="deposit_account_id" class="form-control" required>
                                        <option value="">Select account</option>
                                        @foreach($accounts->where('status', 'active') as $a)
                                            <option value="{{ $a->id }}">{{ $a->account_number }} – {{ number_format((float) $a->balance, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Amount</label>
                                    <input type="number" step="0.01" name="amount" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Payment Method</label>
                                    <select name="payment_method" id="withdraw_payment_method" class="form-control" required>
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="mobile_money">Mobile Money</option>
                                    </select>
                                </div>
                                <div class="form-group" id="withdraw_bank_account_group" style="display:none;">
                                    <label>Bank Account</label>
                                    <select name="bank_account_id" id="withdraw_bank_account_id" class="form-control">
                                        <option value="">Select Bank Account</option>
                                        @foreach(($bankAccounts ?? collect()) as $account)
                                            <option value="{{ $account->id }}" data-account-type="{{ (string) $account->account_type }}">
                                                {{ $account->account_name }} - {{ $account->account_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Reference</label>
                                    <input type="text" name="reference" class="form-control" placeholder="Optional">
                                </div>
                                @if(!$liabilityConfigured)
                                    <div class="alert alert-danger mb-2">
                                        <i class="fas fa-ban"></i> Cannot withdraw: liability account not configured.
                                    </div>
                                    <button class="btn btn-outline-danger btn-block" type="button" disabled>
                                        <i class="fas fa-ban"></i> Withdrawal Unavailable
                                    </button>
                                @else
                                    <button class="btn btn-outline-danger btn-block" type="submit">
                                        <i class="fas fa-minus"></i> Withdraw
                                    </button>
                                @endif
                            </form>

                            <!-- Transfer Form -->
                            <form method="POST" action="{{ route('deposits.transfer') }}" class="mb-3">
                                @csrf
                                <div class="form-group">
                                    <label>From Account</label>
                                    <select name="from_account_id" class="form-control" required>
                                        <option value="">Select account</option>
                                        @foreach($accounts->where('status', 'active') as $a)
                                            <option value="{{ $a->id }}">{{ $a->account_number }} – {{ number_format((float) $a->balance, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>To Account</label>
                                    <select name="to_account_id" class="form-control" required>
                                        <option value="">Select account</option>
                                        @foreach($accounts->where('status', 'active') as $a)
                                            <option value="{{ $a->id }}">{{ $a->account_number }} – {{ number_format((float) $a->balance, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Amount</label>
                                    <input type="number" step="0.01" name="amount" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Reference</label>
                                    <input type="text" name="reference" class="form-control" placeholder="Optional">
                                </div>
                                <button class="btn btn-info btn-block" type="submit">
                                    <i class="fas fa-exchange-alt"></i> Transfer
                                </button>
                            </form>

                            <!-- Pay Loan Form -->
                            @if($activeLoans->isNotEmpty())
                                <form method="POST" action="{{ route('deposits.pay-loan') }}" class="mb-3">
                                    @csrf
                                    <div class="form-group">
                                        <label>Account</label>
                                        <select name="deposit_account_id" class="form-control" required>
                                            <option value="">Select account</option>
                                            @foreach($accounts->where('status', 'active') as $a)
                                                <option value="{{ $a->id }}">{{ $a->account_number }} – {{ number_format((float) $a->balance, 2) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Loan</label>
                                        <select name="loan_id" class="form-control" required>
                                            <option value="">Select loan</option>
                                            @foreach($activeLoans as $l)
                                                <option value="{{ $l->id }}">{{ $l->loan_code }} – Balance: {{ number_format((float) $l->outstanding_balance, 2) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Amount</label>
                                        <input type="number" step="0.01" name="amount" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Reference</label>
                                        <input type="text" name="reference" class="form-control" placeholder="Optional">
                                    </div>
                                    <button class="btn btn-success btn-block" type="submit">
                                        <i class="fas fa-credit-card"></i> Pay Loan
                                    </button>
                                </form>
                            @endif
                        @else
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                <p class="mb-0">No active accounts available for transactions.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><strong>Accounts</strong></div>
                    <div class="card-body table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Account</th>
                                    <th>Product</th>
                                    <th class="text-right">Balance</th>
                                    <th>Status</th>
                                    <th>Opened</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($accounts as $a)
                                    <tr>
                                        <td>{{ $a->account_number }}</td>
                                        <td>{{ $a->depositProduct?->name ?? '—' }}</td>
                                        <td class="text-right">{{ number_format((float) $a->balance, 2) }}</td>
                                        <td>
                                            @php
                                                $cls = match((string) $a->status) {
                                                    'active' => 'badge-success',
                                                    'frozen' => 'badge-warning',
                                                    'dormant' => 'badge-secondary',
                                                    'closed' => 'badge-dark',
                                                    default => 'badge-light',
                                                };
                                            @endphp
                                            <span class="badge {{ $cls }}">{{ ucfirst($a->status) }}</span>
                                        </td>
                                        <td>{{ $a->opened_at?->format('Y-m-d') ?? '—' }}</td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('deposits.transactions', $a) }}">
                                                <i class="fas fa-list"></i> Ledger
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No deposit accounts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="mt-3">
                            {{ $accounts->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

<!-- Liability Configuration Modal -->
<div class="modal fade" id="liabilityConfigModal" tabindex="-1" role="dialog" aria-labelledby="liabilityConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('deposits.liability-account.configure') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="liabilityConfigModalLabel">
                        <i class="fas fa-cog"></i> Configure Customer Deposits Liability Account
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Select a liability account (Class 2) to use for customer deposits.
                    </div>
                    <div class="form-group">
                        <label>Liability Account <span class="text-danger">*</span></label>
                        <select name="chart_of_account_id" class="form-control" required>
                            <option value="">Select Liability Account</option>
                            @php
                                $liabilityAccounts = \App\Models\ChartsOfAccount::with('accountClass')
                                    ->where('subshop_id', session('subshop_id'))
                                    ->where('is_active', true)
                                    ->get()
                                    ->filter(fn($acc) => (int)($acc->accountClass?->code ?? 0) === 2);
                            @endphp
                            @foreach($liabilityAccounts as $acc)
                                <option value="{{ $acc->id }}" @selected(($liabilityConfig?->chart_of_account_id) == $acc->id)>
                                    {{ $acc->account_name }} ({{ $acc->account_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ $liabilityConfig?->notes }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function setOptionVisibility(option, visible) {
        option.hidden = !visible;
        option.disabled = !visible;
    }

    function updateBankAccountOptions(methodEl, bankGroupEl, bankEl) {
        if (!methodEl || !bankGroupEl || !bankEl) {
            return;
        }

        const method = methodEl.value;
        const shouldShow = method === 'cash' || method === 'bank_transfer' || method === 'mobile_money';
        bankGroupEl.style.display = shouldShow ? '' : 'none';

        const required = method === 'bank_transfer' || method === 'mobile_money';
        bankEl.required = required;

        const options = Array.from(bankEl.querySelectorAll('option'));
        options.forEach((opt) => {
            if (!opt.value) {
                setOptionVisibility(opt, true);
                return;
            }

            const accountType = (opt.dataset.accountType || '').toLowerCase();
            let match = true;

            if (method === 'cash') {
                match = accountType === 'cash';
            } else if (method === 'bank_transfer') {
                match = accountType === 'bank';
            } else if (method === 'mobile_money') {
                match = accountType === 'mobile_money' || accountType === 'mobile' || accountType === 'wallet';
            }

            setOptionVisibility(opt, match);
        });

        const selected = bankEl.options[bankEl.selectedIndex];
        if (selected && (selected.hidden || selected.disabled)) {
            bankEl.value = '';
        }
    }

    const depositMethodEl = document.getElementById('deposit_payment_method');
    const depositBankGroupEl = document.getElementById('deposit_bank_account_group');
    const depositBankEl = document.getElementById('deposit_bank_account_id');

    if (depositMethodEl) {
        depositMethodEl.addEventListener('change', function () {
            updateBankAccountOptions(depositMethodEl, depositBankGroupEl, depositBankEl);
        });
        updateBankAccountOptions(depositMethodEl, depositBankGroupEl, depositBankEl);
    }

    const withdrawMethodEl = document.getElementById('withdraw_payment_method');
    const withdrawBankGroupEl = document.getElementById('withdraw_bank_account_group');
    const withdrawBankEl = document.getElementById('withdraw_bank_account_id');

    if (withdrawMethodEl) {
        withdrawMethodEl.addEventListener('change', function () {
            updateBankAccountOptions(withdrawMethodEl, withdrawBankGroupEl, withdrawBankEl);
        });
        updateBankAccountOptions(withdrawMethodEl, withdrawBankGroupEl, withdrawBankEl);
    }

    // Deposit form
    const depositForm = document.querySelector('form[action*="deposit"]');
    if (depositForm) {
        depositForm.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Confirm Deposit?',
                text: 'This will record a deposit into the selected account.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Deposit'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    // Withdrawal form
    const withdrawForm = document.querySelector('form[action*="withdraw"]');
    if (withdrawForm) {
        withdrawForm.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Confirm Withdrawal?',
                text: 'This will record a withdrawal from the selected account.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Withdraw'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    // Transfer form
    const transferForm = document.querySelector('form[action*="transfer"]');
    if (transferForm) {
        transferForm.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Confirm Transfer?',
                text: 'This will transfer funds between the selected accounts.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#17a2b8',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Transfer'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    // Pay loan form
    const payLoanForm = document.querySelector('form[action*="pay-loan"]');
    if (payLoanForm) {
        payLoanForm.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Pay Loan from Savings?',
                text: 'This will use the selected deposit account to pay the loan installment.',
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Pay'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }
});
</script>
@endpush
