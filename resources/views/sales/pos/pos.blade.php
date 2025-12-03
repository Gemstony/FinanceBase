@extends('adminlte::page')

@section('title', 'Point of Sale - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-cash-register"></i> Point Of Sale (POS)</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-cash-register"></i> POS</h1>
                    <p class="mb-0 text-light">Shop: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('pos.subshops') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Change Shop
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('pos.subshops') }}">Choose Shop</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - POS</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <input type="hidden" id="pos_subshop_id" value="{{ $subshop->id }}">

    <!-- Customer and Payment Info -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-lg" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-user-circle text-primary"></i> Customer</h3>
                    @can('sale_items')
                    <button class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addCustomerModal"><i class="fas fa-user-plus"></i> Quick Add Customer</button>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="customer_select" class="mb-1"><i class="fas fa-search text-muted"></i> Search Customer</label>
                        @can('sale_items')
                        <select id="customer_select" class="form-control" style="width:100%" placeholder="Type to search..."></select>
                        @endcan
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3 border-0 rounded-lg" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-box-open text-primary"></i> Items</h3>
                    @can('sale_items')
                    <button type="button" class="btn btn-outline-primary btn-sm" id="open_item_picker"><i class="fas fa-th-large"></i> Browse Items</button>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="cart_table">
                            <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                                <tr>
                                    <th style="width: 22%">Item</th>
                                    <th style="width: 10%">Unit</th>
                                    <th style="width: 12%" class="text-right">Unit Price</th>
                                    <th style="width: 10%" class="text-center">Batch</th>
                                    <th style="width: 10%" class="text-center">Qty</th>
                                    <th style="width: 16%" class="text-left">VAT Type</th>
                                    <th style="width: 10%" class="text-right">VAT Amt</th>
                                    <th style="width: 12%" class="text-right">Subtotal</th>
                                    <th style="width: 8%" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="cart_body">
                                <tr class="empty-row">
                                    <td colspan="8" class="text-center text-muted py-4"><i class="fas fa-inbox"></i> No items added. Use the box above to add items.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-lg sticky-summary" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-receipt text-info"></i> Summary</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Subtotal</span>
                        <strong id="sum_subtotal" class="text-dark">0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Total VAT</span>
                        <strong id="sum_vat" class="text-secondary">0.00</strong>
                    </div>
                    <hr/>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label><i class="fas fa-percentage text-muted"></i> Discount %</label>
                            <input type="number" min="0" max="100" step="0.01" id="discount_percent" class="form-control" value="0">
                            <small id="discount_percent_error" class="text-danger"></small>
                        </div>
                        <div class="form-group col-6">
                            <label><i class="fas fa-coins text-muted"></i> Discount (Cash)</label>
                            <input type="number" min="0" step="0.01" id="discount_cash" class="form-control" value="0">
                            <small id="discount_cash_error" class="text-danger"></small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Discount Total</span>
                        <strong id="sum_discount" class="text-danger">0.00</strong>
                    </div>  
                    
                    <div class="d-flex justify-content-between align-items-center h4 mt-3 p-2 rounded" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb);">
                        <span class="mb-0"><i class="fas fa-calculator text-primary"></i> Grand Total</span>
                        <strong id="sum_grand" class="text-primary h4 mb-0">0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Remaining</span>
                        <strong id="sum_remaining" class="text-danger">0.00</strong>
                    </div>
                    <hr/>
                    <div class="form-group">
                        <label><i class="fas fa-money-check-alt text-muted"></i> Payment Method</label>
                        @can('sale_items')
                        <select id="payment_method" class="form-control">
                            <option value="" disabled selected>--Choose--</option>
                           @foreach ($banks as $bank)
                           <option value="{{ $bank->name }}">{{ $bank->name }} ({{ $bank->account_number }})</option>
                           @endforeach
                        </select>
                        @endcan
                    </div>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label><i class="fas fa-hand-holding-usd text-muted"></i> Amount Paid</label>
                            <input type="number" min="0" step="0.01" id="amount_paid" class="form-control" value="0">
                        </div>
                        <div class="form-group col-6">
                            <label><i class="fas fa-exchange-alt text-muted"></i> Change</label>
                            <input type="text" id="amount_change" class="form-control" value="0.00" readonly>
                        </div>
                    </div>
                    <div id="pos_save_alert" class="alert d-none mt-2" role="alert"></div>
                    @can('sale_items')
                    <button type="button" class="btn btn-primary btn-block shadow-sm" id="complete_sale_btn"><i class="fas fa-check"></i> Complete Sale</button>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="quickAddCustomerForm" method="POST" action="{{ route('customers.store') }}">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCustomerModalLabel">Add New Customer</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label>Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="form-group col-6">
                            <label>Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" class="form-control" name="address">
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="quick_is_active" name="is_active" value="1" checked>
                        <label class="custom-control-label" for="quick_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        #cart_table input[type=number] { min-width: 90px; }
        #cart_table td, #cart_table th { vertical-align: middle; }
        #cart_table tbody tr:hover { background: #f4fff7; }
        #cart_table tbody td { border-top-color: #dcedc8; }
        .select2-container--default .select2-selection--single { height: 38px; padding: 4px 8px; border-radius: .5rem; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 30px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .sticky-summary { position: sticky; top: 12px; }
        .card-header { background: linear-gradient(90deg, #ffffff, #f3fff7); border-bottom: 1px solid #dcedc8; }
        .card { border-radius: .75rem; }
        .badge { font-weight: 500; }
        .input-group-text { border-right: 0; }
        .input-group .form-control { border-left: 0; }
        .alert { border-radius: .5rem; }
        .qty-input.is-invalid { box-shadow: 0 0 0 .2rem rgba(220,53,69,.1); }
        .batch-number-input { min-width: 120px; }
        .selling-price-input { min-width: 120px; }

        .item-card { border: 1px solid #dcedc8; border-radius: .75rem; background: #fff; box-shadow: 0 6px 20px rgba(0,0,0,.03); transition: transform .15s ease, box-shadow .15s ease; }
        .item-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(0,0,0,.08); }
        .item-card .card-body { padding: 0.9rem 1rem; }
        .item-name { font-weight: 600; color: #1b5e20; }
        .item-meta { font-size: .875rem; color: #2e7d32; }
        .badge-stock { background: #c8e6c9; color: #1b5e20; }
        .badge-out { background: #ffebee; color: #b71c1c; }
        .picker-search { border-radius: .5rem; }

        .batch-row { padding: 0.75rem; border: 1px solid #e9ecef; border-radius: 0.375rem; margin-bottom: 0.5rem; background: #f8f9fa; }
        .batch-row.selected { border-color: #007bff; background: #e7f3ff; }
        .batch-radio { margin-right: 0.5rem; }
        .batch-info { font-size: 0.875rem; color: #6c757d; }
        .batch-expiry-warning { color: #dc3545; font-weight: 500; }
    </style>
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .batch-summary { margin-top: 0.25rem; }
    .batch-summary .batch-numbers { 
        font-size: 0.75rem; 
        word-break: break-word;
        margin-top: 0.25rem;
    }
    .item-card { 
        border: 1px solid #dee2e6; 
        border-radius: 0.375rem;
        transition: box-shadow 0.15s ease-in-out;
    }
    .item-card:hover { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); }
</style>
<script>
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

(function(){
    const subshopId = document.getElementById('pos_subshop_id').value;
    const csrfToken = '{{ csrf_token() }}';

    // Select2: Customers
    $('#customer_select').select2({
        placeholder: 'Search customer by name, email, phone',
        allowClear: true,
        ajax: {
            url: '{{ route('api.pos.customers') }}',
            dataType: 'json', delay: 250,
            data: function (params) {
                return { q: params.term, subshop_id: subshopId };
            },
            processResults: function (data) {
                return {
                    results: data.map(c => ({id: c.id, text: c.name + (c.phone ? ' - ' + c.phone : '')}))
                };
            },
            cache: true
        },
        width: '100%'
    });

    // Item Picker Modal
    const itemModalHtml = `
<div class="modal fade" id="itemPickerModal" tabindex="-1" role="dialog" aria-labelledby="itemPickerLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="itemPickerLabel"><i class="fas fa-th-large text-primary"></i> Browse Items</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="input-group mb-3">
          <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
          <input type="text" id="picker_search" class="form-control picker-search" placeholder="Search items by name or batch...">
        </div>
        <div id="picker_results" class="container-fluid">
          <div class="row" id="picker_grid"></div>
        </div>
        <div id="picker_empty" class="text-center text-muted d-none py-5"><i class="fas fa-box-open"></i> No items found</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Batch Selection Modal -->
<div class="modal fade" id="batchSelectionModal" tabindex="-1" role="dialog" aria-labelledby="batchSelectionLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="batchSelectionLabel"><i class="fas fa-boxes text-primary"></i> Select Batch for <span id="batch-item-name"></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="batch-list" class="mb-3">
          <!-- Batch options will be populated here -->
        </div>
        <div class="form-group">
          <label>Quantity to Add:</label>
          <input type="number" id="batch-qty-input" class="form-control" min="1" value="1">
          <small id="batch-qty-error" class="text-danger d-none"></small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirm-batch-add">Add to Cart</button>
      </div>
    </div>
  </div>
</div>`;
    if(!document.getElementById('itemPickerModal')){
        document.body.insertAdjacentHTML('beforeend', itemModalHtml);
    }

    function debounce(fn, wait){ let t; return function(){ clearTimeout(t); const args=arguments, ctx=this; t=setTimeout(()=>fn.apply(ctx,args), wait); }; }

    function getCartIdSet(){ return new Set(cart.map(r => r.id)); }

    function renderPickerItems(items){
        const grid = document.getElementById('picker_grid');
        grid.innerHTML = '';
        if(!items || items.length === 0){
            document.getElementById('picker_empty').classList.remove('d-none');
            return;
        }
        document.getElementById('picker_empty').classList.add('d-none');
        const idSet = getCartIdSet();
        items.forEach(i => {
            // Calculate total available quantity from all batches only
            const totalAvailable = (i.batches && i.batches.length > 0) 
                ? i.batches.reduce((sum, batch) => sum + parseInt(batch.quantity || 0, 10), 0)
                : 0; // Always 0 if no batches available
            
            // Calculate batch details
            let batchDetails = '';
            if (i.batches && i.batches.length > 0) {
                const batchCount = i.batches.length;
                const batchNumbers = i.batches.map(b => b.batch_number).join(', ');
                
                // Get price range
                const prices = i.batches.map(b => parseFloat(b.selling_price)).filter(p => !isNaN(p));
                const minPrice = Math.min(...prices);
                const maxPrice = Math.max(...prices);
                const priceRange = minPrice === maxPrice ? minPrice.toFixed(2) : `${minPrice.toFixed(2)} - ${maxPrice.toFixed(2)}`;
                
                // Get earliest expiry date
                const expiryDates = i.batches
                    .map(b => b.expire_date)
                    .filter(date => date)
                    .sort((a, b) => new Date(a) - new Date(b));
                const earliestExpiry = expiryDates.length > 0 ? expiryDates[0] : null;
                
                // Check for expiring soon batches
                const expiringSoonCount = i.batches.filter(b => 
                    b.expire_date && new Date(b.expire_date) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)
                ).length;
                
                batchDetails = `
                    <div class="batch-summary small text-muted">
                        <div><strong>${batchCount}</strong> batch${batchCount > 1 ? 'es' : ''} • Price: <strong>${priceRange}</strong></div>
                        ${earliestExpiry ? `<div>Earliest expiry: <strong>${earliestExpiry}</strong></div>` : ''}
                        ${expiringSoonCount > 0 ? `<div class="text-danger"><i class="fas fa-exclamation-triangle"></i> ${expiringSoonCount} batch${expiringSoonCount > 1 ? 'es' : ''} Expired</div>` : ''}
                        <div class="batch-numbers">Batches: <strong>${batchNumbers}</strong></div>
                    </div>`;
            }
            
            const inCart = idSet.has(i.id);
            const disabled = totalAvailable <= 0 || inCart;
            const col = document.createElement('div');
            col.className = 'col-md-4 mb-3';
            col.innerHTML = `
                <div class="item-card h-100">
                  <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                      <div class="item-name">${i.name}</div>
                      <span class="badge ${totalAvailable<=0?'badge-out':'badge-stock'}">${totalAvailable<=0?'Out':'In'} Stock</span>
                    </div>
                    <div class="item-meta mb-2">
                        <div class="mb-1">Unit: ${i.unit || 'unit'}</div>
                        ${batchDetails}
                    </div>
                    <div class="mt-auto d-flex justify-content-between align-items-center">
                      <small class="text-muted">Available: <strong>${totalAvailable}</strong></small>
                      <button class="btn btn-sm ${disabled?'btn-secondary':'btn-primary'} add-from-picker" data-id="${i.id}" ${disabled?'disabled':''} data-json='${JSON.stringify(i).replace(/'/g,"&#39;")}'>
                        ${inCart ? '<i class="fas fa-check"></i> Added' : '<i class="fas fa-cart-plus"></i> Add'}
                      </button>
                    </div>
                  </div>
                </div>`;
            grid.appendChild(col);
        });
    }

    function loadPickerItems(q){
        $.ajax({
            url: '{{ route('api.pos.items') }}', dataType: 'json', data: { q: q || '', subshop_id: subshopId },
            success: function(data){ renderPickerItems(data); },
            error: function(){ renderPickerItems([]); }
        });
    }

    $('#open_item_picker').on('click', function(){
        $('#itemPickerModal').modal('show');
        loadPickerItems('');
    });
    $(document).on('keyup', '#picker_search', debounce(function(){ loadPickerItems(this.value); }, 300));
    $(document).on('click', '.add-from-picker', function(){
        const $btn = $(this);
        if($btn.prop('disabled')) return;
        const raw = $btn.attr('data-json');
        if(!raw) return; const i = JSON.parse(raw.replace(/&#39;/g, "'"));
        // Check if item has batches
        if(!i.batches || i.batches.length === 0){
            showAlert('warning', 'No batches available for this item');
            return;
        }
        // Show batch selection modal instead of adding directly
        showBatchSelectionModal(i);
        $('#itemPickerModal').modal('hide');
    });

    // Cart state
    const VAT_RATE = 18; // percent
    let cart = []; // {id,name,unit,price,qty,vatType,batch_id,batch_number,available,qtyError}
    let selectedItemForBatch = null;
    let selectedBatchId = null;

    function showBatchSelectionModal(item) {
        selectedItemForBatch = item;
        selectedBatchId = null;
        
        document.getElementById('batch-item-name').textContent = item.name;
        document.getElementById('batch-qty-input').value = '1';
        document.getElementById('batch-qty-error').classList.add('d-none');
        
        const batchList = document.getElementById('batch-list');
        batchList.innerHTML = '';
        
        if (!item.batches || item.batches.length === 0) {
            batchList.innerHTML = '<div class="alert alert-warning">No batches available for this item.</div>';
            return;
        }
        
        // Sort batches by expiry date (FIFO - oldest first)
        const sortedBatches = [...item.batches].sort((a, b) => {
            if (!a.expire_date && !b.expire_date) return 0;
            if (!a.expire_date) return 1;
            if (!b.expire_date) return -1;
            return new Date(a.expire_date) - new Date(b.expire_date);
        });
        
        sortedBatches.forEach((batch, index) => {
            const isExpiringSoon = batch.expire_date && new Date(batch.expire_date) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000); // 30 days
            
            const batchRow = document.createElement('div');
            batchRow.className = 'batch-row';
            batchRow.setAttribute('data-batch-id', batch.id);
            batchRow.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input batch-radio" type="radio" name="selected_batch" value="${batch.id}" id="batch-${batch.id}">
                    <label class="form-check-label" for="batch-${batch.id}">
                        <strong>${batch.batch_number}</strong>
                        <span class="batch-info ml-2">
                            Available: ${batch.quantity} | 
                            Price: ${parseFloat(batch.selling_price).toFixed(2)}
                            ${batch.expire_date ? ` | Expires: ${batch.expire_date}` : ''}
                        </span>
                        ${isExpiringSoon ? '<span class="batch-expiry-danger text-danger ml-2">⚠️ Expired</span>' : ''}
                    </label>
                </div>
            `;
            batchList.appendChild(batchRow);
            
            // Auto-select first batch (FIFO)
            if (index === 0) {
                batchRow.classList.add('selected');
                selectedBatchId = batch.id;
                const radio = batchRow.querySelector('.batch-radio');
                radio.checked = true;
            }
        });
        
        $('#batchSelectionModal').modal('show');
    }

    function computeLine(row){
        const qty = row.qty;
        const price = row.price;
        const base = price * qty; // base amount from unit price
        let vatAmt = 0;
        if(row.vatType === 'exclusive'){
            vatAmt = base * (VAT_RATE/100); // add VAT on top
        } else {
            // none or inclusive -> no extra VAT added per requirement
            vatAmt = 0;
        }
        const lineTotal = base + vatAmt;
        return { base, vatAmt, lineTotal };
    }

    function refreshPickerButtons(){
        const idSet = getCartIdSet();
        document.querySelectorAll('#itemPickerModal .add-from-picker').forEach(btn => {
            const id = parseInt(btn.getAttribute('data-id'), 10);
            if(idSet.has(id)){
                btn.disabled = true;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-secondary');
                btn.innerHTML = '<i class="fas fa-check"></i> Added';
            } else if(!btn.classList.contains('btn-secondary')) {
                // leave as is (enabled success)
            }
        });
    }

    function renderCart(){
        const tbody = document.getElementById('cart_body');
        tbody.innerHTML = '';
        if(cart.length === 0){
            tbody.innerHTML = '<tr class="empty-row"><td colspan="9" class="text-center text-muted py-4">No items added. Use the selector above to add items.</td></tr>';
            updateSummary();
            refreshPickerButtons();
            return;
        }

        cart.forEach((row, idx) => {
            const { vatAmt, lineTotal } = computeLine(row);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${row.name}</td>
                <td>${row.unit || ''}</td>
                <td class="text-right">
                    <input type="number" class="form-control form-control-sm text-right price-input" data-idx="${idx}" value="${row.price}" step="0.01" min="0" readonly />
                </td>
                <td class="text-center">
                    <span class="badge badge-info">${row.batch_number || 'N/A'}</span>
                </td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm text-center qty-input ${row.qtyError?'is-invalid':''}" data-idx="${idx}" value="${row.qty}" step="1" min="1" max="${row.available ?? ''}" />
                    <div class="small text-muted">Avail: ${row.available ?? 0}</div>
                    ${row.qtyError ? `<div class=\"invalid-feedback d-block\">Max ${row.available}</div>` : ''}
                </td>
                <td class="text-left">
                    <select class="form-control form-control-sm vat-type" data-idx="${idx}" style="min-width:140px;">
                        <option value="none" ${row.vatType==='none'?'selected':''}>No VAT</option>
                        <option value="inclusive" ${row.vatType==='inclusive'?'selected':''}>Inclusive (18%)</option>
                        <option value="exclusive" ${row.vatType==='exclusive'?'selected':''}>Exclusive (18%)</option>
                    </select>
                </td>
                <td class="text-right vat-amt">${vatAmt.toFixed(2)}</td>
                <td class="text-right subtotal">${lineTotal.toFixed(2)}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-danger remove-item" data-idx="${idx}"><i class="fas fa-trash"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
        updateSummary();
        refreshPickerButtons();
    }

    function updateRowDOM(idx, tr){
        if (!tr) return;
        const vals = computeLine(cart[idx]);
        const vatCell = tr.querySelector('.vat-amt');
        const subCell = tr.querySelector('.subtotal');
        if (vatCell) vatCell.textContent = vals.vatAmt.toFixed(2);
        if (subCell) subCell.textContent = vals.lineTotal.toFixed(2);
        const qtyInput = tr.querySelector('.qty-input');
        if (qtyInput) {
            if (cart[idx].qtyError) {
                qtyInput.classList.add('is-invalid');
            } else {
                qtyInput.classList.remove('is-invalid');
            }
        }
    }

    function updateSummary(){
        let sumSubtotal = 0; // subtotal should reflect base + VAT (line totals)
        let sumVat = 0;
        cart.forEach(r => {
            const { base, vatAmt, lineTotal } = computeLine(r);
            sumVat += vatAmt;
            sumSubtotal += lineTotal;
        });
        let discountPercent = parseFloat(document.getElementById('discount_percent').value || '0');
        let discountCash = parseFloat(document.getElementById('discount_cash').value || '0');
        if(discountPercent < 0) discountPercent = 0; if(discountPercent > 100) discountPercent = 100;

        const gross = sumSubtotal; // subtotal already includes VAT
        const discountFromPercent = gross * (discountPercent/100);
        const maxCash = Math.max(0, gross - discountFromPercent);
        if(discountCash > maxCash){
            discountCash = maxCash;
            const dc = document.getElementById('discount_cash');
            if(dc) dc.value = maxCash.toFixed(2);
            const err = document.getElementById('discount_cash_error');
            if(err) err.textContent = 'Cannot exceed grand total';
        } else {
            const err = document.getElementById('discount_cash_error');
            if(err) err.textContent = '';
        }
        const discountTotal = discountFromPercent + discountCash;
        let grand = gross - discountTotal;
        if(grand < 0) grand = 0;

        document.getElementById('sum_subtotal').innerText = sumSubtotal.toFixed(2);
        document.getElementById('sum_vat').innerText = sumVat.toFixed(2);
        document.getElementById('sum_discount').innerText = discountTotal.toFixed(2);
        document.getElementById('sum_grand').innerText = grand.toFixed(2);

        const paid = parseFloat(document.getElementById('amount_paid').value || '0');
        const change = paid - grand;
        document.getElementById('amount_change').value = (change > 0 ? change : 0).toFixed(2);
        const remaining = Math.max(0, grand - (isNaN(paid)?0:paid));
        const remEl = document.getElementById('sum_remaining');
        if(remEl){ remEl.innerText = remaining.toFixed(2); }
    }

    // Removed old select2 add flow; now handled by item picker modal

    // Delegated events for cart inputs
    document.getElementById('cart_body').addEventListener('input', function(e){
        if(!e.target.classList.contains('qty-input')) return;
        const idx = e.target.getAttribute('data-idx');
        if(idx === null) return;
        let q = parseInt(e.target.value || '1', 10);
        if(isNaN(q) || q < 1) q = 1;
        const maxAvail = parseInt(cart[idx].available || 0, 10);
        if(maxAvail > 0 && q > maxAvail){
            cart[idx].qty = maxAvail;
            cart[idx].qtyError = true;
            e.target.value = String(maxAvail);
        } else {
            cart[idx].qtyError = false;
            cart[idx].qty = q;
        }
        // Update only this row and totals to preserve typing
        const tr = e.target.closest('tr');
        updateRowDOM(idx, tr);
        updateSummary();
    });

    // Select all qty on focus to make overwrite easy
    document.getElementById('cart_body').addEventListener('focusin', function(e){
        if(e.target.classList.contains('qty-input')){
            e.target.select();
        }
    });

    // Handle VAT type change
    document.getElementById('cart_body').addEventListener('change', function(e){
        const sel = e.target.closest('select.vat-type');
        if(!sel) return;
        const idx = sel.getAttribute('data-idx');
        if(idx === null) return;
        cart[idx].vatType = sel.value;
        renderCart();
    });

    document.getElementById('cart_body').addEventListener('click', function(e){
        if(e.target.closest('.remove-item')){
            const idx = e.target.closest('.remove-item').getAttribute('data-idx');
            cart.splice(idx, 1);
            renderCart();
        }
    });

    // Discounts, payment changes
    ['discount_percent','discount_cash','amount_paid'].forEach(id => {
        document.getElementById(id).addEventListener('input', function(e){
            if(e.target.id === 'discount_percent'){
                let v = parseFloat(e.target.value || '0');
                if(v > 100){ v = 100; e.target.value = '100';
                    document.getElementById('discount_percent_error').textContent = 'Max 100%';
                } else {
                    document.getElementById('discount_percent_error').textContent = '';
                }
                if(v < 0){ v = 0; e.target.value = '0'; }
            }
            updateSummary();
        });
    });

    // Batch selection event handlers
    $(document).on('change', '.batch-radio', function() {
        const batchId = $(this).val();
        selectedBatchId = batchId;
        
        // Update visual selection
        $('.batch-row').removeClass('selected');
        $(this).closest('.batch-row').addClass('selected');
        
        // Update quantity max based on selected batch
        const selectedBatch = selectedItemForBatch.batches.find(b => b.id == batchId);
        if (selectedBatch) {
            $('#batch-qty-input').attr('max', selectedBatch.quantity);
        }
    });

    // Confirm batch add
    $('#confirm-batch-add').on('click', function() {
        if (!selectedBatchId || !selectedItemForBatch) {
            showAlert('warning', 'Please select a batch');
            return;
        }
        
        const qty = parseInt($('#batch-qty-input').val(), 10);
        if (isNaN(qty) || qty < 1) {
            $('#batch-qty-error').text('Please enter a valid quantity').removeClass('d-none');
            return;
        }
        
        const selectedBatch = selectedItemForBatch.batches.find(b => b.id == selectedBatchId);
        if (qty > selectedBatch.quantity) {
            $('#batch-qty-error').text(`Cannot add more than ${selectedBatch.quantity} items from this batch`).removeClass('d-none');
            return;
        }
        
        // Check if this specific batch is already in cart
        const existingCartItem = cart.find(r => r.batch_id == selectedBatchId);
        if (existingCartItem) {
            // Update quantity instead of adding new item
            const newQty = existingCartItem.qty + qty;
            if (newQty > selectedBatch.quantity) {
                $('#batch-qty-error').text(`Total quantity would exceed batch availability (${selectedBatch.quantity})`).removeClass('d-none');
                return;
            }
            existingCartItem.qty = newQty;
        } else {
            // Add new item with batch info
            cart.push({ 
                id: selectedItemForBatch.id, 
                name: selectedItemForBatch.name, 
                unit: selectedItemForBatch.unit, 
                price: parseFloat(selectedBatch.selling_price), 
                qty: qty, 
                vatType: 'none', 
                batch_id: selectedBatch.id,
                batch_number: selectedBatch.batch_number,
                available: selectedBatch.quantity, 
                qtyError: false 
            });
        }
        
        renderCart();
        $('#batchSelectionModal').modal('hide');
        $('#batch-qty-error').addClass('d-none');
    });
    
    // Quick add customer (AJAX)
    $('#quickAddCustomerForm').on('submit', function(e){
        e.preventDefault();
        const form = $(this);
        $.ajax({
            url: form.attr('action'), method: 'POST', data: form.serialize(),
            success: function(res){
                if(res && res.customer){
                    const c = res.customer;
                    const option = new Option(c.name + (c.phone? ' - '+c.phone : ''), c.id, true, true);
                    $('#customer_select').append(option).trigger('change');
                    $('#addCustomerModal').modal('hide');
                    form[0].reset();
                    Swal.fire({
                        icon: 'success',
                        title: 'Customer added',
                        text: c.name + (c.phone? ' - '+c.phone : ''),
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: 'Unable to add customer' });
                }
            },
            error: function(xhr){
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to add customer';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });

    function showAlert(type, message){
        const el = document.getElementById('pos_save_alert');
        el.className = 'alert alert-' + type;
        el.textContent = message;
        el.classList.remove('d-none');
        setTimeout(()=>{ el.classList.add('d-none'); }, 5000);
    }

    function submitSale(payload){
        const $btn = $('#complete_sale_btn');
        $btn.prop('disabled', true);
        // Show loading while processing the order
        Swal.fire({
            title: 'Processing sale...',
            html: 'Please wait while we place your order.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => { Swal.showLoading(); }
        });
        $.ajax({
            url: '{{ route('pos.store') }}',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            data: JSON.stringify(payload),
            processData: false,
            contentType: 'application/json',
            success: function(res){
                const changeTxt = parseFloat(res.change).toFixed(2);
                Swal.fire({
                    icon: 'success',
                    title: 'Sale completed',
                    html: 'Order No: <strong>' + res.order_no + '</strong><br/>Change: <strong>' + changeTxt + '</strong>',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // reset cart and summary
                    cart = [];
                    renderCart();
                    $('#amount_paid').val('0');
                });
            },
            error: function(xhr){
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to complete sale';
                try { Swal.close(); } catch(e){}
                showAlert('danger', msg);
            },
            complete: function(){
                $btn.prop('disabled', false);
            }
        });
    }

    // Complete sale with confirmation -> send to backend
    $('#complete_sale_btn').on('click', function(){
        if(cart.length === 0){ showAlert('warning','Please add items to cart'); return; }

        const customerId = ($('#customer_select').val() || null);
        // Require a customer selection before proceeding
        if(!customerId){
            showAlert('warning','Please select a customer.');
            try { $('#customer_select').select2('open'); } catch(e) { $('#customer_select').focus(); }
            return;
        }
        const paymentMethod = $('#payment_method').val();
        const amountPaid = parseFloat($('#amount_paid').val() || '0');
        const discountPercent = parseFloat($('#discount_percent').val() || '0');
        const discountCash = parseFloat($('#discount_cash').val() || '0');

        // Conditionally require payment method only if some payment is entered
        if(amountPaid > 0 && (!paymentMethod || paymentMethod === '')){
            showAlert('warning', 'Please select a payment method when entering Amount Paid.');
            $('#payment_method').focus();
            return;
        }

        const payload = {
            subshop_id: subshopId,
            customer_id: customerId,
            payment_method: paymentMethod,
            amount_paid: amountPaid,
            discount_percent: discountPercent,
            discount_cash: discountCash,
            items: cart.map(r => ({ id: r.id, name: r.name, unit: r.unit, price: r.price, qty: r.qty, vatType: r.vatType, batch_id: r.batch_id }))
        };

        const totalText = document.getElementById('sum_grand').innerText || '0.00';
        const paidText = (isNaN(amountPaid) ? 0 : amountPaid).toFixed(2);
        const remainingText = (document.getElementById('sum_remaining').innerText || '0.00');
        Swal.fire({
            icon: 'question',
            title: 'Confirm Sale?',
            html: 'Grand Total: <strong>' + totalText + '</strong>' +
                  '<br/>Paid Amount: <strong class="text-success">' + paidText + '</strong>' +
                  '<br/>Remaining: <strong class="text-danger">' + remainingText + '</strong>' +
                  '<br/><br/>Proceed to place this order?',
            showCancelButton: true,
            confirmButtonText: 'Yes, place order',
            cancelButtonText: 'No, cancel'
        }).then((result) => {
            if(result.isConfirmed){
                submitSale(payload);
            }
        });
    });
})();
</script>
@stop