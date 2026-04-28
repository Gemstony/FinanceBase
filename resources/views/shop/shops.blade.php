@extends('adminlte::page')

@section('title', 'Main Finance Branch')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body text-center justify-content-between ">
            <h1 class="d-none d-md-block text-light"><i class="fas fa-university"></i> Main Finance Branch (Branches)</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-university"></i> Main Finance Branch</h1>
            <a href="{{ url()->previous() }}" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
                <li class="breadcrumb-item active text-dark d-none d-md-inline" aria-current="page">Main Finance Branch (Branches)</li>
                <li class="breadcrumb-item active text-dark d-md-none" aria-current="page">Main Branch</li>
            </ol>
        </nav>
        @php
            $currentSubshops = $shop->subShops()->count();
            $maxSubshops = $shop->max_subshops;
            $canAddMore = $maxSubshops == 0 || $currentSubshops < $maxSubshops;
        @endphp

        @can('add_subshop')
        <button type="button" class="btn btn-primary btn-sm mt-2 mt-md-0" 
                data-toggle="modal" data-target="#addSubShopModal"
                {{ !$canAddMore ? 'disabled' : '' }}>
            <i class="fas fa-plus"></i> <span class="d-none d-md-inline">Add New Branch</span><span class="d-md-none">Add Branch</span>
            @if(!$canAddMore)
                <i class="fas fa-lock ml-1"></i>
            @endif
        </button>
        @endcan
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


    <!-- Error Message -->
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Main Branch Info Card -->
    <div class="card main-shop-card mb-4">
        <div class="card-header" style="background: var(--sidebar-bg)">
            <h3 class="card-title text-light"><i class="fas fa-university"></i> Main Branch Information</h3>
            <div class="card-tools">

                @can('edit_shop')
                <button type="button" class="btn btn-light btn-sm" onclick="editMainShop('{{ $shop->id ?? 0 }}', '{{ $shop->name ?? '' }}', '{{ $shop->short_name ?? '' }}', '{{ $shop->registration_number ?? '' }}', '{{ $shop->license_number ?? '' }}', '{{ $shop->tin ?? '' }}', '{{ $shop->website ?? '' }}', '{{ $shop->country ?? '' }}', '{{ $shop->region ?? '' }}', '{{ $shop->district ?? '' }}', '{{ $shop->street ?? '' }}', '{{ $shop->currency ?? '' }}', '{{ $shop->logo ?? '' }}', '{{ $shop->phone ?? '' }}', '{{ $shop->address ?? '' }}', '{{ $shop->email ?? '' }}', '{{ $shop->description ?? '' }}')" style="border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                    <i class="fas fa-edit"></i> Edit
                </button>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 text-center mb-4">
                    <img id="main_shop_logo_img_large" src="{{ $shop->logo ? asset('storage/' . $shop->logo) : '' }}" alt="Logo" style="{{ $shop->logo ? '' : 'display: none;' }} max-height: 100px; max-width: 180px; object-fit: contain; border: 1px solid #dee2e6; padding: 8px; background: #fff;">
                    <div id="main_shop_logo_placeholder_large" style="{{ $shop->logo ? 'display: none;' : '' }} color: #6c757d; font-weight: 500; padding: 20px; border: 1px dashed #dee2e6; background: #f8f9fa;">No logo uploaded</div>
                </div>
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <span id="main_shop_name">{{ $shop->name ?? 'N/A' }}</span></p>
                            <p><strong>Short Name:</strong> <span id="main_shop_short_name">{{ $shop->short_name ?? 'N/A' }}</span></p>
                            <p><strong>Registration No:</strong> <span id="main_shop_registration_number">{{ $shop->registration_number ?? 'N/A' }}</span></p>
                            <p><strong>License No:</strong> <span id="main_shop_license_number">{{ $shop->license_number ?? 'N/A' }}</span></p>
                            <p><strong>Phone:</strong> <span id="main_shop_phone">{{ $shop->phone ?? 'N/A' }}</span></p>
                            <p><strong>Email:</strong> <span id="main_shop_email">{{ $shop->email ?? 'N/A' }}</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>TIN:</strong> <span id="main_shop_tin">{{ $shop->tin ?? 'N/A' }}</span></p>
                            <p><strong>Website:</strong> <span id="main_shop_website">{{ $shop->website ?? 'N/A' }}</span></p>
                            <p><strong>Country:</strong> <span id="main_shop_country">{{ $shop->country ?? 'N/A' }}</span></p>
                            <p><strong>Region:</strong> <span id="main_shop_region">{{ $shop->region ?? 'N/A' }}</span></p>
                            <p><strong>District:</strong> <span id="main_shop_district">{{ $shop->district ?? 'N/A' }}</span></p>
                            <p><strong>Street:</strong> <span id="main_shop_street">{{ $shop->street ?? 'N/A' }}</span></p>
                            <p><strong>Currency:</strong> <span id="main_shop_currency">{{ $shop->currency ?? 'N/A' }}</span></p>
                            <p><strong>Status:</strong>
                                <span class="badge badge-{{ $shop->status === 'active' ? 'success' : ($shop->status === 'inactive' ? 'secondary' : ($shop->status === 'suspended' ? 'danger' : 'warning')) }}">
                                    {{ ucfirst($shop->status) }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong>Address:</strong> <span id="main_shop_address">{{ $shop->address ?? 'N/A' }}</span></p>
                            <p><strong>Description:</strong> <span id="main_shop_description">{{ $shop->description ?? 'No description available' }}</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @can('view_subshops')
        <div class="mb-3">
            <h4 class="h4"><i class="fas fa-code-branch"></i> Branches</h4>
        </div>
         <hr>

        <!-- Branches Usage Info -->
        @php
            $currentSubshops = $shop->subShops()->count();
            $maxSubshops = $shop->max_subshops;
            $usagePercentage = $maxSubshops > 0 ? min(($currentSubshops / $maxSubshops) * 100, 100) : 0;
            $canAddMore = $maxSubshops == 0 || $currentSubshops < $maxSubshops;
        @endphp
       
        <div class="row mb-4">
            <div class="col-12">
                <div class="info-box {{ $usagePercentage >= 90 ? 'bg-danger' : ($usagePercentage >= 70 ? 'bg-warning' : 'bg-info') }}">
                    <span class="info-box-icon"><i class="fas fa-chart-bar"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Branches Usage</span>
                        <span class="info-box-number">
                            {{ $currentSubshops }}/{{ $maxSubshops > 0 ? $maxSubshops : '∞' }}
                            @if($maxSubshops > 0)
                                <small>({{ number_format($usagePercentage, 1) }}% used)</small>
                            @endif
                        </span>
                        <div class="progress">
                            <div class="progress-bar {{ $usagePercentage >= 90 ? 'bg-danger' : ($usagePercentage >= 70 ? 'bg-warning' : 'bg-success') }}" role="progressbar" style="width: {{ $usagePercentage }}%" aria-valuenow="{{ $currentSubshops }}" aria-valuemin="0" aria-valuemax="{{ $maxSubshops }}"></div>
                        </div>
                        <div class="progress-description">
                            @if($maxSubshops == 0)
                                <i class="fas fa-infinity text-success"></i> Unlimited Branches
                            @elseif($canAddMore)
                                <i class="fas fa-check-circle text-success"></i> {{ $maxSubshops - $currentSubshops }} branches remaining
                            @else
                                <i class="fas fa-exclamation-triangle text-danger"></i> Branches limit reached - Contact admin to increase limit
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            </div>
        <!-- Sub Branches Grid -->
        <div class="row">
            @forelse($shop->subShops ?? [] as $subshop)
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card subshop-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background: var(--sidebar-bg);">
                            <h5 class="mb-0 text-light">
                                <i class="fas fa-building"></i> {{ $subshop->name ?? 'N/A' }}
                            </h5>
                            <span class="badge {{ ($subshop->is_active ?? false) ? 'badge-success' : 'badge-secondary' }}">
                                {{ ($subshop->is_active ?? false) ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="subshop-info">
                                <p class="info-item">
                                    <i class="fas fa-phone"></i>
                                    <strong>Phone:</strong> {{ $subshop->phone ?? 'N/A' }}
                                </p>
                                <p class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <strong>Address:</strong> {{ $subshop->address ?? 'N/A' }}
                                </p>
                                <p class="info-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <strong>Date:</strong> {{ $subshop->created_at ? $subshop->created_at->format('d/m/Y') : 'N/A' }}
                                </p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group d-flex" role="group">
                                @can('edit_subshop')
                                <button type="button" class="btn btn-outline-primary flex-fill" 
                                        onclick="editSubShop({{ $subshop->id ?? 0 }}, '{{ $subshop->name ?? '' }}', '{{ $subshop->phone ?? '' }}', '{{ $subshop->address ?? '' }}', {{ ($subshop->is_active ?? false) ? 'true' : 'false' }})">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                @endcan
                                @can('delete_subshop')
                                <form id="deleteSubShopForm{{ $subshop->id }}" action="{{ route('subshop.destroy', $subshop) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-outline-danger flex-fill delete-subshop-btn" data-subshop-id="{{ $subshop->id }}" data-subshop-name="{{ $subshop->name }}">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card empty-state">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-building fa-4x mb-3 text-muted"></i>
                            <h4>No Branches</h4>
                            <p class="text-muted">Click the "Add New Branch" button to get started</p>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

    @endcan

    <!-- Add Branches Modal -->
    <div class="modal fade" id="addSubShopModal" tabindex="-1" role="dialog" aria-labelledby="addSubShopModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form action="{{ route('subshop.store') }}" method="POST" id="addSubShopForm">
                    @csrf
                    <div class="modal-header" style="background: var(--sidebar-bg);">
                        <h5 class="modal-title text-light" id="addSubShopModalLabel">
                            <i class="fas fa-plus-circle"></i> Add New Branch
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="subshop_name">
                                Branch Name <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-store"></i></span>
                                </div>
                                <input type="text" class="form-control" id="subshop_name" name="name" 
                                       placeholder="e.g: Downtown Branch" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="subshop_phone">
                                Phone Number <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                </div>
                                <input type="tel" class="form-control" id="subshop_phone" name="phone" 
                                       placeholder="e.g: 0712345678" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="subshop_address">
                                Address <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                </div>
                                <input type="text" class="form-control" id="subshop_address" name="address" 
                                       placeholder="e.g: Downtown, City" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="subshop_is_active" 
                                       name="is_active" value="1" checked>
                                <label class="custom-control-label" for="subshop_is_active">
                                    Branch is Active?
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Close
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Branch Modal -->
    <div class="modal fade" id="editSubShopModal" tabindex="-1" role="dialog" aria-labelledby="editSubShopModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form action="" method="POST" id="editSubShopForm">
                    @csrf
                    @method('PUT')
                    <div class="modal-header" style="background: var(--sidebar-bg);">
                        <h5 class="modal-title text-light" id="editSubShopModalLabel">
                            <i class="fas fa-edit"></i> Edit Branch
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_subshop_id" name="id">
                        
                        <div class="form-group">
                            <label for="edit_subshop_name">
                                Branch Name <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-store"></i></span>
                                </div>
                                <input type="text" class="form-control" id="edit_subshop_name" name="name" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_subshop_phone">
                                Phone Number <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                </div>
                                <input type="tel" class="form-control" id="edit_subshop_phone" name="phone" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_subshop_address">
                                Address <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                </div>
                                <input type="text" class="form-control" id="edit_subshop_address" name="address" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="edit_subshop_is_active" 
                                       name="is_active" value="1">
                                <label class="custom-control-label" for="edit_subshop_is_active">
                                    Branch is Active?
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Close
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Main Branch Modal -->
    <div class="modal fade" id="editMainShopModal" tabindex="-1" role="dialog" aria-labelledby="editMainShopModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form id="editMainShopForm" enctype="multipart/form-data">
                    <div class="modal-header" style="background: var(--sidebar-bg);">
                        <h5 class="modal-title text-light" id="editMainShopModalLabel">
                            <i class="fas fa-edit"></i> Edit Main Branch Information
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_shop_name">
                                        Branch Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-store"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_shop_name" name="shop_name"
                                               placeholder="e.g: My Awesome Branch" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_short_name">
                                        Short Name
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_short_name" name="short_name"
                                               placeholder="e.g: FIN" >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_shop_phone">
                                        Phone Number <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        </div>
                                        <input type="tel" class="form-control" id="edit_shop_phone" name="shop_phone"
                                               placeholder="e.g: 0712345678" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_website">
                                        Website
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_website" name="website" >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_registration_number">
                                        Registration Number
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_registration_number" name="registration_number"
                                               placeholder="Auto-generated if empty" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_license_number">
                                        License Number
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-certificate"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_license_number" name="license_number" >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_tin">
                                        TIN
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-receipt"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_tin" name="tin" >
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_currency">
                                        Currency
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-money-bill"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_currency" name="currency" >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_country">
                                        Country
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-flag"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_country" name="country" >
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_region">
                                        Region
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-map"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_region" name="region" >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_district">
                                        District
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-map-marked-alt"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_district" name="district" >
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_street">
                                        Street
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-road"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_street" name="street" >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_shop_address">
                                        Address <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="edit_shop_address" name="shop_address"
                                            placeholder="e.g: Downtown, City Name" required>
                                    </div>
                                </div>
                            </div>

                             <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_shop_email">
                                        Email <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        </div>
                                        <input type="email" class="form-control" id="edit_shop_email" name="email"
                                            placeholder="e.g: fintech@example.com" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                  
                        <div class="form-group">
                            <label for="edit_shop_description">
                                Description
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                </div>
                                <textarea class="form-control" id="edit_shop_description" name="shop_description"
                                          placeholder="Brief description of your shop..." rows="3"></textarea>
                            </div>
                            <small class="form-text text-muted">Optional: Add a description for your Branch</small>
                        </div>

                        <div class="form-group">
                            <label for="edit_logo">
                                Logo
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-image"></i></span>
                                </div>
                                <input type="file" class="form-control" id="edit_logo" name="logo" accept="image/*" >
                            </div>
                            <div class="mt-2">
                                <img id="edit_logo_preview" src="" alt="Logo Preview" style="display: none; max-height: 60px; border-radius: 8px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update Branch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@stop
    @push('css')
        <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    @endpush
@push('css')
<style>
    /* ====================================
       CSS ROOT VARIABLES - MICROFINANCE SYSTEM COLORS
       ==================================== */
    :root {
        --primary-color: #2c3e50;
        --secondary-color: #34495e;
        --gradient: linear-gradient(135deg, #2c3e50, #34495e);
        --accent-color: #3498db;
        --success-color: #27ae60;
        --danger-color: #e74c3c;
        --warning-color: #f39c12;
        --info-color: #2980b9;
        --light-color: #ecf0f1;
        --dark-color: #2c3e50;
        --white: #ffffff;
        --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.12);
        --shadow-medium: 0 2px 6px rgba(0, 0, 0, 0.15);
        --shadow-heavy: 0 4px 12px rgba(0, 0, 0, 0.18);
        --border-radius: 4px;
        --transition: all 0.2s ease;
    }

    /* Main Branch Card - Professional Corporate Design */
    .main-shop-card {
        border: 1px solid #dee2e6;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-light);
        overflow: hidden;
        background: var(--white);
    }

    .main-shop-card .card-header {
        background: var(--gradient);
        color: var(--white);
        font-weight: 600;
        padding: 1rem 1.5rem;
        border: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .main-shop-card .card-header .card-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
    }

    .main-shop-card .card-header .card-tools {
        display: flex;
        gap: 0.5rem;
    }

    .main-shop-card .card-body {
        padding: 1.5rem;
    }

    .main-shop-card .card-body p {
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
        color: var(--dark-color);
        line-height: 1.5;
    }

    .main-shop-card .card-body p strong {
        color: var(--secondary-color);
        font-weight: 600;
        display: inline-block;
        min-width: 120px;
    }

    /* SubShop Cards - Professional Corporate Design */
    .subshop-card {
        border: 1px solid #dee2e6;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-light);
        transition: var(--transition);
        background: var(--white);
        overflow: hidden;
        height: 100%;
    }

    .subshop-card:hover {
        box-shadow: var(--shadow-medium);
    }

    .subshop-card .card-header {
        background: var(--gradient);
        color: var(--white);
        padding: 1rem 1.25rem;
        border: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .subshop-card .card-header h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .subshop-card .card-header h5 i {
        margin-right: 0.5rem;
    }

    .subshop-card .card-header .badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.65rem;
        border-radius: 3px;
        font-weight: 500;
    }

    .subshop-card .card-body {
        padding: 1.25rem;
        flex-grow: 1;
    }

    .subshop-info {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .subshop-info .info-item {
        display: flex;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #ecf0f1;
    }

    .subshop-info .info-item:last-child {
        border-bottom: none;
    }

    .subshop-info .info-item i {
        width: 20px;
        margin-right: 0.75rem;
        color: var(--secondary-color);
        font-size: 0.9rem;
    }

    .subshop-info .info-item strong {
        font-weight: 600;
        color: var(--dark-color);
        min-width: 70px;
    }

    .subshop-card .card-footer {
        background: #f8f9fa;
        border-top: 1px solid #dee2e6;
        padding: 0.75rem 1.25rem;
    }

    .subshop-card .card-footer .btn-group {
        width: 100%;
        gap: 0.5rem;
    }

    .subshop-card .card-footer .btn {
        flex: 1;
        border-radius: var(--border-radius);
        font-weight: 500;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        transition: var(--transition);
    }

    .subshop-card .card-footer .btn i {
        margin-right: 0.25rem;
    }

    /* Edit Button - Primary */
    .subshop-card .card-footer .btn-outline-primary {
        border-color: var(--accent-color);
        color: var(--accent-color);
    }

    .subshop-card .card-footer .btn-outline-primary:hover {
        background: var(--accent-color);
        border-color: var(--accent-color);
        color: white;
    }

    /* Delete Button - Danger */
    .subshop-card .card-footer .btn-outline-danger {
        border-color: var(--danger-color);
        color: var(--danger-color);
    }

    .subshop-card .card-footer .btn-outline-danger:hover {
        background: var(--danger-color);
        border-color: var(--danger-color);
        color: white;
    }

    /* Empty State - Professional Design */
    .empty-state {
        border: 1px dashed #dee2e6;
        background: #f8f9fa;
        border-radius: var(--border-radius);
        text-align: center;
        padding: 2.5rem 2rem;
    }

    .empty-state i {
        font-size: 3rem;
        color: #bdc3c7;
        margin-bottom: 1rem;
        display: block;
    }

    .empty-state h4 {
        color: var(--dark-color);
        font-weight: 600;
        margin-bottom: 0.75rem;
    }

    .empty-state p {
        color: #7f8c8d;
        font-size: 0.95rem;
        margin: 0;
    }

    /* Badges */
    .badge-success {
        background: var(--success-color);
        color: white;
    }

    .badge-secondary {
        background: #95a5a6;
        color: white;
    }

    .badge-danger {
        background: var(--danger-color);
        color: white;
    }

    .badge-warning {
        background: var(--warning-color);
        color: white;
    }

    /* Modal Enhancements */
    .modal-content {
        border: 1px solid #dee2e6;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-heavy);
    }

    .modal-header {
        background: var(--gradient);
        color: var(--white);
        border: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        padding: 1rem 1.5rem;
    }

    .modal-header .close {
        color: rgba(255, 255, 255, 0.8);
        opacity: 1;
        font-size: 1.25rem;
    }

    .modal-header .close:hover {
        color: white;
        opacity: 1;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .main-shop-card .card-body {
            padding: 1rem;
        }

        .subshop-card .card-header {
            padding: 0.75rem 1rem;
        }

        .subshop-card .card-body {
            padding: 1rem;
        }

        .subshop-card .card-footer {
            padding: 0.75rem 1rem;
        }

        .subshop-info .info-item {
            padding: 0.5rem 0;
        }

        .empty-state {
            padding: 2rem 1.5rem;
        }

        .empty-state i {
            font-size: 2.5rem;
        }
    }

    /* Button Styles */
    .btn-primary {
        background: var(--accent-color);
        border: none;
        border-radius: var(--border-radius);
        padding: 0.5rem 1.5rem;
        font-weight: 500;
        transition: var(--transition);
    }

    .btn-primary:hover {
        background: #2980b9;
        box-shadow: var(--shadow-medium);
    }

    /* Alert Enhancements */
    .alert {
        border: 1px solid transparent;
        border-radius: var(--border-radius);
        border-left: 4px solid;
    }

    .alert-success {
        border-left-color: var(--success-color);
    }

    .alert-danger {
        border-left-color: var(--danger-color);
    }

    .alert-warning {
        border-left-color: var(--warning-color);
    }

    .alert-info {
        border-left-color: var(--info-color);
    }
</style>
@endpush

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Edit Main Shop Function
    function editMainShop(id, name, shortName, registrationNumber, licenseNumber, tin, website, country, region, district, street, currency, logo, phone, address, email, description) {
        $('#edit_shop_name').val(name);
        $('#edit_short_name').val(shortName);
        $('#edit_registration_number').val(registrationNumber);
        $('#edit_license_number').val(licenseNumber);
        $('#edit_tin').val(tin);
        $('#edit_website').val(website);
        $('#edit_country').val(country);
        $('#edit_region').val(region);
        $('#edit_district').val(district);
        $('#edit_street').val(street);
        $('#edit_currency').val(currency);
        $('#edit_logo').val('');
        if (logo) {
            $('#edit_logo_preview').attr('src', `{{ asset('storage') }}/${logo}`).show();
        } else {
            $('#edit_logo_preview').hide();
        }
        $('#edit_shop_phone').val(phone);
        $('#edit_shop_address').val(address);
        $('#edit_shop_email').val(email);

        $('#edit_shop_description').val(description);
        
        // Show modal
        $('#editMainShopModal').modal('show');
    }

    // Edit Branch Function
    function editSubShop(id, name, phone, address, isActive) {
        $('#edit_subshop_id').val(id);
        $('#edit_subshop_name').val(name);
        $('#edit_subshop_phone').val(phone);
        $('#edit_subshop_address').val(address);
        $('#edit_subshop_is_active').prop('checked', isActive);
        
        // Set form action URL
        $('#editSubShopForm').attr('action', `/subshop/${id}`);
        
        // Show modal
        $('#editSubShopModal').modal('show');
    }

    // Confirm Delete Function (old - keeping for compatibility but updating to use SweetAlert2)
    function confirmDelete(id, name) {
        // This function is now replaced by the SweetAlert2 handler below
        // Keeping for backward compatibility
        console.warn('confirmDelete function is deprecated. Using SweetAlert2 instead.');
    }

    // Auto-hide alerts after 5 seconds
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);

        $('#edit_logo').on('change', function(e) {
            const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
            if (!file) {
                $('#edit_logo_preview').hide();
                return;
            }

            const url = URL.createObjectURL(file);
            $('#edit_logo_preview').attr('src', url).show();
        });
        
        // Reset form when modal is closed
        $('#addSubShopModal').on('hidden.bs.modal', function () {
            $('#addSubShopForm')[0].reset();
        });
        
        $('#editSubShopModal').on('hidden.bs.modal', function () {
            $('#editSubShopForm')[0].reset();
        });

        $('#editMainShopModal').on('hidden.bs.modal', function () {
            $('#editMainShopForm')[0].reset();
            $('#edit_logo_preview').hide();
        });
        
        // Add click event listener to the Add New Branch button
        $('button[data-target="#addSubShopModal"]').on('click', function(e) {
            console.log('Add New Shop button clicked');
            console.log('Button disabled?', $(this).prop('disabled'));
            
            if ($(this).prop('disabled')) {
                console.log('Button is disabled, not opening modal');
                return;
            }
            
            // Manually show the modal
            $('#addSubShopModal').modal('show');
            console.log('Modal show command executed');
        });
    });

    // Handle Main Branch Edit Form Submission
    $('#editMainShopForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('_method', 'PUT'); // Add method spoofing for PUT
        formData.append('_token', '{{ csrf_token() }}'); // Add CSRF token
        
        $.ajax({
            url: '{{ route("shop.update") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            beforeSend: function() {
                // Disable submit button and show loading
                $('#editMainShopForm button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');
            },
            success: function(response) {
                if (response.success) {
                    // Update the displayed Branch information
                    $('#main_shop_name').text(response.shop.name || 'N/A');
                    $('#main_shop_short_name').text(response.shop.short_name || 'N/A');
                    $('#main_shop_registration_number').text(response.shop.registration_number || 'N/A');
                    $('#main_shop_license_number').text(response.shop.license_number || 'N/A');
                    $('#main_shop_tin').text(response.shop.tin || 'N/A');
                    $('#main_shop_website').text(response.shop.website || 'N/A');
                    $('#main_shop_country').text(response.shop.country || 'N/A');
                    $('#main_shop_region').text(response.shop.region || 'N/A');
                    $('#main_shop_district').text(response.shop.district || 'N/A');
                    $('#main_shop_street').text(response.shop.street || 'N/A');
                    $('#main_shop_currency').text(response.shop.currency || 'N/A');
                    $('#main_shop_logo').text(response.shop.logo || '');
                    if (response.shop.logo_url) {
                        $('#main_shop_logo_img').attr('src', response.shop.logo_url).show();
                        $('#main_shop_logo_img_large').attr('src', response.shop.logo_url).show();
                        $('#main_shop_logo_placeholder').hide();
                        $('#main_shop_logo_placeholder_large').hide();
                    } else {
                        $('#main_shop_logo_img').hide();
                        $('#main_shop_logo_img_large').hide();
                        $('#main_shop_logo_placeholder').show();
                        $('#main_shop_logo_placeholder_large').show();
                    }
                    $('#main_shop_phone').text(response.shop.phone || 'N/A');
                    $('#main_shop_email').text(response.shop.email || 'N/A');
                    $('#main_shop_address').text(response.shop.address || 'N/A');
                    $('#main_shop_description').text(response.shop.description || 'No description available');
                    
                    // Hide modal
                    $('#editMainShopModal').modal('hide');
                    
                    // Show success message using SweetAlert2 if available, otherwise use alert
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        alert(response.message);
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message
                        });
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            },
            error: function(xhr) {
                let message = 'An error occurred. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: message
                    });
                } else {
                    alert('Error: ' + message);
                }
            },
            complete: function() {
                // Re-enable submit button
                $('#editMainShopForm button[type="submit"]').prop('disabled', false).html('<i class="fas fa-save"></i> Update Shop');
            }
        });
    });

    // Delete Branches with SweetAlert
    $(document).on('click', '.delete-subshop-btn', function() {
        const subshopId = $(this).data('subshop-id');
        const subshopName = $(this).data('subshop-name');

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete the Branch "${subshopName}". This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $(`#deleteSubShopForm${subshopId}`).submit();
            }
        });
    });
</script>
@stop