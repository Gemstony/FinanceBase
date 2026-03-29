@extends('adminlte::page')

@section('title', 'SMS Configurations')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-cog"></i> SMS Configurations</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-cog"></i> SMS Configs</h1>
                <p class="mb-0 text-light">Manage SMS provider configurations</p>
            </div>
            <a href="{{ route('settings.sms_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>

        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.sms_settings.index') }}">SMS Settings</a></li>

            <li class="breadcrumb-item active text-dark" aria-current="page">SMS Configurations</li>
        </ol>
    </nav>
    <a href="{{ route('settings.sms_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
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
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">SMS Configurations</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
                        <div>
                            <a href="{{ route('sms.configs.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Configuration
                            </a>
                        </div>
                    </div>

                    <!-- @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show mb-4">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif -->

                    @if($configs->isEmpty())
                        <div class="alert alert-info">
                            No SMS configurations found.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Shop</th>
                                        <th>Provider</th>
                                        <th>Sender ID</th>
                                        <th>Status</th>
                                        <th>Default</th>
                                        <th>Rate Limit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($configs as $config)
                                        <tr>
                                            <td>{{ $config->shop->name }}</td>
                                            <td>
                                                <span class="badge bg-{{ $config->provider == 'twilio' ? 'info' : 'success' }}">
                                                    {{ ucfirst($config->provider) }}
                                                </span>
                                            </td>
                                            <td>{{ $config->sender_id }}</td>
                                            <td>
                                                <span class="badge bg-{{ $config->is_active ? 'success' : 'secondary' }}">
                                                    {{ $config->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $config->is_default ? 'warning' : 'light' }} text-dark">
                                                    {{ $config->is_default ? 'Yes' : 'No' }}
                                                </span>
                                            </td>
                                            <td>{{ $config->rate_limit_per_minute }}/min</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('sms.configs.show', $config->id) }}" class="btn btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('sms.configs.edit', $config->id) }}" class="btn btn-outline-secondary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('sms.configs.destroy', $config->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this SMS configuration?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                         <div class="mt-4">
                             {{ $configs->links() }}
                         </div>
                     @endif
                 </div>
             </div>
         </div>
     </div>
 </div>
 @endsection

 @push('css')
 <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
 @endpush