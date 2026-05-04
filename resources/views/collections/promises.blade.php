@extends('adminlte::page')

@section('title', 'Promises to Pay')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-handshake"></i> Promises to Pay</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-handshake"></i> PTP</h1>
                <div class="small text-light-50">Track and manage customer payment promises</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.collections') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-hand-holding-usd"></i> Worklist</a>
                <a href="{{ route('collections.schedule') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-calendar"></i> Schedule</a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('risk.collections') }}">Collections</a></li>
                <li class="breadcrumb-item active" aria-current="page">Promises</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $stats['total_promises'] ?? 0 }}</h3>
                        <p>Total Promises</p>
                    </div>
                    <div class="icon"><i class="fas fa-handshake"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $stats['pending_promises'] ?? 0 }}</h3>
                        <p>Pending</p>
                    </div>
                    <div class="icon"><i class="fas fa-clock"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $stats['fulfilled_promises'] ?? 0 }}</h3>
                        <p>Fulfilled</p>
                    </div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>

        </div>

        <!-- Statistics Cards -->
        <div class="row">
            
            <div class="col-md-4">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $stats['broken_promises'] ?? 0 }}</h3>
                        <p>Broken</p>
                    </div>
                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ $stats['fulfillment_rate'] ?? 0 }}<sup style="font-size: 20px">%</sup></h3>
                        <p>Success Rate</p>
                    </div>
                    <div class="icon"><i class="fas fa-percentage"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>{{ number_format($stats['total_promised_amount'] ?? 0, 0) }}</h3>
                        <p>Promised</p>
                    </div>
                    <div class="icon"><i class="fas fa-money-bill"></i></div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        @if(isset($overduePromises) && $overduePromises->count() > 0)
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> {{ $overduePromises->count() }} Promise(s) Overdue</h5>
                </div>
            </div>
        </div>
        @endif

        @if(isset($dueToday) && $dueToday->count() > 0)
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-warning">
                    <h5><i class="fas fa-calendar-day"></i> {{ $dueToday->count() }} Promise(s) Due Today</h5>
                </div>
            </div>
        </div>
        @endif

        <!-- Filter Panel -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter"></i> Filter Promises</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-2">
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="fulfilled" {{ request('status') == 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                                    <option value="broken" {{ request('status') == 'broken' ? 'selected' : '' }}>Broken</option>
                                    <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="promise_type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="full_payment" {{ request('promise_type') == 'full_payment' ? 'selected' : '' }}>Full Payment</option>
                                    <option value="partial_payment" {{ request('promise_type') == 'partial_payment' ? 'selected' : '' }}>Partial Payment</option>
                                    <option value="installment_resumption" {{ request('promise_type') == 'installment_resumption' ? 'selected' : '' }}>Resume Installments</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" placeholder="Promise From">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" placeholder="Promise To">
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Customer/Loan...">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Filter</button>
                                <a class="btn btn-light border" href="{{ route('collections.promises') }}"><i class="fas fa-undo"></i> Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promises Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Promises to Pay</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="promisesTable">
                                <thead>
                                    <tr>
                                        <th>Promise Date</th>
                                        <th>Customer</th>
                                        <th>Loan</th>
                                        <th>Type</th>
                                        <th>Amount Promised</th>
                                        <th>Status</th>
                                        <th>Fulfilled</th>
                                        <th>%</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($promises ?? [] as $promise)
                                        <tr class="{{ $promise->isOverdue() ? 'table-danger' : ($promise->isFulfilled() ? 'table-success' : ($promise->promise_date->isToday() ? 'table-warning' : '')) }}">
                                            <td>
                                                {{ $promise->promise_date->format('Y-m-d') }}
                                                @if($promise->isOverdue())
                                                    <span class="badge badge-danger">Overdue</span>
                                                @elseif($promise->promise_date->isToday())
                                                    <span class="badge badge-warning">Today</span>
                                                @elseif($promise->promise_date->isTomorrow())
                                                    <span class="badge badge-info">Tomorrow</span>
                                                @endif
                                            </td>
                                            <td>{{ $promise->customer->name ?? 'N/A' }}</td>
                                            <td>
                                                <a href="{{ route('loans.loans.show', $promise->loan) }}">
                                                    {{ $promise->loan->loan_code ?? 'N/A' }}
                                                </a>
                                            </td>
                                            <td>{{ ucwords(str_replace('_', ' ', $promise->promise_type)) }}</td>
                                            <td class="font-weight-bold">{{ number_format($promise->amount_promised, 2) }}</td>
                                            <td>
                                                <span class="badge {{ $promise->status === 'fulfilled' ? 'bg-success' : ($promise->status === 'broken' ? 'bg-danger' : ($promise->status === 'overdue' ? 'bg-dark' : 'bg-warning')) }}">
                                                    {{ ucwords($promise->status) }}
                                                </span>
                                            </td>
                                            <td class="{{ $promise->amount_fulfilled > 0 ? 'text-success' : '' }}">
                                                {{ $promise->amount_fulfilled > 0 ? number_format($promise->amount_fulfilled, 2) : '-' }}
                                            </td>
                                            <td>
                                                @if($promise->amount_fulfilled > 0)
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar {{ $promise->getFulfillmentPercentage() >= 100 ? 'bg-success' : 'bg-warning' }}" role="progressbar" style="width: {{ $promise->getFulfillmentPercentage() }}%">
                                                            {{ $promise->getFulfillmentPercentage() }}%
                                                        </div>
                                                    </div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if($promise->status === 'pending')
                                                    <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#fulfillPromiseModal" data-promise-id="{{ $promise->id }}" data-amount="{{ $promise->amount_promised }}">
                                                        <i class="fas fa-check"></i> Fulfill
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#breakPromiseModal" data-promise-id="{{ $promise->id }}">
                                                        <i class="fas fa-times"></i> Break
                                                    </button>
                                                @endif
                                                <a href="{{ route('loans.loans.show', $promise->loan) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">No promises to pay found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fulfill Promise Modal -->
    <div class="modal fade" id="fulfillPromiseModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('collections.record-promise') }}">
                    @csrf
                    <input type="hidden" name="promise_id" id="fulfillPromiseId">
                    <input type="hidden" name="action" value="fulfill">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Promise Fulfillment</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Amount Paid</label>
                            <input type="number" name="amount_paid" id="fulfillAmount" class="form-control" step="0.01" min="0" required>
                            <small class="form-text text-muted">Amount promised: <span id="promisedAmount"></span></small>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Break Promise Modal -->
    <div class="modal fade" id="breakPromiseModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('collections.record-promise') }}">
                    @csrf
                    <input type="hidden" name="promise_id" id="breakPromiseId">
                    <input type="hidden" name="action" value="break">
                    <div class="modal-header">
                        <h5 class="modal-title">Mark Promise as Broken</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> This will mark the promise as broken and may trigger escalation procedures.
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Why was the promise broken?" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Mark as Broken</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(document).ready(function() {
    $('#promisesTable').DataTable({
        responsive: true,
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf']
    });

    // Pass promise data to fulfill modal
    $('#fulfillPromiseModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var promiseId = button.data('promise-id');
        var amount = button.data('amount');
        $('#fulfillPromiseId').val(promiseId);
        $('#fulfillAmount').val(amount);
        $('#promisedAmount').text(amount.toLocaleString('en-US', {minimumFractionDigits: 2}));
    });

    // Pass promise ID to break modal
    $('#breakPromiseModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var promiseId = button.data('promise-id');
        $('#breakPromiseId').val(promiseId);
    });
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
