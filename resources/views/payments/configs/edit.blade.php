@extends('adminlte::page')

@section('title', 'Edit Payment Configuration')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-edit"></i> Edit Payment Configuration</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-edit"></i> Edit Config</h1>
                <p class="mb-0 text-light">Update payment provider settings</p>
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
            <li class="breadcrumb-item active text-dark" aria-current="page">Edit Configuration</li>
        </ol>
    </nav>
</div>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Edit {{ ucfirst($config->provider) }} Configuration</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('payments.configs.update', $config->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="provider">Provider</label>
                    <input type="text" class="form-control" value="{{ ucfirst($config->provider) }}" disabled>
                </div>

                <div class="form-group">
                    <label for="api_url">API URL <span class="text-danger">*</span></label>
                    <input type="url" name="api_url" id="api_url" class="form-control @error('api_url') is-invalid @enderror" value="{{ old('api_url', $config->api_url) }}" required>
                    @error('api_url')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="api_key">API Key</label>
                    <input type="text" name="api_key" id="api_key" class="form-control @error('api_key') is-invalid @enderror" value="{{ old('api_key') }}" placeholder="Leave blank to keep current">
                    <small class="form-text text-muted">Leave blank to keep current API key</small>
                    @error('api_key')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="secret_key">Secret Key</label>
                    <input type="text" name="secret_key" id="secret_key" class="form-control @error('secret_key') is-invalid @enderror" value="{{ old('secret_key') }}" placeholder="Leave blank to keep current">
                    <small class="form-text text-muted">Leave blank to keep current secret key</small>
                    @error('secret_key')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="shortcode">Shortcode</label>
                    <input type="text" name="shortcode" id="shortcode" class="form-control @error('shortcode') is-invalid @enderror" value="{{ old('shortcode', $config->shortcode) }}">
                    @error('shortcode')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="passkey">Passkey</label>
                    <input type="text" name="passkey" id="passkey" class="form-control @error('passkey') is-invalid @enderror" value="{{ old('passkey') }}" placeholder="Leave blank to keep current">
                    <small class="form-text text-muted">Leave blank to keep current passkey</small>
                    @error('passkey')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="environment">Environment <span class="text-danger">*</span></label>
                    <select name="environment" id="environment" class="form-control @error('environment') is-invalid @enderror" required>
                        <option value="sandbox" {{ old('environment', $config->environment) === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                        <option value="live" {{ old('environment', $config->environment) === 'live' ? 'selected' : '' }}>Live</option>
                    </select>
                    @error('environment')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_active" id="is_active" class="custom-control-input" value="1" {{ old('is_active', $config->is_active) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="is_active">Active</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_default" id="is_default" class="custom-control-input" value="1" {{ old('is_default', $config->is_default) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="is_default">Set as Default</label>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Configuration
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
