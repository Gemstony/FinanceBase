@extends('adminlte::page')

@section('title', 'Provision Report')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-calculator"></i> Loan Loss Provision Report</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-calculator"></i> Provision Report</h1>
                <div class="small text-light-50">Required provisions based on risk classification</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.portfolio') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="{{ route('risk.history') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-history"></i> History</a>
                <a href="{{ route('risk.stress-test') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-vial"></i> Stress Test</a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('risk.portfolio') }}">Risk</a></li>
                <li class="breadcrumb-item active" aria-current="page">Provision Report</li>
            </ol>
        </nav>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-print"></i> Print Report
        </button>
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
        <!-- Report Header Info -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <strong><i class="fas fa-info-circle"></i> Report Generated:</strong> {{ $report['generated_at'] }}
                    @if(isset($report['thresholds_used']) && is_array($report['thresholds_used']))
                        | Using provision rates: PAR30={{ $report['thresholds_used']['par30_rate'] }}%, PAR60={{ $report['thresholds_used']['par60_rate'] }}%, PAR90={{ $report['thresholds_used']['par90_rate'] }}%, Default={{ $report['thresholds_used']['default_rate'] }}%
                    @else
                        | Using default provision rates
                    @endif
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ number_format($report['summary']['total_outstanding'], 2) }}</h3>
                        <p>Total Portfolio Outstanding</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-university"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ number_format($report['summary']['total_provision_required'], 2) }}</h3>
                        <p>Total Provision Required</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $report['summary']['provision_percentage'] }}<sup style="font-size: 20px">%</sup></h3>
                        <p>Provision Coverage Ratio</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ number_format($report['summary']['total_outstanding'] - $report['summary']['total_provision_required'], 2) }}</h3>
                        <p>Net Portfolio Value</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Provision Breakdown Chart -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Provision by Risk Bucket</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="provisionChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Portfolio Distribution</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="distributionChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Breakdown Table -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Detailed Provision Breakdown</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Risk Category</th>
                                        <th>Loan Count</th>
                                        <th>Outstanding Amount</th>
                                        <th>% of Portfolio</th>
                                        <th>Provision Rate</th>
                                        <th>Provision Amount</th>
                                        <th>% of Total Provision</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report['breakdown'] as $status => $data)
                                        @if($data['count'] > 0)
                                            <tr>
                                                <td>
                                                    <span class="badge {{ $status === 'current' ? 'bg-success' : ($status === 'par30' ? 'bg-warning' : ($status === 'par60' ? 'bg-orange' : ($status === 'par90' ? 'bg-danger' : 'bg-dark'))) }}">
                                                        {{ strtoupper($status) }}
                                                    </span>
                                                </td>
                                                <td>{{ number_format($data['count']) }}</td>
                                                <td>{{ number_format($data['outstanding'], 2) }}</td>
                                                <td>{{ $data['percentage_of_portfolio'] }}%</td>
                                                <td>{{ $data['rate'] }}%</td>
                                                <td class="font-weight-bold {{ $data['provision'] > 0 ? 'text-warning' : '' }}">{{ number_format($data['provision'], 2) }}</td>
                                                <td>
                                                    @if($report['summary']['total_provision_required'] > 0)
                                                        {{ round(($data['provision'] / $report['summary']['total_provision_required']) * 100, 1) }}%
                                                    @else
                                                        0%
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light">
                                        <th>Total</th>
                                        <th>{{ number_format(array_sum(array_column($report['breakdown'], 'count'))) }}</th>
                                        <th>{{ number_format($report['summary']['total_outstanding'], 2) }}</th>
                                        <th>100%</th>
                                        <th>-</th>
                                        <th class="text-warning">{{ number_format($report['summary']['total_provision_required'], 2) }}</th>
                                        <th>100%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounting Entry Preview -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Suggested Journal Entry</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Loan Loss Expense</td>
                                    <td>{{ number_format($report['summary']['total_provision_required'], 2) }}</td>
                                    <td>-</td>
                                    <td>Provision for loan losses</td>
                                </tr>
                                <tr>
                                    <td>Allowance for Loan Losses</td>
                                    <td>-</td>
                                    <td>{{ number_format($report['summary']['total_provision_required'], 2) }}</td>
                                    <td>Provision for loan losses</td>
                                </tr>
                            </tbody>
                        </table>
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
    // Provision by Bucket Chart
    const provisionCtx = document.getElementById('provisionChart').getContext('2d');
    new Chart(provisionCtx, {
        type: 'bar',
        data: {
            labels: ['Current', 'PAR30', 'PAR60', 'PAR90', 'Default'],
            datasets: [{
                label: 'Provision Amount',
                data: [
                    {{ $report['breakdown']['current']['provision'] ?? 0 }},
                    {{ $report['breakdown']['par30']['provision'] ?? 0 }},
                    {{ $report['breakdown']['par60']['provision'] ?? 0 }},
                    {{ $report['breakdown']['par90']['provision'] ?? 0 }},
                    {{ $report['breakdown']['default']['provision'] ?? 0 }}
                ],
                backgroundColor: [
                    '#28a745',
                    '#ffc107',
                    '#fd7e14',
                    '#dc3545',
                    '#343a40'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Required Provision by Risk Category' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Distribution Chart
    const distCtx = document.getElementById('distributionChart').getContext('2d');
    new Chart(distCtx, {
        type: 'doughnut',
        data: {
            labels: ['Current', 'PAR30', 'PAR60', 'PAR90', 'Default'],
            datasets: [{
                data: [
                    {{ $report['breakdown']['current']['outstanding'] ?? 0 }},
                    {{ $report['breakdown']['par30']['outstanding'] ?? 0 }},
                    {{ $report['breakdown']['par60']['outstanding'] ?? 0 }},
                    {{ $report['breakdown']['par90']['outstanding'] ?? 0 }},
                    {{ $report['breakdown']['default']['outstanding'] ?? 0 }}
                ],
                backgroundColor: [
                    '#28a745',
                    '#ffc107',
                    '#fd7e14',
                    '#dc3545',
                    '#343a40'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' },
                title: { display: true, text: 'Portfolio Distribution' }
            }
        }
    });
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
