@extends('adminlte::page')

@section('title', 'Owners Management')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body text-center">
            <h1 class="d-none d-md-block text-light"><i class="fas fa-users text-warning"></i> <strong>DB</strong> Owners Management Panel</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-users text-warning"></i> <strong>DB</strong> Owners</h1>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active text-dark d-none d-md-inline" aria-current="page">Owners Management Panel</li>
                <li class="breadcrumb-item active text-dark d-md-none" aria-current="page">Owners</li>
            </ol>
        </nav>

    </div>
@stop
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


@section('content')
    <!-- Success Message -->

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



    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                <button type="button" class="btn btn-success btn-lg btn-sm m-2" data-toggle="modal" data-target="#createOwnerModal">
                    <i class="fas fa-plus-circle"></i> Create Owner
                </button>
            </div>
        </div>
    </div>

    <!-- Create Owner Modal -->
    <div class="modal fade" id="createOwnerModal" tabindex="-1" role="dialog" aria-labelledby="createOwnerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createOwnerModalLabel"><i class="fas fa-plus-circle"></i> Create New Owner</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="createOwnerForm" action="{{ route('owners.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="owner_name">Owner Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="owner_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="owner_email">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="owner_email" name="email" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="owner_phone">Phone Number</label>
                                    <input type="text" class="form-control" id="owner_phone" name="phone_number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="owner_password">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="owner_password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="owner_password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="owner_password_confirmation" name="password_confirmation" required>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-muted mb-3"><i class="fas fa-store"></i> Shop Information</h6>
                        <div class="form-group">
                            <label for="shop_name">Shop Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="shop_name" name="shop_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="shop_phone">Shop Phone</label>
                                    <input type="text" class="form-control" id="shop_phone" name="shop_phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="shop_address">Shop Address</label>
                                    <textarea class="form-control" id="shop_address" name="shop_address" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Owner</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Owner Modal -->
    <div class="modal fade" id="editOwnerModal" tabindex="-1" role="dialog" aria-labelledby="editOwnerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editOwnerModalLabel"><i class="fas fa-edit"></i> Edit Owner</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editOwnerForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_owner_name">Owner Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_owner_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_owner_email">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="edit_owner_email" name="email" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_owner_phone">Phone Number</label>
                                    <input type="text" class="form-control" id="edit_owner_phone" name="phone_number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_owner_role">Role <span class="text-danger">*</span></label>
                                    <select class="form-control" id="edit_owner_role" name="role" required>
                                        <option value="owner">Owner</option>
                                        <option value="Super Admin">Super Admin</option>
                                        <!-- Add more roles as needed -->
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_shop_status">Shop Status <span class="text-danger">*</span></label>
                                    <select class="form-control" id="edit_shop_status" name="shop_status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="trial">Trial</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-muted mb-3"><i class="fas fa-store"></i> Shop Information</h6>
                        <div class="form-group">
                            <label for="edit_shop_name">Shop Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_shop_name" name="shop_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_shop_phone">Shop Phone</label>
                                    <input type="text" class="form-control" id="edit_shop_phone" name="shop_phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_shop_address">Shop Address</label>
                                    <textarea class="form-control" id="edit_shop_address" name="shop_address" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Owner</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel"><i class="fas fa-key"></i> Reset Password for <span id="resetOwnerName"></span></h5>
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

    <!-- Subshops Modal -->
    <div class="modal fade" id="subshopsModal" tabindex="-1" role="dialog" aria-labelledby="subshopsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subshopsModalLabel"><i class="fas fa-store-alt"></i> Subshops for <span id="subshopsOwnerName"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="subshopsContent">
                        <!-- Subshops will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Shopkeepers Modal -->
    <div class="modal fade" id="shopkeepersModal" tabindex="-1" role="dialog" aria-labelledby="shopkeepersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shopkeepersModalLabel"><i class="fas fa-users"></i> Shopkeepers for <span id="shopkeepersOwnerName"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="shopkeepersContent">
                        <!-- Shopkeepers will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Owners Management Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <h5 class="mb-0"><i class="fas fa-users"></i> Owners Management</h5>
                </div>
                <div class="card-body">
                    @if($owners->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="ownersTable">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Owner</th>
                                        <th>Role</th>
                                        <th>Shop</th>
                                        <th>Subshops</th>
                                        <th>Shopkeepers</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($owners as $owner)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-primary text-white mr-2" style="width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                                        {{ strtoupper(substr($owner->name, 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <strong>{{ $owner->name }}</strong>
                                                        <br><small class="text-muted">{{ $owner->email }}</small>
                                                        @if($owner->phone_number)
                                                            <br><small class="text-muted"><i class="fas fa-phone"></i> {{ $owner->phone_number }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if($owner->roles && $owner->roles->count() > 0)
                                                    @php
                                                        $role = $owner->roles->first();
                                                        $roleClass = match($role->name) {
                                                            'Super Admin' => 'badge-danger',
                                                            'owner' => 'badge-primary',
                                                            default => 'badge-secondary'
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $roleClass }}">{{ $role->name }}</span>
                                                @else
                                                    <span class="badge badge-secondary">No Role</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($owner->shop)
                                                    <strong>{{ $owner->shop->name }}</strong>
                                                    @if($owner->shop->phone)
                                                        <br><small class="text-muted"><i class="fas fa-phone"></i> {{ $owner->shop->phone }}</small>
                                                    @endif
                                                    @if($owner->shop->address)
                                                        <br><small class="text-muted"><i class="fas fa-map-marker-alt"></i> {{ Str::limit($owner->shop->address, 30) }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">No shop</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($owner->shop && $owner->shop->subShops)
                                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showSubshopsModal({{ $owner->id }}, '{{ $owner->name }}')">
                                                        <i class="fas fa-store-alt"></i> {{ $owner->shop->subShops->count() }}
                                                    </button>
                                                @else
                                                    <span class="badge badge-secondary">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($owner->shop && $owner->shop->subShops)
                                                    @php
                                                        $shopkeepers = collect();
                                                        foreach ($owner->shop->subShops as $subShop) {
                                                            $shopkeepers = $shopkeepers->merge($subShop->users);
                                                        }
                                                        $uniqueShopkeepers = $shopkeepers->unique('id');
                                                    @endphp
                                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="showShopkeepersModal({{ $owner->id }}, '{{ $owner->name }}')">
                                                        <i class="fas fa-users"></i> {{ $uniqueShopkeepers->count() }}
                                                    </button>
                                                @else
                                                    <span class="badge badge-secondary">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($owner->shop)
                                                    <span class="badge badge-{{ $owner->shop->is_active ? 'success' : 'secondary' }}">
                                                        {{ $owner->shop->is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                    <br><small class="text-muted">{{ ucfirst($owner->shop->status ?? 'N/A') }}</small>
                                                @else
                                                    <span class="badge badge-warning">No Shop</span>
                                                @endif
                                            </td>
                                            <td>{{ $owner->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editOwner({{ $owner->id }})">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="resetOwnerPassword({{ $owner->id }}, '{{ $owner->name }}')">
                                                    <i class="fas fa-key"></i> Reset Password
                                                </button>
                                                <form id="deleteOwnerForm{{ $owner->id }}" action="{{ route('owners.destroy', $owner) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-owner-btn" data-owner-id="{{ $owner->id }}" data-owner-name="{{ $owner->name }}">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No owners registered yet</h5>
                            <p class="text-muted">Create your first owner using the button above.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination for Owners -->
    @if($owners->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $owners->links() }}
    </div>
    @endif

@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush


@section('js')

<script>
     $(function () {
    // Initialize DataTable
        $('#ownersTable').DataTable();
    })
    // Auto-hide alerts after 5 seconds
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });

    // Owners data for editing
    const ownersData = @json($owners->getCollection()->toArray());

    // Edit Owner function
    function editOwner(ownerId) {
        const owner = ownersData.find(o => o.id == ownerId);
        if (!owner) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Owner not found!'
            });
            return;
        }

        // Populate form fields
        $('#edit_owner_name').val(owner.name);
        $('#edit_owner_email').val(owner.email);
        $('#edit_owner_phone').val(owner.phone_number || '');

        // Set role
        $('#edit_owner_role').val(owner.roles && owner.roles.length > 0 ? owner.roles[0].name : 'owner');

        if (owner.shop) {
            $('#edit_shop_name').val(owner.shop.name);
            $('#edit_shop_phone').val(owner.shop.phone || '');
            $('#edit_shop_address').val(owner.shop.address || '');
            $('#edit_shop_status').val(owner.shop.status || 'trial');
        }

        // Update form action
        $('#editOwnerForm').attr('action', `/owners/${ownerId}`);

        // Show modal
        $('#editOwnerModal').modal('show');
    }

    // Reset Owner Password function
    function resetOwnerPassword(ownerId, ownerName) {
        $('#resetOwnerName').text(ownerName);
        $('#resetPasswordForm').attr('action', `/owners/${ownerId}/reset-password`);
        $('#resetPasswordForm')[0].reset(); // Reset form fields
        $('#resetPasswordModal').modal('show');
    }

    // Show Subshops Modal function
    function showSubshopsModal(ownerId, ownerName) {
        const owner = ownersData.find(o => o.id == ownerId);
        if (!owner || !owner.shop || !owner.shop.sub_shops) {
            Swal.fire({
                icon: 'info',
                title: 'No Subshops',
                text: 'This owner has no subshops.'
            });
            return;
        }

        $('#subshopsOwnerName').text(ownerName);
        let content = '';

        if (owner.shop.sub_shops.length > 0) {
            content = '<div class="table-responsive"><table class="table table-striped table-sm"><thead class="thead-dark"><tr><th>Name</th><th>Phone</th><th>Address</th><th>Status</th><th>Created</th></tr></thead><tbody>';

            owner.shop.sub_shops.forEach(subshop => {
                content += `
                    <tr>
                        <td><strong>${subshop.name}</strong></td>
                        <td>${subshop.phone || 'N/A'}</td>
                        <td>${subshop.address ? subshop.address.substring(0, 50) + (subshop.address.length > 50 ? '...' : '') : 'N/A'}</td>
                        <td><span class="badge badge-${subshop.is_active ? 'success' : 'secondary'}">${subshop.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td>${new Date(subshop.created_at).toLocaleDateString()}</td>
                    </tr>
                `;
            });

            content += '</tbody></table></div>';
        } else {
            content = '<div class="text-center py-4"><i class="fas fa-store-alt fa-3x text-muted mb-3"></i><h5>No subshops found</h5></div>';
        }

        $('#subshopsContent').html(content);
        $('#subshopsModal').modal('show');
    }

    // Show Shopkeepers Modal function
    function showShopkeepersModal(ownerId, ownerName) {
        const owner = ownersData.find(o => o.id == ownerId);
        if (!owner || !owner.shop || !owner.shop.sub_shops) {
            Swal.fire({
                icon: 'info',
                title: 'No Shopkeepers',
                text: 'This owner has no shopkeepers.'
            });
            return;
        }

        $('#shopkeepersOwnerName').text(ownerName);
        let shopkeepers = [];
        let content = '';

        // Collect all unique shopkeepers from subshops
        owner.shop.sub_shops.forEach(subshop => {
            if (subshop.users && subshop.users.length > 0) {
                subshop.users.forEach(user => {
                    if (!shopkeepers.find(s => s.id === user.id)) {
                        shopkeepers.push(user);
                    }
                });
            }
        });

        if (shopkeepers.length > 0) {
            content = '<div class="table-responsive"><table class="table table-striped table-sm"><thead class="thead-dark"><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead><tbody>';

            shopkeepers.forEach(shopkeeper => {
                const pivot = shopkeeper.pivot || {};
                content += `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-primary text-white mr-2" style="width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px;">
                                    ${shopkeeper.name.charAt(0).toUpperCase()}
                                </div>
                                <strong>${shopkeeper.name}</strong>
                            </div>
                        </td>
                        <td>${shopkeeper.email}</td>
                        <td>${shopkeeper.phone_number || 'N/A'}</td>
                        <td><span class="badge badge-info">${pivot.role || 'Staff'}</span></td>
                        <td><span class="badge badge-${pivot.is_active ? 'success' : 'secondary'}">${pivot.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td>${new Date(shopkeeper.created_at).toLocaleDateString()}</td>
                    </tr>
                `;
            });

            content += '</tbody></table></div>';
        } else {
            content = '<div class="text-center py-4"><i class="fas fa-users fa-3x text-muted mb-3"></i><h5>No shopkeepers found</h5></div>';
        }

        $('#shopkeepersContent').html(content);
        $('#shopkeepersModal').modal('show');
    }

    // Create Owner Form Submission - Now uses regular form submission since controller returns redirect
    // $('#createOwnerForm').on('submit', function(e) {
    //     // Form will submit normally to controller which returns redirect
    // });

    // Edit Owner Form Submission - Now uses regular form submission since controller returns redirect
    // $('#editOwnerForm').on('submit', function(e) {
    //     // Form will submit normally to controller which returns redirect
    // });

    // Delete Owner with SweetAlert
    $(document).on('click', '.delete-owner-btn', function() {
        const ownerId = $(this).data('owner-id');
        const ownerName = $(this).data('owner-name');

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete the owner "${ownerName}" and their entire shop. This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $(`#deleteOwnerForm${ownerId}`).submit();
            }
        });
    });
</script>
@stop