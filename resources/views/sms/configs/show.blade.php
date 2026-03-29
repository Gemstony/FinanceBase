@extends('adminlte::page')

@section('title', 'SMS Configuration Details')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-cog"></i> SMS Configuration Details</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-cog"></i> Config Details</h1>
                <p class="mb-0 text-light">View SMS provider configuration details</p>
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
        <li class="breadcrumb-item active" aria-current="page">Configuration Details</li>
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
                    <h5 class="card-title mb-0">SMS Configuration Details</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Shop</dt>
                        <dd class="col-sm-9">{{ $smsConfig->shop?->name ?? 'Not assigned' }}</dd>
                        
                        <dt class="col-sm-3">Provider</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-{{ $smsConfig->provider == 'twilio' ? 'info' : 'success' }}">
                                {{ ucfirst($smsConfig->provider) }}
                            </span>
                        </dd>
                        
                        <dt class="col-sm-3">API URL</dt>
                        <dd class="col-sm-9">{{ $smsConfig->api_url }}</dd>
                        
                        <dt class="col-sm-3">Sender ID</dt>
                        <dd class="col-sm-9">{{ $smsConfig->sender_id }}</dd>
                        
                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-{{ $smsConfig->is_active ? 'success' : 'secondary' }}">
                                {{ $smsConfig->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </dd>
                        
                        <dt class="col-sm-3">Default Configuration</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-{{ $smsConfig->is_default ? 'warning' : 'light' }} text-dark">
                                {{ $smsConfig->is_default ? 'Yes' : 'No' }}
                            </span>
                        </dd>
                        
                        <dt class="col-sm-3">Rate Limit (per minute)</dt>
                        <dd class="col-sm-9">{{ $smsConfig->rate_limit_per_minute }}</dd>
                        
                        <dt class="col-sm-3">Created At</dt>
                        <dd class="col-sm-9">{{ $smsConfig->created_at->format('Y-m-d H:i:s') ?? '-'}}</dd>
                        
                        <dt class="col-sm-3">Updated At</dt>
                        <dd class="col-sm-9">{{ $smsConfig->updated_at->format('Y-m-d H:i:s') ?? '-' }}</dd>
                     </dl>
                 </div>
             </div>
         </div>
     </div>
 </div>
 @endsection

 @push('css')
 <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
 @endpush