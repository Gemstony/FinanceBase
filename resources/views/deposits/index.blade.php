@extends('adminlte::page')

@section('title', 'Security Deposits')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-shield-alt"></i> Security Deposits</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-shield-alt"></i> Security Deposits</h1>
                    <p class="mb-0 text-light">Manage held, applied, refunded and forfeited deposits</p>
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
                <li class="breadcrumb-item active" aria-current="page">Security Deposits</li>
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
                <form method="GET" action="{{ route('security-deposits.index') }}" class="mb-3">
                    <div class="bg-light p-2 rounded border">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-4">
                                <label class="small mb-1">Borrower</label>
                                <input type="text" name="borrower" class="form-control" value="{{ request('borrower') }}" placeholder="Borrower name">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small mb-1">Loan Code</label>
                                <input type="text" name="loan_code" class="form-control" value="{{ request('loan_code') }}" placeholder="LN-...">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small mb-1">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="held" @selected(request('status') === 'held')>Held</option>
                                    <option value="applied" @selected(request('status') === 'applied')>Applied</option>
                                    <option value="refunded" @selected(request('status') === 'refunded')>Refunded</option>
                                    <option value="forfeited" @selected(request('status') === 'forfeited')>Forfeited</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                                <a class="btn btn-light border" href="{{ route('security-deposits.index') }}"><i class="fas fa-undo"></i> Reset</a>

                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="depositsTable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Borrower</th>
                                <th>Loan</th>
                                <th class="text-right">Amount</th>
                                <th>Status</th>
                                <th>Held At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $counter = 1;
                            @endphp
                            @forelse($deposits as $d)
                                <tr>
                                    <td>{{ $counter++ }}</td>
                                    <td>{{ $d->customer?->name ?? '—' }}</td>
                                    <td>{{ $d->loan?->loan_code ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $d->amount, 2) }}</td>
                                    <td>
                                        @php
                                            $cls = match((string) $d->status) {
                                                'held' => 'badge-success',
                                                'applied' => 'badge-info',
                                                'refunded' => 'badge-secondary',
                                                'forfeited' => 'badge-dark',
                                                default => 'badge-light',
                                            };
                                        @endphp
                                        <span class="badge {{ $cls }}">{{ ucfirst($d->status) }}</span>
                                    </td>
                                    <td>{{ $d->held_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td>
                                        @if($d->customer)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('security-deposits.borrower', $d->customer) }}">
                                                <i class="fas fa-user"></i> Borrower
                                            </a>
                                        @endif 
                                        @if($d->loan)
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('security-deposits.loan', $d->loan) }}">
                                                <i class="fas fa-file-invoice-dollar"></i> Loan
                                            </a>
                                        @endif

                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No deposits found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $deposits->links() }}
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
    if ($('#depositsTable').length) {
        $('#depositsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [5] },
                { searchable: false, targets: [5] }
            ],
            order: [[5, 'desc']]
        });
    }
});
</script>
@endpush
