@extends('adminlte::page')

@section('title', 'Transfers')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exchange-alt"></i> Transfers</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exchange-alt"></i> Transfers</h1>
                <div class="small text-light-50">Create, approve, dispatch and receive stock between subshops</div>
            </div>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-light"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('items.index') }}" class="btn btn-outline-light"><i class="fas fa-box"></i> Items</a>
            </div>
        </div>
    </div>
@endsection

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('transfers.index') }}" class="form-inline">
            <label class="mr-2">Status</label>
            <select name="status" class="form-control form-control-sm mr-2">
                <option value="">All</option>
                @foreach(['draft','approved','dispatched','partially_received','received','cancelled'] as $st)
                    <option value="{{ $st }}" {{ request('status')===$st ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ', $st)) }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-striped table-hover" id="TransfersTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Source</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $t)
                    <tr>
                        <td data-order="{{ $t->id }}">#{{ $t->id }}</td>
                        <td>{{ $t->sourceSubshop->name ?? '-' }}</td>
                        <td>{{ $t->destinationSubshop->name ?? '-' }}</td>
                        <td><span class="badge badge-{{ $t->status==='received' ? 'success' : ($t->status==='dispatched' ? 'info' : ($t->status==='approved' ? 'primary' : ($t->status==='partially_received' ? 'warning' : 'secondary'))) }}">{{ ucwords(str_replace('_',' ', $t->status)) }}</span></td>
                        <td>{{ $t->items->count() }}</td>
                        <td>{{ $t->created_at? $t->created_at->format('Y-m-d H:i') : '-' }}</td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a class="btn btn-outline-info" href="{{ route('transfers.show', $t) }}"><i class="fas fa-eye"></i> View</a>

                                @can('approve_items_transfers')
                                @if($t->status==='draft')
                                    <form method="POST" action="{{ route('transfers.approve', $t) }}" class="approve-form">
                                        
                                        @csrf
                                        <button class="btn btn-outline-primary"><i class="fas fa-check"></i> Approve</button>
                                    </form>
                                @endif
                               

                                @if($t->status==='approved')
                                    <form method="POST" action="{{ route('transfers.dispatch', $t) }}" class="dispatch-form">
                                        
                                        @csrf
                                        <button class="btn btn-outline-info"><i class="fas fa-truck"></i> Dispatch</button>
                                    </form>
                                @endif
                                @if(in_array($t->status, ['dispatched','partially_received']))
                                    <a class="btn btn-outline-success" href="{{ route('transfers.receive.form', $t) }}"><i class="fas fa-clipboard-check"></i> Receive</a>
                                @endif
                                @endcan
                                @can('cancel_items_transfers')
                                @if(!in_array($t->status, ['received','cancelled']))
                                    <form method="POST" action="{{ route('transfers.cancel', $t) }}" class="cancel-form">
                                        @csrf
                                        <button class="btn btn-outline-danger"><i class="fas fa-times"></i> Cancel</button>
                                    </form>
                                @endif
                                @endcan

                                
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No transfers found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div>
            {{ $transfers->appends(request()->query())->links() }}
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<!-- <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script> -->
<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1"></script> -->
<script>
$(document).ready(function() {
    // Initialize DataTable
   
// Initialize DataTable
    $('#TransfersTable').DataTable({
        order: [[0, 'desc']],
        language: {
            emptyTable: 'No transfers found.'
        }
    });
})

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

    document.addEventListener("DOMContentLoaded", function () {
        const withConfirm = (selector, options) => {
            document.querySelectorAll(selector).forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    Swal.fire(options).then((result) => {
                        if (result.isConfirmed) { form.submit(); }
                    });
                });
            });
        };

        withConfirm('.approve-form', {
            title: 'Approve transfer?',
            text: 'This will move the transfer to Approved.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, approve',
            cancelButtonText: 'No'
        });

        withConfirm('.dispatch-form', {
            title: 'Dispatch transfer?',
            text: 'Stock will be deducted from source batches.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, dispatch',
            cancelButtonText: 'No'
        });

        withConfirm('.cancel-form', {
            title: 'Are you sure?',
            text: 'Cancel this transfer?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, cancel it!',
            cancelButtonText: 'No'
        });
    });
</script>

@endpush
@endsection
