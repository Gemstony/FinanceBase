@extends('adminlte::page')

@section('title', 'Voucher ' . $voucher->voucher_number)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-receipt"></i> Voucher {{ $voucher->voucher_number }}</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-receipt"></i> {{ $voucher->voucher_number }}</h1>
                    <p class="mb-0 text-light">View voucher details</p>
                </div>
                <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('accounting.vouchers.index') }}">Vouchers</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $voucher->voucher_number }}</li>
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
            <div class="card-header"><strong>Voucher Header</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="small text-muted">Voucher Number</div>
                        <div><strong>{{ $voucher->voucher_number }}</strong></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Type</div>
                        <div>
                            @if($voucher->voucher_type === 'receipt')
                                <span class="badge badge-success">Receipt</span>
                            @else
                                <span class="badge badge-primary">Payment</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Date</div>
                        <div><strong>{{ $voucher->voucher_date?->format('Y-m-d') ?? '—' }}</strong></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Status</div>
                        <div>
                            @if($voucher->status === 'posted')
                                <span class="badge badge-success">Posted</span>
                            @elseif($voucher->status === 'draft')
                                <span class="badge badge-warning">Draft</span>
                            @elseif($voucher->status === 'cancelled')
                                <span class="badge badge-danger">Cancelled</span>
                            @else
                                <span class="badge badge-secondary">{{ $voucher->status }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="small text-muted">Payment Method</div>
                        <div>{{ $voucher->payment_method ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Bank Account</div>
                        <div>{{ $voucher->bankAccount?->account_name ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Source</div>
                        <div>
                            @if($voucher->source_type === 'manual')
                                <span class="badge badge-warning">Manual</span>
                            @else
                                <span class="badge badge-secondary">System</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Created By</div>
                        <div>{{ $voucher->creator?->name ?? '—' }}</div>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="small text-muted">Reference</div>
                    <div>{{ $voucher->reference_type ?? '—' }}{{ $voucher->reference_id ? ' #' . (int) $voucher->reference_id : '' }}</div>
                </div>

                <div class="mt-3">
                    <div class="small text-muted">Description</div>
                    <div>{{ $voucher->description ?? '—' }}</div>
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
                            @foreach($voucher->lines as $l)
                                <tr>
                                    <td>{{ $l->account?->account_name ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $l->debit, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $l->credit, 2) }}</td>
                                    <td>{{ $l->description ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="text-right">Totals</th>
                                <th class="text-right">{{ number_format((float) $totalDebit, 2) }}</th>
                                <th class="text-right">{{ number_format((float) $totalCredit, 2) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-2">
                    <small class="text-muted">Total Amount: {{ number_format((float) $voucher->total_amount, 2) }}</small>
                </div>
            </div>
        </div>
    </div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
