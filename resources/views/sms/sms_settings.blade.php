@extends('adminlte::page')

@section('title', 'SMS Settings - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-sms"></i> SMS Settings</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-sms"></i> SMS Settings</h1>
                <p class="mb-0 text-light">Managing SMS Settings for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('settings.general_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>

        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">SMS Settings</li>
        </ol>
    </nav>
    <a href="{{ route('settings.general_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
</div>
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

<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">SMS Settings</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- SMS Analystics  -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-primary elevation-1">
                        <i class="fas fa-chart-bar"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">SMS Analytics</span>
                        <span class="info-box-number">
                            Monitor SMS delivery and performance
                        </span>
                        <div class="mt-2">
                            <a href="{{ route('sms.analytics.index') }}" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-cog mr-1"></i> Configure
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMS configurations -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-success elevation-1">
                        <i class="fas fa-cog"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">SMS Configurations</span>
                        <span class="info-box-number">
                            Manage SMS provider configurations
                        </span>
                        <div class="mt-2">
                            <a href="{{ Route('sms.configs.index') }}" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-cog mr-1"></i> Configure
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMS Events -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-secondary elevation-1">
                        <i class="fas fa-bolt"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">SMS Events</span>
                        <span class="info-box-number">
                            Map events to SMS templates
                        </span>
                        <div class="mt-2">
                            <a href="{{ Route('sms.events.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-cog mr-1"></i> Configure
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMS Logs -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-warning elevation-1">
                        <i class="fas fa-sms"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">SMS Logs</span>
                        <span class="info-box-number">
                            View SMS delivery logs
                        </span>
                        <div class="mt-2">
                            <a href="{{ Route('sms.logs.index') }}" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-cog mr-1"></i> Configure
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMS Templates -->
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="info-box bg-gradient-white shadow-sm border">
                    <span class="info-box-icon bg-info elevation-1">
                        <i class="fas fa-file-alt"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">SMS Templates</span>
                        <span class="info-box-number">
                            Manage SMS templates
                        </span>
                        <div class="mt-2">
                            <a href="{{ Route('sms.templates.index') }}" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-cog mr-1"></i> Configure
                            </a>
                        </div>
                    </div>
                </div>
            </div>




        </div>
    </div>
</div>

@push('css')
<style>
    .info-box {
        border-radius: 0.50rem;
        min-height: 120px;
        transition: all 0.3s ease;
    }
    
    .info-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
    }
    
    .info-box-icon {
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        border-radius: 0.50rem;
        margin: 15px;
    }
    
    .info-box-content {
        padding: 10px 15px 10px 0;
    }
    
    .info-box-text {
        display: block;
        font-size: 1rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.25rem;
    }
    
    .info-box-number {
        display: block;
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    
    .btn-outline-primary {
        --bs-btn-hover-color: #fff;
    }
    
    .btn-outline-success {
        --bs-btn-hover-color: #fff;
    }
    
    .btn-outline-info {
        --bs-btn-hover-color: #fff;
    }
    
    .card {
        border-top: 3px solid #007bff;
        box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    }
</style>
@endpush

    </div>


    @push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    @endpush
    @stop

    @section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    @if(session('success'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: "{{ session('success') }}",
            showConfirmButton: true,
            timerProgressBar: true,
            timer: 2500
        });
    });
    @endif

    @if(session('error'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: "{{ session('error') }}",
            showConfirmButton: true

        });
    });
    @endif
    </script>
    @stop