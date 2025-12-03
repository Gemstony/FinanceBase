@extends('adminlte::page')

@section('title', 'Invoices History - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-file-invoice"></i> Invoices History</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-file-invoice"></i> Invoices</h1>
                <div class="small text-light-50">Shop: {{ $subshop->name }}</div>
            </div>
            <a href="{{ route('pos.index') }}" class="btn btn-outline-light btn "><i class="fas fa-dollar-sign"></i> POS</a>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <!-- Summary cards -->
            <div class="row mb-3">
                <div class="col-md-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['count'] ?? 0) }}</h3>
                            <p>Total Invoices</p>
                        </div>
                        <div class="icon"><i class="fas fa-file-invoice"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['grand_total'] ?? 0, 2) }}</h3>
                            <p>Grand Total</p>
                        </div>
                        <div class="icon"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['paid_total'] ?? 0, 2) }}</h3>
                            <p>Total Paid</p>
                        </div>
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['remaining_total'] ?? 0, 2) }}</h3>
                            <p>Remaining</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" action="{{ route('invoices.index') }}" class="mb-3">
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
                            <a class="btn btn-light border" href="{{ route('invoices.index', ['subshop_id'=>$subshop->id]) }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>

                    </div>
                </div>
            </form>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted small">Filtered results: {{ number_format($orders->count()) }}</div>
                @can('export_invoice_history')
                <div class="dropdown">
                    <!-- Export Dropdown -->
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="{{ route('invoices.export', ['format' => 'csv'] + request()->query()) }}">
                            <i class="fas fa-file-csv mr-1 text-success"></i> CSV
                        </a>
                        <a class="dropdown-item" href="{{ route('invoices.export', ['format' => 'excel'] + request()->query()) }}">
                            <i class="fas fa-file-excel mr-1 text-success"></i> Excel
                        </a>
                        <a class="dropdown-item" href="{{ route('invoices.export', ['format' => 'pdf'] + request()->query()) }}">
                            <i class="fas fa-file-pdf mr-1 text-danger"></i> PDF
                        </a>
                    </div>
                </div>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="InvoicesTable">
                    <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                        <tr>
                            <th><i class="fas fa-hashtag mr-1"></i> Order No</th>
                            <th><i class="fas fa-calendar-alt mr-1"></i> Date</th>
                            <th><i class="fas fa-user mr-1"></i> Customer</th>
                            <th class="text-right"><i class="fas fa-boxes mr-1"></i> Items</th>
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
                        <tr>
                            <td><span class="badge badge-primary">{{ $order->order_no }}</span></td>
                            <td>{{ $order->created_at? $order->created_at->format('d M Y, H:i') : '' }}</td>
                            <td>{{ $order->customer->name ?? 'No Customer' }}</td>
                            <td class="text-right">{{ $order->items->sum('quantity') }}</td>
                            <td class="text-right">Tsh {{ number_format($order->subtotal, 2) }}</td>
                            <td class="text-right">Tsh {{ number_format($order->vat_total, 2) }}</td>
                            <td class="text-right">Tsh {{ number_format($order->discount_total, 2) }}</td>
                            <td class="text-right"><strong>Tsh {{ number_format($order->grand_total, 2) }}</strong></td>
                            @php
                                $paid = (float)($paidMap[$order->id] ?? 0);
                                $remaining = max(0, (float)$order->grand_total - $paid);
                                $status = $remaining <= 0 ? 'paid' : ($paid <= 0 ? 'pending' : 'partial');
                            @endphp
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
                            <td>{{ $order->creator->name ?? '-' }}</td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary view-order" data-id="{{ $order->id }}"><i class="fas fa-eye"></i> View</button>
                                    @can('pay_invoice')
                                    <button class="btn btn-sm btn-success pay-order" data-id="{{ $order->id }}" data-remaining="{{ number_format($remaining, 2, '.', '') }}" data-order-no="{{ $order->order_no }}"><i class="fas fa-cash-register"></i> Pay</button>
                                    @endcan
                                    @can('invoice_payment_history')
                                    <button class="btn btn-sm btn-outline-secondary history-order" data-id="{{ $order->id }}" data-order-no="{{ $order->order_no }}"><i class="fas fa-history"></i> History</button>
                                    @endcan
                                    @can('return_invoice')
                                    <button class="btn btn-sm btn-warning return-order" data-id="{{ $order->id }}" data-order-no="{{ $order->order_no }}"><i class="fas fa-undo-alt"></i> Return</button>
                                    @endcan
                                    @can('print_invoice')
                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('invoices.print', $order->id) }}" target="_blank"><i class="fas fa-file-invoice"></i> View</a>
                                    <button class="btn btn-sm btn-dark escpos-print" data-id="{{ $order->id }}"><i class="fas fa-print"></i> ESC/POS</button>
                                    @endcan
                                    @can('delete_invoice')
                                    <form method="POST" action="{{ route('invoices.destroy', $order->id) }}" style="display:inline-block;" class="delete-order-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger delete-order-btn" data-order-no="{{ $order->order_no }}">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5"><i class="fas fa-inbox"></i> No invoices found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            
        </div>
    </div>
</div>

<form id="delete_invoice_form" method="POST" action="#" style="display:none;">
    @csrf
    @method('DELETE')
</form>

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
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Unit</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">VAT</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody id="order_items"></tbody>
            </table>
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

<!-- Sales Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1" role="dialog" aria-labelledby="returnLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="returnLabel"><i class="fas fa-undo-alt text-warning"></i> Sales Return</h5>
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
              <select id="ret_method" class="form-control" required required>
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
                  <th class="text-right">Sold</th>
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

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
    /* Responsive tweaks for summary cards */
    .small-box .inner h3{
        font-size: 1.6rem;
        line-height: 1.2;
        word-break: break-word;
        white-space: normal;
    }
    .small-box .inner p{ margin-bottom: 0; }
    /* Prevent icon overflow */
    .small-box{ position: relative; overflow: hidden; }
    .small-box .icon{ position:absolute; right:10px; top:8px; font-size:36px; opacity:.35; z-index:0; line-height:1; }
    .small-box .inner{ position: relative; z-index:1; }
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
// Global config for JS (avoid inline Blade in expressions)
window.DUKA = Object.assign({}, window.DUKA || {}, {
    apiInvoicesBase: @json(url('/api/invoices')),
    apiPrintStatus: @json(url('/api/print-jobs/status')),
    csrf: @json(csrf_token())
});
document.addEventListener('DOMContentLoaded', function(){
    const flashSuccess = @json(session('success'));
    const flashError = @json(session('error'));
    if (flashSuccess) { Swal.fire({ icon:'success', title: flashSuccess, timer: 1800, timerProgressBar: true, showConfirmButton:false }); }
    if (flashError) { Swal.fire({ icon:'error', title: flashError, timer: 2200, showConfirmButton:true }); }
});


$(function () {
    // Initialize DataTable
    $('#InvoicesTable').DataTable({
        // Preserve backend order determined by the controller (Advanced sorting select)
        // Leave initial order empty so DataTables doesn't re-sort on load
        "order": [],
        "pageLength": 10,
        "language": {
            "search": "Search invoices:",
            "lengthMenu": "Show _MENU_ invoices per page",
            "zeroRecords": "No invoices found",
            "info": "Showing _START_ to _END_ of _TOTAL_ invoices",
            "infoEmpty": "No invoices available",
            "infoFiltered": "(filtered from _MAX_ total invoices)"
        }
    });

    // SweetAlert confirm for delete forms
    document.addEventListener('submit', function(e){
        const form = e.target.closest('.delete-order-form');
        if(!form) return;
        e.preventDefault();
        const btn = form.querySelector('.delete-order-btn');
        const orderNo = btn ? (btn.getAttribute('data-order-no') || '') : '';
        Swal.fire({
            icon: 'warning',
            title: `Delete invoice ${orderNo}?`,
            text: 'This will remove the invoice, its items and payments.',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then((res) => {
            if(res.isConfirmed){ form.submit(); }
        });
    });
});

(function(){
    function fmt(n){ return parseFloat(n||0).toFixed(2); }
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.view-order');
        if(!btn) return;
        const id = btn.getAttribute('data-id');
        fetch(`{{ url('/api/invoices') }}/${id}`, { headers: { 'Accept': 'application/json' }})
            .then(r => r.json())
            .then(data => {
                const o = data.order || {};
                const items = data.items || [];
                document.getElementById('order_meta').innerHTML = `
                    <div><strong>Order No:</strong> ${o.order_no} &nbsp; <strong>Date:</strong> ${o.created_at ?? ''}</div>
                    <div><strong>Customer:</strong> ${(o.customer && o.customer.name) ? o.customer.name : '-'} &nbsp; <strong>Payment:</strong> ${(o.payment_method||'').toUpperCase()}</div>
                `;
                const tbody = document.getElementById('order_items');
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
                document.getElementById('ov_subtotal').innerText = fmt(o.subtotal);
                document.getElementById('ov_vat').innerText = fmt(o.vat_total);
                document.getElementById('ov_discount').innerText = fmt(o.discount_total);
                document.getElementById('ov_grand').innerText = fmt(o.grand_total);
                // payment summary
                document.getElementById('ov_paid').innerText = fmt(data.paid);
                document.getElementById('ov_remaining').innerText = fmt(data.remaining);
                const stBadge = data.status==='paid' ? '<span class="badge badge-success">Paid</span>' : (data.status==='pending' ? '<span class="badge badge-danger">Pending</span>' : '<span class="badge badge-warning">Partial</span>');
                document.getElementById('ov_status').innerHTML = stBadge;
                $('#orderViewModal').modal('show');
            });
    });

    // Open Pay modal
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

    // Submit payment
    document.getElementById('submit_payment_btn').addEventListener('click', function(){
        const id = document.getElementById('pay_order_id').value;
        const amount = parseFloat(document.getElementById('pay_amount').value || '0');
        const date = document.getElementById('pay_date').value;
        const ref = document.getElementById('pay_ref').value;
        const method = document.getElementById('pay_method').value;
        const notes = document.getElementById('pay_notes').value;
        if(!id || !amount || amount <= 0 || !date || !method){ return; }
        const err = document.getElementById('pay_error'); err.classList.add('d-none'); err.textContent='';
        // Show loading while recording payment
        if(window.Swal){
            Swal.fire({
                title: 'Recording payment...',
                html: 'Please wait.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });
        }
        fetch(`{{ url('/api/invoices') }}/${id}/payments`, {
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
            // update row cells
            const rowBtn = document.querySelector(`.pay-order[data-id="${id}"]`);
            if(rowBtn){
                const tr = rowBtn.closest('tr');
                const paidCell = tr.querySelectorAll('td')[8]; // after removing Pay Method, indices shift left
                const remainingCell = tr.querySelectorAll('td')[9];
                const statusCell = tr.querySelectorAll('td')[10];
                if(paidCell) paidCell.textContent = fmt(data.paid);
                if(remainingCell) remainingCell.textContent = fmt(data.remaining);
                if(statusCell){
                    statusCell.innerHTML = data.status==='paid' ? '<span class="badge badge-success">Paid</span>' : (data.status==='pending' ? '<span class="badge badge-danger">Pending</span>' : '<span class="badge badge-warning">Partial</span>');
                }
                // update button remaining data attribute
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

    // Open Payments History modal (standalone listener)
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.history-order');
        if(!btn) return;
        const id = btn.getAttribute('data-id');
        const orderNo = btn.getAttribute('data-order-no') || '';
        document.getElementById('ph_order_no').innerText = orderNo;
        const tbody = document.getElementById('payments_tbody'); tbody.innerHTML='';
        fetch(`{{ url('/api/invoices') }}/${id}/payments`, { headers: { 'Accept': 'application/json' }})
            .then(r => r.json())
            .then(data => {
                const pays = data.payments || [];
                pays.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${p.transaction_date || p.created_at}</td>
                        <td class="text-right">${fmt(p.total_amount)}</td>
                        <td>${p.payment_method || '-'}</td>
                        <td>${p.reference_number || '-'}</td>
                        <td>${p.notes || ''}</td>
                        <td>${p.user ? p.user.name : '-'}</td>
                    `;
                    tbody.appendChild(tr);
                });
                document.getElementById('ph_paid').innerText = fmt((data.summary && data.summary.paid) || 0);
                document.getElementById('ph_remaining').innerText = fmt((data.summary && data.summary.remaining) || 0);
                const st = (data.summary && data.summary.status) || 'pending';
                const stBadge = st==='paid' ? '<span class="badge badge-success">Paid</span>' : (st==='pending' ? '<span class="badge badge-danger">Pending</span>' : '<span class="badge badge-warning">Partial</span>');
                document.getElementById('ph_status').innerHTML = stBadge;
                $('#paymentsModal').modal('show');
            });
    });

    // Open Sales Return modal
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

        fetch(`{{ url('/api/invoices') }}/${id}/returns`, { headers: { 'Accept': 'application/json' }})
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
                                   data-soi-id="${it.sales_order_item_id}" style="text-align:right;">
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

    // Submit return
    document.getElementById('submit_return_btn').addEventListener('click', function(){
        const id = document.getElementById('ret_order_id').value;
        const date = document.getElementById('ret_date').value;
        const reason = document.getElementById('ret_reason').value;
        const ref = document.getElementById('ret_ref').value;
        const notes = document.getElementById('ret_notes').value;
        const method = document.getElementById('ret_method').value;
        const qtyInputs = Array.from(document.querySelectorAll('#ret_items_tbody .ret-qty'));
        const items = qtyInputs.map(inp => ({ sales_order_item_id: parseInt(inp.getAttribute('data-soi-id')), quantity: parseInt(inp.value||'0') }))
            .filter(x => x.quantity && x.quantity > 0);

        const err = document.getElementById('ret_error'); err.classList.add('d-none'); err.textContent='';
        if(!id || !date || items.length === 0){
            err.classList.remove('d-none'); err.textContent = 'Select at least one item and fill required fields.'; return;
        }
        // Show loading while processing return
        if(window.Swal){
            Swal.fire({
                title: 'Processing return...',
                html: 'Please wait.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });
        }
        fetch(`{{ url('/api/invoices') }}/${id}/returns`, {
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
                const paidCell = tr.querySelectorAll('td')[8];
                const remainingCell = tr.querySelectorAll('td')[9];
                const statusCell = tr.querySelectorAll('td')[10];
                if(paidCell) paidCell.textContent = parseFloat(data.paid||0).toFixed(2);
                if(remainingCell) remainingCell.textContent = parseFloat(data.remaining||0).toFixed(2);
                if(statusCell){
                    statusCell.innerHTML = data.status==='paid' ? '<span class="badge badge-success">Paid</span>' : (data.status==='pending' ? '<span class="badge badge-danger">Pending</span>' : '<span class="badge badge-warning">Partial</span>');
                }
            }
            $('#returnModal').modal('hide');
            if(window.Swal){ Swal.fire({ icon:'success', title:'Return processed', timer:1500, timerProgressBar: true, showConfirmButton:false }); }
        }).catch(errMsg => {
            try { if(window.Swal){ Swal.close(); } } catch(e){}
            err.classList.remove('d-none');
            err.textContent = errMsg.message || String(errMsg);
        });
    });
})();

// ESC/POS printing handler
document.addEventListener('click', function(e){
    const btn = e.target.closest('.escpos-print');
    if(!btn) return;
    e.preventDefault();
    const id = btn.getAttribute('data-id');
    if (!id) return;
    const base = (window.DUKA && window.DUKA.apiInvoicesBase) ? window.DUKA.apiInvoicesBase : '';
    const apiUrl = base + '/' + id + '/print';

    // Ask user: Dummy or Real
    if (window.Swal) {
        Swal.fire({
            title: 'Print invoice',
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
                        // Build and download file
                        const raw = atob(data.data);
                        const blob = new Blob([new Uint8Array(Array.from(raw, c => c.charCodeAt(0)))], { type: 'application/octet-stream' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a'); a.href = url; a.download = 'invoice-'+id+'-escpos.bin'; document.body.appendChild(a); a.click(); a.remove();
                        Swal.fire({ icon:'success', title:'Dummy output generated', timer: 1600, showConfirmButton:false });
                    } else {
                        // Start polling status if job id returned
                        const jobId = data.job_id;
                        if (!jobId) { Swal.fire({ icon:'success', title:'Print job sent', timer: 1500, showConfirmButton:false }); return; }
                        const statusUrl = (window.DUKA && window.DUKA.apiPrintStatus) ? window.DUKA.apiPrintStatus : '';
                        let attempts = 0;
                        const maxAttempts = 20; // ~40s at 2s interval
                        Swal.fire({ title:'Printing...', html:'<span class="text-muted">Queued</span>', allowOutsideClick:false, allowEscapeKey:false, didOpen: () => Swal.showLoading() });
                        const poll = async () => {
                            attempts++;
                            try {
                                const r = await fetch(statusUrl, { method:'POST', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': (window.DUKA && window.DUKA.csrf) ? window.DUKA.csrf : '' }, body: JSON.stringify({ job_id: jobId }) });
                                const j = await r.json().catch(()=>({ ok:false }));
                                if (!j.ok) throw new Error('Status check failed');
                                const st = (j.status||'').toLowerCase();
                                if (st === 'success') { Swal.fire({ icon:'success', title:'Printed', timer:1200, showConfirmButton:false }); return; }
                                if (st === 'failed') { Swal.fire({ icon:'error', title:'Print failed', text: j.message || 'Unknown error' }); return; }
                                // queued or running
                                if (attempts < maxAttempts) { setTimeout(poll, 2000); Swal.getHtmlContainer().innerHTML = `<span class="text-muted">${st==='running'?'Running':'Queued'}...</span>`; }
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
@stop