@extends('adminlte::page')

@section('title', 'Sales Transactions - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exchange-alt"></i> Sales Transactions</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exchange-alt"></i> Transactions</h1>
                <div class="small text-light-50">Shop: {{ $subshop->name }}</div>
            </div>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-file-invoice"></i> Invoices</a>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <!-- Summary cards -->
            <div class="row mb-3">
                <div class="col-md-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($transactions->total() ?? 0) }}</h3>
                            <p>Total Transactions</p>
                        </div>
                        <div class="icon"><i class="fas fa-exchange-alt"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($transactions->where('total_amount', '>=', 0)->sum('total_amount'), 2) }}</h3>
                            <p>Total Payments</p>
                        </div>
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($transactions->where('total_amount', '<', 0)->sum('total_amount') * -1, 2) }}</h3>
                            <p>Total Refunds</p>
                        </div>
                        <div class="icon"><i class="fas fa-undo-alt"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($transactions->where('total_amount', '>=', 0)->sum('total_amount') + $transactions->where('total_amount', '<', 0)->sum('total_amount'), 2) }}</h3>
                            <p>Net Amount</p>
                        </div>
                        <div class="icon"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" action="{{ route('sales.transactions.index') }}" class="mb-3">
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}" />
                <div class="bg-light p-2 rounded border">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Search</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Order No / Reference">
                            </div>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date From</label>
                            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date To</label>
                            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Min Amount</label>
                            <input type="number" step="0.01" name="min_amount" value="{{ $minAmount }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Max Amount</label>
                            <input type="number" step="0.01" name="max_amount" value="{{ $maxAmount }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Type</label>
                            <select name="transaction_type" class="form-control">
                                <option value="">All Types</option>
                                <option value="payment" {{ ($transactionType==='payment')?'selected':'' }}>Payments</option>
                                <option value="refund" {{ ($transactionType==='refund')?'selected':'' }}>Refunds</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Payment Method</label>
                            <select name="payment_method" class="form-control">
                                <option value="">All Methods</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method }}" {{ ($paymentMethod===$method)?'selected':'' }}>{{ $method }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Sort</label>
                            <select name="sort" class="form-control">
                                <option value="date_desc" {{ ($sort==='date_desc')?'selected':'' }}>Date: New → Old</option>
                                <option value="date_asc" {{ ($sort==='date_asc')?'selected':'' }}>Date: Old → New</option>
                                <option value="amount_desc" {{ ($sort==='amount_desc')?'selected':'' }}>Amount: High → Low</option>
                                <option value="amount_asc" {{ ($sort==='amount_asc')?'selected':'' }}>Amount: Low → High</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
                            <a class="btn btn-light border" href="{{ route('sales.transactions.index', ['subshop_id'=>$subshop->id]) }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted small">Filtered results: {{ number_format($transactions->count()) }}</div>
                @can('export_sales_transactions')
                <div class="dropdown">
                    <!-- Export Dropdown -->
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="{{ route('sales.transactions.export', ['format' => 'csv'] + request()->query()) }}">
                            <i class="fas fa-file-csv mr-1 text-success"></i> CSV
                        </a>
                        <a class="dropdown-item" href="{{ route('sales.transactions.export', ['format' => 'excel'] + request()->query()) }}">
                            <i class="fas fa-file-excel mr-1 text-success"></i> Excel
                        </a>
                        <a class="dropdown-item" href="{{ route('sales.transactions.export', ['format' => 'pdf'] + request()->query()) }}">
                            <i class="fas fa-file-pdf mr-1 text-danger"></i> PDF
                        </a>
                    </div>
                </div>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="TransactionsTable">
                    <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                        <tr>
                            <th><i class="fas fa-calendar-alt mr-1"></i> Date</th>
                            <th><i class="fas fa-hashtag mr-1"></i> Order No</th>
                            <th><i class="fas fa-user mr-1"></i> Customer</th>
                            <th><i class="fas fa-exchange-alt mr-1"></i> Type</th>
                            <th class="text-right"><i class="fas fa-coins mr-1"></i> Amount</th>
                            <th><i class="fas fa-credit-card mr-1"></i> Payment Method</th>
                            <th><i class="fas fa-hashtag mr-1"></i> Reference</th>
                            <th><i class="fas fa-user-check mr-1"></i> Recorded By</th>
                            <th><i class="fas fa-comment mr-1"></i> Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->transaction_date ? $transaction->transaction_date->format('d M Y') : '-' }}</td>
                            <td><span class="badge badge-primary">{{ $transaction->order->order_no ?? '-' }}</span></td>
                            <td>{{ $transaction->order->customer->name ?? 'No Customer' }}</td>
                            <td>
                                @if($transaction->total_amount < 0)
                                    <span class="badge badge-warning">Refund</span>
                                @else
                                    <span class="badge badge-success">Payment</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($transaction->total_amount < 0)
                                    <span class="text-danger">Tsh {{ number_format(abs($transaction->total_amount), 2) }}</span>
                                @else
                                    <span class="text-success">Tsh {{ number_format($transaction->total_amount, 2) }}</span>
                                @endif
                            </td>
                            <td>{{ $transaction->payment_method ?: '-' }}</td>
                            <td>{{ $transaction->reference_number ?: '-' }}</td>
                            <td>{{ $transaction->user->name ?? 'System' }}</td>
                            <td>{{ $transaction->notes ?: '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5"><i class="fas fa-inbox"></i> No transactions found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-3">
                {{ $transactions->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
    /* Responsive tweaks for summary cards */
    .small-box .inner h3{
        font-size: 1.6rem;
        line-height: 1.2;
        word-break: break-word;
        white-space: normal;
    }
    .small-box .inner p{ margin-bottom: 0; }
    /* Prevent icon overflow */
    .small-box{ position: relative; overflow: hidden; }
    .small-box .icon{ position:absolute; right:10px; top:8px; font-size:36px; opacity:.35; z-index:0; line-height:1; }
    .small-box .inner{ position: relative; z-index:1; }
    @media (max-width: 992px){ .small-box .inner h3{ font-size: 1.4rem; } }
    @media (max-width: 768px){ .small-box .inner h3{ font-size: 1.2rem; } }
    @media (max-width: 576px){ .small-box .inner h3{ font-size: 1.05rem; } .small-box .inner p{ font-size: .8rem; } .small-box .icon{ font-size:28px; right:8px; top:8px; } }
    @media (max-width: 360px){ .small-box .inner h3{ font-size: .95rem; } .small-box .icon{ font-size:24px; right:6px; top:6px; } }
    </style>
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const flashSuccess = @json(session('success'));
    const flashError = @json(session('error'));
    if (flashSuccess) { Swal.fire({ icon:'success', title: flashSuccess, timer: 1800, timerProgressBar: true, showConfirmButton:false }); }
    if (flashError) { Swal.fire({ icon:'error', title: flashError, timer: 2200, showConfirmButton:true }); }
});

$(function () {
    // Initialize DataTable
    $('#TransactionsTable').DataTable({
        "order": [],
        "pageLength": 15,
        "language": {
            "search": "Search transactions:",
            "lengthMenu": "Show _MENU_ transactions per page",
            "zeroRecords": "No transactions found",
            "info": "Showing _START_ to _END_ of _TOTAL_ transactions",
            "infoEmpty": "No transactions available",
            "infoFiltered": "(filtered from _MAX_ total transactions)"
        }
    });
});
</script>
@stop