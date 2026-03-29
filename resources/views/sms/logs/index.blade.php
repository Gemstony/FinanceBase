@extends('adminlte::page')

@section('title', 'SMS Logs')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-sms"></i> SMS Logs</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-sms"></i> SMS Logs</h1>
                <p class="mb-0 text-light">View and filter SMS delivery logs</p>
            </div>
            <a href="{{ route('settings.sms_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>

        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
        <li class="breadcrumb-item"><a href="{{ route('settings.sms_settings.index') }}">SMS Settings</a></li>
        <li class="breadcrumb-item active" aria-current="page">SMS Logs</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
     
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif


    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">SMS Logs</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('sms.logs.index') }}" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="queued" {{ request('status') == 'queued' ? 'selected' : '' }}>Queued</option>
                                    <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                                    <option value="error" {{ request('status') == 'error' ? 'selected' : '' }}>Error</option>
                                    <option value="retrying" {{ request('status') == 'retrying' ? 'selected' : '' }}>Retrying</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="event" class="form-select">
                                    <option value="">All Events</option>
                                    @foreach($events as $event)
                                        <option value="{{ $event }}" {{ request('event') == $event ? 'selected' : '' }}>
                                            {{ $event }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                <a href="{{ route('sms.logs.index') }}" class="btn btn-outline-secondary w-100 ms-2">Reset</a>
                            </div>
                        </div>
                    </form>

                    @if($logs->isEmpty())
                        <div class="alert alert-info">
                            No SMS logs found.
                        </div>
                    @else
                        <div class="mb-3">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Total SMS</h6>
                                            <h2 class="display-6">{{ $total }}</h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Sent</h6>
                                            <h2 class="display-6 text-success">{{ $sent }}</h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Failed</h6>
                                            <h2 class="display-6 text-danger">{{ $failed }}</h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Error</h6>
                                            <h2 class="display-6 text-danger">{{ $errors }}</h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Queued</h6>
                                            <h2 class="display-6 text-warning">{{ $queued }}</h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="logsTable">
                                <thead>
                                    <tr>
                                        <th>Shop</th>
                                        <th>Phone</th>
                                        <th>Message</th>
                                        <th>Event</th>
                                        <th>Status</th>
                                        <th>Provider</th>
                                        <th>Attempts</th>
                                        <th>Sent At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr>
                                            <td>{{ $log->shop->name ?? '-' }}</td>
                                            <td>{{ $log->phone }}</td>
                                            <td>
                                                @if($log->message === '[REDACTED]')
                                                    <span class="text-danger">[REDACTED]</span>
                                                @else
                                                    {{ Str::limit($log->message, 50) }}
                                                @endif
                                            </td>
                                            <td>
                                                @if($log->event)
                                                    <span class="badge bg-info">{{ $log->event }}</span>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                            @php
                                                $badgeClass = match ($log->status) {
                                                    'sent' => 'success',
                                                    'failed', 'error' => 'danger',
                                                    'queued', 'retrying' => 'warning',
                                                    default => 'secondary',
                                                };
                                            @endphp

                                            <span class="badge bg-{{ $badgeClass }}">
                                                {{ ucfirst($log->status) }}
                                            </span>
                                        </td>
                                            <td>
                                                <span class="badge bg-{{ $log->provider == 'twilio' ? 'info' : 'success' }}">
                                                    {{ ucfirst($log->provider) }}
                                                </span>
                                            </td>
                                            <td>{{ $log->attempts }}</td>
                                            <td>
                                                @if($log->sent_at)
                                                    {{ $log->sent_at->format('Y-m-d H:i:s') }}
                                                @else
                                                    <span class="text-muted">Pending</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('sms.logs.show', $log->id) }}" class="btn btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <form action="{{ route('sms.logs.destroy', $log->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this SMS log?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <small class="text-muted">
                                        Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }} entries
                                    </small>
                                </div>
                                <div>
                                    {{ $logs->links() }}
                                </div>
                            </div>
                        </div>


                     @endif
                 </div>
             </div>
         </div>
     </div>
 </div>
 @endsection

 @push('css')
 <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
 @endpush

 @push('js')
 <script>
$(document).ready(function() {
    // Initialize DataTable
    $('#logsTable').DataTable({
        responsive: true,
        columnDefs: [{
                orderable: false,
                targets: [0, 7]
            }, // Disable sorting on action column
            {
                searchable: false,
                targets: [0, 4, 5, 6,  7]
            } // Disable search on action and status columns
        ],
        order: [
            [7, 'desc']
        ] // Sort by code by default
    });
});
 </script>
 @endpush