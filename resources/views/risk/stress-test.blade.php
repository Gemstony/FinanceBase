@extends('adminlte::page')

@section('title', 'Stress Testing')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-vial"></i> Portfolio Stress Testing</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-vial"></i> Stress Test</h1>
                <div class="small text-light-50">Simulate portfolio scenarios and assess resilience</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.portfolio') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="{{ route('risk.history') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-history"></i> History</a>
                <a href="{{ route('risk.provision-report') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-calculator"></i> Provision</a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('risk.portfolio') }}">Risk</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stress Test</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <!-- Scenario Selection -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-flask"></i> Select Scenario</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('risk.stress-test') }}" class="row">
                            @csrf
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Scenario Type</label>
                                    <select name="scenario" id="scenarioSelect" class="form-control">
                                        <option value="par_increase" {{ ($scenario ?? '') === 'par_increase' ? 'selected' : '' }}>PAR Rate Increase</option>
                                        <option value="mass_default" {{ ($scenario ?? '') === 'mass_default' ? 'selected' : '' }}>Mass Default Event</option>
                                        <option value="economic_downturn" {{ ($scenario ?? '') === 'economic_downturn' ? 'selected' : '' }}>Economic Downturn</option>
                                        <option value="sector_crisis" {{ ($scenario ?? '') === 'sector_crisis' ? 'selected' : '' }}>Sector Crisis</option>
                                    </select>
                                </div>
                            </div>

                            <!-- PAR Increase Parameters -->
                            <div class="col-md-4 scenario-params" id="par_increase_params">
                                <div class="form-group">
                                    <label>PAR30 Increase (percentage points)</label>
                                    <input type="number" name="par30_increase" class="form-control" value="{{ $params['par30_increase'] ?? 5 }}" min="0" max="100">
                                </div>
                                <div class="form-group">
                                    <label>PAR90 Increase (percentage points)</label>
                                    <input type="number" name="par90_increase" class="form-control" value="{{ $params['par90_increase'] ?? 2 }}" min="0" max="100">
                                </div>
                            </div>

                            <!-- Mass Default Parameters -->
                            <div class="col-md-4 scenario-params d-none" id="mass_default_params">
                                <div class="form-group">
                                    <label>Default Rate (%)</label>
                                    <input type="number" name="default_percentage" class="form-control" value="{{ $params['default_percentage'] ?? 10 }}" min="0" max="100">
                                    <small class="form-text text-muted">% of performing loans defaulting</small>
                                </div>
                                <div class="form-group">
                                    <label>Recovery Rate (%)</label>
                                    <input type="number" name="recovery_rate" class="form-control" value="{{ $params['recovery_rate'] ?? 30 }}" min="0" max="100">
                                    <small class="form-text text-muted">Expected recovery on defaulted loans</small>
                                </div>
                            </div>

                            <!-- Economic Downturn Parameters -->
                            <div class="col-md-4 scenario-params d-none" id="economic_downturn_params">
                                <div class="form-group">
                                    <label>Severity</label>
                                    <select name="severity" class="form-control">
                                        <option value="mild" {{ ($params['severity'] ?? '') === 'mild' ? 'selected' : '' }}>Mild</option>
                                        <option value="moderate" {{ ($params['severity'] ?? 'moderate') === 'moderate' ? 'selected' : '' }}>Moderate</option>
                                        <option value="severe" {{ ($params['severity'] ?? '') === 'severe' ? 'selected' : '' }}>Severe</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Sector Crisis Parameters -->
                            <div class="col-md-4 scenario-params d-none" id="sector_crisis_params">
                                <div class="form-group">
                                    <label>Sector</label>
                                    <input type="text" name="sector" class="form-control" value="{{ $params['sector'] ?? 'agriculture' }}" placeholder="e.g. agriculture, trade">
                                </div>
                                <div class="form-group">
                                    <label>Impact Percentage (%)</label>
                                    <input type="number" name="impact_percentage" class="form-control" value="{{ $params['impact_percentage'] ?? 50 }}" min="0" max="100">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-play"></i> Run Scenario
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if($result)
        @php
            // Get risk level from either impact or impact_assessment
            $riskLevel = $result['impact']['risk_level'] ?? $result['impact_assessment']['risk_level'] ?? 'low';
            $alertClass = match($riskLevel) {
                'critical' => 'alert-danger',
                'high' => 'alert-warning',
                'moderate' => 'alert-info',
                default => 'alert-success'
            };
        @endphp
        <!-- Results -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="alert {{ $alertClass }}">
                    <h5><i class="fas fa-exclamation-circle"></i> Impact Assessment: {{ ucfirst($riskLevel) }} Risk</h5>
                    <p class="mb-0">{{ $result['scenario_name'] }} - {{ $result['description'] }}</p>
                </div>
            </div>
        </div>

        <!-- Current vs Projected -->
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h3 class="card-title">Current State</h3>
                    </div>
                    <div class="card-body">
                        @if(isset($result['current_state']))
                            @foreach($result['current_state'] as $key => $value)
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                                    <strong>{{ is_numeric($value) ? number_format($value, 2) : $value }}{{ is_numeric($value) && $value < 100 ? '' : '' }}</strong>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header {{ ($result['projected_state']['additional_provision_required'] ?? 0) > 0 ? 'bg-warning' : 'bg-light' }}">
                        <h3 class="card-title">Projected Impact</h3>
                    </div>
                    <div class="card-body">
                        @if(isset($result['projected_state']))
                            @foreach($result['projected_state'] as $key => $value)
                                <div class="d-flex justify-content-between border-bottom py-2 {{ $key === 'additional_provision_required' || $key === 'additional_delinquent_amount' || $key === 'estimated_loss' ? 'text-danger font-weight-bold' : '' }}">
                                    <span>{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                                    <strong>{{ is_numeric($value) ? number_format($value, 2) : $value }}</strong>
                                </div>
                            @endforeach
                        @endif

                        @if(isset($result['new_par90_rate']))
                            <div class="d-flex justify-content-between border-bottom py-2 text-danger font-weight-bold">
                                <span>New PAR90 Rate</span>
                                <strong>{{ number_format($result['new_par90_rate'], 2) }}%</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Impact Chart -->
        <div class="row mt-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Portfolio Impact Visualization</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="impactChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Key Metrics Change</h3>
                    </div>
                    <div class="card-body">
                        @php
                            // Get impact metrics from either impact or impact_assessment
                            $impactMetrics = $result['impact'] ?? [];
                            if (isset($result['impact_assessment'])) {
                                $impactMetrics = array_merge($impactMetrics, $result['impact_assessment']);
                            }
                        @endphp
                        @if(!empty($impactMetrics))
                            @foreach($impactMetrics as $key => $value)
                                @if(!is_array($value) && is_numeric($value))
                                    @php
                                        $iconClass = match($key) {
                                            'provision_impact_percentage', 'loss_percentage' => 'fa-percentage',
                                            'severity_score' => 'fa-exclamation-triangle',
                                            default => 'fa-chart-line'
                                        };
                                        $badgeClass = $value > 10 ? 'bg-danger' : ($value > 5 ? 'bg-warning' : 'bg-success');
                                    @endphp
                                    <div class="info-box mb-2">
                                        <span class="info-box-icon {{ $badgeClass }}">
                                            <i class="fas {{ $iconClass }}"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                                            <span class="info-box-number">{{ number_format($value, 2) }}%</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <p class="text-muted">No metrics available for this scenario.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommended Actions -->
        @if(isset($result['impact_assessment']['recommended_actions']))
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-tasks"></i> Recommended Actions</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            @foreach($result['impact_assessment']['recommended_actions'] as $action)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {{ $action }}
                                    <span class="badge badge-primary badge-pill"><i class="fas fa-check"></i></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Compare to Historical -->
        @if($historicalStress)
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> Historical Stress Comparison</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5>Current PAR90</h5>
                                    <h2 class="text-primary">{{ $historicalStress['current_par90'] }}%</h2>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5>Historical Worst (12mo)</h5>
                                    <h2 class="text-danger">{{ $historicalStress['historical_worst_par90'] }}%</h2>
                                    <small>{{ $historicalStress['historical_worst_date'] ?? 'N/A' }}</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5>Stress Buffer</h5>
                                    <h2 class="{{ ($historicalStress['stress_buffer'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">{{ $historicalStress['stress_buffer'] ?? 0 }}pp</h2>
                                    <small>{{ $historicalStress['comparison'] === 'below_historical_worst' ? 'Below historical worst' : 'Above historical worst' }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endif
    </div>
@stop

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Scenario selector toggle
    $('#scenarioSelect').on('change', function() {
        $('.scenario-params').addClass('d-none');
        $('#' + $(this).val() + '_params').removeClass('d-none');
    });

    // Show correct params on page load
    $('#scenarioSelect').trigger('change');

    @if($result && isset($result['current_state']) && isset($result['projected_state']))
    // Impact Chart
    const ctx = document.getElementById('impactChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Portfolio Outstanding', 'Delinquent Amount', 'Provision Required'],
            datasets: [
                {
                    label: 'Current',
                    data: [
                        {{ $result['current_state']['performing_amount'] ?? $result['current_state']['portfolio_outstanding'] ?? 0 }},
                        {{ ($result['projected_state']['additional_delinquent_amount'] ?? 0) > 0 ? ($result['current_state']['portfolio_outstanding'] ?? 0) - ($result['projected_state']['additional_delinquent_amount'] ?? 0) * 2 : ($result['current_state']['portfolio_outstanding'] ?? 0) * ($result['current_state']['par90_rate'] ?? 0) / 100 }},
                        {{ $result['current_state']['total_provision'] ?? 0 }}
                    ],
                    backgroundColor: '#17a2b8'
                },
                {
                    label: 'Projected',
                    data: [
                        {{ $result['projected_state']['performing_amount'] ?? $result['current_state']['portfolio_outstanding'] ?? 0 }},
                        {{ ($result['projected_state']['additional_delinquent_amount'] ?? 0) + (($result['current_state']['portfolio_outstanding'] ?? 0) * ($result['current_state']['par90_rate'] ?? 0) / 100) }},
                        {{ ($result['current_state']['total_provision'] ?? 0) + ($result['projected_state']['additional_provision_required'] ?? 0) }}
                    ],
                    backgroundColor: '#dc3545'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                title: { display: true, text: 'Current vs Projected Impact' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    @endif
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
