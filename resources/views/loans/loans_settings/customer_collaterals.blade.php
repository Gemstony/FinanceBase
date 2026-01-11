@extends('adminlte::page')

@section('title', 'Customer Collaterals - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-shield-alt"></i> Customer Collaterals</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-shield-alt"></i> Collaterals</h1>
                <p class="mb-0 text-light">Managing customer collaterals for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('categories.subshops') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Change Branch
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('loans.loans_settings.index') }}">Loans settings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Customer Collaterals</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCustomerCollateralModal">
        <i class="fas fa-plus"></i> New Collateral
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
                <table class="table table-bordered table-striped table-hover" id="customerCollateralTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Collateral Type</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Value</th>
                            <th>Documents</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customerCollaterals as $index => $collateral)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ optional($collateral->customer)->name ?? 'N/A' }}</td>
                            <td>{{ optional($collateral->collateralType)->name ?? 'N/A' }}</td>
                            <td>{{ $collateral->description }}</td>
                            <td>{{ $collateral->reference_number ?? 'N/A' }}</td>
                            <td>{{ number_format($collateral->estimated_value, 2) }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="badge badge-info mr-2">{{ $collateral->documents->count() }}</span>
                                    @if($collateral->documents->count() > 0)
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewDocuments({{ $collateral->id }})">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $collateral->status === 'available' ? 'badge-success' : ($collateral->status === 'pledged' ? 'badge-warning' : 'badge-secondary') }}">
                                    {{ ucfirst($collateral->status) }}
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button type="button" class="btn btn-sm btn-secondary view-btn" 
                                        data-id="{{ $collateral->id }}"
                                        data-customer-name="{{ optional($collateral->customer)->name ?? 'N/A' }}"
                                        data-collateral-type-name="{{ optional($collateral->collateralType)->name ?? 'N/A' }}"
                                        data-reference-number="{{ $collateral->reference_number ?? '' }}"
                                        data-description="{{ $collateral->description }}"
                                        data-location="{{ $collateral->location ?? '' }}"
                                        data-estimated-value="{{ $collateral->estimated_value }}"
                                        data-valuation-date="{{ $collateral->valuation_date ?? '' }}"
                                        data-valued-by="{{ $collateral->valued_by ?? '' }}"
                                        data-is-insured="{{ $collateral->is_insured }}"
                                        data-insurance-expiry-date="{{ $collateral->insurance_expiry_date ?? '' }}"
                                        data-status="{{ $collateral->status }}"
                                        data-is-active="{{ $collateral->is_active }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-primary edit-btn" 
                                        data-id="{{ $collateral->id }}"
                                        data-customer-id="{{ $collateral->customer_id }}"
                                        data-collateral-type-id="{{ $collateral->collateral_type_id }}"
                                        data-reference-number="{{ $collateral->reference_number ?? '' }}"
                                        data-description="{{ $collateral->description }}"
                                        data-location="{{ $collateral->location ?? '' }}"
                                        data-estimated-value="{{ $collateral->estimated_value }}"
                                        data-valuation-date="{{ $collateral->valuation_date ?? '' }}"
                                        data-valued-by="{{ $collateral->valued_by ?? '' }}"
                                        data-is-insured="{{ $collateral->is_insured }}"
                                        data-insurance-expiry-date="{{ $collateral->insurance_expiry_date ?? '' }}"
                                        data-status="{{ $collateral->status }}"
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
                            <td colspan="9" class="text-center">No customer collaterals found. Click 'New Collateral' to add one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Customer Collateral Modal -->
<div class="modal fade" id="viewCustomerCollateralModal" tabindex="-1" role="dialog" aria-labelledby="viewCustomerCollateralModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title text-light" id="viewCustomerCollateralModalLabel">Customer Collateral Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Customer:</strong> <span id="v_customer">-</span></div>
                        <div class="mb-2"><strong>Collateral Type:</strong> <span id="v_collateral_type">-</span></div>
                        <div class="mb-2"><strong>Description:</strong> <span id="v_description">-</span></div>
                        <div class="mb-2"><strong>Reference:</strong> <span id="v_reference">-</span></div>
                        <div class="mb-2"><strong>Estimated Value:</strong> <span id="v_estimated_value">-</span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Location:</strong> <span id="v_location">-</span></div>
                        <div class="mb-2"><strong>Valuation Date:</strong> <span id="v_valuation_date">-</span></div>
                        <div class="mb-2"><strong>Valued By:</strong> <span id="v_valued_by">-</span></div>
                        <div class="mb-2"><strong>Insurance:</strong> <span id="v_insurance">-</span></div>
                        <div class="mb-2"><strong>Status:</strong> <span id="v_status" class="badge badge-secondary">-</span> <strong class="ml-2">Active:</strong> <span id="v_active" class="badge badge-secondary">-</span></div>
                    </div>
                </div>
                <hr>
                <h6 class="mb-3">Documents</h6>
                <div id="viewDocumentsList">
                    <p class="text-muted">No documents loaded.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
    </div>

<!-- Add Customer Collateral Modal -->
<div class="modal fade" id="addCustomerCollateralModal" tabindex="-1" role="dialog" aria-labelledby="addCustomerCollateralModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <form id="addCustomerCollateralForm" action="{{ route('loans.customer_collaterals.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-light" id="addCustomerCollateralModalLabel">Add New Customer Collateral</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                <select class="form-control" id="customer_id" name="customer_id" required>
                                    <option value="">-- Select Customer --</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="collateral_type_id">Collateral Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="collateral_type_id" name="collateral_type_id" required>
                                    <option value="">-- Select Collateral Type --</option>
                                    @foreach($collateralTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reference_number">Reference Number</label>
                                <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                       placeholder="e.g. Title Deed No, Logbook No">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="estimated_value">Estimated Value <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="estimated_value" name="estimated_value" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="description" name="description" required 
                               placeholder="e.g. Toyota Hilux 2019, House at Sinza">
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <textarea class="form-control" id="location" name="location" rows="2" 
                                  placeholder="Physical location of the asset"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="valuation_date">Valuation Date</label>
                                <input type="date" class="form-control" id="valuation_date" name="valuation_date">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="valued_by">Valued By</label>
                                <input type="text" class="form-control" id="valued_by" name="valued_by" 
                                       placeholder="Internal officer or external valuer">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="status">Status <span class="text-danger">*</span></label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="available">Available</option>
                                    <option value="pledged">Pledged</option>
                                    <option value="released">Released</option>
                                    <option value="seized">Seized</option>
                                    <option value="disposed">Disposed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_insured" name="is_insured" value="1">
                                <label class="form-check-label" for="is_insured">Is Insured</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="insuranceFields" style="display: none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="insurance_expiry_date">Insurance Expiry Date</label>
                                <input type="date" class="form-control" id="insurance_expiry_date" name="insurance_expiry_date">
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label for="documents">Upload Documents (Optional)</label>
                        <div class="border rounded p-3 bg-light">
                            <div id="documentUploads">
                                <div class="document-row mb-2">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <select name="document_types[]" class="form-control document-type-select">
                                                <option value="">Select Document Type</option>
                                                <option value="title_deed">Title Deed</option>
                                                <option value="logbook">Vehicle Logbook</option>
                                                <option value="photo">Asset Photo</option>
                                                <option value="valuation_report">Valuation Report</option>
                                                <option value="insurance">Insurance Policy</option>
                                                <option value="ownership_proof">Ownership Proof</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="file" name="documents[]" class="form-control document-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger btn-sm remove-document" onclick="removeDocumentRow(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addDocumentRow()">
                                <i class="fas fa-plus"></i> Add More Documents
                            </button>
                        </div>
                        <small class="text-muted">Max 5 files, 10MB each. Supported: PDF, JPG, PNG, DOC, DOCX</small>
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

<!-- Edit Customer Collateral Modal -->
<div class="modal fade" id="editCustomerCollateralModal" tabindex="-1" role="dialog" aria-labelledby="editCustomerCollateralModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <form id="editCustomerCollateralForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-light" id="editCustomerCollateralModalLabel">Edit Customer Collateral</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_customer_id">Customer <span class="text-danger">*</span></label>
                                <select class="form-control" id="edit_customer_id" name="customer_id" required>
                                    <option value="">-- Select Customer --</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_collateral_type_id">Collateral Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="edit_collateral_type_id" name="collateral_type_id" required>
                                    <option value="">-- Select Collateral Type --</option>
                                    @foreach($collateralTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_reference_number">Reference Number</label>
                                <input type="text" class="form-control" id="edit_reference_number" name="reference_number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_estimated_value">Estimated Value <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_estimated_value" name="estimated_value" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_description" name="description" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_location">Location</label>
                        <textarea class="form-control" id="edit_location" name="location" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_valuation_date">Valuation Date</label>
                                <input type="date" class="form-control" id="edit_valuation_date" name="valuation_date">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_valued_by">Valued By</label>
                                <input type="text" class="form-control" id="edit_valued_by" name="valued_by">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_status">Status <span class="text-danger">*</span></label>
                                <select class="form-control" id="edit_status" name="status" required>
                                    <option value="available">Available</option>
                                    <option value="pledged">Pledged</option>
                                    <option value="released">Released</option>
                                    <option value="seized">Seized</option>
                                    <option value="disposed">Disposed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_is_insured" name="is_insured" value="1">
                                <label class="form-check-label" for="edit_is_insured">Is Insured</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1">
                                <label class="form-check-label" for="edit_is_active">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="editInsuranceFields" style="display: none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="edit_insurance_expiry_date">Insurance Expiry Date</label>
                                <input type="date" class="form-control" id="edit_insurance_expiry_date" name="insurance_expiry_date">
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label for="edit_documents">Upload Additional Documents (Optional)</label>
                        <div class="border rounded p-3 bg-light">
                            <div id="editDocumentUploads">
                                <div class="document-row mb-2">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <select name="document_types[]" class="form-control document-type-select">
                                                <option value="">Select Document Type</option>
                                                <option value="title_deed">Title Deed</option>
                                                <option value="logbook">Vehicle Logbook</option>
                                                <option value="photo">Asset Photo</option>
                                                <option value="valuation_report">Valuation Report</option>
                                                <option value="insurance">Insurance Policy</option>
                                                <option value="ownership_proof">Ownership Proof</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="file" name="documents[]" class="form-control document-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger btn-sm remove-document" onclick="removeDocumentRow(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addEditDocumentRow()">
                                <i class="fas fa-plus"></i> Add More Documents
                            </button>
                        </div>
                        <small class="text-muted">Max 5 files, 10MB each. Supported: PDF, JPG, PNG, DOC, DOCX</small>
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

<!-- Documents View Modal -->
<div class="modal fade" id="documentsModal" tabindex="-1" role="dialog" aria-labelledby="documentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentsModalLabel">Collateral Documents</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="documentsList">
                    <!-- Documents will be loaded here via AJAX -->
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
    // Ensure CSRF token is sent with all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    });
    // Initialize DataTable
    $('#customerCollateralTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [0, 8] }, // Disable sorting on action column (#, Actions)
            { searchable: false, targets: [0, 6, 7, 8] } // Disable search on #, Documents, Status, Actions
        ],
        order: [[1, 'asc']] // Sort by customer name by default
    });

    // Handle View button click
    $('.view-btn').click(function() {
        var id = $(this).data('id');
        var customerName = $(this).data('customer-name');
        var collateralTypeName = $(this).data('collateral-type-name');
        var referenceNumber = $(this).data('reference-number') || 'N/A';
        var description = $(this).data('description') || 'N/A';
        var location = $(this).data('location') || 'N/A';
        var estimatedValue = $(this).data('estimated-value');
        var valuationDate = $(this).data('valuation-date') || 'N/A';
        var valuedBy = $(this).data('valued-by') || 'N/A';
        var isInsured = !!$(this).data('is-insured');
        var insuranceExpiryDate = $(this).data('insurance-expiry-date') || 'N/A';
        var status = $(this).data('status');
        var isActive = !!$(this).data('is-active');

        // Fill primary details
        $('#v_customer').text(customerName);
        $('#v_collateral_type').text(collateralTypeName);
        $('#v_description').text(description);
        $('#v_reference').text(referenceNumber);
        var formattedValue = (estimatedValue !== undefined && estimatedValue !== null && estimatedValue !== '') ?
            parseFloat(estimatedValue).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00';
        $('#v_estimated_value').text(formattedValue);
        $('#v_location').text(location);
        $('#v_valuation_date').text(valuationDate);
        $('#v_valued_by').text(valuedBy);
        $('#v_insurance').text(isInsured ? ('Insured (Expiry: ' + insuranceExpiryDate + ')') : 'Not Insured');

        // Status badges
        var $status = $('#v_status');
        $status.removeClass('badge-success badge-warning badge-secondary');
        if (status === 'available') $status.addClass('badge-success');
        else if (status === 'pledged') $status.addClass('badge-warning');
        else $status.addClass('badge-secondary');
        $status.text((status || '').charAt(0).toUpperCase() + (status || '').slice(1));

        var $active = $('#v_active');
        $active.removeClass('badge-success badge-secondary');
        if (isActive) { $active.addClass('badge-success').text('Yes'); } else { $active.addClass('badge-secondary').text('No'); }

        // Load documents list
        $('#viewDocumentsList').html('<p class="text-muted">Loading documents...</p>');
        $.ajax({
            url: '/loans/loans_settings/customer_collaterals/' + id + '/documents',
            type: 'GET',
            success: function(response) {
                var documentsHtml = '';
                if (response.documents && response.documents.length > 0) {
                    documentsHtml = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Type</th><th>File Name</th><th>Size</th><th>Uploaded By</th><th>Verified</th><th>Actions</th></tr></thead><tbody>';
                    response.documents.forEach(function(doc) {
                        documentsHtml += '\n<tr>' +
                            '<td>' + (doc.document_type_display || '-') + '</td>' +
                            '<td>' + (doc.original_filename || '-') + '</td>' +
                            '<td>' + (doc.formatted_file_size || '-') + '</td>' +
                            '<td>' + (doc.uploaded_by ? doc.uploaded_by.name : 'N/A') + '</td>' +
                            '<td><span class="badge ' + (doc.is_verified ? 'badge-success' : 'badge-secondary') + '">' + (doc.is_verified ? 'Verified' : 'Not Verified') + '</span></td>' +
                            '<td>' +
                                '<button type="button" class="btn btn-sm btn-primary" onclick="downloadDocument(' + doc.id + ')"><i class="fas fa-download"></i></button> ' +
                                '<button type="button" class="btn btn-sm btn-info" onclick="verifyDocument(' + doc.id + ')" ' + (doc.is_verified ? 'disabled' : '') + '><i class="fas fa-check"></i></button> ' +
                                '<button type="button" class="btn btn-sm btn-danger" onclick="deleteDocument(' + doc.id + ')"><i class="fas fa-trash"></i></button>' +
                            '</td>' +
                        '</tr>';
                    });
                    documentsHtml += '</tbody></table></div>';
                } else {
                    documentsHtml = '<p class="text-muted">No documents found for this collateral.</p>';
                }
                $('#viewDocumentsList').html(documentsHtml);
            },
            error: function() {
                $('#viewDocumentsList').html('<p class="text-danger">Failed to load documents.</p>');
            }
        });

        // Show modal after populating
        $('#viewCustomerCollateralModal').modal('show');
    });

    // Handle insurance checkbox toggle
    $('#is_insured, #edit_is_insured').change(function() {
        var isEdit = $(this).attr('id') === 'edit_is_insured';
        var targetFields = isEdit ? '#editInsuranceFields' : '#insuranceFields';
        
        if ($(this).is(':checked')) {
            $(targetFields).show();
        } else {
            $(targetFields).hide();
        }
    });

    // Handle Add form submission
    $('#addCustomerCollateralForm').submit(function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop('disabled', true);
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: { 'Accept': 'application/json' },
            success: function(response) {
                if (response.success || response.redirect) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Customer collateral created successfully!',
                        showConfirmButton: true,
                        timerProgressBar: true,
                        timer: 2000
                    }).then(() => {
                        resetAddCollateralForm();
                        // Refresh page so the new collateral appears in the table
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message || 'Failed to create customer collateral',
                        showConfirmButton: true
                    });
                }
                $submitBtn.prop('disabled', false);
            },
            error: function(xhr) {
                var errorMessage = 'Failed to create customer collateral';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    console.error('Server response:', xhr.responseText);
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage,
                    showConfirmButton: true
                });
                $submitBtn.prop('disabled', false);
            }
        });
    });

    // Reset Add Collateral form after successful submit so user can add another
    function resetAddCollateralForm() {
        var $form = $('#addCustomerCollateralForm');
        // Reset primitive inputs
        $form[0].reset();
        // Clear selects explicitly
        $form.find('select[name="customer_id"]').val('');
        $form.find('select[name="collateral_type_id"]').val('');
        $form.find('select.document-type-select').val('');
        // Uncheck checkboxes
        $form.find('#is_insured').prop('checked', false);
        $form.find('#is_active').prop('checked', true);
        // Hide insurance fields
        $('#insuranceFields').hide();
        // Reset document uploads to a single empty row
        var docRow = `
            <div class="document-row mb-2">
                <div class="row">
                    <div class="col-md-6">
                        <select name="document_types[]" class="form-control document-type-select">
                            <option value="">Select Document Type</option>
                            <option value="title_deed">Title Deed</option>
                            <option value="logbook">Vehicle Logbook</option>
                            <option value="photo">Asset Photo</option>
                            <option value="valuation_report">Valuation Report</option>
                            <option value="insurance">Insurance Policy</option>
                            <option value="ownership_proof">Ownership Proof</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="file" name="documents[]" class="form-control document-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-document" onclick="removeDocumentRow(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#documentUploads').html(docRow);
        // Keep modal open for quick subsequent entry
    }

    // Handle Edit form submission
    $('#editCustomerCollateralForm').submit(function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop('disabled', true);
        
        $.ajax({
            url: $(this).attr('action'),
            // Use POST + _method=PUT for reliable CSRF handling with FormData
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: { 'Accept': 'application/json' },
            success: function(response) {
                if (response.success || response.redirect) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Customer collateral updated successfully!',
                        showConfirmButton: true,
                        timerProgressBar: true,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message || 'Failed to update customer collateral',
                        showConfirmButton: true
                    });
                }
                $submitBtn.prop('disabled', false);
            },
            error: function(xhr) {
                var errorMessage = 'Failed to update customer collateral';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    console.error('Server response:', xhr.responseText);
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage,
                    showConfirmButton: true
                });
                $submitBtn.prop('disabled', false);
            }
        });
    });

    // Handle edit button click
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var customerId = $(this).data('customer-id');
        var collateralTypeId = $(this).data('collateral-type-id');
        var referenceNumber = $(this).data('reference-number');
        var description = $(this).data('description');
        var location = $(this).data('location');
        var estimatedValue = $(this).data('estimated-value');
        var valuationDate = $(this).data('valuation-date');
        var valuedBy = $(this).data('valued-by');
        var isInsured = $(this).data('is-insured');
        var insuranceExpiryDate = $(this).data('insurance-expiry-date');
        var status = $(this).data('status');
        var isActive = $(this).data('is-active');
        
        $('#editCustomerCollateralForm').attr('action', '/loans/loans_settings/customer_collaterals/' + id);
        $('#edit_customer_id').val(customerId);
        $('#edit_collateral_type_id').val(collateralTypeId);
        $('#edit_reference_number').val(referenceNumber);
        $('#edit_description').val(description);
        $('#edit_location').val(location);
        $('#edit_estimated_value').val(estimatedValue);
        $('#edit_valuation_date').val(valuationDate);
        $('#edit_valued_by').val(valuedBy);
        $('#edit_is_insured').prop('checked', isInsured);
        $('#edit_insurance_expiry_date').val(insuranceExpiryDate);
        $('#edit_status').val(status);
        $('#edit_is_active').prop('checked', isActive);
        
        // Toggle insurance fields based on checkbox
        if (isInsured) {
            $('#editInsuranceFields').show();
        } else {
            $('#editInsuranceFields').hide();
        }
        
        $('#editCustomerCollateralModal').modal('show');
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
                    url: '/loans/loans_settings/customer_collaterals/' + id,
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
                            'An error occurred while deleting the customer collateral.',
                            'error'
                        );
                    }
                });
            }
        });
    });

    // Document management functions
    function addDocumentRow() {
        var documentCount = $('#documentUploads .document-row').length;
        if (documentCount >= 5) {
            Swal.fire('Limit Reached', 'Maximum 5 documents allowed', 'warning');
            return;
        }
        
        var newRow = `
            <div class="document-row mb-2">
                <div class="row">
                    <div class="col-md-6">
                        <select name="document_types[]" class="form-control document-type-select">
                            <option value="">Select Document Type</option>
                            <option value="title_deed">Title Deed</option>
                            <option value="logbook">Vehicle Logbook</option>
                            <option value="photo">Asset Photo</option>
                            <option value="valuation_report">Valuation Report</option>
                            <option value="insurance">Insurance Policy</option>
                            <option value="ownership_proof">Ownership Proof</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="file" name="documents[]" class="form-control document-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-document" onclick="removeDocumentRow(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#documentUploads').append(newRow);
    }

    window.removeDocumentRow = function(button) {
        $(button).closest('.document-row').remove();
    }

    window.addEditDocumentRow = function() {
        var documentCount = $('#editDocumentUploads .document-row').length;
        if (documentCount >= 5) {
            Swal.fire('Limit Reached', 'Maximum 5 documents allowed', 'warning');
            return;
        }
        
        var newRow = `
            <div class="document-row mb-2">
                <div class="row">
                    <div class="col-md-6">
                        <select name="document_types[]" class="form-control document-type-select">
                            <option value="">Select Document Type</option>
                            <option value="title_deed">Title Deed</option>
                            <option value="logbook">Vehicle Logbook</option>
                            <option value="photo">Asset Photo</option>
                            <option value="valuation_report">Valuation Report</option>
                            <option value="insurance">Insurance Policy</option>
                            <option value="ownership_proof">Ownership Proof</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="file" name="documents[]" class="form-control document-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-document" onclick="removeDocumentRow(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#editDocumentUploads').append(newRow);
    }

    function removeDocumentRow(button) {
        $(button).closest('.document-row').remove();
    }

    window.viewDocuments = function(collateralId) {
        $.ajax({
            url: '/loans/loans_settings/customer_collaterals/' + collateralId + '/documents',
            type: 'GET',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                var documentsHtml = '';
                if (response.documents && response.documents.length > 0) {
                    documentsHtml = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Document Type</th><th>File Name</th><th>Size</th><th>Uploaded By</th><th>Verified</th><th>Actions</th></tr></thead><tbody>';
                    
                    response.documents.forEach(function(doc) {
                        documentsHtml += `
                            <tr>
                                <td>${doc.document_type_display}</td>
                                <td>${doc.original_filename}</td>
                                <td>${doc.formatted_file_size}</td>
                                <td>${doc.uploaded_by ? doc.uploaded_by.name : 'N/A'}</td>
                                <td>
                                    <span class="badge ${doc.is_verified ? 'badge-success' : 'badge-secondary'}">
                                        ${doc.is_verified ? 'Verified' : 'Not Verified'}
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="downloadDocument(${doc.id})">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info" onclick="verifyDocument(${doc.id})" ${doc.is_verified ? 'disabled' : ''}>
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteDocument(${doc.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    documentsHtml += '</tbody></table></div>';
                } else {
                    documentsHtml = '<p class="text-muted">No documents found for this collateral.</p>';
                }
                
                $('#documentsList').html(documentsHtml);
                $('#documentsModal').modal('show');
            },
            error: function(xhr) {
                Swal.fire('Error', 'Failed to load documents', 'error');
            }
        });
    }

    window.downloadDocument = function(documentId) {
        window.open('/loans/loans_settings/customer_collaterals/documents/' + documentId + '/download', '_blank');
    }

    window.verifyDocument = function(documentId) {
        Swal.fire({
            title: 'Verify Document?',
            text: 'Are you sure you want to verify this document?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, verify it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/loans/loans_settings/customer_collaterals/documents/' + documentId + '/verify',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        Swal.fire('Success!', response.message, 'success').then(() => {
                            location.reload();
                        });
                    },
                    error: function(xhr) {
                        Swal.fire('Error', 'Failed to verify document', 'error');
                    }
                });
            }
        });
    }

    window.deleteDocument = function(documentId) {
        Swal.fire({
            title: 'Delete Document?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/loans/loans_settings/customer_collaterals/documents/' + documentId,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        Swal.fire('Deleted!', response.message, 'success').then(() => {
                            location.reload();
                        });
                    },
                    error: function(xhr) {
                        Swal.fire('Error', 'Failed to delete document', 'error');
                    }
                });
            }
        });
    }

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

// Document management functions (outside document.ready scope)
function addDocumentRow() {
    var documentCount = $('#documentUploads .document-row').length;
    if (documentCount >= 5) {
        Swal.fire('Limit Reached', 'Maximum 5 documents allowed', 'warning');
        return;
    }
    
    var newRow = `
        <div class="document-row mb-2">
            <div class="row">
                <div class="col-md-6">
                    <select name="document_types[]" class="form-control document-type-select">
                        <option value="">Select Document Type</option>
                        <option value="title_deed">Title Deed</option>
                        <option value="logbook">Vehicle Logbook</option>
                        <option value="photo">Asset Photo</option>
                        <option value="valuation_report">Valuation Report</option>
                        <option value="insurance">Insurance Policy</option>
                        <option value="ownership_proof">Ownership Proof</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <input type="file" name="documents[]" class="form-control document-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm remove-document" onclick="removeDocumentRow(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    $('#documentUploads').append(newRow);
}

</script>
@endpush