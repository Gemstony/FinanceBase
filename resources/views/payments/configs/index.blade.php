@extends('adminlte::page')

@section('title', 'Payment Configurations')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-credit-card"></i> Payment Configurations</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-credit-card"></i> Payments</h1>
                <p class="mb-0 text-light">Manage payment provider configurations</p>
            </div>
            <a href="{{ route('settings.payment_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.payment_settings.index') }}">Payment Settings</a></li>
            <li class="breadcrumb-item active text-dark" aria-current="page">Payment Configurations</li>
        </ol>
    </nav>
</div>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Payment Providers</h3>
            <div class="card-tools">
                <a href="{{ route('payments.configs.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Provider
                </a>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    {{ session('error') }}
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Environment</th>
                            <th>Status</th>
                            <th>Default</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($configs as $config)
                            <tr>
                                <td>
                                    <span class="badge badge-{{ $config->provider === 'mpesa' ? 'success' : ($config->provider === 'airtel' ? 'danger' : 'info') }}">
                                        {{ ucfirst($config->provider) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $config->environment === 'live' ? 'success' : 'warning' }}">
                                        {{ ucfirst($config->environment) }}
                                    </span>
                                </td>
                                <td>
                                    @if($config->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($config->is_default)
                                        <span class="badge badge-primary">Default</span>
                                    @else
                                        <span class="badge badge-secondary">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('payments.configs.edit', $config->id) }}" class="btn btn-info btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    @if(!$config->is_default)
                                        <form action="{{ route('payments.configs.setDefault', $config->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fas fa-star"></i> Set Default
                                            </button>
                                        </form>
                                    @endif
                                    <form action="{{ route('payments.configs.delete', $config->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this configuration?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No payment configurations found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop
@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
