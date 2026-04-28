@extends('adminlte::page')

@section('title', 'SMS Log Details')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-sms"></i> SMS Log Details</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-sms"></i> Log Details</h1>
                <p class="mb-0 text-light">View detailed SMS log information</p>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-light">
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
        <li class="breadcrumb-item"><a href="{{ route('sms.logs.index') }}">SMS Logs</a></li>
        <li class="breadcrumb-item active" aria-current="page">Log Details</li>
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
                    <h5 class="card-title mb-0">SMS Log Details</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Shop</dt>
                        <dd class="col-sm-9">{{ $smsLog->shop ? $smsLog->shop->name : 'N/A' }}</dd>
                        
                        <dt class="col-sm-3">SubShop</dt>
                        <dd class="col-sm-9">{{ $smsLog->subshop ? $smsLog->subshop->name : 'N/A' }}</dd>
                        
                        <dt class="col-sm-3">User</dt>
                        <dd class="col-sm-9">{{ $smsLog->user ? $smsLog->user->name : 'N/A' }}</dd>
                        
                        <dt class="col-sm-3">Phone Number</dt>
                        <dd class="col-sm-9">{{ $smsLog->phone }}</dd>
                        
                        <dt class="col-sm-3">Message</dt>
                        <dd class="col-sm-9">
                            @if($smsLog->message === '[REDACTED]')
                                <span class="text-danger fs-5">[REDACTED]</span>
                            @else
                                {{ $smsLog->message }}
                            @endif
                        </dd>
                        
                        <dt class="col-sm-3">Template</dt>
                        <dd class="col-sm-9">
                            @if($smsLog->template)
                                {{ $smsLog->template->name }} ({{ $smsLog->template->event }})
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </dd>
                        
                        <dt class="col-sm-3">Event</dt>
                        <dd class="col-sm-9">
                            @if($smsLog->event)
                                <span class="badge bg-info">{{ $smsLog->event }}</span>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </dd>
                        
                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">
                        @php
                            $statusColors = [
                                'sent' => 'success',
                                'failed' => 'danger',
                                'error' => 'danger',
                                'queued' => 'warning',
                                'retrying' => 'warning',
                            ];
                        @endphp

                        <span class="badge bg-{{ $statusColors[$smsLog->status] ?? 'secondary' }}"> 
                            {{ ucfirst($smsLog->status) }}
                        </span>
                        </dd>
                        
                        <dt class="col-sm-3">Provider</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-{{ $smsLog->provider == 'twilio' ? 'info' : 'success' }}">
                                {{ ucfirst($smsLog->provider) }}
                            </span>
                        </dd>
                        
                        <dt class="col-sm-3">Provider Message ID</dt>
                        <dd class="col-sm-9">{{ $smsLog->provider_message_id ?? 'N/A' }}</dd>
                        
                        <dt class="col-sm-3">Attempts</dt>
                        <dd class="col-sm-9">{{ $smsLog->attempts }}</dd>
                        
                        <dt class="col-sm-3">Cost</dt>
                        <dd class="col-sm-9">{{ $smsLog->cost ?? 'N/A' }}</dd>
                        
                        <dt class="col-sm-3">Error Code</dt>
                        <dd class="col-sm-9">{{ $smsLog->error_code ?? 'N/A' }}</dd>
                        
                        <dt class="col-sm-3">Error Message</dt>
                        <dd class="col-sm-9">{{ $smsLog->error_message ?? 'N/A' }}</dd>
                        
                        <dt class="col-sm-3">Sent At</dt>
                        <dd class="col-sm-9">{{ $smsLog->sent_at ? $smsLog->sent_at->format('Y-m-d H:i:s') : 'N/A' }}</dd>
                        
                        <dt class="col-sm-3">Delivered At</dt>
                        <dd class="col-sm-9">{{ $smsLog->delivered_at ? $smsLog->delivered_at->format('Y-m-d H:i:s') : 'N/A' }}</dd>
                        
                        <dt class="col-sm-3">Provider Response</dt>
                        <dd class="col-sm-9">
                            @if($smsLog->provider_response)
                                <pre class="bg-light p-3 rounded">{{ json_encode(json_decode($smsLog->provider_response), JSON_PRETTY_PRINT) }}</pre>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </dd>
                        
                        <dt class="col-sm-3">Created At</dt>
                        <dd class="col-sm-9">{{ $smsLog->created_at->format('Y-m-d H:i:s') }}</dd>
                        
                        <dt class="col-sm-3">Updated At</dt>
                        <dd class="col-sm-9">{{ $smsLog->updated_at->format('Y-m-d H:i:s') }}</dd>
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