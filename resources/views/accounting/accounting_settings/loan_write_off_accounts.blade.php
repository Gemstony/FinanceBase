@extends('adminlte::page')

@section('title', 'Loan Write-Off Accounts Configuration')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-ban"></i> Loan Write-Off Accounts</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-ban"></i> Write-Off Accounts</h1>
                    <p class="mb-0 text-light">Configure GL accounts for loan write-offs and recoveries</p>
                </div>
                <a href="{{ route('accounting.accounting_settings.index') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('accounting.accounting_settings.index') }}">Accounting Settings</a></li>
                <li class="breadcrumb-item active" aria-current="page">Write-Off Accounts</li>
            </ol>
        </nav>
        <a href="{{ route('accounting.accounting_settings.index') }}" class="btn btn-light border">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="row">
        <!-- Configuration Status Card -->
        <div class="col-md-4">
            <div class="card {{ $config ? 'border-success' : 'border-warning' }}">
                <div class="card-header {{ $config ? 'bg-success' : 'bg-warning' }}">
                    <h3 class="card-title {{ $config ? 'text-white' : '' }}">
                        <i class="fas {{ $config ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i>
                        Configuration Status
                    </h3>
                </div>
                <div class="card-body">
                    @if($config)
                        <div class="alert alert-success">
                            <strong><i class="fas fa-check"></i> Configured</strong><br>
                            <small>Write-off accounts are properly configured for this branch.</small>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted">Write-Off Expense Account</label>
                            <div class="d-flex align-items-center">
                                <span class="badge badge-primary mr-2">{{ $config->writeOffExpenseAccount->account_code ?? 'N/A' }}</span>
                                <span>{{ $config->writeOffExpenseAccount->account_name ?? 'N/A' }}</span>
                            </div>
                            <small class="text-muted">
                                Class: {{ $config->writeOffExpenseAccount->accountClass->class_name ?? 'Expense' }}
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted">Recovery Income Account</label>
                            <div class="d-flex align-items-center">
                                <span class="badge badge-success mr-2">{{ $config->recoveryIncomeAccount->account_code ?? 'N/A' }}</span>
                                <span>{{ $config->recoveryIncomeAccount->account_name ?? 'N/A' }}</span>
                            </div>
                            <small class="text-muted">
                                Class: {{ $config->recoveryIncomeAccount->accountClass->class_name ?? 'Income' }}
                            </small>
                        </div>

                        @if($config->notes)
                            <div class="mb-3">
                                <label class="text-muted">Notes</label>
                                <p class="mb-0">{{ $config->notes }}</p>
                            </div>
                        @endif

                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> Last updated: {{ $config->updated_at->format('Y-m-d H:i') }}
                            </small>
                        </div>

                        <form action="{{ route('accounting.loan-write-off-accounts.destroy') }}" method="POST" class="mt-3">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to remove this configuration? This will prevent loan write-offs until reconfigured.')">
                                <i class="fas fa-trash"></i> Remove Configuration
                            </button>
                        </form>
                    @else
                        <div class="alert alert-warning">
                            <strong><i class="fas fa-exclamation-triangle"></i> Not Configured</strong><br>
                            <small>Loan write-off accounts are not configured for this branch.</small>
                        </div>

                        <div class="alert alert-info">
                            <strong>Impact:</strong><br>
                            <ul class="mb-0 mt-2">
                                <li>Loan write-offs will fail</li>
                                <li>Recovery income cannot be posted</li>
                                <li>No journal entries will be created</li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Accounting Rules Reference -->
            <div class="card mt-3">
                <div class="card-header bg-info">
                    <h3 class="card-title text-white">
                        <i class="fas fa-book"></i> Accounting Rules
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Write-Off Journal Entry:</strong>
                        <div class="mt-2 small">
                            <div class="text-danger">
                                <i class="fas fa-minus-square"></i> Dr. Write-Off Expense (Class 5)<br>
                                <small class="ml-3">Loss recognition for uncollectible amounts</small>
                            </div>
                            <div class="text-success mt-1">
                                <i class="fas fa-plus-square"></i> Cr. Various Receivables<br>
                                <small class="ml-3">Principal, Interest, Fees, Penalties</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <strong>Recovery Journal Entry:</strong>
                        <div class="mt-2 small">
                            <div class="text-success">
                                <i class="fas fa-plus-square"></i> Dr. Cash/Bank<br>
                                <small class="ml-3">Money received</small>
                            </div>
                            <div class="text-success mt-1">
                                <i class="fas fa-minus-square"></i> Cr. Recovery Income (Class 4)<br>
                                <small class="ml-3">Income from recovered write-offs</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-cog"></i>
                        {{ $config ? 'Update Configuration' : 'Configure Write-Off Accounts' }}
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('accounting.loan-write-off-accounts.store') }}">
                        @csrf

                        <div class="row">
                            <!-- Write-Off Expense Account -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="write_off_expense_account_id">
                                        <i class="fas fa-minus-circle text-danger"></i>
                                        Write-Off Expense Account
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control @error('write_off_expense_account_id') is-invalid @enderror"
                                            id="write_off_expense_account_id"
                                            name="write_off_expense_account_id"
                                            required>
                                        <option value="">-- Select Expense Account --</option>
                                        @foreach($expenseAccounts as $account)
                                            <option value="{{ $account->id }}"
                                                {{ old('write_off_expense_account_id', $config?->write_off_expense_account_id) == $account->id ? 'selected' : '' }}>
                                                {{ $account->account_code }} - {{ $account->account_name }}
                                                ({{ $account->accountGroup->group_name ?? 'Expense' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('write_off_expense_account_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">
                                        Must be a Class 5 (Expense) account for recognizing loan losses.
                                    </small>
                                </div>
                            </div>

                            <!-- Recovery Income Account -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="recovery_income_account_id">
                                        <i class="fas fa-plus-circle text-success"></i>
                                        Recovery Income Account
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control @error('recovery_income_account_id') is-invalid @enderror"
                                            id="recovery_income_account_id"
                                            name="recovery_income_account_id"
                                            required>
                                        <option value="">-- Select Income Account --</option>
                                        @foreach($incomeAccounts as $account)
                                            <option value="{{ $account->id }}"
                                                {{ old('recovery_income_account_id', $config?->recovery_income_account_id) == $account->id ? 'selected' : '' }}>
                                                {{ $account->account_code }} - {{ $account->account_name }}
                                                ({{ $account->accountGroup->group_name ?? 'Income' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('recovery_income_account_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">
                                        Must be a Class 4 (Income) account for recovery income.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="form-group">
                            <label for="notes">
                                <i class="fas fa-sticky-note"></i> Notes
                            </label>
                            <textarea class="form-control @error('notes') is-invalid @enderror"
                                      id="notes"
                                      name="notes"
                                      rows="3"
                                      placeholder="Optional notes about this configuration...">{{ old('notes', $config?->notes) }}</textarea>
                            @error('notes')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                {{ $config ? 'Update Configuration' : 'Save Configuration' }}
                            </button>
                            <a href="{{ route('accounting.accounting_settings.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Available Accounts Summary -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list"></i> Available Accounts
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Group</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($expenseAccounts->take(5) as $account)
                                    <tr>
                                        <td><span class="badge badge-primary">{{ $account->account_code }}</span></td>
                                        <td>{{ $account->account_name }}</td>
                                        <td>Expense (5)</td>
                                        <td>{{ $account->accountGroup->group_name ?? '-' }}</td>
                                        <td><span class="badge badge-outline-primary">Expense</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No expense accounts available</td>
                                    </tr>
                                @endforelse
                                @if($expenseAccounts->count() > 5)
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            <small>... and {{ $expenseAccounts->count() - 5 }} more expense accounts</small>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Group</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($incomeAccounts->take(5) as $account)
                                    <tr>
                                        <td><span class="badge badge-success">{{ $account->account_code }}</span></td>
                                        <td>{{ $account->account_name }}</td>
                                        <td>Income (4)</td>
                                        <td>{{ $account->accountGroup->group_name ?? '-' }}</td>
                                        <td><span class="badge badge-outline-success">Income</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No income accounts available</td>
                                    </tr>
                                @endforelse
                                @if($incomeAccounts->count() > 5)
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            <small>... and {{ $incomeAccounts->count() - 5 }} more income accounts</small>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
.badge-outline-primary {
    color: #007bff;
    border: 1px solid #007bff;
    background: transparent;
}
.badge-outline-success {
    color: #28a745;
    border: 1px solid #28a745;
    background: transparent;
}
</style>
@endpush
