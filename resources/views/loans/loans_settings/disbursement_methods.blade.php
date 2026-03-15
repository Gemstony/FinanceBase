@extends('adminlte::page')

@section('title', 'Disbursement Methods - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exchange-alt"></i> Disbursement Methods</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exchange-alt"></i> Disbursement Methods</h1>
                <p class="mb-0 text-light">Managing disbursement methods for: <strong>{{ $subshop->name }}</strong></p>
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
            <li class="breadcrumb-item active" aria-current="page">Disbursement Methods</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLoanFeeModal">
        <i class="fas fa-plus"></i> New Method
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
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="disbursementMethodTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Reference</th>
                            <th>Account Details</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($methods as $index => $method)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge badge-info">{{ $method->code }}</span></td>
                            <td>{{ $method->name }}</td>
                            <td>
                                <span class="badge {{ $method->requires_reference ? 'badge-warning' : 'badge-secondary' }}">
                                    {{ $method->requires_reference ? 'Required' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $method->requires_account_details ? 'badge-warning' : 'badge-secondary' }}">
                                    {{ $method->requires_account_details ? 'Required' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $method->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $method->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" 
                                        data-id="{{ $method->id }}"
                                        data-name="{{ $method->name }}"
                                        data-code="{{ $method->code }}"
                                        data-description="{{ $method->description }}"
                                        data-requires-reference="{{ (int) $method->requires_reference }}"
                                        data-requires-account-details="{{ (int) $method->requires_account_details }}"
                                        data-is-active="{{ (int) $method->is_active }}"
                                        data-is-system-method="{{ (int) $method->is_system_method }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $method->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No disbursement methods found. Click 'New Method' to add one.</td>
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
            <form id="addDisbursementMethodForm" action="{{ route('loans.disbursement_methods.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addLoanFeeModalLabel">Add New Disbursement Method</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required 
                               placeholder="e.g. cash, bank_transfer, mobile_money">
                    </div>
                    <div class="form-group">
                        <label for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g. Cash, Bank Transfer, Mobile Money">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Optional"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="requires_reference" name="requires_reference" value="1">
                        <label class="form-check-label" for="requires_reference">Requires Reference</label>
                    </div>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="requires_account_details" name="requires_account_details" value="1">
                        <label class="form-check-label" for="requires_account_details">Requires Account Details</label>
                    </div>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Loan Fee Modal -->
<div class="modal fade" id="editLoanFeeModal" tabindex="-1" role="dialog" aria-labelledby="editLoanFeeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editDisbursementMethodForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editLoanFeeModalLabel">Edit Disbursement Method</h5>
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
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2" placeholder="Optional"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_requires_reference" name="requires_reference" value="1">
                        <label class="form-check-label" for="edit_requires_reference">Requires Reference</label>
                    </div>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="edit_requires_account_details" name="requires_account_details" value="1">
                        <label class="form-check-label" for="edit_requires_account_details">Requires Account Details</label>
                    </div>
                    <div class="form-check mt-2">
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
    $('#disbursementMethodTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [0, 6] },
            { searchable: false, targets: [0, 5, 6] }
        ],
        order: [[1, 'asc']] // Sort by code by default
    });

    // Handle edit button click
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var description = $(this).data('description');
        var requiresReference = $(this).data('requires-reference');
        var requiresAccountDetails = $(this).data('requires-account-details');
        var isActive = $(this).data('is-active');
        
        $('#editDisbursementMethodForm').attr('action', '/loans/loans_settings/disbursement_methods/' + id);
        $('#edit_code').val(code);
        $('#edit_name').val(name);
        $('#edit_description').val(description);
        $('#edit_requires_reference').prop('checked', requiresReference == 1);
        $('#edit_requires_account_details').prop('checked', requiresAccountDetails == 1);
        $('#edit_is_active').prop('checked', isActive == 1);
        
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
                    url: '/loans/loans_settings/disbursement_methods/' + id,
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