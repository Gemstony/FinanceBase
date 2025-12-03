@extends('adminlte::page')

@section('title', 'Items Management - ' . $subshop->name)

@section('plugins.Datatables', true)

@section('css')
<!-- Ensure jQuery is loaded -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<!-- SweetAlert2 for beautiful alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Fallback for SweetAlert2 -->
<script>
// Fallback for SweetAlert2 if not loaded
if (typeof Swal === 'undefined') {
    window.Swal = {
        fire: function(options) {
            if (typeof options === 'string') {
                alert(options);
            } else {
                alert(options.title || 'Alert');
            }
        },
        showValidationMessage: function(message) {
            alert(message);
        },
        isLoading: function() {
            return false;
        },
        isConfirmed: function() {
            return true;
        }
    };
}
</script>
@endsection

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-box"></i> Items Management</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-box"></i> Items</h1>
                    <p class="mb-0 text-light">Managing items for: <strong>{{ $subshop->name }}</strong></p>
                </div>
                @can('add_items')
                <button type="button" class="btn btn-light" data-toggle="modal" data-target="#addItemModal">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                @endcan
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('items.subshops') }}">Choose Shop</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Items</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center">
            <a href="{{ route('items.subshops') }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Change Shop
            </a>
        </div>
    </div>
@stop

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<!-- Summary Cards -->
<style>
    /* Responsive font sizes for card numbers */
    .small-box .inner h3 {
        font-size: 2.2rem;
        font-weight: bold;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 5px;
    }
    
    /* For small devices */
    @media (max-width: 576px) {
        .small-box .inner h3 {
            font-size: 1.5rem;
        }
        .small-box .inner p {
            font-size: 0.9rem;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
    
    /* For medium devices */
    @media (min-width: 577px) and (max-width: 991px) {
        .small-box .inner h3 {
            font-size: 1.8rem;
        }
    }
    
    /* Ensure text wraps properly in card headers */
    .card-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }
    
    .card-header h5, 
    .card-header .btn-group {
        margin: 0;
    }
    
    .card-header .btn-group {
        flex-shrink: 0;
    }
    
    /* Make buttons stack on small screens */
    @media (max-width: 576px) {
        .btn-group-vertical {
            width: 100%;
        }
        .btn-group-vertical > .btn {
            width: 100%;
            margin-bottom: 5px;
        }
    }
</style>

<div class="row mb-4">
    <div class="col-lg-3 col-6 mb-3">
        <div class="small-box bg-primary h-100">
            <div class="inner">
                <div class="d-flex justify-content-between">
                    <h3 class="mb-0">{{ $stats['total_items'] }}</h3>
                    <div class="icon">
                        <i class="fas fa-box"></i>
                    </div>
                </div>

                <p class="mb-0 mt-2">Total Items</p>
            </div>
            <a href="#" class="small-box-footer d-block p-2 text-center" onclick="loadModalData('totalItems'); $('#totalItemsModal').modal('show');">
                More info <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="small-box bg-success h-100">
            <div class="inner">
                <div class="d-flex justify-content-between">
                    <h3 class="mb-0">{{ number_format($stats['total_value'], 2) }}</h3>
                    <div class="icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <p class="mb-0 mt-2">Total Value (TZS)</p>
            </div>
            <a href="#" class="small-box-footer d-block p-2 text-center" onclick="loadModalData('totalValue'); $('#totalValueModal').modal('show');">
                More info <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="small-box bg-info h-100">
            <div class="inner">
                <div class="d-flex justify-content-between">
                    <h3 class="mb-0">{{ $stats['items_in_stock'] }}</h3>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <p class="mb-0 mt-2">Items In Stock</p>
            </div>
            <a href="#" class="small-box-footer d-block p-2 text-center" onclick="loadModalData('inStock'); $('#inStockModal').modal('show');">
                More info <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $stats['low_stock_items'] }}</h3>
                    <p>Low Stock Items</p>
                </div>
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <a href="#" class="small-box-footer d-block p-2 text-center" onclick="loadModalData('lowStock'); $('#lowStockModal').modal('show');">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="small-box bg-danger h-100">
            <div class="inner">
                <div class="d-flex justify-content-between">
                    <h3 class="mb-0">{{ $stats['items_out_of_stock'] }}</h3>
                    <div class="icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
                <p class="mb-0 mt-2">Out of Stock</p>
            </div>
            <a href="#" class="small-box-footer d-block p-2 text-center" onclick="loadModalData('outOfStock'); $('#outOfStockModal').modal('show');">
                More info <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-secondary">
            <div class="inner">
                <h3>{{ $stats['active_items'] }}</h3>
                <p>Active Items</p>
            </div>
            <div class="icon">
                <i class="fas fa-toggle-on"></i>
            </div>
            <a href="#" class="small-box-footer d-block p-2 text-center" onclick="loadModalData('activeItems'); $('#activeItemsModal').modal('show');">
                More info <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-light">
            <div class="inner">
                <h3>{{ $stats['total_categories'] }}</h3>
                <p>Categories</p>
            </div>
            <div class="icon">
                <i class="fas fa-tags"></i>
            </div>
            <a href="#" class="small-box-footer d-block p-2 text-center" onclick="loadModalData('categories'); $('#categoriesModal').modal('show');">
                More info <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-dark">
            <div class="inner">
                <h3>{{ $stats['total_suppliers'] }}</h3>
                <p>Suppliers</p>
            </div>
            <div class="icon">
                <i class="fas fa-truck"></i>
            </div>
            <a href="#" class="small-box-footer d-block p-2 text-center" onclick="loadModalData('suppliers'); $('#suppliersModal').modal('show');">
                More info <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
</div>

<!-- Advanced Filters -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <button class="btn btn-outline-primary btn-sm" type="button" data-toggle="collapse" data-target="#advancedFilters" aria-expanded="false" aria-controls="advancedFilters">
                <i class="fas fa-filter"></i> Advanced Filters
                <i class="fas fa-chevron-down ml-1" id="filterIcon"></i>
            </button>
            <small class="text-muted ml-2">Click to show/hide advanced filtering options</small>
        </h5>
    </div>
    <div class="collapse" id="advancedFilters">
        <div class="card-body">
            <form method="GET" action="{{ route('items.index') }}">
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select class="form-control" id="category_id" name="category_id">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="supplier_id">Supplier</label>
                            <select class="form-control" id="supplier_id" name="supplier_id">
                                <option value="">All Suppliers</option>
                                @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="min_price">Min Price</label>
                            <input type="number" class="form-control" id="min_price" name="min_price" step="0.01" min="0" value="{{ request('min_price') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="max_price">Max Price</label>
                            <input type="number" class="form-control" id="max_price" name="max_price" step="0.01" min="0" value="{{ request('max_price') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="min_quantity">Min Quantity</label>
                            <input type="number" class="form-control" id="min_quantity" name="min_quantity" min="0" value="{{ request('min_quantity') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="max_quantity">Max Quantity</label>
                            <input type="number" class="form-control" id="max_quantity" name="max_quantity" min="0" value="{{ request('max_quantity') }}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="created_from">Created From</label>
                            <input type="date" class="form-control" id="created_from" name="created_from" value="{{ request('created_from') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="created_to">Created To</label>
                            <input type="date" class="form-control" id="created_to" name="created_to" value="{{ request('created_to') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group d-flex align-items-end">
                            <button type="submit" class="btn btn-primary mr-2">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="{{ route('items.index', ['subshop_id' => $subshop->id]) }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    .important-gap{
        gap: 1rem !important;
    }
</style>
<div class="card">
    <div class="card-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center important-gap">
            <h5 class="mb-2 mb-md-0"><i class="fas fa-list "></i> Items List</h5>
            <div class="d-flex flex-wrap align-items-center important-gap">
                <div class="d-flex flex-wrap gap-3 important-gap">
                    @can('bulk_import_items')
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#importItemsModal">
                        <i class="fas fa-file-import"></i> <span class="d-none d-md-inline">Bulk Import</span>
                    </button>
                    @endcan
                     @can('add_items')
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addItemModal">
                        <i class="fas fa-plus"></i> <span class="d-none d-md-inline">Add Item</span>
                    </button>
                    @endcan
                </div>
                
                <form method="GET" action="{{ route('items.index') }}" class="d-flex flex-grow-1 flex-md-grow-0" style="min-width: 200px;">
                    <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="{{ request('search') }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
                @can('export_items_reports')
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="exportDropdown" data-toggle="dropdown">
                        <i class="fas fa-download"></i> <span class="d-none d-md-inline">Export</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="{{ route('items.export', ['format' => 'csv'] + request()->query()) }}"><i class="fas fa-file-csv mr-1 text-success"></i> CSV</a>
                        <a class="dropdown-item" href="{{ route('items.export', ['format' => 'excel'] + request()->query()) }}"><i class="fas fa-file-excel mr-1 text-success"></i> Excel</a>
                        <a class="dropdown-item" href="{{ route('items.export', ['format' => 'pdf'] + request()->query()) }}"><i class="fas fa-file-pdf mr-1 text-danger"></i> PDF</a>
                    </div>
                </div>
                @endcan
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="itemsTable">
                <thead class="thead-dark">
                    <tr>
                        <th><i class="fas fa-tag"></i> Name</th>
                        <th><i class="fas fa-barcode"></i> SKU</th>
                        <th><i class="fas fa-list"></i> Category</th>
                        <th><i class="fas fa-truck"></i> Supplier</th>
                        <th><i class="fas fa-dollar-sign"></i> Price Range</th>
                        <th><i class="fas fa-percent"></i> Margin</th>
                        <th><i class="fas fa-boxes"></i> Total Qty</th>
                        <th><i class="fas fa-layer-group"></i> Batches</th>
                        <th><i class="fas fa-calendar-times"></i> Earliest Expiry</th>
                        <th><i class="fas fa-exchange-alt"></i> Transactions</th>
                        <th><i class="fas fa-toggle-on"></i> Status</th>
                        <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    @php
                        $totalQuantity = $item->itemBatches->sum('quantity');
                        $batchCount = $item->itemBatches->count();
                        $minPrice = $item->itemBatches->min('selling_price');
                        $maxPrice = $item->itemBatches->max('selling_price');
                        $avgCostPrice = $item->itemBatches->avg('cost_price');
                        $earliestBatch = $item->itemBatches
                            ->filter(function($b){ return !is_null($b->expiry_date) || !is_null($b->expire_date); })
                            ->sortBy(function($b){ return $b->expiry_date ?? $b->expire_date; })
                            ->first();
                        $earliestExpiry = $earliestBatch ? ($earliestBatch->expiry_date ?? $earliestBatch->expire_date) : null;
                        $batchesJson = $item->itemBatches->map(function($b){
                            return [
                                'id' => $b->id,
                                'batch_number' => $b->batch_number,
                                'quantity' => (int)($b->quantity ?? 0),
                                'cost_price' => (float)($b->cost_price ?? 0),
                                'selling_price' => (float)($b->selling_price ?? 0),
                                'expiry_date' => ($b->expiry_date ?? $b->expire_date) ? ($b->expiry_date ?? $b->expire_date)->format('Y-m-d') : null,
                                'received_at' => $b->created_at ? $b->created_at->format('Y-m-d') : null,
                            ];
                        })->values()->toJson();
                        $batchesEncoded = rawurlencode($batchesJson);
                    @endphp
                        <td>
                            <strong>{{ $item->name }}</strong>
                            @if($item->description)
                            <br><small class="text-muted">{{ Str::limit($item->description, 50) }}</small>
                            @endif
                        </td>
                        <td>
                            @if($item->sku)
                            <code>{{ $item->sku }}</code>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($item->category)
                            <span class="badge badge-info">{{ $item->category->name }}</span>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($item->supplier)
                            {{ $item->supplier->name }}
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($batchCount > 0)
                                @if($minPrice == $maxPrice)
                                    <strong class="text-success">Tsh {{ number_format($minPrice, 2) }}</strong>
                                @else
                                    <strong class="text-success">Tsh {{ number_format($minPrice, 2) }} - {{ number_format($maxPrice, 2) }}</strong>
                                @endif
                            @else
                                <strong class="text-success">Tsh {{ number_format($item->price, 2) }}</strong>
                            @endif
                        </td>
                        <td>
                            @if($avgCostPrice && $avgCostPrice > 0)
                                <span class="badge badge-info">{{ number_format((($item->price - $avgCostPrice) / $avgCostPrice) * 100, 1) }}%</span>
                            @elseif($item->cost_price && $item->cost_price > 0)
                                <span class="badge badge-info">{{ number_format($item->margin_percentage, 1) }}%</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <div>
                                    <span class="badge badge-primary">{{ $totalQuantity }} {{ $item->unit }}</span>
                                    @if($item->total_write_off_quantity > 0)
                                    <span class="badge badge-danger ml-1" title="Total Write-offs: {{ $item->total_write_off_quantity }}">
                                        <i class="fas fa-minus-circle"></i> {{ $item->total_write_off_quantity }}
                                    </span>
                                @endif
                                </div>
                                @if($totalQuantity <= 0)
                                    <small class="text-danger"><i class="fas fa-times-circle"></i> Out of Stock</small>
                                @elseif($item->min_quantity && $totalQuantity <= $item->min_quantity)
                                    <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Low Stock ({{ $totalQuantity }} left)</small>
                                @elseif($totalQuantity > $item->max_quantity && $item->max_quantity > 0)
                                    <small class="text-info"><i class="fas fa-exclamation-triangle"></i> Over stocked(
                                        @php
                                         $overstocked_quantity = $totalQuantity - $item->max_quantity
                                        @endphp
                                    {{ $overstocked_quantity }} More that Max)</small>
                                @endif
                                
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-secondary">{{ $batchCount }}</span>
                            @if($batchCount > 0)
                                <br><small class="text-muted">batches</small>
                            @endif
                        </td>
                        <td>
                    @if($earliestExpiry && $earliestBatch)
                    @php
                        $expiryDate = \Carbon\Carbon::parse($earliestExpiry);
                        $isExpired = $expiryDate->isPast();
                        $daysUntilExpiry = $expiryDate->diffInDays(now());
                        $expiredQty = (int)($earliestBatch->quantity ?? 0);
                    @endphp
                    <div class="d-flex flex-column">
                        <span class="badge {{ $isExpired ? 'badge-danger' : ($daysUntilExpiry <= 30 ? 'badge-warning' : 'badge-success') }}">
                            {{ $expiryDate->format('d/m/Y') }}
                        </span>
                        <small class="text-muted">Batch: {{ $earliestBatch->batch_number ?? ('#'.$earliestBatch->id) }}</small>

                        @if($isExpired && $expiredQty > 0)
                            <small class="text-danger mb-1">Expired</small>
                            @can('writeoff_items')
                            <button type="button" class="btn btn-xs btn-danger" title="Write off expired batch"
                                onclick="writeOffExpiredBatch({{ $item->id }}, '{{ addslashes($item->name) }}', {{ $earliestBatch->id }}, '{{ $batchesEncoded }}', {{ (int)($earliestBatch->quantity ?? 0) }}, '{{ (float)($earliestBatch->selling_price ?? 0) }}')">
                                <i class="fas fa-minus-circle"></i> Write off
                            </button>
                            @endcan
                        @elseif($isExpired && $expiredQty <= 0)
                            <small class="text-muted">Expired (no stock)</small>
                        @elseif($daysUntilExpiry <= 30)
                            <small class="text-warning">{{ $daysUntilExpiry }} days left</small>
                        @endif
                    </div>
                @else
                    <span class="text-muted">-</span>
                @endif
                        </td>
                        <td>
                            <span class="badge badge-secondary">{{ $item->total_transactions }}</span>
                            <br><small class="text-muted">{{ $item->total_quantity_transacted }} total</small>
                        </td>
                        <td>
                            @if($item->is_active)
                            <span class="badge badge-success">Active</span>
                            @else
                            <span class="badge badge-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                @php
                                    // batchesEncoded prepared above
                                @endphp
                                <button type="button" class="btn btn-sm btn-outline-info" 
                                    onclick="viewItem(
                                        '{{ $item->id }}', 
                                        '{{ addslashes($item->name) }}', 
                                        '{{ addslashes($item->description ?? '') }}', 
                                        '{{ $item->category ? addslashes($item->category->name) : '' }}', 
                                        '{{ $item->supplier ? addslashes($item->supplier->name) : '' }}', 
                                        '{{ $item->sku ?? '' }}', 
                                        '{{ $item->barcode ?? '' }}', 
                                        '{{ $item->price }}', 
                                        '{{ $item->cost_price ?? '' }}', 
                                        '{{ $totalQuantity }}', 
                                        '{{ $item->min_quantity ?? '' }}', 
                                        '{{ $item->max_quantity ?? '' }}', 
                                        '{{ $item->unit }}', 
                                        '{{ $item->is_active ? 'true' : 'false' }}', 
                                        '{{ $item->expiry_date ? $item->expiry_date->format('Y-m-d') : '' }}', 
                                        '{{ $subshop->name }}', 
                                        '{{ $item->total_transactions }}', 
                                        '{{ $item->total_quantity_transacted }}', 
                                        '{{ $item->margin_percentage ?? 0 }}', 
                                        '{{ $item->total_write_off_quantity }}',
                                        '{{ $batchesEncoded }}'
                                    )"
                                >
                                    <i class="fas fa-eye"></i> View
                                </button>
                                @can('edit_items')
                                <button type="button" class="btn btn-sm btn-primary" onclick="editItem({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ addslashes($item->description ?? '') }}', '{{ $item->category_id ?? '' }}', '{{ $item->supplier_id ?? '' }}', '{{ $item->sku ?? '' }}', '{{ $item->barcode ?? '' }}', '{{ $item->price }}', '{{ $item->cost_price ?? '' }}', '{{ $totalQuantity }}', '{{ $item->min_quantity ?? '' }}', '{{ $item->max_quantity ?? '' }}', '{{ $item->unit }}', '{{ $item->is_active ? 'true' : 'false' }}', '{{ $item->expiry_date ? $item->expiry_date->format('Y-m-d') : '' }}')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                @endcan
                                @can('writeoff_items')
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="writeOffItem({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ $totalQuantity }}', '{{ $item->price }}', '{{ $batchesEncoded }}')">
                                    <i class="fas fa-minus-circle"></i> Write Off
                                </button>
                                @endcan
                                @can('delete_items')
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteItem({{ $item->id }}, '{{ addslashes($item->name) }}')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                @endcan
                                @can('transfer_items')
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openTransferModal({{ $item->id }}, '{{ addslashes($item->name) }}', decodeBatches('{{ $batchesEncoded }}'))">
                                    <i class="fas fa-exchange-alt"></i> Transfer
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Items Found</h5>
                            <p class="text-muted">Start by adding your first item to this shop.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination handled by DataTables (frontend) -->

        <!-- Transfer Modal -->
        <div class="modal fade" id="transferModal" tabindex="-1" role="dialog" aria-labelledby="transferModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="transferModalLabel"><i class="fas fa-exchange-alt"></i> Transfer Item</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="transferForm">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" id="transfer_item_id">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Source Subshop</label>
                                    <input type="text" class="form-control" value="{{ $subshop->name }}" disabled>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="destination_subshop_id">Destination Subshop</label>
                                    <select class="form-control" id="destination_subshop_id" required>
                                        <option value="">Select destination</option>
                                        @foreach($destinationSubshops as $d)
                                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Batches to Transfer (FEFO)</label>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered" id="transfer_batches_table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Batch</th>
                                                <th>Expiry</th>
                                                <th>Available</th>
                                                <th>Cost</th>
                                                <th>Qty to Transfer</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <small class="text-muted">Batches are sorted by earliest expiry (FEFO). You cannot exceed available quantities.</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="dispatch_now" >
                                        <label class="form-check-label" for="dispatch_now">Dispatch immediately</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-6 text-right">
                                    <strong>Total Planned Qty: <span id="transfer_total_planned">0</span></strong>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-outline-info" onclick="fillAllMax()"><i class="fas fa-fill"></i> Fill all max</button>
                        <button type="button" class="btn btn-primary" onclick="submitTransfer()"><i class="fas fa-paper-plane"></i> Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function decodeBatches(encoded){
                try { return JSON.parse(decodeURIComponent(encoded)); } catch(e){ return []; }
            }

            function openTransferModal(itemId, itemName, batches){
                $('#transfer_item_id').val(itemId);
                $('#transferModalLabel').text('Transfer: ' + itemName);
                // sort by expiry (nulls last)
                batches.sort(function(a,b){
                    if(!a.expiry_date && !b.expiry_date) return 0;
                    if(!a.expiry_date) return 1;
                    if(!b.expiry_date) return -1;
                    return (a.expiry_date < b.expiry_date) ? -1 : (a.expiry_date > b.expiry_date ? 1 : 0);
                });
                const tbody = $('#transfer_batches_table tbody');
                tbody.empty();
                let idx = 1;
                batches.forEach(function(b){
                    const available = Number(b.quantity || 0);
                    if(available <= 0) return;
                    const row = `
                        <tr>
                            <td>${idx++}</td>
                            <td><input type="hidden" class="t-batch-id" value="${b.id}"/>${b.batch_number || ('#'+b.id)}</td>
                            <td>${b.expiry_date ? b.expiry_date : '-'}</td>
                            <td>${available}</td>
                            <td>${(b.cost_price ?? 0).toFixed ? Number(b.cost_price||0).toFixed(2) : b.cost_price}</td>
                            <td style="max-width:150px"><input type="number" class="form-control form-control-sm t-qty" min="0" max="${available}" step="0.001" value="0"></td>
                        </tr>`;
                    tbody.append(row);
                });
                $('#transfer_total_planned').text('0');
                $('#transfer_batches_table').on('input', '.t-qty', function(){
                    const max = Number($(this).attr('max'));
                    let v = Number($(this).val()||0);
                    if(v > max){ $(this).val(max); v = max; }
                    let total = 0; $('.t-qty').each(function(){ total += Number($(this).val()||0); });
                    $('#transfer_total_planned').text(total);
                });
                $('#transferModal').modal('show');
            }

            function fillAllMax(){
                let total = 0;
                $('#transfer_batches_table tbody .t-qty').each(function(){
                    const max = Number($(this).attr('max')) || 0;
                    $(this).val(max);
                    total += max;
                });
                $('#transfer_total_planned').text(total);
            }

            function submitTransfer(){
                const itemId = $('#transfer_item_id').val();
                const destId = $('#destination_subshop_id').val();
                if(!destId){ Swal.fire('Please select a destination subshop'); return; }
                const rows = [];
                $('#transfer_batches_table tbody tr').each(function(){
                    const batchId = $(this).find('.t-batch-id').val();
                    const qty = Number($(this).find('.t-qty').val()||0);
                    if(qty > 0){ rows.push({ batch_id: batchId, qty: qty }); }
                });
                if(rows.length === 0){ Swal.fire('Enter at least one batch quantity'); return; }

                const payload = {
                    destination_subshop_id: destId,
                    dispatch_now: $('#dispatch_now').is(':checked'),
                    items: [ { item_id: itemId, batches: rows } ]
                };

                $.ajax({
                    url: '{{ route('transfers.store') }}',
                    method: 'POST',
                    data: payload,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function(res){
                        Swal.fire({ icon: 'success', title: 'Transfer created', text: 'Status: '+res.status });
                        $('#transferModal').modal('hide');
                        setTimeout(function(){ location.reload(); }, 800);
                    },
                    error: function(xhr){
                        let msg = 'Failed to create transfer';
                        try { const j = xhr.responseJSON; if(j && j.message) msg = j.message; } catch(e){}
                        Swal.fire({ icon: 'error', title: 'Error', text: msg });
                    }
                });
            }
        </script>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #004e92, #000428); color: white;">
                <h5 class="modal-title text-light" id="addItemModalLabel"><i class="fas fa-plus"></i> Add New Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addItemForm" action="{{ route('items.store') }}" method="POST">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sku">SKU</label>
                                <input type="text" class="form-control" id="sku" name="sku" readonly placeholder="Auto-generated (SC-00001)">
                                <small class="text-muted">SKU will be auto-generated</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select class="form-control" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    @foreach(\App\Models\Category::where('subshop_id', $subshop->id)->get() as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="supplier_id">Supplier</label>
                                <select class="form-control" id="supplier_id" name="supplier_id">
                                    <option value="">Select Supplier</option>
                                    @foreach(\App\Models\Suppliers::where('subshop_id', $subshop->id)->get() as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="price">Selling Price <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cost_price">Cost Price</label>
                                <input type="number" class="form-control" id="cost_price" name="cost_price" step="0.01" min="0">
                                <small id="margin_display" class="text-success" style="display: none;"></small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="quantity">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="min_quantity">Min Quantity</label>
                                <input type="number" class="form-control" id="min_quantity" name="min_quantity" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="unit">Unit</label>
                                <select class="form-control" id="unit" name="unit">
                                    <option value="piece">Piece</option>
                                    <option value="kg">Kilogram</option>
                                    <option value="liter">Liter</option>
                                    <option value="meter">Meter</option>
                                    <option value="box">Box</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="barcode">Barcode</label>
                                <input type="text" class="form-control" id="barcode" name="barcode" readonly placeholder="Auto-generated">
                                <small class="text-muted">Barcode will be auto-generated</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="batch">Batch Number</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="batch" name="batch" value="{{ \App\Models\Item::generateBatchNumber() }}" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="generateBatchBtn" title="Generate New Batch Number">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">Auto-generated batch number</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                                <small class="text-muted">Optional - leave empty if item doesn't expire</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Active Item</label>
                            <small class="text-muted">Uncheck to make this item inactive</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitItemForm()">
                        <i class="fas fa-save"></i> Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" role="dialog" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #004e92, #000428); color: white;">
                <h5 class="modal-title text-light" id="editItemModalLabel"><i class="fas fa-edit"></i> Edit Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editItemForm" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_name">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_sku">SKU</label>
                                <input type="text" class="form-control" id="edit_sku" name="sku" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_category_id">Category</label>
                                <select class="form-control" id="edit_category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    @foreach(\App\Models\Category::where('subshop_id', $subshop->id)->get() as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_supplier_id">Supplier</label>
                                <select class="form-control" id="edit_supplier_id" name="supplier_id">
                                    <option value="">Select Supplier</option>
                                    @foreach(\App\Models\Suppliers::where('subshop_id', $subshop->id)->get() as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_price">Selling Price <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_cost_price">Cost Price</label>
                                <input type="number" class="form-control" id="edit_cost_price" name="cost_price" step="0.01" min="0">
                                <small id="edit_margin_display" class="text-success" style="display: none;"></small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_quantity">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_min_quantity">Min Quantity</label>
                                <input type="number" class="form-control" id="edit_min_quantity" name="min_quantity" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_max_quantity">Max Quantity</label>
                                <input type="number" class="form-control" id="edit_max_quantity" name="max_quantity" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_unit">Unit</label>
                                <select class="form-control" id="edit_unit" name="unit">
                                    <option value="piece">Piece</option>
                                    <option value="kg">Kilogram</option>
                                    <option value="liter">Liter</option>
                                    <option value="meter">Meter</option>
                                    <option value="box">Box</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_barcode">Barcode</label>
                                <input type="text" class="form-control" id="edit_barcode" name="barcode" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_expiry_date">Expiry Date</label>
                                <input type="date" class="form-control" id="edit_expiry_date" name="expiry_date">
                                <small class="text-muted">Optional - leave empty if item doesn't expire</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1">
                            <label class="form-check-label" for="edit_is_active">Active Item</label>
                            <small class="text-muted">Uncheck to make this item inactive</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateItem()">
                        <i class="fas fa-save"></i> Update Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- View Item Modal -->
<div class="modal fade" id="viewItemModal" tabindex="-1" role="dialog" aria-labelledby="viewItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8, #138496); color: white;">
                <h5 class="modal-title" id="viewItemModalLabel"><i class="fas fa-eye"></i> Item Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Item Name and SKU -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h4 id="view_item_name" class="text-primary mb-1"></h4>
                        <small class="text-muted" id="view_item_description"></small>
                    </div>
                    <div class="col-md-4 text-right">
                        <div class="mb-2">
                            <strong>SKU:</strong> <code id="view_item_sku">-</code>
                        </div>
                        <div>
                            <strong>Barcode:</strong> <code id="view_item_barcode">-</code>
                        </div>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong class="text-muted">Shop:</strong><br>
                                    <span id="view_item_shop" class="badge badge-info"></span>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted">Category:</strong><br>
                                    <span id="view_item_category">-</span>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted">Supplier:</strong><br>
                                    <span id="view_item_supplier">-</span>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted">Unit:</strong><br>
                                    <span id="view_item_unit">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong class="text-muted">Status:</strong><br>
                                    <span id="view_item_status" class="badge"></span>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted">Expiry Date:</strong><br>
                                    <span id="view_item_expiry">-</span>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted">Min Quantity:</strong><br>
                                    <span id="view_item_min_quantity">-</span>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted">Max Quantity:</strong><br>
                                    <span id="view_item_max_quantity">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing Information -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-dollar-sign"></i> Pricing Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="text-success mb-1" id="view_item_price">-</h5>
                                    <small class="text-muted">Selling Price</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="text-warning mb-1" id="view_item_cost_price">-</h5>
                                    <small class="text-muted">Cost Price</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="text-info mb-1" id="view_item_margin">-</h5>
                                    <small class="text-muted">Margin %</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Information -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-boxes"></i> Inventory Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="text-primary mb-1" id="view_item_quantity">-</h5>
                                    <small class="text-muted">Current Stock</small>
                                </div>
                            </div>
                            <!-- <div class="col-md-3">
                                <div class="text-center">
                                    <h5 class="text-secondary mb-1" id="view_item_opening_balance">-</h5>
                                    <small class="text-muted">Opening Balance</small>
                                </div>
                            </div> -->
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="text-success mb-1" id="view_item_sold">-</h5>
                                    <small class="text-muted">Sold</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="text-warning mb-1" id="view_item_available">-</h5>
                                    <small class="text-muted">Available</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Batches -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-layer-group"></i> Batches</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Batch No.</th>
                                        <th class="text-right">Qty</th>
                                        <th class="text-right">Cost</th>
                                        <th class="text-right">Selling</th>
                                        <th>Expiry</th>
                                        <th>Received</th>
                                    </tr>
                                </thead>
                                <tbody id="view_batches_table_body">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No batches found</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Transaction Summary -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-chart-line"></i> Transaction Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="text-info mb-1" id="view_item_transactions">-</h5>
                                    <small class="text-muted">Total Transactions</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="text-primary mb-1" id="view_item_quantity_transacted">-</h5>
                                    <small class="text-muted">Quantity Transacted</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="text-danger mb-1" id="view_item_write_off">-</h5>
                                    <small class="text-muted">Write Off</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Summary Card Modals -->
<!-- Total Items Modal -->
<div class="modal fade" id="totalItemsModal" tabindex="-1" role="dialog" aria-labelledby="totalItemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="totalItemsModalLabel"><i class="fas fa-box"></i> Total Items Summary</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This shows a summary of all items in your inventory.
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Item Name</th>
                                <th>Price (TZS)</th>
                                <th>Quantity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="totalItemsTableBody">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <h6>Summary:</h6>
                    <ul>
                        <li>Total Items: <strong>{{ $stats['total_items'] }}</strong></li>
                        <li>Active Items: <strong>{{ $stats['active_items'] }}</strong></li>
                        <li>Total Categories: <strong>{{ $stats['total_categories'] }}</strong></li>
                        <li>Total Suppliers: <strong>{{ $stats['total_suppliers'] }}</strong></li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            
            </div>
        </div>
    </div>
</div>

<!-- Total Value Modal -->
<div class="modal fade" id="totalValueModal" tabindex="-1" role="dialog" aria-labelledby="totalValueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="totalValueModalLabel"><i class="fas fa-dollar-sign"></i> Total Inventory Value</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This shows the total value of your inventory.
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Total Inventory Value</h5>
                                <h2 class="text-success">TZS {{ number_format($stats['total_value'], 2) }}</h2>
                                <p class="text-muted">Based on current stock levels and prices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Value by Category</h5>
                                <div id="valueByCategoryChart" style="height: 200px;">
                                    <!-- Chart will be rendered here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <h6>Top Valuable Items</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total Value</th>
                                </tr>
                            </thead>
                            <tbody id="topValuableItems">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                
            </div>
        </div>
    </div>
</div>

<!-- In Stock Items Modal -->
<div class="modal fade" id="inStockModal" tabindex="-1" role="dialog" aria-labelledby="inStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="inStockModalLabel"><i class="fas fa-check-circle"></i> In Stock Items</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Showing all items currently in stock.
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Min Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="inStockTableBody">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Stock Status</h6>
                         @php
                            $total = $stats['total_items'] ?? 0;
                            $inStock = $stats['items_in_stock'] ?? 0;
                            $percentage = $total > 0 ? ($inStock / $total) * 100 : 0;
                        @endphp

                        <div class="progress-bar bg-success" 
                            role="progressbar" 
                            style="width: {{ $percentage }}%" 
                            aria-valuenow="{{ $inStock }}" 
                            aria-valuemin="0" 
                            aria-valuemax="{{ $total }}">
                            {{ round($percentage, 1) }}%
                        </div>

                            <small class="text-muted">{{ $stats['items_in_stock'] }} out of {{ $stats['total_items'] }} items in stock</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
               
            </div>
        </div>
    </div>
</div>

<!-- Out of Stock Items Modal -->
<div class="modal fade" id="outOfStockModal" tabindex="-1" role="dialog" aria-labelledby="outOfStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="outOfStockModalLabel"><i class="fas fa-exclamation-triangle"></i> Out of Stock Items</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> The following items are currently out of stock and may need to be reordered.
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Last Stock Date</th>
                                <th>Supplier</th>
                            </tr>
                        </thead>
                        <tbody id="outOfStockTableBody">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-light">
                                <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Consider creating purchase orders for these items.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-light">
                                <i class="fas fa-chart-line"></i> <strong>Did you know?</strong> You can set up automatic reorder points in the item settings.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
           
               
            </div>
        </div>
    </div>
</div>

<!-- Write Off Item Modal -->
<div class="modal fade" id="writeOffModal" tabindex="-1" role="dialog" aria-labelledby="writeOffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white;">
                <h5 class="modal-title" id="writeOffModalLabel"><i class="fas fa-minus-circle"></i> Write Off Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="writeOffForm" action="{{ route('writeoffs.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <!-- Item Information Display -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-info-circle"></i> Item Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Item Name:</strong><br>
                                    <span id="write_off_item_name" class="text-primary"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Current Stock:</strong><br>
                                    <span id="write_off_current_quantity" class="badge badge-primary"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Selling Price:</strong><br>
                                    <span id="write_off_selling_price" class="text-success"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Write Off Form -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="write_off_quantity">Write Off Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="write_off_quantity" name="quantity" min="1" required>
                                <small class="text-muted">Maximum: <span id="max_quantity_text"></span></small>
                                <input type="hidden" id="write_off_product_id" name="product_id">
                                <input type="hidden" id="write_off_subshop_id" name="subshop_id" value="{{ $subshop->id }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="write_off_batch_id">Batch <span class="text-danger">*</span></label>
                                <select class="form-control" id="write_off_batch_id" name="batch_id" required>
                                    <option value="">Select Batch</option>
                                </select>
                                <small class="text-muted">Choose the batch to write off from.</small>
                            </div>
                            <div class="form-group">
                                <label for="write_off_reason">Reason <span class="text-danger">*</span></label>
                                <select class="form-control" id="write_off_reason" name="reason" required>
                                    <option value="">Select Reason</option>
                                    <option value="damage">Damage</option>
                                    <option value="expiry">Expiry</option>
                                    <option value="theft">Theft</option>
                                    <option value="obsolescence">Obsolescence</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="write_off_date">Write Off Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="write_off_date" name="writeoff_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="write_off_description">Description</label>
                                <input type="text" class="form-control" id="write_off_description" name="notes" placeholder="Optional description">
                            </div>
                        </div>
                    </div>

                    <!-- Calculation Display -->
                    <div class="card mt-4">
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

<!-- Low Stock Items Modal -->
<div class="modal fade" id="lowStockModal" tabindex="-1" role="dialog" aria-labelledby="lowStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="lowStockModalLabel"><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Active Items Modal -->
<div class="modal fade" id="activeItemsModal" tabindex="-1" role="dialog" aria-labelledby="activeItemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="activeItemsModalLabel"><i class="fas fa-toggle-on"></i> Active Items</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Categories Modal -->
<div class="modal fade" id="categoriesModal" tabindex="-1" role="dialog" aria-labelledby="categoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light text-dark">
                <h5 class="modal-title" id="categoriesModalLabel"><i class="fas fa-tags"></i> Categories</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Suppliers Modal -->
<div class="modal fade" id="suppliersModal" tabindex="-1" role="dialog" aria-labelledby="suppliersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="suppliersModalLabel"><i class="fas fa-truck"></i> Suppliers</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Import Items Modal -->
<div class="modal fade" id="importItemsModal" tabindex="-1" role="dialog" aria-labelledby="importItemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                <h5 class="modal-title" id="importItemsModalLabel"><i class="fas fa-file-import"></i> Bulk Import Items</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="importItemsForm" action="{{ route('items.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle"></i> Import Instructions</h5>
                        <ol class="mb-0">
                            <li>Download the sample CSV file to see the required format</li>
                            <li>Fill in your items data following the same format</li>
                            <li>The field with star (*) are required, Example:  Name* , Price* and Quantity*</li>
                            <li>SKU and Barcode will be auto-generated if not provided</li>
                            <li>Select category *</li>
                            <li>Upload your CSV file using the form below</li>
                            <li>Click 'Import Items' to process the file</li>
                        </ol>
                    </div>
                    
                    <div class="text-center mb-4">
                        <a href="{{ route('items.import.sample') }}" class="btn btn-outline-primary">
                            <i class="fas fa-file-download"></i> Download Sample CSV
                        </a>
                    </div>

                    <div class="form-group">
                        <label for="category_id">Select Category <span class="text-danger">*</span></label>
                        <select class="form-control" id="category_name" name="category_name" required>
                            <option value="" selected disabled>Choose Category</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="import_file">Select CSV File <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="import_file" name="import_file" accept=".csv" required>
                            <label class="custom-file-label" for="import_file">Choose file</label>
                        </div>
                        <small class="form-text text-muted">Only CSV files are accepted. Max file size: 5MB</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="has_headers" name="has_headers" checked>
                        <label class="form-check-label" for="has_headers">
                            First row contains headers
                        </label>
                    </div>
                    
                    <div class="progress mb-3 d-none" id="importProgressContainer">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="importProgress" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    
                    <div id="importStatus" class="d-none">
                        <div class="alert alert-success" id="importSuccess" style="display: none;"></div>
                        <div class="alert alert-danger" id="importError" style="display: none;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="importSubmitBtn">
                        <i class="fas fa-upload"></i> Import Items
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
   @push('css')
        <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    @endpush
<script>
// Utility function to escape HTML
function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Function to load data for modals
function loadModalData(modalType) {
    // Show loading state
    const modalId = `${modalType}Modal`;
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.error(`Modal with ID ${modalId} not found`);
        return;
    }
    
    const modalBody = modal.querySelector('.modal-body');
    if (!modalBody) {
        console.error('Modal body not found');
        return;
    }
    
    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Loading data...</p></div>';

    // Fetch data from the API - subshop_id is now handled by the session
    fetch('/api/items/summary', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
        .then(response => {
            // console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // console.log('Summary data received:', data);
            populateModal(modalId, modalType, data);
        })
        .catch(error => {
            console.error('Error loading summary data:', error);
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load data. Please try again.
                    <div class="small text-muted mt-2">${error.message}</div>
                    <div class="small text-muted mt-1">Check browser console for more details.</div>
                </div>
            `;
        });
}

// Function to populate modal with data
function populateModal(modalId, modalType, data) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.error(`Modal with ID ${modalId} not found`);
        return;
    }

    const modalBody = modal.querySelector('.modal-body');
    if (!modalBody) {
        console.error('Modal body not found');
        return;
    }
    
    try {
        switch(modalType) {
            case 'totalItems':
                populateTotalItemsModal(modalBody, data);
                break;
            case 'totalValue':
                populateTotalValueModal(modalBody, data);
                break;
            case 'inStock':
                populateInStockModal(modalBody, data);
                break;
            case 'outOfStock':
                populateOutOfStockModal(modalBody, data);
                break;
            case 'lowStock':
                populateLowStockModal(modalBody, data);
                break;
            case 'activeItems':
                populateActiveItemsModal(modalBody, data);
                break;
            case 'categories':
                populateCategoriesModal(modalBody, data);
                break;
            case 'suppliers':
                populateSuppliersModal(modalBody, data);
                break;
            default:
                throw new Error(`Unknown modal type: ${modalType}`);
        }
    } catch (error) {
        console.error(`Error populating ${modalType} modal:`, error);
        modalBody.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Error displaying data.
                <div class="small text-muted mt-2">${error.message}</div>
            </div>
        `;
    }
}

// Function to populate Total Items modal
function populateTotalItemsModal(modalBody, data) {
    if (!data || !data.recentItems) {
        throw new Error('Invalid data received for total items');
    }

    const tableRows = data.recentItems.map(item => `
        <tr>
            <td>${escapeHtml(item.name || 'N/A')}</td>
            <td class="text-right">${escapeHtml(item.formatted_price || 'TZS 0.00')}</td>
            <td class="text-center">${escapeHtml(item.quantity || 0)}</td>
            <td class="text-center"><span class="badge badge-${escapeHtml(item.status_class || 'secondary')}">${escapeHtml(item.status || 'N/A')}</span></td>
        </tr>
    `).join('');

    modalBody.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> This shows a summary of all items in your inventory.
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Item Name</th>
                        <th class="text-right">Price (TZS)</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows || '<tr><td colspan="4" class="text-center">No items found</td></tr>'}
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <h6>Summary:</h6>
            <ul>
                <li>Total Items: <strong>${escapeHtml(data.totalItems || 0)}</strong></li>
                <li>In Stock: <strong>${escapeHtml(data.inStockCount || 0)}</strong></li>
                <li>Out of Stock: <strong>${escapeHtml(data.outOfStockCount || 0)}</strong></li>
                <li>Total Categories: <strong>${escapeHtml((data.valueByCategory && data.valueByCategory.length) || 0)}</strong></li>
            </ul>
        </div>
    `;
}

// Function to populate Total Value modal
function populateTotalValueModal(modalBody, data) {
    if (!data) {
        throw new Error('No data received for total value');
    }

    const valueByCategory = Array.isArray(data.valueByCategory) ? data.valueByCategory : [];
    const topValuableItems = data.topValuableItems || [];
    const totalInventoryValue = data.totalInventoryValue || '0.00';

    const categoryRows = valueByCategory.map(category => `
        <tr>
            <td>${escapeHtml(category.name || 'Uncategorized')}</td>
            <td class="text-right">TZS ${escapeHtml(category.formatted_value || '0.00')}</td>
            <td>${escapeHtml(category.percentage || '0')}%</td>
            <td>${escapeHtml(category.item_count || 0)} items</td>
        </tr>
    `).join('');

    const topItemsRows = topValuableItems.map((item, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(item.name || 'N/A')}</td>
            <td class="text-right">${escapeHtml(item.quantity || 0)} ${escapeHtml(item.unit || '')}</td>
            <td class="text-right">TZS ${escapeHtml(item.price || '0.00')}</td>
            <td class="text-right">TZS ${escapeHtml(item.formatted_value || '0.00')}</td>
        </tr>
    `).join('');

    modalBody.innerHTML = `
        <div class="alert alert-success">
            <i class="fas fa-info-circle"></i> Total Inventory Value: 
            <strong>TZS ${escapeHtml(totalInventoryValue)}</strong>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Value by Category</h6>
                    </div>
                    <div class="card-body">
                        ${valueByCategory.length > 0 ? `
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th class="text-right">Value</th>
                                        <th>%</th>
                                        <th>Items</th>
                                    </tr>
                                </thead>
                                <tbody>${categoryRows}</tbody>
                            </table>
                        </div>
                        ` : '<div class="alert alert-warning">No category data available</div>'}
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-star"></i> Top Valuable Items</h6>
                    </div>
                    <div class="card-body">
                        ${topValuableItems.length > 0 ? `
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Item</th>
                                        <th class="text-right">Qty</th>
                                        <th class="text-right">Price</th>
                                        <th class="text-right">Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${topItemsRows || '<tr><td colspan="5" class="text-center">No items available</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                        ` : '<div class="alert alert-warning">No valuable items available</div>'}
                    </div>
                </div>
            </div>
        </div>
        <div class="alert alert-success">
            <i class="fas fa-coins"></i> <strong>Total Inventory Value:</strong> TZS ${data.totalInventoryValue}
        </div>
    `;
}

// Function to populate In Stock modal
function populateInStockModal(modalBody, data) {
    if (!data) {
        throw new Error('Invalid data received for in-stock items');
    }

    const inStockItems = Array.isArray(data.inStockItems)
        ? data.inStockItems
        : (data.inStockItems ? Object.values(data.inStockItems) : []);

    const tableRows = inStockItems.map(item => `
        <tr>
            <td>${escapeHtml(item.name || 'N/A')}</td>
            <td>${escapeHtml((item.category && item.category.name) || item.category || 'N/A')}</td>
            <td class="text-right">${escapeHtml(item.quantity || 0)}</td>
            <td class="text-right">${escapeHtml(item.min_quantity || 'N/A')}</td>
            <td class="text-center">
                <span class="badge ${(item.quantity || 0) > (item.min_quantity || 0) ? 'badge-success' : 'badge-warning'}">
                    ${(item.quantity || 0) > (item.min_quantity || 0) ? 'Adequate' : 'Low Stock'}
                </span>
            </td>
        </tr>
    `).join('');

    const inStockCount = data.inStockCount || inStockItems.length || 0;
    const totalItems = data.totalItems || 1; // Avoid division by zero
    const inStockPercentage = Math.round((inStockCount / totalItems) * 100);

    modalBody.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            Showing ${inStockCount} items currently in stock (${inStockPercentage}% of total inventory).
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Min Qty</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows || '<tr><td colspan="5" class="text-center">No items in stock</td></tr>'}
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <div class="progress" style="height: 25px;">
                <div class="progress-bar bg-success" role="progressbar" 
                     style="width: ${inStockPercentage}%" 
                     aria-valuenow="${inStockPercentage}" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                    ${inStockPercentage}%
                </div>
            </div>
            <small class="text-muted">${inStockCount} out of ${totalItems} items in stock</small>
        </div>
    
    `;
}

// Function to populate Low Stock modal
function populateLowStockModal(modalBody, data) {
    if (!data) {
        throw new Error('Invalid data received for low stock items');
    }

    const lowStockItems = Array.isArray(data.lowStockItems)
        ? data.lowStockItems
        : (data.lowStockItems ? Object.values(data.lowStockItems) : []);

    const tableRows = lowStockItems.map(item => `
        <tr>
            <td>${escapeHtml(item.name || 'N/A')}</td>
            <td>${escapeHtml((item.category && item.category.name) || item.category || 'N/A')}</td>
            <td class="text-right">${escapeHtml(item.quantity || 0)}</td>
            <td class="text-right">${escapeHtml(item.min_quantity || 'N/A')}</td>
            <td class="text-right">${escapeHtml(item.max_quantity || 'N/A')}</td>
            <td class="text-center">
                <span class="badge badge-warning">Low Stock</span>
            </td>
        </tr>
    `).join('');

    const lowStockCount = data.lowStockCount || lowStockItems.length || 0;
    const totalItems = data.totalItems || 1;
    const lowStockPercentage = Math.round((lowStockCount / totalItems) * 100);

    modalBody.innerHTML = `
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            Showing ${lowStockCount} items with low stock (${lowStockPercentage}% of total inventory).
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th class="text-right">Current Stock</th>
                        <th class="text-right">Min Quantity</th>
                        <th class="text-right">Max Quantity</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows || '<tr><td colspan="6" class="text-center">No low stock items found</td></tr>'}
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <h6>Summary:</h6>
            <ul>
                <li>Low Stock Items: <strong>${escapeHtml(lowStockCount)}</strong></li>
                <li>Percentage of Total: <strong>${escapeHtml(lowStockPercentage)}%</strong></li>
                <li>Consider restocking these items to avoid stockouts</li>
            </ul>
        </div>
    `;
}

// Function to populate Active Items modal
function populateActiveItemsModal(modalBody, data) {
    if (!data || !data.activeItems) {
        throw new Error('Invalid data received for active items');
    }

    const tableRows = data.activeItems.map(item => `
        <tr>
            <td>${escapeHtml(item.name || 'N/A')}</td>
            <td>${escapeHtml(item.category || 'N/A')}</td>
            <td class="text-right">${escapeHtml(item.quantity || 0)}</td>
            <td class="text-right">${escapeHtml(item.formatted_price || 'TZS 0.00')}</td>
            <td class="text-center">
                <span class="badge badge-success">Active</span>
            </td>
        </tr>
    `).join('');

    const activeCount = data.activeCount || 0;
    const totalItems = data.totalItems || 1;
    const activePercentage = Math.round((activeCount / totalItems) * 100);

    modalBody.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            Showing ${activeCount} active items (${activePercentage}% of total inventory).
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th class="text-right">Stock</th>
                        <th class="text-right">Price</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows || '<tr><td colspan="5" class="text-center">No active items found</td></tr>'}
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <h6>Summary:</h6>
            <ul>
                <li>Active Items: <strong>${escapeHtml(activeCount)}</strong></li>
                <li>Percentage of Total: <strong>${escapeHtml(activePercentage)}%</strong></li>
                <li>These items are currently available for sale</li>
            </ul>
        </div>
    `;
}

// Function to populate Categories modal
function populateCategoriesModal(modalBody, data) {
    if (!data || !data.categories) {
        throw new Error('Invalid data received for categories');
    }

    const tableRows = data.categories.map(category => `
        <tr>
            <td>${escapeHtml(category.name || 'N/A')}</td>
            <td>${escapeHtml(category.description || 'No description')}</td>
            <td class="text-center">${escapeHtml(category.item_count || 0)}</td>
            <td class="text-right">${escapeHtml(category.formatted_value || 'TZS 0.00')}</td>
            <td class="text-center">${escapeHtml(category.percentage || 0)}%</td>
        </tr>
    `).join('');

    const totalCategories = data.totalCategories || 0;
    const totalItems = data.totalItems || 0;

    modalBody.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            Showing ${totalCategories} categories with ${totalItems} total items.
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th class="text-center">Items</th>
                        <th class="text-right">Total Value</th>
                        <th class="text-center">% of Inventory</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows || '<tr><td colspan="5" class="text-center">No categories found</td></tr>'}
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <h6>Summary:</h6>
            <ul>
                <li>Total Categories: <strong>${escapeHtml(totalCategories)}</strong></li>
                <li>Total Items: <strong>${escapeHtml(totalItems)}</strong></li>
                <li>Average Items per Category: <strong>${escapeHtml(totalCategories > 0 ? Math.round(totalItems / totalCategories) : 0)}</strong></li>
            </ul>
        </div>
    `;
}

// Function to populate Suppliers modal
function populateSuppliersModal(modalBody, data) {
    if (!data || !data.suppliers) {
        throw new Error('Invalid data received for suppliers');
    }

    const tableRows = data.suppliers.map(supplier => `
        <tr>
            <td>${escapeHtml(supplier.name || 'N/A')}</td>
            <td>${escapeHtml(supplier.contact_person || 'N/A')}</td>
            <td>${escapeHtml(supplier.phone || 'N/A')}</td>
            <td>${escapeHtml(supplier.email || 'N/A')}</td>
            <td class="text-center">${escapeHtml(supplier.item_count || 0)}</td>
            <td class="text-right">${escapeHtml(supplier.formatted_value || 'TZS 0.00')}</td>
        </tr>
    `).join('');

    const totalSuppliers = data.totalSuppliers || 0;
    const totalItems = data.totalItems || 0;

    modalBody.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            Showing ${totalSuppliers} suppliers with ${totalItems} total items.
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th class="text-center">Items</th>
                        <th class="text-right">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows || '<tr><td colspan="6" class="text-center">No suppliers found</td></tr>'}
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <h6>Summary:</h6>
            <ul>
                <li>Total Suppliers: <strong>${escapeHtml(totalSuppliers)}</strong></li>
                <li>Total Items: <strong>${escapeHtml(totalItems)}</strong></li>
                <li>Average Items per Supplier: <strong>${escapeHtml(totalSuppliers > 0 ? Math.round(totalItems / totalSuppliers) : 0)}</strong></li>
            </ul>
        </div>
    `;
}

// Function to populate Out of Stock modal
function populateOutOfStockModal(modalBody, data) {
    if (!data) {
        throw new Error('Invalid data received for out-of-stock items');
    }

    const outOfStockItems = Array.isArray(data.outOfStockItems)
        ? data.outOfStockItems
        : (data.outOfStockItems ? Object.values(data.outOfStockItems) : []);

    const tableRows = outOfStockItems.map(item => `
        <tr>
            <td>${escapeHtml(item.name || 'N/A')}</td>
            <td>${escapeHtml((item.category && item.category.name) || item.category || 'N/A')}</td>
            <td>${escapeHtml(item.last_stocked || 'N/A')}</td>
            <td>${escapeHtml((item.supplier && item.supplier.name) || 'N/A')}</td>
        </tr>
    `).join('');

    const outOfStockCount = data.outOfStockCount || outOfStockItems.length || 0;
    const totalItems = data.totalItems || 1; // Avoid division by zero
    const outOfStockPercentage = Math.round((outOfStockCount / totalItems) * 100);

    modalBody.innerHTML = `
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            ${outOfStockCount} item${outOfStockCount !== 1 ? 's are' : ' is'} currently out of stock.
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Last Stock Date</th>
                        <th>Supplier</th>
                        
                    </tr>
                </thead>
                <tbody>
                    ${tableRows || '<tr><td colspan="5" class="text-center">No out of stock items</td></tr>'}
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <div class="progress" style="height: 25px;">
                <div class="progress-bar bg-danger" role="progressbar" 
                     style="width: ${outOfStockPercentage}%" 
                     aria-valuenow="${outOfStockPercentage}" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                    ${outOfStockPercentage}%
                </div>
            </div>
            <small class="text-muted">${outOfStockCount} out of ${totalItems} items out of stock</small>
        </div>
        ${outOfStockCount > 0 ? `
        <div class="mt-3">
            <a href="#" class="btn btn-warning" >
                <i class="fas fa-clipboard-list"></i> Purchase 
            </a>
       
        </div>
        ` : ''}
    `;
}

// Function to render category value chart
function renderCategoryValueChart(categories) {
    // This is a placeholder - you'll need to implement your chart library of choice
    // For example, using Chart.js or any other library you prefer
    // console.log('Rendering chart with data:', categories);
    // Your chart rendering code here
}






// Helper function to escape HTML to prevent XSS
function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) {
        return '';
    }
    return String(unsafe)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to modals
    // Modal event listeners are now handled directly in the onclick events

    // Generate batch number button
    const generateBtn = document.getElementById('generateBatchBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', function(e) {
            e.preventDefault();
            generateNewBatchNumber();
        });
    }
    
    // File input label update
    const fileInput = document.getElementById('import_file');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Choose file';
            const nextSibling = e.target.nextElementSibling;
            nextSibling.innerText = fileName;
            
            // Reset status messages
            document.getElementById('importStatus').classList.add('d-none');
            document.getElementById('importSuccess').style.display = 'none';
            document.getElementById('importError').style.display = 'none';
        });
    }
    
    // Import form submission
    const importForm = document.getElementById('importItemsForm');
    if (importForm) {
        importForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('importSubmitBtn');
            const progressContainer = document.getElementById('importProgressContainer');
            const progressBar = document.getElementById('importProgress');
            const importStatus = document.getElementById('importStatus');
            const importSuccess = document.getElementById('importSuccess');
            const importError = document.getElementById('importError');
            
            // Get the checkbox value and ensure it's a proper boolean
            const hasHeadersCheckbox = document.getElementById('has_headers');
            formData.set('has_headers', hasHeadersCheckbox.checked ? '1' : '0');
            
            // Reset UI
            submitBtn.disabled = true;
            progressContainer.classList.remove('d-none');
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            progressBar.classList.remove('bg-danger', 'bg-success');
            importStatus.classList.add('d-none');
            importSuccess.style.display = 'none';
            importError.style.display = 'none';
            
            // Submit form with AJAX
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 90); // Max 90% until complete
                    progressBar.style.width = percentComplete + '%';
                    progressBar.textContent = percentComplete + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success === true) {
                        // Success
                        progressBar.classList.add('bg-success');
                        importSuccess.innerHTML = `
                            <strong>Import Successful!</strong>
                            <ul class="mb-0">
                                <li>Total Rows: ${response.total_rows || 0}</li>
                                <li>Imported: ${response.imported || 0}</li>
                                ${response.skipped ? `<li>Skipped: ${response.skipped}</li>` : ''}
                                ${response.errors && response.errors.length ? 
                                    `<li>Errors: ${response.errors.length} <button class="btn btn-sm btn-link p-0" onclick="document.getElementById('importErrorDetails').classList.toggle('d-none')">(View Details)</button>
                                    <div id="importErrorDetails" class="d-none mt-2">
                                        <ul class="small">
                                            ${response.errors.map(error => `<li>${error}</li>`).join('')}
                                        </ul>
                                    </div>
                                    </li>` : ''
                                }
                            </ul>
                        `;
                        importSuccess.style.display = 'block';
                        
                        // Reload the page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        // Error or Failed Import
                        progressBar.classList.add('bg-danger');
                        
                        let errorHtml = `<strong>Import Failed:</strong> ${response.message || 'An unknown error occurred during import.'}`;
                        
                        // Show detailed error information if available
                        if (response.errors && response.errors.length > 0) {
                            errorHtml += `
                                <div class="mt-2">
                                    <strong>Error Details:</strong>
                                    <ul class="small mb-0">
                                        ${response.errors.map(error => `<li>${error}</li>`).join('')}
                                    </ul>
                                </div>
                            `;
                        }
                        
                        // Show stats if available
                        if (response.total_rows !== undefined) {
                            errorHtml += `
                                <div class="mt-2">
                                    <small>Total Rows: ${response.total_rows}, Imported: ${response.imported || 0}, Skipped: ${response.skipped || 0}</small>
                                </div>
                            `;
                        }
                        
                        importError.innerHTML = errorHtml;
                        importError.style.display = 'block';
                        submitBtn.disabled = false;
                    }
                } catch (e) {
                    console.error('Parse error:', e);
                    console.error('Response:', xhr.responseText);
                    progressBar.classList.add('bg-danger');
                    importError.innerHTML = '<strong>Error:</strong> Failed to process server response. Check console for details.';
                    importError.style.display = 'block';
                    submitBtn.disabled = false;
                }
                
                importStatus.classList.remove('d-none');
            });
            
            xhr.addEventListener('error', function() {
                progressBar.classList.add('bg-danger');
                progressBar.style.width = '100%';
                progressBar.textContent = 'Error';
                importError.innerHTML = '<strong>Error:</strong> Failed to upload file. Please try again.';
                importStatus.classList.remove('d-none');
                importError.style.display = 'block';
                submitBtn.disabled = false;
            });
            
            // Get CSRF token from meta tag or form
            const csrfMetaTag = document.querySelector('meta[name="csrf-token"]');
            const csrfInput = document.querySelector('input[name="_token"]');
            const csrfToken = (csrfMetaTag && csrfMetaTag.getAttribute('content')) || (csrfInput && csrfInput.value);
            
            xhr.open('POST', this.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            }
            xhr.send(formData);
            
            return false;
        });
    }
});

// Global functions for margin calculation
function calculateMargin() {
    const price = parseFloat($('#price').val()) || 0;
    const costPrice = parseFloat($('#cost_price').val()) || 0;

    if (costPrice > 0 && price > 0) {
        const margin = ((price - costPrice) / costPrice) * 100;
        $('#margin_display').text(margin.toFixed(1) + '%');
        $('#margin_display').show();
    } else {
        $('#margin_display').hide();
    }
}

function calculateEditMargin() {
    const price = parseFloat($('#edit_price').val()) || 0;
    const costPrice = parseFloat($('#edit_cost_price').val()) || 0;

    if (costPrice > 0 && price > 0) {
        const margin = ((price - costPrice) / costPrice) * 100;
        $('#edit_margin_display').text(margin.toFixed(1) + '%');
        $('#edit_margin_display').show();
    } else {
        $('#edit_margin_display').hide();
    }
}

// Format number into TZS currency string
function fmtTZS(val) {
    const n = Number(val) || 0;
    return 'TZS ' + n.toLocaleString();
}

function calculateWriteOffValue() {
    const quantity = parseInt(document.getElementById('write_off_quantity').value) || 0;
    const unitPrice = parseFloat(document.getElementById('write_off_selling_price').textContent.replace('TZS ', '').replace(/,/g, '')) || 0;
    const totalValue = quantity * unitPrice;

    document.getElementById('calc_quantity').textContent = quantity;
    document.getElementById('calc_unit_price').textContent = 'TZS ' + unitPrice.toLocaleString();
    document.getElementById('calc_total_value').textContent = 'TZS ' + totalValue.toLocaleString();
}

@if (session('success'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: "{{ session('success') }}",
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });
    });
@endif

@if (session('error'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Kuna Tatizo!',
            text: "{{ session('error') }}",
            showConfirmButton: true,
            
        });
    });
@endif

// Define all window functions globally
window.editItem = function(id, name, description, categoryId, supplierId, sku, barcode, price, costPrice, quantity, minQuantity, maxQuantity, unit, isActive, expiryDate) {
    // Set form action first
    document.getElementById('editItemForm').action = '{{ url("/admin/inventory/items") }}/' + id;

    // Show modal
    $('#editItemModal').modal('show');

    // Wait for modal to be shown, then populate immediately
    $('#editItemModal').on('shown.bs.modal', function () {
        // Populate all form fields immediately
        document.getElementById('edit_name').value = name || '';
        document.getElementById('edit_description').value = description || '';
        document.getElementById('edit_category_id').value = categoryId || '';
        document.getElementById('edit_supplier_id').value = supplierId || '';
        document.getElementById('edit_sku').value = sku || '';
        document.getElementById('edit_barcode').value = barcode || '';
        document.getElementById('edit_price').value = price || '';
        document.getElementById('edit_cost_price').value = costPrice || '';
        document.getElementById('edit_quantity').value = quantity || '';
        document.getElementById('edit_min_quantity').value = minQuantity || '';
        document.getElementById('edit_max_quantity').value = maxQuantity || '';
        document.getElementById('edit_unit').value = unit || '';
        document.getElementById('edit_expiry_date').value = expiryDate || '';
        document.getElementById('edit_is_active').checked = isActive || false;

        // Calculate margin for existing data
        calculateEditMargin();

        // console.log('Edit form populated with:', {
            // name, description, categoryId, supplierId, sku, barcode,
            // price, costPrice, quantity, minQuantity, maxQuantity, unit, isActive, expiryDate
        // });
    });
};

window.viewItem = function(id, name, description, categoryName, supplierName, sku, barcode, price, costPrice, quantity, minQuantity, maxQuantity, unit, isActive, expiryDate, shopName, totalTransactions, totalQuantityTransacted, marginPercentage, totalWriteOffQuantity, batches) {
    // Set modal title
    document.getElementById('viewItemModalLabel').innerHTML = '<i class="fas fa-eye"></i> ' + name + ' Details';

    // Populate item name and description
    document.getElementById('view_item_name').textContent = name;
    document.getElementById('view_item_description').textContent = description || '';

    // Populate SKU and Barcode
    document.getElementById('view_item_sku').textContent = sku || '-';
    document.getElementById('view_item_barcode').textContent = barcode || '-';

    // Populate basic information
    document.getElementById('view_item_shop').textContent = shopName;
    document.getElementById('view_item_category').textContent = categoryName || '-';
    document.getElementById('view_item_supplier').textContent = supplierName || '-';
    document.getElementById('view_item_unit').textContent = unit || '-';

    // Status badge
    const statusElement = document.getElementById('view_item_status');
    if (isActive) {
        statusElement.className = 'badge badge-success';
        statusElement.textContent = 'Active';
    } else {
        statusElement.className = 'badge badge-secondary';
        statusElement.textContent = 'Inactive';
    }

    // Expiry date
    document.getElementById('view_item_expiry').textContent = expiryDate || 'No expiry';

    // Min/Max quantities
    document.getElementById('view_item_min_quantity').textContent = minQuantity || 'Not set';
    document.getElementById('view_item_max_quantity').textContent = maxQuantity || 'Not set';

    // Pricing information (batch-aware)
    const fmtTZS = (n) => 'TZS ' + (parseFloat(n || 0)).toLocaleString();
    // Note: batchList computed below; temporarily set placeholders, then overwrite after batch parsing
    document.getElementById('view_item_price').textContent = price ? fmtTZS(price) : '-';
    document.getElementById('view_item_cost_price').textContent = costPrice ? fmtTZS(costPrice) : '-';
    document.getElementById('view_item_margin').textContent = marginPercentage ? marginPercentage + '%' : '-';

    // Batch-aware inventory information
    let batchList = [];
    try {
        if (typeof batches === 'string') {
            const decoded = decodeURIComponent(batches);
            batchList = JSON.parse(decoded);
        } else if (Array.isArray(batches)) {
            batchList = batches;
        } else if (batches && typeof batches === 'object') {
            batchList = Object.values(batches);
        }
    } catch (e) {
        console.warn('Failed to parse batches payload for viewItem:', e);
        batchList = [];
    }
    const totalBatchQty = batchList.reduce((sum, b) => sum + (parseInt(b.quantity) || 0), 0);
    const displayQty = totalBatchQty || quantity || 0;

    document.getElementById('view_item_quantity').textContent = displayQty;
    //document.getElementById('view_item_opening_balance').textContent = displayQty; // Opening approximated as current total
    document.getElementById('view_item_sold').textContent = totalQuantityTransacted || '0';
    document.getElementById('view_item_available').textContent = displayQty; // Available equals current total

    // Render batches table
    const tbody = document.getElementById('view_batches_table_body');
    if (tbody) {
        const rows = batchList.map(b => `
            <tr>
                <td>${escapeHtml(b.batch_number || '-')}</td>
                <td class="text-right">${escapeHtml(b.quantity || 0)}</td>
                <td class="text-right">TZS ${(parseFloat(b.cost_price || 0)).toLocaleString()}</td>
                <td class="text-right">TZS ${(parseFloat(b.selling_price || 0)).toLocaleString()}</td>
                <td>${escapeHtml(b.expiry_date || '-')}</td>
                <td>${escapeHtml(b.received_at || '-')}</td>
            </tr>
        `).join('');
        tbody.innerHTML = rows || '<tr><td colspan="6" class="text-center text-muted">No batches found</td></tr>';
    }

    // Compute batch-based price/cost ranges and weighted avg margin
    if (batchList.length > 0) {
        const selling = batchList
            .map(b => parseFloat(b.selling_price))
            .filter(v => !isNaN(v) && v > 0);
        const costs = batchList
            .map(b => parseFloat(b.cost_price))
            .filter(v => !isNaN(v) && v >= 0);
        const totalValue = batchList.reduce((sum, b) => sum + ((parseFloat(b.selling_price) || 0) * (parseInt(b.quantity) || 0)), 0);

        if (selling.length > 0) {
            const minSell = Math.min.apply(null, selling);
            const maxSell = Math.max.apply(null, selling);
            document.getElementById('view_item_price').textContent = minSell === maxSell
                ? fmtTZS(minSell)
                : `${fmtTZS(minSell)} - ${fmtTZS(maxSell)}`;
            const priceRangeEl = document.getElementById('view_batch_price_range');
            if (priceRangeEl) priceRangeEl.textContent = minSell === maxSell ? fmtTZS(minSell) : `${fmtTZS(minSell)} - ${fmtTZS(maxSell)}`;
        }

        if (costs.length > 0) {
            const minCost = Math.min.apply(null, costs);
            const maxCost = Math.max.apply(null, costs);
            document.getElementById('view_item_cost_price').textContent = minCost === maxCost
                ? fmtTZS(minCost)
                : `${fmtTZS(minCost)} - ${fmtTZS(maxCost)}`;
            const costRangeEl = document.getElementById('view_batch_cost_range');
            if (costRangeEl) costRangeEl.textContent = minCost === maxCost ? fmtTZS(minCost) : `${fmtTZS(minCost)} - ${fmtTZS(maxCost)}`;
        }

        // Weighted average margin across batches (by quantity)
        const { totalWeightedMargin, totalQtyForMargin } = batchList.reduce((acc, b) => {
            const cp = parseFloat(b.cost_price);
            const sp = parseFloat(b.selling_price);
            const q = parseInt(b.quantity) || 0;
            if (!isNaN(cp) && cp > 0 && !isNaN(sp) && q > 0) {
                const m = ((sp - cp) / cp) * 100; // percent
                acc.totalWeightedMargin += m * q;
                acc.totalQtyForMargin += q;
            }
            return acc;
        }, { totalWeightedMargin: 0, totalQtyForMargin: 0 });
        if (totalQtyForMargin > 0) {
            const avgMargin = totalWeightedMargin / totalQtyForMargin;
            document.getElementById('view_item_margin').textContent = avgMargin.toFixed(1) + '%';
            const avgMarginEl = document.getElementById('view_batch_avg_margin');
            if (avgMarginEl) avgMarginEl.textContent = avgMargin.toFixed(1) + '%';
        }

        // Nearest expiry from batches
        const expiries = batchList
            .map(b => b.expiry_date)
            .filter(d => !!d)
            .sort();
        if (expiries.length > 0) {
            document.getElementById('view_item_expiry').textContent = expiries[0];
            const nearEl = document.getElementById('view_batch_nearest_expiry');
            if (nearEl) nearEl.textContent = expiries[0];
        }

        // Populate batch summary aggregates
        const countEl = document.getElementById('view_batch_count');
        const qtyEl = document.getElementById('view_batch_total_qty');
        const valEl = document.getElementById('view_batch_total_value');
        if (countEl) countEl.textContent = batchList.length;
        if (qtyEl) qtyEl.textContent = totalBatchQty;
        if (valEl) valEl.textContent = fmtTZS(totalValue);
    }

    // Transaction summary
    document.getElementById('view_item_transactions').textContent = totalTransactions || '0';
    document.getElementById('view_item_quantity_transacted').textContent = totalQuantityTransacted || '0';
    document.getElementById('view_item_write_off').textContent = totalWriteOffQuantity || '0';

    // Show modal
    $('#viewItemModal').modal('show');
};

window.deleteItem = function(id, name) {
    Swal.fire({
        title: 'Are you sure?',
        text: `You want to delete item "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Delete!',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteForm').action = '{{ url("/admin/inventory/items") }}/' + id;
            document.getElementById('deleteForm').submit();
        }
    });
};

window.submitItemForm = function() {
    const form = document.getElementById('addItemForm');
    if (!form) {
        console.error('Form not found');
        return;
    }

    // Get the subshop_id from the hidden input
    const subshopIdInput = form.querySelector('input[name="subshop_id"]');
    const subshopId = subshopIdInput ? subshopIdInput.value : '{{ $subshop->id }}';
    
    // console.log('Submitting form with subshop_id:', subshopId); // Debug log

    // Custom validation: cost price should not be higher than selling price
    const price = parseFloat(form.price.value) || 0;
    const costPrice = parseFloat(form.cost_price.value) || 0;

    if (costPrice > 0 && costPrice > price) {
        Swal.fire({ icon: 'warning', title: 'Invalid pricing', text: 'Cost price cannot be higher than selling price', showConfirmButton: true });
        form.cost_price.focus();
        return;
    }

    // Create FormData and ensure subshop_id is included
    const formData = new FormData(form);
    formData.set('subshop_id', subshopId); // Ensure subshop_id is set

    // Check required fields
    const nameValue = formData.get('name');
    if (!nameValue || !nameValue.trim()) {
        Swal.fire({ icon: 'warning', title: 'Missing item name', text: 'Please enter the item name', showConfirmButton: true });
        return;
    }

    const priceValue = formData.get('price');
    if (!priceValue || parseFloat(priceValue) <= 0) {
        Swal.fire({ icon: 'warning', title: 'Invalid price', text: 'Please enter a valid price for the item', showConfirmButton: true });
        return;
    }

    const quantityValue = formData.get('quantity');
    if (quantityValue === null || quantityValue === '' || parseInt(quantityValue) < 0) {
        Swal.fire({ icon: 'warning', title: 'Invalid quantity', text: 'Please enter a valid item quantity', showConfirmButton: true });
        return;
    }

    // Log the form data being sent
    const formDataObj = {};
    for (let [key, value] of formData.entries()) {
        formDataObj[key] = value;
    }
    // console.log('Form data being submitted:', formDataObj);

    // Submit using fetch
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
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
                timer: 2500,
                timerProgressBar: true
            });
            // Close modal and reload page to show new item
            $('#addItemModal').modal('hide');
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            // Handle validation errors or other errors
            let errorMessage = data.message || 'Failed to create item';

            if (data.errors) {
                // Format validation errors
                const errorList = Object.values(data.errors).flat();
                errorMessage = errorList.join('\n');
            }

            // Show error message
            Swal.fire({
                icon: 'error',
                title: 'Something went wrong!',
                text: errorMessage,
                showConfirmButton: true
            });
        }
    }))
    .catch(error => {
        console.error('Error submitting form:', error);
        Swal.fire({
            icon: 'error',
            title: 'Something went wrong!',
            text: 'Network error occurred: ' + error.message,
            showConfirmButton: true
        });
    });
};

window.updateItem = function() {
    const form = document.getElementById('editItemForm');
    if (!form) {
        console.error('Form not found');
        return;
    }

    // Custom validation: cost price should not be higher than selling price
    const price = parseFloat(document.getElementById('edit_price').value) || 0;
    const costPrice = parseFloat(document.getElementById('edit_cost_price').value) || 0;

    if (costPrice > 0 && costPrice > price) {
        alert('Cost price cannot be higher than selling price');
        document.getElementById('edit_cost_price').focus();
        return;
    }

    // Debug: Log current form values before submission
    // console.log('Submitting edit form with values:');
    // console.log('Name:', document.getElementById('edit_name').value);
    // console.log('Price:', document.getElementById('edit_price').value);
    // console.log('Quantity:', document.getElementById('edit_quantity').value);

    // Create data object and submit as JSON
    const formDataObj = {
        name: document.getElementById('edit_name').value,
        description: document.getElementById('edit_description').value,
        category_id: document.getElementById('edit_category_id').value,
        supplier_id: document.getElementById('edit_supplier_id').value,
        price: document.getElementById('edit_price').value,
        cost_price: document.getElementById('edit_cost_price').value,
        quantity: document.getElementById('edit_quantity').value,
        min_quantity: document.getElementById('edit_min_quantity').value,
        max_quantity: document.getElementById('edit_max_quantity').value,
        unit: document.getElementById('edit_unit').value,
        barcode: document.getElementById('edit_barcode').value,
        expiry_date: document.getElementById('edit_expiry_date').value,
        is_active: document.getElementById('edit_is_active').checked ? '1' : '0'
    };

    // console.log('Submitting JSON data:', formDataObj);

    // Disable submit button
    const submitBtn = document.querySelector('#editItemModal .btn-primary');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    }

    fetch(form.action, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: JSON.stringify(formDataObj)
    })
    .then(response => response.json().then(data => {
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Item';
        }

        if (response.ok) {
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message || 'Item updated successfully.',
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true
            });
            // Close modal and reload page to show updated item
            $('#editItemModal').modal('hide');
            // Reset form after successful update
            $('#editItemForm')[0].reset();
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            // Handle validation errors or other errors
            let errorMessage = data.message || 'Failed to update item';

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
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Item';
        }

        Swal.fire({
            icon: 'error',
            title: 'Kuna Tatizo!',
            text: 'Network error occurred',
            showConfirmButton: true
        });
    });
};

window.writeOffItem = function(id, name, currentQuantity, sellingPrice, batchesEncoded) {
    // console.log('writeOffItem called with:', id, name, currentQuantity, sellingPrice);

    // Set product ID in the form
    document.getElementById('write_off_product_id').value = id;

    // Populate item information
    document.getElementById('write_off_item_name').textContent = name;
    document.getElementById('write_off_current_quantity').textContent = currentQuantity;
    document.getElementById('write_off_selling_price').textContent = 'TZS ' + parseFloat(sellingPrice).toLocaleString();
    document.getElementById('max_quantity_text').textContent = currentQuantity;

    // Set max quantity
    document.getElementById('write_off_quantity').max = currentQuantity;
    document.getElementById('write_off_quantity').value = ''; // Reset quantity input

    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('write_off_date').value = today;

    // Reset other form fields
    document.getElementById('write_off_reason').value = '';
    document.getElementById('write_off_description').value = '';

    // Populate batch select
    const batchSelect = document.getElementById('write_off_batch_id');
    batchSelect.innerHTML = '<option value="">Select Batch</option>';
    let batches = [];
    if (typeof batchesEncoded === 'string' && batchesEncoded.length) {
        try {
            const decoded = decodeURIComponent(batchesEncoded);
            batches = JSON.parse(decoded);
        } catch (e) {
            console.warn('Failed to parse batches for write-off:', e);
        }
    }

    batches.forEach((b, idx) => {
        const opt = document.createElement('option');
        opt.value = b.id || idx; // Prefer actual batch id if present
        opt.textContent = `${b.batch_number || 'BATCH'} | Qty: ${b.quantity ?? 0} | Price: ${fmtTZS(parseFloat(b.selling_price) || 0)}${b.expiry_date ? ' | Exp: ' + b.expiry_date : ''}`;
        opt.setAttribute('data-qty', parseInt(b.quantity) || 0);
        opt.setAttribute('data-price', parseFloat(b.selling_price) || 0);
        batchSelect.appendChild(opt);
    });

    // When selecting a batch, update max, unit price and calculation
    batchSelect.onchange = function() {
        const sel = batchSelect.options[batchSelect.selectedIndex];
        const bQty = parseInt(sel ? sel.getAttribute('data-qty') : '0') || 0;
        const bPrice = parseFloat(sel ? sel.getAttribute('data-price') : '0') || 0;
        document.getElementById('write_off_quantity').max = bQty;
        document.getElementById('max_quantity_text').textContent = bQty;
        document.getElementById('write_off_selling_price').textContent = fmtTZS(bPrice);
        calculateWriteOffValue();
    };

    // If there is at least one batch, select first by default
    if (batchSelect.options.length > 1) {
        batchSelect.selectedIndex = 1;
        batchSelect.onchange();
    } else {
        // No batches
        document.getElementById('write_off_quantity').max = 0;
        document.getElementById('max_quantity_text').textContent = 0;
    }

    // Initialize calculation
    calculateWriteOffValue();

    // Show modal
    $('#writeOffModal').modal('show');
};

// Quick action to write-off an expired batch without opening a modal
window.writeOffExpiredBatch = function(itemId, itemName, batchId, batchesEncoded, batchQty, batchPrice) {
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : null;
    const subshopInput = document.getElementById('write_off_subshop_id');
    const subshopId = subshopInput ? subshopInput.value : null;

    if (!csrfToken || !subshopId) {
        Swal.fire({
            icon: 'error',
            title: 'Cannot proceed',
            text: 'Unable to process write-off: missing CSRF token or subshop context.',
        });
        return;
    }

    Swal.fire({
        title: 'Write off expired batch?',
        html: 'Item: <strong>' + itemName.replace(/</g, '&lt;') + '</strong><br>This will remove the entire batch from stock.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, write off',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: 'Processing...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch("{{ route('writeoffs.expired-batch') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                subshop_id: subshopId,
                item_id: itemId,
                batch_id: batchId
            })
        })
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.success === false) {
                throw new Error(data.message || 'Failed to write off expired batch');
            }
            return data;
        })
        .then((data) => {
            Swal.fire({
                icon: 'success',
                title: 'Write-off completed',
                text: data.message || 'Expired batch has been written off.',
                timer: 1500,
                timerProgressBar: true,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        })
        .catch((err) => {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message || 'An error occurred while writing off the expired batch.',
            });
        });
    });
};

window.submitWriteOffForm = function() {
    const form = document.getElementById('writeOffForm');
    if (!form) {
        console.error('Form not found');
        return;
    }

    // Check required fields
    const quantityInput = form.querySelector('#write_off_quantity');
    const reasonInput = form.querySelector('#write_off_reason');
    const dateInput = form.querySelector('#write_off_date');
    
    // Reset validation
    [quantityInput, reasonInput, dateInput].forEach(input => {
        input.classList.remove('is-invalid');
    });

    let isValid = true;
    
    // Validate quantity
    if (!quantityInput.value || parseInt(quantityInput.value) <= 0) {
        quantityInput.classList.add('is-invalid');
        isValid = false;
    } else if (parseInt(quantityInput.value) > parseInt(quantityInput.max)) {
        Swal.fire({
            icon: 'error',
            title: 'Kosa!',
            text: 'Idadi ya bidhaa haifai. Hakuna vifaa vya kutosha kwenye hifadhi.',
            showConfirmButton: true
        });
        return;
    }

    // Validate reason
    if (!reasonInput.value) {
        reasonInput.classList.add('is-invalid');
        isValid = false;
    }

    // Validate date
    if (!dateInput.value) {
        dateInput.classList.add('is-invalid');
        isValid = false;
    }

    if (!isValid) {
        Swal.fire({
            icon: 'error',
            title: 'Warning!',
            text: 'Please make sure you have completed all required fields (*)',
            showConfirmButton: true
        });
        return;
    }

    // Show loading state
    const submitButton = form.querySelector('button[type="button"]');
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    // Submit using fetch
    fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw err;
            });
        }
        return response.json();
    })
    .then(data => {
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message || 'Item write-off submitted successfully and is awaiting approval.',
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true,
            });
        
            // Close modal and reload page
            $('#writeOffModal').modal('hide');
            setTimeout(() => {
                location.reload();
            }, 500);
    })
    .catch(error => {
        console.error('Error:', error);
        
        let errorMessage = 'An error occurred while submitting the item write-off.';
        
        if (error && error.message) {
            errorMessage = error.message;
        } else if (error && error.errors) {
                // Format validation errors
            const errorList = Object.values(error.errors).flat();
                errorMessage = errorList.join('\n');
            }

            // Show error message
            Swal.fire({
                icon: 'error',
                title: 'Something went wrong!',
                text: errorMessage,
                showConfirmButton: true
            });
    })
    .finally(() => {
        // Reset button state
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    });
};

$(document).ready(function() {
    $('#editItemModal').on('show.bs.modal', function (event) {
        // Don't reset form here - we'll populate it with data
        // $('#editItemForm')[0].reset();
    });

    // Don't reset form when modal is hidden to avoid clearing data during submission
    // $('#editItemModal').on('hidden.bs.modal', function (event) {
    //     $('#editItemForm')[0].reset();
    // });

    $('#addItemModal').on('hidden.bs.modal', function (event) {
        // Reset form when add modal is closed
        $('#addItemForm')[0].reset();
        // Reset checkbox to default checked state
        document.getElementById('is_active').checked = true;
    });

    // Auto-calculate margin percentage
    $('#cost_price, #price').on('input', function() {
        calculateMargin();
    });

    $('#edit_cost_price, #edit_price').on('input', function() {
        calculateEditMargin();
    });

    // Initialize DataTable with enhanced features
    $('#itemsTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "responsive": true,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        "pageLength": 10,
        //"order": [[0, "asc"]], // Default sort by first column (ID)
        "language": {
            "search": "Tafuta:",
            "lengthMenu": "Onyesha _MENU_ entries",
            "info": "Onyesha _START_ hadi _END_ ya jumla ya _TOTAL_ entries",
            "infoEmpty": "Hakuna data iliyopatikana",
            "infoFiltered": "(iliyochujwa kutoka jumla ya _MAX_ entries)",
            "zeroRecords": "Hakuna rekodi zinazolingana",
            "emptyTable": "Hakuna data katika jedwali",
            "paginate": {
                "first": "Mwanzo",
                "last": "Mwisho",
                "next": "Ijayo",
                "previous": "Iliyopita"
            }
        },
        "columnDefs": [
            {
                "targets": [7], // Actions column
                "orderable": false,
                "searchable": false
            }
        ]
    });

    // Add write-off calculation listener
    $('#write_off_quantity').on('input', function() {
        calculateWriteOffValue();
    });

    // Reset write-off modal when closed
    $('#writeOffModal').on('hidden.bs.modal', function (event) {
        $('#writeOffForm')[0].reset();
        calculateWriteOffValue(); // Reset calculation
    });

    // Toggle filter icon on collapse
    $('#advancedFilters').on('show.bs.collapse', function () {
        $('#filterIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    });

    $('#advancedFilters').on('hide.bs.collapse', function () {
        $('#filterIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });
});
</script>
@stop

@section('js')
<script>
// Flag to prevent multiple initializations
var pageInitialized = false;

// Wait for jQuery to load
function initPage() {
    if (typeof jQuery === 'undefined') {
        // console.log('jQuery not ready, retrying...');
        setTimeout(initPage, 100);
        return;
    }
    
    if (pageInitialized) {
        // console.log('Page already initialized, skipping...');
        return;
    }
    
    // Check if SweetAlert2 is available
    if (typeof Swal === 'undefined') {
        // console.log('SweetAlert2 not ready, retrying...');
        setTimeout(initPage, 100);
        return;
    }
    
    // console.log('SweetAlert2 is available:', typeof Swal);
    
    // console.log('jQuery and SweetAlert2 are ready, initializing...');
    
    $(document).ready(function() {
        $('#editItemModal').on('show.bs.modal', function (event) {
            // Don't reset form here - we'll populate it with data
            // $('#editItemForm')[0].reset();
        });

        // Don't reset form when modal is hidden to avoid clearing data during submission
        // $('#editItemModal').on('hidden.bs.modal', function (event) {
        //     $('#editItemForm')[0].reset();
        // });

        $('#addItemModal').on('hidden.bs.modal', function (event) {
            // Reset form when add modal is closed
            $('#addItemForm')[0].reset();
            
            // Reset checkbox to default checked state
            document.getElementById('is_active').checked = true;
        });

        // Auto-calculate margin percentage
        $('#cost_price, #price').on('input', function() {
            calculateMargin();
        });

        $('#edit_cost_price, #edit_price').on('input', function() {
            calculateEditMargin();
        });

        // Initialize DataTable with enhanced features (check if already initialized)
        if ($.fn.DataTable.isDataTable('#itemsTable')) {
            // console.log('DataTable already exists, destroying first...');
            $('#itemsTable').DataTable().destroy();
        }
        
        // console.log('Initializing DataTable...');
        $('#itemsTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "responsive": true,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
            "pageLength": 10,
            //"order": [[1, "asc"]], // Default sort by first column (ID)
            "language": {
                "search": "Search:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoEmpty": "No data available",
                "infoFiltered": "(filtered from _MAX_ total entries)",
                "zeroRecords": "No matching records found",
                "emptyTable": "No data available in table",
                "paginate": {
                    "first": "First",
                    "last": "Last",
                    "next": "Next",
                    "previous": "Previous"
                }
            },
            "columnDefs": [
                {
                    "targets": [7], // Actions column
                    "orderable": false,
                    "searchable": false
                }
            ]
        });

        // Add write-off calculation listener
        $('#write_off_quantity').on('input', function() {
            calculateWriteOffValue();
        });

        // Reset write-off modal when closed
        $('#writeOffModal').on('hidden.bs.modal', function (event) {
            $('#writeOffForm')[0].reset();
            calculateWriteOffValue(); // Reset calculation
        });

        // Toggle filter icon on collapse
        $('#advancedFilters').on('show.bs.collapse', function () {
            $('#filterIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        });

        $('#advancedFilters').on('hide.bs.collapse', function () {
            $('#filterIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });
        
        // Mark page as initialized
        pageInitialized = true;
        // console.log('Page initialization completed');
    });
}

// Start initialization
initPage();
</script>
@stop