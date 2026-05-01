@extends('adminlte::page')

@section('title', 'Payment Methods & GL Mappings - Shop Level')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-credit-card"></i> Payment Methods & GL Mappings</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-credit-card"></i> Payment Methods & GL Mappings</h1>
                <p class="mb-0 text-light">Configure payment methods and map them to chart of accounts for shop: <strong>{{ $shop->name ?? 'N/A' }}</strong></p>
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
            <li class="breadcrumb-item active" aria-current="page">Payment Methods & GL Mappings</li>
        </ol>
    </nav>
    <div class="btn-group">
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#createPaymentMethodModal">
            <i class="fas fa-plus"></i> Create Payment Method
        </button>
    </div>
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

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="paymentMethodsTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="payment-methods-tab" data-toggle="tab" href="#payment-methods" role="tab" aria-controls="payment-methods" aria-selected="true">
                <i class="fas fa-list"></i> Payment Methods
                <span class="badge badge-primary ml-1">{{ $paymentMethods->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="gl-mappings-tab" data-toggle="tab" href="#gl-mappings" role="tab" aria-controls="gl-mappings" aria-selected="false">
                <i class="fas fa-link"></i> GL Account Mappings
                <span class="badge badge-info ml-1">{{ $paymentMethodAccounts->count() }}</span>
            </a>
        </li>
    </ul>

    <div class="tab-content" id="paymentMethodsTabsContent">
        <!-- Payment Methods Tab -->
        <div class="tab-pane fade show active" id="payment-methods" role="tabpanel" aria-labelledby="payment-methods-tab">
            <div class="card mt-3">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-cog"></i> Available Payment Methods</strong>
                    <small class="text-muted float-right">Create and manage payment method types used across the system</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover" id="paymentMethodsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Account Type</th>
                                    <th>Usage</th>
                                    <th>Status</th>
                                    <th>GL Mapping</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($paymentMethods as $index => $pm)
                                    @php
                                        $mapping = $paymentMethodAccounts->firstWhere('payment_method', $pm->code);
                                        $allAccounts = $assetsAccounts->concat($liabilityAccounts);
                                        $acc = $mapping ? $allAccounts->firstWhere('id', $mapping->chart_of_account_id) : null;
                                        $accLabel = $acc ? (($acc->account_code ?? '') . ' - ' . ($acc->account_name ?? '')) : '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Not Mapped</span>';
                                    @endphp
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><strong>{{ $pm->name }}</strong></td>
                                        <td><code>{{ $pm->code }}</code></td>
                                        <td>
                                            <span class="badge badge-{{ $pm->account_type === 'liability' ? 'warning' : 'success' }}">
                                                {{ ucfirst($pm->account_type ?? 'asset') }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($pm->is_repayment_method)<span class="badge badge-info mr-1">Repayment</span>@endif
                                            @if($pm->is_deposit_method)<span class="badge badge-secondary mr-1">Deposit</span>@endif
                                            @if($pm->is_refund_method)<span class="badge badge-dark mr-1">Refund</span>@endif
                                            @if($pm->is_withdrawal_method)<span class="badge badge-primary">Withdrawal</span>@endif
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $pm->status ? 'success' : 'danger' }}">
                                                {{ $pm->status ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{!! $accLabel !!}</td>
                                        <td>
                                            <button class="btn btn-sm btn-info edit-pm-btn"
                                                    data-id="{{ $pm->id }}"
                                                    data-name="{{ $pm->name }}"
                                                    data-code="{{ $pm->code }}"
                                                    data-description="{{ $pm->description }}"
                                                    data-status="{{ $pm->status }}"
                                                    data-account-type="{{ $pm->account_type }}"
                                                    data-requires-bank="{{ $pm->requires_bank_account }}"
                                                    data-requires-phone="{{ $pm->requires_phone }}"
                                                    data-is-repayment="{{ $pm->is_repayment_method }}"
                                                    data-is-deposit="{{ $pm->is_deposit_method }}"
                                                    data-is-refund="{{ $pm->is_refund_method }}"
                                                    data-is-withdrawal="{{ $pm->is_withdrawal_method }}"
                                                    data-sort-order="{{ $pm->sort_order }}"
                                                    title="Edit Payment Method">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @if(!$mapping && $pm->payments()->count() === 0)
                                                <button class="btn btn-sm btn-danger delete-pm-btn"
                                                        data-id="{{ $pm->id }}"
                                                        data-name="{{ $pm->name }}"
                                                        title="Delete Payment Method">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-danger" disabled title="Cannot delete: In use or has mapping">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                            @if(!$mapping)
                                                <button class="btn btn-sm btn-primary map-pm-btn"
                                                        data-code="{{ $pm->code }}"
                                                        data-name="{{ $pm->name }}"
                                                        data-account-type="{{ $pm->account_type }}"
                                                        title="Map to GL Account">
                                                    <i class="fas fa-link"></i> Map
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-warning edit-mapping-btn"
                                                        data-id="{{ $mapping->id }}"
                                                        data-payment-method="{{ $mapping->payment_method }}"
                                                        data-chart-of-account-id="{{ $mapping->chart_of_account_id }}"
                                                        data-account-type="{{ $pm->account_type }}"
                                                        title="Edit GL Mapping">
                                                    <i class="fas fa-edit"></i> Map
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <div class="py-4">
                                                <i class="fas fa-credit-card fa-2x text-muted mb-3"></i>
                                                <p>No payment methods found. Click "Create Payment Method" to add one.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- GL Mappings Tab -->
        <div class="tab-pane fade" id="gl-mappings" role="tabpanel" aria-labelledby="gl-mappings-tab">
            <div class="card mt-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-link"></i> Payment Method to GL Account Mappings</strong>
                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addPaymentMethodAccountModal">
                        <i class="fas fa-plus"></i> New Mapping
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>About GL Mappings:</strong> Each payment method must be mapped to a Chart of Account for proper accounting entries.
                        <ul class="mb-0 mt-2">
                            <li><strong>Asset accounts (1.xxx)</strong> - Used for cash, bank, mobile money (debit when receiving)</li>
                            <li><strong>Liability accounts (2.xxx)</strong> - Used for customer deposits, savings, credit (credit when receiving)</li>
                        </ul>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover" id="paymentMethodAccountsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Payment Method</th>
                                    <th>Account Type</th>
                                    <th>Mapped GL Account</th>
                                    <th>Account Class</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($paymentMethodAccounts as $index => $mapping)
                                    @php
                                        $allAccounts = $assetsAccounts->concat($liabilityAccounts);
                                        $acc = $allAccounts->firstWhere('id', $mapping->chart_of_account_id);
                                        $accLabel = $acc ? (($acc->account_code ?? '') . ' - ' . ($acc->account_name ?? '')) : 'N/A';
                                        $accClass = $acc ? ($acc->accountClass->name ?? 'N/A') : 'N/A';
                                        $pmInfo = $paymentMethods->firstWhere('code', $mapping->payment_method);
                                        $pmLabel = $pmInfo->name ?? $mapping->payment_method;
                                        $pmAccountType = $pmInfo->account_type ?? 'asset';
                                    @endphp
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><span class="badge badge-info">{{ $pmLabel }}</span></td>
                                        <td>
                                            <span class="badge badge-{{ $pmAccountType === 'liability' ? 'warning' : 'success' }}">
                                                {{ ucfirst($pmAccountType) }}
                                            </span>
                                        </td>
                                        <td>{{ $accLabel }}</td>
                                        <td>{{ $accClass }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-btn"
                                                    data-id="{{ $mapping->id }}"
                                                    data-payment-method="{{ $mapping->payment_method }}"
                                                    data-chart-of-account-id="{{ $mapping->chart_of_account_id }}"
                                                    data-account-type="{{ $pmAccountType }}"
                                                    title="Edit Mapping">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $mapping->id }}" title="Delete Mapping">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <div class="py-4">
                                                <i class="fas fa-link fa-2x text-muted mb-3"></i>
                                                <p>No GL mappings found. Click "New Mapping" to configure.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Payment Method Modal -->
<div class="modal fade" id="createPaymentMethodModal" tabindex="-1" role="dialog" aria-labelledby="createPaymentMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('payment-methods.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="createPaymentMethodModalLabel"><i class="fas fa-plus"></i> Create Payment Method</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="pm_name">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="pm_name" name="name" required placeholder="e.g., Cash, Bank Transfer, Mobile Money">
                                <small class="text-muted">Display name for the payment method</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="pm_code">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="pm_code" name="code" required placeholder="e.g., cash, bank, mobile_money">
                                <small class="text-muted">Unique lowercase code, no spaces (used in system)</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pm_description">Description</label>
                        <textarea class="form-control" id="pm_description" name="description" rows="2" placeholder="Optional description..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="pm_account_type">Account Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="pm_account_type" name="account_type" required>
                                    <option value="asset" selected>Asset (Cash, Bank, Mobile Money)</option>
                                    <option value="liability">Liability (Deposits, Credit, Savings)</option>
                                </select>
                                <small class="text-muted">Determines which GL accounts can be mapped</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="pm_status">Status</label>
                                <select class="form-control" id="pm_status" name="status">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="pm_sort_order">Sort Order</label>
                                <input type="number" class="form-control" id="pm_sort_order" name="sort_order" value="0" min="0">
                                <small class="text-muted">Display order (0 = first)</small>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <label>Usage Contexts</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pm_is_repayment" name="is_repayment_method" value="1" checked>
                                <label class="form-check-label" for="pm_is_repayment">Available for Loan Repayments</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pm_is_deposit" name="is_deposit_method" value="1" checked>
                                <label class="form-check-label" for="pm_is_deposit">Available for Deposits</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pm_is_refund" name="is_refund_method" value="1" checked>
                                <label class="form-check-label" for="pm_is_refund">Available for Refunds</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pm_is_withdrawal" name="is_withdrawal_method" value="1" checked>
                                <label class="form-check-label" for="pm_is_withdrawal">Available for Withdrawals</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label>Requirements</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pm_requires_bank" name="requires_bank_account" value="1">
                                <label class="form-check-label" for="pm_requires_bank">Requires Bank Account Selection</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pm_requires_phone" name="requires_phone" value="1">
                                <label class="form-check-label" for="pm_requires_phone">Requires Phone Number (Mobile Money)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Create Payment Method</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Payment Method Modal -->
<div class="modal fade" id="editPaymentMethodModal" tabindex="-1" role="dialog" aria-labelledby="editPaymentMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="editPaymentMethodForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="editPaymentMethodModalLabel"><i class="fas fa-edit"></i> Edit Payment Method</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_pm_name">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_pm_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_pm_code">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_pm_code" name="code" required>
                                <small class="text-muted">Changing code may affect existing transactions</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_pm_description">Description</label>
                        <textarea class="form-control" id="edit_pm_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_pm_account_type">Account Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="edit_pm_account_type" name="account_type" required>
                                    <option value="asset">Asset (Cash, Bank, Mobile Money)</option>
                                    <option value="liability">Liability (Deposits, Credit, Savings)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_pm_status">Status</label>
                                <select class="form-control" id="edit_pm_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_pm_sort_order">Sort Order</label>
                                <input type="number" class="form-control" id="edit_pm_sort_order" name="sort_order" min="0">
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <label>Usage Contexts</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_pm_is_repayment" name="is_repayment_method" value="1">
                                <label class="form-check-label" for="edit_pm_is_repayment">Available for Loan Repayments</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_pm_is_deposit" name="is_deposit_method" value="1">
                                <label class="form-check-label" for="edit_pm_is_deposit">Available for Deposits</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_pm_is_refund" name="is_refund_method" value="1">
                                <label class="form-check-label" for="edit_pm_is_refund">Available for Refunds</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_pm_is_withdrawal" name="is_withdrawal_method" value="1">
                                <label class="form-check-label" for="edit_pm_is_withdrawal">Available for Withdrawals</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label>Requirements</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_pm_requires_bank" name="requires_bank_account" value="1">
                                <label class="form-check-label" for="edit_pm_requires_bank">Requires Bank Account Selection</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_pm_requires_phone" name="requires_phone" value="1">
                                <label class="form-check-label" for="edit_pm_requires_phone">Requires Phone Number (Mobile Money)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-save"></i> Update Payment Method</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Mapping Modal -->
<div class="modal fade" id="addPaymentMethodAccountModal" tabindex="-1" role="dialog" aria-labelledby="addPaymentMethodAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addPaymentMethodAccountForm" action="{{ route('accounting.payment_method_accounts.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addPaymentMethodAccountModalLabel">Add Payment Method Mapping</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="" disabled selected>-- Select Payment Method --</option>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->code }}" data-account-type="{{ $pm->account_type ?? 'asset' }}">
                                    {{ $pm->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Select a payment method to map to a GL account.</small>
                    </div>

                    <div class="form-group">
                        <label for="chart_of_account_id">GL Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="chart_of_account_id" name="chart_of_account_id" required>
                            <option value="" disabled selected>-- Select GL Account --</option>
                            @foreach($assetsAccounts as $account)
                                <option value="{{ $account->id }}" data-account-class-code="1">
                                    {{ $account->account_code ?? '' }} - {{ $account->account_name ?? '' }} (Class: {{ $account->accountClass->name }})
                                </option>
                            @endforeach
                            @foreach($liabilityAccounts as $account)
                                <option value="{{ $account->id }}" data-account-class-code="2">
                                    {{ $account->account_code ?? '' }} - {{ $account->account_name ?? '' }}  (Class: {{ $account->accountClass->name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Mapping Modal -->
<div class="modal fade" id="editPaymentMethodAccountModal" tabindex="-1" role="dialog" aria-labelledby="editPaymentMethodAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editPaymentMethodAccountForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editPaymentMethodAccountModalLabel">Edit Payment Method Mapping</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_payment_method">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_payment_method" name="payment_method" required>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->code }}" data-account-type="{{ $pm->account_type ?? 'asset' }}">
                                    {{ $pm->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_chart_of_account_id">GL Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_chart_of_account_id" name="chart_of_account_id" required>
                            <option value="" disabled selected>-- Select GL Account --</option>
                            @foreach($assetsAccounts as $account)
                                <option value="{{ $account->id }}" data-account-class-code="1">
                                    {{ $account->account_code ?? '' }} - {{ $account->account_name ?? '' }} (Class: {{ $account->accountClass->name }})
                                </option>
                            @endforeach
                            @foreach($liabilityAccounts as $account)
                                <option value="{{ $account->id }}" data-account-class-code="2">
                                    {{ $account->account_code ?? '' }} - {{ $account->account_name ?? '' }} (Class: {{ $account->accountClass->name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">

<style>
    .table th, .table td {
        vertical-align: middle;
    }
    .nav-tabs .nav-link {
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        background-color: #f8f9fa;
        border-bottom-color: #f8f9fa;
    }
    .tab-content {
        background-color: #f8f9fa;
        margin-top: -1px;
    }
    .form-check-label {
        font-size: 0.9rem;
        margin-left: 0.25rem;
    }
    .form-check-input {
        margin-top: 0.2rem;
    }
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable for Payment Methods
    if ($('#paymentMethodsTable').length) {
        $('#paymentMethodsTable').DataTable({
            responsive: true,
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: [0, 7] },
                { searchable: false, targets: [0] }
            ],
            order: [[1, 'asc']]
        });
    }

    // Initialize DataTable for GL Mappings
    if ($('#paymentMethodAccountsTable').length) {
        $('#paymentMethodAccountsTable').DataTable({
            responsive: true,
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: [0, 5] },
                { searchable: false, targets: [0] }
            ],
            order: [[2, 'asc']]
        });
    }

    function requiredAccountClassCodeForPaymentMethod(pm) {
        pm = String(pm || '').toLowerCase().trim();
        // Get the selected option's account type
        const pmSelect = document.getElementById('payment_method') || document.getElementById('edit_payment_method');
        if (pmSelect) {
            const selectedOption = pmSelect.options[pmSelect.selectedIndex];
            if (selectedOption) {
                const accountType = selectedOption.getAttribute('data-account-type');
                if (accountType === 'liability') return '2';
            }
        }
        // Legacy fallback
        if (pm === 'savings' || pm === 'customer_credit') return '2';
        return '1';
    }

    function filterAccountOptions(selectEl, expectedCode) {
        if (!selectEl) return;

        if (!selectEl._allOptions) {
            selectEl._allOptions = Array.from(selectEl.querySelectorAll('option')).map(o => o.cloneNode(true));
        }

        const currentValue = String(selectEl.value || '');
        selectEl.innerHTML = '';

        selectEl._allOptions.forEach(opt => {
            const classCode = opt.getAttribute('data-account-class-code');
            if (!classCode) {
                selectEl.appendChild(opt.cloneNode(true));
                return;
            }
            if (String(classCode) === String(expectedCode)) {
                selectEl.appendChild(opt.cloneNode(true));
            }
        });

        if (currentValue) {
            const stillExists = !!selectEl.querySelector(`option[value="${currentValue}"]`);
            selectEl.value = stillExists ? currentValue : '';
        }
    }

    function setupAccountFiltering(paymentMethodSelectId, coaSelectId) {
        const pmEl = document.getElementById(paymentMethodSelectId);
        const coaEl = document.getElementById(coaSelectId);
        if (!pmEl || !coaEl) return;

        const apply = () => {
            const expected = requiredAccountClassCodeForPaymentMethod(pmEl.value);
            filterAccountOptions(coaEl, expected);
        };

        $(pmEl).on('change', apply);
        apply();
    }

    // Setup account filtering for mapping modals
    setupAccountFiltering('payment_method', 'chart_of_account_id');
    setupAccountFiltering('edit_payment_method', 'edit_chart_of_account_id');

    $('#addPaymentMethodAccountModal').on('shown.bs.modal', function() {
        const pmEl = document.getElementById('payment_method');
        const coaEl = document.getElementById('chart_of_account_id');
        if (!pmEl || !coaEl) return;
        const expected = requiredAccountClassCodeForPaymentMethod(pmEl.value);
        filterAccountOptions(coaEl, expected);
    });

    $('#editPaymentMethodAccountModal').on('shown.bs.modal', function() {
        const pmEl = document.getElementById('edit_payment_method');
        const coaEl = document.getElementById('edit_chart_of_account_id');
        if (!pmEl || !coaEl) return;
        const expected = requiredAccountClassCodeForPaymentMethod(pmEl.value);
        filterAccountOptions(coaEl, expected);
    });

    // ==================== PAYMENT METHOD MANAGEMENT ====================

    // Edit Payment Method
    $(document).on('click', '.edit-pm-btn', function() {
        var id = $(this).data('id');
        $('#editPaymentMethodForm').attr('action', '/payment-methods/' + id);

        $('#edit_pm_name').val($(this).data('name'));
        $('#edit_pm_code').val($(this).data('code'));
        $('#edit_pm_description').val($(this).data('description') || '');
        $('#edit_pm_account_type').val($(this).data('account-type') || 'asset');
        $('#edit_pm_status').val($(this).data('status') ? 'active' : 'inactive');
        $('#edit_pm_sort_order').val($(this).data('sort-order') || 0);

        // Checkboxes
        $('#edit_pm_is_repayment').prop('checked', $(this).data('is-repayment'));
        $('#edit_pm_is_deposit').prop('checked', $(this).data('is-deposit'));
        $('#edit_pm_is_refund').prop('checked', $(this).data('is-refund'));
        $('#edit_pm_is_withdrawal').prop('checked', $(this).data('is-withdrawal'));
        $('#edit_pm_requires_bank').prop('checked', $(this).data('requires-bank'));
        $('#edit_pm_requires_phone').prop('checked', $(this).data('requires-phone'));

        $('#editPaymentMethodModal').modal('show');
    });

    // Delete Payment Method
    $(document).on('click', '.delete-pm-btn', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');

        Swal.fire({
            title: 'Delete Payment Method?',
            html: `Are you sure you want to delete <strong>${name}</strong>?<br><small class="text-muted">This action cannot be undone.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/payment-methods/' + id,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error!', response.message || 'Failed to delete.', 'error');
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'An error occurred while deleting.';
                        Swal.fire('Error!', msg, 'error');
                    }
                });
            }
        });
    });

    // Map Payment Method (from Payment Methods tab)
    $(document).on('click', '.map-pm-btn', function() {
        var code = $(this).data('code');
        var name = $(this).data('name');
        var accountType = $(this).data('account-type') || 'asset';

        // Set and lock the payment method
        $('#payment_method').val(code).prop('disabled', true);

        // Set modal title
        $('#addPaymentMethodAccountModalLabel').text('Map "' + name + '" to GL Account');

        // Filter accounts based on type
        const coaEl = document.getElementById('chart_of_account_id');
        const expectedCode = accountType === 'liability' ? '2' : '1';
        filterAccountOptions(coaEl, expectedCode);

        $('#addPaymentMethodAccountModal').modal('show');
    });

    // Reset payment method select when modal closes
    $('#addPaymentMethodAccountModal').on('hidden.bs.modal', function() {
        $('#payment_method').prop('disabled', false);
        $('#addPaymentMethodAccountModalLabel').text('Add Payment Method Mapping');
    });

    // Edit Mapping (from GL Mappings tab)
    $(document).on('click', '.edit-mapping-btn, .edit-btn', function() {
        var id = $(this).data('id');
        var paymentMethod = $(this).data('payment-method');
        var coaId = $(this).data('chart-of-account-id');
        var accountType = $(this).data('account-type') || 'asset';

        $('#editPaymentMethodAccountForm').attr('action', '/accounting/payment_method_accounts/' + id);
        $('#edit_payment_method').val(paymentMethod);
        $('#edit_chart_of_account_id').val(coaId);

        var expected = accountType === 'liability' ? '2' : '1';
        filterAccountOptions(document.getElementById('edit_chart_of_account_id'), expected);

        $('#editPaymentMethodAccountModal').modal('show');
    });

    $(document).on('click', '.delete-btn', function() {
        var id = $(this).data('id');

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/accounting/payment_method_accounts/' + id,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                'Deleted!',
                                response.message,
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                response.message,
                                'error'
                            );
                        }
                    },
                    error: function() {
                        Swal.fire(
                            'Error!',
                            'An error occurred while deleting the mapping.',
                            'error'
                        );
                    }
                });
            }
        });
    });

    @if(session('success'))
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '{{ session('success') }}',
        showConfirmButton: true,
        timerProgressBar: true,
        timer: 3000
    });
    @endif

    @if(session('error'))
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '{{ session('error') }}',
        showConfirmButton: true
    });
    @endif
});
</script>
@endpush
