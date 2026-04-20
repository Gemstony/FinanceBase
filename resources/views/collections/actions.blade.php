@extends('adminlte::page')

@section('title', 'Collection Actions Tracker')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-tasks"></i> Collection Actions Tracker</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-tasks"></i> Actions</h1>
                <div class="small text-light-50">Monitor and manage collection activities</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.collections') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-hand-holding-usd"></i> Worklist</a>
                <a href="{{ route('collections.promises') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-handshake"></i> Promises</a>
                <a href="{{ route('collections.schedule') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-calendar"></i> Schedule</a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('risk.collections') }}">Collections</a></li>
                <li class="breadcrumb-item active" aria-current="page">Actions</li>
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
        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $stats['total_actions'] ?? 0 }}</h3>
                        <p>Total Actions</p>
                    </div>
                    <div class="icon"><i class="fas fa-tasks"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $stats['pending_actions'] ?? 0 }}</h3>
                        <p>Pending</p>
                    </div>
                    <div class="icon"><i class="fas fa-clock"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $stats['completed_actions'] ?? 0 }}</h3>
                        <p>Completed</p>
                    </div>
                    <div class="icon"><i class="fas fa-check"></i></div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $stats['overdue_actions'] ?? 0 }}</h3>
                        <p>Overdue</p>
                    </div>
                    <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ $stats['successful_collections'] ?? 0 }}</h3>
                        <p>Successful</p>
                    </div>
                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>{{ number_format($stats['total_collected'] ?? 0, 0) }}</h3>
                        <p>Collected</p>
                    </div>
                    <div class="icon"><i class="fas fa-money-bill"></i></div>
                </div>
            </div>
        </div>

        <!-- Overdue Actions Alert -->
        @if(isset($overdueActions) && $overdueActions->count() > 0)
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> {{ $overdueActions->count() }} Overdue Action(s) Require Attention</h5>
                </div>
            </div>
        </div>
        @endif

        <!-- Filter Panel -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter"></i> Filter Actions</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-2">
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="action_type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="phone_call" {{ request('action_type') == 'phone_call' ? 'selected' : '' }}>Phone Call</option>
                                    <option value="sms_reminder" {{ request('action_type') == 'sms_reminder' ? 'selected' : '' }}>SMS</option>
                                    <option value="email_notice" {{ request('action_type') == 'email_notice' ? 'selected' : '' }}>Email</option>
                                    <option value="field_visit" {{ request('action_type') == 'field_visit' ? 'selected' : '' }}>Field Visit</option>
                                    <option value="promise_to_pay" {{ request('action_type') == 'promise_to_pay' ? 'selected' : '' }}>Promise to Pay</option>
                                    <option value="payment_received" {{ request('action_type') == 'payment_received' ? 'selected' : '' }}>Payment</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" placeholder="From Date">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" placeholder="To Date">
                            </div>
                            <div class="col-md-2">
                                <select name="assigned_to" class="form-control">
                                    <option value="">All Staff</option>
                                    @foreach($staff ?? [] as $user)
                                        <option value="{{ $user->id }}" {{ request('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions List -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Collection Actions</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="actionsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Loan</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Scheduled</th>
                                        <th>Outcome</th>
                                        <th>Amount</th>
                                        <th>Assigned To</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($actions ?? [] as $action)
                                        <tr class="{{ $action->status === 'overdue' ? 'table-danger' : ($action->status === 'completed' ? 'table-success' : '') }}">
                                            <td>{{ $action->created_at->format('Y-m-d') }}</td>
                                            <td>
                                                <a href="{{ route('loans.loans.show', $action->loan) }}">
                                                    {{ $action->loan->loan_code ?? 'N/A' }}
                                                </a>
                                            </td>
                                            <td>{{ $action->customer->name ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <i class="fas {{ $action->action_type === 'phone_call' ? 'fa-phone' : ($action->action_type === 'sms_reminder' ? 'fa-sms' : ($action->action_type === 'email_notice' ? 'fa-envelope' : ($action->action_type === 'field_visit' ? 'fa-walking' : 'fa-hand-holding-usd'))) }}"></i>
                                                    {{ ucwords(str_replace('_', ' ', $action->action_type)) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $action->status === 'completed' ? 'bg-success' : ($action->status === 'overdue' ? 'bg-danger' : ($action->status === 'pending' ? 'bg-warning' : 'bg-info')) }}">
                                                    {{ ucwords(str_replace('_', ' ', $action->status)) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($action->scheduled_at)
                                                    {{ $action->scheduled_at->format('Y-m-d H:i') }}
                                                    @if($action->isOverdue())
                                                        <span class="text-danger"><i class="fas fa-exclamation-circle"></i></span>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $action->outcome ? ucwords(str_replace('_', ' ', $action->outcome)) : '-' }}</td>
                                            <td class="{{ $action->amount_collected > 0 ? 'text-success font-weight-bold' : '' }}">
                                                {{ $action->amount_collected > 0 ? number_format($action->amount_collected, 2) : '-' }}
                                            </td>
                                            <td>{{ $action->assignedTo->name ?? 'Unassigned' }}</td>
                                            <td class="text-right">
                                                @if($action->status !== 'completed' && $action->status !== 'cancelled')
                                                    <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#completeActionModal" data-action-id="{{ $action->id }}">
                                                        <i class="fas fa-check"></i> Complete
                                                    </button>
                                                @endif
                                                <a href="{{ route('loans.loans.show', $action->loan) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center text-muted">No collection actions found.</td>
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

    <!-- Complete Action Modal -->
    <div class="modal fade" id="completeActionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('collections.record-action') }}">
                    @csrf
                    <input type="hidden" name="action_id" id="completeActionId">
                    <div class="modal-header">
                        <h5 class="modal-title">Complete Action</h5>
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
                                <option value="refused_payment">Refused Payment</option>
                                <option value="dispute_raised">Dispute Raised</option>
                                <option value="wrong_contact">Wrong Contact</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount Collected (if any)</label>
                            <input type="number" name="amount_collected" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Complete Action</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(document).ready(function() {
    $('#actionsTable').DataTable({
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf']
    });

    // Pass action ID to modal
    $('#completeActionModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var actionId = button.data('action-id');
        $('#completeActionId').val(actionId);
    });
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
