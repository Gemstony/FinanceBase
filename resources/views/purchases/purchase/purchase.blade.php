@extends('adminlte::page')

@section('title', 'New Purchase - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-shopping-bag"></i> New Purchase</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-shopping-bag"></i> Purchase</h1>
                    <p class="mb-0 text-light">Shop: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('purchases.subshops') }}" class="btn btn-outline-success bg-white text-success border-0">
                    <i class="fas fa-arrow-left"></i> Change Shop
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('purchases.subshops') }}">Choose Shop</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Purchase</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <input type="hidden" id="purchase_subshop_id" value="{{ $subshop->id }}">

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-lg" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-truck-loading text-success"></i> Supplier</h3>
                    @can('purchase_items')
                    <button class="btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#addSupplierModal"><i class="fas fa-user-plus"></i> Quick Add Supplier</button>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="supplier_select" class="mb-1"><i class="fas fa-search text-muted"></i> Search Supplier</label>
                        @can('purchase_items')
                        <select id="supplier_select" class="form-control" style="width:100%" placeholder="Type to search..."></select>
                        @endcan
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3 border-0 rounded-lg" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-boxes text-success"></i> Items To Purchase</h3>
                    @can('purchase_items')
                    <button type="button" class="btn btn-outline-success btn-sm" id="open_item_picker"><i class="fas fa-th-large"></i> Browse Items</button>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="cart_table">
                            <thead class="thead-light" style="background: linear-gradient(90deg, #E8F5E9, #F1F8E9); border-bottom: 1px solid #dcedc8;">
                                <tr>
                                    <th style="width: 15%">Item</th>
                                    <th style="width: 8%">Unit</th>
                                    <th style="width: 10%" class="text-right">Cost Price</th>
                                    <th style="width: 8%" class="text-center">Qty</th>
                                    <th style="width: 10%" class="text-center">Batch No.</th>
                                    <th style="width: 10%" class="text-center">Expire Date</th>
                                    <th style="width: 10%" class="text-right">Selling Price</th>
                                    <th style="width: 12%" class="text-left">VAT Type</th>
                                    <th style="width: 8%" class="text-right">VAT Amt</th>
                                    <th style="width: 10%" class="text-right">Subtotal</th>
                                    <th style="width: 9%" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="cart_body">
                                <tr class="empty-row">
                                    <td colspan="11" class="text-center text-muted py-4"><i class="fas fa-inbox"></i> No items added. Use the button above to add items.</td>
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
                    <h3 class="card-title mb-0"><i class="fas fa-file-invoice-dollar text-success"></i> Summary</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Subtotal</span>
                        <strong id="sum_subtotal" class="text-success">0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Total VAT</span>
                        <strong id="sum_vat" class="text-muted">0.00</strong>
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
                    
                    <div class="d-flex justify-content-between align-items-center h4 mt-3 p-2 rounded" style="background: linear-gradient(90deg, #E8F5E9, #F1F8E9);">
                        <span class="mb-0"><i class="fas fa-calculator text-success"></i> Grand Total</span>
                        <strong id="sum_grand" class="text-success h4 mb-0">0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Remaining</span>
                        <strong id="sum_remaining" class="text-warning">0.00</strong>
                    </div>
                    <hr/>
                    <div class="form-group">
                        <label><i class="fas fa-money-check-alt text-success"></i> Payment Method</label>
                        @can('purchase_items')
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
                    <div id="purchase_save_alert" class="alert d-none mt-2" role="alert"></div>
                    @can('purchase_items')
                    <button type="button" class="btn btn-success btn-block shadow-sm" id="complete_purchase_btn"><i class="fas fa-check"></i> Complete Purchase</button>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="quickAddSupplierForm" method="POST" action="{{ route('suppliers.store') }}">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
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
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" class="form-control" name="contact_person">
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="quick_supplier_is_active" name="is_active" value="1" checked>
                        <label class="custom-control-label" for="quick_supplier_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
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

    // Prefill from query params (Create PO shortcut)
    function getParam(name){ const url=new URL(window.location.href); return url.searchParams.get(name); }
    const prefillName = getParam('add_item_name');
    const prefillQtyRaw = getParam('add_qty');
    const prefillQty = Math.max(1, parseInt(prefillQtyRaw || '1', 10));
    if (prefillName) {
        // fetch item by name via API (search)
        $.ajax({
            url: '{{ route('api.purchases.items') }}', method: 'GET', dataType: 'json', data: { q: prefillName },
            headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            success: function(items){
                if (!items || !items.length) return;
                const i = items.find(x => (x.name || '').toLowerCase() === prefillName.toLowerCase()) || items[0];
                // avoid duplicates
                if (cart.some(r => r.id === i.id)) return;
                // get next batch number then add
                $.ajax({
                    url: '{{ route('api.purchases.next-batch-number') }}', method: 'GET', data: { item_id: i.id },
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    success: function(data){
                        const batchNumber = data.batch_number || ('BATCH-' + Date.now());
                        cart.push({
                            id: i.id,
                            name: i.name,
                            unit: i.unit,
                            cost_price: parseFloat(i.cost_price || 0),
                            selling_price: parseFloat(i.price || 0),
                            qty: prefillQty,
                            vatType: 'none',
                            batch_number: batchNumber,
                            expire_date: '',
                            available: parseInt(i.quantity || 0, 10),
                            qtyError: false
                        });
                        renderCart();
                        // Optional: notify
                        if (window.Swal) Swal.fire({ icon: 'success', title: 'Item added', text: i.name + ' x' + prefillQty, timer: 1500, showConfirmButton: false });
                    },
                    error: function(){
                        const batchNumber = 'BATCH-' + Date.now();
                        cart.push({ id: i.id, name: i.name, unit: i.unit, cost_price: parseFloat(i.cost_price||0), selling_price: parseFloat(i.price||0), qty: prefillQty, vatType: 'none', batch_number: batchNumber, expire_date: '', available: parseInt(i.quantity||0,10), qtyError: false });
                        renderCart();
                    }
                });
            }
        });
    }
    .item-card { 
        border: 1px solid #dee2e6; 
        border-radius: 0.375rem;
        transition: box-shadow 0.15s ease-in-out;
    }
    .item-card:hover { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); }
</style>
<script>
(function(){
    const subshopId = document.getElementById('purchase_subshop_id').value;
    const csrfToken = '{{ csrf_token() }}';

    // Select2: Suppliers
    $('#supplier_select').select2({
        placeholder: 'Search supplier by name, email, phone',
        allowClear: true,
        ajax: {
            url: '{{ route('api.purchases.suppliers') }}',
            dataType: 'json', delay: 250,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return {
                    results: data.map(s => ({id: s.id, text: s.name + (s.phone ? ' - ' + s.phone : '')}))
                };
            },
            cache: true
        },
        width: '100%'
    });

    // Quick add supplier (AJAX)
    $('#quickAddSupplierForm').on('submit', function(e){
        e.preventDefault();
        const form = $(this);
        $.ajax({
            url: form.attr('action'), method: 'POST', data: form.serialize(),
            success: function(res){
                if(res && res.supplier){
                    const s = res.supplier;
                    const option = new Option(s.name + (s.phone? ' - '+s.phone : ''), s.id, true, true);
                    $('#supplier_select').append(option).trigger('change');
                    $('#addSupplierModal').modal('hide');
                    form[0].reset();
                    Swal.fire({
                        icon: 'success',
                        title: 'Supplier added',
                        text: s.name + (s.phone? ' - '+s.phone : ''),
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: 'Unable to add supplier' });
                }
            },
            error: function(xhr){
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to add supplier';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });

    // Item Picker Modal
    const itemModalHtml = `
<div class="modal fade" id="itemPickerModal" tabindex="-1" role="dialog" aria-labelledby="itemPickerLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="itemPickerLabel"><i class="fas fa-th-large text-success"></i> Browse Items</h5>
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
                
                // Get price range for selling prices
                const sellingPrices = i.batches.map(b => parseFloat(b.selling_price)).filter(p => !isNaN(p));
                const minSellingPrice = Math.min(...sellingPrices);
                const maxSellingPrice = Math.max(...sellingPrices);
                const sellingPriceRange = minSellingPrice === maxSellingPrice ? minSellingPrice.toFixed(2) : `${minSellingPrice.toFixed(2)} - ${maxSellingPrice.toFixed(2)}`;
                
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
                        <div><strong>${batchCount}</strong> batch${batchCount > 1 ? 'es' : ''} • Selling: <strong>${sellingPriceRange}</strong></div>
                        ${earliestExpiry ? `<div>Earliest expiry: <strong>${earliestExpiry}</strong></div>` : ''}
                        ${expiringSoonCount > 0 ? `<div class="text-warning"><i class="fas fa-exclamation-triangle"></i> ${expiringSoonCount} batch${expiringSoonCount > 1 ? 'es' : ''} expiring soon</div>` : ''}
                        <div class="batch-numbers">Batches: <strong>${batchNumbers}</strong></div>
                    </div>`;
            }
            
            const inCart = idSet.has(i.id);
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
                      <button class="btn btn-sm ${inCart?'btn-secondary':'btn-success'} add-from-picker" data-id="${i.id}" ${inCart?'disabled':''} data-json='${JSON.stringify(i).replace(/'/g,"&#39;")}'>
                        ${inCart ? '<i class="fas fa-check"></i> Added' : '<i class="fas fa-cart-plus"></i> Add'}
                      </button>
                    </div>
                  </div>
                </div>`;
            grid.appendChild(col);
        });
    }

    function loadPickerItems(q){
        console.log('Loading items with query:', q);
        console.log('API URL:', '{{ route('api.purchases.items') }}');
        console.log('CSRF Token:', csrfToken);
        
        $.ajax({
            url: '{{ route('api.purchases.items') }}', 
            method: 'GET',
            dataType: 'json', 
            data: { q: q || '' },
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(data, status, xhr) {
                console.log('API Response:', data);
                console.log('Response Status:', status);
                console.log('XHR Status:', xhr.status);
                renderPickerItems(data); 
            },
            error: function(xhr, status, error) { 
                console.error('Error loading items:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                renderPickerItems([]); 
            },
            complete: function() {
                console.log('Picker grid content:', document.getElementById('picker_grid').innerHTML);
            }
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
        if(cart.some(r => r.id === i.id)){
            $btn.prop('disabled', true).removeClass('btn-success').addClass('btn-secondary').html('<i class="fas fa-check"></i> Added');
            return;
        }

        // Fetch next batch number
        $.ajax({
            url: '{{ route('api.purchases.next-batch-number') }}',
            method: 'GET',
            data: { item_id: i.id },
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            success: function(data) {
                const batchNumber = data.batch_number;
                cart.push({ 
                    id: i.id, 
                    name: i.name, 
                    unit: i.unit, 
                    cost_price: parseFloat(i.cost_price || 0), 
                    selling_price: parseFloat(i.price || 0), 
                    qty: 1, 
                    vatType: 'none', 
                    batch_number: batchNumber, 
                    expire_date: '', 
                    available: parseInt(i.quantity || 0, 10), 
                    qtyError: false 
                });
                renderCart();
                $btn.prop('disabled', true).removeClass('btn-success').addClass('btn-secondary').html('<i class="fas fa-check"></i> Added');
            },
            error: function() {
                // Fallback: use placeholder
                const batchNumber = 'BATCH-' + Date.now();
                cart.push({ 
                    id: i.id, 
                    name: i.name, 
                    unit: i.unit, 
                    cost_price: parseFloat(i.cost_price || 0), 
                    selling_price: parseFloat(i.price || 0), 
                    qty: 1, 
                    vatType: 'none', 
                    batch_number: batchNumber, 
                    expire_date: '', 
                    available: parseInt(i.quantity || 0, 10), 
                    qtyError: false 
                });
                renderCart();
                $btn.prop('disabled', true).removeClass('btn-success').addClass('btn-secondary').html('<i class="fas fa-check"></i> Added');
            }
        });
    });

    const VAT_RATE = 18; // percent
    let cart = [];

    function computeLine(row){
        const qty = row.qty;
        const price = row.cost_price;
        const base = price * qty;
        let vatAmt = 0;
        if(row.vatType === 'exclusive'){
            vatAmt = base * (VAT_RATE/100);
        } else {
            vatAmt = 0;
        }
        const lineTotal = base + vatAmt;
        return { base, vatAmt, lineTotal };
    }

    function renderCart(){
        const tbody = document.getElementById('cart_body');
        tbody.innerHTML = '';
        if(cart.length === 0){
            tbody.innerHTML = '<tr class="empty-row"><td colspan="8" class="text-center text-muted py-4">No items added. Use the selector above to add items.</td></tr>';
            updateSummary();
            return;
        }

        cart.forEach((row, idx) => {
            const { vatAmt, lineTotal } = computeLine(row);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${row.name}</td>
                <td>${row.unit || ''}</td>
                <td class="text-right">
                    <input type="number" class="form-control form-control-sm text-right cost-price-input" data-idx="${idx}" value="${row.cost_price}" step="0.01" min="0" />
                </td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm text-center qty-input ${row.qtyError?'is-invalid':''}" data-idx="${idx}" value="${row.qty}" step="1" min="1" />
                </td>
                <td class="text-center">
                    <input type="text" class="form-control form-control-sm batch-number-input" data-idx="${idx}" value="${row.batch_number || ''}" placeholder="Batch-001" readonly />
                </td>
                <td class="text-center">
                    <input type="date" class="form-control form-control-sm expire-date-input" data-idx="${idx}" value="${row.expire_date || ''}" />
                </td>
                <td class="text-right">
                    <input type="number" class="form-control form-control-sm text-right selling-price-input" data-idx="${idx}" value="${row.selling_price || 0}" step="0.01" min="0" />
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
    }

    document.getElementById('cart_body').addEventListener('input', function(e){
        if(e.target.classList.contains('qty-input')){
            const idx = e.target.getAttribute('data-idx');
            let q = parseInt(e.target.value || '1', 10);
            if(isNaN(q) || q < 1) q = 1;
            cart[idx].qty = q;
            cart[idx].qtyError = false;
        }
        if(e.target.classList.contains('cost-price-input')){
            const idx = e.target.getAttribute('data-idx');
            let p = parseFloat(e.target.value || '0');
            if(isNaN(p) || p < 0) p = 0;
            cart[idx].cost_price = p;
        }
        if(e.target.classList.contains('batch-number-input')){
            const idx = e.target.getAttribute('data-idx');
            cart[idx].batch_number = e.target.value;
        }
        if(e.target.classList.contains('expire-date-input')){
            const idx = e.target.getAttribute('data-idx');
            cart[idx].expire_date = e.target.value;
        }
        if(e.target.classList.contains('selling-price-input')){
            const idx = e.target.getAttribute('data-idx');
            let sp = parseFloat(e.target.value || '0');
            if(isNaN(sp) || sp < 0) sp = 0;
            cart[idx].selling_price = sp;
        }
        const tr = e.target.closest('tr');
        const idx = e.target.getAttribute('data-idx');
        if(idx !== null){
            const vals = computeLine(cart[idx]);
            if (tr){
                const vatCell = tr.querySelector('.vat-amt');
                const subCell = tr.querySelector('.subtotal');
                if (vatCell) vatCell.textContent = vals.vatAmt.toFixed(2);
                if (subCell) subCell.textContent = vals.lineTotal.toFixed(2);
            }
        }
        updateSummary();
    });

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

    ['discount_percent','discount_cash','amount_paid'].forEach(id => {
        const el = document.getElementById(id);
        if(el){ el.addEventListener('input', function(e){
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
        }); }
    });

    function updateSummary(){
        let sumSubtotal = 0;
        let sumVat = 0;
        cart.forEach(r => {
            const { base, vatAmt, lineTotal } = computeLine(r);
            sumVat += vatAmt;
            sumSubtotal += lineTotal;
        });
        let discountPercent = parseFloat(document.getElementById('discount_percent').value || '0');
        let discountCash = parseFloat(document.getElementById('discount_cash').value || '0');
        if(discountPercent < 0) discountPercent = 0; if(discountPercent > 100) discountPercent = 100;

        const gross = sumSubtotal;
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

    function showAlert(type, message){
        const el = document.getElementById('purchase_save_alert');
        el.className = 'alert alert-' + type;
        el.textContent = message;
        el.classList.remove('d-none');
        setTimeout(()=>{ el.classList.add('d-none'); }, 5000);
    }

    function submitPurchase(payload){
        const $btn = $('#complete_purchase_btn');
        $btn.prop('disabled', true);
             // Show loading while processing the order
        Swal.fire({
            title: 'Processing purchase...',
            html: 'Please wait while we place your order.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: '{{ route('purchases.store') }}',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            data: JSON.stringify(payload),
            processData: false,
            contentType: 'application/json',
            success: function(res){
                Swal.fire({
                    icon: 'success',
                    title: 'Purchase recorded',
                    html: 'Order No: <strong>' + res.order_no + '</strong>',
                    confirmButtonText: 'OK'
                }).then(() => {
                    cart = [];
                    renderCart();
                    $('#amount_paid').val('0');
                });
            },
            error: function(xhr){
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to record purchase';
                showAlert('danger', msg);
            },
            complete: function(){
                $btn.prop('disabled', false);
            }
        });
    }

    $('#complete_purchase_btn').on('click', function(){
        if(cart.length === 0){ showAlert('warning','Please add items to cart'); return; }
        const supplierId = ($('#supplier_select').val() || null);
        if(!supplierId){
            showAlert('warning','Please select a supplier.');
            try { $('#supplier_select').select2('open'); } catch(e) { $('#supplier_select').focus(); }
            return;
        }
        const paymentMethod = $('#payment_method').val();
        const amountPaid = parseFloat($('#amount_paid').val() || '0');
        const discountPercent = parseFloat($('#discount_percent').val() || '0');
        const discountCash = parseFloat($('#discount_cash').val() || '0');

        if(amountPaid > 0 && (!paymentMethod || paymentMethod === '')){
            showAlert('warning', 'Please select a payment method when entering Amount Paid.');
            $('#payment_method').focus();
            return;
        }

        const payload = {
            subshop_id: subshopId,
            supplier_id: supplierId,
            payment_method: paymentMethod,
            amount_paid: amountPaid,
            discount_percent: discountPercent,
            discount_cash: discountCash,
            items: cart.map(r => ({ id: r.id, name: r.name, unit: r.unit, cost_price: r.cost_price, selling_price: r.selling_price, batch_number: r.batch_number, expire_date: r.expire_date, qty: r.qty, vatType: r.vatType }))
        };

        const totalText = document.getElementById('sum_grand').innerText || '0.00';
        const paidText = (isNaN(amountPaid) ? 0 : amountPaid).toFixed(2);
        const remainingText = (document.getElementById('sum_remaining').innerText || '0.00');
        Swal.fire({
            icon: 'question',
            title: 'Confirm Purchase?',
            html: 'Grand Total: <strong>' + totalText + '</strong>' +
                  '<br/>Paid Amount: <strong class="text-success">' + paidText + '</strong>' +
                  '<br/>Remaining: <strong class="text-danger">' + remainingText + '</strong>' +
                  '<br/><br/>Proceed to record this purchase?',
            showCancelButton: true,
            confirmButtonText: 'Yes, record',
            cancelButtonText: 'No, cancel'
        }).then((result) => {
            if(result.isConfirmed){
                submitPurchase(payload);
            }
        });
    });
})();
</script>
@stop