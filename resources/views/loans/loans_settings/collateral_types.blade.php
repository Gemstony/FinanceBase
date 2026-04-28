@extends('adminlte::page')

@section('title', 'Collateral Types - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-cube"></i> Collateral Types</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-cube"></i> Collaterals</h1>
                <p class="mb-0 text-light">Managing collateral types for: <strong>{{ $subshop->name }}</strong></p>
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
            <li class="breadcrumb-item active" aria-current="page">Collateral Types</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCollateralTypeModal">
        <i class="fas fa-plus"></i> New Collateral Type
    </button>
</div>
@stop

@section('content')
<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="collateralTypeTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>LTV Ratio</th>
                            <th>Depreciates</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($collateralTypes as $index => $collateral)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge badge-info">{{ $collateral->code }}</span></td>
                            <td>{{ $collateral->name }}</td>
                            <td>{{ $collateral->default_ltv_ratio ?? 'N/A' }}%</td>
                            <td>
                                <span class="badge {{ $collateral->depreciates ? 'badge-primary' : 'badge-secondary' }}">
                                    {{ $collateral->depreciates ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $collateral->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $collateral->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info view-btn" 
                                        data-id="{{ $collateral->id }}"
                                        data-name="{{ $collateral->name }}"
                                        data-code="{{ $collateral->code }}"
                                        data-description="{{ $collateral->description ?? '' }}"
                                        data-default-ltv-ratio="{{ $collateral->default_ltv_ratio ?? '' }}"
                                        data-requires-valuation="{{ $collateral->requires_valuation }}"
                                        data-depreciates="{{ $collateral->depreciates }}"
                                        data-revaluation-interval-days="{{ $collateral->revaluation_interval_days ?? '' }}"
                                        data-requires-ownership-proof="{{ $collateral->requires_ownership_proof }}"
                                        data-requires-insurance="{{ $collateral->requires_insurance }}"
                                        data-allow-multiple-per-loan="{{ $collateral->allow_multiple_per_loan }}"
                                        data-is-active="{{ $collateral->is_active }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-primary edit-btn" 
                                        data-id="{{ $collateral->id }}"
                                        data-name="{{ $collateral->name }}"
                                        data-code="{{ $collateral->code }}"
                                        data-description="{{ $collateral->description ?? '' }}"
                                        data-default-ltv-ratio="{{ $collateral->default_ltv_ratio ?? '' }}"
                                        data-requires-valuation="{{ $collateral->requires_valuation }}"
                                        data-depreciates="{{ $collateral->depreciates }}"
                                        data-revaluation-interval-days="{{ $collateral->revaluation_interval_days ?? '' }}"
                                        data-requires-ownership-proof="{{ $collateral->requires_ownership_proof }}"
                                        data-requires-insurance="{{ $collateral->requires_insurance }}"
                                        data-allow-multiple-per-loan="{{ $collateral->allow_multiple_per_loan }}"
                                        data-is-active="{{ $collateral->is_active }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $collateral->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No collateral types found. Click 'New Collateral Type' to add one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Collateral Type Modal -->
<div class="modal fade" id="addCollateralTypeModal" tabindex="-1" role="dialog" aria-labelledby="addCollateralTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="addCollateralTypeForm" action="{{ route('loans.collateral_types.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addCollateralTypeModalLabel">Add New Collateral Type</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required 
                               placeholder="e.g. HOUSE, LAND, CAR">
                    </div>
                    <div class="form-group">
                        <label for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g. House, Land, Car">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Brief description of this collateral type"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="default_ltv_ratio">Default LTV Ratio (%)</label>
                                <input type="number" class="form-control" id="default_ltv_ratio" name="default_ltv_ratio" 
                                       step="0.01" min="0" max="100" placeholder="e.g. 70.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="revaluation_interval_days">Revaluation Interval (Days)</label>
                                <input type="number" class="form-control" id="revaluation_interval_days" name="revaluation_interval_days" 
                                       min="0" placeholder="e.g. 365">
                            </div>
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="requires_valuation" name="requires_valuation" value="1">
                        <label class="form-check-label" for="requires_valuation">Requires Valuation</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="depreciates" name="depreciates" value="1">
                        <label class="form-check-label" for="depreciates">Depreciates</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="requires_ownership_proof" name="requires_ownership_proof" value="1">
                        <label class="form-check-label" for="requires_ownership_proof">Requires Ownership Proof</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="requires_insurance" name="requires_insurance" value="1">
                        <label class="form-check-label" for="requires_insurance">Requires Insurance</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="allow_multiple_per_loan" name="allow_multiple_per_loan" value="1">
                        <label class="form-check-label" for="allow_multiple_per_loan">Allow Multiple per Loan</label>
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

<!-- Edit Collateral Type Modal -->
<div class="modal fade" id="editCollateralTypeModal" tabindex="-1" role="dialog" aria-labelledby="editCollateralTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="editCollateralTypeForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editCollateralTypeModalLabel">Edit Collateral Type</h5>
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
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_default_ltv_ratio">Default LTV Ratio (%)</label>
                                <input type="number" class="form-control" id="edit_default_ltv_ratio" name="default_ltv_ratio" 
                                       step="0.01" min="0" max="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_revaluation_interval_days">Revaluation Interval (Days)</label>
                                <input type="number" class="form-control" id="edit_revaluation_interval_days" name="revaluation_interval_days" 
                                       min="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_requires_valuation" name="requires_valuation" value="1">
                        <label class="form-check-label" for="edit_requires_valuation">Requires Valuation</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_depreciates" name="depreciates" value="1">
                        <label class="form-check-label" for="edit_depreciates">Depreciates</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_requires_ownership_proof" name="requires_ownership_proof" value="1">
                        <label class="form-check-label" for="edit_requires_ownership_proof">Requires Ownership Proof</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_requires_insurance" name="requires_insurance" value="1">
                        <label class="form-check-label" for="edit_requires_insurance">Requires Insurance</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_allow_multiple_per_loan" name="allow_multiple_per_loan" value="1">
                        <label class="form-check-label" for="edit_allow_multiple_per_loan">Allow Multiple per Loan</label>
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

<!-- View Collateral Type Modal -->
<div class="modal fade" id="viewCollateralTypeModal" tabindex="-1" role="dialog" aria-labelledby="viewCollateralTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewCollateralTypeModalLabel"><i class="fas fa-cube"></i> Collateral Type Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Code:</label>
                            <p><span class="badge badge-info" id="view_code"></span></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Status:</label>
                            <p><span class="badge" id="view_status"></span></p>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Name:</label>
                    <p id="view_name"></p>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Description:</label>
                    <p id="view_description" class="text-muted"></p>
                </div>

                <hr>

                <h6 class="font-weight-bold mb-3"><i class="fas fa-cogs"></i> Configuration</h6>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Default LTV Ratio (%):</label>
                            <p id="view_ltv_ratio"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Revaluation Interval (Days):</label>
                            <p id="view_revaluation_interval"></p>
                        </div>
                    </div>
                </div>

                <hr>

                <h6 class="font-weight-bold mb-3"><i class="fas fa-check-circle"></i> Requirements</h6>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Requires Valuation:</label>
                            <p><span class="badge" id="view_requires_valuation"></span></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Depreciates:</label>
                            <p><span class="badge" id="view_depreciates"></span></p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Requires Ownership Proof:</label>
                            <p><span class="badge" id="view_requires_ownership_proof"></span></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Requires Insurance:</label>
                            <p><span class="badge" id="view_requires_insurance"></span></p>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Allow Multiple per Loan:</label>
                    <p><span class="badge" id="view_allow_multiple"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
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
    $('#collateralTypeTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [0, 6] }, // Disable sorting on action column
            { searchable: false, targets: [0, 5, 6] } // Disable search on action and status columns
        ],
        order: [[1, 'asc']] // Sort by code by default
    });

    // Handle view button click
    $('.view-btn').click(function() {
        var code = $(this).data('code');
        var name = $(this).data('name');
        var description = $(this).data('description');
        var defaultLtvRatio = $(this).data('default-ltv-ratio');
        var requiresValuation = $(this).data('requires-valuation');
        var depreciates = $(this).data('depreciates');
        var revaluationIntervalDays = $(this).data('revaluation-interval-days');
        var requiresOwnershipProof = $(this).data('requires-ownership-proof');
        var requiresInsurance = $(this).data('requires-insurance');
        var allowMultiplePerLoan = $(this).data('allow-multiple-per-loan');
        var isActive = $(this).data('is-active');
        
        // Populate view modal fields
        $('#view_code').text(code);
        $('#view_name').text(name);
        $('#view_description').text(description || 'No description provided');
        $('#view_ltv_ratio').text((defaultLtvRatio || 'N/A') + '%');
        $('#view_revaluation_interval').text(revaluationIntervalDays || 'Not specified');
        
        // Set status badge
        var statusBadge = isActive ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
        $('#view_status').html(statusBadge);
        
        // Set boolean badges
        var requiresValuationBadge = requiresValuation ? '<span class="badge badge-primary">Yes</span>' : '<span class="badge badge-secondary">No</span>';
        $('#view_requires_valuation').html(requiresValuationBadge);
        
        var depreciatesBadge = depreciates ? '<span class="badge badge-primary">Yes</span>' : '<span class="badge badge-secondary">No</span>';
        $('#view_depreciates').html(depreciatesBadge);
        
        var requiresOwnershipProofBadge = requiresOwnershipProof ? '<span class="badge badge-primary">Yes</span>' : '<span class="badge badge-secondary">No</span>';
        $('#view_requires_ownership_proof').html(requiresOwnershipProofBadge);
        
        var requiresInsuranceBadge = requiresInsurance ? '<span class="badge badge-primary">Yes</span>' : '<span class="badge badge-secondary">No</span>';
        $('#view_requires_insurance').html(requiresInsuranceBadge);
        
        var allowMultipleBadge = allowMultiplePerLoan ? '<span class="badge badge-primary">Yes</span>' : '<span class="badge badge-secondary">No</span>';
        $('#view_allow_multiple').html(allowMultipleBadge);
        
        $('#viewCollateralTypeModal').modal('show');
    });

    // Handle edit button click
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var description = $(this).data('description');
        var defaultLtvRatio = $(this).data('default-ltv-ratio');
        var requiresValuation = $(this).data('requires-valuation');
        var depreciates = $(this).data('depreciates');
        var revaluationIntervalDays = $(this).data('revaluation-interval-days');
        var requiresOwnershipProof = $(this).data('requires-ownership-proof');
        var requiresInsurance = $(this).data('requires-insurance');
        var allowMultiplePerLoan = $(this).data('allow-multiple-per-loan');
        var isActive = $(this).data('is-active');
        
        $('#editCollateralTypeForm').attr('action', '/loans/loans_settings/collateral_types/' + id);
        $('#edit_code').val(code);
        $('#edit_name').val(name);
        $('#edit_description').val(description);
        $('#edit_default_ltv_ratio').val(defaultLtvRatio);
        $('#edit_revaluation_interval_days').val(revaluationIntervalDays);
        $('#edit_requires_valuation').prop('checked', requiresValuation);
        $('#edit_depreciates').prop('checked', depreciates);
        $('#edit_requires_ownership_proof').prop('checked', requiresOwnershipProof);
        $('#edit_requires_insurance').prop('checked', requiresInsurance);
        $('#edit_allow_multiple_per_loan').prop('checked', allowMultiplePerLoan);
        $('#edit_is_active').prop('checked', isActive);
        
        $('#editCollateralTypeModal').modal('show');
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
                    url: '/loans/loans_settings/collateral_types/' + id,
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
                            'An error occurred while deleting the collateral type.',
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