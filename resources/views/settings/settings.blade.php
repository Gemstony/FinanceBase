@extends('adminlte::page')

@section('title', 'Roles and Permissions Management')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-user-shield"></i> Roles & Permissions</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-user-shield"></i> Permissions</h1>
                    <p class="mb-0 text-light">Manage roles and assign permissions.</p>
                </div>
                <a href="{{ route('settings.profile.show') }}" class="btn btn-light">
                    <i class="fas fa-user-cog mr-1"></i> Profile Settings
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('settings.roles-permissions.show') }}">Settings</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">Roles & Permissions</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="row">
    <div class="col-md-12">
        <!-- Success/Error Messages -->
        @if(session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '{{ session('success') }}',
                confirmButtonText: 'OK'
            });
        </script>
        @endif
        @if(session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '{{ session('error') }}',
                confirmButtonText: 'OK'
            });
        </script>
        @endif

        <!-- Buttons to Add New Role and Permission (Super Admin or Owner) -->
        @can('admin-or-owner')
        <div class="mb-3">
            <button class="btn btn-primary" data-toggle="modal" data-target="#addRoleModal"><i class="fas fa-plus"></i> Add New Role</button>
            @can('Super Admin')
            <button class="btn btn-secondary" data-toggle="modal" data-target="#addPermissionModal"><i class="fas fa-plus"></i> Add New Permission</button>
            @endcan
        </div>
        @endcan



        <!-- Permissions Assignment Table -->
        <div class="card">
            <div class="card-header d-flex align-items-center" style="background: var(--sidebar-bg); color: white; border: none;">
                <div class="">
                    <h3 class="card-title mb-0 text-light"><i class="fas fa-user-lock mr-1"></i> Assign Permissions to Roles</h3>
                    <div class="text-muted small text-light">Permissions for <span class="badge badge-info">shopkeeper</span> apply only to shopkeepers in your current shop.</div>
                </div>
                <div class="card-tools ml-auto">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse"><i class="fas fa-minus"></i></button>
                </div>
            </div>
            <div class="card-body">
                @foreach($roles as $role)
                    @if($role->name !== 'Super Admin' && $role->name !== 'owner' || auth()->user()->hasRole('Super Admin'))
                    <h4 class="mb-3">
                        <span class="badge badge-secondary text-uppercase">{{ $role->name }}</span>
                        @if($role->name === 'shopkeeper')
                            <small class="text-info ml-2">(Shop-specific permissions)</small>
                        @endif
                    </h4>
                    <form method="POST" action="{{ route('settings.roles-permissions.assign') }}">
                        @csrf
                        <input type="hidden" name="role_id" value="{{ $role->id }}">
                        <div class="row">
                       @foreach($permissions as $permission)
                        <div class="col-md-4">
                            <div class="custom-control custom-switch mb-2">
                                <input class="custom-control-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm_{{ $role->id }}_{{ $permission->id }}"
                                    @if($role->name === 'shopkeeper')
                                        @if(($shopkeeperUsersCount ?? 0) > 0)
                                            @if(isset($shopkeeperPermissions) && $shopkeeperPermissions->contains('id', $permission->id))
                                                checked
                                            @endif
                                        @endif
                                    @else
                                        @if($role->hasPermissionTo($permission->name)) checked @endif
                                    @endif>
                                <label class="custom-control-label" for="perm_{{ $role->id }}_{{ $permission->id }}">{{ $permission->name }}</label>
                            </div>
                        </div>
                    @endforeach
                        </div>
                        <button type="submit" class="btn btn-sm btn-success mt-2"><i class="fas fa-save mr-1"></i> Update {{ $role->name }} Permissions</button>
                    </form>
                    <hr>
                    @endif
                @endforeach
            </div>
        </div>


        <!-- Roles List (Super Admin or Owner) -->
        @can('admin-or-owner')
        <div class="card mb-3 collapsed-card">
            <div class="card-header d-flex align-items-center" style="background: var(--sidebar-bg); color: white; border: none;">
                <h3 class="card-title mb-0 text-light" ><i class="fas fa-users mr-1 text-light"></i> Roles</h3>
                <div class="card-tools ml-auto">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Scope</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $role)
                            @if($role->name !== 'Super Admin' && $role->name !== 'owner' || auth()->user()->hasRole('Super Admin'))
                            <tr>
                                <td><span class="badge badge-light border">{{ $role->name }}</span></td>
                                <td>
                                    @if(is_null($role->shop_id))
                                        <span class="badge badge-success">Global</span>
                                    @else
                                        <span class="badge badge-info">Shop: {{ $shops[$role->shop_id] ?? 'Unknown' }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($role->name !== 'shopkeeper' || auth()->user()->hasRole('Super Admin'))
                                    <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editRoleModal{{ $role->id }}"><i class="fas fa-edit"></i> Edit</button>
                                    <form method="POST" action="{{ route('settings.roles-permissions.delete-role', $role) }}" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" onclick="confirmDelete(event, this.form, '{{ $role->name }}')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endcan

        <!-- Search Permissions -->
        <form method="GET" action="{{ route('settings.roles-permissions.show') }}" class="mb-3">
            <div class="input-group input-group-sm col-lg-6 col-md-8">
                <div class="input-group-prepend">
                    <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                </div>
                <input type="text" name="search" class="form-control" placeholder="Search permissions or groups..." value="{{ $search ?? '' }}">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">Search</button>
                    @if($search ?? '')
                        <a href="{{ route('settings.roles-permissions.show') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        <!-- Permissions List -->
        <div class="card mb-3  collapsed-card" >
            <div class="card-header d-flex align-items-center" style="background: var(--sidebar-bg); color: white; border: none; ">
                <h3 class="card-title mb-0 text-light"><i class="fas fa-key mr-1"></i> Permissions</h3>
                <div class="card-tools ml-auto">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            <div class="card-body">
                @foreach($groupedPermissions as $group => $perms)
                    <h5 class="mt-3"><span class="badge badge-dark"><i class="fas fa-list mr-1"></i> {{ ucfirst($group) }}</span></h5>
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Permission</th>
                                 @can('Super Admin')
                                <th>Actions</th>
                                 @endcan
                            </tr>
                        </thead>
                        <tbody>
                         @foreach($perms as $permission)
                            <tr>
                                <td>{{ $permission['name'] }}</td>
                                @can('Super Admin')
                                <td>
                                    <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editPermissionModal{{ $permission['id'] }}"><i class="fas fa-edit"></i> Edit</button>
                                    <form method="POST" action="{{ route('settings.roles-permissions.delete-permission', $permission['id']) }}" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" onclick="confirmDelete(event, this.form, '{{ $permission['name'] }}')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endforeach
               
            </div>
        </div>
    </div>
</div>

<!-- Add Role Modal (Super Admin or Owner) -->
@can('admin-or-owner')
<div class="modal fade" id="addRoleModal" tabindex="-1" role="dialog" aria-labelledby="addRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRoleModalLabel">Add New Role</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('settings.roles-permissions.create-role') }}">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="role_name">Role Name</label>
                        <input type="text" class="form-control" id="role_name" name="name" required>
                    </div>
                    @can('Super Admin')
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" name="is_global" value="1" id="is_global">
                            <label class="custom-control-label" for="is_global">
                                Global role (visible to all shops)
                            </label>
                        </div>
                    </div>
                    @endcan
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Role</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
<!-- Add Permission Modal (Super Admin only) -->
@can('Super Admin')
<div class="modal fade" id="addPermissionModal" tabindex="-1" role="dialog" aria-labelledby="addPermissionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPermissionModalLabel">Add New Permission</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('settings.roles-permissions.create-permission') }}">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="permission_name">Permission Name</label>
                        <input type="text" class="form-control" id="permission_name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Permission</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
<!-- Edit Role Modals (Super Admin or Owner) -->
@can('admin-or-owner')
@foreach($roles as $role)
@if($role->name !== 'Super Admin' && $role->name !== 'owner' || auth()->user()->hasRole('Super Admin'))
<div class="modal fade" id="editRoleModal{{ $role->id }}" tabindex="-1" role="dialog" aria-labelledby="editRoleModalLabel{{ $role->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRoleModalLabel{{ $role->id }}">Edit Role</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('settings.roles-permissions.edit-role', $role) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_role_name_{{ $role->id }}">Role Name</label>
                        <input type="text" class="form-control" id="edit_role_name_{{ $role->id }}" name="name" value="{{ $role->name }}" required>
                    </div>
                    @can('Super Admin')
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" name="is_global" value="1" id="edit_is_global_{{ $role->id }}" {{ is_null($role->shop_id) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="edit_is_global_{{ $role->id }}">
                                Global role (visible to all shops)
                            </label>
                        </div>
                    </div>
                    @endcan
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endforeach
@endcan

<!-- Edit Permission Modals (Super Admin only) -->
@can('Super Admin')
@foreach($permissions as $permission)
<div class="modal fade" id="editPermissionModal{{ $permission['id'] }}" tabindex="-1" role="dialog" aria-labelledby="editPermissionModalLabel{{ $permission['id'] }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPermissionModalLabel{{ $permission['id'] }}">Edit Permission</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('settings.roles-permissions.edit-permission', $permission['id']) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_permission_name_{{ $permission['id'] }}">Permission Name</label>
                        <input type="text" class="form-control" id="edit_permission_name_{{ $permission['id'] }}" name="name" value="{{ $permission['name'] }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Permission</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endcan

@endsection

@push('js')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@section('js')
<script>
    function confirmDelete(event, form, name) {
        event.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete "${name}". This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }
</script>
@endsection