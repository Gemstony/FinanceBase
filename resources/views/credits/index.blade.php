@extends('adminlte::page')

@section('title', 'Customer Credits')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-wallet"></i> Customer Credits</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-wallet"></i> Customer Credits</h1>
                    <p class="mb-0 text-light">Manage overpayments and credit balances</p>
                </div>
                <a href="{{ route('dashboard') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Customer Credits</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <form method="GET" action="{{ route('credits.index') }}" class="mb-3">
                    <div class="bg-light p-2 rounded border">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-4">
                                <label class="small mb-1">Borrower</label>
                                <input type="text" name="borrower" class="form-control" value="{{ request('borrower') }}" placeholder="Borrower name">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="available" @selected(request('status') === 'available')>Available</option>
                                    <option value="applied" @selected(request('status') === 'applied')>Applied</option>
                                    <option value="refunded" @selected(request('status') === 'refunded')>Refunded</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">From</label>
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">To</label>
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                            <div class="form-group col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="creditsTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Borrower</th>
                                <th>Loan Source</th>
                                <th class="text-right">Amount</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($credits as $credit)
                                <tr>
                                    <td>{{ $credit->customer?->name ?? '—' }}</td>
                                    <td>{{ $credit->loan?->loan_code ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $credit->amount, 2) }}</td>
                                    <td>
                                        @php
                                            $cls = match((string) $credit->status) {
                                                'available' => 'badge-success',
                                                'applied' => 'badge-info',
                                                'refunded' => 'badge-secondary',
                                                default => 'badge-light',
                                            };
                                        @endphp
                                        <span class="badge {{ $cls }}">{{ ucfirst($credit->status) }}</span>
                                    </td>
                                    <td>{{ $credit->created_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        @if($credit->customer)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('credits.show', $credit->customer) }}">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $credits->links() }}
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script>
$(document).ready(function() {
    if ($('#creditsTable').length) {
        $('#creditsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [5] },
                { searchable: false, targets: [5] }
            ],
            order: [[4, 'desc']]
        });
    }
});
</script>
@endpush
