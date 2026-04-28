@extends('adminlte::page')

@section('title', 'Bank Accounts - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-university"></i> Bank Accounts</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-university"></i> Accounts</h1>
                <p class="mb-0 text-light">Managing bank/cash/mobile money accounts for: <strong>{{ $subshop->name }}</strong></p>
            </div>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Bank Accounts</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary @if($chartAccounts->isEmpty()) disabled @endif" 
            @if(!$chartAccounts->isEmpty()) data-toggle="modal" data-target="#addBankAccountModal" @endif
            @if($chartAccounts->isEmpty()) title="No chart of accounts available" @endif>
        <i class="fas fa-plus"></i> New Account
    </button>
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
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($chartAccounts->isEmpty())
    <div class="alert alert-warning">
        <h5><i class="fas fa-exclamation-triangle"></i> No Chart of Accounts Available</h5>
        <p>You need to create chart of accounts for this subshop before you can add bank accounts. Please contact your administrator to set up accounts.</p>
    </div>
@endif

<div class="row">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small">Total Accounts</div>
                        <div class="h4 mb-0">{{ number_format((int) ($summaryTotalAccounts ?? 0)) }}</div>
                    </div>
                    <i class="fas fa-university fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small">Active Accounts</div>
                        <div class="h4 mb-0">{{ number_format((int) ($summaryActiveAccounts ?? 0)) }}</div>
                    </div>
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small">Inactive Accounts</div>
                        <div class="h4 mb-0">{{ number_format((int) ($summaryInactiveAccounts ?? 0)) }}</div>
                    </div>
                    <i class="fas fa-times-circle fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-light">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small">Total Opening Balance</div>
                        <div class="h4 mb-0">{{ number_format((float) ($summaryTotalOpeningBalance ?? 0), 2) }}</div>
                    </div>
                    <i class="fas fa-coins fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" id="bankAccountTable">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Bank Name</th>
                        <th>Account Number</th>
                        <th>Currency</th>
                        <th>Opening Balance</th>
                        <th>GL Account</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bankAccounts as $index => $account)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $account->account_name }}</td>
                        <td>
                            <span class="badge {{ $account->account_type == 'bank' ? 'badge-primary' : ($account->account_type == 'cash' ? 'badge-success' : 'badge-info') }}">
                                {{ ucfirst($account->account_type) }}
                            </span>
                        </td>
                        <td>{{ $account->bank_name ?? 'N/A' }}</td>
                        <td>{{ $account->account_number ?? 'N/A' }}</td>
                        <td><span class="badge badge-secondary">{{ $account->currency_code }}</span></td>
                        <td>{{ number_format($account->opening_balance, 2) }}</td>
                        <td>
                            <small class="text-muted">{{ $account->chartOfAccount->account_name ?? 'N/A' }}</small>
                        </td>
                        <td>
                            <span class="badge {{ $account->is_active ? 'badge-success' : 'badge-secondary' }}">
                                {{ $account->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="d-flex ">
                            <a class="btn btn-sm btn-outline-secondary mr-1" href="{{ route('accounting.bank-accounts.show', $account->id) }}">
                                <i class="fas fa-chart-line"></i> Dashboard
                            </a>
                            <button class="btn btn-sm btn-outline-primary edit-btn" 
                                    data-id="{{ $account->id }}"
                                    data-account-name="{{ $account->account_name }}"
                                    data-account-type="{{ $account->account_type }}"
                                    data-bank-name="{{ $account->bank_name ?? '' }}"
                                    data-account-number="{{ $account->account_number ?? '' }}"
                                    data-opening-balance="{{ $account->opening_balance }}"
                                    data-currency-code="{{ $account->currency_code }}"
                                    data-chart-of-account-id="{{ $account->chart_of_account_id }}"
                                    data-is-active="{{ $account->is_active }}"
                                    data-description="{{ $account->description ?? '' }}">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $account->id }}">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center">No bank accounts found. Click 'New Account' to add one.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Add Bank Account Modal -->
<div class="modal fade" id="addBankAccountModal" tabindex="-1" role="dialog" aria-labelledby="addBankAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addBankAccountForm" action="{{ route('accounting.bank_accounts.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addBankAccountModalLabel">Add New Bank Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="account_name">Account Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="account_name" name="account_name" required 
                               placeholder="e.g. CRDB Loan Account, Branch Cash Box">
                    </div>
                    <div class="form-group">
                        <label for="account_type">Account Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="account_type" name="account_type" required>
                            <option value="">Select Type</option>
                            <option value="bank">Bank</option>
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="form-group" id="bank_name_group" style="display: none;">
                        <label for="bank_name">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name" 
                               placeholder="e.g. CRDB, NMB, NBC">
                    </div>
                    <div class="form-group" id="account_number_group" style="display: none;">
                        <label for="account_number">Account Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="account_number" name="account_number" 
                               placeholder="Bank account or mobile money number">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="currency_code">Currency <span class="text-danger">*</span></label>
                                <select class="form-control" id="currency_code" name="currency_code" required>
                                    <option value="TZS" selected>TZS - Tanzanian Shilling</option>
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="KES">KES - Kenyan Shilling</option>
                                    <option value="UGX">UGX - Ugandan Shilling</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="opening_balance">Opening Balance <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="opening_balance" name="opening_balance" 
                                       step="0.01" min="0" value="0.00" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="chart_of_account_id">GL Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="chart_of_account_id" name="chart_of_account_id" required>
                            <option value="">Select GL Account</option>
                            @foreach($chartAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" checked>
                            <label class="custom-control-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bank Account Modal -->
<div class="modal fade" id="editBankAccountModal" tabindex="-1" role="dialog" aria-labelledby="editBankAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editBankAccountForm" action="#" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editBankAccountModalLabel">Edit Bank Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label for="edit_account_name">Account Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_account_name" name="account_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_account_type">Account Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_account_type" name="account_type" required>
                            <option value="">Select Type</option>
                            <option value="bank">Bank</option>
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="form-group" id="edit_bank_name_group" style="display: none;">
                        <label for="edit_bank_name">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_bank_name" name="bank_name">
                    </div>
                    <div class="form-group" id="edit_account_number_group" style="display: none;">
                        <label for="edit_account_number">Account Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_account_number" name="account_number">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_currency_code">Currency <span class="text-danger">*</span></label>
                                <select class="form-control" id="edit_currency_code" name="currency_code" required>
                                    <option value="TZS">TZS - Tanzanian Shilling</option>
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="KES">KES - Kenyan Shilling</option>
                                    <option value="UGX">UGX - Ugandan Shilling</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_opening_balance">Opening Balance <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_opening_balance" name="opening_balance" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_chart_of_account_id">GL Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_chart_of_account_id" name="chart_of_account_id" required>
                            <option value="">Select GL Account</option>
                            @foreach($chartAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="edit_is_active" name="is_active" value="1">
                            <label class="custom-control-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    if ($('#bankAccountTable').length) {
        $('#bankAccountTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [0, 9] },
                { searchable: false, targets: [0, 9] }
            ],
            order: [[1, 'asc']]
        });
    }

    // Toggle bank name and account number fields based on account type
    function toggleAccountTypeFields(type, prefix = '') {
        prefix = prefix || '';
        const bankNameGroup = $('#' + prefix + 'bank_name_group');
        const accountNumberGroup = $('#' + prefix + 'account_number_group');
        const bankName = $('#' + prefix + 'bank_name');
        const accountNumber = $('#' + prefix + 'account_number');

        if (type === 'bank' || type === 'mobile_money') {
            bankNameGroup.show();
            accountNumberGroup.show();
            bankName.prop('required', true);
            accountNumber.prop('required', true);
        } else {
            bankNameGroup.hide();
            accountNumberGroup.hide();
            bankName.prop('required', false);
            accountNumber.prop('required', false);
        }
    }

    $('#account_type').on('change', function() {
        toggleAccountTypeFields($(this).val());
    });

    $('#edit_account_type').on('change', function() {
        toggleAccountTypeFields($(this).val(), 'edit_');
    });

    // Edit Bank Account
    $(document).on('click', '.edit-btn', function() {
        const data = $(this).data();
        $('#edit_id').val(data.id);
        $('#edit_account_name').val(data.accountName);
        $('#edit_account_type').val(data.accountType);
        $('#edit_bank_name').val(data.bankName);
        $('#edit_account_number').val(data.accountNumber);
        $('#edit_currency_code').val(data.currencyCode);
        $('#edit_opening_balance').val(data.openingBalance);
        $('#edit_chart_of_account_id').val(data.chartOfAccountId);
        $('#edit_is_active').prop('checked', data.isActive);
        $('#edit_description').val(data.description);
        
        toggleAccountTypeFields(data.accountType, 'edit_');
        
        $('#editBankAccountForm').attr('action', '/admin/accounting/bank_accounts/' + data.id);
        $('#editBankAccountModal').modal('show');
    });

    // Update Bank Account
    $('#editBankAccountForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const id = $('#edit_id').val();
        const updateUrl = '/admin/accounting/bank_accounts/' + id;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

        $.ajax({
            url: updateUrl,
            method: 'POST',
            data: $(form).serialize() + '&_method=PUT',
            success: function(response) {
                const message = (response && response.message) ? response.message : 'Bank account updated successfully!';
                Swal.fire('Updated!', message, 'success').then(() => {
                    location.reload();
                });
            },
            error: function(xhr) {
                let message = 'Failed to update bank account.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    const errors = $(xhr.responseText).find('li');
                    if (errors.length) {
                        message = errors.map(function() { return $(this).text(); }).get().join(', ');
                    }
                }
                Swal.fire('Error', message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    });

    // Delete Bank Account
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
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
                    url: '/admin/accounting/bank_accounts/' + id,
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        Swal.fire('Deleted!', response.message, 'success');
                        location.reload();
                    },
                    error: function(xhr) {
                        let message = 'Failed to delete bank account.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        Swal.fire('Error', message, 'error');
                    }
                });
            }
        });
    });
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
