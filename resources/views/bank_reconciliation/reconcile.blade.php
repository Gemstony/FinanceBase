@extends('adminlte::page')

@section('title', 'Reconcile Statement')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-random"></i> Reconcile Statement #{{ $statement->id }}</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-random"></i> Reconcile #{{ $statement->id }}</h1>
                    <p class="mb-0 text-light">{{ $statement->bankAccount?->account_name ?? '—' }} • {{ $statement->statement_date?->format('Y-m-d') ?? '—' }}</p>
                </div>
                <div>
                    <a href="{{ route('bank-reconciliation.show', $statement->id) }}" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back
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
            <div class="col-md-2">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ $summary['total_transactions'] ?? 0 }}</h3>
                        <p>Total</p>
                    </div>
                    <div class="icon"><i class="fas fa-list"></i></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $summary['matched_transactions'] ?? 0 }}</h3>
                        <p>Matched</p>
                    </div>
                    <div class="icon"><i class="fas fa-check"></i></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $summary['unmatched_transactions'] ?? 0 }}</h3>
                        <p>Unmatched</p>
                    </div>
                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ number_format((float) ($summary['statement_net'] ?? 0), 2) }}</h3>
                        <p>Statement Net</p>
                    </div>
                    <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
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

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <!-- <form method="POST" action="{{ route('bank-reconciliation.auto-match', $statement->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-magic"></i> Auto Match
                    </button>
                </form> -->
                <form method="POST" action="{{ route('bank-reconciliation.reset-matches', $statement->id) }}" class="d-inline ml-2">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="fas fa-undo"></i> Reset Matches
                    </button>
                </form>
            </div>
            <div>
                <form method="POST" action="{{ route('bank-reconciliation.finalize', $statement->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Finalize Reconciliation
                    </button>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                    <div class="card-header">
                        <strong>Bank Statement Lines</strong>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="statementLines">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Description</th>
                                        <th class="text-right">Debit</th>
                                        <th class="text-right">Credit</th>
                                        <th>Matched</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($statement->lines as $l)
                                        <tr data-line-id="{{ $l->id }}" class="js-statement-line {{ ($l->is_matched && !empty($l->matched_journal_entry_id)) ? 'table-success' : '' }}">
                                            <td>{{ $l->transaction_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td>{{ $l->reference ?? '—' }}</td>
                                            <td>{{ $l->description ?? '—' }}</td>
                                            <td class="text-right">{{ number_format((float) $l->debit, 2) }}</td>
                                            <td class="text-right">{{ number_format((float) $l->credit, 2) }}</td>
                                            <td>
                                                @if($l->is_matched && !empty($l->matched_journal_entry_id))
                                                    <span class="badge badge-success">Yes</span>
                                                    @if(!empty($l->matched_journal_entry_id))
                                                        <div><small class="text-muted">JE #{{ (int) $l->matched_journal_entry_id }}</small></div>
                                                    @endif
                                                @else
                                                    <span class="badge badge-secondary">No</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!$l->is_matched || empty($l->matched_journal_entry_id))
                                                    <a href="{{ route('bank-reconciliation.lines.create-journal', [$statement->id, $l->id]) }}" class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-plus"></i> Create Journal Entry
                                                    </a>
                                                @else
                                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                                        <i class="fas fa-plus"></i> Create Journal Entry
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                    <div class="card-header">
                        <strong>System Journal Entries (latest 300)</strong>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="journalEntries">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Ref Type</th>
                                        <th>Description</th>
                                        <th class="text-right">Debit</th>
                                        <th class="text-right">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($journals as $j)
                                        @php
                                            $totalDebit = (float) $j->lines->sum('debit');
                                            $totalCredit = (float) $j->lines->sum('credit');
                                        @endphp
                                        <tr data-journal-id="{{ $j->id }}" class="js-journal-entry">
                                            <td>{{ $j->transaction_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td>{{ $j->reference_type }}</td>
                                            <td>{{ $j->description ?? '—' }}</td>
                                            <td class="text-right">{{ number_format($totalDebit, 2) }}</td>
                                            <td class="text-right">{{ number_format($totalCredit, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <form method="POST" action="{{ route('bank-reconciliation.match-line') }}" id="matchForm" class="mt-2">
                            @csrf
                            <input type="hidden" name="statement_line_id" id="statement_line_id">
                            <input type="hidden" name="journal_entry_id" id="journal_entry_id">

                            <button type="submit" class="btn btn-primary" id="matchBtn" disabled>
                                <i class="fas fa-link"></i> Match Selected
                            </button>
                            <small class="text-muted ml-2">Select a statement line then a journal entry.</small>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
    .js-statement-line.selected, .js-journal-entry.selected { background: #fff3cd !important; }
    .js-statement-line { cursor: pointer; }
    .js-journal-entry { cursor: pointer; }
</style>
@endpush

@push('js')
<script>
$(document).ready(function() {
    function updateMatchButton() {
        const lineId = $('#statement_line_id').val();
        const journalId = $('#journal_entry_id').val();
        $('#matchBtn').prop('disabled', !(lineId && journalId));
    }

    $(document).on('click', '.js-statement-line', function() {
        $('.js-statement-line').removeClass('selected');
        $(this).addClass('selected');
        $('#statement_line_id').val($(this).data('line-id'));
        updateMatchButton();
    });

    $(document).on('click', '.js-journal-entry', function() {
        $('.js-journal-entry').removeClass('selected');
        $(this).addClass('selected');
        $('#journal_entry_id').val($(this).data('journal-id'));
        updateMatchButton();
    });

    var $s = $('#statementLines');
    if ($.fn.DataTable) {
        if ($.fn.DataTable.isDataTable($s)) { $s.DataTable().clear().destroy(); }
        $s.DataTable({
            order: [[0, 'desc']],
            pageLength: 15
        });
    }

    var $j = $('#journalEntries');
    if ($.fn.DataTable) {
        if ($.fn.DataTable.isDataTable($j)) { $j.DataTable().clear().destroy(); }
        $j.DataTable({
            order: [[0, 'desc']],
            pageLength: 15
        });
    }
});
</script>
@endpush
