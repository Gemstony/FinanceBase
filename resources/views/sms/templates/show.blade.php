@extends('adminlte::page')

@section('title', 'SMS Template Details')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-file-alt"></i> SMS Template Details</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-file-alt"></i> Template Details</h1>
                <p class="mb-0 text-light">View SMS template details</p>
            </div>
            <a href="{{ route('sms.templates.index') }}" class="btn btn-light">
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
        <li class="breadcrumb-item"><a href="{{ route('sms.templates.index') }}">SMS Templates</a></li>
        <li class="breadcrumb-item active" aria-current="page">Template Details</li>
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
                    <h5 class="card-title mb-0">SMS Template Details</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Shop</dt>
                        <dd class="col-sm-9">{{ $smsTemplate->shop->name }}</dd>
                        
                        <dt class="col-sm-3">Name</dt>
                        <dd class="col-sm-9">{{ $smsTemplate->name }}</dd>
                        
                        <dt class="col-sm-3">Event</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-info">{{ $smsTemplate->event }}</span>
                        </dd>
                        
                        <dt class="col-sm-3">Message Template</dt>
                        <dd class="col-sm-9">{!! $smsTemplate->message_template !!}</dd>
                        
                        <dt class="col-sm-3">Variables</dt>
                        <dd class="col-sm-9">
                            @if(count($smsTemplate->variables) > 0)
                                @foreach($smsTemplate->variables as $variable)
                                    <span class="badge bg-primary bg-opacity-10 text-primary me-1 mb-1">{{ $variable }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">None</span>
                            @endif
                        </dd>
                        
                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-{{ $smsTemplate->is_active ? 'success' : 'secondary' }}">
                                {{ $smsTemplate->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </dd>
                        
                        <dt class="col-sm-3">Created At</dt>
                        <dd class="col-sm-9">{{ $smsTemplate->created_at->format('Y-m-d H:i:s') }}</dd>
                        
                        <dt class="col-sm-3">Updated At</dt>
                        <dd class="col-sm-9">{{ $smsTemplate->updated_at->format('Y-m-d H:i:s') }}</dd>
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