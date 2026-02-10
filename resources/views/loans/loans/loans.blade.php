@extends('adminlte::page')

@section('title', 'Write-offs - ' . $subshop->name)

@section('content_header')
    <meta name="base-url" content="{{ url('/') }}">
    <meta name="update-status-route" content="{{ route('writeoffs.updateStatus', ['writeoff' => 'WRITEOFF_ID']) }}">
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-minus-circle"></i> Write-offs Management</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-minus-circle"></i> Write-offs</h1>
                    <p class="mb-0 text-light">Managing write-offs for: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('writeoffs.subshops') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Change Shop
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('writeoffs.subshops') }}">Choose Shop</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Write-offs</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <!-- Summary Cards Row -->
    <div class="row mb-4">
        <!-- Total Write-offs Card -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-gradient-info">
                <span class="info-box-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Write-offs</span>
                    <span class="info-box-number">{{ $writeoffs->count() }}</span>
                    <div class="progress">
                        <div class="progress-bar" style="width: 100%"></div>
                    </div>
                    <span class="progress-description">
                        All time records
                    </span>
                </div>
            </div>
        </div>

        <!-- Pending Approval Card -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-gradient-warning">
                <span class="info-box-icon"><i class="far fa-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Pending Approval</span>
                    <span class="info-box-number">{{ $writeoffs->where('status', 'pending')->count() }}</span>
                    <div class="progress">
                        <div class="progress-bar" style="width: {{ ($writeoffs->where('status', 'pending')->count() / max($writeoffs->count(), 1)) * 100 }}%"></div>
                    </div>
                    <span class="progress-description">
                        Awaiting review
                    </span>
                </div>
            </div>
        </div>

        <!-- Approved Card -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-gradient-success">
                <span class="info-box-icon"><i class="far fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Approved</span>
                    <span class="info-box-number">{{ $writeoffs->where('status', 'approved')->count() }}</span>
                    <div class="progress">
                        <div class="progress-bar" style="width: {{ ($writeoffs->where('status', 'approved')->count() / max($writeoffs->count(), 1)) * 100 }}%"></div>
                    </div>
                    <span class="progress-description">
                        Completed write-offs
                    </span>
                </div>
            </div>
        </div>

        <!-- Rejected Card -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box bg-gradient-danger">
                <span class="info-box-icon"><i class="far fa-times-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Rejected</span>
                    <span class="info-box-number">{{ $writeoffs->where('status', 'rejected')->count() }}</span>
                    <div class="progress">
                        <div class="progress-bar" style="width: {{ ($writeoffs->where('status', 'rejected')->count() / max($writeoffs->count(), 1)) * 100 }}%"></div>
                    </div>
                    <span class="progress-description">
                        Not approved
                    </span>
                </div>
            </div>
        </div>
    </div>
    <!-- End Summary Cards Row -->

    <!-- Card with search and actions -->
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-minus-circle"></i>
                Write-offs Management
            </h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('writeoffs.index') }}" class="mb-3">
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Search</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Product / Notes / Reason">
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
                            <label class="small mb-1">Min Qty</label>
                            <input type="number" name="min_qty" value="{{ request('min_qty') }}" class="form-control" placeholder="0">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Max Qty</label>
                            <input type="number" name="max_qty" value="{{ request('max_qty') }}" class="form-control" placeholder="0">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Min Value</label>
                            <input type="number" step="0.01" name="min_total" value="{{ request('min_total') }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Max Value</label>
                            <input type="number" step="0.01" name="max_total" value="{{ request('max_total') }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="pending" {{ request('status')==='pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ request('status')==='approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('status')==='rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Reason</label>
                            <select name="reason" class="form-control">
                                <option value="">All</option>
                                @php $reasons = ['damage'=>'Damaged','expiry'=>'Expired','theft'=>'Theft','obsolescence'=>'Obsolescence','other'=>'Other']; @endphp
                                @foreach($reasons as $key=>$label)
                                    <option value="{{ $key }}" {{ request('reason')===$key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Recorded By</label>
                            <input type="text" name="recorded_by" value="{{ request('recorded_by') }}" class="form-control" placeholder="User name">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Sort</label>
                            <select name="sort" class="form-control">
                                <option value="date_desc" {{ request('sort')==='date_desc' ? 'selected' : '' }}>Date: New → Old</option>
                                <option value="date_asc" {{ request('sort')==='date_asc' ? 'selected' : '' }}>Date: Old → New</option>
                                <option value="total_desc" {{ request('sort')==='total_desc' ? 'selected' : '' }}>Value: High → Low</option>
                                <option value="total_asc" {{ request('sort')==='total_asc' ? 'selected' : '' }}>Value: Low → High</option>
                                <option value="qty_desc" {{ request('sort')==='qty_desc' ? 'selected' : '' }}>Qty: High → Low</option>
                                <option value="qty_asc" {{ request('sort')==='qty_asc' ? 'selected' : '' }}>Qty: Low → High</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
                            <a class="btn btn-light border" href="{{ route('writeoffs.index', ['subshop_id'=>$subshop->id]) }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </form>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted small">Filtered results: {{ number_format($writeoffs->total()) }}</div>
                <div class="d-flex align-items-center">
                    @can('export_writeoffs')
                    <div class="dropdown mr-2">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="{{ route('writeoffs.export', ['format' => 'csv'] + request()->query()) }}">
                                <i class="fas fa-file-csv mr-1 text-success"></i> CSV
                            </a>
                            <a class="dropdown-item" href="{{ route('writeoffs.export', ['format' => 'excel'] + request()->query()) }}">
                                <i class="fas fa-file-excel mr-1 text-success"></i> Excel
                            </a>
                            <a class="dropdown-item" href="{{ route('writeoffs.export', ['format' => 'pdf'] + request()->query()) }}">
                                <i class="fas fa-file-pdf mr-1 text-danger"></i> PDF
                            </a>
                        </div>
                    </div>
                    @endcan
                    @can('add_writeoffs')
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addWriteoffModal">
                        <i class="fas fa-plus"></i> Record Write-off
                    </button>
                    @endcan
                </div>
            </div>

            <!-- Write-offs Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Write-offs List</h3>
            
                </div>
                <!-- /.card-header -->
                <div class="card-body p-2">
                    <div class="table-responsive" id="writeoffTable" style="margin: 0 -1px">
                        <table id="writeoffsTable" class="table table-bordered table-hover table-striped m-0" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Shop</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>Reason</th>
                                    <th>Date</th>
                                    <th>Recorded By</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($writeoffs as $writeoff)
                                <tr class="{{ $writeoff->status === 'approved' ? 'table-success' : ($writeoff->status === 'pending' ? 'table-warning' : 'table-danger') }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <i class="fas fa-store text-muted mr-1"></i>
                                        {{ $writeoff->subshop->name ?? 'N/A' }}
                                    </td>
                                    <td>
                                        <i class="fas fa-box text-primary mr-1"></i>
                                        {{ $writeoff->product->name ?? 'N/A' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark">
                                            {{ number_format($writeoff->quantity) }}
                                        </span>
                                    </td>
                                    <td class="text-right font-weight-bold text-primary">
                                        {{ number_format($writeoff->unit_price, 2) }}
                                    </td>
                                    <td class="text-right font-weight-bold text-success">
                                        {{ number_format($writeoff->total_value, 2) }}
                                    </td>
                                    <td>
                                        <span class="d-inline-block text-truncate" style="max-width: 150px;" title="{{ $writeoff->reason }}">
                                            {{ $writeoff->reason }}
                                        </span>
                                    </td>
                                    <td>
                                        <i class="far fa-calendar-alt text-muted mr-1"></i>
                                        {{ $writeoff->write_off_date->format('d/m/Y') ?? 'N/A' }}
                                    </td>
                                    <td>
                                        <i class="fas fa-user text-muted mr-1"></i>
                                        {{ $writeoff->creator->name ?? 'System' }}
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="badge {{ $writeoff->status === 'approved' ? 'badge-success' : ($writeoff->status === 'pending' ? 'badge-warning' : 'badge-danger') }} mb-1" style="min-width: 80px;">
                                                <i class="fas {{ $writeoff->status === 'approved' ? 'fa-check-circle' : ($writeoff->status === 'pending' ? 'fa-clock' : 'fa-times-circle') }} mr-1"></i>
                                                {{ ucfirst($writeoff->status) }}
                                            </span>
                                            
                                            <small class="text-muted" title="Reviewed by {{ $writeoff->reviewed->name ?? 'System' }}">
                                                <i class="fas fa-user-edit"></i> {{ $writeoff->reviewed->name ?? 'System' }}
                                            </small>

                                            @if($writeoff->status === 'rejected' && $writeoff->review_notes)
                                                <button type="button" class="btn btn-xs btn-link p-0 mt-1" data-toggle="tooltip" title="{{ $writeoff->review_notes }}">
                                                    <i class="fas fa-comment-dots text-danger"></i> View Reason
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-info" title="View Details" data-toggle="modal" data-target="#viewWriteoffModal" 
                                                data-product="{{ $writeoff->product->name ?? 'N/A' }}"
                                                data-subshop="{{ $writeoff->subshop->name ?? 'N/A' }}"
                                                data-quantity="{{ number_format($writeoff->quantity) }}"
                                                data-unit-price="{{ $writeoff->unit_price }}"
                                                data-total-value="{{ $writeoff->total_value }}"
                                                data-reason="{{ $writeoff->reason }}"
                                                data-date="{{ $writeoff->write_off_date->format('d/m/Y') }}"
                                                data-recorded-by="{{ $writeoff->creator->name ?? 'System' }}"
                                                data-status="{{ $writeoff->status }}"
                                                data-notes="{{ $writeoff->description ?? 'No additional notes' }}">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            @if($writeoff->status === 'pending')
                                                @can('approve_writeoffs')
                                                <button class="btn btn-success" title="Approve" onclick="updateWriteoffStatus({{ $writeoff->id }}, 'approved')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                @endcan
                                                @can('reject_writeoffs')
                                                <button class="btn btn-warning" title="Reject" onclick="updateWriteoffStatus({{ $writeoff->id }}, 'rejected')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                @endcan
                                                @can('delete_writeoffs')
                                                <button type="button" class="btn btn-danger" title="Delete" onclick="deleteWriteoff({{ $writeoff->id }})">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- /.table-responsive -->
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
        <!-- /.col -->
    </div>
    <!-- /.row -->
</div>
<!-- /.container-fluid -->

<!-- Initialize DataTables -->
@push('js')
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#writeoffsTable').DataTable({
        "responsive": true,
        "autoWidth": false,
        "pageLength": 10,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[7, 'desc']], // Default sort by date column
        "language": {
            "search": "_INPUT_",
            "searchPlaceholder": "Search...",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "Showing 0 to 0 of 0 entries",
            "infoFiltered": "(filtered from _MAX_ total entries)",
            "zeroRecords": "No matching records found",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        },
        "columnDefs": [
            { "orderable": false, "targets": [10] }, // Disable sorting on Actions column
            { "className": "text-center", "targets": [3, 9] }, // Center align quantity and status
            { "className": "text-right", "targets": [4, 5] } // Right align price columns
        ]

});

    // Add search functionality
    $('#searchInput').on('keyup', function() {
        table.search(this.value).draw();
    });

    $('#searchButton').on('click', function() {
        table.search($('#searchInput').val()).draw();
    });

    // Initialize tooltips (guarded)
    if ($.fn && $.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }
});
</script>
@endpush
    <!-- /.card -->
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $writeoffs->appends(request()->query())->links() }}
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- Add Write-off Modal -->
<div class="modal fade" id="addWriteoffModal" tabindex="-1" role="dialog" aria-labelledby="addWriteoffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white;">
                <h5 class="modal-title" id="addWriteoffModalLabel"><i class="fas fa-minus-circle"></i> Write Off Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="writeOffForm" action="{{ route('writeoffs.store') }}" method="POST">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-body">
                    <!-- Product Selection -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-info-circle"></i> Product Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_id">Product <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="product_id" name="product_id" required>
                                            <option value="">Select Product</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}" 
                                                    data-quantity="{{ $product->quantity }}"
                                                    data-price="{{ $product->price }}"
                                                    data-name="{{ $product->name }}">
                                                    {{ $product->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="batch_id">Batch <span class="text-danger">*</span></label>
                                        <select class="form-control" id="batch_id" name="batch_id" required>
                                            <option value="">Select Batch</option>
                                        </select>
                                        <small class="text-muted">Choose the batch to write off from.</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="quantity">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                                        <small class="text-muted">Max: <span id="max_quantity_text">0</span></small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="writeoff_date">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="writeoff_date" name="writeoff_date" value="{{ date('Y-m-d') }}" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Write Off Details -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-edit"></i> Write Off Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reason">Reason <span class="text-danger">*</span></label>
                                        <select class="form-control" id="reason" name="reason" required>
                                            <option value="">Select Reason</option>
                                            <option value="damage">Damaged</option>
                                            <option value="expiry">Expired</option>
                                            <option value="theft">Theft</option>
                                            <option value="obsolescence">Obsolescence</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="notes">Additional Notes</label>
                                        <input type="text" class="form-control" id="notes" name="notes" placeholder="Optional description">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calculation Display -->
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h6 class="mb-0"><i class="fas fa-calculator"></i> Write Off Value Calculation</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <strong>Quantity:</strong><br>
                                        <span id="calc_quantity" class="h5 text-primary">0</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <strong>Unit Price (Batch):</strong><br>
                                        <span id="calc_unit_price" class="h5 text-success">TZS 0</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <strong>Total Value:</strong><br>
                                        <span id="calc_total_value" class="h5 text-danger font-weight-bold">TZS 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="submitWriteOffForm()">
                        <i class="fas fa-minus-circle"></i> Write Off Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Write-off Details Modal -->
<div class="modal fade" id="viewWriteoffModal" tabindex="-1" role="dialog" aria-labelledby="viewWriteoffModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewWriteoffModalLabel">Write-off Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 40%;">Product:</th>
                            <td id="view-product"></td>
                        </tr>
                        <tr>
                            <th>Quantity:</th>
                            <td id="view-quantity"></td>
                        </tr>
                        <tr>
                            <th>Reason:</th>
                            <td id="view-reason"></td>
                        </tr>
                        <tr>
                            <th>Date:</th>
                            <td id="view-date"></td>
                        </tr>
                        <tr>
                            <th>Recorded By:</th>
                            <td id="view-recorded-by"></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td id="view-status"></td>
                        </tr>
                        <tr>
                            <th>Notes:</th>
                            <td id="view-notes"></td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@stop

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 38px !important;
        padding: 5px 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
</style>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>


<script>

  $(function () {
        // Initialize DataTable
    $('#writeOffTable').DataTable();
  });

// Expose product batches to JS
@php
    $productBatches = $products->mapWithKeys(function($p){
        return [
            $p->id => $p->itemBatches->map(function($b){
                return [
                    'id' => $b->id,
                    'batch_number' => $b->batch_number,
                    'quantity' => (int)($b->quantity ?? 0),
                    'selling_price' => (float)($b->selling_price ?? 0),
                    'expiry_date' => optional($b->expire_date)->format('Y-m-d') ?? (optional($b->expiry_date)->format('Y-m-d') ?? null),
                ];
            })->values()->all()
        ];
    })->toArray();
    $productBatchesJson = json_encode($productBatches);
@endphp
window.PRODUCT_BATCHES = {!! $productBatchesJson !!};

// Helper formatters
function fmtTZS(val){ const n = Number(val) || 0; return 'TZS ' + n.toLocaleString(); }

// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // console.log('DOM fully loaded');
    
    // Make sure jQuery is loaded
    if (typeof jQuery == 'undefined') {
        console.error('jQuery is not loaded!');
        return;
    }
    
    // Format number with commas
    function formatNumber(num) {
        if (isNaN(num)) return '0';
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Update write-off calculation display (uses selected batch)
    window.updateWriteOffCalculation = function() {
        try {
            // console.log('Updating calculations...');
            const $quantity = $('#quantity');
            const $productSelect = $('#product_id');
            const $batchSelect = $('#batch_id');
            const selectedOption = $productSelect.find('option:selected');
            const selectedBatch = $batchSelect.find('option:selected');
            
            // console.log('Selected product:', selectedOption.val(), 'Quantity:', $quantity.val());
            
            // Only proceed if elements exist and a product is selected
            if ($quantity.length && $productSelect.length && selectedOption.val() !== '') {
                const quantity = parseInt($quantity.val()) || 0;
                // Determine batch price and available from selected batch
                const batchPrice = parseFloat(selectedBatch.data('price') || selectedBatch.attr('data-price') || 0);
                const availableQty = parseInt(selectedBatch.data('qty') || selectedBatch.attr('data-qty') || 0);
                const total = quantity * batchPrice;
                
                // console.log('Data attributes:', selectedOption[0].dataset); // Debug: log all data attributes
                
                // console.log('Raw values - Price:', selectedOption.data('price'), 'Qty:', selectedOption.data('quantity'));
                // console.log('Calculated values - Quantity:', quantity, 'Price:', price, 'Total:', total, 'Available:', availableQty);
                
                // Update the display
                $('#calc_quantity').text(formatNumber(quantity));
                const formattedPrice = batchPrice ? batchPrice.toFixed(2) : '0.00';
                const formattedTotal = total ? total.toFixed(2) : '0.00';
                $('#calc_unit_price').text('TZS ' + formatNumber(formattedPrice));
                $('#calc_total_value').text('TZS ' + formatNumber(formattedTotal));
                
                // Update max quantity display
                const $maxQtyText = $('#max_quantity_text');
                if ($maxQtyText.length) {
                    $maxQtyText.text(availableQty);
                }
                
                // Update max attribute on quantity input
                $quantity.attr({
                    'max': availableQty,
                    'min': 1
                });
                
                // console.log('Display updated');
            } else {
                // Reset values if no product is selected
                $('#calc_quantity').text('0');
                $('#calc_unit_price').text('TZS 0.00');
                $('#calc_total_value').text('TZS 0.00');
                $('#max_quantity_text').text('0');
                
                if ($quantity.length) {
                    $quantity.attr('max', 0).val('0');
                }
                
                // console.log('No product selected or elements not found');
            }
        } catch (error) {
            console.error('Error in updateWriteOffCalculation:', error);
        }
    };
    
    // Populate batches for selected product
    function populateBatches(productId){
        const $batch = $('#batch_id');
        $batch.empty();
        $batch.append('<option value="">Select Batch</option>');
        const batches = (window.PRODUCT_BATCHES && window.PRODUCT_BATCHES[productId]) ? window.PRODUCT_BATCHES[productId] : [];
        batches.forEach(function(b){
            const opt = $('<option></option>')
                .val(b.id)
                .text((b.batch_number || 'BATCH') + ' | Qty: ' + (b.quantity || 0) + ' | Price: ' + fmtTZS(b.selling_price) + (b.expiry_date ? (' | Exp: ' + b.expiry_date) : ''))
                .attr('data-qty', b.quantity || 0)
                .attr('data-price', b.selling_price || 0);
            $batch.append(opt);
        });
        // Auto-select first available batch
        if ($batch.find('option').length > 1) {
            $batch.prop('selectedIndex', 1);
        } else {
            $batch.empty().append('<option value="" disabled selected>No batches available</option>');
            $('#max_quantity_text').text('0');
            $('#quantity').attr('max', 0).val('0');
        }
    }

    // Initialize the form when the modal is shown
    $(document).on('show.bs.modal', '#addWriteoffModal', function() {
        // console.log('Modal shown, initializing...');
        
        // Initialize Select2 on product select with modal parent to enable search
        const $product = $('#product_id');
        if ($product.data('select2')) { $product.select2('destroy'); }
        $product.select2({
            placeholder: 'Select a product',
            allowClear: true,
            dropdownParent: $('#addWriteoffModal'),
            width: '100%',
            minimumResultsForSearch: 0
        }).on('select2:open', function(){
            const sf = document.querySelector('.select2-container--open .select2-search__field');
            if (sf) { sf.focus(); }
        }).on('select2:select', function(e) {
            // Force update of data attributes
            const selectedOption = $(e.params.data.element);
            const productId = selectedOption.val();
            // Populate batch list for this product
            populateBatches(productId);
            // When a product is selected, update the max and min values
            const $batchSel = $('#batch_id');
            const selectedBatch = $batchSel.find('option:selected');
            const availableQty = parseInt(selectedBatch.attr('data-qty') || 0);
            const $quantity = $('#quantity');
            
            // Only set default value if the field is empty or 0
            if (!$quantity.val() || parseInt($quantity.val()) === 0) {
                $quantity.val(availableQty > 0 ? 1 : 0);
            }
            
            // Update min and max attributes
            $quantity.attr({
                'max': availableQty,
                'min': 1
            });
            
            // Update the max quantity display
            $('#max_quantity_text').text(availableQty);
            
            // Trigger calculation update
            updateWriteOffCalculation();
        });
        
        // Set today's date
        const today = new Date().toISOString().split('T')[0];
        $('#writeoff_date').val(today);
        
        // Initialize batch list if a product is pre-selected
        const initialProduct = $('#product_id').val();
        if (initialProduct) { populateBatches(initialProduct); }
        // Initialize calculation
        updateWriteOffCalculation();
        
        // Product change handler
        $(document).off('change', '#product_id').on('change', '#product_id', function() {
            // console.log('Product changed');
            const $selectedOption = $(this).find('option:selected');
            const productId = $selectedOption.val();
            populateBatches(productId);
            updateWriteOffCalculation();
        });

        // Batch change handler
        $(document).off('change', '#batch_id').on('change', '#batch_id', function(){
            const sel = $(this).find('option:selected');
            const bQty = parseInt(sel.attr('data-qty') || '0') || 0;
            $('#max_quantity_text').text(bQty);
            const $quantityInput = $('#quantity');
            $quantityInput.attr('max', bQty);
            // Set default quantity to 1 if stock is available
            if (!$quantityInput.val() || parseInt($quantityInput.val()) === 0) {
                $quantityInput.val(bQty > 0 ? 1 : 0);
            }
            updateWriteOffCalculation();
        });
        
        // Quantity change handler
        $(document).off('input', '#quantity').on('input', '#quantity', function(e) {
            const $input = $(this);
            let value = $input.val();
            
            // Only allow numbers and backspace
            if (value !== '' && !/^\d+$/.test(value)) {
                $input.val(value.replace(/[^\d]/g, ''));
                return;
            }
            
            updateWriteOffCalculation();
        });
        
        // Handle blur event to validate quantity
        $(document).off('blur', '#quantity').on('blur', '#quantity', function() {
            const $input = $(this);
            const maxQty = parseInt($input.attr('max')) || 0;
            let currentQty = parseInt($input.val()) || 0;
            
            // If empty or 0, set to 1 if there's stock
            if (currentQty < 1 && maxQty > 0) {
                currentQty = 1;
                $input.val(currentQty);
            } 
            // If exceeds max, set to max
            else if (currentQty > maxQty && maxQty > 0) {
                currentQty = maxQty;
                $input.val(currentQty);
                
                Swal.fire({
                    icon: 'warning',
                    title: 'Insufficient Stock',
                    text: `Only ${maxQty} items available in stock.`,
                    timer: 3000,
                    showConfirmButton: false
                });
            }
            
            updateWriteOffCalculation();
        });
    });
    
    // Initial calculation
    updateWriteOffCalculation();
});

// Submit write-off form
window.submitWriteOffForm = function() {
    const form = document.getElementById('writeOffForm');
    if (!form) {
        console.error('Form not found');
        return;
    }

    // Create FormData
    const formData = new FormData(form);
    const productId = formData.get('product_id');
    const quantity = formData.get('quantity');
    const reason = formData.get('reason');
    const writeoffDate = formData.get('writeoff_date');

    // Validate required fields
    if (!productId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please select a product',
            showConfirmButton: true
        });
        return;
    }

    if (!quantity || parseInt(quantity) <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please enter a valid quantity',
            showConfirmButton: true
        });
        return;
    }

    if (!reason) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please select a reason for write-off',
            showConfirmButton: true
        });
        return;
    }

    if (!writeoffDate) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please select a write-off date',
            showConfirmButton: true
        });
        return;
    }

    // Show loading state
    const submitBtn = $('button[onclick="submitWriteOffForm()"]');
    const originalBtnText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

    // Submit the form via AJAX
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(JSON.stringify(err));
            });
        }
        return response.json();
    })
    .then(data => {
        // Show success message
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: data.message || 'Write-off recorded successfully',
            showConfirmButton: false,
            timer: 2000
        });

        // Close the modal and reload the page after a short delay
        setTimeout(() => {
            $('#addWriteoffModal').modal('hide');
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.reload();
            }
        }, 1000);
    })
    .catch(error => {
        console.error('Error:', error);
        let errorMessage = 'An error occurred while processing your request';
        
        try {
            const errorData = JSON.parse(error.message);
            if (errorData.message) {
                errorMessage = errorData.message;
            } else if (errorData.errors) {
                errorMessage = Object.values(errorData.errors).flat().join('\n');
            }
        } catch (e) {
            // If we can't parse the error, use the default message
        }

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMessage,
            showConfirmButton: true
        });
    })
    .finally(() => {
        // Restore button state
        submitBtn.prop('disabled', false).html(originalBtnText);
    });
};

// All initialization code has been moved to the DOMContentLoaded event handler above
    
    // Function to handle write-off status update (approve/reject)
// Function to delete a write-off
function deleteWriteoff(writeoffId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait while we delete the write-off',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send delete request
            const token = $('meta[name="csrf-token"]').attr('content');
            
            $.ajax({
                url: `/admin/inventory/writeoffs/${writeoffId}`,
                type: 'DELETE',
                data: {
                    _token: token
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: response.message || 'Write-off has been deleted.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Reload the page to update the list
                            window.location.reload();
                        });
                    } else {
                        throw new Error(response.message || 'Failed to delete write-off');
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'An error occurred while deleting the write-off.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    Swal.fire('Error!', errorMessage, 'error');
                }
            });
        }
    });
}

function updateWriteoffStatus(writeoffId, status) {
    const showRejectionDialog = async () => {
        if (status !== 'rejected') {
            return '';
        }
        
        // Close any existing loading dialogs
        Swal.close();
        
        try {
            const { value: reason } = await Swal.fire({
                title: 'Reason for Rejection',
                html: `
                    <div class="mb-3">
                        <label class="form-label d-block text-start mb-2">Please enter a reason for rejection:</label>
                        <textarea 
                            id="swal-reason" 
                            class="form-control" 
                            rows="4" 
                            placeholder="Enter the reason here..."
                            style="width: 100%; min-height: 100px;"
                        ></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Submit',
                confirmButtonColor: '#28a745',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                cancelButtonColor: '#6c757d',
                showLoaderOnConfirm: true,
                allowOutsideClick: () => !Swal.isLoading(),
                focusConfirm: false,
                preConfirm: () => {
                    const value = document.getElementById('swal-reason').value;
                    if (!value || value.trim() === '') {
                        Swal.showValidationMessage('Please provide a reason for rejection');
                        return false;
                    }
                    return value.trim();
                },
                didOpen: () => {
                    // Focus the textarea when dialog opens
                    setTimeout(() => {
                        const input = document.getElementById('swal-reason');
                        if (input) input.focus();
                    }, 100);
                }
            });
            
            // If we get here, the dialog was either confirmed (with a reason) or dismissed
            // For confirmed dialogs, reason will be the trimmed value
            // For dismissed dialogs, reason will be undefined
            return reason !== undefined ? reason : null;
        } catch (error) {
            console.error('Error in rejection dialog:', error);
            return null;
        }
    };

    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to ${status} this write-off.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: status === 'approved' ? '#28a745' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${status} it!`,
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then(async (result) => {
        if (!result.isConfirmed) return;

        // Show processing state
        const loadingSwal = Swal.fire({
            title: 'Processing...',
            text: 'Please wait while we process your request',
            allowOutsideClick: false,
            showConfirmButton: false,
         
            customClass: {
                popup: 'swal-wide'
            }
        });
        
        // Add custom CSS for the loading dialog
        if (!document.getElementById('swal-custom-css')) {
            const style = document.createElement('style');
            style.id = 'swal-custom-css';
            style.textContent = `
                .swal-wide {
                    width: 400px !important;
                }
                .swal2-popup .swal2-textarea {
                    min-height: 100px !important;
                }
                .swal2-actions {
                    margin: 1.25em auto 0 !important;
                }
            `;
            document.head.appendChild(style);
        }

        try {
            // Get any additional notes if needed
            const notes = await showRejectionDialog();
            
            // If notes is null, user cancelled the rejection dialog
            if (notes === null) {
                loadingSwal.close();
                return;
            }

            // Get the update status URL and replace the placeholder with the actual writeoff ID
            const updateStatusUrl = document.querySelector('meta[name="update-status-route"]').getAttribute('content').replace('WRITEOFF_ID', writeoffId);
            
            // Send the request
            return fetch(updateStatusUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    status: status,
                    notes: notes || ''
                })
            })
            .then(response => response.json())
            .then(data => {
                loadingSwal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        // Reload the page to show updated status
                        window.location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Failed to update status');
                }
            })
            .catch(error => {
                loadingSwal.close();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: error.message || 'An error occurred while updating the status.',
                    confirmButtonText: 'OK'
                });
            });
        } catch (error) {
            loadingSwal.close();
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An unexpected error occurred.',
                confirmButtonText: 'OK'
            });
        }
    });
}

// View write-off details modal
$('#viewWriteoffModal').on('show.bs.modal', function (event) {
    const button = $(event.relatedTarget);
    const modal = $(this);
    
    // Update modal title and content
    modal.find('#viewWriteoffModalLabel').text('Write-off Details: ' + button.data('product'));
    $('#view-product').text(button.data('product') || 'N/A');
    $('#view-quantity').text(button.data('quantity') || '0');
    $('#view-reason').text(button.data('reason') || 'N/A');
    $('#view-date').text(button.data('date') || 'N/A');
    $('#view-recorded-by').text(button.data('recorded-by') || 'System');
    
    // Format status with appropriate badge
    const status = button.data('status') || 'pending';
    const statusHtml = status === 'approved' 
        ? '<span class="badge badge-success">Approved</span>' 
        : (status === 'pending' 
            ? '<span class="badge badge-warning">Pending</span>' 
            : '<span class="badge badge-danger">Rejected</span>');
    $('#view-status').html(statusHtml);
    
    // Set notes or default message
    $('#view-notes').text(button.data('notes') || 'No additional notes');
});


@if (session('success'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: "{{ session('success') }}",
            showConfirmButton: false,
            timer: 2500
        });
    });
@endif

@if (session('error'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Kuna Tatizo!',
            text: "{{ session('error') }}",
            showConfirmButton: true
        });
    });
@endif

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});
</script>
@stop