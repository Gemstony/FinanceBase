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
            <li class="breadcrumb-item active" aria-current="page">Interest Methods</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addInterestMethodModal">
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