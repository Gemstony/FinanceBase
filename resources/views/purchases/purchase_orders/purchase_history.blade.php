@extends('adminlte::page')

@section('title', 'Purchase Orders - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-file-invoice-dollar"></i> Purchases History</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-file-invoice-dollar"></i> Purchases</h1>
                <div class="small text-light-50">Shop: {{ $subshop->name }}</div>
            </div>
            <a href="{{ route('purchases.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-shopping-basket"></i> New Purchase</a>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-navy elevation-1"><i class="fas fa-file-invoice"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Purchases</span>
                            <span class="info-box-number">{{ number_format($summary['count'] ?? 0) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-indigo elevation-1"><i class="fas fa-coins"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Grand Total</span>
                            <span class="info-box-number">{{ number_format($summary['grand_total'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-teal elevation-1"><i class="fas fa-hand-holding-usd"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Paid</span>
                            <span class="info-box-number">{{ number_format($summary['paid_total'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-orange elevation-1"><i class="fas fa-wallet"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Remaining</span>
                            <span class="info-box-number">{{ number_format($summary['remaining_total'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

<!-- Purchase Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1" role="dialog" aria-labelledby="returnLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="returnLabel"><i class="fas fa-undo-alt text-warning"></i> Purchase Return</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="returnForm">
          <input type="hidden" id="ret_order_id" />
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Order No</label>
              <input type="text" id="ret_order_no" class="form-control" readonly>
            </div>
            <div class="form-group col-md-4">
              <label>Date</label>
              <input type="date" id="ret_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="form-group col-md-4">
              <label>Refund Method</label>
              <select id="ret_method" class="form-control">
                <option value="">Select method...</option>
                @foreach(($banks ?? []) as $b)
                  <option value="{{ $b->name }}">{{ $b->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Reason</label>
            <input type="text" id="ret_reason" class="form-control" placeholder="Optional reason">
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Reference</label>
              <input type="text" id="ret_ref" class="form-control" placeholder="Ref/Receipt no (optional)">
            </div>
            <div class="form-group col-md-6">
              <label>Notes</label>
              <input type="text" id="ret_notes" class="form-control" placeholder="Notes (optional)">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead>
                <tr>
                  <th>Item</th>
                  <th class="text-right">Purchased</th>
                  <th class="text-right">Returned</th>
                  <th class="text-right">Available</th>
                  <th class="text-right">Unit Price</th>
                  <th class="text-right" style="width:120px;">Return Qty</th>
                </tr>
              </thead>
              <tbody id="ret_items_tbody"></tbody>
            </table>
          </div>
          <div id="ret_error" class="alert alert-danger d-none"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="submit_return_btn">Process Return</button>
      </div>
    </div>
  </div>
</div>

            <form method="get" action="{{ route('purchase_orders.index') }}" class="mb-3">
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}" />
                <div class="bg-light p-2 rounded border">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Search</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Order No / Payment method">
                            </div>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date From</label>
                            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date To</label>
                            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Min Total</label>
                            <input type="number" step="0.01" name="min_total" value="{{ $minTotal }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Max Total</label>
                            <input type="number" step="0.01" name="max_total" value="{{ $maxTotal }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="paid" {{ ($status==='paid')?'selected':'' }}>Paid</option>
                                <option value="partial" {{ ($status==='partial')?'selected':'' }}>Partial</option>
                                <option value="pending" {{ ($status==='pending')?'selected':'' }}>Pending</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Sort</label>
                            <select name="sort" class="form-control">
                                <option value="date_desc" {{ ($sort==='date_desc')?'selected':'' }}>Date: New → Old</option>
                                <option value="date_asc" {{ ($sort==='date_asc')?'selected':'' }}>Date: Old → New</option>
                                <option value="grand_desc" {{ ($sort==='grand_desc')?'selected':'' }}>Grand: High → Low</option>
                                <option value="grand_asc" {{ ($sort==='grand_asc')?'selected':'' }}>Grand: Low → High</option>
                                <option value="remain_desc" {{ ($sort==='remain_desc')?'selected':'' }}>Remaining: High → Low</option>
                                <option value="remain_asc" {{ ($sort==='remain_asc')?'selected':'' }}>Remaining: Low → High</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
                            <a class="btn btn-light border" href="{{ route('purchase_orders.index', ['subshop_id'=>$subshop->id]) }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted small">Filtered results: {{ number_format($orders->total()) }}</div>
                @can('export_purchase_history')
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="{{ route('purchase_orders.export', ['format' => 'csv'] + request()->query()) }}">
                            <i class="fas fa-file-csv mr-1 text-success"></i> CSV
                        </a>
                        <a class="dropdown-item" href="{{ route('purchase_orders.export', ['format' => 'excel'] + request()->query()) }}">
                            <i class="fas fa-file-excel mr-1 text-success"></i> Excel
                        </a>
                        <a class="dropdown-item" href="{{ route('purchase_orders.export', ['format' => 'pdf'] + request()->query()) }}">
                            <i class="fas fa-file-pdf mr-1 text-danger"></i> PDF
                        </a>
                    </div>
                </div>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="PurchasesTable">
                    <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                        <tr>
                            <th><i class="fas fa-hashtag mr-1"></i> Order No</th>
                            <th><i class="fas fa-calendar-alt mr-1"></i> Date</th>
                            <th><i class="fas fa-truck mr-1"></i> Supplier</th>
                            <th class="text-right"><i class="fas fa-calculator mr-1"></i> Subtotal</th>
                            <th class="text-right"><i class="fas fa-percentage mr-1"></i> VAT</th>
                            <th class="text-right"><i class="fas fa-tags mr-1"></i> Discount</th>
                            <th class="text-right"><i class="fas fa-coins mr-1"></i> Grand</th>
                            <th class="text-right"><i class="fas fa-hand-holding-usd mr-1"></i> Paid</th>
                            <th class="text-right"><i class="fas fa-wallet mr-1"></i> Remaining</th>
                            <th><i class="fas fa-info-circle mr-1"></i> Status</th>
                            <th><i class="fas fa-user-check mr-1"></i> Created By</th>
                            <th class="text-center"><i class="fas fa-ellipsis-h mr-1"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        @php
                            $paid = (float)($paidMap[$order->id] ?? 0);
                            $remaining = max(0, (float)$order->grand_total - $paid);
                            $status = $remaining <= 0 ? 'paid' : ($paid <= 0 ? 'pending' : 'partial');
                        @endphp
                        <tr>
                            <td><span class="badge badge-primary">{{ $order->order_no }}</span></td>
                            <td>{{ $order->created_at? $order->created_at->format('d M Y, H:i') : '' }}</td>
                            <td>{{ optional($order->supplier)->name ?? '-' }}</td>
                            <td class="text-right">Tsh {{ number_format($order->subtotal, 2) }}</td>
                            <td class="text-right">Tsh {{ number_format($order->vat_total, 2) }}</td>
                            <td class="text-right">Tsh {{ number_format($order->discount_total, 2) }}</td>
                            <td class="text-right"><strong>Tsh {{ number_format($order->grand_total, 2) }}</strong></td>
                            <td class="text-right text-success">Tsh {{ number_format($paid, 2) }}</td>
                            <td class="text-right text-danger">Tsh {{ number_format($remaining, 2) }}</td>
                            <td>
                                @if($status==='paid')
                                    <span class="badge badge-success">Paid</span>
                                @elseif($status==='pending')
                                    <span class="badge badge-danger">Pending</span>
                                @else
                                    <span class="badge badge-warning">Partial</span>
                                @endif
                            </td>
                            <td>{{ optional($order->creator)->name ?? '-' }}</td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary view-order" data-id="{{ $order->id }}"><i class="fas fa-eye"></i> View</button>
                                    @can('pay_purchases')
                                    <button class="btn btn-sm btn-success pay-order" data-id="{{ $order->id }}" data-remaining="{{ number_format($remaining, 2, '.', '') }}" data-order-no="{{ $order->order_no }}"><i class="fas fa-cash-register"></i> Pay</button>
                                    @endcan
                                    @can('purchases_payment_history')
                                    <button class="btn btn-sm btn-outline-secondary history-order" data-id="{{ $order->id }}" data-order-no="{{ $order->order_no }}"><i class="fas fa-history"></i> History</button>
                                    @endcan
                                    @can('return_purchases')
                                    <button class="btn btn-sm btn-warning return-order" data-id="{{ $order->id }}" data-order-no="{{ $order->order_no }}"><i class="fas fa-undo-alt"></i> Return</button>
                                    @endcan
                                    @can('print_purchases_receipt_invoice')
                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('purchase_orders.print', $order->id) }}" target="_blank"><i class="fas fa-file-invoice"></i> View</a>
                                    <button class="btn btn-sm btn-dark escpos-purchase" data-id="{{ $order->id }}"><i class="fas fa-print"></i> ESC/POS</button>
                                    @endcan
                                    @can('delete_purchases')
                                    <button class="btn btn-sm btn-outline-danger delete-order" data-id="{{ $order->id }}" data-order-no="{{ $order->order_no }}"><i class="fas fa-trash"></i> Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted py-5"><i class="fas fa-inbox"></i> No purchase orders found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Payments History Modal -->
<div class="modal fade" id="paymentsModal" tabindex="-1" role="dialog" aria-labelledby="paymentsLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentsLabel"><i class="fas fa-history text-secondary"></i> Payments History</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between flex-wrap mb-3">
            <div>
                <div class="small text-muted">Order No</div>
                <div id="ph_order_no" class="font-weight-bold">-</div>
            </div>
            <div>
                <div class="small text-muted">Total Paid</div>
                <div id="ph_paid" class="font-weight-bold text-success">0.00</div>
            </div>
            <div>
                <div class="small text-muted">Remaining</div>
                <div id="ph_remaining" class="font-weight-bold text-danger">0.00</div>
            </div>
            <div>
                <div class="small text-muted">Status</div>
                <div id="ph_status"><span class="badge badge-secondary">-</span></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Notes</th>
                        <th>Processed By</th>
                    </tr>
                </thead>
                <tbody id="payments_tbody"></tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- View Order Modal -->
<div class="modal fade" id="orderViewModal" tabindex="-1" role="dialog" aria-labelledby="orderViewLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="orderViewLabel"><i class="fas fa-receipt text-info"></i> Order Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="order_meta" class="mb-3"></div>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Unit</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">VAT</th>
                        <th class="text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody id="order_items"></tbody>
            </table>
        </div>
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="border border-success rounded p-2 text-center">
                    <div class="small text-muted">Paid</div>
                    <div class="h5 mb-0" id="ov_paid">0.00</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border border-info rounded p-2 text-center">
                    <div class="small text-muted">Remaining</div>
                    <div class="h5 mb-0" id="ov_remaining">0.00</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border border-warning rounded p-2 text-center">
                    <div class="small text-muted">Status</div>
                    <div class="h5 mb-0" id="ov_status"><span class="badge badge-secondary">-</span></div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end">
            <div>
                <div class="d-flex justify-content-between"><span class="mr-3">Subtotal:</span> <strong id="ov_subtotal">0.00</strong></div>
                <div class="d-flex justify-content-between"><span class="mr-3">VAT:</span> <strong id="ov_vat">0.00</strong></div>
                <div class="d-flex justify-content-between"><span class="mr-3">Discount:</span> <strong id="ov_discount">0.00</strong></div>
                <div class="d-flex justify-content-between h5"><span class="mr-3">Grand:</span> <strong id="ov_grand" class="text-primary">0.00</strong></div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentLabel"><i class="fas fa-cash-register text-success"></i> Add Payment</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="paymentForm">
          <input type="hidden" id="pay_order_id" />
          <div class="form-group">
            <label>Order No</label>
            <input type="text" id="pay_order_no" class="form-control" readonly>
          </div>
          <div class="form-row">
            <div class="form-group col-6">
              <label>Amount</label>
              <input type="number" min="0.01" step="0.01" id="pay_amount" class="form-control" required>
            </div>
            <div class="form-group col-6">
              <label>Date</label>
              <input type="date" id="pay_date" class="form-control" required value="{{ date('Y-m-d') }}">
            </div>
          </div>
          <div class="form-group">
            <label>Reference</label>
            <input type="text" id="pay_ref" class="form-control" placeholder="Receipt/Ref no (optional)">
          </div>
          <div class="form-group">
            <label>Payment Method</label>
            <select id="pay_method" class="form-control" required>
              <option value="">Select method...</option>
              @foreach(($banks ?? []) as $b)
                <option value="{{ $b->name }}">{{ $b->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea id="pay_notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
          </div>
        </form>
        <div id="pay_error" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="submit_payment_btn">Record Payment</button>
      </div>
    </div>
  </div>
  </div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
    .small-box .inner h3{ font-size: 1.6rem; line-height: 1.2; word-break: break-word; white-space: normal; }
    .small-box .inner p{ margin-bottom: 0; }
    .small-box{ position: relative; overflow: hidden; }
    .small-box .icon{ position:absolute; right:10px; top:8px; font-size:36px; opacity:.35; z-index:0; line-height:1; }
    .small-box .inner{ position: relative; z-index:1; }
    /* Improve info-box responsiveness */
    .info-box { min-height: 70px; box-shadow: 0 2px 8px rgba(0,0,0,.04); border-radius: .4rem; }
    .info-box .info-box-icon { width: 58px; font-size: 26px; }
    .info-box .info-box-content { padding: 6px 8px; }
    .info-box .info-box-text { font-size: .8rem; color: #6b7280; line-height: 1.1; }
    .info-box .info-box-number { font-weight: 700; font-size: 1.05rem; line-height: 1.2; }
    @media (max-width: 992px){
        .info-box { min-height: 66px; }
        .info-box .info-box-icon { width: 52px; font-size: 24px; }
        .info-box .info-box-number { font-size: 1rem; }
    }
    @media (max-width: 768px){
        .info-box { min-height: 62px; }
        .info-box .info-box-icon { width: 48px; font-size: 22px; }
        .info-box .info-box-text { font-size: .78rem; }
        .info-box .info-box-number { font-size: .95rem; }
    }
    @media (max-width: 576px){
        .info-box { min-height: 58px; margin-bottom: .6rem; }
        .info-box .info-box-icon { width: 44px; font-size: 20px; }
        .info-box .info-box-content { padding: 4px 6px; }
        .info-box .info-box-text { font-size: .75rem; }
        .info-box .info-box-number { font-size: .9rem; }
    }
    @media (max-width: 360px){
        .info-box { min-height: 54px; }
        .info-box .info-box-icon { width: 40px; font-size: 18px; }
        .info-box .info-box-text { font-size: .72rem; }
        .info-box .info-box-number { font-size: .86rem; }
    }
    @media (max-width: 992px){ .small-box .inner h3{ font-size: 1.4rem; } }
    @media (max-width: 768px){ .small-box .inner h3{ font-size: 1.2rem; } }
    @media (max-width: 576px){ .small-box .inner h3{ font-size: 1.05rem; } .small-box .inner p{ font-size: .8rem; } .small-box .icon{ font-size:28px; right:8px; top:8px; } }
    @media (max-width: 360px){ .small-box .inner h3{ font-size: .95rem; } .small-box .icon{ font-size:24px; right:6px; top:6px; } }
    </style>
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Global config
window.DUKA = Object.assign({}, window.DUKA || {}, {
    apiPurchasesBase: @json(url('/api/purchase-orders')),
    apiPrintStatus: @json(url('/api/print-jobs/status')),
    csrf: @json(csrf_token())
});

// ESC/POS printing handler for purchases
document.addEventListener('click', function(e){
    const btn = e.target.closest('.escpos-purchase');
    if(!btn) return;
    e.preventDefault();
    const id = btn.getAttribute('data-id');
    const base = (window.DUKA && window.DUKA.apiPurchasesBase) ? window.DUKA.apiPurchasesBase : '';
    const apiUrl = base + '/' + id + '/print';
    if (!id || !apiUrl) return;

    if (window.Swal) {
        Swal.fire({
            title: 'Print purchase invoice',
            text: 'Choose how you want to print',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Real Printer',
            denyButtonText: 'Dummy (Preview)',
        }).then(async (result) => {
            if (result.isConfirmed || result.isDenied) {
                const dummy = result.isDenied ? 1 : 0;
                try {
                    Swal.fire({ title: 'Sending to printer...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    const res = await fetch(apiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (window.DUKA && window.DUKA.csrf) ? window.DUKA.csrf : '' }, body: JSON.stringify({ dummy }) });
                    const data = await res.json();
                    if (!data.ok) throw new Error(data.error || 'Failed');
                    if (data.dummy && data.data) {
                        const raw = atob(data.data);
                        const blob = new Blob([new Uint8Array(Array.from(raw, c => c.charCodeAt(0)))], { type: 'application/octet-stream' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a'); a.href = url; a.download = 'purchase-order-'+id+'-escpos.bin'; document.body.appendChild(a); a.click(); a.remove();
                        Swal.fire({ icon:'success', title:'Dummy output generated', timer: 1600, showConfirmButton:false });
                    } else {
                        const jobId = data.job_id;
                        if (!jobId) { Swal.fire({ icon:'success', title:'Print job sent', timer: 1500, showConfirmButton:false }); return; }
                        const statusUrl = (window.DUKA && window.DUKA.apiPrintStatus) ? window.DUKA.apiPrintStatus : '';
                        let attempts = 0; const maxAttempts = 20;
                        Swal.fire({ title:'Printing...', html:'<span class=\"text-muted\">Queued</span>', allowOutsideClick:false, allowEscapeKey:false, didOpen: () => Swal.showLoading() });
                        const poll = async () => {
                            attempts++;
                            try {
                                const r = await fetch(statusUrl, { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': (window.DUKA && window.DUKA.csrf) ? window.DUKA.csrf : '' }, body: JSON.stringify({ job_id: jobId }) });
                                const j = await r.json().catch(()=>({ ok:false }));
                                if (!j.ok) throw new Error('Status check failed');
                                const st = (j.status||'').toLowerCase();
                                if (st==='success'){ Swal.fire({ icon:'success', title:'Printed', timer:1200, showConfirmButton:false }); return; }
                                if (st==='failed'){ Swal.fire({ icon:'error', title:'Print failed', text: j.message || 'Unknown error' }); return; }
                                if (attempts < maxAttempts) { setTimeout(poll, 2000); Swal.getHtmlContainer().innerHTML = `<span class=\"text-muted\">${st==='running'?'Running':'Queued'}...</span>`; }
                                else { Swal.fire({ icon:'info', title:'Still processing', text:'We will continue printing in the background.' }); }
                            } catch (e) {
                                if (attempts < maxAttempts) { setTimeout(poll, 2500); }
                                else { Swal.fire({ icon:'info', title:'Processing', text:'Job is still running. You can close this dialog.' }); }
                            }
                        };
                        poll();
                    }
                } catch (err) {
                    Swal.fire({ icon:'error', title:'Failed', text: err.message || 'Error' });
                }
            }
        });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const flashSuccess = @json(session('success'));
    const flashError = @json(session('error'));
    if (flashSuccess) { Swal.fire({ icon:'success', title: flashSuccess, timer: 1800, timerProgressBar: true, showConfirmButton:false }); }
    if (flashError) { Swal.fire({ icon:'error', title: flashError, timer: 2200, timerProgressBar: true, showConfirmButton:true }); }
});

$(function () {
    window.PO_DT = $('#PurchasesTable').DataTable({
        "order": [],
        "pageLength": 10,
        "language": {
            "search": "Search purchases:",
            "lengthMenu": "Show _MENU_ purchases per page",
            "zeroRecords": "No purchases found",
            "info": "Showing _START_ to _END_ of _TOTAL_ purchases",
            "infoEmpty": "No purchases available",
            "infoFiltered": "(filtered from _MAX_ total purchases)"
        }
    });
});

(function(){
    function fmt(n){ return parseFloat(n||0).toFixed(2); }
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.view-order');
        if(!btn) return;
        const id = btn.getAttribute('data-id');
        fetch(`{{ url('/api/purchase-orders') }}/${id}`, { headers: { 'Accept': 'application/json' }})
            .then(r => r.json())
            .then(data => {
                const o = data.order || {};
                const items = data.items || [];
                document.getElementById('order_meta').innerHTML = `
                    <div><strong>Order No:</strong> ${o.order_no} &nbsp; <strong>Date:</strong> ${o.created_at ?? ''}</div>
                    <div><strong>Supplier:</strong> ${(o.supplier && o.supplier.name) ? o.supplier.name : '-'} &nbsp; <strong>Payment:</strong> ${(o.payment_method||'').toUpperCase()}</div>
                `;
                const tbody = document.getElementById('order_items');
                if (tbody) {
                    tbody.innerHTML = '';
                    items.forEach(it => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${it.item_name}</td>
                            <td>${it.unit || ''}</td>
                            <td class="text-right">${fmt(it.unit_price)}</td>
                            <td class="text-right">${it.quantity}</td>
                            <td class="text-right">${fmt(it.vat_amount)}</td>
                            <td class="text-right">${fmt(it.line_total)}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
                document.getElementById('ov_subtotal').innerText = fmt(o.subtotal);
                document.getElementById('ov_vat').innerText = fmt(o.vat_total);
                document.getElementById('ov_discount').innerText = fmt(o.discount_total);
                document.getElementById('ov_grand').innerText = fmt(o.grand_total);
                document.getElementById('ov_paid').innerText = fmt(data.paid);
                document.getElementById('ov_remaining').innerText = fmt(data.remaining);
                const stBadge = data.status==='paid' ? '<span class="badge badge-success">Paid</span>' : (data.status==='pending' ? '<span class="badge badge-danger">Pending</span>' : '<span class="badge badge-warning">Partial</span>');
                document.getElementById('ov_status').innerHTML = stBadge;
                $('#orderViewModal').modal('show');
            });
    });

    // Open Purchase Return modal
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.return-order');
        if(!btn) return;
        const id = btn.getAttribute('data-id');
        const orderNo = btn.getAttribute('data-order-no') || '';
        document.getElementById('ret_order_id').value = id;
        document.getElementById('ret_order_no').value = orderNo;
        document.getElementById('ret_date').value = '{{ date('Y-m-d') }}';
        document.getElementById('ret_reason').value = '';
        document.getElementById('ret_ref').value = '';
        document.getElementById('ret_notes').value = '';
        const err = document.getElementById('ret_error'); err.classList.add('d-none'); err.textContent='';

        fetch(`{{ url('/api/purchase-orders') }}/${id}/returns`, { headers: { 'Accept': 'application/json' }})
            .then(async r => {
                if(!r.ok){
                    let msg = 'Failed to load return items';
                    try { const j = await r.json(); if(j && j.message) msg = j.message; } catch(e) {}
                    throw new Error(msg);
                }
                return r.json();
            })
            .then(data => {
                const tbody = document.getElementById('ret_items_tbody');
                tbody.innerHTML = '';
                const list = data.items || [];
                if(list.length === 0){
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No returnable items found for this order.</td></tr>';
                }
                list.forEach(it => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${it.item_name}</td>
                        <td class="text-right">${it.quantity}</td>
                        <td class="text-right">${it.already_returned}</td>
                        <td class="text-right">${it.available}</td>
                        <td class="text-right">${parseFloat(it.unit_price||0).toFixed(2)}</td>
                        <td class="text-right">
                            <input type="number" class="form-control form-control-sm ret-qty" min="0" max="${it.available}" value="0"
                                   data-poi-id="${it.purchase_order_item_id}" style="text-align:right;">
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
                $('#returnModal').modal('show');
            })
            .catch(err => {
                const tbody = document.getElementById('ret_items_tbody');
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">'+ (err && err.message ? err.message : 'Failed to load items') +'</td></tr>';
                $('#returnModal').modal('show');
            });
    });

    // Submit purchase return (with confirmation)
    document.getElementById('submit_return_btn').addEventListener('click', function(){
        const id = document.getElementById('ret_order_id').value;
        const date = document.getElementById('ret_date').value;
        const reason = document.getElementById('ret_reason').value;
        const ref = document.getElementById('ret_ref').value;
        const notes = document.getElementById('ret_notes').value;
        const method = document.getElementById('ret_method').value;
        const qtyInputs = Array.from(document.querySelectorAll('#ret_items_tbody .ret-qty'));
        const items = qtyInputs.map(inp => ({ purchase_order_item_id: parseInt(inp.getAttribute('data-poi-id')), quantity: parseInt(inp.value||'0') }))
            .filter(x => x.quantity && x.quantity > 0);

        const err = document.getElementById('ret_error'); err.classList.add('d-none'); err.textContent='';
        if(!id || !date || items.length === 0){
            err.classList.remove('d-none'); err.textContent = 'Select at least one item and fill required fields.'; return;
        }

        const proceed = () => {
            // Show loading while processing purchase return
            if (window.Swal) {
                Swal.fire({
                    title: 'Processing return...',
                    html: 'Please wait.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => { Swal.showLoading(); }
                });
            }
            fetch(`{{ url('/api/purchase-orders') }}/${id}/returns`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ items, reason, transaction_date: date, payment_method: method, reference_number: ref, notes })
            }).then(async r => {
                if(!r.ok){ const j = await r.json().catch(()=>({message:'Failed'})); throw new Error(j.message || 'Failed'); }
                return r.json();
            }).then(data => {
                const rowBtn = document.querySelector(`.pay-order[data-id="${id}"]`);
                if(rowBtn){
                    const tr = rowBtn.closest('tr');
                    const paidCell = tr.querySelectorAll('td')[7];
                    const remainingCell = tr.querySelectorAll('td')[8];
                    const statusCell = tr.querySelectorAll('td')[9];
                    if(paidCell) paidCell.textContent = `Tsh ${parseFloat(data.paid||0).toFixed(2)}`;
                    if(remainingCell) remainingCell.textContent = `Tsh ${parseFloat(data.remaining||0).toFixed(2)}`;
                    if(statusCell){
                        statusCell.innerHTML = data.status==='paid' ? '<span class="badge badge-success">Paid</span>' : (data.status==='pending' ? '<span class="badge badge-danger">Pending</span>' : '<span class="badge badge-warning">Partial</span>');
                    }
                    rowBtn.setAttribute('data-remaining', String(data.remaining));
                }
                $('#returnModal').modal('hide');
                if(window.Swal){ Swal.fire({ icon:'success', title:'Return processed', timer:1500, timerProgressBar: true, showConfirmButton:false }); }
            }).catch(errMsg => {
                try { if(window.Swal){ Swal.close(); } } catch(e){}
                err.classList.remove('d-none');
                err.textContent = errMsg.message || String(errMsg);
            });
        };

        const totalQty = items.reduce((s,x)=>s + (x.quantity||0), 0);
        const confirmMsg = `Process purchase return for ${items.length} item(s), total quantity ${totalQty}?`;
        if (window.Swal) {
            Swal.fire({
                title: 'Confirm Return',
                text: confirmMsg,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, process',
                cancelButtonText: 'Cancel'
            }).then(res => { if(res.isConfirmed){ proceed(); } });
        } else {
            if (window.confirm(confirmMsg)) { proceed(); }
        }
    });
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.pay-order');
        if(!btn) return;
        const id = btn.getAttribute('data-id');
        const remaining = parseFloat(btn.getAttribute('data-remaining') || '0');
        const orderNo = btn.getAttribute('data-order-no') || '';
        document.getElementById('pay_order_id').value = id;
        document.getElementById('pay_order_no').value = orderNo;
        document.getElementById('pay_amount').value = fmt(remaining);
        document.getElementById('pay_date').value = '{{ date('Y-m-d') }}';
        document.getElementById('pay_ref').value = '';
        document.getElementById('pay_notes').value = '';
        const err = document.getElementById('pay_error'); err.classList.add('d-none'); err.textContent = '';
        $('#paymentModal').modal('show');
    });
//handle payment form
    document.getElementById('submit_payment_btn').addEventListener('click', function(){
        const id = document.getElementById('pay_order_id').value;
        const amount = parseFloat(document.getElementById('pay_amount').value || '0');
        const date = document.getElementById('pay_date').value;
        const ref = document.getElementById('pay_ref').value;
        const method = document.getElementById('pay_method').value;
        const notes = document.getElementById('pay_notes').value;
        const err = document.getElementById('pay_error'); err.classList.add('d-none'); err.textContent='';

        if(!id || !amount || amount <= 0 || !date || !method){
            if(!method){
                err.classList.remove('d-none');
                err.textContent = 'Please select a payment method before recording the payment.';
                if (window.Swal) { Swal.fire({ icon:'warning', title:'Select payment method', timer:1500, showConfirmButton:false }); }
                document.getElementById('pay_method').focus();
            } else {
                err.classList.remove('d-none');
                err.textContent = 'Fill all required fields with valid values.';
            }
            return;
        }
        // Show loading while recording payment
        if (window.Swal) {
            Swal.fire({
                title: 'Recording payment...',
                html: 'Please wait.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });
        }
        fetch(`{{ url('/api/purchase-orders') }}/${id}/payments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ amount: amount, transaction_date: date, reference_number: ref, payment_method: method, notes: notes })
        }).then(async r => {
            if(!r.ok){ const j = await r.json().catch(()=>({message:'Failed'})); throw new Error(j.message || 'Failed'); }
            return r.json();
        }).then(data => {
            const rowBtn = document.querySelector(`.pay-order[data-id="${id}"]`);
            if(rowBtn){
                const tr = rowBtn.closest('tr');
                const paidCell = tr.querySelectorAll('td')[7];
                const remainingCell = tr.querySelectorAll('td')[8];
                const statusCell = tr.querySelectorAll('td')[9];
                if(paidCell) paidCell.textContent = `Tsh ${fmt(data.paid)}`;
                if(remainingCell) remainingCell.textContent = `Tsh ${fmt(data.remaining)}`;
                if(statusCell){
                    statusCell.innerHTML = data.status==='paid' ? '<span class="badge badge-success">Paid</span>' : (data.status==='pending' ? '<span class="badge badge-danger">Pending</span>' : '<span class="badge badge-warning">Partial</span>');
                }
                rowBtn.setAttribute('data-remaining', String(data.remaining));
            }
            $('#paymentModal').modal('hide');
            if(window.Swal){ Swal.fire({ icon:'success', title:'Payment recorded', timer:1500, timerProgressBar: true, showConfirmButton:false }); }
        }).catch(errMsg => {
            try { if(window.Swal){ Swal.close(); } } catch(e){}
            err.classList.remove('d-none');
            err.textContent = errMsg.message || String(errMsg);
        });
    });

    document.addEventListener('click', function(e){
        const btn = e.target.closest('.history-order');
        if(!btn) return;
        const id = btn.getAttribute('data-id');
        const orderNo = btn.getAttribute('data-order-no') || '';
        fetch(`{{ url('/api/purchase-orders') }}/${id}/payments`, { headers: { 'Accept': 'application/json' }})
            .then(r=>r.json())
            .then(data=>{
                document.getElementById('ph_order_no').innerText = orderNo;
                document.getElementById('ph_paid').innerText = fmt(data.summary.paid);
                document.getElementById('ph_remaining').innerText = fmt(data.summary.remaining);
                const stBadge = data.summary.status==='paid' ? '<span class="badge badge-success">Paid</span>' : (data.summary.status==='pending' ? '<span class="badge badge-danger">Pending</span>' : '<span class="badge badge-warning">Partial</span>');
                document.getElementById('ph_status').innerHTML = stBadge;
                const tbody = document.getElementById('payments_tbody');
                tbody.innerHTML = '';
                (data.payments||[]).forEach(p=>{
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${p.transaction_date ?? ''}
                    </td><td class="text-right">${fmt(p.total_amount)}</td>
                    <td>${p.payment_method||''}</td>
                    <td>${p.reference_number||''}</td>
                    <td>${p.notes||''}</td>
                    <td>${p.user ? p.user.name: '-'}</td>`;
                    tbody.appendChild(tr);
                });
                $('#paymentsModal').modal('show');
            });
    });

    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.delete-order');
        if(!btn) return;
        const id = btn.getAttribute('data-id');
        const orderNo = btn.getAttribute('data-order-no') || '';
        const tr = btn.closest('tr');
        const result = await Swal.fire({
            title: `Delete PO ${orderNo}?`,
            text: 'This will soft delete the purchase order. You can recover it from the database if needed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        });
        if(!result.isConfirmed) return;
        try {
            const r = await fetch(`{{ url('/admin/purchases/purchase-orders') }}/${id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            if(!r.ok){ const j = await r.json().catch(()=>({message:'Delete failed'})); throw new Error(j.message || 'Delete failed'); }
            if (tr) {
                if (window.PO_DT) { window.PO_DT.row(tr).remove().draw(false); }
                else { tr.remove(); }
            }
            Swal.fire({ icon:'success', title:`PO ${orderNo} deleted`, timer: 1500, timerProgressBar: true, showConfirmButton:false });
        } catch(err){
            Swal.fire({ icon:'error', title: (err && err.message) ? err.message : 'Failed to delete', showConfirmButton:true });
        }
    });
})();
</script>
@stop