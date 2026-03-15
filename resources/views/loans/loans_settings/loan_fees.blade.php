@extends('adminlte::page')

@section('title', 'Loan Fees - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-money-bill-wave"></i> Loan Fees</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-money-bill-wave"></i> Fees</h1>
                <p class="mb-0 text-light">Managing loan fees for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('loans.loans_settings.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('loans.loans_settings.index') }}">Loans settings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Loan Fees</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary @if($incomeAccounts->isEmpty()) disabled @endif" 
            @if(!$incomeAccounts->isEmpty()) data-toggle="modal" data-target="#addLoanFeeModal" @endif
            @if($incomeAccounts->isEmpty()) title="No income accounts available" @endif>
        <i class="fas fa-plus"></i> New Fee
    </button>
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
    
    @if($incomeAccounts->isEmpty())
        <div class="alert alert-warning">
            <h5><i class="fas fa-exclamation-triangle"></i> No Income Accounts Available</h5>
            <p>You need to create income accounts for this subshop before you can add loan fees. Please contact your administrator to set up income accounts.</p>
        </div>
    @endif
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="loanFeeTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Fee Type</th>
                            <th>Amount/Percentage</th>
                            <th>Apply On</th>
                            <th>Income Account</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loanFees as $index => $fee)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge badge-info">{{ $fee->code }}</span></td>
                            <td>{{ $fee->name }}</td>
                            <td>
                                <span class="badge {{ $fee->fee_type == 'FIXED' ? 'badge-primary' : 'badge-info' }}">
                                    {{ $fee->fee_type }}
                                </span>
                            </td>
                            <td>
                                @if($fee->fee_type == 'FIXED')
                                    <span class="text-success">{{ number_format($fee->amount, 2) }}</span>
                                @else
                                 <span class="text-info">{{ number_format($fee->percentage, 2) }}%</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-secondary">{{ $fee->apply_on }}</span>
                            </td>
                            <td>
                                <small class="text-muted">{{ $fee->incomeAccount->account_name ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $fee->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $fee->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" 
                                        data-id="{{ $fee->id }}"
                                        data-name="{{ $fee->name }}"
                                        data-code="{{ $fee->code }}"
                                        data-fee-type="{{ $fee->fee_type }}"
                                        data-amount="{{ $fee->amount ?? '' }}"
                                        data-percentage="{{ $fee->percentage ?? '' }}"
                                        data-apply-on="{{ $fee->apply_on }}"
                                        data-income-account-id="{{ $fee->income_account_id }}"
                                        data-is-active="{{ $fee->is_active }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $fee->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center">No loan fees found. Click 'New Fee' to add one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Loan Fee Modal -->
<div class="modal fade" id="addLoanFeeModal" tabindex="-1" role="dialog" aria-labelledby="addLoanFeeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addLoanFeeForm" action="{{ route('loans.loan_fees.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addLoanFeeModalLabel">Add New Loan Fee</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required 
                               placeholder="e.g. PROC, LATE, INS">
                    </div>
                    <div class="form-group">
                        <label for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g. Processing Fee, Late Payment Fee">
                    </div>
                    <div class="form-group">
                        <label for="fee_type">Fee Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="fee_type" name="fee_type" required>
                            <option value="">Select Fee Type</option>
                            <option value="FIXED">Fixed Amount</option>
                            <option value="PERCENTAGE">Percentage</option>
                        </select>
                    </div>
                    <div class="form-group" id="amount_group">
                        <label for="amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group" id="percentage_group" style="display: none;">
                        <label for="percentage">Percentage <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="percentage" name="percentage" 
                               step="0.01" min="0" max="100" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="apply_on">Apply On <span class="text-danger">*</span></label>
                        <select class="form-control" id="apply_on" name="apply_on" required>
                            <option value="">Select When to Apply</option>
                            <option value="DISBURSEMENT">Disbursement</option>
                            <option value="REPAYMENT">Repayment</option>
                            <option value="TOP_UP">Top Up</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="income_account_id">Income Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="income_account_id" name="income_account_id" required>
                            <option value="">Select Income Account</option>
                            @foreach($incomeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">Active</label>
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

<!-- Edit Loan Fee Modal -->
<div class="modal fade" id="editLoanFeeModal" tabindex="-1" role="dialog" aria-labelledby="editLoanFeeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editLoanFeeForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editLoanFeeModalLabel">Edit Loan Fee</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_code" name="code" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_fee_type">Fee Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_fee_type" name="fee_type" required>
                            <option value="">Select Fee Type</option>
                            <option value="FIXED">Fixed Amount</option>
                            <option value="PERCENTAGE">Percentage</option>
                        </select>
                    </div>
                    <div class="form-group" id="edit_amount_group">
                        <label for="edit_amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_amount" name="amount" 
                               step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group" id="edit_percentage_group" style="display: none;">
                        <label for="edit_percentage">Percentage <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_percentage" name="percentage" 
                               step="0.01" min="0" max="100" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="edit_apply_on">Apply On <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_apply_on" name="apply_on" required>
                            <option value="">Select When to Apply</option>
                            <option value="DISBURSEMENT">Disbursement</option>
                            <option value="REPAYMENT">Repayment</option>
                            <option value="TOP_UP">Top Up</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_income_account_id">Income Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_income_account_id" name="income_account_id" required>
                            <option value="">Select Income Account</option>
                            @foreach($incomeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1">
                        <label class="form-check-label" for="edit_is_active">Active</label>
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
    .action-buttons {
        white-space: nowrap;
    }
</style>
@endpush




@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#loanFeeTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [0, 8] }, // Disable sorting on action column
            { searchable: false, targets: [0, 7, 8] } // Disable search on action and status columns
        ],
        order: [[1, 'asc']] // Sort by code by default
    });

    // Handle fee type change for add form
    $('#fee_type').change(function() {
        var feeType = $(this).val();
        console.log('Fee type changed to:', feeType);
        
        if (feeType === 'FIXED') {
            $('#amount_group').show();
            $('#percentage_group').hide();
            $('#amount').prop('required', true);
            $('#percentage').prop('required', false);
            console.log('Showing amount field, hiding percentage field');
        } else if (feeType === 'PERCENTAGE') {
            $('#amount_group').hide();
            $('#percentage_group').show();
            $('#amount').prop('required', false);
            $('#percentage').prop('required', true);
            console.log('Showing percentage field, hiding amount field');
        } else {
            $('#amount_group').hide();
            $('#percentage_group').hide();
            $('#amount').prop('required', false);
            $('#percentage').prop('required', false);
            console.log('Hiding both amount and percentage fields');
        }
    });

    // Debug: Add form submission handler
    $('#addLoanFeeForm').submit(function(e) {
        console.log('Form submitted');
        console.log('Form data:', $(this).serialize());
        
        // Check if required fields are filled
        var feeType = $('#fee_type').val();
        var amount = $('#amount').val();
        var percentage = $('#percentage').val();
        
        console.log('Fee type:', feeType);
        console.log('Amount:', amount);
        console.log('Percentage:', percentage);
        
        if (feeType === 'FIXED' && !amount) {
            console.error('Amount is required for FIXED fee type');
            e.preventDefault();
            alert('Amount is required for Fixed fee type');
            return false;
        }
        
        if (feeType === 'PERCENTAGE' && !percentage) {
            console.error('Percentage is required for PERCENTAGE fee type');
            e.preventDefault();
            alert('Percentage is required for Percentage fee type');
            return false;
        }
        
        return true;
    });

    // Handle fee type change for edit form
    $('#edit_fee_type').change(function() {
        var feeType = $(this).val();
        if (feeType === 'FIXED') {
            $('#edit_amount_group').show();
            $('#edit_percentage_group').hide();
            $('#edit_amount').prop('required', true);
            $('#edit_percentage').prop('required', false);
        } else if (feeType === 'PERCENTAGE') {
            $('#edit_amount_group').hide();
            $('#edit_percentage_group').show();
            $('#edit_amount').prop('required', false);
            $('#edit_percentage').prop('required', true);
        } else {
            $('#edit_amount_group').hide();
            $('#edit_percentage_group').hide();
            $('#edit_amount').prop('required', false);
            $('#edit_percentage').prop('required', false);
        }
    });

    // Handle edit button click
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var feeType = $(this).data('fee-type');
        var amount = $(this).data('amount');
        var percentage = $(this).data('percentage');
        var applyOn = $(this).data('apply-on');
        var incomeAccountId = $(this).data('income-account-id');
        var isActive = $(this).data('is-active');
        
        $('#editLoanFeeForm').attr('action', '/loans/loans_settings/loan_fees/' + id);
        $('#edit_code').val(code);
        $('#edit_name').val(name);
        $('#edit_fee_type').val(feeType).trigger('change');
        $('#edit_amount').val(amount);
        $('#edit_percentage').val(percentage);
        $('#edit_apply_on').val(applyOn);
        $('#edit_income_account_id').val(incomeAccountId);
        $('#edit_is_active').prop('checked', isActive);
        
        $('#editLoanFeeModal').modal('show');
    });

    // Handle delete button click
    $('.delete-btn').click(function() {
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
                    url: '/loans/loans_settings/loan_fees/' + id,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.message) {
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
                                'Unexpected response format',
                                'error'
                            );
                        }
                    },
                    error: function(xhr) {
                        Swal.fire(
                            'Error!',
                            'An error occurred while deleting the loan fee.',
                            'error'
                        );
                    }
                });
            }
        });
    });

    // Show success/error messages
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