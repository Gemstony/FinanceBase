@extends('adminlte::page')

@section('title', 'Journal Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-clipboard-list"></i> Journal Report</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-clipboard-list"></i> Journal</h1>
                <p class="mb-0 text-light">Journal entries with full debit/credit lines</p>
            </div>
            <a href="{{ route('reports.accounting_reports.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('reports.accounting_reports.index') }}">Accounting Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Journal Report</li>
    </ol>
</nav>
@stop

@section('content')
@php
    $entries = $report['entries'] ?? null;
    $totals = $report['totals'] ?? [];
@endphp

<div class="container-fluid">
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <form method="get" action="{{ route('reports.accounting.journal_report.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label for="from_date">From Date</label>
                            <input type="date" class="form-control form-control-sm" id="from_date" name="from_date" value="{{ request('from_date', $dateFrom ?? '') }}">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="to_date">To Date</label>
                            <input type="date" class="form-control form-control-sm" id="to_date" name="to_date" value="{{ request('to_date', $dateTo ?? '') }}">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="subshop_id">Branch</label>
                            <select class="form-control form-control-sm" id="subshop_id" name="subshop_id">
                                <option value="">All Accessible</option>
                                @foreach(($subshops ?? []) as $s)
                                    <option value="{{ $s->id }}" {{ ($selectedSubshopId ?? null) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="reference">Reference Search</label>
                            <input type="text" class="form-control form-control-sm" id="reference" name="reference" value="{{ request('reference') }}" placeholder="#123 / narration">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="reference_type">Transaction Type</label>
                            <select class="form-control form-control-sm" id="reference_type" name="reference_type">
                                <option value="">All</option>
                                @foreach(($referenceTypes ?? []) as $t)
                                    <option value="{{ $t }}" {{ request('reference_type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="created_by">Created By</label>
                            <select class="form-control form-control-sm" id="created_by" name="created_by">
                                <option value="">All</option>
                                @foreach(($creators ?? []) as $u)
                                    <option value="{{ $u?->id }}" {{ (string) request('created_by') === (string) $u?->id ? 'selected' : '' }}>{{ $u?->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="per_page">Per Page</label>
                            <select class="form-control form-control-sm" id="per_page" name="per_page">
                                @foreach([10,15,20,30,50] as $pp)
                                    <option value="{{ $pp }}" {{ (int) request('per_page', 15) === (int) $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
                            <a href="{{ route('reports.accounting.journal_report.index') }}" class="btn btn-outline-secondary btn-sm mr-2"><i class="fas fa-times"></i> Clear</a>

                            <a href="{{ $exportUrl ?? '#' }}" class="btn btn-success btn-sm mr-2" {{ empty($exportUrl) ? 'aria-disabled=true' : '' }}>
                                <i class="fas fa-file-excel"></i> Excel
                            </a>
                            <a href="{{ $pdfUrl ?? '#' }}" class="btn btn-danger btn-sm mr-2" {{ empty($pdfUrl) ? 'aria-disabled=true' : '' }}>
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                            <button type="button" onclick="window.print()" class="btn btn-outline-dark btn-sm">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small">Entries</div>
                                    <div class="h4 mb-0">{{ number_format((float) ($totals['entries_count'] ?? 0)) }}</div>
                                </div>
                                <i class="fas fa-list fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small">Total Debits</div>
                                    <div class="h4 mb-0">{{ number_format((float) ($totals['total_debit'] ?? 0), 2) }}</div>
                                </div>
                                <i class="fas fa-arrow-circle-down fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small">Total Credits</div>
                                    <div class="h4 mb-0">{{ number_format((float) ($totals['total_credit'] ?? 0), 2) }}</div>
                                </div>
                                <i class="fas fa-arrow-circle-up fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if(!$entries || $entries->count() === 0)
                <div class="alert alert-info mb-0">
                    No journal entries found for selected period
                </div>
            @else
                @foreach($entries as $j)
                    @php
                        $totalDebit = (float) ($j->total_debit ?? ($j->lines?->sum('debit') ?? 0));
                        $totalCredit = (float) ($j->total_credit ?? ($j->lines?->sum('credit') ?? 0));
                        $isBalanced = (bool) ($j->is_balanced ?? (abs($totalDebit - $totalCredit) < 0.005));
                    @endphp

                    <div class="border rounded mb-3" style="background:#fff;">
                        <div class="p-2 border-bottom" style="background:#f8f9fa;">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div><strong>Journal Entry #{{ $j->id }}</strong> <span class="text-muted">({{ $j->transaction_date?->format('Y-m-d') ?? '—' }})</span></div>
                                    <div class="small text-muted">Type: <strong>{{ $j->reference_type ?? '—' }}</strong> | Reference: <strong>{{ $j->reference_id ?? '—' }}</strong></div>
                                    <div class="small">{{ $j->description ?? '—' }}</div>
                                    <div class="small text-muted">Created By: {{ $j->creator?->name ?? '—' }}</div>
                                </div>
                                <div class="text-right">
                                    <a href="{{ route('reports.accounting.general_ledger.journal_entry', ['journalEntryId' => $j->id, 'subshop_id' => request('subshop_id')]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    @if(!$isBalanced)
                                        <div class="mt-2">
                                            <span class="badge badge-danger">Unbalanced Entry</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Account</th>
                                            <th class="text-right" style="width: 160px;">Debit</th>
                                            <th class="text-right" style="width: 160px;">Credit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(($j->lines ?? []) as $line)
                                            <tr>
                                                <td class="pl-3">{{ $line->account?->account_code ?? '' }} - {{ $line->account?->account_name ?? '' }}</td>
                                                <td class="text-right">{{ number_format((float) ($line->debit ?? 0), 2) }}</td>
                                                <td class="text-right">{{ number_format((float) ($line->credit ?? 0), 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="text-right">Totals</th>
                                            <th class="text-right">{{ number_format($totalDebit, 2) }}</th>
                                            <th class="text-right">{{ number_format($totalCredit, 2) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="d-flex justify-content-end">
                    {{ $entries->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
