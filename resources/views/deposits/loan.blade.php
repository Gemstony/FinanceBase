@extends('adminlte::page')

@section('title', 'Loan Security Deposits')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-file-invoice-dollar"></i> Loan Deposits</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-file-invoice-dollar"></i> Loan Deposits</h1>
                    <p class="mb-0 text-light">Loan: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ route('security-deposits.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('security-deposits.index') }}">Security Deposits</a></li>
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

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><strong>Totals</strong></div>
                    <div class="card-body">
                        <div class="mb-2"><strong>Required:</strong> {{ number_format((float) ($loan->requires_security_deposit ? ($loan->security_deposit_amount ?? 0) : 0), 2) }}</div>
                        <div class="mb-2"><strong>Held:</strong> {{ number_format((float) $heldTotal, 2) }}</div>
                        <div class="mb-2"><strong>Applied:</strong> {{ number_format((float) $appliedTotal, 2) }}</div>
                        <div class="mb-2"><strong>Refunded:</strong> {{ number_format((float) $refundedTotal, 2) }}</div>
                        <div class="mb-2"><strong>Forfeited:</strong> {{ number_format((float) $forfeitedTotal, 2) }}</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Actions</strong></div>
                    <div class="card-body">
                        <div class="text-muted mb-2">
                            Manage held deposits: refund, forfeit, or apply to loans.
                        </div>

                        @if($heldDeposits->isNotEmpty())
                            <form method="POST" action="{{ route('security-deposits.refund') }}" class="mb-3">
                                @csrf
                                <div class="form-group">
                                    <label>Held Deposit</label>
                                    <select name="deposit_id" class="form-control" required>
                                        <option value="">Select deposit</option>
                                        @foreach($heldDeposits as $d)
                                            <option value="{{ $d->id }}">{{ $d->customer?->name ?? '—' }} - {{ number_format((float) $d->amount, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Refund Amount</label>
                                    <input name="refund_amount" type="number" step="0.01" min="0" class="form-control" required>
                                    <small class="text-muted">Must be less than or equal to the selected held deposit amount.</small>
                                </div>
                                <div class="form-group">
                                    <label>Refund Method</label>
                                    <select name="refund_method" id="refund_method" class="form-control" required>
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="mobile_money">Mobile Money</option>
                                    </select>
                                </div>
                                <div class="form-group" id="bank_account_group" style="display:none;">
                                    <label>Bank Account</label>
                                    <select name="bank_account_id" id="bank_account_id" class="form-control">
                                        <option value="">Select Bank Account</option>
                                        @foreach(($bankAccounts ?? collect()) as $account)
                                            <option value="{{ $account->id }}" data-account-type="{{ (string) $account->account_type }}">
                                                {{ $account->account_name }} - {{ $account->account_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Security Deposit Liability Account</label>
                                    <select name="liability_account_id" class="form-control" required>
                                        <option value="">Select Liability Account</option>
                                        @foreach(($liabilityAccounts ?? collect()) as $acc)
                                            <option value="{{ $acc->id }}">
                                                {{ $acc->account_name }} ({{ $acc->account_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional"></textarea>
                                </div>
                                <button class="btn btn-outline-danger btn-block" type="submit">
                                    <i class="fas fa-undo"></i> Refund Deposit
                                </button>
                            </form>

                            <form method="POST" action="{{ route('security-deposits.forfeit') }}" class="mb-3">
                                @csrf
                                <div class="form-group">
                                    <label>Held Deposit</label>
                                    <select name="deposit_id" class="form-control" required>
                                        <option value="">Select deposit</option>
                                        @foreach($heldDeposits as $d)
                                            <option value="{{ $d->id }}">{{ $d->customer?->name ?? '—' }} - {{ number_format((float) $d->amount, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional"></textarea>
                                </div>
                                <button class="btn btn-outline-dark btn-block" type="submit">
                                    <i class="fas fa-ban"></i> Forfeit Deposit
                                </button>
                            </form>

                            <form method="POST" action="{{ route('security-deposits.apply') }}">
                                @csrf
                                <div class="form-group">
                                    <label>Held Deposit</label>
                                    <select name="deposit_id" class="form-control" required>
                                        <option value="">Select deposit</option>
                                        @foreach($heldDeposits as $d)
                                            <option value="{{ $d->id }}">{{ $d->customer?->name ?? '—' }} - {{ number_format((float) $d->amount, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Target Loan</label>
                                    <select name="loan_id" class="form-control" required>
                                        <option value="">Select active loan</option>
                                        @foreach(($activeLoans ?? collect()) as $l)
                                            <option value="{{ $l->id }}">{{ $l->loan_code }} - Balance: {{ number_format((float) $l->outstanding_balance, 2) }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Only active loans with outstanding balance are shown.</small>
                                </div>
                                <button class="btn btn-primary btn-block" type="submit">
                                    <i class="fas fa-check"></i> Apply Deposit
                                </button>
                            </form>
                        @else
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                <p class="mb-0">No held deposits available for actions.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><strong>Deposit History</strong></div>
                    <div class="card-body table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Borrower</th>
                                    <th class="text-right">Amount</th>
                                    <th>Status</th>
                                    <th>Held At</th>
                                    <th>Released At</th>
                                    <th>Applied To</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($deposits as $d)
                                    <tr>
                                        <td>{{ $d->customer?->name ?? '—' }}</td>
                                        <td class="text-right">{{ number_format((float) $d->amount, 2) }}</td>
                                        <td>
                                            @php
                                                $cls = match((string) $d->status) {
                                                    'held' => 'badge-success',
                                                    'applied' => 'badge-info',
                                                    'refunded' => 'badge-secondary',
                                                    'forfeited' => 'badge-dark',
                                                    default => 'badge-light',
                                                };
                                            @endphp
                                            <span class="badge {{ $cls }}">{{ ucfirst($d->status) }}</span>
                                        </td>
                                        <td>{{ $d->held_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td>{{ $d->released_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td>{{ $d->appliedToLoan?->loan_code ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No deposits found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="mt-3">
                            {{ $deposits->links() }}
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
@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const refundMethodEl = document.getElementById('refund_method');
    const bankAccountGroupEl = document.getElementById('bank_account_group');
    const bankAccountEl = document.getElementById('bank_account_id');

    function setOptionVisibility(option, visible) {
        option.hidden = !visible;
        option.disabled = !visible;
    }

    function updateBankAccountOptions() {
        if (!refundMethodEl || !bankAccountGroupEl || !bankAccountEl) {
            return;
        }

        const method = refundMethodEl.value;
        const shouldShow = method === 'cash' || method === 'bank_transfer' || method === 'mobile_money';
        bankAccountGroupEl.style.display = shouldShow ? '' : 'none';

        const required = method === 'bank_transfer' || method === 'mobile_money';
        bankAccountEl.required = required;

        const options = Array.from(bankAccountEl.querySelectorAll('option'));
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

        const selected = bankAccountEl.options[bankAccountEl.selectedIndex];
        if (selected && (selected.hidden || selected.disabled)) {
            bankAccountEl.value = '';
        }
    }

    // Refund form
    const refundForm = document.querySelector('form[action*="refund"]');
    if (refundForm) {
        refundForm.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Refund Deposit?',
                text: 'This will refund the selected security deposit to the borrower.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Refund'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    if (refundMethodEl) {
        refundMethodEl.addEventListener('change', updateBankAccountOptions);
        updateBankAccountOptions();
    }

    // Forfeit form
    const forfeitForm = document.querySelector('form[action*="forfeit"]');
    if (forfeitForm) {
        forfeitForm.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Forfeit Deposit?',
                text: 'This will mark the selected security deposit as forfeit income. The borrower will not receive it back.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#6c757d',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Forfeit'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    // Apply form
    const applyForm = document.querySelector('form[action*="apply"]');
    if (applyForm) {
        applyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Apply Deposit?',
                text: 'This will apply the selected security deposit to the chosen loan as a repayment.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Apply'
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
