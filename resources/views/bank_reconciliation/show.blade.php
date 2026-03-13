@extends('adminlte::page')

@section('title', 'Bank Statement')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-file-invoice"></i> Statement #{{ $statement->id }}</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-file-invoice"></i> #{{ $statement->id }}</h1>
                    <p class="mb-0 text-light">{{ $statement->bankAccount?->account_name ?? '—' }} • {{ $statement->statement_date?->format('Y-m-d') ?? '—' }}</p>
                </div>
                <div>
                    <a href="{{ route('bank-reconciliation.index') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="{{ route('bank-reconciliation.reconcile', $statement->id) }}" class="btn btn-success border">
                        <i class="fas fa-random"></i> Reconcile
                    </a>
                </div>
            </div>
        </div>
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

        <div class="row">
            <div class="col-md-3">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ $summary['total_transactions'] ?? 0 }}</h3>
                        <p>Total Transactions</p>
                    </div>
                    <div class="icon"><i class="fas fa-list"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $summary['matched_transactions'] ?? 0 }}</h3>
                        <p>Matched</p>
                    </div>
                    <div class="icon"><i class="fas fa-check"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $summary['unmatched_transactions'] ?? 0 }}</h3>
                        <p>Unmatched</p>
                    </div>
                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ number_format((float) ($summary['difference'] ?? 0), 2) }}</h3>
                        <p>Difference</p>
                    </div>
                    <div class="icon"><i class="fas fa-not-equal"></i></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Status:</strong> {{ ucfirst((string) $statement->status) }}</p>
                        <p class="mb-1"><strong>Reference:</strong> {{ $statement->reference_number ?? '—' }}</p>
                        <p class="mb-0"><strong>File:</strong> {{ $statement->file_path ?? '—' }}</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Opening Balance:</strong> {{ number_format((float) $statement->opening_balance, 2) }}</p>
                        <p class="mb-1"><strong>Closing Balance:</strong> {{ number_format((float) $statement->closing_balance, 2) }}</p>
                        <p class="mb-0"><strong>Balance Move:</strong> {{ number_format((float) ($summary['statement_balance_move'] ?? 0), 2) }}</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Statement Net:</strong> {{ number_format((float) ($summary['statement_net'] ?? 0), 2) }}</p>
                        <p class="mb-1"><strong>Ledger Net:</strong> {{ number_format((float) ($summary['ledger_net'] ?? 0), 2) }}</p>
                        <p class="mb-0"><strong>Reconciled At:</strong> {{ $statement->reconciled_at?->format('Y-m-d H:i') ?? '—' }}</p>
                    </div>
                </div>

                <hr>

                <form method="POST" action="{{ route('bank-reconciliation.import', $statement->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-6">
                            <label>Import CSV</label>
                            <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
                        </div>
                        <div class="form-group col-md-6">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Import
                            </button>
                            <a href="{{ route('bank-reconciliation.reconcile', $statement->id) }}" class="btn btn-outline-success">
                                <i class="fas fa-random"></i> Reconcile Now
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="statementLinesTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th>Matched</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($statement->lines as $l)
                                <tr>
                                    <td>{{ $l->transaction_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $l->reference ?? '—' }}</td>
                                    <td>{{ $l->description ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $l->debit, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $l->credit, 2) }}</td>
                                    <td>
                                        @if($l->is_matched)
                                            <span class="badge badge-success">Yes</span>
                                        @else
                                            <span class="badge badge-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No statement lines yet. Import a CSV to begin.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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
    var $t = $('#statementLinesTable');
    if ($.fn.DataTable) {
        if ($.fn.DataTable.isDataTable($t)) {
            $t.DataTable().clear().destroy();
        }
        $t.DataTable({
            order: [[0, 'desc']],
            pageLength: 25
        });
    }
});
</script>
@endpush
