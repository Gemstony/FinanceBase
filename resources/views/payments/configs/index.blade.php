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
                <a href="{{ route('payments.configs.test') }}" class="btn btn-info btn-sm ml-2">
                    <i class="fas fa-vial"></i> Test
                </a>
            </div>
        </div>
        <div class="card-body">

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
                                    @php
                                        $badgeClass = match($config->provider) {
                                            'mpesa' => 'success',
                                            'airtel' => 'danger',
                                            'tigo' => 'info',
                                            'clickpesa' => 'primary',
                                            'azampay' => 'warning',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $badgeClass }}">
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
                                    <form action="{{ route('payments.configs.delete', $config->id) }}" method="POST" class="d-inline" data-swal-confirm>
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
@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteForms = document.querySelectorAll('form[data-swal-confirm]');

        deleteForms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                Swal.fire({
                    title: 'Delete Payment Configuration',
                    text: 'Are you sure you want to delete this payment configuration?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Deleting...',
                            text: 'Please wait while the payment configuration is deleted.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        form.submit();
                    }
                });
            });
        });

        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: {!! json_encode(session('success')) !!},
                confirmButtonText: 'OK'
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: {!! json_encode(session('error')) !!},
                confirmButtonText: 'OK'
            });
        @endif
    });
</script>
@endpush

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
