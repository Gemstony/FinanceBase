@extends('adminlte::page')

@section('title', 'Add Payment Configuration')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-plus"></i> Add Payment Configuration</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-plus"></i> Add Config</h1>
                <p class="mb-0 text-light">Configure a new payment provider</p>
            </div>
            <a href="{{ route('payments.configs') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.payment_settings.index') }}">Payment Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('payments.configs') }}">Payment Configurations</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">Add Configuration</li>
        </ol>
    </nav>
</div>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">New Provider Configuration</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('payments.configs.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="provider">Provider <span class="text-danger">*</span></label>
                    <select name="provider" id="provider" class="form-control @error('provider') is-invalid @enderror" required onchange="toggleProviderFields()">
                        <option value="">Select Provider</option>
                        <option value="clickpesa" {{ old('provider') === 'clickpesa' ? 'selected' : '' }}>ClickPesa</option>
                        <option value="azampay" {{ old('provider') === 'azampay' ? 'selected' : '' }}>AzamPay</option>
                        <option value="mpesa" {{ old('provider') === 'mpesa' ? 'selected' : '' }}>M-Pesa</option>
                        <option value="airtel" {{ old('provider') === 'airtel' ? 'selected' : '' }}>Airtel Money</option>
                        <option value="tigo" {{ old('provider') === 'tigo' ? 'selected' : '' }}>Tigo Pesa</option>
                    </select>
                    @error('provider')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Standard Fields (for mpesa, airtel, tigo, clickpesa) -->
                <div id="standard-fields">
                    <div class="form-group">
                        <label for="api_url">API URL <span class="text-danger">*</span></label>
                        <input type="url" name="api_url" id="api_url" class="form-control @error('api_url') is-invalid @enderror" value="{{ old('api_url') }}">
                        @error('api_url')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="api_key">API Key <span class="text-danger">*</span></label>
                        <input type="text" name="api_key" id="api_key" class="form-control @error('api_key') is-invalid @enderror" value="{{ old('api_key') }}">
                        @error('api_key')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="secret_key">Secret Key <span class="text-danger">*</span></label>
                        <input type="text" name="secret_key" id="secret_key" class="form-control @error('secret_key') is-invalid @enderror" value="{{ old('secret_key') }}">
                        @error('secret_key')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="shortcode">Shortcode</label>
                        <input type="text" name="shortcode" id="shortcode" class="form-control @error('shortcode') is-invalid @enderror" value="{{ old('shortcode') }}">
                        @error('shortcode')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="passkey">Passkey</label>
                        <input type="text" name="passkey" id="passkey" class="form-control @error('passkey') is-invalid @enderror" value="{{ old('passkey') }}">
                        @error('passkey')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- AzamPay Fields -->
                <div id="azampay-fields" style="display: none;">
                    <div class="form-group">
                        <label for="client_id">Client ID <span class="text-danger">*</span></label>
                        <input type="text" name="client_id" id="client_id" class="form-control" value="{{ old('client_id') }}">
                    </div>

                    <div class="form-group">
                        <label for="client_secret">Client Secret <span class="text-danger">*</span></label>
                        <input type="text" name="client_secret" id="client_secret" class="form-control" value="{{ old('client_secret') }}">
                    </div>

                    <div class="form-group">
                        <label for="azampay_api_key">API Key <span class="text-danger">*</span></label>
                        <input type="text" name="azampay_api_key" id="azampay_api_key" class="form-control" value="{{ old('azampay_api_key') }}">
                    </div>

                    <div class="form-group">
                        <label for="app_name">App Name <span class="text-danger">*</span></label>
                        <input type="text" name="app_name" id="app_name" class="form-control" value="{{ old('app_name') }}">
                    </div>

                    <div class="form-group">
                        <label for="base_url">Base URL <span class="text-danger">*</span></label>
                        <input type="url" name="base_url" id="base_url" class="form-control" value="{{ old('base_url', 'https://api.azampay.co.tz') }}" placeholder="https://api.azampay.co.tz">
                    </div>

                    <div class="form-group">
                        <label for="default_network">Default Network</label>
                        <select name="default_network" id="default_network" class="form-control">
                            <option value="Mpesa" {{ old('default_network') === 'Mpesa' ? 'selected' : '' }}>M-Pesa</option>
                            <option value="Airtel" {{ old('default_network') === 'Airtel' ? 'selected' : '' }}>Airtel</option>
                            <option value="Tigo" {{ old('default_network') === 'Tigo' ? 'selected' : '' }}>Tigo</option>
                            <option value="Halopesa" {{ old('default_network') === 'Halopesa' ? 'selected' : '' }}>Halopesa</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="environment">Environment <span class="text-danger">*</span></label>
                    <select name="environment" id="environment" class="form-control @error('environment') is-invalid @enderror" required>
                        <option value="sandbox" {{ old('environment', 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                        <option value="live" {{ old('environment') === 'live' ? 'selected' : '' }}>Live</option>
                    </select>
                    @error('environment')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_active" id="is_active" class="custom-control-input" value="1" {{ old('is_active', '1') === '1' ? 'checked' : '' }}>
                        <label class="custom-control-label" for="is_active">Active</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_default" id="is_default" class="custom-control-input" value="1" {{ old('is_default') === '1' ? 'checked' : '' }}>
                        <label class="custom-control-label" for="is_default">Set as Default</label>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                    <a href="{{ route('payments.configs') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@stop
@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script>
function toggleProviderFields() {
    var provider = document.getElementById('provider').value;
    var standardFields = document.getElementById('standard-fields');
    var azampayFields = document.getElementById('azampay-fields');

    if (provider === 'azampay') {
        standardFields.style.display = 'none';
        azampayFields.style.display = 'block';
        standardFields.querySelectorAll('input').forEach(function(el) { el.removeAttribute('required'); });
        azampayFields.querySelectorAll('input[required]').forEach(function(el) {});
    } else {
        standardFields.style.display = 'block';
        azampayFields.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleProviderFields();
});
</script>
@endpush
