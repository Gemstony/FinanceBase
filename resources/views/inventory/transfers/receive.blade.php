@extends('adminlte::page')

@section('title', 'Receive Transfer #'.$transfer->id)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-clipboard-check"></i> Receive Transfer #{{ $transfer->id }}</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-clipboard-check"></i> Receive</h1>
                <div class="small text-light-50">From <strong>{{ $transfer->sourceSubshop->name }}</strong> to <strong>{{ $transfer->destinationSubshop->name }}</strong> • Status: <span class="badge badge-light">{{ ucwords(str_replace('_',' ', $transfer->status)) }}</span></div>
            </div>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('transfers.index') }}" class="btn btn-outline-light"><i class="fas fa-list"></i> Transfers</a>
                <a href="{{ route('transfers.show', $transfer) }}" class="btn btn-outline-light"><i class="fas fa-eye"></i> View</a>
            </div>
        </div>
    </div>
@endsection

@section('content')
@if (session('error'))
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            Swal.fire({ icon: 'error', title: 'Error', text: "{{ session('error') }}" });
        });
    </script>
@endif

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Items & Batches</h5>
    </div>
    <div class="card-body">
        <form id="receiveForm">
            @csrf
            <input type="hidden" id="transfer_id" value="{{ $transfer->id }}" />

            @foreach($transfer->items as $ti)
                <div class="mb-3">
                    <h6 class="mb-2"><i class="fas fa-box"></i> {{ $ti->item_name_snapshot }} <small class="text-muted">(SKU: {{ $ti->sku_snapshot }})</small></h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Batch</th>
                                    <th>Expiry</th>
                                    <th>Dispatched</th>
                                    <th>Received</th>
                                    <th>Damaged</th>
                                    <th>Remaining</th>
                                    <th>Receive Now</th>
                                    <th>Damaged Now</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $idx=1; @endphp
                                @foreach($ti->batches as $tib)
                                    @php 
                                        $dispatched = (float)($tib->dispatched_qty ?? 0);
                                        $already = (float)($tib->received_qty ?? 0) + (float)($tib->damaged_qty ?? 0);
                                        $remaining = max($dispatched - $already, 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $idx++ }}</td>
                                        <td>{{ $tib->batch_number }}</td>
                                        <td>{{ $tib->expire_date ? \Carbon\Carbon::parse($tib->expire_date)->format('Y-m-d') : '-' }}</td>
                                        <td>{{ number_format($dispatched,3) }}</td>
                                        <td>{{ number_format($tib->received_qty ?? 0,3) }}</td>
                                        <td>{{ number_format($tib->damaged_qty ?? 0,3) }}</td>
                                        <td><span class="badge badge-info">{{ number_format($remaining,3) }}</span></td>
                                        <td style="max-width:140px"><input type="number" class="form-control form-control-sm rcv-qty" data-id="{{ $tib->id }}" min="0" max="{{ $remaining }}" step="0.001" value="0"></td>
                                        <td style="max-width:140px"><input type="number" class="form-control form-control-sm dmg-qty" data-id="{{ $tib->id }}" min="0" max="{{ $remaining }}" step="0.001" value="0"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </form>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div>
            <strong>Total to Receive:</strong> <span id="total_receive">0</span>
            <span class="ml-3"><strong>Total Damaged:</strong> <span id="total_damaged">0</span></span>
        </div>
        <div>
            <button class="btn btn-success" onclick="submitReceive()"><i class="fas fa-check"></i> Confirm Receive</button>
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function recalcTotals(){
        let tr=0, td=0;
        document.querySelectorAll('.rcv-qty').forEach(function(el){ tr += Number(el.value||0); });
        document.querySelectorAll('.dmg-qty').forEach(function(el){ td += Number(el.value||0); });
        document.getElementById('total_receive').innerText = tr;
        document.getElementById('total_damaged').innerText = td;
    }
    document.addEventListener('input', function(e){
        if(e.target.classList && (e.target.classList.contains('rcv-qty') || e.target.classList.contains('dmg-qty'))){
            const row = e.target.closest('tr');
            const max = Number((e.target.getAttribute('max'))||0);
            let v = Number(e.target.value||0);
            if(v > max){ e.target.value = max; }
            // keep sum receive+damaged <= remaining
            const id = e.target.getAttribute('data-id');
            const rcv = Number(document.querySelector('.rcv-qty[data-id="'+id+'"]').value||0);
            const dmg = Number(document.querySelector('.dmg-qty[data-id="'+id+'"]').value||0);
            if(rcv + dmg > max){
                if(e.target.classList.contains('rcv-qty')){
                    document.querySelector('.rcv-qty[data-id="'+id+'"]').value = Math.max(max - dmg, 0);
                } else {
                    document.querySelector('.dmg-qty[data-id="'+id+'"]').value = Math.max(max - rcv, 0);
                }
            }
            recalcTotals();
        }
    });

    function submitReceive(){
        const transferId = document.getElementById('transfer_id').value;
        const items = [];
        document.querySelectorAll('.rcv-qty').forEach(function(rcv){
            const id = rcv.getAttribute('data-id');
            const r = Number(rcv.value||0);
            const d = Number(document.querySelector('.dmg-qty[data-id="'+id+'"]').value||0);
            if(r > 0 || d > 0){
                items.push({ transfer_item_batch_id: id, received_qty: r, damaged_qty: d });
            }
        });
        if(items.length === 0){
            Swal.fire({ icon: 'warning', title: 'Nothing to receive', text: 'Enter quantities to receive.' });
            return;
        }

        Swal.fire({
            title: 'Confirm receive?',
            text: 'Proceed to post the received quantities.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, confirm',
            cancelButtonText: 'No'
        }).then((result)=>{
            if(!result.isConfirmed) return;
            fetch("{{ route('transfers.receive', $transfer) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ items: items })
            }).then(async (res)=>{
                let payload = null;
                try { payload = await res.json(); } catch(e) { /* non-JSON */ }
                if(!res.ok){
                    const txt = payload && payload.message ? payload.message : (await res.text().catch(()=>'')) || 'Failed to receive';
                    Swal.fire({ icon: 'error', title: `Error (${res.status})`, text: txt });
                    return;
                }
                const j = payload || {};
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: j.status === 'received' ? 'Transfer fully received' : 'Transfer partially received',
                    timer: 1200,
                    showConfirmButton: false
                }).then(()=>{ window.location = "{{ route('transfers.index') }}"; });
            }).catch(()=>{
                Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.' });
            });
        });
    }
</script>
@endsection
