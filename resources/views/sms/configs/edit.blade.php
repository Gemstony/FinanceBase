@extends('adminlte::page')

@section('title', 'Edit SMS Configuration')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-edit"></i> Edit SMS Configuration</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-edit"></i> Edit Config</h1>
                <p class="mb-0 text-light">Modify SMS provider settings</p>
            </div>
            <a href="{{ route('sms.configs.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.sms_settings.index') }}">SMS Settings</a></li>        
            <li class="breadcrumb-item"><a href="{{ route('sms.configs.index') }}">SMS Configurations</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit Configuration</li>
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

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Edit SMS Configuration</h5>
                </div>
                <div class="card-body">
                <form action="{{ route('sms.configs.update', $smsConfig->id) }}" method="POST">
                    @csrf
                    @method('PUT')



                    {{-- Provider --}}
                    <div class="row mb-3">
                        <label for="provider" class="col-md-2 col-form-label">Provider*</label>
                        <div class="col-md-10">
                            <select name="provider" id="provider" class="form-select @error('provider') is-invalid @enderror" required>
                                <option value="">Select Provider</option>
                                <option value="beem" {{ old('provider', $smsConfig->provider) == 'beem' ? 'selected' : '' }}>Beem</option>
                                <option value="twilio" {{ old('provider', $smsConfig->provider) == 'twilio' ? 'selected' : '' }}>Twilio</option>
                            </select>
                            @error('provider')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- API URL --}}
                    <div class="row mb-3">
                        <label for="api_url" class="col-md-2 col-form-label">API URL*</label>
                        <div class="col-md-10">
                            <input type="url" name="api_url" id="api_url"
                                class="form-control @error('api_url') is-invalid @enderror"
                                value="{{ old('api_url', $smsConfig->api_url) }}" required>
                            @error('api_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- API Key --}}
                    <div class="row mb-3">
                        <label for="api_key" class="col-md-2 col-form-label">API Key</label>
                        <div class="col-md-10">
                            <input type="text" name="api_key" id="api_key"
                                class="form-control @error('api_key') is-invalid @enderror"
                                placeholder="Leave blank to keep current">
                            <small class="text-muted">Leave blank to keep existing key</small>
                            @error('api_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Secret Key --}}
                    <div class="row mb-3">
                        <label for="secret_key" class="col-md-2 col-form-label">Secret Key</label>
                        <div class="col-md-10">
                            <input type="password" name="secret_key" id="secret_key"
                                class="form-control @error('secret_key') is-invalid @enderror"
                                placeholder="Leave blank to keep current">
                            <small class="text-muted">Leave blank to keep existing secret</small>
                            @error('secret_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Sender ID --}}
                    <div class="row mb-3">
                        <label for="sender_id" class="col-md-2 col-form-label">Sender ID*</label>
                        <div class="col-md-10">
                            <input type="text" name="sender_id" id="sender_id"
                                class="form-control @error('sender_id') is-invalid @enderror"
                                value="{{ old('sender_id', $smsConfig->sender_id) }}"
                                maxlength="11" required>
                            <small class="text-muted">Max 11 characters</small>
                            @error('sender_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Status --}}
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">Status</label>
                        <div class="col-md-10">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" id="is_active"
                                    class="form-check-input"
                                    value="1"
                                    {{ old('is_active', $smsConfig->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    {{-- Default --}}
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">Default</label>
                        <div class="col-md-10">
                            <div class="form-check">
                                <input type="hidden" name="is_default" value="0">
                                <input type="checkbox" name="is_default" id="is_default"
                                    class="form-check-input"
                                    value="1"
                                    {{ old('is_default', $smsConfig->is_default) ? 'checked' : '' }}>
                                <label class="form-check-label">
                                    Set as default configuration for this shop
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Rate Limit --}}
                    <div class="row mb-3">
                        <label for="rate_limit_per_minute" class="col-md-2 col-form-label">Rate Limit</label>
                        <div class="col-md-10">
                            <input type="number" name="rate_limit_per_minute" id="rate_limit_per_minute"
                                class="form-control @error('rate_limit_per_minute') is-invalid @enderror"
                                min="1"
                                value="{{ old('rate_limit_per_minute', $smsConfig->rate_limit_per_minute ?? 60) }}">
                            <small class="text-muted">Default: 60 SMS/min</small>
                            @error('rate_limit_per_minute')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="row">
                        <div class="col-md-10 offset-md-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Configuration
                            </button>
                            <a href="{{ route('sms.configs.index') }}" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
                 </div>
             </div>
         </div>
     </div>
 </div>
 @endsection

 @push('css')
 <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
 @endpush