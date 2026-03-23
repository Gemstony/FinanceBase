@extends('adminlte::page')

@section('title', 'Restructure Loan - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-random"></i> Restructure Loan</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-random"></i> Restructure Loan</h1>
                <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('loans.loans.show', $loan) }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to Loan
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('loans.management') }}">Loans</a></li>
        <li class="breadcrumb-item"><a href="{{ route('loans.loans.show', $loan) }}">{{ $loan->loan_code }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Restructure</li>
    </ol>
</nav>
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

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white">
                    <strong>Loan Summary</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2"><strong>Loan Code:</strong> {{ $loan->loan_code }}</div>
                            <div class="mb-2"><strong>Product:</strong> {{ $loan->loanProduct?->name }}</div>
                            <div class="mb-2"><strong>Status:</strong> <span class="badge badge-secondary">{{ $loan->status }}</span></div>
                            <div class="mb-2"><strong>Borrower Type:</strong> {{ ucfirst($loan->borrower_type) }}</div>
                            <div class="mb-2"><strong>Borrower:</strong>
                                @if($loan->borrower_type === 'group')
                                    {{ $loan->loanGroup?->name }}
                                @else
                                    {{ $loan->customer?->name }}
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2"><strong>Principal:</strong> {{ number_format((float) $loan->principal_amount, 2) }}</div>
                            <div class="mb-2"><strong>Interest Rate:</strong> {{ number_format((float) $loan->interest_rate, 2) }}%</div>
                            <div class="mb-2"><strong>Installments:</strong> {{ (int) $loan->installments }}</div>
                            <div class="mb-2"><strong>Disbursement Date:</strong> {{ $loan->disbursement_date ? \Carbon\Carbon::parse($loan->disbursement_date)->format('Y-m-d') : '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white">
                    <strong>Restructure Request</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('loan.restructures.store', $loan) }}">
                        @csrf

                        <div class="form-group">
                            <label>Restructure Date</label>
                            <input type="date" name="restructure_date" class="form-control" value="{{ old('restructure_date') }}">
                        </div>

                        <div class="form-group">
                            <label>New Interest Rate (%)</label>
                            <input type="number" step="0.0001" name="new_interest_rate" class="form-control" value="{{ old('new_interest_rate', $loan->interest_rate) }}" required>
                        </div>

                        <div class="form-group">
                            <label>New Term (installments)</label>
                            <input type="number" name="new_term" class="form-control" value="{{ old('new_term', $loan->installments) }}" required>
                        </div>

                        <div class="form-group">
                            <label>Grace Period (installments)</label>
                            <input type="number" name="grace_period" class="form-control" value="{{ old('grace_period', 0) }}">
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="capitalize_interest" value="1" class="custom-control-input" id="capInt" {{ old('capitalize_interest') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="capInt">Capitalize outstanding interest into principal</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Capitalized Interest (optional)</label>
                            <input type="number" step="0.01" name="capitalized_interest" class="form-control" value="{{ old('capitalized_interest') }}">
                            <small class="text-muted">If blank, system will use outstanding interest at restructure date.</small>
                        </div>

                        <div class="form-group">
                            <label>Reason</label>
                            <textarea name="reason" rows="4" class="form-control" required>{{ old('reason') }}</textarea>
                        </div>

                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                        <a href="{{ route('loan.restructures.history', $loan) }}" class="btn btn-secondary">
                            <i class="fas fa-history"></i> History
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white">
                    <strong>Notes</strong>
                </div>
                <div class="card-body">
                    <div class="text-muted">
                        This request will preserve all historical installments and create a new schedule version. <br>
                        Restructuring can help loan officers to bypass Loan Product rules during emergence time
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
    document.addEventListener('DOMContentLoaded', function() {
        // SweetAlert confirmation for Submit Request button
        const restructureForm = document.querySelector('form[action*="restructures"]');
        if (restructureForm) {
            restructureForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const newInterestRate = document.querySelector('input[name="new_interest_rate"]').value;
                const newTerm = document.querySelector('input[name="new_term"]').value;
                const gracePeriod = document.querySelector('input[name="grace_period"]').value || '0';
                const reason = document.querySelector('textarea[name="reason"]').value;
                
                Swal.fire({
                    title: 'Confirm Restructure Request',
                    html: `
                        <div class="text-left">
                            <p><strong>New Interest Rate:</strong> ${newInterestRate}%</p>
                            <p><strong>New Term:</strong> ${newTerm} installments</p>
                            <p><strong>Grace Period:</strong> ${gracePeriod} installments</p>
                            <p><strong>Reason:</strong> ${reason.substring(0, 100)}${reason.length > 100 ? '...' : ''}</p>
                        </div>
                        <p class="mt-3">Are you sure you want to submit this restructure request?</p>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Submit Request',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Submitting Request...',
                            text: 'Please wait while we submit your restructure request.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit the form
                        restructureForm.submit();
                    }
                });
            });
        }
    });
</script>
@endpush
