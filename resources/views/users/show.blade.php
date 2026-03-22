@extends('adminlte::page')

@section('title', 'User - ' . $user->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-user"></i> User Details</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-user"></i> User</h1>
                    <p class="mb-0 text-light">Viewing: <strong>{{ $user->name }}</strong></p>
                </div>
                <div>
                    <a href="{{ route('users.edit', $user->id) }}" class="btn btn-warning btn-sm border mr-1">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="{{ route('users.index') }}" class="btn btn-light btn-sm border">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
                <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page">View User</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <!-- User Profile Card -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-circle"></i> Profile</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                            <i class="fas fa-user fa-3x text-primary"></i>
                        </div>
                    </div>
                    <h4 class="mb-1">{{ $user->name }}</h4>
                    <p class="text-muted mb-2">{{ $user->email }}</p>
                    <span class="badge badge-lg badge-{{ $user->getRoleNames()->first() == 'admin' ? 'danger' : ($user->getRoleNames()->first() == 'shopkeeper' ? 'success' : 'info') }}">
                        {{ $user->getRoleNames()->first() ?? 'No Role' }}
                    </span>
                    <hr>
                    <div class="text-left">
                        <p class="mb-1"><i class="fas fa-phone text-info"></i> <strong>Phone:</strong> {{ $user->phone_number ?? '—' }}</p>
                        <p class="mb-1"><i class="fas fa-calendar text-info"></i> <strong>Joined:</strong> {{ $user->created_at->format('M d, Y') }}</p>
                        <p class="mb-0"><i class="fas fa-clock text-info"></i> <strong>Last Updated:</strong> {{ $user->updated_at->format('M d, Y') }}</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-cogs"></i> Actions</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('users.edit', $user->id) }}" class="btn btn-warning btn-block mb-2">
                        <i class="fas fa-edit"></i> Edit User
                    </a>
                    <button type="button" class="btn btn-outline-warning btn-block mb-2" data-toggle="modal" data-target="#resetPasswordModal">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-block" data-toggle="modal" data-target="#assignRoleModal">
                        <i class="fas fa-user-tag"></i> Change Role
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Branch Assignments -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-store"></i> Branch Assignments</h5>
                    <button type="button" class="btn btn-light btn-sm" data-toggle="modal" data-target="#assignBranchesModal">
                        <i class="fas fa-edit"></i> Manage
                    </button>
                </div>
                <div class="card-body">
                    @if($user->subshops->count() > 0)
                        <div class="row">
                            @foreach($user->subshops as $subshop)
                                <div class="col-md-6 mb-2">
                                    <div class="border rounded p-2">
                                        <i class="fas fa-store text-info"></i> <strong>{{ $subshop->name }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $subshop->location ?? 'No location' }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-store-slash fa-2x mb-2"></i>
                            <p>No branches assigned to this user</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Performance Statistics -->
            @if(!empty($stats))
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Performance by Branch</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($stats as $stat)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="border-bottom pb-2 mb-3">
                                            <i class="fas fa-store text-info"></i> {{ $stat['subshop_name'] }}
                                        </h6>

                                        @php
                                            $portfolioClass = $stat['portfolio_value'] > 0 ? 'bg-primary' : 'bg-secondary';
                                        @endphp

                                        <div class="mb-3">
                                            <small class="text-muted">Portfolio Value</small>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar {{ $portfolioClass }}" style="width: 100%">
                                                    Tsh{{ number_format($stat['portfolio_value'], 0) }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h5 class="mb-0 text-primary">{{ $stat['loans_disbursed'] }}</h5>
                                                <small class="text-muted">Loans Disbursed</small>
                                                <br><small class="text-success">Tsh{{ number_format($stat['disbursement_amount'], 0) }}</small>
                                            </div>
                                            <div class="col-6">
                                                <h5 class="mb-0 text-success">{{ $stat['repayments_count'] }}</h5>
                                                <small class="text-muted">Repayments</small>
                                                <br><small class="text-success">Tsh{{ number_format($stat['repayments_amount'], 0) }}</small>
                                            </div>
                                        </div>

                                        <hr class="my-2">

                                        <div class="row text-center">
                                            <div class="col-3">
                                                <small class="text-info font-weight-bold">{{ $stat['active_loans'] }}</small>
                                                <br><small class="text-muted">Active</small>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-warning font-weight-bold">{{ $stat['pending_approvals'] }}</small>
                                                <br><small class="text-muted">Pending</small>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-danger font-weight-bold">{{ $stat['writeoffs_count'] }}</small>
                                                <br><small class="text-muted">Write-offs</small>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-danger font-weight-bold">{{ $stat['overdue_loans'] }}</small>
                                                <br><small class="text-muted">Overdue</small>
                                            </div>
                                        </div>

                                        @if($stat['overdue_amount'] > 0)
                                            <div class="mt-2 text-center">
                                                <small class="text-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    Overdue Amount: <strong>Tsh{{ number_format($stat['overdue_amount'], 0) }}</strong>
                                                </small>
                                            </div>
                                        @endif

                                        @if($stat['writeoffs_amount'] > 0)
                                            <div class="mt-1 text-center">
                                                <small class="text-muted">
                                                    Write-off Amount: <strong>Tsh{{ number_format($stat['writeoffs_amount'], 0) }}</strong>
                                                </small>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
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

<!-- Assign Role Modal -->
<div class="modal fade" id="assignRoleModal" tabindex="-1" role="dialog" aria-labelledby="assignRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('users.update', $user->id) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" value="{{ $user->name }}">
                <input type="hidden" name="email" value="{{ $user->email }}">
                <input type="hidden" name="phone_number" value="{{ $user->phone_number }}">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title" id="assignRoleModalLabel"><i class="fas fa-user-tag"></i> Change Role</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Changing role for: <strong>{{ $user->name }}</strong></p>
                    <div class="form-group">
                        <label for="modal_role">Select Role <span class="text-danger">*</span></label>
                        <select class="form-control" id="modal_role" name="role" required>
                            <option value="">-- Select Role --</option>
                            @foreach($roles as $role)
                                @if($role->name !== 'Super Admin' && $role->name !== 'owner')
                                    <option value="{{ $role->name }}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>{{ $role->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Current role: <strong>{{ $user->getRoleNames()->first() ?? 'None' }}</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Branches Modal -->
<div class="modal fade" id="assignBranchesModal" tabindex="-1" role="dialog" aria-labelledby="assignBranchesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('users.assign-subshops', $user->id) }}">
                @csrf
                <div class="modal-header bg-info">
                    <h5 class="modal-title" id="assignBranchesModalLabel"><i class="fas fa-store"></i> Manage Branch Assignments</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Managing branches for: <strong>{{ $user->name }}</strong></p>
                    <div class="form-group">
                        <label for="modal_subshop_ids">Select Branches</label>
                        <select class="form-control select2" id="modal_subshop_ids" name="subshop_ids[]" multiple required>
                            @foreach($subshops as $s)
                                <option value="{{ $s->id }}" {{ $user->subshops->contains($s->id) ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Select one or more branches to assign to this user</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Save Assignments</button>
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
    $('#modal_subshop_ids').select2({ width: '100%', placeholder: 'Select branches' });
});
</script>
@endpush
