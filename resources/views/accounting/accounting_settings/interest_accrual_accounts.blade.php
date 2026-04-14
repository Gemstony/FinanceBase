@extends('adminlte::page')

@section('title', 'Interest Accrual Accounts')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-percentage"></i> Interest Accrual Accounts</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-percentage"></i> Interest Accrual Accounts</h1>
                <p class="mb-0 text-light">Configure accounts for daily interest accrual journal entries</p>
            </div>
            <a href="{{ route('accounting.accounting_settings.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('accounting.accounting_settings.index') }}">Accounting settings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Interest Accrual Accounts</li>
        </ol>
    </nav>
</div>
@stop

@section('content')
<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle"></i> Error</h5>
            <p class="mb-0">{{ session('error') }}</p>
        </div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">
            <h5><i class="fas fa-check-circle"></i> Success</h5>
            <p class="mb-0">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Configuration Status Card -->
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-cog"></i> Configuration Status
                    </h3>
                    @if($isConfigured)
                        <span class="badge badge-success">Configured</span>
                    @else
                        <span class="badge badge-danger">Not Configured</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($isConfigured)
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong>Interest Receivable Account (Asset)</strong></label>
                                    <div class="p-2 bg-light rounded">
                                        {{ $config->interestReceivableAccount->account_code }} - {{ $config->interestReceivableAccount->account_name }}
                                        <br>
                                        <small class="text-muted">Class: {{ $config->interestReceivableAccount->accountClass->name ?? 'N/A' }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong>Interest Income Account (Revenue)</strong></label>
                                    <div class="p-2 bg-light rounded">
                                        {{ $config->interestIncomeAccount->account_code }} - {{ $config->interestIncomeAccount->account_name }}
                                        <br>
                                        <small class="text-muted">Class: {{ $config->interestIncomeAccount->accountClass->name ?? 'N/A' }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if($config->notes)
                            <div class="form-group">
                                <label><strong>Notes</strong></label>
                                <p class="text-muted">{{ $config->notes }}</p>
                            </div>
                        @endif
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Daily interest accrual will post journal entries using these accounts.
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#editConfigModal">
                                <i class="fas fa-edit"></i> Edit Configuration
                            </button>
                            <form method="POST" action="{{ route('accounting.interest-accrual-accounts.destroy') }}" onsubmit="return confirm('Are you sure you want to remove this configuration?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Remove Configuration
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Not Configured</strong><br>
                            Interest accrual accounts are not configured for this branch. Please configure them before running the interest accrual command.
                        </div>
                        <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#editConfigModal">
                            <i class="fas fa-plus"></i> Configure Accounts
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit/Create Configuration Modal -->
<div class="modal fade" id="editConfigModal" tabindex="-1" role="dialog" aria-labelledby="editConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('accounting.interest-accrual-accounts.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="editConfigModalLabel">
                        <i class="fas fa-cog"></i> {{ $isConfigured ? 'Edit' : 'Configure' }} Interest Accrual Accounts
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Select the accounts to use for daily interest accrual journal entries.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="interest_receivable_account_id">
                                    Interest Receivable Account <span class="text-danger">*</span>
                                </label>
                                <select name="interest_receivable_account_id" id="interest_receivable_account_id" class="form-control" required>
                                    <option value="">Select Asset Account (Class 1)</option>
                                    @foreach($assetAccounts as $acc)
                                        <option value="{{ $acc->id }}" @selected($isConfigured && $config->interest_receivable_account_id == $acc->id)>
                                            {{ $acc->account_code }} - {{ $acc->account_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    This account will be debited for accrued interest.
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="interest_income_account_id">
                                    Interest Income Account <span class="text-danger">*</span>
                                </label>
                                <select name="interest_income_account_id" id="interest_income_account_id" class="form-control" required>
                                    <option value="">Select Revenue Account (Class 4)</option>
                                    @foreach($revenueAccounts as $acc)
                                        <option value="{{ $acc->id }}" @selected($isConfigured && $config->interest_income_account_id == $acc->id)>
                                            {{ $acc->account_code }} - {{ $acc->account_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    This account will be credited for interest income.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3">{{ $config->notes ?? '' }}</textarea>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important:</strong> Ensure these accounts are correctly set up in your Chart of Accounts. Incorrect configuration will cause journal entry posting failures during interest accrual.
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

<style>
    .bg-light {
        background-color: #f8f9fa !important;
    }
</style>
@endpush
