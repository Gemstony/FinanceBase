@extends('adminlte::page')

@section('title', 'Daily Collections Schedule')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-calendar-alt"></i> Daily Collections Schedule</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-calendar-alt"></i> Schedule</h1>
                <div class="small text-light-50">Plan and track daily collection activities</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.collections') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-hand-holding-usd"></i> Worklist</a>
                <a href="{{ route('collections.actions') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-tasks"></i> Actions</a>
                <a href="{{ route('collections.promises') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-handshake"></i> Promises</a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('risk.collections') }}">Collections</a></li>
                <li class="breadcrumb-item active" aria-current="page">Schedule</li>
            </ol>
        </nav>
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="prevDate">
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" id="todayDate">
                Today
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="nextDate">
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <!-- Date Display -->
        <div class="row mb-3">
            <div class="col-md-12 text-center">
                <h2 id="currentDateDisplay">{{ $date->format('l, F j, Y') }}</h2>
                <p class="text-muted">{{ $date->diffForHumans() }}</p>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $todaysActions->count() + $duePromises->count() }}</h3>
                        <p>Total Activities</p>
                    </div>
                    <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ $todaysActions->count() }}</h3>
                        <p>Scheduled Actions</p>
                    </div>
                    <div class="icon"><i class="fas fa-tasks"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $duePromises->count() }}</h3>
                        <p>Promises Due</p>
                    </div>
                    <div class="icon"><i class="fas fa-handshake"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ number_format($todaysActions->sum('amount_collected') + $duePromises->sum('amount_fulfilled'), 0) }}</h3>
                        <p>Expected Collections</p>
                    </div>
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Scheduled Actions -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title"><i class="fas fa-tasks"></i> Scheduled Actions</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool text-white" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if($todaysActions->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Action</th>
                                            <th>Customer</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($todaysActions as $action)
                                            <tr class="{{ $action->status === 'completed' ? 'table-success' : ($action->status === 'overdue' ? 'table-danger' : '') }}">
                                                <td>
                                                    @if($action->scheduled_at)
                                                        {{ $action->scheduled_at->format('H:i') }}
                                                    @else
                                                        <span class="text-muted">Any time</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <i class="fas {{ $action->action_type === 'phone_call' ? 'fa-phone' : ($action->action_type === 'field_visit' ? 'fa-walking' : 'fa-hand-holding-usd') }}"></i>
                                                        {{ ucwords(str_replace('_', ' ', $action->action_type)) }}
                                                    </span>
                                                </td>
                                                <td>{{ $action->customer->name ?? 'N/A' }}</td>
                                                <td>
                                                    <span class="badge {{ $action->status === 'completed' ? 'bg-success' : ($action->status === 'overdue' ? 'bg-danger' : 'bg-warning') }}">
                                                        {{ ucwords($action->status) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($action->status !== 'completed')
                                                        <button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#completeActionModal" data-action-id="{{ $action->id }}">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    @endif
                                                    <a href="{{ route('loans.loans.show', $action->loan) }}" class="btn btn-xs btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center p-4 text-muted">
                                <i class="fas fa-calendar-check fa-3x mb-3"></i>
                                <p>No actions scheduled for {{ $date->format('F j') }}.</p>
                                <a href="{{ route('risk.collections') }}" class="btn btn-sm btn-primary">Go to Worklist</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Promises Due -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h3 class="card-title"><i class="fas fa-handshake"></i> Promises Due</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if($duePromises->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Loan</th>
                                            <th>Amount Promised</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($duePromises as $promise)
                                            <tr class="{{ $promise->isFulfilled() ? 'table-success' : '' }}">
                                                <td>{{ $promise->customer->name ?? 'N/A' }}</td>
                                                <td>
                                                    <a href="{{ route('loans.loans.show', $promise->loan) }}">
                                                        {{ $promise->loan->loan_code ?? 'N/A' }}
                                                    </a>
                                                </td>
                                                <td class="font-weight-bold">{{ number_format($promise->amount_promised, 2) }}</td>
                                                <td>
                                                    <span class="badge {{ $promise->status === 'fulfilled' ? 'bg-success' : ($promise->status === 'broken' ? 'bg-danger' : 'bg-warning') }}">
                                                        {{ ucwords($promise->status) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($promise->status === 'pending')
                                                        <button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#fulfillPromiseModal" data-promise-id="{{ $promise->id }}" data-amount="{{ $promise->amount_promised }}">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center p-4 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <p>No promises due for {{ $date->format('F j') }}.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Overview -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calendar-week"></i> Week at a Glance</h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            @for($i = 0; $i < 7; $i++)
                                @php
                                    $dayDate = $date->copy()->startOfWeek()->addDays($i);
                                    $isToday = $dayDate->isToday();
                                    $isCurrentDay = $dayDate->isSameDay($date);
                                    $dayActions = $weekActions[$dayDate->toDateString()] ?? collect();
                                    $dayPromises = $weekPromises[$dayDate->toDateString()] ?? collect();
                                @endphp
                                <div class="col-md-1 col-3 mb-2">
                                    <a href="?date={{ $dayDate->toDateString() }}" class="text-decoration-none">
                                        <div class="card {{ $isCurrentDay ? 'bg-primary' : ($isToday ? 'bg-info' : '') }}">
                                            <div class="card-body p-2">
                                                <div class="small {{ $isCurrentDay || $isToday ? 'text-white' : 'text-muted' }}">{{ $dayDate->format('D') }}</div>
                                                <div class="h5 mb-0 {{ $isCurrentDay || $isToday ? 'text-white' : '' }}">{{ $dayDate->format('j') }}</div>
                                                @if($dayActions->count() > 0 || $dayPromises->count() > 0)
                                                    <span class="badge {{ $isCurrentDay ? 'badge-light' : 'badge-primary' }}">
                                                        {{ $dayActions->count() + $dayPromises->count() }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Complete Action Modal -->
    <div class="modal fade" id="completeActionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('collections.record-action') }}">
                    @csrf
                    <input type="hidden" name="action_id" id="scheduleCompleteActionId">
                    <div class="modal-header">
                        <h5 class="modal-title">Complete Scheduled Action</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Outcome</label>
                            <select name="outcome" class="form-control" required>
                                <option value="">Select outcome...</option>
                                <option value="successful_payment">Successful Payment</option>
                                <option value="promise_made">Promise Made</option>
                                <option value="no_contact">No Contact</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount Collected</label>
                            <input type="number" name="amount_collected" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Complete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fulfill Promise Modal -->
    <div class="modal fade" id="fulfillPromiseModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('collections.record-promise') }}">
                    @csrf
                    <input type="hidden" name="promise_id" id="scheduleFulfillPromiseId">
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
                            <input type="number" name="amount_paid" id="scheduleFulfillAmount" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(document).ready(function() {
    let currentDate = new URLSearchParams(window.location.search).get('date') || '{{ $date->toDateString() }}';

    $('#prevDate').on('click', function() {
        const date = new Date(currentDate);
        date.setDate(date.getDate() - 1);
        window.location.href = '?date=' + date.toISOString().split('T')[0];
    });

    $('#nextDate').on('click', function() {
        const date = new Date(currentDate);
        date.setDate(date.getDate() + 1);
        window.location.href = '?date=' + date.toISOString().split('T')[0];
    });

    $('#todayDate').on('click', function() {
        window.location.href = '{{ route('collections.schedule') }}';
    });

    // Modals
    $('#completeActionModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var actionId = button.data('action-id');
        $('#scheduleCompleteActionId').val(actionId);
    });

    $('#fulfillPromiseModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var promiseId = button.data('promise-id');
        var amount = button.data('amount');
        $('#scheduleFulfillPromiseId').val(promiseId);
        $('#scheduleFulfillAmount').val(amount);
    });
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
