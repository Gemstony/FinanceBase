@extends('adminlte::page')

@section('title', 'Risk History & Trends')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-chart-line"></i> Risk History & Trends</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-chart-line"></i> Risk History</h1>
                <div class="small text-light-50">Historical portfolio risk analysis</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.portfolio') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="{{ route('risk.provision-report') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-calculator"></i> Provision</a>
                <a href="{{ route('risk.stress-test') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-vial"></i> Stress Test</a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('risk.portfolio') }}">Risk</a></li>
                <li class="breadcrumb-item active" aria-current="page">History</li>
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
        @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif
        <!-- Date Range Filter -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="form-inline">
                            <label class="mr-2">Date Range:</label>
                            <select name="range" class="form-control mr-2" onchange="this.form.submit()">
                                <option value="30" {{ $range == 30 ? 'selected' : '' }}>Last 30 Days</option>
                                <option value="90" {{ $range == 90 ? 'selected' : '' }}>Last 3 Months</option>
                                <option value="180" {{ $range == 180 ? 'selected' : '' }}>Last 6 Months</option>
                                <option value="365" {{ $range == 365 ? 'selected' : '' }}>Last 12 Months</option>
                            </select>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trend Charts -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">PAR Rate Trends</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="parTrendChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Portfolio Outstanding</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="portfolioTrendChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- NPL Trend -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Non-Performing Loans (NPL) Trend</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="nplTrendChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trend Analysis Summary -->
        @if($trendAnalysis)
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">Trend Analysis ({{ $trendAnalysis['period']['start'] }} to {{ $trendAnalysis['period']['end'] }})</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon {{ $trendAnalysis['portfolio_change']['amount'] >= 0 ? 'bg-success' : 'bg-danger' }}">
                                        <i class="fas {{ $trendAnalysis['portfolio_change']['amount'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Portfolio Change</span>
                                        <span class="info-box-number">{{ number_format($trendAnalysis['portfolio_change']['amount'], 2) }}</span>
                                        <span class="progress-description">{{ $trendAnalysis['portfolio_change']['percentage'] }}%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon {{ $trendAnalysis['par30_change']['rate_change'] > 0 ? 'bg-danger' : 'bg-success' }}">
                                        <i class="fas {{ $trendAnalysis['par30_change']['rate_change'] > 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">PAR30 Change</span>
                                        <span class="info-box-number">{{ $trendAnalysis['par30_change']['rate_change'] }}pp</span>
                                        <span class="progress-description">{{ number_format($trendAnalysis['par30_change']['amount_change'], 2) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon {{ $trendAnalysis['par90_change']['rate_change'] > 0 ? 'bg-danger' : 'bg-success' }}">
                                        <i class="fas {{ $trendAnalysis['par90_change']['rate_change'] > 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">PAR90 Change</span>
                                        <span class="info-box-number">{{ $trendAnalysis['par90_change']['rate_change'] }}pp</span>
                                        <span class="progress-description">{{ number_format($trendAnalysis['par90_change']['amount_change'], 2) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon {{ $trendAnalysis['npl_change']['rate_change'] > 0 ? 'bg-danger' : 'bg-success' }}">
                                        <i class="fas {{ $trendAnalysis['npl_change']['rate_change'] > 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">NPL Change</span>
                                        <span class="info-box-number">{{ $trendAnalysis['npl_change']['rate_change'] }}pp</span>
                                        <span class="progress-description">{{ number_format($trendAnalysis['npl_change']['amount_change'], 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Snapshot Table -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Historical Snapshots</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="snapshotsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Portfolio Outstanding</th>
                                        <th>PAR30 %</th>
                                        <th>PAR60 %</th>
                                        <th>PAR90 % (NPL)</th>
                                        <th>PAR180 %</th>
                                        <th>Delinquent Loans</th>
                                        <th>Total Loans</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($snapshots as $snapshot)
                                        <tr>
                                            <td>{{ $snapshot->snapshot_date->format('Y-m-d') }}</td>
                                            <td>{{ number_format($snapshot->portfolio_outstanding, 2) }}</td>
                                            <td>
                                                <span class="badge {{ $snapshot->par30_rate > 10 ? 'bg-danger' : ($snapshot->par30_rate > 5 ? 'bg-warning' : 'bg-success') }}">
                                                    {{ $snapshot->par30_rate }}%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $snapshot->par60_rate > 5 ? 'bg-danger' : ($snapshot->par60_rate > 2 ? 'bg-warning' : 'bg-success') }}">
                                                    {{ $snapshot->par60_rate }}%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $snapshot->par90_rate > 5 ? 'bg-danger' : ($snapshot->par90_rate > 2 ? 'bg-warning' : 'bg-success') }}">
                                                    {{ $snapshot->par90_rate }}%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $snapshot->par180_rate > 2 ? 'bg-danger' : 'bg-secondary' }}">
                                                    {{ $snapshot->par180_rate }}%
                                                </span>
                                            </td>
                                            <td>{{ $snapshot->delinquent_loans }}</td>
                                            <td>{{ $snapshot->total_active_loans }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No historical data available. Run "risk:create-snapshot" to generate snapshots.</td>
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
@stop

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // PAR Trend Chart
    const parCtx = document.getElementById('parTrendChart').getContext('2d');
    new Chart(parCtx, {
        type: 'line',
        data: {
            labels: @json($snapshots->pluck('snapshot_date')->map(fn($d) => $d->format('M d'))),
            datasets: [
                {
                    label: 'PAR30 %',
                    data: @json($snapshots->pluck('par30_rate')),
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'PAR60 %',
                    data: @json($snapshots->pluck('par60_rate')),
                    borderColor: '#fd7e14',
                    backgroundColor: 'rgba(253, 126, 20, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'PAR90 (NPL) %',
                    data: @json($snapshots->pluck('par90_rate')),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                title: { display: true, text: 'Portfolio At Risk Trends' }
            },
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });

    // Portfolio Trend Chart
    const portfolioCtx = document.getElementById('portfolioTrendChart').getContext('2d');
    new Chart(portfolioCtx, {
        type: 'line',
        data: {
            labels: @json($snapshots->pluck('snapshot_date')->map(fn($d) => $d->format('M d'))),
            datasets: [
                {
                    label: 'Portfolio Outstanding',
                    data: @json($snapshots->pluck('portfolio_outstanding')),
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Delinquent Amount (PAR30+)',
                    data: @json($snapshots->map(fn($s) => $s->par30_amount + $s->par60_amount + $s->par90_amount + $s->par180_amount)),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                title: { display: true, text: 'Portfolio Value Trend' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // NPL Trend Chart
    const nplCtx = document.getElementById('nplTrendChart').getContext('2d');
    new Chart(nplCtx, {
        type: 'line',
        data: {
            labels: @json($snapshots->pluck('snapshot_date')->map(fn($d) => $d->format('M d'))),
            datasets: [
                {
                    label: 'NPL Rate %',
                    data: @json($snapshots->pluck('par90_rate')),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.2)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'NPL Amount',
                    data: @json($snapshots->pluck('par90_amount')),
                    borderColor: '#6c757d',
                    backgroundColor: 'rgba(108, 117, 125, 0.1)',
                    type: 'bar',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                title: { display: true, text: 'Non-Performing Loans Trend' }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    max: 100,
                    title: { display: true, text: 'Rate (%)' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Amount' }
                }
            }
        }
    });

    // DataTable for snapshots
    $('#snapshotsTable').DataTable({
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf']
    });
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
