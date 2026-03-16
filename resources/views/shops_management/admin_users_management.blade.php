@extends('adminlte::page')

@section('title', 'Users Management')

@push('styles')
<style>
    .user-checkbox, #selectAll {
        transform: scale(1.4);
        margin: 0;
        cursor: pointer;
        opacity: 1 !important;
        position: relative;
        z-index: 10;
    }
    
    .table th:first-child, .table td:first-child {
        padding-left: 25px !important;
        min-width: 70px;
        text-align: left;
    }
    
    .table-responsive {
        overflow-x: auto;
        margin-left: 0;
        margin-right: 0;
    }
    
    .table {
        margin-left: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    @media (max-width: 768px) {
        .user-checkbox, #selectAll {
            transform: scale(1.3);
        }
        
        .table th:first-child, .table td:first-child {
            padding-left: 15px !important;
            min-width: 60px;
        }
    }
    
    /* Ensure checkboxes are visible */
    .form-check-input {
        background-color: #fff !important;
        border: 2px solid #6c757d !important;
    }
    
    .form-check-input:checked {
        background-color: #007bff !important;
        border-color: #007bff !important;
    }
    
    .form-check-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
        border-color: #007bff !important;
    }
</style>
@endpush

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body text-center">
            <h1 class="d-none d-md-block text-light"><i class="fas fa-users text-warning"></i> <strong>DB</strong> Users Management Panel</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-users text-warning"></i> <strong>DB</strong> Users</h1>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active text-dark d-none d-md-inline" aria-current="page">Users Management Panel</li>
                <li class="breadcrumb-item active text-dark d-md-none" aria-current="page">Users</li>
            </ol>
        </nav>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" onclick="exportUsers()">
                <i class="fas fa-download"></i> Export
            </button>
            <button type="button" class="btn btn-info btn-sm" onclick="refreshUsers()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>
@stop

@section('content')
    <!-- Success Messages -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: "{{ session('success') }}",
                showConfirmButton: false,
                timer: 2500
            });
        @endif

        @if (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Kuna Tatizo!',
                text: "{{ session('error') }}",
                showConfirmButton: true
            });
        @endif

        @if (session('warning'))
            Swal.fire({
                icon: 'warning',
                title: 'Angalizo!',
                text: "{{ session('warning') }}",
                showConfirmButton: true
            });
        @endif

        @if (session('info'))
            Swal.fire({
                icon: 'info',
                title: 'Taarifa',
                text: "{{ session('info') }}",
                showConfirmButton: false,
                timer: 2500
            });
        @endif
    </script>

    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
        </div>
        <div class="card-body">
            <form id="filterForm" method="GET" action="{{ route('superadmin.users.index') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label for="role_filter">Role</label>
                        <select class="form-control" id="role_filter" name="role">
                            <option value="">All Roles</option>
                            <option value="Super Admin">Super Admin</option>
                            <option value="owner">Owner</option>
                            <option value="shopkeeper">Shopkeeper</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status_filter">Status</label>
                        <select class="form-control" id="status_filter" name="status">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="unassigned">Unassigned</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="shop_filter">Shop</label>
                        <select class="form-control" id="shop_filter" name="shop">
                            <option value="">All Shops</option>
                            @if(isset($shops))
                                @foreach($shops as $shop)
                                    <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search_filter">Search</label>
                        <input type="text" class="form-control" id="search_filter" name="search" placeholder="Name, Email, Phone..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="{{ route('superadmin.users.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Management Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-users"></i> All Users ({{ $users->total() ?? 0 }})</h5>
            <div class="btn-group">
                <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#createUserModal">
                    <i class="fas fa-plus"></i> Create User
                </button>
                <button type="button" class="btn btn-warning btn-sm" onclick="bulkActions()">
                    <i class="fas fa-tasks"></i> Bulk Actions
                </button>
            </div>
        </div>
        <div class="card-body">
            @if(isset($users) && $users->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="usersTable">
                        <thead class="thead-dark">
                            <tr>
                                <th width="80" style="padding-left: 25px; text-align: left;">
                                    <input type="checkbox" id="selectAll" class="form-check-input"> 
                                </th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Shop</th>
                                <th>Subshop</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Last Login</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                <tr>
                                    <td style="padding-left: 15px; text-align: center; min-width: 40px;">
                                        <input type="checkbox" class="user-checkbox form-check-input" value="{{ $user->id }}">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary text-white mr-2" style="width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <strong>{{ $user->name }}</strong>
                                                <br><small class="text-muted">{{ $user->email }}</small>
                                                @if($user->phone_number)
                                                    <br><small class="text-muted"><i class="fas fa-phone"></i> {{ $user->phone_number }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($user->roles && $user->roles->count() > 0)
                                            @php
                                                $role = $user->roles->first();
                                                $roleClass = match($role->name) {
                                                    'Super Admin' => 'badge-danger',
                                                    'owner' => 'badge-primary',
                                                    'shopkeeper' => 'badge-success',
                                                    'staff' => 'badge-info',
                                                    default => 'badge-secondary'
                                                };
                                            @endphp
                                            <span class="badge {{ $roleClass }}">{{ $role->name }}</span>
                                        @else
                                            <span class="badge badge-secondary">No Role</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($user->shop)
                                            <strong>{{ $user->shop->name }}</strong>
                                            @if($user->shop->phone)
                                                <br><small class="text-muted"><i class="fas fa-phone"></i> {{ $user->shop->phone }}</small>
                                            @endif
                                        @elseif($user->subShops && $user->subShops->count() > 0)
                                            <span class="text-muted">Multiple</span>
                                        @else
                                            <span class="badge badge-warning">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($user->subShops && $user->subShops->count() > 0)
                                            @if($user->subShops->count() == 1)
                                                <strong>{{ $user->subShops->first()->name }}</strong>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-info" onclick="showUserSubshops({{ $user->id }}, '{{ $user->name }}')">
                                                    <i class="fas fa-store-alt"></i> {{ $user->subShops->count() }}
                                                </button>
                                            @endif
                                        @else
                                            <span class="badge badge-secondary">None</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $isActive = false;
                                            $statusText = 'Unknown';
                                            
                                            if($user->shop) {
                                                $isActive = $user->shop->is_active;
                                                $statusText = $user->shop->status ?? 'Active';
                                            } elseif($user->subShops && $user->subShops->count() > 0) {
                                                $activeSubshops = $user->subShops->where('is_active', true);
                                                $isActive = $activeSubshops->count() > 0;
                                                $statusText = $isActive ? 'Active' : 'Inactive';
                                            } else {
                                                $isActive = true; // Default to active for unassigned users
                                                $statusText = 'Unassigned';
                                            }
                                        @endphp
                                        <span class="badge badge-{{ $isActive ? 'success' : 'secondary' }}">
                                            {{ $statusText }}
                                        </span>
                                    </td>
                                    <td>{{ $user->created_at->format('M d, Y') }}</td>
                                    <td>
                                        @if($user->authentications && $user->authentications->count() > 0)
                                            <small>{{ $user->authentications->first()->login_at->diffForHumans() }}</small>
                                            <br><small class="text-muted">{{ $user->authentications->first()->login_at->format('M d, Y H:i') }}</small>
                                        @else
                                            <small class="text-muted">Never</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick="editUser({{ $user->id }})" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-info" onclick="viewUser({{ $user->id }})" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning" onclick="resetUserPassword({{ $user->id }}, '{{ $user->name }}')" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            @if($user->email !== auth()->user()->email)
                                                <button type="button" class="btn btn-outline-danger" onclick="deleteUser({{ $user->id }}, '{{ $user->name }}')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                @if(isset($users) && $users->hasPages())
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} entries
                        </div>
                        {{ $users->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h5>No users found</h5>
                    <p class="text-muted">No users match your current filters.</p>
                    <a href="{{ route('superadmin.users.index') }}" class="btn btn-primary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            @endif
        </div>
    </div>

@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@section('js')
    <!-- User Modals -->
    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createUserModalLabel"><i class="fas fa-plus"></i> Create New User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="createUserForm" action="{{ route('superadmin.users.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_name">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="user_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_email">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="user_email" name="email" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_phone">Phone Number</label>
                                    <input type="text" class="form-control" id="user_phone" name="phone_number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_role">Role <span class="text-danger">*</span></label>
                                    <select class="form-control" id="user_role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="Super Admin">Super Admin</option>
                                        <option value="owner">Owner</option>
                                        <option value="shopkeeper">Shopkeeper</option>
                                        <option value="staff">Staff</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_password">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="user_password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="user_password_confirmation" name="password_confirmation" required>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-muted mb-3"><i class="fas fa-store"></i> Assignment</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_shop">Shop</label>
                                    <select class="form-control" id="user_shop" name="shop_id">
                                        <option value="">Select Shop (Optional)</option>
                                        @if(isset($shops))
                                            @foreach($shops as $shop)
                                                <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_subshop">Subshop</label>
                                    <select class="form-control" id="user_subshop" name="subshop_id">
                                        <option value="">Select Subshop (Optional)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel"><i class="fas fa-edit"></i> Edit User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editUserForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_user_name">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_user_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_user_email">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="edit_user_email" name="email" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_user_phone">Phone Number</label>
                                    <input type="text" class="form-control" id="edit_user_phone" name="phone_number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_user_role">Role <span class="text-danger">*</span></label>
                                    <select class="form-control" id="edit_user_role" name="role" required>
                                        <option value="Super Admin">Super Admin</option>
                                        <option value="owner">Owner</option>
                                        <option value="shopkeeper">Shopkeeper</option>
                                        <option value="staff">Staff</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-muted mb-3"><i class="fas fa-store"></i> Assignment</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_user_shop">Shop</label>
                                    <select class="form-control" id="edit_user_shop" name="shop_id">
                                        <option value="">Select Shop (Optional)</option>
                                        @if(isset($shops))
                                            @foreach($shops as $shop)
                                                <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_user_subshop">Subshop</label>
                                    <select class="form-control" id="edit_user_subshop" name="subshop_id">
                                        <option value="">Select Subshop (Optional)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" role="dialog" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewUserModalLabel"><i class="fas fa-eye"></i> User Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="userDetailsContent">
                        <!-- User details will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel"><i class="fas fa-key"></i> Reset Password for <span id="resetUserName"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="resetPasswordForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="reset_password">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="reset_password" name="password" required minlength="8">
                            <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                        </div>
                        <div class="form-group">
                            <label for="reset_password_confirmation">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="reset_password_confirmation" name="password_confirmation" required>
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

    <!-- User Subshops Modal -->
    <div class="modal fade" id="userSubshopsModal" tabindex="-1" role="dialog" aria-labelledby="userSubshopsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userSubshopsModalLabel"><i class="fas fa-store-alt"></i> Subshops for <span id="userSubshopsName"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="userSubshopsContent">
                        <!-- Subshops will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div class="modal fade" id="bulkActionsModal" tabindex="-1" role="dialog" aria-labelledby="bulkActionsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkActionsModalLabel"><i class="fas fa-tasks"></i> Bulk Actions</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Selected users: <span id="selectedCount">0</span></p>
                    <div class="form-group">
                        <label for="bulkAction">Select Action:</label>
                        <select class="form-control" id="bulkAction">
                            <option value="">Choose action...</option>
                            <option value="activate">Activate Users</option>
                            <option value="deactivate">Deactivate Users</option>
                            <option value="delete">Delete Users</option>
                            <option value="assign_role">Assign Role</option>
                            <option value="remove_assignment">Remove Assignment</option>
                        </select>
                    </div>
                    <div class="form-group" id="roleSelectionGroup" style="display: none;">
                        <label for="bulkRole">Select Role:</label>
                        <select class="form-control" id="bulkRole">
                            <option value="Super Admin">Super Admin</option>
                            <option value="owner">Owner</option>
                            <option value="shopkeeper">Shopkeeper</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="executeBulkAction()">Execute Action</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Users data for editing
        const usersData = @json(isset($users) ? $users->getCollection()->toArray() : []);
        const shopsData = @json(isset($shops) ? $shops->toArray() : []);

        $(document).ready(function() {
            // Initialize DataTable
            $('#usersTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[7, 'desc']] // Sort by join date
            });

            // Select all checkbox
            $('#selectAll').change(function() {
                $('.user-checkbox').prop('checked', $(this).prop('checked'));
                updateSelectedCount();
            });

            // Individual checkboxes
            $('.user-checkbox').change(function() {
                updateSelectedCount();
            });

            // Shop selection for subshops
            $('#user_shop, #edit_user_shop').change(function() {
                const shopId = $(this).val();
                const subshopSelect = $(this).closest('.modal').find('select[name="subshop_id"]');
                
                subshopSelect.empty().append('<option value="">Select Subshop (Optional)</option>');
                
                if (shopId) {
                    const shop = shopsData.find(s => s.id == shopId);
                    if (shop && shop.sub_shops) {
                        shop.sub_shops.forEach(subshop => {
                            subshopSelect.append(`<option value="${subshop.id}">${subshop.name}</option>`);
                        });
                    }
                }
            });
        });

        function updateSelectedCount() {
            const count = $('.user-checkbox:checked').length;
            $('#selectedCount').text(count);
        }

        function editUser(userId) {
            const user = usersData.find(u => u.id == userId);
            if (!user) {
                Swal.fire('Error', 'User not found!', 'error');
                return;
            }

            // Populate form fields
            $('#edit_user_name').val(user.name);
            $('#edit_user_email').val(user.email);
            $('#edit_user_phone').val(user.phone_number || '');
            
            const role = user.roles && user.roles.length > 0 ? user.roles[0].name : 'staff';
            $('#edit_user_role').val(role);

            // Set shop and subshop
            if (user.shop) {
                $('#edit_user_shop').val(user.shop.id);
                // Trigger change to load subshops
                $('#edit_user_shop').trigger('change');
                setTimeout(() => {
                    if (user.sub_shops && user.sub_shops.length > 0) {
                        $('#edit_user_subshop').val(user.sub_shops[0].id);
                    }
                }, 100);
            }

            // Update form action
            $('#editUserForm').attr('action', `/superadmin/users/${userId}`);

            // Show modal
            $('#editUserModal').modal('show');
        }

        function viewUser(userId) {
            const user = usersData.find(u => u.id == userId);
            if (!user) {
                Swal.fire('Error', 'User not found!', 'error');
                return;
            }

            let content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Personal Information</h6>
                        <p><strong>Name:</strong> ${user.name}</p>
                        <p><strong>Email:</strong> ${user.email}</p>
                        <p><strong>Phone:</strong> ${user.phone_number || 'N/A'}</p>
                        <p><strong>Role:</strong> ${user.roles && user.roles.length > 0 ? user.roles[0].name : 'No Role'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Account Information</h6>
                        <p><strong>Joined:</strong> ${new Date(user.created_at).toLocaleDateString()}</p>
                        <p><strong>Last Login:</strong> ${user.last_login_at ? new Date(user.last_login_at).toLocaleString() : 'Never'}</p>
                        <p><strong>Email Verified:</strong> ${user.email_verified_at ? 'Yes' : 'No'}</p>
                    </div>
                </div>
            `;

            if (user.shop || (user.sub_shops && user.sub_shops.length > 0)) {
                content += `
                    <hr>
                    <h6>Shop Assignment</h6>
                `;
                
                if (user.shop) {
                    content += `
                        <div class="alert alert-info">
                            <strong>Main Shop:</strong> ${user.shop.name}<br>
                            ${user.shop.phone ? '<strong>Phone:</strong> ' + user.shop.phone + '<br>' : ''}
                            ${user.shop.address ? '<strong>Address:</strong> ' + user.shop.address : ''}
                        </div>
                    `;
                }
                
                if (user.sub_shops && user.sub_shops.length > 0) {
                    content += '<div class="alert alert-secondary"><strong>Subshops:</strong><ul>';
                    user.sub_shops.forEach(subshop => {
                        content += `<li>${subshop.name} ${subshop.is_active ? '(Active)' : '(Inactive)'}</li>`;
                    });
                    content += '</ul></div>';
                }
            }

            $('#userDetailsContent').html(content);
            $('#viewUserModal').modal('show');
        }

        function resetUserPassword(userId, userName) {
            $('#resetUserName').text(userName);
            $('#resetPasswordForm').attr('action', `/superadmin/users/${userId}/reset-password`);
            $('#resetPasswordForm')[0].reset();
            $('#resetPasswordModal').modal('show');
        }

        function deleteUser(userId, userName) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete the user "${userName}". This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit delete form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/superadmin/users/${userId}`;
                    form.innerHTML = `
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="_method" value="DELETE">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function showUserSubshops(userId, userName) {
            const user = usersData.find(u => u.id == userId);
            if (!user) {
                Swal.fire('Error', 'User not found!', 'error');
                return;
            }

            $('#userSubshopsName').text(userName);
            let content = '';

            if (user.sub_shops && user.sub_shops.length > 0) {
                content = '<div class="table-responsive"><table class="table table-striped table-sm"><thead class="thead-dark"><tr><th>Name</th><th>Phone</th><th>Address</th><th>Status</th><th>Role</th></tr></thead><tbody>';

                user.sub_shops.forEach(subshop => {
                    const pivot = subshop.pivot || {};
                    content += `
                        <tr>
                            <td><strong>${subshop.name}</strong></td>
                            <td>${subshop.phone || 'N/A'}</td>
                            <td>${subshop.address ? subshop.address.substring(0, 50) + (subshop.address.length > 50 ? '...' : '') : 'N/A'}</td>
                            <td><span class="badge badge-${subshop.is_active ? 'success' : 'secondary'}">${subshop.is_active ? 'Active' : 'Inactive'}</span></td>
                            <td><span class="badge badge-info">${pivot.role || 'Staff'}</span></td>
                        </tr>
                    `;
                });

                content += '</tbody></table></div>';
            } else {
                content = '<div class="text-center py-4"><i class="fas fa-store-alt fa-3x text-muted mb-3"></i><h5>No subshops assigned</h5></div>';
            }

            $('#userSubshopsContent').html(content);
            $('#userSubshopsModal').modal('show');
        }

        function bulkActions() {
            const selectedCount = $('.user-checkbox:checked').length;
            if (selectedCount === 0) {
                Swal.fire('Warning', 'Please select at least one user', 'warning');
                return;
            }

            $('#selectedCount').text(selectedCount);
            $('#bulkActionsModal').modal('show');
        }

        function exportUsers() {
            window.location.href = '/superadmin/users/export';
        }

        function refreshUsers() {
            location.reload();
        }

        // Bulk action selection
        $('#bulkAction').change(function() {
            const action = $(this).val();
            $('#roleSelectionGroup').hide();
            
            if (action === 'assign_role') {
                $('#roleSelectionGroup').show();
            }
        });

        function executeBulkAction() {
            const action = $('#bulkAction').val();
            const selectedUsers = $('.user-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (!action) {
                Swal.fire('Warning', 'Please select an action', 'warning');
                return;
            }

            const data = {
                users: selectedUsers,
                action: action,
                _token: '{{ csrf_token() }}'
            };

            if (action === 'assign_role') {
                data.role = $('#bulkRole').val();
            }

            $.post('/superadmin/users/bulk-action', data)
                .done(function(response) {
                    Swal.fire('Success', response.message, 'success').then(() => {
                        location.reload();
                    });
                })
                .fail(function(xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong', 'error');
                });
        }
    </script>
@stop