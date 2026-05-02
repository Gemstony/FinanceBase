@extends('adminlte::page')

@section('title', 'Pay Fees - ' . $loan->loan_code)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-file-invoice-dollar"></i> Pay Individual Fees</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-file-invoice-dollar"></i> Pay Individual Fees</h1>
                    <p class="mb-0 text-light">Loan: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light btn-sm">
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
                <li class="breadcrumb-item active" aria-current="page">Pay Fees</li>
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
        <div class="col-md-8">
            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong><i class="fas fa-file-invoice-dollar"></i> Pending Fees</strong>
                    @if($pendingTotal > 0)
                        <span class="badge badge-warning">{{ number_format((float)$pendingTotal, 2) }} Pending</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($pendingFees->isEmpty())
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> All fees have been paid for this loan.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Fee Name</th>
                                        <th>Amount Due</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingFees as $fee)
                                        <tr>
                                            <td>{{ $fee->loanProductFee?->loanFee?->name ?? 'Fee' }}</td>
                                            <td>{{ number_format((float)$fee->amount, 2) }}</td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#payFeeModal{{ $fee->id }}">
                                                    <i class="fas fa-money-bill-wave"></i> Pay
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white">
                    <strong><i class="fas fa-info-circle"></i> Loan Summary</strong>
                </div>
                <div class="card-body">
                    <div class="mb-2"><strong>Loan Code:</strong> {{ $loan->loan_code }}</div>
                    <div class="mb-2"><strong>{{ $loan->borrower_type === 'group' ? 'Group' : 'Customer' }}:</strong> {{ $loan->borrower_type === 'group' ? ($loan->loanGroup?->name ?? '-') : ($loan->customer?->name ?? '-') }}</div>
                    <div class="mb-2"><strong>Amount:</strong> {{ number_format((float)$loan->principal_amount, 2) }}</div>
                    <div class="mb-2"><strong>Status:</strong>
                        @php
                            $statusBadgeClass = match($loan->status) {
                                'pending' => 'badge-warning',
                                'approved' => 'badge-info',
                                'disbursed' => 'badge-success',
                                'partially_paid' => 'badge-primary',
                                'paid' => 'badge-success',
                                'defaulted' => 'badge-danger',
                                'written_off' => 'badge-secondary',
                                'restructured' => 'badge-dark',
                                default => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $statusBadgeClass }}">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span>
                    </div>
                    <hr>
                    <a href="{{ route('loans.fees.payment-history', $loan) }}" class="btn btn-sm btn-outline-info btn-block">
                        <i class="fas fa-history"></i> View Payment History
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Payment Modals --}}
@foreach($pendingFees as $fee)
<div class="modal fade" id="payFeeModal{{ $fee->id }}" tabindex="-1" role="dialog" aria-labelledby="payFeeModalLabel{{ $fee->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('loans.fees.pay', $loan) }}">
                @csrf
                <input type="hidden" name="fee_application_id" value="{{ $fee->id }}">
                <input type="hidden" name="amount" value="{{ $fee->amount }}">

                <div class="modal-header">
                    <h5 class="modal-title" id="payFeeModalLabel{{ $fee->id }}">
                        Pay Fee: {{ $fee->loanProductFee?->loanFee?->name ?? 'Fee' }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Amount to Pay:</strong> {{ number_format((float)$fee->amount, 2) }}
                    </div>

                    <div class="form-group">
                        <label>Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-control fee-payment-method" data-fee-id="{{ $fee->id }}" required>
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

                    <div class="form-group fee-bank-account-group" data-fee-id="{{ $fee->id }}">
                        <label>Bank Account <span class="text-danger">*</span></label>
                        <select name="payment_bank_account_id" class="form-control">
                            <option value="">-- Select Bank Account --</option>
                            @foreach($bankAccounts as $ba)
                                <option value="{{ $ba->id }}">
                                    {{ $ba->account_name }}{{ !empty($ba->account_number) ? ' - ' . $ba->account_number : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Required for Bank Transfer / Mobile Money.</small>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional payment reference">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

@stop

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle payment method change for fee modals
        document.querySelectorAll('.fee-payment-method').forEach(function(select) {
            select.addEventListener('change', function() {
                var feeId = this.getAttribute('data-fee-id');
                var requiresBank = this.options[this.selectedIndex].getAttribute('data-requires-bank') === 'true';
                var bankGroup = document.querySelector('.fee-bank-account-group[data-fee-id="' + feeId + '"]');
                var bankSelect = bankGroup ? bankGroup.querySelector('select') : null;

                if (bankGroup) {
                    if (requiresBank) {
                        bankGroup.style.display = 'block';
                        if (bankSelect) bankSelect.required = true;
                    } else {
                        bankGroup.style.display = 'none';
                        if (bankSelect) {
                            bankSelect.required = false;
                            bankSelect.value = '';
                        }
                    }
                }
            });

            // Trigger change on load
            select.dispatchEvent(new Event('change'));
        });
    });
</script>
@endpush
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

