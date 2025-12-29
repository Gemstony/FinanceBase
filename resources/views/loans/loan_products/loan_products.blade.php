@extends('adminlte::page')

@section('title', 'Customers - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-users"></i> Customers Management</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-users"></i> Customers</h1>
                <p class="mb-0 text-light">Managing customers for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('customers.subshops') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Change Shop
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i>
                    Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('customers.subshops') }}">Choose Shop</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Customers</li>
        </ol>
    </nav>
</div>
@stop

@section('content')
<div class="container-fluid">

    <!-- Import Customers Modal -->
    <div class="modal fade" id="importCustomersModal" tabindex="-1" role="dialog"
        aria-labelledby="importCustomersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="importCustomersModalLabel">
                        <i class="fas fa-file-import mr-1"></i> Bulk Import Customers (CSV)
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="importCustomersForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                    <div class="modal-body">
                        <div class="alert alert-info border mb-3" role="alert">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle fa-lg mr-2"></i>
                                <div>
                                    <strong>Instructions:</strong>
                                    <ul class="mb-0 mt-1 pl-3">
                                        <li>Accepted file type: CSV only.</li>
                                        <li>Required column: <code>name</code>.</li>
                                        <li>Set <code>is_active</code> as 1 (active) or 0 (inactive). If omitted,
                                            customers are set active.</li>
                                        <li>Use the <strong>Download Sample</strong> to get the correct column order.
                                        </li>
                                        <li><i class="fas fa-info-circle text-warning"></i> Note: make sure the
                                            <code>Birth date</code> is in this format <code>YYYY-MM-DD</code>, Example:
                                            <code>2002-01-25</code></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="customers_import_file">Choose CSV File <span
                                        class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="customers_import_file" name="import_file"
                                    accept=".csv" required>
                            </div>
                            <div class="form-group col-md-4 d-flex align-items-end">
                                <a href="{{ route('customers.import.sample') }}"
                                    class="btn btn-outline-success btn-block">
                                    <i class="fas fa-download"></i> Download Sample
                                </a>
                            </div>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="customers_has_headers"
                                name="has_headers" value="1" checked>
                            <label class="custom-control-label" for="customers_has_headers">First row contains
                                headers</label>
                        </div>

                        <div id="customersImportErrors" class="d-none">
                            <div class="alert alert-warning mb-0">
                                <strong>Some rows were skipped:</strong>
                                <ul class="mb-0" id="customersImportErrorsList"></ul>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="startCustomersImportBtn"
                            onclick="submitImportCustomers()">
                            <i class="fas fa-upload"></i> Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card">
        <div class="card-body table-responsive p-3">
            <table class="table table-hover text-nowrap" id="customersTable">
                <thead class="thead-dark">
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Category</th>
                        <th>Region</th>
                        <th>District</th>
                        <th>Ward</th>
                        <th>Street</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if($customers->count() > 0)
                    @foreach($customers as $customer)
                    <tr>
                        <td>#{{ $loop->iteration }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-primary text-white mr-2"
                                    style="width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                    {{ strtoupper(substr( $customer->name , 0, 1)) }}
                                </div>
                                <div>
                                    <strong>{{ $customer->name }}</strong>
                                </div>
                            </div>
                        </td>
                        <td>{{ $customer->gender ?? 'N/A' }}</td>
                        <td>
                            <div class="d-flex align-items-left">
                                <div>
                                    <span><i class="fas fa-phone"></i> {{ $customer->phone ?? 'N/A' }}</span>
                                    @if($customer->altenative_phone)
                                    <br><small class="text-muted"><i class="fas fa-phone"></i>
                                        {{ $customer->altenative_phone }}</small>
                                    @endif

                                    @if($customer->email)
                                    <br><small class="text-muted"><i class="fas fa-google"></i>
                                        {{ $customer->email }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>{{ $customer->category ?? 'N/A' }}</td>
                        <td>{{ $customer->region  ?? 'N/A'}}</td>
                        <td>{{ $customer->district ?? 'N/A' }}</td>
                        <td>{{ $customer->ward ?? 'N/A' }}</td>
                        <td>{{ $customer->street ?? 'N/A' }}</td>

                        <td>
                            <span class="badge {{ $customer->is_active ? 'badge-success' : 'badge-secondary' }}">
                                {{ $customer->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>

                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="#" class="btn btn-sm btn-primary view-customer">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <button type="button" class="btn btn-sm btn-info" data-toggle="modal"
                                    data-target="#editCustomerModal" data-id="{{ $customer->id }}"
                                    data-name="{{ $customer->name }}" data-gender="{{ $customer->gender }}"
                                    data-email="{{ $customer->email }}" data-phone="{{ $customer->phone }}"
                                    data-altenative_phone="{{ $customer->altenative_phone }}"
                                    data-birth_date="{{ $customer->birth_date }}" data-region="{{ $customer->region }}"
                                    data-district="{{ $customer->district }}" data-ward="{{ $customer->ward }}"
                                    data-street="{{ $customer->street }}" data-house_no="{{ $customer->house_no }}"
                                    data-work="{{ $customer->work }}" data-work_address="{{ $customer->work_address }}"
                                    data-id_type="{{ $customer->id_type }}" data-id_number="{{ $customer->id_number }}"
                                    data-category="{{ $customer->category }}"
                                    data-active="{{ $customer->is_active ? '1' : '0' }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                             
                                <button type="button" class="btn btn-sm btn-danger" title="Delete"
                                    onclick="deleteCustomer({{ $customer->id }}, '{{ $customer->name }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                              
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>

            <!-- Pagination -->
            @if($customers->hasPages())
            <div class="d-flex justify-content-center mt-3">
                {{ $customers->appends(request()->query())->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog" aria-labelledby="addCustomerModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <form action="{{ route('customers.store') }}" method="POST" id="addCustomerForm">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-header " style="background: linear-gradient(135deg, #004e92, #000428); color: white;">
                    <h5 class="modal-title text-white" id="addCustomerModalLabel">Add New Customer</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" class="text-white">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Customer Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="gender">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-control">
                                    <option value="" selected disabled>Choose Gender</option>
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone" name="phone" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="altenative_phone">Altenative phone </label>
                                <input type="text" class="form-control" id="altenative_phone" name="altenative_phone">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="birth_date">Birth Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="birth_date" name="birth_date" required>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="region">Region <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="region" name="region" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="district">District <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="district" name="district" required>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ward">Ward <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ward" name="ward" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="street">Street <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="street" name="street" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="house_no">House No <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="house_no" name="house_no" required>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="work">Work </label>
                                <input type="text" class="form-control" id="work" name="work">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="work_address">Work address </label>
                                <input type="text" class="form-control" id="work_address" name="work_address">
                            </div>
                        </div>
                    </div>



                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_type">ID type </label>
                                <select name="id_type" class="form-control">
                                    <option value="" selected disabled>Choose ID</option>
                                    <option value="NIDA">NIDA Id</option>
                                    <option value="Driving Lesence">Driving Lesence Id</option>
                                    <option value="Voter Id">Voter Id</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_number">ID number </label>
                                <input type="text" class="form-control" id="id_number" name="id_number">
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-control">
                                    <option value="" selected disabled>Choose Category</option>
                                    <option value="borrower">Borrower</option>
                                    <option value="guarantor">Guarantor</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                    value="1" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" role="dialog" aria-labelledby="editCustomerModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <form id="editCustomerForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header" style="background: linear-gradient(135deg, #004e92, #000428); color: white;">
                    <h5 class="modal-title text-light" id="editCustomerModalLabel">Edit Customer</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" class="text-light">&times;</span>
                    </button>
                </div>
                <div class="modal-body">


                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Customer Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="gender">Gender <span class="text-danger">*</span></label>
                                <select id="edit_gender" name="gender" class="form-control">
                                    <option value="" selected disabled>Choose Gender</option>
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_phone" name="phone" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="altenative_phone">Altenative phone </label>
                                <input type="text" class="form-control" id="edit_altenative_phone"
                                    name="altenative_phone">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="birth_date">Birth Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_birth_date" name="birth_date" required>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="region">Region <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_region" name="region" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="district">District <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_district" name="district" required>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ward">Ward <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_ward" name="ward" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="street">Street <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_street" name="street" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="house_no">House No <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_house_no" name="house_no" required>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="work">Work </label>
                                <input type="text" class="form-control" id="edit_work" name="work">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="work_address">Work address </label>
                                <input type="text" class="form-control" id="edit_work_address" name="work_address">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_type">ID type </label>
                                <select name="id_type" id="edit_id_type" class="form-control">
                                    <option value="" selected disabled>Choose ID</option>
                                    <option value="NIDA">NIDA Id</option>
                                    <option value="Driving Lesence">Driving Lesence Id</option>
                                    <option value="Voter Id">Voter Id</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_number">ID number </label>
                                <input type="text" class="form-control" id="edit_id_number" name="id_number">
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category">Category <span class="text-danger">*</span></label>
                                <select name="category" id="edit_category" class="form-control">
                                    <option value="" selected disabled>Choose Category</option>
                                    <option value="borrower">Borrower</option>
                                    <option value="guarantor">Guarantor</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active"
                                    value="1" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                </div>



                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCustomerModal" tabindex="-1" role="dialog" aria-labelledby="deleteCustomerModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCustomerModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete customer: <strong id="deleteCustomerName"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteCustomerForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
.table th {
    white-space: nowrap;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-size: 0.8rem;
    padding: 0.35em 0.65em;
}

.action-buttons .btn {
    margin: 0 2px;
}

/* Loading state for tabs */
.tab-pane.loading {
    position: relative;
    min-height: 200px;
}

.tab-pane.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: translate(-50%, -50%) rotate(0deg);
    }

    100% {
        transform: translate(-50%, -50%) rotate(360deg);
    }
}

/* Modal styles */
.modal-header.bg-primary {
    border-radius: 0.3rem 0.3rem 0 0;
}

.nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    font-weight: 600;
    border-bottom: 3px solid #007bff;
}

.tab-content {
    border: 1px solid #dee2e6;
    border-top: none;
}

/* Responsive tables */
.table-responsive {
    min-height: 200px;
}

/* Stats cards */
.stat-card {
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
</style>
@endpush

@push('js')

<!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script> -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1"></script>


<script>
// Global variables
let customerCharts = {};

$(function() {
    // Initialize DataTable

    var table = $('#customersTable').DataTable({
        "order": [
            [1, "desc"]
        ],
        "pageLength": 25,
        "language": {
            "emptyTable": "No customers found.",
            "zeroRecords": "No matching customers found.",
            "search": "Search customers:",
            "lengthMenu": "Show _MENU_ customers per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ customers",
            "infoEmpty": "No customers available",
            "infoFiltered": "(filtered from _MAX_ total customers)"
        },
        "initComplete": function() {
            // Add 'No data' message if the table is empty
            if (this.api().data().length === 0) {
                $(this).find('tbody').html(
                    '<tr>' +
                    '<td colspan="13" class="text-center">No customers found.</td>' +
                    '</tr>'
                );

                // Hide the DataTable controls when no data
                $(this).closest('.dataTables_wrapper').find(
                        '.dataTables_paginate, .dataTables_length, .dataTables_filter')
                    .addClass('d-none');
            }
        }
    });



    // Handle edit from view
    $('#editFromView').on('click', function() {
        const customerId = $(this).data('id');
        const btn = $(
            `button[data-id="${customerId}"][data-toggle="modal"][data-target="#editCustomerModal"]`
            );
        $('#viewCustomerModal').modal('hide');
        btn.trigger('click');
    });

  
});



// Initialize DataTable and other existing code
$(document).ready(function() {
    // Reset form when add customer modal is opened
    $('#addCustomerModal').on('show.bs.modal', function() {
        document.getElementById('addCustomerForm').reset();
    });

    // Handle edit customer modal
    $('#editCustomerModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var modal = $(this);

        // Set the form action URL
        modal.find('form').attr('action', '/admin/sales/customers/' + id);

        // Fill in the form fields
        modal.find('#edit_name').val(button.data('name'));
        modal.find('#edit_gender').val(button.data('gender') || '');
        modal.find('#edit_birth_date').val(button.data('birth_date') || '');
        modal.find('#edit_email').val(button.data('email') || '');
        modal.find('#edit_phone').val(button.data('phone') || '');
        modal.find('#edit_altenative_phone').val(button.data('altenative_phone') || '');

        modal.find('#edit_region').val(button.data('region'));
        modal.find('#edit_district').val(button.data('district') || '');
        modal.find('#edit_ward').val(button.data('ward') || '');
        modal.find('#edit_street').val(button.data('street') || '');
        modal.find('#edit_house_no').val(button.data('house_no') || '');

        modal.find('#edit_work').val(button.data('work'));
        modal.find('#edit_work_address').val(button.data('work_address') || '');
        modal.find('#edit_id_type').val(button.data('id_type') || '');
        modal.find('#edit_id_number').val(button.data('id_number') || '');
        modal.find('#edit_category').val(button.data('category') || '');




        modal.find('#edit_is_active').prop('checked', button.data('active') == '1');
    });

    // Handle delete customer modal
    window.deleteCustomer = function(id, name) {
        $('#deleteCustomerName').text(name);
        $('#deleteCustomerForm').attr('action', '/admin/sales/customers/' + id);
        $('#deleteCustomerModal').modal('show');
    };

    // Handle form submission with AJAX for better UX
    $('#addCustomerForm, #editCustomerForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = form.serialize();
        var url = form.attr('action');
        var method = form.attr('method') || 'POST';
        var isEdit = method === 'PUT' || method === 'PATCH';

        $.ajax({
            url: url,
            type: method,
            data: formData,
            success: function(response) {
                // Show success message
                var message = isEdit ? 'Customer updated successfully!' :
                    'Customer added successfully!';
                var icon = 'success';

                if (response.message) {
                    message = response.message;
                }

                // Show success message
                Swal.fire({
                    icon: icon,
                    title: 'Success!',
                    text: message,
                    timer: 3000,
                    showConfirmButton: false
                });

                // Close the modal
                if (isEdit) {
                    $('#editCustomerModal').modal('hide');
                } else {
                    $('#addCustomerModal').modal('hide');
                }

                // Reload the page to see the changes
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            },
            error: function(xhr) {
                var errorMessage = 'An error occurred. Please try again.';
                var errors = xhr.responseJSON && xhr.responseJSON.errors;

                if (errors) {
                    errorMessage = '';
                    $.each(errors, function(key, value) {
                        errorMessage += value[0] + '\n';
                    });
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage
                });
            }
        });
    });

    // Handle delete form submission
    $('#deleteCustomerForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var url = form.attr('action');

        $.ajax({
            url: url,
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                $('#deleteCustomerModal').modal('hide');

                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'Customer has been deleted.',
                    timer: 2000,
                    showConfirmButton: false
                });

                // Reload the page after a short delay
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while deleting the customer.'
                });
            }
        });
    });
});

// Bulk import customers via CSV
function submitImportCustomers() {
    const form = document.getElementById('importCustomersForm');
    const formData = new FormData(form);
    const btn = document.getElementById('startCustomersImportBtn');
    const errorsBox = document.getElementById('customersImportErrors');
    const errorsList = document.getElementById('customersImportErrorsList');

    errorsBox.classList.add('d-none');
    errorsList.innerHTML = '';

    btn.disabled = true;
    btn.innerHTML =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Importing...';

    fetch("{{ route('customers.import') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: formData
        })
        .then(async (res) => {
            const data = await res.json().catch(() => ({
                success: false,
                message: 'Invalid server response'
            }));
            if (!res.ok || !data.success) {
                throw data;
            }
            return data;
        })
        .then((data) => {
            Swal.fire({
                icon: 'success',
                title: 'Import Completed',
                html: `${data.message}<br><small>Imported: ${data.imported}, Skipped: ${data.skipped}</small>`
            }).then(() => {
                window.location.reload();
            });

            if (data.errors && data.errors.length > 0) {
                errorsBox.classList.remove('d-none');
                data.errors.slice(0, 20).forEach((e) => {
                    const li = document.createElement('li');
                    li.textContent = e;
                    errorsList.appendChild(li);
                });
            }
        })
        .catch((err) => {
            const msg = err && err.message ? err.message : 'Import failed. Please check your CSV and try again.';
            Swal.fire({
                icon: 'error',
                title: 'Import Failed',
                text: msg
            });
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Start Import';
        });
}
</script>
@endpush