@extends('adminlte::page')

@section('title', 'Risk Thresholds Configuration')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-sliders-h"></i> Risk Thresholds</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-sliders-h"></i> Thresholds</h1>
                <div class="small text-light-50">Configure risk limits and provision rates</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('risk.portfolio') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-line"></i> Dashboard</a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('risk.portfolio') }}">Risk</a></li>
                <li class="breadcrumb-item active" aria-current="page">Thresholds</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('risk.thresholds') }}">
            @csrf

            <div class="row">
                <!-- PAR Alert Thresholds -->
                <div class="col-md-6">
                    <div class="card card-outline card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-bell"></i> PAR Alert Thresholds</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Set percentage points at which alerts will be triggered</p>

                            <h5 class="mt-4 mb-3">PAR30 Thresholds</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Warning Threshold (%)</label>
                                        <input type="number" name="par30_warning_threshold" class="form-control" value="{{ old('par30_warning_threshold', $thresholds->par30_warning_threshold ?? 5) }}" min="0" max="100" step="0.01" required>
                                        <small class="form-text text-muted">Yellow alert when PAR30 exceeds this</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Critical Threshold (%)</label>
                                        <input type="number" name="par30_critical_threshold" class="form-control" value="{{ old('par30_critical_threshold', $thresholds->par30_critical_threshold ?? 10) }}" min="0" max="100" step="0.01" required>
                                        <small class="form-text text-muted">Red alert when PAR30 exceeds this</small>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mt-4 mb-3">PAR90 (NPL) Thresholds</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Warning Threshold (%)</label>
                                        <input type="number" name="par90_warning_threshold" class="form-control" value="{{ old('par90_warning_threshold', $thresholds->par90_warning_threshold ?? 2) }}" min="0" max="100" step="0.01" required>
                                        <small class="form-text text-muted">Yellow alert when NPL exceeds this</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Critical Threshold (%)</label>
                                        <input type="number" name="par90_critical_threshold" class="form-control" value="{{ old('par90_critical_threshold', $thresholds->par90_critical_threshold ?? 5) }}" min="0" max="100" step="0.01" required>
                                        <small class="form-text text-muted">Red alert when NPL exceeds this</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Concentration Limits -->
                <div class="col-md-6">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Concentration Limits</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Maximum portfolio exposure limits</p>

                            <div class="form-group">
                                <label>Max Exposure Per Customer (%)</label>
                                <input type="number" name="max_portfolio_percentage_per_customer" class="form-control" value="{{ old('max_portfolio_percentage_per_customer', $thresholds->max_portfolio_percentage_per_customer ?? 5) }}" min="0" max="100" step="0.01" required>
                                <small class="form-text text-muted">Maximum % of total portfolio for single customer</small>
                            </div>

                            <div class="form-group">
                                <label>Max Exposure Per Customer (Amount)</label>
                                <input type="number" name="max_exposure_per_customer" class="form-control" value="{{ old('max_exposure_per_customer', $thresholds->max_exposure_per_customer ?? '') }}" min="0" step="0.01">
                                <small class="form-text text-muted">Maximum absolute amount (leave blank for no limit)</small>
                            </div>

                            <div class="form-group">
                                <label>Max Sector Concentration (%)</label>
                                <input type="number" name="max_sector_concentration" class="form-control" value="{{ old('max_sector_concentration', $thresholds->max_sector_concentration ?? 25) }}" min="0" max="100" step="0.01" required>
                                <small class="form-text text-muted">Maximum % of portfolio in single sector</small>
                            </div>

                            <div class="form-group">
                                <label>Max Product Concentration (%)</label>
                                <input type="number" name="max_product_concentration" class="form-control" value="{{ old('max_product_concentration', $thresholds->max_product_concentration ?? 50) }}" min="0" max="100" step="0.01" required>
                                <small class="form-text text-muted">Maximum % of portfolio in single loan product</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Provision Rates -->
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="card card-outline card-danger">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-calculator"></i> Provision Rates</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Percentage of outstanding to provision for each risk category</p>

                            <div class="row">
                                <div class="col-md-2 col-6">
                                    <div class="form-group">
                                        <label>Current</label>
                                        <div class="input-group">
                                            <input type="number" name="provision_rate_current" class="form-control text-center" value="{{ old('provision_rate_current', $thresholds->provision_rate_current ?? 0) }}" min="0" max="100" step="0.01">
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="form-group">
                                        <label>PAR30</label>
                                        <div class="input-group">
                                            <input type="number" name="provision_rate_par30" class="form-control text-center bg-warning" value="{{ old('provision_rate_par30', $thresholds->provision_rate_par30 ?? 5) }}" min="0" max="100" step="0.01">
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="form-group">
                                        <label>PAR60</label>
                                        <div class="input-group">
                                            <input type="number" name="provision_rate_par60" class="form-control text-center" style="background-color: #fd7e14; color: white;" value="{{ old('provision_rate_par60', $thresholds->provision_rate_par60 ?? 20) }}" min="0" max="100" step="0.01">
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="form-group">
                                        <label>PAR90</label>
                                        <div class="input-group">
                                            <input type="number" name="provision_rate_par90" class="form-control text-center bg-danger text-white" value="{{ old('provision_rate_par90', $thresholds->provision_rate_par90 ?? 50) }}" min="0" max="100" step="0.01">
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="form-group">
                                        <label>Default</label>
                                        <div class="input-group">
                                            <input type="number" name="provision_rate_default" class="form-control text-center bg-dark text-white" value="{{ old('provision_rate_default', $thresholds->provision_rate_default ?? 100) }}" min="0" max="100" step="0.01">
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <div class="custom-control custom-switch mt-2">
                                            <input type="checkbox" name="is_active" class="custom-control-input" id="isActiveSwitch" value="1" {{ old('is_active', $thresholds->is_active ?? true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="isActiveSwitch">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> <strong>Provision Calculation Example:</strong><br>
                                A loan of 1,000,000 in PAR30 would require provision of: 1,000,000 × {{ $thresholds->provision_rate_par30 ?? 5 }}% = {{ number_format(1000000 * (($thresholds->provision_rate_par30 ?? 5) / 100), 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subshop Selection (for admin) -->
            @if(isset($subshops) && count($subshops) > 0)
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Apply to Subshop</label>
                        <select name="subshop_id" class="form-control">
                            <option value="">Global (All Subshops)</option>
                            @foreach($subshops as $subshop)
                                <option value="{{ $subshop->id }}" {{ (old('subshop_id', $thresholds->subshop_id ?? '') == $subshop->id) ? 'selected' : '' }}>
                                    {{ $subshop->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Select specific subshop or leave as Global</small>
                    </div>
                </div>
            </div>
            @endif

            <!-- Submit -->
            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Thresholds
                    </button>
                    <a href="{{ route('risk.portfolio') }}" class="btn btn-secondary btn-lg ml-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
    .form-group label {
        font-weight: 600;
    }
</style>
@endpush
