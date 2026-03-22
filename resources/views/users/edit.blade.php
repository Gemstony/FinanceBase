@extends('adminlte::page')

@section('title', 'Edit User - ' . $user->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-user-edit"></i> Edit User</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-user-edit"></i> Edit</h1>
                    <p class="mb-0 text-light">Editing: <strong>{{ $user->name }}</strong></p>
                </div>
                <a href="{{ route('users.index') }}" class="btn btn-light btn-sm border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
                <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit User</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-user-edit"></i> Edit User Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.update', $user->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                    @error('name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                                    @error('email')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone_number">Phone Number</label>
                                    <input type="text" class="form-control @error('phone_number') is-invalid @enderror" id="phone_number" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}">
                                    @error('phone_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role">Role <span class="text-danger">*</span></label>
                                    <select class="form-control @error('role') is-invalid @enderror" id="role" name="role" required>
                                        <option value="">-- Select Role --</option>
                                        @foreach($roles as $role)
                                            @if($role->name !== 'Super Admin' && $role->name !== 'owner')
                                                <option value="{{ $role->name }}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>{{ $role->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @error('role')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="subshop_ids">Assign Branches</label>
                            <select class="form-control select2" id="subshop_ids" name="subshop_ids[]" multiple>
                                @foreach($subshops as $s)
                                    <option value="{{ $s->id }}" {{ $user->subshops->contains($s->id) ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Select one or more branches to assign to this user</small>
                        </div>

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save"></i> Update User
                            </button>
                            <a href="{{ route('users.show', $user->id) }}" class="btn btn-info">
                                <i class="fas fa-eye"></i> View User
                            </a>
                            <a href="{{ route('users.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> User Info</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>ID:</strong> #{{ $user->id }}</p>
                    <p class="mb-1"><strong>Created:</strong> {{ $user->created_at->format('M d, Y') }}</p>
                    <p class="mb-1"><strong>Updated:</strong> {{ $user->updated_at->format('M d, Y') }}</p>
                    <hr>
                    <p class="mb-1"><strong>Current Branches:</strong></p>
                    @forelse($user->subshops as $subshop)
                        <span class="badge badge-info mr-1">{{ $subshop->name }}</span>
                    @empty
                        <span class="text-muted">No branches assigned</span>
                    @endforelse
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-key"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('users.show', $user->id) }}" class="btn btn-outline-info btn-block mb-2">
                        <i class="fas fa-eye"></i> View Full Details
                    </a>
                    <button type="button" class="btn btn-outline-warning btn-block" data-toggle="modal" data-target="#resetPasswordModal">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('users.reset-password', $user->id) }}">
                @csrf
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="resetPasswordModalLabel"><i class="fas fa-key"></i> Reset Password</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Resetting password for: <strong>{{ $user->name }}</strong></p>
                    <div class="form-group">
                        <label for="password">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required minlength="8">
                        <small class="form-text text-muted">Password must be at least 8 characters</small>
                        @error('password')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required minlength="8">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
    $('#subshop_ids').select2({ width: '100%', placeholder: 'Select branches' });
});
</script>
@endpush
