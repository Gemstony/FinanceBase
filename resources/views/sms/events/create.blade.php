@extends('adminlte::page')

@section('title', 'Add SMS Event')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-plus-circle"></i> Add SMS Event</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-plus-circle"></i> Add Event</h1>
                <p class="mb-0 text-light">Map an event to an SMS template</p>
            </div>
            <a href="{{ route('sms.events.index') }}" class="btn btn-light">
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
            <li class="breadcrumb-item"><a href="{{ route('sms.events.index') }}">SMS Event Mappings</a></li>
        <li class="breadcrumb-item active" aria-current="page">Add Event</li>
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
                    <h5 class="card-title mb-0">Add SMS Event</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('sms.events.store') }}" method="POST">
                        @csrf
                        

                        
                        <div class="row mb-3">
                            <label for="event_name" class="col-md-2 col-form-label">Event Name*</label>
                            <div class="col-md-10">
                                <input type="text" name="event_name" id="event_name" class="form-control" placeholder="e.g., loan.disbursed" required>
                                <small class="text-muted">Common events: loan.disbursed, payment.received, otp.generated</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="template_id" class="col-md-2 col-form-label">SMS Template*</label>
                            <div class="col-md-10">
                                <select name="template_id" id="template_id" class="form-select" required>
                                    <option value="">Select Template</option>
                                    @foreach($templates as $template)
                                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="is_enabled" class="col-md-2 col-form-label">Status</label>
                            <div class="col-md-10">
                                <div class="form-check">
                                    <input type="hidden" name="is_enabled" value="0">
                                    <input type="checkbox" name="is_enabled" id="is_enabled" class="form-check-input" value="1" checked>
                                    <label class="form-check-label" for="is_enabled">Enabled</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-10 offset-md-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Event
                                </button>
                                <a href="{{ route('sms.events.index') }}" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('scripts')
<script>
    // Any event-specific scripts can go here
</script>
@endpush