@extends('adminlte::page')

@section('title', 'Purchase Returns - ' . ($subshop->name ?? ''))

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-undo-alt"></i> Purchase Returns</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-undo-alt"></i> Returns</h1>
                <div class="small text-light-50">Shop: {{ $subshop->name ?? '-' }}</div>
            </div>
            <a href="{{ route('purchase_orders.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-history"></i> Purchase History</a>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="h5 mb-0">Overview</div>
                <div class="btn-group">
                    @php
                        $params = [
                            'subshop_id' => $subshop->id,
                            'q' => $q,
                            'date_from' => $dateFrom,
                            'date_to' => $dateTo,
                            'min_total' => $minTotal,
                            'max_total' => $maxTotal,
                        ];
                    @endphp

                    @can('export_purchase_returns')
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="{{ route('purchase_returns.export', array_merge(['format' => 'csv'], $params)) }}"><i class="fas fa-file-csv mr-2 text-success"></i>CSV</a>
                        <a class="dropdown-item" href="{{ route('purchase_returns.export', array_merge(['format' => 'excel'], $params)) }}">
                            <i class="fas fa-file-excel mr-2 text-success"></i>Excel
                        </a>
                        <a class="dropdown-item" href="{{ route('purchase_returns.export', array_merge(['format' => 'pdf'], $params)) }}">
                            <i class="fas fa-file-pdf mr-2 text-danger"></i>PDF
                        </a>
                    </div>
                    @endcan
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 col-12 mb-2">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary"><i class="fas fa-clipboard-list"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Returns</span>
                            <span class="info-box-number">{{ number_format($summary['count'] ?? 0) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-12 mb-2">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-tags"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Value (Returned)</span>
                            <span class="info-box-number">{{ number_format($summary['returned_total'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-12 mb-2">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-hand-holding-usd"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Refunded</span>
                            <span class="info-box-number">{{ number_format($summary['refunded_total'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <form method="get" action="{{ route('purchase_returns.index') }}" class="mb-3">
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}" />
                <div class="bg-light p-2 rounded border">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Search</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Order No / Method / Reason">
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
                            <label class="small mb-1">Min Amount</label>
                            <input type="number" step="0.01" name="min_total" value="{{ $minTotal }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Max Amount</label>
                            <input type="number" step="0.01" name="max_total" value="{{ $maxTotal }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Sort</label>
                            <select name="sort" class="form-control">
                                <option value="date_desc" {{ ($sort==='date_desc')?'selected':'' }}>Date: New → Old</option>
                                <option value="date_asc" {{ ($sort==='date_asc')?'selected':'' }}>Date: Old → New</option>
                                <option value="amount_desc" {{ ($sort==='amount_desc')?'selected':'' }}>Amount: High → Low</option>
                                <option value="amount_asc" {{ ($sort==='amount_asc')?'selected':'' }}>Amount: Low → High</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
                            <a class="btn btn-light border" href="{{ route('purchase_returns.index', ['subshop_id'=>$subshop->id]) }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover" id="PurchaseReturnsTable">
                    <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                        <tr>
                            <th><i class="fas fa-calendar-alt mr-1"></i> Date</th>
                            <th><i class="fas fa-hashtag mr-1"></i> Order No</th>
                            <th><i class="fas fa-box mr-1"></i> Item</th>
                            <th class="text-right"><i class="fas fa-tag mr-1"></i> Unit Price</th>
                            <th class="text-right"><i class="fas fa-boxes mr-1"></i> Returned</th>
                            <th class="text-right"><i class="fas fa-calculator mr-1"></i> Base</th>
                            <th class="text-right"><i class="fas fa-percentage mr-1"></i> VAT</th>
                            <th class="text-right"><i class="fas fa-coins mr-1"></i> Line Total</th>
                            <th class="text-right"><i class="fas fa-hand-holding-usd mr-1"></i> Refunded</th>
                            <th><i class="fas fa-credit-card mr-1"></i> Method</th>
                            <th><i class="fas fa-user-check mr-1"></i> Processed By</th>
                            <th><i class="fas fa-comment-dots mr-1"></i> Reason</th>
                            <th class="text-center"><i class="fas fa-cog mr-1"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $r)
                        <tr>
                            <td>{{ $r->created_at? $r->created_at->format('d M Y, H:i') : '' }}</td>
                            <td><span class="badge badge-primary">{{ $r->order_no }}</span></td>
                            <td>{{ $r->item_id }} — {{ $r->item_name ?: ('Item #'.$r->item_id) }}</td>
                            <td class="text-right">{{ number_format($r->unit_price,2) }}</td>
                            <td class="text-right">{{ number_format($r->quantity_returned) }}</td>
                            <td class="text-right">{{ number_format($r->base_amount,2) }}</td>
                            <td class="text-right">{{ number_format($r->vat_amount,2) }}</td>
                            <td class="text-right"><strong>{{ number_format($r->line_total,2) }}</strong></td>
                            @php $refAmt = (float)($r->refund_amount ?? 0); @endphp
                            <td class="text-right {{ $refAmt<0 ? 'text-success' : 'text-muted' }}">{{ $refAmt<0 ? number_format(-$refAmt,2) : '-' }}</td>
                            <td>{{ $r->refund_method ?? '-' }}</td>
                            <td>{{ $r->processed_by_name ?? '-' }}</td>
                            <td>{{ $r->reason ?? '-' }}</td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    @can('print_purchase_return_receipt_invoice')
                                    <a href="{{ route('purchase_returns.print', $r->id) }}" target="_blank" class="btn btn-outline-primary" title="View Return Receipt">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                    <button type="button" class="btn btn-dark escpos-purchase-return" data-id="{{ $r->id }}" title="ESC/POS Print">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    @endcan
                                    @can('restore_and_delete_purchase_return')
                                    <form method="POST" action="{{ route('purchase_returns.destroy', $r->id) }}" class="d-inline delete-return-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-warning" title="Delete and Restore the Return">
                                            <i class="fas fa-undo-alt"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted py-5"><i class="fas fa-inbox"></i> No returns found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $returns->links() }}
            </div>
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
    .info-box { box-shadow: 0 1px 3px rgba(0,0,0,.08); border-radius: .5rem; }
    .info-box .info-box-icon { border-top-left-radius: .5rem; border-bottom-left-radius: .5rem; }
    .info-box .info-box-content { padding: .5rem .75rem; }
    </style>
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Global JS config
window.DUKA = Object.assign({}, window.DUKA || {}, {
    apiPurchaseReturnsBase: @json(url('/api/purchase-returns')),
    apiPrintStatus: @json(url('/api/print-jobs/status')),
    csrf: @json(csrf_token())
});
$(function(){
    // Initialize DataTable for better pagination/search on returns list
    const dt = $('#PurchaseReturnsTable').DataTable({
        order: [],
        pageLength: 10,
        columnDefs: [ { targets: -1, orderable: false, searchable: false } ],
        language: {
            search: 'Search returns:',
            lengthMenu: 'Show _MENU_ returns per page',
            zeroRecords: 'No returns found',
            info: 'Showing _START_ to _END_ of _TOTAL_ returns',
            infoEmpty: 'No returns available',
            infoFiltered: '(filtered from _MAX_ total returns)'
        }
    });

    // Intercept delete & restore form submit to show confirmation and call API via fetch
    $(document).on('submit', '.delete-return-form', function(e){
        e.preventDefault();
        const form = this;
        const action = form.getAttribute('action');
        const row = $(form).closest('tr');
        const token = '{{ csrf_token() }}';

        // If SweetAlert is available, use it for a better UX confirmation dialog
        if (window.Swal) {
            Swal.fire({
                title: 'Delete & Restore purchase return?',
                text: 'This will restore the item to the order and adjust totals.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'rgba(202, 146, 5, 1)',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete & restore it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show a loading state while the backend processes the deletion + restoration
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Processing...',
                            html: 'Deleting and restoring the return. Please wait.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => { Swal.showLoading(); }
                        });
                    }
                    // Send DELETE (via method override) with CSRF token
                    fetch(action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-HTTP-Method-Override': 'DELETE'
                        },
                        body: JSON.stringify({})
                    }).then(async r => {
                        const j = await r.json().catch(()=>({success:false,message:'Failed'}));
                        if (!r.ok || j.success === false) { throw new Error(j.message || 'Failed to delete'); }
                        return j;
                    }).then(() => {
                        // Remove the row from the DataTable without full page reload
                        if (row && row.length) {
                            const dtApi = $('#PurchaseReturnsTable').DataTable();
                            dtApi.row(row).remove().draw(false);
                        }
                        // Show success notice
                        Swal.fire({ icon:'success', title:'Restored & Deleted', text:'Purchase return restored successfully', timer:1200, showConfirmButton:false });
                    }).catch(err => {
                        // Close any in-progress SweetAlert and show an error dialog
                        try { if (window.Swal) { Swal.close(); } } catch(e){}
                        Swal.fire({ icon:'error', title:'Error', text: (err && err.message) ? err.message : 'Failed to delete' });
                    });
                }
            });
        } else {
            // Fallback to native confirm if SweetAlert is not present
            if (confirm('Delete this purchase return? This will restore the item to the order and adjust totals.')) {
                fetch(action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-HTTP-Method-Override': 'DELETE'
                    },
                    body: JSON.stringify({})
                }).then(() => window.location.reload());
            }
        }
    });
});
</script>

<script>
// ESC/POS Purchase Return printing handler
document.addEventListener('click', function(e){
    const btn = e.target.closest('.escpos-purchase-return');
    if(!btn) return;
    e.preventDefault();
    const id = btn.getAttribute('data-id');
    const base = (window.DUKA && window.DUKA.apiPurchaseReturnsBase) ? window.DUKA.apiPurchaseReturnsBase : '';
    const apiUrl = base + '/' + id + '/print';
    if (!id || !apiUrl) return;

    if (window.Swal) {
        Swal.fire({
            title: 'Print purchase return',
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
                    const ct = (res.headers.get('content-type') || '').toLowerCase();
                    if (!ct.includes('application/json')) {
                        const text = await res.text();
                        throw new Error((text || '').replace(/<[^>]*>/g,' ').trim().slice(0, 300) || 'Unexpected response');
                    }
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error(data.error || 'Failed');
                    if (data.dummy && data.data) {
                        const raw = atob(data.data);
                        const blob = new Blob([new Uint8Array(Array.from(raw, c => c.charCodeAt(0)))], { type: 'application/octet-stream' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a'); a.href = url; a.download = 'purchase-return-'+id+'-escpos.bin'; document.body.appendChild(a); a.click(); a.remove();
                        Swal.fire({ icon:'success', title:'Dummy output generated', timer: 1600, showConfirmButton:false });
                    } else {
                        const jobId = data.job_id;
                        if (!jobId) { Swal.fire({ icon:'success', title:'Print job sent', timer: 1500, showConfirmButton:false }); return; }
                        const statusUrl = (window.DUKA && window.DUKA.apiPrintStatus) ? window.DUKA.apiPrintStatus : '';
                        let attempts = 0; const maxAttempts = 20;
                        Swal.fire({ title:'Printing...', html:'<span class="text-muted">Queued</span>', allowOutsideClick:false, allowEscapeKey:false, didOpen: () => Swal.showLoading() });
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
                    Swal.fire({ icon:'error', title:'Failed', text: (err && err.message) ? err.message : 'Error' });
                }
            }
        });
    }
});
</script>
@stop