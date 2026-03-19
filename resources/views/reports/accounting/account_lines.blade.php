@extends('adminlte::page')

@section('title', 'Account Lines')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h1 class="d-none d-md-block text-light"><i class="fas fa-list"></i> Account Transactions</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-list"></i> Account Transactions</h1>
            <div class="small text-light-50">Account ID: {{ $accountId ?? '' }} | As-of: {{ $asOf ?? '' }}</div>
        </div>
        <a href="{{ url()->previous() }}" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>
@stop

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($lines ?? []) as $l)
                            @php $j = $l->journalEntry; @endphp
                            <tr>
                                <td>{{ optional($j)->transaction_date ? optional($j)->transaction_date->toDateString() : '' }}</td>
                                <td>{{ optional($j)->description ?? ($l->description ?? '') }}</td>
                                <td class="text-right">{{ number_format((float) ($l->debit ?? 0), 2) }}</td>
                                <td class="text-right">{{ number_format((float) ($l->credit ?? 0), 2) }}</td>
                                <td>
                                    {{ optional($j)->reference_type ?? '' }}
                                    @if(optional($j)->reference_id)
                                        #{{ optional($j)->reference_id }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if(method_exists(($lines ?? null), 'total') && ($lines->total() === 0))
                            <tr><td colspan="5" class="text-center text-muted p-3">No data</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="p-2">
                @if(method_exists(($lines ?? null), 'links'))
                    {{ $lines->links() }}
                @endif
            </div>
        </div>
    </div>
</div>
@stop
