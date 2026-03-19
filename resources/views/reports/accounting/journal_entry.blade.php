@extends('adminlte::page')

@section('title', 'Journal Entry #' . ($journal->id ?? ''))

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-clipboard-list"></i> Journal Entry #{{ $journal->id ?? '' }}</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-clipboard-list"></i> Journal #{{ $journal->id ?? '' }}</h1>
                <p class="mb-0 text-light">View full journal entry lines</p>
            </div>
            <a href="{{ route('reports.accounting.general_ledger.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('reports.accounting_reports.index') }}">Accounting Reports</a></li>
        <li class="breadcrumb-item"><a href="{{ route('reports.accounting.general_ledger.index') }}">General Ledger</a></li>
        <li class="breadcrumb-item active" aria-current="page">Journal Entry #{{ $journal->id ?? '' }}</li>
    </ol>
</nav>
@stop

@section('content')
@php
    $totalDebit = (float) ($journal?->lines?->sum('debit') ?? 0);
    $totalCredit = (float) ($journal?->lines?->sum('credit') ?? 0);
@endphp

<div class="container-fluid">
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-header"><strong>Journal Header</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="small text-muted">Reference</div>
                    <div><strong>#{{ $journal->id ?? '' }}</strong></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Date</div>
                    <div><strong>{{ $journal->transaction_date?->format('Y-m-d') ?? '—' }}</strong></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Type</div>
                    <div><strong>{{ $journal->reference_type ?? '—' }}</strong></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Ref ID</div>
                    <div><strong>{{ $journal->reference_id ?? '—' }}</strong></div>
                </div>
            </div>

            <div class="row mt-2">
                <div class="col-md-6">
                    <div class="small text-muted">Created By</div>
                    <div><strong>{{ $journal->creator?->name ?? '—' }}</strong></div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Description</div>
                    <div>{{ $journal->description ?? '—' }}</div>
                </div>
            </div>

            <hr>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Account</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($journal->lines ?? []) as $l)
                            <tr>
                                <td>{{ $l->account?->account_code ?? '' }} - {{ $l->account?->account_name ?? '—' }}</td>
                                <td class="text-right">{{ number_format((float) ($l->debit ?? 0), 2) }}</td>
                                <td class="text-right">{{ number_format((float) ($l->credit ?? 0), 2) }}</td>
                                <td>{{ $l->description ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="text-right">Totals</th>
                            <th class="text-right">{{ number_format($totalDebit, 2) }}</th>
                            <th class="text-right">{{ number_format($totalCredit, 2) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
