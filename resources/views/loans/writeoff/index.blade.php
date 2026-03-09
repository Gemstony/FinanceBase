@extends('adminlte::page')

@section('title', 'Write-Offs')

@section('content_header')
 <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
     <div class="card-body">
         <div class="d-flex justify-content-between align-items-center">
             <div>
                 <h1 class="d-none d-md-block text-light"><i class="fas fa-ban"></i> Write-Offs</h1>
                 <h1 class="d-md-none text-light"><i class="fas fa-ban"></i> Write-Offs</h1>
                 <p class="mb-0 text-light">Loan write-off management</p>
             </div>
             <a href="{{ route('loans.management') }}" class="btn btn-light">
                 <i class="fas fa-arrow-left"></i> Loan Management
             </a>
         </div>
     </div>
 </div>
 <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
             <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
             <li class="breadcrumb-item active" aria-current="page">Write-Offs</li>
         </ol>
     </nav>
     <a href="{{ route('loans.management') }}" class="btn btn-light border">
         <i class="fas fa-arrow-left"></i> Back
     </a>
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

    <div class="card mb-3">
        <div class="card-header"><strong>Filters</strong></div>
        <div class="card-body">
            <form method="GET" action="{{ route('writeoffs.index') }}">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="date_from">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="date_to">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="borrower">Borrower</label>
                            <input type="text" class="form-control" id="borrower" name="borrower" value="{{ $filters['borrower'] ?? '' }}" placeholder="Search borrower">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="amount_min">Min Amount</label>
                            <input type="number" step="0.01" class="form-control" id="amount_min" name="amount_min" value="{{ $filters['amount_min'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="amount_max">Max Amount</label>
                            <input type="number" step="0.01" class="form-control" id="amount_max" name="amount_max" value="{{ $filters['amount_max'] ?? '' }}">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="{{ route('writeoffs.index') }}" class="btn btn-light border">Reset</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>All Write-Offs</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="writeoffsTable">
                    <thead class="thead-light">
                        <tr>
                            <th>Write-Off ID</th>
                            <th>Loan ID</th>
                            <th>Borrower</th>
                            <th>Write-Off Date</th>
                            <th>Total Written-Off</th>
                            <th>Approved By</th>
                            <th>Recovered</th>
                            <th>Outstanding</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($writeoffs as $w)
                            <tr>
                                <td>{{ $w->id }}</td>
                                <td>{{ $w->loan_id }}</td>
                                <td>{{ $w->borrower_name ?? '-' }}</td>
                                <td>{{ optional($w->writeoff_date)->format('Y-m-d') }}</td>
                                <td>{{ number_format((float) $w->total_written_off, 2) }}</td>
                                <td>{{ $w->approvedBy?->name ?? '-' }}</td>
                                <td>{{ number_format((float) ($w->total_recovered_amount ?? 0), 2) }}</td>
                                <td>{{ number_format((float) ($w->remaining_written_off_balance ?? 0), 2) }}</td>
                                <td>
                                    <a href="{{ route('writeoffs.show', $w) }}" class="btn btn-sm btn-outline-primary">View</a>
                                    <a href="{{ route('writeoffs.recoveries', $w) }}" class="btn btn-sm btn-outline-secondary">Recoveries</a>
                                    @if($w->loan)
                                        <a href="{{ route('loans.loans.show', $w->loan) }}" class="btn btn-sm btn-outline-success">Loan</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">No write-offs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($writeoffs, 'links'))
                <div class="mt-3">
                    {{ $writeoffs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
$(document).ready(function() {
    if ($('#writeoffsTable').length) {
        $('#writeoffsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [8] },
                { searchable: false, targets: [8] }
            ],
            order: [[0, 'desc']]
        });
    }
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush