@extends('adminlte::page')

@section('title', 'Account Classes - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-layer-group"></i> Loan Product types</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-layer-group"></i> Product types</h1>
                <p class="mb-0 text-light">Managing Loan product types for: <strong>{{ $subshop->name }}</strong></p>
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
            <li class="breadcrumb-item active" aria-current="page">Product types</li>
        </ol>
    </nav>
    <div>
        <div class="btn-group mr-2">
            <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-download"></i> Export
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="{{ route('loans.loan_product_types.export', 'excel') }}">
                    <i class="fas fa-file-excel text-success"></i> Export to Excel
                </a>
                <a class="dropdown-item" href="{{ route('loans.loan_product_types.export', 'pdf') }}">
                    <i class="fas fa-file-pdf text-danger"></i> Export to PDF
                </a>
            </div>
        </div>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLoanProductTypeModal">
            <i class="fas fa-plus"></i> New Product type
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
    @if(session('import_errors'))
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle"></i> Import Errors</h5>
            <p class="mb-2">The following errors were found in your Excel file. Please fix them and try again:</p>
            <ul class="mb-0">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="loanProductTypeTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loanProductTypes as $index => $type)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge badge-info">{{ $type->code }}</span></td>
                            <td>{{ $type->name }}</td>
                            <td>{{ $type->description ?? 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $type->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $type->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ $type->created_at->format('M d, Y') }}</td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" 
                                        data-id="{{ $type->id }}"
                                        data-name="{{ $type->name }}"
                                        data-code="{{ $type->code }}"
                                        data-description="{{ $type->description }}"
                                        data-is-active="{{ $type->is_active }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $type->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No account classes found. Click 'New Account Class' to add one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    
        <!-- Import Section -->
        <div class="card mt-4">
            <div class="card-header">
                <a class="d-flex align-items-center justify-content-between text-decoration-none" data-toggle="collapse" href="#importCollapse" role="button" aria-expanded="false" aria-controls="importCollapse">
                    <h5 class="mb-0 text-dark"><i class="fas fa-file-import"></i> Import Loan Product Types</h5>
                    <i class="fas fa-chevron-down text-dark"></i>
                </a>
            </div>
            <div class="collapse" id="importCollapse">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card border">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-upload"></i> Bulk Import Loan Product Types</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('loans.loan_product_types.import') }}" enctype="multipart/form-data">
                                        @csrf
                                        
                                        <div class="mb-4">
                                            <label for="excel_file" class="form-label fw-bold">Select Excel File <span class="text-danger">*</span></label>
                                            <input type="file" class="form-control @error('excel_file') is-invalid @enderror"
                                                   id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                                            @error('excel_file')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">Only Excel files (.xlsx, .xls, .csv) are allowed. Maximum file size: 2MB</small>
                                        </div>
    
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <a href="{{ route('loans.loan_product_types.download-template') }}" class="btn btn-outline-success">
                                                <i class="fas fa-download"></i> Download Excel Template
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-file-import"></i> Import Loan Product Types
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
    
                        <div class="col-md-4">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Import Instructions</h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="fw-bold">Required Columns:</h6>
                                    <ul class="small">
                                        <li><strong>Code</strong> - Unique code for the product type (e.g., 1000, 2000)</li>
                                        <li><strong>Name</strong> - Name of the loan product type</li>
                                    </ul>
    
                                    <h6 class="fw-bold mt-3">Optional Columns:</h6>
                                    <ul class="small">
                                        <li><strong>Description</strong> - Description of the product type</li>
                                        <li><strong>Is Active</strong> - Yes/No or 1/0 (defaults to Yes)</li>
                                    </ul>
    
                                    <h6 class="fw-bold mt-3">Important Rules:</h6>
                                    <ul class="small text-danger">
                                        <li>First row must be the header</li>
                                        <li>All required fields must be filled</li>
                                        <li>Code must be unique within your shop</li>
                                        <li>Is Active accepts: Yes, No, 1, 0, true, false</li>
                                        <li>If any row fails, all imports will be rolled back</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Loan product type Modal -->
<div class="modal fade" id="addLoanProductTypeModal" tabindex="-1" role="dialog" aria-labelledby="addAccountClassModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addLoanProductTypeForm" action="{{ route('loans.loan_product_types.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addLoanProductTypeModalLabel">Add New Loan Product Type</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required 
                               placeholder="e.g. 1000, 2000, etc.">
                    </div>
                    <div class="form-group">
                        <label for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g. Bussiness loan, Angriculture loan, etc.">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" 
                                 rows="3" placeholder="Enter description (optional)"></textarea>
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

<!-- Edit Loan product type Modal -->
<div class="modal fade" id="editLoanProductTypeModal" tabindex="-1" role="dialog" aria-labelledby="editAccountClassModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editLoanProductTypeForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editLoanProductTypeModalLabel">Edit Loan Product Type</h5>
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
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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
    $('#loanProductTypeTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [0, 6] }, // Disable sorting on action column
            { searchable: false, targets: [0, 4, 5, 6] } // Disable search on action and status columns
        ],
        order: [[1, 'asc']] // Sort by code by default
    });

    // Handle edit button click
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var description = $(this).data('description');
        var isActive = $(this).data('is-active');
        
        $('#editLoanProductTypeForm').attr('action', '/loans/loan_product_types/' + id);
        $('#edit_code').val(code);
        $('#edit_name').val(name);
        $('#edit_description').val(description);
        $('#edit_is_active').prop('checked', isActive);
        
        $('#editLoanProductTypeModal').modal('show');
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
                    url: '/loans/loan_product_types/' + id,
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
                    error: function(xhr) {
                        Swal.fire(
                            'Error!',
                            'An error occurred while deleting the Loan Product Type.',
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