@extends('adminlte::page')

@section('title', 'Repayment Frequencies - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-clock"></i> Repayment Frequencies</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-clock"></i> Frequencies</h1>
                <p class="mb-0 text-light">Managing repayment frequencies for: <strong>{{ $subshop->name }}</strong></p>
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
            <li class="breadcrumb-item active" aria-current="page">Repayment Frequencies</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRepaymentFrequencyModal">
        <i class="fas fa-plus"></i> New Frequency
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
                <table class="table table-bordered table-striped table-hover" id="repaymentFrequencyTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Interval Days</th>
                            <th>Month Based</th>
                            <th>Installments</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($repaymentFrequencies as $index => $frequency)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge badge-info">{{ $frequency->code }}</span></td>
                            <td>{{ $frequency->name }}</td>
                            <td>{{ $frequency->interval_days }} days</td>
                            <td>
                                <span class="badge {{ $frequency->is_month_based ? 'badge-primary' : 'badge-secondary' }}">
                                    {{ $frequency->is_month_based ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    Min: {{ $frequency->min_installments ?? 'N/A' }} / 
                                    Max: {{ $frequency->max_installments ?? 'N/A' }}
                                </small>
                            </td>
                            <td>
                                <span class="badge {{ $frequency->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $frequency->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" 
                                        data-id="{{ $frequency->id }}"
                                        data-name="{{ $frequency->name }}"
                                        data-code="{{ $frequency->code }}"
                                        data-interval-days="{{ $frequency->interval_days }}"
                                        data-is-month-based="{{ $frequency->is_month_based }}"
                                        data-max-installments="{{ $frequency->max_installments ?? '' }}"
                                        data-min-installments="{{ $frequency->min_installments ?? '' }}"
                                        data-is-active="{{ $frequency->is_active }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $frequency->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center">No repayment frequencies found. Click 'New Frequency' to add one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Repayment Frequency Modal -->
<div class="modal fade" id="addRepaymentFrequencyModal" tabindex="-1" role="dialog" aria-labelledby="addRepaymentFrequencyModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addRepaymentFrequencyForm" action="{{ route('loans.repayment_frequencies.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addRepaymentFrequencyModalLabel">Add New Repayment Frequency</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required 
                               placeholder="e.g. DLY, WKY, MTH, QTR">
                    </div>
                    <div class="form-group">
                        <label for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g. Daily, Weekly, Monthly, Quarterly">
                    </div>
                    <div class="form-group">
                        <label for="interval_days">Interval Days <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="interval_days" name="interval_days" required 
                               min="1" placeholder="e.g. 1, 7, 14, 30">
                    </div>
                    <div class="form-group">
                        <label for="min_installments">Min Installments</label>
                        <input type="number" class="form-control" id="min_installments" name="min_installments" 
                               min="1" placeholder="Minimum installments (optional)">
                    </div>
                    <div class="form-group">
                        <label for="max_installments">Max Installments</label>
                        <input type="number" class="form-control" id="max_installments" name="max_installments" 
                               min="1" placeholder="Maximum installments (optional)">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_month_based" name="is_month_based" value="1">
                        <label class="form-check-label" for="is_month_based">Month Based</label>
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

<!-- Edit Repayment Frequency Modal -->
<div class="modal fade" id="editRepaymentFrequencyModal" tabindex="-1" role="dialog" aria-labelledby="editRepaymentFrequencyModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editRepaymentFrequencyForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editRepaymentFrequencyModalLabel">Edit Repayment Frequency</h5>
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
                        <label for="edit_interval_days">Interval Days <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_interval_days" name="interval_days" required min="1">
                    </div>
                    <div class="form-group">
                        <label for="edit_min_installments">Min Installments</label>
                        <input type="number" class="form-control" id="edit_min_installments" name="min_installments" min="1">
                    </div>
                    <div class="form-group">
                        <label for="edit_max_installments">Max Installments</label>
                        <input type="number" class="form-control" id="edit_max_installments" name="max_installments" min="1">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_month_based" name="is_month_based" value="1">
                        <label class="form-check-label" for="edit_is_month_based">Month Based</label>
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
    $('#repaymentFrequencyTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [0, 7] }, // Disable sorting on action column
            { searchable: false, targets: [0, 5, 6, 7] } // Disable search on action and status columns
        ],
        order: [[1, 'asc']] // Sort by code by default
    });

    // Handle edit button click
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var intervalDays = $(this).data('interval-days');
        var isMonthBased = $(this).data('is-month-based');
        var maxInstallments = $(this).data('max-installments');
        var minInstallments = $(this).data('min-installments');
        var isActive = $(this).data('is-active');
        
        $('#editRepaymentFrequencyForm').attr('action', '/loans/loans_settings/repayment_frequencies/' + id);
        $('#edit_code').val(code);
        $('#edit_name').val(name);
        $('#edit_interval_days').val(intervalDays);
        $('#edit_max_installments').val(maxInstallments);
        $('#edit_min_installments').val(minInstallments);
        $('#edit_is_month_based').prop('checked', isMonthBased);
        $('#edit_is_active').prop('checked', isActive);
        
        $('#editRepaymentFrequencyModal').modal('show');
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
                    url: '/loans/loans_settings/repayment_frequencies/' + id,
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
                            'An error occurred while deleting the repayment frequency.',
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
        showConfirmButton: true,

    });
    @endif
});
</script>
@endpush