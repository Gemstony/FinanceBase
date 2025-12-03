@extends('adminlte::page')

@section('title', 'Suppliers - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-truck"></i> Suppliers Management</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-truck"></i> Suppliers</h1>
                    <p class="mb-0 text-light">Managing suppliers for: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('suppliers.subshops') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Change Shop
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('suppliers.subshops') }}">Choose Shop</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Suppliers</li>
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
                <form method="GET" action="{{ route('suppliers.index') }}" class="mb-0 flex-grow-1">
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
                                <label class="small mb-1">From</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">To</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="active" {{ request('status')==='active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ request('status')==='inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small mb-1">Sort</label>
                                <select name="sort" class="form-control">
                                    <option value="">Newest</option>
                                    <option value="name_asc" {{ request('sort')==='name_asc' ? 'selected' : '' }}>Name: A → Z</option>
                                    <option value="name_desc" {{ request('sort')==='name_desc' ? 'selected' : '' }}>Name: Z → A</option>
                                    <option value="date_asc" {{ request('sort')==='date_asc' ? 'selected' : '' }}>Oldest</option>
                                    <option value="orders_desc" {{ request('sort')==='orders_desc' ? 'selected' : '' }}>Orders: High → Low</option>
                                    <option value="orders_asc" {{ request('sort')==='orders_asc' ? 'selected' : '' }}>Orders: Low → High</option>
                                    <option value="spent_desc" {{ request('sort')==='spent_desc' ? 'selected' : '' }}>Spent: High → Low</option>
                                    <option value="spent_asc" {{ request('sort')==='spent_asc' ? 'selected' : '' }}>Spent: Low → High</option>
                                    <option value="status" {{ request('sort')==='status' ? 'selected' : '' }}>Status</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Min Orders</label>
                                <input type="number" name="min_orders" value="{{ request('min_orders') }}" class="form-control" placeholder="0">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Max Orders</label>
                                <input type="number" name="max_orders" value="{{ request('max_orders') }}" class="form-control" placeholder="1000">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Min Spent</label>
                                <input type="number" name="min_spent" step="0.01" value="{{ request('min_spent') }}" class="form-control" placeholder="0.00">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Max Spent</label>
                                <input type="number" name="max_spent" step="0.01" value="{{ request('max_spent') }}" class="form-control" placeholder="100000.00">
                            </div>
                            <div class="form-group col-md-3">
                                <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
                                <a class="btn btn-light border" href="{{ route('suppliers.index', ['subshop_id'=>$subshop->id]) }}"><i class="fas fa-undo"></i> Reset</a>
                            </div>
                            <div class="ml-auto d-flex align-items-center">
                                @can('export_suppliers')
                                <div class="dropdown mr-2">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="{{ route('suppliers.export', ['format' => 'csv'] + request()->query()) }}">
                                            <i class="fas fa-file-csv mr-1 text-success"></i> CSV
                                        </a>
                                        <a class="dropdown-item" href="{{ route('suppliers.export', ['format' => 'excel'] + request()->query()) }}">
                                            <i class="fas fa-file-excel mr-1 text-success"></i> Excel
                                        </a>
                                        <a class="dropdown-item" href="{{ route('suppliers.export', ['format' => 'pdf'] + request()->query()) }}">
                                            <i class="fas fa-file-pdf mr-1 text-danger"></i> PDF
                                        </a>
                                    </div>
                                </div>
                                @endcan
                                @can('add_suppliers')
                                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addSupplierModal">
                                    <i class="fas fa-plus"></i> Add Supplier
                                </button>
                                <button type="button" class="btn btn-outline-success ml-2" data-toggle="modal" data-target="#importSuppliersModal">
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

    <!-- Suppliers Table -->
    <div class="card">
        <div class="card-body table-responsive p-3">
            <table class="table table-hover text-nowrap " id="suppliersTable">
                <thead class="thead-dark">
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php $count = 1; @endphp
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td>#{{$count++}}</td>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->contact_person ?? 'N/A' }}</td>
                            <td>{{ $supplier->email ?? 'N/A' }}</td>
                            <td>{{ $supplier->phone ?? 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $supplier->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $supplier->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ (int)($supplier->orders_count ?? 0) }}</td>
                            <td>TSh {{ number_format((float)($supplier->total_spent ?? 0), 2) }}</td>
                            <td>{{ $supplier->created_at->format('d/m/Y') }}</td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary view-supplier" 
                                        data-id="{{ $supplier->id }}" 
                                        data-name="{{ $supplier->name }}"
                                        data-email="{{ $supplier->email }}"
                                        data-phone="{{ $supplier->phone }}"
                                        data-address="{{ $supplier->address }}"
                                        data-contact_person="{{ $supplier->contact_person }}"
                                        data-created_at="{{ $supplier->created_at->format('M d, Y') }}"
                                        data-status="{{ $supplier->is_active ? 'Active' : 'Inactive' }}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    @can('edit_suppliers')
                                    <button type="button" class="btn btn-sm btn-info" title="Edit" data-toggle="modal" data-target="#editSupplierModal" 
                                        data-id="{{ $supplier->id }}" 
                                        data-name="{{ $supplier->name }}" 
                                        data-email="{{ $supplier->email }}" 
                                        data-phone="{{ $supplier->phone }}" 
                                        data-address="{{ $supplier->address }}" 
                                        data-contact_person="{{ $supplier->contact_person }}" 
                                        data-active="{{ $supplier->is_active ? '1' : '0' }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    @endcan
                                    @can('delete_suppliers')
                                    <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="deleteSupplier({{ $supplier->id }}, '{{ $supplier->name }}')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $suppliers->appends(request()->query())->links() }}
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- View Supplier Modal -->
<div class="modal fade" id="viewSupplierModal" tabindex="-1" role="dialog" aria-labelledby="viewSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewSupplierModalLabel">Supplier Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="supplierTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="details-tab" data-toggle="tab" href="#supplier-details" role="tab" aria-controls="details" aria-selected="true">
                            <i class="fas fa-info-circle"></i> Details
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="purchases-tab" data-toggle="tab" href="#purchases" role="tab" aria-controls="purchases" aria-selected="false">
                            <i class="fas fa-shopping-cart"></i> Purchase History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="stats-tab" data-toggle="tab" href="#stats" role="tab" aria-controls="stats" aria-selected="false">
                            <i class="fas fa-chart-bar"></i> Statistics
                        </a>
                    </li>
                </ul>
                <div class="tab-content p-3 border border-top-0 rounded-bottom" id="supplierTabsContent">
                    <!-- Supplier Details Tab -->
                    <div class="tab-pane fade show active" id="supplier-details" role="tabpanel" aria-labelledby="details-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Supplier Information</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th style="width: 40%;">Name:</th>
                                        <td id="view-supplier-name"></td>
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
                                                    <h3 class="mb-1 text-success" id="total-spent">TSh 0</h3>
                                                    <small class="text-muted">Total Spent</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="p-3 border rounded">
                                                    <h3 class="mb-1 text-info" id="avg-order">TSh 0</h3>
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
                    
                    <!-- Purchase History Tab -->
                    <div class="tab-pane fade" id="purchases" role="tabpanel" aria-labelledby="purchases-tab">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover" id="purchasesTable">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total (Tsh)</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="purchases-table-body">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i> Select a supplier to view purchase history
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="purchases-pagination" class="d-flex justify-content-center mt-3">
                            <!-- Pagination will be loaded here -->
                        </div>
                    </div>
                    
                    <!-- Statistics Tab -->
                    <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Monthly Purchases (Last 6 Months)</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="monthlyPurchasesChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Top Products</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="topProductsChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Recent Activity</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="list-group list-group-flush" id="recent-activity">
                                            <div class="list-group-item text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="sr-only">Loading...</span>
                                                </div>
                                                <p class="mb-0 mt-2">Loading activity...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editSupplierBtn">
                    <i class="fas fa-edit"></i> Edit Supplier
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('suppliers.store') }}" method="POST" id="addSupplierForm">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Supplier Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="contact_person">Contact Person</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person">
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
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" checked>
                            <label class="custom-control-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitSupplierForm()">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" role="dialog" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="editSupplierForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_name">Supplier Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_contact_person">Contact Person</label>
                                <input type="text" class="form-control" id="edit_contact_person" name="contact_person">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_email">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_phone">Phone</label>
                                <input type="text" class="form-control" id="edit_phone" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_address">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="edit_is_active" name="is_active" value="1">
                            <label class="custom-control-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Suppliers Modal -->
<div class="modal fade" id="importSuppliersModal" tabindex="-1" role="dialog" aria-labelledby="importSuppliersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="importSuppliersModalLabel">
                    <i class="fas fa-file-import mr-1"></i> Bulk Import Suppliers (CSV)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="importSuppliersForm" enctype="multipart/form-data">
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
                                    <li>Required column: <code>name</code>. Optional: <code>contact_person, email, phone, address, is_active</code>.</li>
                                    <li>Set <code>is_active</code> as 1 (active) or 0 (inactive). If omitted, suppliers are set active.</li>
                                    <li>Use the <strong>Download Sample</strong> to get the correct column order.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="import_file">Choose CSV File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="import_file" name="import_file" accept=".csv" required>
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <a href="{{ route('suppliers.import.sample') }}" class="btn btn-outline-success btn-block">
                                <i class="fas fa-download"></i> Download Sample
                            </a>
                        </div>
                    </div>

                    <div class="custom-control custom-switch mb-3">
                        <input type="checkbox" class="custom-control-input" id="has_headers" name="has_headers" value="1" checked>
                        <label class="custom-control-label" for="has_headers">First row contains headers</label>
                    </div>

                    <div id="importErrors" class="d-none">
                        <div class="alert alert-warning mb-0">
                            <strong>Some rows were skipped:</strong>
                            <ul class="mb-0" id="importErrorsList"></ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="startImportBtn" onclick="submitImportSuppliers()">
                        <i class="fas fa-upload"></i> Start Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <style>
        .loading {
            position: relative;
            opacity: 0.6;
            pointer-events: none;
        };
        .loading:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7) url('data:image/svg+xml;utf8,<svg class="spinner" width="40px" height="40px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg"><circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle></svg>') no-repeat center center;
            background-size: 50px 50px;
            z-index: 1000;
        }
        .chart-container {
            position: relative;
            height: 250px;
        }
        .activity-item {
            border-left: 3px solid #4e73df;
            padding-left: 10px;
            margin-bottom: 10px;
        }
        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .activity-text {
            margin-bottom: 5px;
        }
    </style>
@endpush
@stop

@push('js')
<!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script> -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<!-- <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script> -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
   
// Initialize DataTable
    $('#suppliersTable').DataTable({
        language: {
            emptyTable: 'No suppliers found.'
        }
    });
    
    
    // View Supplier Modal Handler
    $(document).on('click', '.view-supplier', function() {
        var supplierId = $(this).data('id');
        
        // Set supplier data in the modal
        $('#view-supplier-name').text($(this).data('name'));
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
        $('#editSupplierBtn').off('click').on('click', function() {
            $('#viewSupplierModal').modal('hide');
            
            // Find and trigger the edit button click
            $('.view-supplier').filter(function() {
                return $(this).data('id') === supplierId;
            }).closest('.btn-group').find('.btn-info').click();
        });
        
        // Load supplier details fresh (optional). Using attributes for now.
        // Load purchases and statistics
        loadSupplierPurchases(supplierId);
        loadSupplierStatistics(supplierId);
        
        // Show the modal
        $('#viewSupplierModal').modal('show');
    });
    
    // Function to load supplier purchases (real API)
    function loadSupplierPurchases(supplierId) {
        var tbody = $('#purchases-table-body');
        tbody.html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading purchase history...</td></tr>');

        // Get the current subshop_id from the session
        const subshopId = '{{ session('subshop_id') }}';
        
        if (!subshopId) {
            tbody.html('<tr><td colspan="6" class="text-center text-danger">No subshop selected. Please select a shop first.</td></tr>');
            return;
        }

        // Make the API request with the subshop_id
        $.get(`/api/suppliers/${supplierId}/purchases`)
            .done(function(response) {
                console.log('Purchase History API Response:', response);
                
                // Check if the response has the expected structure
                if (!response || !response.success) {
                    console.error('API Error:', response);
                    throw new Error(response.message || 'Failed to load purchase history');
                }
                
                const purchases = response.data || [];
                
                if (purchases.length === 0) {
                    tbody.html('<tr><td colspan="6" class="text-center text-muted">No purchase history found for this supplier.</td></tr>');
                    return;
                }
                
                let html = '';
                purchases.forEach(purchase => {
                    // Format the date
                    let formattedDate = 'N/A';
                    if (purchase.date) {
                        const dateObj = new Date(purchase.date);
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
                    const status = (purchase.status || 'pending').toLowerCase();
                    let statusClass = 'badge-secondary';
                    if (status === 'completed') {
                        statusClass = 'badge-success';
                    } else if (status === 'pending') {
                        statusClass = 'badge-danger';
                    } else if (status === 'partial') {
                        statusClass = 'badge-warning';
                    }
                    
                    // Build the row HTML
                    html += `
                        <tr>
                            <td>${purchase.order_no || 'N/A'}</td>
                            <td>${formattedDate}</td>
                            <td>${purchase.items || 0} items</td>
                            <td>Tsh ${parseFloat(purchase.grand_total || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            <td><span class="badge ${statusClass}">${status.toUpperCase()}</span></td>
                            <td>
                                <a href="{{ route("purchase_orders.index") }}" class="btn btn-sm btn-outline-primary" >
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>`;
                });
                
                tbody.html(html);
            })
            .fail(function(xhr, status, error) {
                console.error('Error loading purchase history:', { xhr, status, error });
                let errorMessage = 'Failed to load purchase history. Please try again.';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMessage = 'Purchase history endpoint not found. Please check the API URL.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred while loading purchase history.';
                }
                
                tbody.html(`<tr><td colspan="6" class="text-center text-danger">${errorMessage}</td></tr>`);
            });
    }
    
    // Function to load supplier statistics (real API)
    let supplierCharts = { monthly: null, products: null };
    function loadSupplierStatistics(supplierId) {
        // Quick stats spinners
        $('#total-orders').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#total-spent').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#avg-order').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#last-order').html('<i class="fas fa-spinner fa-spin"></i>');

        $.get(`/api/suppliers/${supplierId}/stats`)
            .done(function(res){
                const q = res.quick || {};
                $('#total-orders').text((q.total_orders || 0));
                $('#total-spent').text('Tsh ' + Number(q.total_spent || 0).toLocaleString());
                $('#avg-order').text('Tsh ' + Number(q.avg_order || 0).toLocaleString());
                $('#last-order').text(q.last_order ? new Date(q.last_order).toLocaleDateString() : '-');

                // Charts: destroy if exist
                if (supplierCharts.monthly) { supplierCharts.monthly.destroy(); }
                if (supplierCharts.products) { supplierCharts.products.destroy(); }

                // Monthly purchases chart
                const monthlyCtx = document.getElementById('monthlyPurchasesChart').getContext('2d');
                supplierCharts.monthly = new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: (res.monthly && res.monthly.labels) || [],
                        datasets: [{
                            label: 'Monthly Purchases (Tsh)',
                            data: (res.monthly && res.monthly.values) || [],
                            backgroundColor: 'rgba(78, 115, 223, 0.05)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: function(value){ return 'Tsh ' + Number(value).toLocaleString(); } }
                            }
                        },
                        plugins: { legend: { display: false } }
                    }
                });

                // Top products chart
                const productsCtx = document.getElementById('topProductsChart').getContext('2d');
                const labels = (res.products || []).map(r => r.label);
                const values = (res.products || []).map(r => r.value);
                supplierCharts.products = new Chart(productsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: [
                                'rgba(78, 115, 223, 0.8)',
                                'rgba(54, 185, 204, 0.8)',
                                'rgba(28, 200, 138, 0.8)',
                                'rgba(246, 194, 62, 0.8)',
                                'rgba(231, 74, 59, 0.8)'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right' },
                            tooltip: { callbacks: { label: function(ctx){ return `${ctx.label}: ${ctx.raw}%`; } } }
                        },
                        cutout: '65%'
                    }
                });

                // Recent activity
                const activityHtml = (res.recent || []).map(a => `
                    <div class="activity-item">
                        <div class="activity-text">PO ${a.order_no} - Tsh ${Number(a.grand_total||0).toLocaleString()}</div>
                        <div class="activity-time">${a.date || ''}</div>
                    </div>
                `).join('');
                $('#recent-activity').html(activityHtml || '<div class="list-group-item text-center py-4 text-muted">No recent activity</div>');
            })
            .fail(function(){
                $('#total-orders').text('0');
                $('#total-spent').text('Tsh 0');
                $('#avg-order').text('Tsh 0');
                $('#last-order').text('-');
                $('#recent-activity').html('<div class="list-group-item text-center py-4 text-danger">Failed to load statistics.</div>');
            });
    }
    
    // Initialize the first tab when modal is shown
    $('#viewSupplierModal').on('shown.bs.modal', function() {
        // Reset to first tab
        $('#supplierTabs a[href="#supplier-details"]').tab('show');
    });
    
    // Handle tab changes
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr('href');
        
        // Lazy load content when tab is shown
        // Data loads on modal open; keep here if we need future lazy logic
    });
    
    // Reset form when add supplier modal is opened
    $('#addSupplierModal').on('show.bs.modal', function () {
        document.getElementById('addSupplierForm').reset();
    });

    $('#editSupplierModal').on('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const email = button.getAttribute('data-email');
        const phone = button.getAttribute('data-phone');
        const address = button.getAttribute('data-address');
        const contactPerson = button.getAttribute('data-contact_person');
        const isActive = button.getAttribute('data-active') === '1';


        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email || '';
        document.getElementById('edit_phone').value = phone || '';
        document.getElementById('edit_address').value = address || '';
        document.getElementById('edit_contact_person').value = contactPerson || '';
        document.getElementById('edit_is_active').checked = isActive;
        document.getElementById('editSupplierForm').action = '{{ url("/admin/inventory/suppliers") }}/' + id;
    });

    window.deleteSupplier = function(id, name) {
        Swal.fire({
            title: 'Una uhakika?',
            text: `Unataka kufuta supplier "${name}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ndio, Futa!',
            cancelButtonText: 'Ghairi'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm').action = '{{ url("/admin/inventory/suppliers") }}/' + id;
                document.getElementById('deleteForm').submit();
            }
        });
    };

    window.submitSupplierForm = function() {
        const form = document.getElementById('addSupplierForm');
        if (!form) {
            console.error('Form not found');
            return;
        }

        // Create FormData
        const formData = new FormData(form);

        // Check required fields
        const nameValue = formData.get('name');
        if (!nameValue || !nameValue.trim()) {
            alert('Tafadhali jaza jina la supplier');
            return;
        }

        // Submit using fetch
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json().then(data => {
            if (response.ok) {
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    showConfirmButton: false,
                    timer: 2500
                });
                // Close modal and reload page to show new supplier
                $('#addSupplierModal').modal('hide');
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                // Handle validation errors or other errors
                let errorMessage = data.message || 'Failed to create supplier';

                if (data.errors) {
                    // Format validation errors
                    const errorList = Object.values(data.errors).flat();
                    errorMessage = errorList.join('\n');
                }

                // Show error message
                Swal.fire({
                    icon: 'error',
                    title: 'Kuna Tatizo!',
                    text: errorMessage,
                    showConfirmButton: true
                });
            }
        }))
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Errro!',
                text: 'Network error occurred',
                showConfirmButton: true
            });
        });
    };

    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: "{{ session('success') }}",
            showConfirmButton: false,
            timer: 2500
        });
    @endif

    @if (session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: "{{ session('error') }}",
            showConfirmButton: true
        });
    @endif

    @if (session('error'))
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: "{{ session('error') }}",
                showConfirmButton: true
            });
        });
    @endif

    // Reset form when add supplier modal is opened
    $('#addSupplierModal').on('show.bs.modal', function () {
        document.getElementById('addSupplierForm').reset();
    });

    $('#editSupplierModal').on('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const email = button.getAttribute('data-email');
        const phone = button.getAttribute('data-phone');
        const address = button.getAttribute('data-address');
        const contactPerson = button.getAttribute('data-contact_person');
        const isActive = button.getAttribute('data-active') === '1';


        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email || '';
        document.getElementById('edit_phone').value = phone || '';
        document.getElementById('edit_address').value = address || '';
        document.getElementById('edit_contact_person').value = contactPerson || '';
        document.getElementById('edit_is_active').checked = isActive;
        document.getElementById('editSupplierForm').action = '{{ url("/admin/inventory/suppliers") }}/' + id;
    });

    window.deleteSupplier = function(id, name) {
        Swal.fire({
            title: 'Una uhakika?',
            text: `Unataka kufuta supplier "${name}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ndio, Futa!',
            cancelButtonText: 'Ghairi'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm').action = '{{ url("/admin/inventory/suppliers") }}/' + id;
                document.getElementById('deleteForm').submit();
            }
        });
    };

    window.submitSupplierForm = function() {
        const form = document.getElementById('addSupplierForm');
        if (!form) {
            console.error('Form not found');
            return;
        }

        // Create FormData
        const formData = new FormData(form);

        // Check required fields
        const nameValue = formData.get('name');
        if (!nameValue || !nameValue.trim()) {
            alert('Tafadhali jaza jina la supplier');
            return;
        }

        // Submit using fetch
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json().then(data => {
            if (response.ok) {
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    showConfirmButton: false,
                    timer: 2500
                });
                // Close modal and reload page to show new supplier
                $('#addSupplierModal').modal('hide');
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                // Handle validation errors or other errors
                let errorMessage = data.message || 'Failed to create supplier';

                if (data.errors) {
                    // Format validation errors
                    const errorList = Object.values(data.errors).flat();
                    errorMessage = errorList.join('\n');
                }

                // Show error message
                Swal.fire({
                    icon: 'error',
                    title: 'Kuna Tatizo!',
                    text: errorMessage,
                    showConfirmButton: true
                });
            }
        }))
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Kuna Tatizo!',
                text: 'Network error occurred',
                showConfirmButton: true
            });
        });
    };

    window.submitImportSuppliers = function() {
        const form = document.getElementById('importSuppliersForm');
        const formData = new FormData(form);
        const btn = document.getElementById('startImportBtn');
        const errorsBox = document.getElementById('importErrors');
        const errorsList = document.getElementById('importErrorsList');

        errorsBox.classList.add('d-none');
        errorsList.innerHTML = '';

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Importing...';

        fetch("{{ route('suppliers.import') }}", {
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
                // Reload page to refresh suppliers list
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
    };

});

</script>
@endpush