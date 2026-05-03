@extends('adminlte::page')

@section('title', 'Forgive Penalty - ' . $loan->loan_code)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-heart"></i> Forgive Penalty</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-heart"></i> Forgive Penalty</h1>
                    <p class="mb-0 text-light">Loan Code: <strong>{{ $loan->loan_code }}</strong></p>
                </div>
                <a href="{{ route('loan.penalties.pay.form', $loan) }}" class="btn btn-light btn-sm border">
                    <i class="fas fa-arrow-left"></i> Back to Penalties
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
                <li class="breadcrumb-item"><a href="{{ route('loan.penalties.pay.form', $loan) }}">Penalties</a></li>
                <li class="breadcrumb-item active" aria-current="page">Forgive</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header bg-gradient-info">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i> Penalty Forgiveness
                        </h3>
                    </div>
                    <div class="card-body">
                        <!-- Penalty Details -->
                        <div class="alert alert-info mb-4">
                            <h5><i class="fas fa-file-invoice"></i> Penalty Details</h5>
                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td>{{ $penalty->loanProductPenalty?->loanPenalty?->name ?? 'Penalty' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Date Applied:</strong></td>
                                    <td>{{ $penalty->applied_on->format('Y-m-d') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Original Amount:</strong></td>
                                    <td>{{ number_format($penalty->amount, 2) }}</td>
                                </tr>
                                @if($penalty->paid_amount > 0)
                                    <tr>
                                        <td><strong>Already Paid:</strong></td>
                                        <td class="text-success">{{ number_format($penalty->paid_amount, 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td><strong>Outstanding:</strong></td>
                                    <td class="text-danger font-weight-bold">{{ number_format($penalty->getOutstandingAmount(), 2) }}</td>
                                </tr>
                            </table>
                        </div>

                        @if($penalty->getOutstandingAmount() <= 0)
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> This penalty has already been fully settled.
                            </div>
                            <a href="{{ route('loan.penalties.pay.form', $loan) }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Penalties
                            </a>
                        @else
                            <form id="forgiveForm" action="{{ route('loan.penalties.forgive', [$loan, $penalty->id]) }}" method="POST">
                                @csrf

                                <!-- Forgiveness Amount -->
                                <div class="form-group">
                                    <label for="amount">Forgiveness Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">{{ config('app.currency', 'TZS') }}</span>
                                        </div>
                                        <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror"
                                               step="0.01" min="0.01" max="{{ $penalty->getOutstandingAmount() }}"
                                               value="{{ old('amount', $penalty->getOutstandingAmount()) }}" required>
                                    </div>
                                    <small class="form-text text-muted">
                                        Maximum: {{ number_format($penalty->getOutstandingAmount(), 2) }}
                                    </small>
                                    @error('amount')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Forgiveness Reason -->
                                <div class="form-group">
                                    <label for="reason">Forgiveness Reason <span class="text-danger">*</span></label>
                                    <textarea name="reason" id="reason" class="form-control @error('reason') is-invalid @enderror"
                                              rows="4" minlength="10" maxlength="500" required
                                              placeholder="Please provide a detailed reason for forgiving this penalty (minimum 10 characters)...">{{ old('reason') }}</textarea>
                                    <small class="form-text text-muted">
                                        This reason will be recorded for audit purposes.
                                    </small>
                                    @error('reason')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Warning Box -->
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Important:</strong> Penalty forgiveness is a serious action that reduces revenue. 
                                    Please ensure you have proper authorization before proceeding.
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-hand-holding-heart"></i> Confirm Forgiveness
                                    </button>
                                    <a href="{{ route('loan.penalties.pay.form', $loan) }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('forgiveForm');
            const amountInput = document.getElementById('amount');
            const reasonInput = document.getElementById('reason');

            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const amount = parseFloat(amountInput.value).toFixed(2);
                    const maxAmount = parseFloat(amountInput.max).toFixed(2);
                    const reason = reasonInput.value;

                    if (reason.length < 10) {
                        Swal.fire({
                            title: 'Invalid Reason',
                            text: 'Please provide a detailed reason (minimum 10 characters).',
                            icon: 'warning',
                            confirmButtonColor: '#ffc107'
                        });
                        return;
                    }

                    Swal.fire({
                        title: 'Confirm Penalty Forgiveness',
                        html: `
                            <div class="text-left">
                                <p><strong>Amount to Forgive:</strong> ${parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                <p><strong>Reason:</strong></p>
                                <p class="text-muted">${reason}</p>
                            </div>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                This action cannot be undone. The penalty will be marked as forgiven.
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#17a2b8',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Forgive Penalty',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Processing...',
                                text: 'Please wait while we process the forgiveness.',
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
