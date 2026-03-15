@extends('adminlte::page')

@section('title', 'Interest Cycles - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-clock"></i> Interest Cycles</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-clock"></i> Cycles</h1>
                <p class="mb-0 text-light">Managing interest cycles for: <strong>{{ $subshop->name }}</strong></p>
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
            <li class="breadcrumb-item active" aria-current="page">Interest Cycles</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addInterestCycleModal">
        <i class="fas fa-plus"></i> New Cycle
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
                <table class="table table-bordered table-striped table-hover" id="interestCycleTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Interval Days</th>
                            <th>Installment Based</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($interestCycles as $index => $cycle)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge badge-info">{{ $cycle->code }}</span></td>
                            <td>{{ $cycle->name }}</td>
                            <td>{{ $cycle->interval_days ?? 'N/A' }} days</td>
                            <td>
                                <span class="badge {{ $cycle->is_installment_based ? 'badge-primary' : 'badge-secondary' }}">
                                    {{ $cycle->is_installment_based ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $cycle->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $cycle->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" 
                                        data-id="{{ $cycle->id }}"
                                        data-name="{{ $cycle->name }}"
                                        data-code="{{ $cycle->code }}"
                                        data-interval-days="{{ $cycle->interval_days ?? '' }}"
                                        data-is-installment-based="{{ $cycle->is_installment_based }}"
                                        data-is-active="{{ $cycle->is_active }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $cycle->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No interest cycles found. Click 'New Cycle' to add one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Interest Cycle Modal -->
<div class="modal fade" id="addInterestCycleModal" tabindex="-1" role="dialog" aria-labelledby="addInterestCycleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addInterestCycleForm" action="{{ route('loans.interest_cycles.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addInterestCycleModalLabel">Add New Interest Cycle</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required 
                               placeholder="e.g. DLY, MTH, INST">
                    </div>
                    <div class="form-group">
                        <label for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g. Daily, Monthly, Per Installment">
                    </div>
                    <div class="form-group">
                        <label for="interval_days">Interval Days</label>
                        <input type="number" class="form-control" id="interval_days" name="interval_days" 
                               min="1" placeholder="e.g. 1, 30 (null if installment based)">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_installment_based" name="is_installment_based" value="1">
                        <label class="form-check-label" for="is_installment_based">Installment Based</label>
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

<!-- Edit Interest Cycle Modal -->
<div class="modal fade" id="editInterestCycleModal" tabindex="-1" role="dialog" aria-labelledby="editInterestCycleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editInterestCycleForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editInterestCycleModalLabel">Edit Interest Cycle</h5>
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
                        <label for="edit_interval_days">Interval Days</label>
                        <input type="number" class="form-control" id="edit_interval_days" name="interval_days" min="1">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_installment_based" name="is_installment_based" value="1">
                        <label class="form-check-label" for="edit_is_installment_based">Installment Based</label>
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
    $('#interestCycleTable').DataTable({
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
        var intervalDays = $(this).data('interval-days');
        var isInstallmentBased = $(this).data('is-installment-based');
        var isActive = $(this).data('is-active');
        
        $('#editInterestCycleForm').attr('action', '/loans/loans_settings/interest_cycles/' + id);
        $('#edit_code').val(code);
        $('#edit_name').val(name);
        $('#edit_interval_days').val(intervalDays);
        $('#edit_is_installment_based').prop('checked', isInstallmentBased);
        $('#edit_is_active').prop('checked', isActive);
        
        $('#editInterestCycleModal').modal('show');
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
                    url: '/loans/loans_settings/interest_cycles/' + id,
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
                            'An error occurred while deleting the interest cycle.',
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