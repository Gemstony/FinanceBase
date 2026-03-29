@extends('adminlte::page')

@section('title', 'Data Management')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body text-center">
            <h1 class="d-none d-md-block text-light"><i class="fas fa-database text-warning"></i> <strong>DB</strong> Data Management Panel</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-database text-warning"></i> <strong>DB</strong> Data Management</h1>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active text-dark d-none d-md-inline" aria-current="page">Data Management Panel</li>
                <li class="breadcrumb-item active text-dark d-md-none" aria-current="page">Data Management</li>
            </ol>
        </nav>

    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h5><i class="icon fas fa-check"></i> Success!</h5>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h5><i class="icon fas fa-ban"></i> Error!</h5>
            {{ session('error') }}
        </div>
    @endif

    <div class="row">
        <!-- Storage Usage Statistics -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-hdd mr-2"></i>
                        Storage Usage Statistics
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="info-box bg-light">
                                <div class="info-box-content">
                                    <span class="info-box-text">App Storage</span>
                                    <span class="info-box-number">{{ number_format($storageSizeMB, 2) }} MB</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-box bg-light">
                                <div class="info-box-content">
                                    <span class="info-box-text">Disk Usage</span>
                                    <span class="info-box-number">{{ number_format($diskUsagePercent, 2) }}%</span>
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" style="width: {{ $diskUsagePercent }}%"></div>
                                    </div>
                                    <span class="progress-description">
                                        {{ number_format($diskUsed / 1024 / 1024 / 1024, 2) }} GB used of {{ number_format($diskTotal / 1024 / 1024 / 1024, 2) }} GB
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Information -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-database mr-2"></i>
                        Database Information
                    </h3>
                </div>
                <div class="card-body">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text">Database Size</span>
                            <span class="info-box-number">{{ number_format($dbSizeMB, 2) }} MB</span>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Database Backup -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-download mr-2"></i>
                        Database Backup
                    </h3>
                    <div class="card-tools">
                        <form action="{{ route('data.backup') }}" method="POST" class="d-inline create-backup-form">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus mr-1"></i>
                                Create Backup
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body table-responsive p-2">
                    <table class="table table-hover text-nowrap" id="databaseBackupTable">
                        <thead>
                            <tr>
                                <th>Backup File</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($backups as $backup)
                                <tr>
                                    <td>{{ $backup['name'] }}</td>
                                    <td>{{ $backup['size'] }}</td>
                                    <td>{{ $backup['date'] }}</td>
                                    <td>
                                        <a href="{{ route('data.backup.download', $backup['name']) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <form action="{{ route('data.backup.delete', $backup['name']) }}" method="POST" class="d-inline delete-backup-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No backups found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bolt mr-2"></i>
                        Quick Actions
                    </h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <form action="{{ route('data.backup') }}" method="POST" class="create-backup-form">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Create Database Backup
                            </button>
                        </form>
                        <p class="text-sm text-muted mb-0">
                            <i class="fas fa-info-circle mr-1"></i>
                            Backups are stored in storage/app/backups/
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@section('js')

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Handle backup creation with SweetAlert confirmation
        $(document).on('submit', '.create-backup-form', function(e) {
            e.preventDefault();

            const form = $(this);

            Swal.fire({
                title: 'Create Database Backup?',
                text: 'This will create a complete backup of your database. This process may take a few moments.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, create backup!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Creating Backup...',
                        text: 'Please wait while we create your database backup.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form[0].submit();
                }
            });

            return false;
        });

        // Handle backup deletion with SweetAlert confirmation
        $(document).on('submit', '.delete-backup-form', function(e) {
            e.preventDefault();

            const form = $(this);
            const backupName = form.find('input[name="_method"]').closest('form').attr('action').split('/').pop();

            Swal.fire({
                title: 'Delete Backup?',
                text: `Are you sure you want to delete "${backupName}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form[0].submit();
                }
            });

            return false;
        });
    </script>
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#databaseBackupTable').DataTable({
        responsive: true,
     
        order: [
            [2, 'desc']
        ] // Sort by code by default
    });
});
 </script>
@stop

