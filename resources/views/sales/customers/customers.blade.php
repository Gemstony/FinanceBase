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
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('customers.subshops') }}">Choose Shop</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Customers</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <!-- Filters and Add Button -->
    <div class="card card-primary card-outline mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter"></i> Filters</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            </div>
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <form method="GET" action="{{ route('customers.index') }}" class="mb-0 flex-grow-1">
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Search</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name / Email / Phone / Contact">
                            </div>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Min Orders</label>
                            <input type="number" name="min_orders" value="{{ request('min_orders') }}" class="form-control" placeholder="0">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Max Orders</label>
                            <input type="number" name="max_orders" value="{{ request('max_orders') }}" class="form-control" placeholder="0">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Min Spent</label>
                            <input type="number" step="0.01" name="min_spent" value="{{ request('min_spent') }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Max Spent</label>
                            <input type="number" step="0.01" name="max_spent" value="{{ request('max_spent') }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="active" {{ request('status')==='active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status')==='inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Sort</label>
                            <select name="sort" class="form-control">
                                <option value="date_desc" {{ request('sort')==='date_desc' ? 'selected' : '' }}>Date: New → Old</option>
                                <option value="date_asc" {{ request('sort')==='date_asc' ? 'selected' : '' }}>Date: Old → New</option>
                                <option value="name_asc" {{ request('sort')==='name_asc' ? 'selected' : '' }}>Name: A → Z</option>
                                <option value="name_desc" {{ request('sort')==='name_desc' ? 'selected' : '' }}>Name: Z → A</option>
                                <option value="orders_desc" {{ request('sort')==='orders_desc' ? 'selected' : '' }}>Orders: High → Low</option>
                                <option value="orders_asc" {{ request('sort')==='orders_asc' ? 'selected' : '' }}>Orders: Low → High</option>
                                <option value="spent_desc" {{ request('sort')==='spent_desc' ? 'selected' : '' }}>Spent: High → Low</option>
                                <option value="spent_asc" {{ request('sort')==='spent_asc' ? 'selected' : '' }}>Spent: Low → High</option>
                                <option value="status" {{ request('sort')==='status' ? 'selected' : '' }}>Status</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
                            <a class="btn btn-light border" href="{{ route('customers.index', ['subshop_id'=>$subshop->id]) }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                        <div class="ml-auto d-flex align-items-center">
                            @can('export_customers')
                            <div class="dropdown mr-2">
                                
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-download"></i> Export
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="{{ route('customers.export', ['format' => 'csv'] + request()->query()) }}">
                                        <i class="fas fa-file-csv mr-1 text-success"></i> CSV
                                    </a>
                                    <a class="dropdown-item" href="{{ route('customers.export', ['format' => 'excel'] + request()->query()) }}">
                                        <i class="fas fa-file-excel mr-1 text-success"></i> Excel
                                    </a>
                                    <a class="dropdown-item" href="{{ route('customers.export', ['format' => 'pdf'] + request()->query()) }}">
                                        <i class="fas fa-file-pdf mr-1 text-danger"></i> PDF
                                    </a>
                                </div>
                            </div>
                            @endcan
                            @can('add_customers')
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addCustomerModal">
                                <i class="fas fa-plus"></i> Add Customer
                            </button>
                            <button type="button" class="btn btn-outline-success ml-2" data-toggle="modal" data-target="#importCustomersModal">
                                <i class="fas fa-file-import"></i> Import CSV
                            </button>
                            @endcan
                        </div>
                    </div>
                </div>
                </form>
            </div>
        </div>
    </div>

<!-- Import Customers Modal -->
<div class="modal fade" id="importCustomersModal" tabindex="-1" role="dialog" aria-labelledby="importCustomersModalLabel" aria-hidden="true">
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
                                    <li>Set <code>is_active</code> as 1 (active) or 0 (inactive). If omitted, customers are set active.</li>
                                    <li>Use the <strong>Download Sample</strong> to get the correct column order.</li>
                                    <li><i class="fas fa-info-circle text-warning"></i> Note: make sure the <code>Birth date</code> is in this format <code>YYYY-MM-DD</code>, Example: <code>2002-01-25</code></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="customers_import_file">Choose CSV File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="customers_import_file" name="import_file" accept=".csv" required>
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <a href="{{ route('customers.import.sample') }}" class="btn btn-outline-success btn-block">
                                <i class="fas fa-download"></i> Download Sample
                            </a>
                        </div>
                    </div>

                    <div class="custom-control custom-switch mb-3">
                        <input type="checkbox" class="custom-control-input" id="customers_has_headers" name="has_headers" value="1" checked>
                        <label class="custom-control-label" for="customers_has_headers">First row contains headers</label>
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
                    <button type="button" class="btn btn-primary" id="startCustomersImportBtn" onclick="submitImportCustomers()">
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
                                        <div class="avatar-circle bg-primary text-white mr-2" style="width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
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
                                                <br><small class="text-muted"><i class="fas fa-phone"></i> {{ $customer->altenative_phone }}</small>
                                            @endif

                                            @if($customer->email)
                                                <br><small class="text-muted"><i class="fas fa-google"></i> {{ $customer->email }}</small>
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
                                        <a href="{{ route('credits.show', $customer->id) }}" class="btn btn-sm btn-outline-success" title="View Credits">
                                            <i class="fas fa-wallet"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-primary view-customer" 
                                            data-id="{{ $customer->id }}" 
                                            data-name="{{ $customer->name }}"
                                            data-email="{{ $customer->email }}"
                                            data-phone="{{ $customer->phone }}"
                                            data-address="{{ $customer->address }}"
                                            data-contact_person="{{ $customer->contact_person }}"
                                            data-created_at="{{ $customer->created_at->format('M d, Y') }}"
                                            data-status="{{ $customer->is_active ? 'Active' : 'Inactive' }}">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        @can('edit_customers')
                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#editCustomerModal" 
                                           
                                            data-id="{{ $customer->id }}" 
                                            data-name="{{ $customer->name }}" 
                                            data-gender="{{ $customer->gender }}" 
                                            data-email="{{ $customer->email }}" 
                                            data-phone="{{ $customer->phone }}" 
                                            data-altenative_phone ="{{ $customer->altenative_phone }}" 
                                            data-birth_date="{{ $customer->birth_date }}" 
                                            data-region="{{ $customer->region }}" 

                                            data-district="{{ $customer->district }}" 
                                            data-ward="{{ $customer->ward }}" 
                                            data-street="{{ $customer->street }}" 
                                            data-house_no="{{ $customer->house_no }}" 
                                            data-work ="{{ $customer->work }}" 
                                            data-work_address ="{{ $customer->work_address }}" 
                                            data-id_type="{{ $customer->id_type }}" 

                                            data-id_number ="{{ $customer->id_number }}" 
                                            data-category="{{ $customer->category }}" 


                                            data-active="{{ $customer->is_active ? '1' : '0' }}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        @endcan
                                        @can('delete_customers')
                                        <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="deleteCustomer({{ $customer->id }}, '{{ $customer->name }}')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endcan
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
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <form action="{{ route('customers.store') }}" method="POST" id="addCustomerForm">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-header " style="background: linear-gradient(135deg, #004e92, #000428); color: white;" >
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
                                <label for="phone">Phone  <span class="text-danger">*</span></label>
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
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
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
<div class="modal fade" id="editCustomerModal" tabindex="-1" role="dialog" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
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
                                <label for="phone">Phone  <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_phone" name="phone" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="altenative_phone">Altenative phone </label>
                                <input type="text" class="form-control" id="edit_altenative_phone" name="altenative_phone">
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
                                <select name="id_type"  id="edit_id_type" class="form-control">
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
                                <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1" checked>
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

<!-- View Customer Modal -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1" role="dialog" aria-labelledby="viewCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewCustomerModalLabel">Customer Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="customerTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details" role="tab" aria-controls="details" aria-selected="true">
                            <i class="fas fa-info-circle"></i> Details
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="sales-tab" data-toggle="tab" href="#sales" role="tab" aria-controls="sales" aria-selected="false">
                            <i class="fas fa-shopping-cart"></i> Sales History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="stats-tab" data-toggle="tab" href="#stats" role="tab" aria-controls="stats" aria-selected="false">
                            <i class="fas fa-chart-bar"></i> Statistics
                        </a>
                    </li>
                </ul>
                <div class="tab-content p-3 border border-top-0 rounded-bottom" id="customerTabsContent">
                    <!-- Customer Details Tab -->
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Customer Information</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th style="width: 40%;">Name:</th>
                                        <td id="view-customer-name"></td>
                                    </tr>
                                    <tr>
                                        <th>Contact Person:</th>
                                        <td id="view-contact-person"></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td id="view-email"></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td id="view-phone"></td>
                                    </tr>
                                    <tr>
                                        <th>Address:</th>
                                        <td id="view-address"></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td><span class="badge" id="view-status"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Member Since:</th>
                                        <td id="view-created-at"></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Quick Stats</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6 mb-3">
                                                <div class="p-3 border rounded">
                                                    <h3 class="mb-1 text-primary" id="total-orders">0</h3>
                                                    <small class="text-muted">Total Orders</small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="p-3 border rounded">
                                                    <h3 class="mb-1 text-success" id="total-spent">Tsh 0</h3>
                                                    <small class="text-muted">Total Spent</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="p-3 border rounded">
                                                    <h3 class="mb-1 text-info" id="avg-order">Tsh 0</h3>
                                                    <small class="text-muted">Avg. Order Value</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="p-3 border rounded">
                                                    <h3 class="mb-1 text-warning" id="last-order">-</h3>
                                                    <small class="text-muted">Last Order</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sales History Tab -->
                    <div class="tab-pane fade" id="sales" role="tabpanel" aria-labelledby="sales-tab">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover" id="salesTable">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="sales-table-body">
                                    <!-- Sales data will be loaded here via AJAX -->
                                    <tr>
                                        <td colspan="6" class="text-center">Loading sales history...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Statistics Tab -->
                    <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Monthly Spending</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="monthlySpendingChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Top Categories</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="categoriesChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Recent Activity</h6>
                                        <span class="badge badge-primary">Last 5 Activities</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="list-group list-group-flush" id="recent-activity">
                                            <li class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <small class="text-muted">Loading activity...</small>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromView">
                    <i class="fas fa-edit"></i> Edit Customer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCustomerModal" tabindex="-1" role="dialog" aria-labelledby="deleteCustomerModalLabel" aria-hidden="true">
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
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
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

$(function () {
    // Initialize DataTable
   
    var table = $('#customersTable').DataTable({
        "order": [[1, "desc"]],
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
                $(this).closest('.dataTables_wrapper').find('.dataTables_paginate, .dataTables_length, .dataTables_filter')
                    .addClass('d-none');
            }
        }
    });
    
    // Initialize view customer modal
    $('.view-customer').on('click', function() {
        const customerId = $(this).data('id');
        const customerName = $(this).data('name');
        
        // Set modal title
        $('#viewCustomerModalLabel').html(`<i class="fas fa-user"></i> ${customerName}`);
        
        // Show loading state
        //$('#details, #sales, #stats').addClass('loading');
        
        // Load customer details
        $.get(`/admin/sales/customers/${customerId}`, function(response) {
            // Populate customer details
            const customer = response;
            $('#view-customer-name').text(customer.name);
            $('#view-contact-person').text(customer.contact_person || 'N/A');
            $('#view-email').html(customer.email ? `<a href="mailto:${customer.email}">${customer.email}</a>` : 'N/A');
            $('#view-phone').html(customer.phone ? `<a href="tel:${customer.phone}">${customer.phone}</a>` : 'N/A');
            $('#view-address').text(customer.address || 'N/A');
            $('#view-status').text(customer.is_active ? 'Active' : 'Inactive')
                .removeClass('badge-danger badge-success')
                .addClass(customer.is_active ? 'badge-success' : 'badge-danger');
            $('#view-created-at').text(new Date(customer.created_at).toLocaleDateString());
            
            // Set edit button data
            $('#editFromView').data('id', customer.id);
            
            // Load sales data
            loadCustomerSales(customerId);
            
            // Load statistics
            loadCustomerStatistics(customerId);
            
            // Show the modal
            $('#viewCustomerModal').modal('show');
        }).fail(function() {
            Swal.fire('Error', 'Failed to load customer details', 'error');
        });
    });
    
    // Handle edit from view
    $('#editFromView').on('click', function() {
        const customerId = $(this).data('id');
        const btn = $(`button[data-id="${customerId}"][data-toggle="modal"][data-target="#editCustomerModal"]`);
        $('#viewCustomerModal').modal('hide');
        btn.trigger('click');
    });
    
    // Reset charts when modal is closed
    $('#viewCustomerModal').on('hidden.bs.modal', function () {
        // Destroy existing charts to prevent memory leaks
        if (customerCharts.monthlySpending) {
            customerCharts.monthlySpending.destroy();
        }
        if (customerCharts.categories) {
            customerCharts.categories.destroy();
        }
    });
});

// Load customer sales data (real API)
function loadCustomerSales(customerId) {
    console.log('Loading sales for customer:', customerId);
    $('#sales-table-body').html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading sales history...</td></tr>');
    
    // Add CSRF token to the headers
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
    
    // Get the current subshop_id from the session
    const subshopId = '{{ session('subshop_id') }}';
    
    if (!subshopId) {
        $('#sales-table-body').html('<tr><td colspan="6" class="text-center text-danger">No subshop selected. Please select a shop first.</td></tr>');
        return;
    }
    
    // Make the API request with the subshop_id
    $.get(`/api/customers/${customerId}/sales`)
        .done(function(response) {
            console.log('API Response:', response);
            
            // Check if the response has the expected structure
            if (!response || !response.success) {
                console.error('API Error:', response);
                throw new Error(response.message || 'Failed to load sales data');
            }
            
            const rows = response.data || [];
            
            if (rows.length === 0) {
                $('#sales-table-body').html('<tr><td colspan="6" class="text-center text-muted">No sales history found for this customer.</td></tr>');
                
                // Reset stats
                $('#total-orders').text('0');
                $('#total-spent').text('Tsh 0.00');
                $('#avg-order').text('Tsh 0.00');
                $('#last-order').text('N/A');
                
                return;
            }
            
            let html = '';
            let totalSpent = 0;
            let lastOrderDate = null;
            
            rows.forEach(sale => {
                const orderDate = sale.order_date || sale.created_at;
                const status = sale.payment_status || 'pending';
                const stBadge = status === 'paid' ? 'badge-success' : 
                              (status === 'pending' ? 'badge-danger' : 'badge-warning');
                
                // Calculate total spent for stats
                const amount = parseFloat(sale.grand_total || 0);
                totalSpent += amount;
                
                // Track the most recent order date
                if (orderDate && (!lastOrderDate || new Date(orderDate) > new Date(lastOrderDate))) {
                    lastOrderDate = orderDate;
                }
                
                try {
                    // Safely access properties with fallbacks
                    const orderNo = sale.order_no || `#${sale.id}` || 'N/A';
                    const orderDate = sale.date || sale.created_at || null;
                    const itemsCount = sale.items_count || 0;
                    const grandTotal = parseFloat(sale.grand_total || 0);
                    const status = (sale.status || 'pending').toLowerCase();
                    
                    // Format the date
                    let formattedDate = 'N/A';
                    if (orderDate) {
                        const dateObj = new Date(orderDate);
                        if (!isNaN(dateObj.getTime())) {
                            formattedDate = dateObj.toLocaleString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true
                            });
                        }
                    }
                    
                    // Determine badge class based on status
                    let badgeClass = 'badge-secondary';
                    if (status === 'paid') {
                        badgeClass = 'badge-success';
                    } else if (status === 'pending') {
                        badgeClass = 'badge-danger';
                    } else if (status === 'partial') {
                        badgeClass = 'badge-warning';
                    }
                    
                    // Build the row HTML
                    html += `
                        <tr>
                            <td>${orderNo}</td>
                            <td>${formattedDate}</td>
                            <td>${itemsCount} ${itemsCount === 1 ? 'item' : 'items'}</td>
                            <td>Tsh ${grandTotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")}</td>
                            <td><span class="badge ${badgeClass}">${status.toUpperCase()}</span></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="{{ Route("invoices.index") }}">
                                    <i class="fas fa-eye"></i> View
                                </a>`;
                    
                    // Only show print button if there's an invoice number
                    if (sale.invoice_number) {
                        html += `
                                <a class="btn btn-sm btn-outline-secondary ml-1" href="/admin/sales/invoices/${sale.id}/print" target="_blank">
                                    <i class="fas fa-print"></i> Print
                                </a>`;
                    }
                    
                    html += `
                            </td>
                        </tr>`;
                } catch (error) {
                    console.error('Error processing sale:', sale, error);
                }
            });
            
            // Update the table with the sales data
            $('#sales-table-body').html(html || '<tr><td colspan="6" class="text-center text-muted">No sales data available</td></tr>');
            
            try {
                // Update the stats in the statistics tab
                const totalOrders = rows.length;
                const totalSpent = rows.reduce((sum, sale) => sum + parseFloat(sale.grand_total || 0), 0);
                const avgOrder = totalOrders > 0 ? (totalSpent / totalOrders) : 0;
                
                // Find the most recent order date
                let lastOrderDate = null;
                rows.forEach(sale => {
                    const orderDate = sale.date || sale.created_at;
                    if (orderDate && (!lastOrderDate || new Date(orderDate) > new Date(lastOrderDate))) {
                        lastOrderDate = orderDate;
                    }
                });
                
                // Format the last order date
                let formattedLastOrderDate = 'N/A';
                if (lastOrderDate) {
                    const date = new Date(lastOrderDate);
                    formattedLastOrderDate = date.toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
                
                // Update the UI
                $('#total-orders').text(totalOrders);
                $('#total-spent').text(`Tsh ${totalSpent.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")}`);
                $('#avg-order').text(`Tsh ${avgOrder.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")}`);
                $('#last-order').text(formattedLastOrderDate);
                
            } catch (error) {
                console.error('Error updating stats:', error);
                
                // Reset stats on error
                $('#total-orders').text('0');
                $('#total-spent').text('Tsh 0.00');
                $('#avg-order').text('Tsh 0.00');
                $('#last-order').text('N/A');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading sales history:', { xhr, status, error });
            let errorMessage = 'Failed to load sales history. Please try again.';
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.status === 404) {
                errorMessage = 'Sales history endpoint not found. Please check the API URL.';
            } else if (xhr.status === 500) {
                errorMessage = 'Server error occurred while loading sales history.';
            }
            
            $('#sales-table-body').html(`<tr><td colspan="6" class="text-center text-danger">${errorMessage}</td></tr>`);
        });
}

// Load customer statistics (real API)
function loadCustomerStatistics(customerId) {
    // Quick stats spinners
    $('#total-orders').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#total-spent').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#avg-order').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#last-order').html('<i class="fas fa-spinner fa-spin"></i>');

    $.get(`/api/customers/${customerId}/stats`)
        .done(function(res){
            const q = res.quick || {};
            $('#total-orders').text((q.total_orders || 0));
            $('#total-spent').text('Tsh ' + Number(q.total_spent || 0).toLocaleString());
            $('#avg-order').text('Tsh ' + Number(q.avg_order || 0).toLocaleString());
            $('#last-order').text(q.last_order ? new Date(q.last_order).toLocaleDateString() : '-');

            // Charts
            try {
                if (customerCharts.monthlySpending) { customerCharts.monthlySpending.destroy(); }
                const monthlyCtx = document.getElementById('monthlySpendingChart').getContext('2d');
                customerCharts.monthlySpending = new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: (res.monthly && res.monthly.labels) || [],
                        datasets: [{
                            label: 'Monthly Spending (Tsh)',
                            data: (res.monthly && res.monthly.values) || [],
                            borderColor: 'rgba(0, 123, 255, 0.8)',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { callback: (v)=>('Tsh ' + Number(v).toLocaleString()) } } }
                    }
                });
            } catch(e) {}

            try {
                if (customerCharts.categories) { customerCharts.categories.destroy(); }
                const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
                const labels = (res.categories || []).map(c => c.label);
                const values = (res.categories || []).map(c => c.value);
                customerCharts.categories = new Chart(categoriesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: [
                                'rgba(0, 123, 255, 0.8)',
                                'rgba(40, 167, 69, 0.8)',
                                'rgba(255, 193, 7, 0.8)',
                                'rgba(220, 53, 69, 0.8)',
                                'rgba(108, 117, 125, 0.8)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'right' } } }
                });
            } catch(e) {}

            // Recent activity
            const recent = res.recent || [];
            if(recent.length === 0){
                $('#recent-activity').html('<li class="list-group-item text-muted text-center">No recent activity</li>');
            } else {
                let act = '';
                recent.forEach(r => {
                    act += `
                        <li class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">Order ${r.order_no}</h6>
                                <small class="text-muted">${r.date || ''}</small>
                            </div>
                            <p class="mb-1">Tsh ${Number(r.grand_total || 0).toLocaleString()}</p>
                        </li>`;
                });
                $('#recent-activity').html(act);
            }

            $('#stats').removeClass('loading');
        })
        .fail(function(){
            // Clear loading state and set safe defaults
            $('#stats').removeClass('loading');
            $('#total-orders').text('0');
            $('#total-spent').text('Tsh 0');
            $('#avg-order').text('Tsh 0');
            $('#last-order').text('-');
            $('#recent-activity').html('<li class="list-group-item text-danger text-center">Failed to load statistics</li>');
        });
}

// Initialize when document is ready
// View Customer Modal Handler
    $(document).on('click', '.view-customer', function() {
        var customerId = $(this).data('id');
        
        // Set customer data in the modal
        $('#view-customer-name').text($(this).data('name'));
        $('#view-contact-person').text($(this).data('contact_person') || 'N/A');
        $('#view-email').text($(this).data('email') || 'N/A');
        $('#view-phone').text($(this).data('phone') || 'N/A');
        $('#view-address').text($(this).data('address') || 'N/A');
        $('#view-created-at').text($(this).data('created_at'));
        
        // Set status badge
        var status = $(this).data('status');
        var statusBadge = $('<span>').addClass('badge ' + (status === 'Active' ? 'badge-success' : 'badge-secondary'))
                                   .text(status);
        $('#view-status').empty().append(statusBadge);
        
        // Set up edit button
        $('#editFromView').off('click').on('click', function() {
            $('#viewCustomerModal').modal('hide');
            
            // Find and trigger the edit button click
            $('.view-customer').filter(function() {
                return $(this).data('id') === customerId;
            }).closest('.btn-group').find('.btn-info').click();
        });
        
        // Load customer statistics
        loadCustomerStats(customerId);
        
        // Show the modal
        $('#viewCustomerModal').modal('show');
    });
    
    // Function to load customer statistics (legacy entry point -> delegate to real loaders)
    function loadCustomerStats(customerId) {
        // Show loading state for quick stats
        $('#total-orders').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#total-spent').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#avg-order').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#last-order').html('<i class="fas fa-spinner fa-spin"></i>');
        // Use real API-backed loaders
        loadCustomerSales(customerId);
        loadCustomerStatistics(customerId);
    }
    
    // Initialize the first tab when modal is shown
    $('#viewCustomerModal').on('shown.bs.modal', function() {
        // Reset to first tab
        $('#customerTabs a[href="#details"]').tab('show');
    });
    
    // Handle tab changes
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr('href');
        
        // Lazy load content when tab is shown
        if (target === '#sales') {
            // Sales tab is loaded when the modal opens
        } else if (target === '#stats') {
            // Stats tab is loaded when the modal opens
        }
    });
    
    // Initialize DataTable and other existing code
    $(document).ready(function() {
        // Reset form when add customer modal is opened
        $('#addCustomerModal').on('show.bs.modal', function () {
            document.getElementById('addCustomerForm').reset();
        });

        // Handle edit customer modal
        $('#editCustomerModal').on('show.bs.modal', function (event) {
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
                    var message = isEdit ? 'Customer updated successfully!' : 'Customer added successfully!';
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
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Importing...';

    fetch("{{ route('customers.import') }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(async (res) => {
        const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response'}));
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
        Swal.fire({ icon: 'error', title: 'Import Failed', text: msg });
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload"></i> Start Import';
    });
}

</script>
@endpush