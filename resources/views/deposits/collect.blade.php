@extends('adminlte::page')

@section('title', 'Collect Security Deposit')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-plus"></i> Collect Security Deposit</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-plus"></i> Collect Deposit</h1>
                    <p class="mb-0 text-light">Loan: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light border btn-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        {{-- Security Deposit Summary Card --}}
        <div class="card mb-3">
            <div class="card-header bg-light">
                <strong><i class="fas fa-shield-alt"></i> Security Deposit Summary</strong>
                @if($securityDepositRequired > 0)
                    <span class="badge badge-info float-right">{{ number_format($securityDepositRequired, 2) }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($securityDepositRequired > 0)
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center p-2 border rounded">
                                <div class="text-muted small">Status</div>
                                @if($isFullyPaid)
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Fully Paid</span>
                                @elseif($securityDepositPaid > 0)
                                    <span class="badge badge-warning"><i class="fas fa-clock"></i> Partially Paid</span>
                                @else
                                    <span class="badge badge-danger"><i class="fas fa-exclamation-circle"></i> Pending</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-2 border rounded">
                                <div class="text-muted small">Required</div>
                                <strong>{{ number_format($securityDepositRequired, 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-2 border rounded">
                                <div class="text-muted small">Paid</div>
                                <strong class="text-success">{{ number_format($securityDepositPaid, 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-2 border rounded">
                                <div class="text-muted small">Remaining</div>
                                <strong class="text-{{ $pendingDeposit > 0 ? 'warning' : 'success' }}">{{ number_format($pendingDeposit, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> No security deposit required for this loan.
                    </div>
                @endif
            </div>
        </div>

        {{-- Collection Form (only if not fully paid) --}}
        @if($isFullyPaid)
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <strong><i class="fas fa-check-circle"></i> Security Deposit Collected</strong>
                </div>
                <div class="card-body">
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle"></i> Security deposit has been fully collected.
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('security-deposits.loan', $loan) }}" class="btn btn-outline-primary">
                            <i class="fas fa-eye"></i> View Deposits
                        </a>
                        <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Loan
                        </a>
                    </div>
                </div>
            </div>
        @elseif($securityDepositRequired <= 0)
            <div class="card">
                <div class="card-header"><strong>Deposit Collection</strong></div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> This loan does not require a security deposit.
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header"><strong>Collect Security Deposit</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('security-deposits.collect', $loan) }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Amount</label>
                            <input type="number" name="amount" step="0.01" min="0" class="form-control" value="{{ old('amount') }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Payment Method</label>
                            <select name="payment_method" class="form-control" data-security-deposit-payment-method required>
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
                            <label>Payment Bank Account</label>
                            <select name="payment_bank_account_id" class="form-control" data-security-deposit-bank-select>
                                <option value="">-- Select Bank Account --</option>
                                @foreach(($bankAccounts ?? collect()) as $ba)
                                    <option value="{{ $ba->id }}" @selected((string) old('payment_bank_account_id') === (string) $ba->id)>
                                        {{ $ba->account_name }}{{ !empty($ba->account_number) ? ' - ' . $ba->account_number : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Required for Bank Transfer / Mobile Money.</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <label>Notes</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Collect
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const methodSelect = document.querySelector('[data-security-deposit-payment-method]');
    const bankSelect = document.querySelector('[data-security-deposit-bank-select]');
    if (!methodSelect || !bankSelect) return;

    function syncBankRequired() {
        const selectedOption = methodSelect.options[methodSelect.selectedIndex];
        const requiresBank = selectedOption && selectedOption.getAttribute('data-requires-bank') === 'true';
        bankSelect.required = requiresBank;
        if (!requiresBank) {
            bankSelect.value = '';
        }
    }

    methodSelect.addEventListener('change', syncBankRequired);
    syncBankRequired();
});
</script>
@endpush
