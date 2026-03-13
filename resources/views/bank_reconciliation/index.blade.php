@extends('adminlte::page')

@section('title', 'Bank Reconciliation')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-balance-scale"></i> Bank Reconciliation</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-balance-scale"></i> Reconciliation</h1>
                    <p class="mb-0 text-light">Import bank statements and reconcile with journal entries</p>
                </div>
                <div>
                    <a href="{{ route('bank-reconciliation.create') }}" class="btn btn-success border">
                        <i class="fas fa-plus"></i> New Statement
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Bank Reconciliation</li>
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
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="bankStatementsTable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Bank Account</th>
                                <th class="text-right">Opening</th>
                                <th class="text-right">Closing</th>
                                <th>Status</th>
                                <th>Reconciled At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($statements as $s)
                                <tr>
                                    <td>#{{ $s->id }}</td>
                                    <td>{{ $s->statement_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $s->bankAccount?->account_name ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $s->opening_balance, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $s->closing_balance, 2) }}</td>
                                    <td>
                                        @php
                                            $status = (string) ($s->status ?? 'draft');
                                        @endphp
                                        @if($status === 'reconciled')
                                            <span class="badge badge-success">Reconciled</span>
                                        @elseif($status === 'in_progress')
                                            <span class="badge badge-warning">In Progress</span>
                                        @else
                                            <span class="badge badge-secondary">Draft</span>
                                        @endif
                                    </td>
                                    <td>{{ $s->reconciled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('bank-reconciliation.show', $s->id) }}">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a class="btn btn-sm btn-outline-success" href="{{ route('bank-reconciliation.reconcile', $s->id) }}">
                                            <i class="fas fa-random"></i> Reconcile
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No bank statements found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $statements->links() }}
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
    var $t = $('#bankStatementsTable');
    if ($.fn.DataTable) {
        if ($.fn.DataTable.isDataTable($t)) {
            $t.DataTable().clear().destroy();
        }
        $t.DataTable({
            order: [[0, 'desc']],
            pageLength: 15
        });
    }
});
</script>
@endpush
