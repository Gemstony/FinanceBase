@extends('adminlte::page')

@section('title', 'Interest Methods - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-calculator"></i> Interest Methods</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-calculator"></i> Methods</h1>
                <p class="mb-0 text-light">Managing interest methods for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-light btn-sm">
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
            <li class="breadcrumb-item active" aria-current="page">Interest Methods</li>
        </ol>
    </nav>
    <div>
        <div class="btn-group mr-2">
            <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-download"></i> Export
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="{{ route('loans.interest_methods.export', 'excel') }}">
                    <i class="fas fa-file-excel text-success"></i> Export to Excel
                </a>
                <a class="dropdown-item" href="{{ route('loans.interest_methods.export', 'pdf') }}">
                    <i class="fas fa-file-pdf text-danger"></i> Export to PDF
                </a>
            </div>
        </div>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addInterestMethodModal">
            <i class="fas fa-plus"></i> New Method
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
                <table class="table table-bordered table-striped table-hover" id="interestMethodTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Supports Installment Based</th>
                            <th>Supports Daily Accrual</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($interestMethods as $index => $method)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge badge-info">{{ $method->code }}</span></td>
                            <td>{{ $method->name }}</td>
                            <td>
                                <span class="badge {{ $method->supports_installment_based ? 'badge-primary' : 'badge-secondary' }}">
                                    {{ $method->supports_installment_based ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $method->supports_daily_accrual ? 'badge-primary' : 'badge-secondary' }}">
                                    {{ $method->supports_daily_accrual ? 'Yes' : 'No' }}
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
                                        data-supports-installment-based="{{ $method->supports_installment_based }}"
                                        data-supports-daily-accrual="{{ $method->supports_daily_accrual }}"
                                        data-is-active="{{ $method->is_active }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $method->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No interest methods found. Click 'New Method' to add one.</td>
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
                    <h5 class="mb-0 text-dark"><i class="fas fa-file-import"></i> Import Interest Methods</h5>
                    <i class="fas fa-chevron-down text-dark"></i>
                </a>
            </div>
            <div class="collapse" id="importCollapse">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card border">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-upload"></i> Bulk Import Interest Methods</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('loans.interest_methods.import') }}" enctype="multipart/form-data">
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
                                            <a href="{{ route('loans.interest_methods.download-template') }}" class="btn btn-outline-success">
                                                <i class="fas fa-download"></i> Download Excel Template
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-file-import"></i> Import Interest Methods
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
                                        <li><strong>Code</strong> - Unique code for the interest method (e.g., FLAT, RED, COMP)</li>
                                        <li><strong>Name</strong> - Name of the interest method</li>
                                    </ul>
    
                                    <h6 class="fw-bold mt-3">Optional Columns:</h6>
                                    <ul class="small">
                                        <li><strong>Supports Installment Based</strong> - Yes/No or 1/0 (defaults to No)</li>
                                        <li><strong>Supports Daily Accrual</strong> - Yes/No or 1/0 (defaults to No)</li>
                                        <li><strong>Is Active</strong> - Yes/No or 1/0 (defaults to Yes)</li>
                                    </ul>
    
                                    <h6 class="fw-bold mt-3">Important Rules:</h6>
                                    <ul class="small text-danger">
                                        <li>First row must be the header</li>
                                        <li>All required fields must be filled</li>
                                        <li>Code must be unique within your shop</li>
                                        <li>Boolean columns accept: Yes, No, 1, 0, true, false</li>
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

<!-- Add Interest Method Modal -->
<div class="modal fade" id="addInterestMethodModal" tabindex="-1" role="dialog" aria-labelledby="addInterestMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addInterestMethodForm" action="{{ route('loans.interest_methods.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addInterestMethodModalLabel">Add New Interest Method</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required 
                               placeholder="e.g. FLAT, RED, COMP">
                    </div>
                    <div class="form-group">
                        <label for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g. Flat, Reducing Balance, Compound">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="supports_installment_based" name="supports_installment_based" value="1">
                        <label class="form-check-label" for="supports_installment_based">Supports Installment Based</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="supports_daily_accrual" name="supports_daily_accrual" value="1">
                        <label class="form-check-label" for="supports_daily_accrual">Supports Daily Accrual</label>
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

<!-- Edit Interest Method Modal -->
<div class="modal fade" id="editInterestMethodModal" tabindex="-1" role="dialog" aria-labelledby="editInterestMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editInterestMethodForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editInterestMethodModalLabel">Edit Interest Method</h5>
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
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_supports_installment_based" name="supports_installment_based" value="1">
                        <label class="form-check-label" for="edit_supports_installment_based">Supports Installment Based</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_supports_daily_accrual" name="supports_daily_accrual" value="1">
                        <label class="form-check-label" for="edit_supports_daily_accrual">Supports Daily Accrual</label>
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
    $('#interestMethodTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [0, 6] }, // Disable sorting on action column
            { searchable: false, targets: [0, 5, 6] } // Disable search on action and status columns
        ],
        order: [[1, 'asc']] // Sort by code by default
    });

    // Handle edit button click
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var supportsInstallmentBased = $(this).data('supports-installment-based');
        var supportsDailyAccrual = $(this).data('supports-daily-accrual');
        var isActive = $(this).data('is-active');
        
        $('#editInterestMethodForm').attr('action', '/loans/loans_settings/interest_methods/' + id);
        $('#edit_code').val(code);
        $('#edit_name').val(name);
        $('#edit_supports_installment_based').prop('checked', supportsInstallmentBased);
        $('#edit_supports_daily_accrual').prop('checked', supportsDailyAccrual);
        $('#edit_is_active').prop('checked', isActive);
        
        $('#editInterestMethodModal').modal('show');
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
                    url: '/loans/loans_settings/interest_methods/' + id,
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
                            'An error occurred while deleting the interest method.',
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
        timer: 2500
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