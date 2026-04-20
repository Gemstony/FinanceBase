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
                                <i class="fas fa-exclamation-triangle"></i> Please configure the liability account before processing refunds or applying deposits.
                            </div>
                        @endif
                        <button class="btn btn-sm btn-outline-primary btn-block" data-toggle="modal" data-target="#liabilityConfigModal">
                            <i class="fas fa-cog"></i> {{ $liabilityConfigured ? 'Change' : 'Configure' }}
                        </button>
                    </div>
                </div>

                <!-- Forfeiture Income Configuration Status Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Forfeiture Income Account</strong>
                        @if($forfeitureConfigured)
                            <span class="badge badge-success">Configured</span>
                        @else
                            <span class="badge badge-danger">Not Configured</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($forfeitureConfigured)
                            <div class="mb-2">
                                <strong>Account:</strong> {{ $forfeitureConfig->chartOfAccount->account_name }}
                            </div>
                            @if($forfeitureConfig->notes)
                                <div class="text-muted small">{{ $forfeitureConfig->notes }}</div>
                            @endif
                        @else
                            <div class="alert alert-warning mb-2">
                                <i class="fas fa-exclamation-triangle"></i> Please configure the forfeiture income account to process forfeits.
                            </div>
                        @endif
                        <button class="btn btn-sm btn-outline-primary btn-block" data-toggle="modal" data-target="#forfeitureConfigModal">
                            <i class="fas fa-cog"></i> {{ $forfeitureConfigured ? 'Change' : 'Configure' }}
                        </button>
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
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional"></textarea>
                                </div>
                                @if(!$liabilityConfigured)
                                    <div class="alert alert-danger mb-2">
                                        <i class="fas fa-ban"></i> Cannot refund: liability account not configured.
                                    </div>
                                    <button class="btn btn-outline-danger btn-block" type="button" disabled>
                                        <i class="fas fa-ban"></i> Refund Unavailable
                                    </button>
                                @else
                                    <button class="btn btn-outline-danger btn-block" type="submit">
                                        <i class="fas fa-undo"></i> Refund Deposit
                                    </button>
                                @endif
                            </form>

                            <form method="POST" action="{{ route('security-deposits.forfeit') }}" class="mb-3">
                                @csrf
                                <div class="form-group">
                                    <label>Held Deposit</label>
                                    <select name="deposit_id" id="forfeit_deposit_id" class="form-control" required>
                                        <option value="" data-amount="0">Select deposit</option>
                                        @foreach($heldDeposits as $d)
                                            <option value="{{ $d->id }}" data-amount="{{ $d->amount }}">{{ $d->customer?->name ?? '—' }} - {{ number_format((float) $d->amount, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Forfeit Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" id="forfeit_amount" class="form-control" step="0.01" min="0.01" required>
                                    <small class="text-muted">Max: <span id="forfeit_max_amount">0.00</span></small>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional"></textarea>
                                </div>
                                @if(!$forfeitureConfigured || !$liabilityConfigured)
                                    <div class="alert alert-danger mb-2">
                                        <i class="fas fa-ban"></i>
                                        @if(!$forfeitureConfigured && !$liabilityConfigured)
                                            Cannot forfeit: liability and forfeiture accounts not configured.
                                        @elseif(!$forfeitureConfigured)
                                            Cannot forfeit: forfeiture income account not configured.
                                        @else
                                            Cannot forfeit: liability account not configured.
                                        @endif
                                    </div>
                                    <button class="btn btn-outline-dark btn-block" type="button" disabled>
                                        <i class="fas fa-ban"></i> Forfeit Unavailable
                                    </button>
                                @else
                                    <button class="btn btn-outline-dark btn-block" type="submit">
                                        <i class="fas fa-ban"></i> Forfeit Deposit
                                    </button>
                                @endif
                            </form>

                            <form method="POST" action="{{ route('security-deposits.apply') }}">
                                @csrf
                                <div class="form-group">
                                    <label>Held Deposit</label>
                                    <select name="deposit_id" id="apply_deposit_id" class="form-control" required>
                                        <option value="" data-amount="0">Select deposit</option>
                                        @foreach($heldDeposits as $d)
                                            <option value="{{ $d->id }}" data-amount="{{ $d->amount }}">{{ $d->customer?->name ?? '—' }} - {{ number_format((float) $d->amount, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Apply Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" id="apply_amount" class="form-control" step="0.01" min="0.01" required>
                                    <small class="text-muted">Max: <span id="apply_max_amount">0.00</span></small>
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
                        <table class="table table-striped table-hover" id="depositHistoryTable">
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

    <!-- Liability Configuration Modal -->
<div class="modal fade" id="liabilityConfigModal" tabindex="-1" role="dialog" aria-labelledby="liabilityConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('security-deposits.liability-account.configure') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="liabilityConfigModalLabel">
                        <i class="fas fa-cog"></i> Configure Security Deposit Liability Account
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Select a liability account (Class 2) to use for security deposits.
                    </div>
                    <div class="form-group">
                        <label>Liability Account <span class="text-danger">*</span></label>
                        <select name="chart_of_account_id" class="form-control" required>
                            <option value="">Select Liability Account</option>
                            @php
                                // Use shop-level scoping for liability accounts (all subshops under same shop)
                                $subshopId = session('subshop_id');
                                $subshop = \App\Models\SubShop::find($subshopId);
                                $shopId = $subshop?->shop_id;
                                $shopSubshopIds = \App\Models\SubShop::where('shop_id', $shopId)->pluck('id');

                                $liabilityAccounts = \App\Models\ChartsOfAccount::with('accountClass')
                                    ->whereIn('subshop_id', $shopSubshopIds)
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
                        <small class="form-text text-muted">Showing liability accounts from all branches in this shop.</small>
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

<!-- Forfeiture Income Configuration Modal -->
<div class="modal fade" id="forfeitureConfigModal" tabindex="-1" role="dialog" aria-labelledby="forfeitureConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('security-deposits.forfeiture-account.configure') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="forfeitureConfigModalLabel">
                        <i class="fas fa-cog"></i> Configure Forfeiture Income Account
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Select a income account (Class 4) for security deposit forfeiture income.
                    </div>
                    <div class="form-group">
                        <label>Income Account <span class="text-danger">*</span></label>
                        <select name="chart_of_account_id" class="form-control" required>
                            <option value="">Select Income Account</option>
                            @php
                                // Use shop-level scoping for income accounts (all subshops under same shop)
                                $subshopId = session('subshop_id');
                                $subshop = \App\Models\SubShop::find($subshopId);
                                $shopId = $subshop?->shop_id;
                                $shopSubshopIds = \App\Models\SubShop::where('shop_id', $shopId)->pluck('id');

                                $incomeAccounts = \App\Models\ChartsOfAccount::with('accountClass')
                                    ->whereIn('subshop_id', $shopSubshopIds)
                                    ->where('is_active', true)
                                    ->get()
                                    ->filter(fn($acc) => in_array((int)($acc->accountClass?->code ?? 0), [4, 5]));
                            @endphp
                            @foreach($incomeAccounts as $acc)
                                <option value="{{ $acc->id }}" @selected(($forfeitureConfig?->chart_of_account_id) == $acc->id)>
                                    {{ $acc->account_name }} ({{ $acc->account_code }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Showing income accounts (Class 4 & 5) from all branches in this shop.</small>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ $forfeitureConfig?->notes }}</textarea>
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

    // Forfeit amount handling
    const forfeitDepositEl = document.getElementById('forfeit_deposit_id');
    const forfeitAmountEl = document.getElementById('forfeit_amount');
    const forfeitMaxEl = document.getElementById('forfeit_max_amount');

    if (forfeitDepositEl && forfeitAmountEl) {
        forfeitDepositEl.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const maxAmount = parseFloat(selected.dataset.amount || 0);
            forfeitMaxEl.textContent = maxAmount.toFixed(2);
            forfeitAmountEl.max = maxAmount;
            if (parseFloat(forfeitAmountEl.value) > maxAmount) {
                forfeitAmountEl.value = maxAmount.toFixed(2);
            }
        });
    }

    // Apply amount handling
    const applyDepositEl = document.getElementById('apply_deposit_id');
    const applyAmountEl = document.getElementById('apply_amount');
    const applyMaxEl = document.getElementById('apply_max_amount');

    if (applyDepositEl && applyAmountEl) {
        applyDepositEl.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const maxAmount = parseFloat(selected.dataset.amount || 0);
            applyMaxEl.textContent = maxAmount.toFixed(2);
            applyAmountEl.max = maxAmount;
            if (parseFloat(applyAmountEl.value) > maxAmount) {
                applyAmountEl.value = maxAmount.toFixed(2);
            }
        });
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


//table

$(document).ready(function() {
    if ($('#depositHistoryTable').length) {
        $('#depositHistoryTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [5] },
                { searchable: false, targets: [5] }
            ],
        });
    }
});
</script>
@endpush
