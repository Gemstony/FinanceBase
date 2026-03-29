@extends('adminlte::page')

@section('title', 'Add SMS Configuration')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-plus-circle"></i> Add SMS Configuration</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-plus-circle"></i> Add Config</h1>
                <p class="mb-0 text-light">Configure a new SMS provider</p>
            </div>
            <a href="{{ route('sms.configs.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
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
        <li class="breadcrumb-item active" aria-current="page">Add Configuration</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add SMS Configuration</h5>
                </div>
                     <div class="card-body">
                     @if ($errors->any())
                         <div class="alert alert-danger">
                             <ul>
                                 @foreach ($errors->all() as $error)
                                     <li>{{ $error }}</li>
                                 @endforeach
                             </ul>
                         </div>
                     @endif
                     <form action="{{ route('sms.configs.store') }}" method="POST">
                        @csrf
                        

                        
                        <div class="row mb-3">
                            <label for="provider" class="col-md-2 col-form-label">Provider*</label>
                            <div class="col-md-10">
                                <select name="provider" id="provider" class="form-select" required>
                                    <option value="">Select Provider</option>
                                    <option value="beem">Beem</option>
                                    <option value="twilio">Twilio</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="api_url" class="col-md-2 col-form-label">API URL*</label>
                            <div class="col-md-10">
                                <input type="url" name="api_url" id="api_url" class="form-control" placeholder="https://api.example.com/sms" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="api_key" class="col-md-2 col-form-label">API Key*</label>
                            <div class="col-md-10">
                                <input type="text" name="api_key" id="api_key" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="secret_key" class="col-md-2 col-form-label">Secret Key*</label>
                            <div class="col-md-10">
                                <input type="text" name="secret_key" id="secret_key" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="sender_id" class="col-md-2 col-form-label">Sender ID*</label>
                            <div class="col-md-10">
                                <input type="text" name="sender_id" id="sender_id" class="form-control" maxlength="11" placeholder="e.g., DukaBase" required>
                                <small class="text-muted">Maximum 11 characters</small>
                            </div>
                        </div>
                        
                         <div class="row mb-3">
                             <label for="is_active" class="col-md-2 col-form-label">Status</label>
                             <div class="col-md-10">
                                 <div class="form-check">
                                     <input type="hidden" name="is_active" value="0">
                                     <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" checked>
                                     <label class="form-check-label" for="is_active">Active</label>
                                 </div>
                             </div>
                         </div>
                        
                         <div class="row mb-3">
                             <label for="is_default" class="col-md-2 col-form-label">Default</label>
                             <div class="col-md-10">
                                 <div class="form-check">
                                     <input type="hidden" name="is_default" value="0">
                                     <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1">
                                     <label class="form-check-label" for="is_default">Set as default configuration for this shop</label>
                                 </div>
                             </div>
                         </div>
                        
                        <div class="row mb-3">
                            <label for="rate_limit_per_minute" class="col-md-2 col-form-label">Rate Limit (per minute)</label>
                            <div class="col-md-10">
                                <input type="number" name="rate_limit_per_minute" id="rate_limit_per_minute" class="form-control" min="1" value="60">
                                <small class="text-muted">Maximum SMS messages per minute (default: 60)</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-10 offset-md-2">
                                 <button type="submit" class="btn btn-primary">
                                     <i class="fas fa-save"></i> Save Configuration
                                 </button>
                                 <a href="{{ route('sms.configs.index') }}" class="btn btn-secondary">Cancel</a>
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