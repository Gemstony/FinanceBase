@extends('adminlte::page')

@section('title', 'Trial Balance Report')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-balance-scale"></i> Trial Balance</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-balance-scale"></i> Trial Balance</h1>
                <p class="mb-0 text-light">As-of: <strong>{{ $asOf ?? '' }}</strong></p>
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
        <li class="breadcrumb-item active" aria-current="page">Trial Balance</li>
    </ol>
</nav>
@stop

@section('content')
@php
    $tree = $report['tree'] ?? [];
    $totals = $report['totals'] ?? [];
    $validation = $report['validation'] ?? [];

    $fmt = function ($v) {
        $n = (float) ($v ?? 0);
        $abs = number_format(abs($n), 2);
        return $n < 0 ? '(' . $abs . ')' : $abs;
    };
@endphp

<div class="container-fluid">
    <div class="card">
        <div class="card-body">

            <form method="get" action="{{ route('reports.accounting.trial_balance.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="as_of">As-of Date</label>
                            <input type="date" class="form-control form-control-sm" id="as_of" name="as_of" value="{{ $asOf ?? '' }}" required>
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
                            <label for="account_class_id">Account Class (optional)</label>
                            <select class="form-control form-control-sm" id="account_class_id" name="account_class_id">
                                <option value="">All Classes</option>
                                @foreach(($accountClasses ?? []) as $c)
                                    <option value="{{ $c->id }}" {{ request('account_class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>&nbsp;</label>
                            <div class="custom-control custom-checkbox">
                                <input class="custom-control-input" type="checkbox" id="hide_zero" name="hide_zero" value="1" {{ !empty($hideZero) ? 'checked' : '' }}>
                                <label for="hide_zero" class="custom-control-label">Hide zero balances</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Apply</button>
                            <a href="{{ route('reports.accounting.trial_balance.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="row m-2">
                <div class="col-12 text-center">
                    <div class="btn-group" role="group">
                        <a href="{{ $exportUrl ?? '#' }}" class="btn btn-success"><i class="fas fa-file-excel"></i> Export to Excel</a>
                        <a href="{{ $pdfUrl ?? '#' }}" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Export to PDF</a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                    </div>
                </div>
            </div>

            @if(!empty($validation) && empty($validation['balanced']))
                <div class="alert alert-warning">
                    <strong>Not Balanced:</strong>
                    Difference (Debit - Credit): <strong>{{ $fmt($validation['difference'] ?? 0) }}</strong>
                </div>
            @else
                <div class="alert alert-success">
                    <strong>Balanced:</strong> Total Debits = Total Credits
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th style="width: 20%">Account</th>
                            <th style="width: 50%">Name</th>
                            <th class="text-right" style="width: 15%">Debit</th>
                            <th class="text-right" style="width: 15%">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($tree ?? []) as $classNode)
                            <tr class="bg-light">
                                <td colspan="4"><strong>{{ $classNode['class_name'] ?? '' }}</strong></td>
                            </tr>

                            @foreach(($classNode['groups'] ?? []) as $groupNode)
                                <tr>
                                    <td colspan="4" style="padding-left: 16px;"><strong>{{ $groupNode['group_name'] ?? '' }}</strong></td>
                                </tr>

                                @foreach(($groupNode['accounts'] ?? []) as $acc)
                                    <tr>
                                        <td style="padding-left: 32px;">
                                            <a href="{{ route('reports.accounting.trial_balance.account_lines', [
                                                'accountId' => $acc['account_id'] ?? 0,
                                                'as_of' => $asOf ?? null,
                                                'subshop_id' => $selectedSubshopId ?? null,
                                            ]) }}" target="_blank">
                                                {{ $acc['account_code'] ?? '' }}
                                            </a>
                                        </td>
                                        <td>{{ $acc['account_name'] ?? '' }}</td>
                                        <td class="text-right">{{ $fmt($acc['debit'] ?? 0) }}</td>
                                        <td class="text-right">{{ $fmt($acc['credit'] ?? 0) }}</td>
                                    </tr>
                                @endforeach

                                <tr class="bg-white">
                                    <td colspan="2" style="padding-left: 16px;"><strong>Subtotal - {{ $groupNode['group_name'] ?? '' }}</strong></td>
                                    <td class="text-right"><strong>{{ $fmt($groupNode['subtotal_debit'] ?? 0) }}</strong></td>
                                    <td class="text-right"><strong>{{ $fmt($groupNode['subtotal_credit'] ?? 0) }}</strong></td>
                                </tr>
                            @endforeach

                            <tr class="bg-white border-top">
                                <td colspan="2"><strong>Total - {{ $classNode['class_name'] ?? '' }}</strong></td>
                                <td class="text-right"><strong>{{ $fmt($classNode['subtotal_debit'] ?? 0) }}</strong></td>
                                <td class="text-right"><strong>{{ $fmt($classNode['subtotal_credit'] ?? 0) }}</strong></td>
                            </tr>
                        @endforeach

                        @if(empty($tree ?? []))
                            <tr>
                                <td colspan="4" class="text-center text-muted p-3">No data</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr class="bg-light">
                            <th colspan="2">Grand Total</th>
                            <th class="text-right">{{ $fmt($totals['total_debit'] ?? 0) }}</th>
                            <th class="text-right">{{ $fmt($totals['total_credit'] ?? 0) }}</th>
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
