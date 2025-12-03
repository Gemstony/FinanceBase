@extends('adminlte::page')

@section('title', 'Transfer #'.$transfer->id)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exchange-alt"></i> Transfer #{{ $transfer->id }}</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exchange-alt"></i> Transfer</h1>
                <div class="small text-light-50">From <strong>{{ $transfer->sourceSubshop->name }}</strong> to <strong>{{ $transfer->destinationSubshop->name }}</strong> • Status: <span class="badge badge-light">{{ ucwords(str_replace('_',' ', $transfer->status)) }}</span></div>
            </div>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('transfers.index') }}" class="btn btn-outline-light"><i class="fas fa-list"></i> Transfers</a>
                <a href="{{ route('transfers.print', $transfer) }}" target="_blank" class="btn btn-outline-light"><i class="fas fa-print"></i> Print</a>
            </div>
        </div>
    </div>
@endsection

@section('content')
@php
    $cancellationAudit = $transfer->audits->where('action', 'cancelled')->first();
    $writeOffs = $cancellationAudit->meta['write_offs'] ?? [];
@endphp

@if (session('success') || session('error'))
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            @if(session('success'))
                Swal.fire({ icon: 'success', title: 'Success', text: "{{ session('success') }}", timer: 2000, showConfirmButton: false });
            @endif
            @if(session('error'))
                Swal.fire({ icon: 'error', title: 'Error', text: "{{ session('error') }}" });
            @endif
        });
    </script>
@endif

@if(!empty($writeOffs))
    <div class="alert alert-warning">
        <h5><i class="icon fas fa-exclamation-triangle"></i> Write-Offs Created During Cancellation</h5>
        <p>The following items were written off due to insufficient stock in the destination subshop during cancellation:</p>
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Batch</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Cost</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($writeOffs as $wo)
                    <tr>
                        <td>{{ $wo['item_name'] }}</td>
                        <td>{{ $wo['batch'] }}</td>
                        <td class="text-right">{{ number_format($wo['qty'], 2) }}</td>
                        <td class="text-right">{{ number_format($wo['cost'], 2) }}</td>
                        <td class="text-right">{{ number_format($wo['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Items</h5></div>
            <div class="card-body table-responsive">
                @foreach($transfer->items as $ti)
                    <h6 class="mt-2"><i class="fas fa-box"></i> {{ $ti->item_name_snapshot }} <small class="text-muted">(SKU: {{ $ti->sku_snapshot }})</small></h6>
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Batch</th>
                                <th>Expiry</th>
                                <th>Planned</th>
                                <th>Dispatched</th>
                                <th>Received</th>
                                <th>Damaged</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $i=1; @endphp
                            @foreach($ti->batches as $tib)
                                <tr>
                                    <td>{{ $i++ }}</td>
                                    <td>{{ $tib->batch_number }}</td>
                                    <td>{{ $tib->expire_date ? \Carbon\Carbon::parse($tib->expire_date)->format('Y-m-d') : '-' }}</td>
                                    <td>{{ number_format($tib->planned_qty,3) }}</td>
                                    <td>{{ number_format($tib->dispatched_qty,3) }}</td>
                                    <td>{{ number_format($tib->received_qty,3) }}</td>
                                    <td>{{ number_format($tib->damaged_qty,3) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Audit Trail</h5></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($transfer->audits->sortByDesc('created_at') as $a)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ ucwords(str_replace('_',' ', $a->action)) }}</strong>
                                <div class="text-muted small">{{ $a->created_at? $a->created_at->format('Y-m-d H:i') : '' }}</div>
                            </div>
                            <span class="badge badge-light">#{{ $a->id }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No audit entries.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@endsection
