@extends('adminlte::page')

@section('title', 'Vouchers')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-receipt"></i> Vouchers</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-receipt"></i> Vouchers</h1>
                    <p class="mb-0 text-light">Receipt and Payment vouchers</p>
                </div>
                <div>
                    <a href="{{ route('accounting.vouchers.create') }}" class="btn btn-success border">
                        <i class="fas fa-plus"></i> New Voucher
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('accounting.accounting_settings.index') }}">Accounting</a></li>
                <li class="breadcrumb-item active" aria-current="page">Vouchers</li>
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

        <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <form method="GET" action="{{ route('accounting.vouchers.index') }}" class="mb-0">
                    <div class="bg-light p-2 rounded border">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Type</label>
                                <select name="voucher_type" class="form-control">
                                    <option value="">All</option>
                                    <option value="receipt" @selected(request('voucher_type') === 'receipt')>Receipt</option>
                                    <option value="payment" @selected(request('voucher_type') === 'payment')>Payment</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Payment Method</label>
                                <select name="payment_method" id="refund_method" class="form-control" required>
                                    <option value="">Select Method</option>
                                    <option value="cash" @selected(request('payment_method') === 'cash')>Cash</option>
                                    <option value="bank_transfer" @selected(request('payment_method') === 'bank_transfer')>Bank Transfer</option>
                                    <option value="mobile_money" @selected(request('payment_method') === 'mobile_money')>Mobile Money</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">From</label>
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">To</label>
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-light ml-1">Reset</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ number_format((int) $summaryTotalCount) }}</h3>
                        <p>Total Vouchers</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>{{ number_format((float) $summaryTotalAmount, 2) }}</h3>
                        <p>Total Amount</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ number_format((float) $summaryReceiptAmount, 2) }}</h3>
                        <p>Receipts ({{ number_format((int) $summaryReceiptCount) }})</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ number_format((float) $summaryPaymentAmount, 2) }}</h3>
                        <p>Payments ({{ number_format((int) $summaryPaymentCount) }})</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0" id="vouchersTable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Voucher #</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th class="text-right">Amount</th>
                                <th>Payment Method</th>
                                <th>Bank Account</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th style="width:1%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $counter = 1;
                            @endphp
                            @forelse($vouchers as $v)
                                <tr>
                                    <td>{{ $counter++ }}</td>
                                    <td><strong>{{ $v->voucher_number }}</strong></td>
                                    <td>
                                        @if($v->voucher_type === 'receipt')
                                            <span class="badge badge-success">Receipt</span>
                                        @else
                                            <span class="badge badge-primary">Payment</span>
                                        @endif
                                    </td>
                                    <td>{{ $v->voucher_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $v->total_amount, 2) }}</td>
                                    <td>{{ $v->payment_method ?? '—' }}</td>
                                    <td>{{ $v->bankAccount?->account_name ?? '—' }}</td>
                                    <td>
                                        @if($v->source_type === 'manual')
                                            <span class="badge badge-warning">Manual</span>
                                        @else
                                            <span class="badge badge-secondary">System</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($v->status === 'posted')
                                            <span class="badge badge-success">Posted</span>
                                        @elseif($v->status === 'draft')
                                            <span class="badge badge-warning">Draft</span>
                                        @elseif($v->status === 'cancelled')
                                            <span class="badge badge-danger">Cancelled</span>
                                        @else
                                            <span class="badge badge-secondary">{{ $v->status }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.vouchers.show', (int) $v->id) }}">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No vouchers found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(method_exists($vouchers, 'links'))
                <div class="card-footer">
                    {{ $vouchers->links() }}
                </div>
            @endif
        </div>
    </div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script>
$(document).ready(function() {
    if ($('#vouchersTable').length) {
        $('#vouchersTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [8] },
                { searchable: false, targets: [8] }
            ],
            order: [[3, 'desc']]
        });
    }
});
</script>
@endpush
