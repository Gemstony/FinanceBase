@extends('adminlte::page')

@section('title', 'SMS Analytics Dashboard')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-bar"></i> SMS Analytics Dashboard</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-chart-bar"></i> SMS Analytics</h1>
                <p class="mb-0 text-light">Monitor SMS delivery and performance</p>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>

        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.sms_settings.index') }}">SMS Settings</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">SMS Analytics</li>
        </ol>
     <a href="{{ route('settings.sms_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
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

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif
                    <form method="GET" action="{{ route('sms.analytics.index') }}" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-4">
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                <a href="{{ route('sms.analytics.index') }}" class="btn btn-outline-secondary w-100 ms-2">Reset</a>
                            </div>
                        </div>
                    </form>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show mb-4">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Key Metrics -->
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Total SMS</h6>
                                    <h2 class="display-6">{{ $totalSms }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Sent</h6>
                                    <h2 class="display-6 text-success">{{ $sentSms }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Failed</h6>
                                    <h2 class="display-6 text-danger">{{ $failedSms }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Error</h6>
                                    <h2 class="display-6 text-danger">{{ $errorSms }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Queued</h6>
                                    <h2 class="display-6 text-warning">{{ $queuedSms }}</h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rates -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light h-100">
                <div class="card-body">
                                    <h6 class="card-title">Delivery Rate</h6>
                                    <h2 class="display-6">{{ $deliveryRate }}%</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Failure Rate</h6>
                                    <h2 class="display-6">{{ $failureRate }}%</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Retry Rate</h6>
                                    <h2 class="display-6">{{ $retryRate }}%</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Retrying</h6>
                                    <h2 class="display-6 text-info">{{ $retryingSms }}</h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="card-title">Daily SMS Trends (Last 30 Days)</h6>
                                </div>
                                <div class="card-body">
                                     <div style="height: 300px; position: relative;">
                                        <canvas id="dailyChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="card-title">Provider Performance</h6>
                                </div>
                                <div class="card-body">                                    
                                        <div style="height: 300px; position: relative;">
                                            <canvas id="providerChart"></canvas>
                                        </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4 mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="card-title">Top Events by Volume</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Event</th>
                                                    <th>Total</th>
                                                    <th>Sent</th>
                                                    <th>Success Rate</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if($eventStats->isEmpty())
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">No event data available</td>
                                                    </tr>
                                                @else
                                                    @foreach($eventStats as $stat)
                                                        <tr>
                                                            <td>{{ $stat->event }}</td>
                                                            <td>{{ $stat->total }}</td>
                                                            <td>{{ $stat->sent }}</td>
                                                            <td>
                                                                @php
                                                                    $successRate = $stat->total > 0 ? round(($stat->sent / $stat->total) * 100, 2) : 0;
                                                                @endphp
                                                                <span class="badge bg-{{ $successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger') }}">
                                                                    {{ $successRate }}%
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="card-title">Provider Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Provider</th>
                                                    <th>Total</th>
                                                    <th>Sent</th>
                                                    <th>Failed</th>
                                                    <th>Success Rate</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if($providerStats->isEmpty())
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted">No provider data available</td>
                                                    </tr>
                                                @else
                                                    @foreach($providerStats as $stat)
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-{{ $stat->provider == 'twilio' ? 'info' : 'success' }}">
                                                                    {{ ucfirst($stat->provider) }}
                                                                </span>
                                                            </td>
                                                            <td>{{ $stat->total }}</td>
                                                            <td>{{ $stat->sent }}</td>
                                                            <td>{{ $stat->failed }}</td>
                                                            <td>
                                                                @php
                                                                    $successRate = $stat->total > 0 ? round(($stat->sent / $stat->total) * 100, 2) : 0;
                                                                @endphp
                                                                <span class="badge bg-{{ $successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger') }}">
                                                                    {{ $successRate }}%
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                     </div>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

 <script>
     // Daily SMS Trends Chart
     document.addEventListener('DOMContentLoaded', function() {
         // Helper function to capitalize first letter (JavaScript equivalent of PHP's ucfirst)
         function ucfirst(str) {
             if (!str) return '';
             return str.charAt(0).toUpperCase() + str.slice(1);
         }
         
         const dailyCtx = document.getElementById('dailyChart');
         if (dailyCtx) {
             const dailyData = @json($dailyStats);
             const dates = dailyData.map(item => item.date);
             const totals = dailyData.map(item => item.total);
             const sent = dailyData.map(item => item.sent);
             
             new Chart(dailyCtx, {
                 type: 'line',
                 data: {
                     labels: dates,
                     datasets: [
                         {
                             label: 'Total SMS',
                             data: totals,
                             borderColor: 'rgb(75, 192, 192)',
                             backgroundColor: 'rgba(75, 192, 192, 0.2)',
                             tension: 0.1
                         },
                         {
                             label: 'Sent SMS',
                             data: sent,
                             borderColor: 'rgb(54, 162, 235)',
                             backgroundColor: 'rgba(54, 162, 235, 0.2)',
                             tension: 0.1
                         }
                     ]
                 },
                 options: {
                     responsive: true,
                     maintainAspectRatio: false,
                     plugins: {
                         legend: {
                             position: 'top',
                         },
                         title: {
                             display: false
                         }
                     }
                 }
             });
         }
         
         // Provider Performance Chart
         const providerCtx = document.getElementById('providerChart');
         if (providerCtx) {
             const providerData = @json($providerStats);
             const providers = providerData.map(item => ucfirst(item.provider));
             const totals = providerData.map(item => item.total);
             const sent = providerData.map(item => item.sent);
             
             new Chart(providerCtx, {
                 type: 'bar',
                 data: {
                     labels: providers,
                     datasets: [
                         {
                             label: 'Total SMS',
                             data: totals,
                             backgroundColor: 'rgba(75, 192, 192, 0.5)'
                         },
                         {
                             label: 'Sent SMS',
                             data: sent,
                             backgroundColor: 'rgba(54, 162, 235, 0.5)'
                         }
                     ]
                 },
                 options: {
                     responsive: true,
                     maintainAspectRatio: false,
                     plugins: {
                         legend: {
                             position: 'top',
                         },
                         title: {
                             display: false
                         }
                     }
                 }
             });
         }
     });
 </script>
 @endpush