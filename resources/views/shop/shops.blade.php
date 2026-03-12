@extends('adminlte::page')

@section('title', 'Main Finance Branch')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body text-center">
            <h1 class="d-none d-md-block text-light"><i class="fas fa-building"></i> Main Finance Branch(Branches)</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-building"></i> Main Finance Branch</h1>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
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
    <div class="card main-shop-card mb-4" style="border: 4px solid #FFD700; box-shadow: 0 15px 35px rgba(0,0,0,0.4), 0 0 20px rgba(255,215,0,0.3); position: relative;">
        <div class="card-header" style="background: linear-gradient(135deg, #FFD700, #FFA500, #FF6347); color: white; position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 2rem; color: #FFD700; opacity: 0.8;">
                <i class="fas fa-crown"></i>
            </div>
            <h3 class="card-title text-center" style="text-shadow: 3px 3px 6px rgba(0,0,0,0.7); font-weight: bold; font-size: 1.5rem; margin-top: 20px;"><i class="fas fa-crown"></i> Main Branch Headquarters</h3>
            <div style="position: absolute; bottom: 10px; right: 15px;">

                @can('edit_shop')
                <button type="button" class="btn btn-light btn-sm" onclick="editMainShop('{{ $shop->id ?? 0 }}', '{{ $shop->name ?? '' }}', '{{ $shop->short_name ?? '' }}', '{{ $shop->registration_number ?? '' }}', '{{ $shop->license_number ?? '' }}', '{{ $shop->tin ?? '' }}', '{{ $shop->website ?? '' }}', '{{ $shop->country ?? '' }}', '{{ $shop->region ?? '' }}', '{{ $shop->district ?? '' }}', '{{ $shop->street ?? '' }}', '{{ $shop->currency ?? '' }}', '{{ $shop->logo ?? '' }}', '{{ $shop->phone ?? '' }}', '{{ $shop->address ?? '' }}', '{{ $shop->email ?? '' }}', '{{ $shop->description ?? '' }}')" style="border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                    <i class="fas fa-edit"></i> Edit
                </button>
                @endcan
            </div>
            <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, #FFD700, transparent);"></div>
        </div>
        <div class="card-body" style="background: linear-gradient(135deg, #FFF8DC, #FAFAD2); padding: 2.5rem;">
            <div class="text-center mb-4">
                <img id="main_shop_logo_img_large" src="{{ $shop->logo ? asset('storage/' . $shop->logo) : '' }}" alt="Logo" style="{{ $shop->logo ? '' : 'display: none;' }} max-height: 120px; max-width: 220px; object-fit: contain; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.15); background: rgba(255,255,255,0.6); padding: 10px;">
                <div id="main_shop_logo_placeholder_large" style="{{ $shop->logo ? 'display: none;' : '' }} color: #8B4513; font-weight: 600;">No logo uploaded</div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Name:</strong> <span id="main_shop_name">{{ $shop->name ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Short Name:</strong> <span id="main_shop_short_name">{{ $shop->short_name ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Reg No:</strong> <span id="main_shop_registration_number">{{ $shop->registration_number ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">License No:</strong> <span id="main_shop_license_number">{{ $shop->license_number ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Phone:</strong> <span id="main_shop_phone">{{ $shop->phone ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Email:</strong> <span id="main_shop_email">{{ $shop->email ?? 'N/A' }}</span></p>
                </div>
                <div class="col-md-6">
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">TIN:</strong> <span id="main_shop_tin">{{ $shop->tin ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Website:</strong> <span id="main_shop_website">{{ $shop->website ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Country:</strong> <span id="main_shop_country">{{ $shop->country ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Region:</strong> <span id="main_shop_region">{{ $shop->region ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">District:</strong> <span id="main_shop_district">{{ $shop->district ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Street:</strong> <span id="main_shop_street">{{ $shop->street ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Currency:</strong> <span id="main_shop_currency">{{ $shop->currency ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;">
                        <strong style="color: #8B4513;">Logo:</strong>
                        <span id="main_shop_logo" style="display: none;">{{ $shop->logo ?? '' }}</span>
                        <span id="main_shop_logo_placeholder" style="{{ $shop->logo ? 'display: none;' : '' }}">N/A</span>
                        <img id="main_shop_logo_img" src="{{ $shop->logo ? asset('storage/' . $shop->logo) : '' }}" alt="Logo" style="{{ $shop->logo ? '' : 'display: none;' }} max-height: 40px; margin-left: 10px; border-radius: 6px;">
                    </p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Address:</strong> <span id="main_shop_address">{{ $shop->address ?? 'N/A' }}</span></p>
                    <p style="font-size: 1.1rem; font-weight: 600;"><strong style="color: #8B4513;">Description:</strong> <span id="main_shop_description">{{ $shop->description ?? 'No description available' }}</span></p>
                </div> 
                        <span class="badge" style="background: linear-gradient(135deg, 
                            {{ $shop->status === 'active' ? '#28a745, #20c997' : 
                               ($shop->status === 'inactive' ? '#6c757d, #495057' : 
                                ($shop->status === 'suspended' ? '#dc3545, #c82333' : '#ffc107, #e0a800')) }}); 
                            color: {{ $shop->status === 'suspended' ? '#fff' : '#8B4513' }}; 
                            font-weight: bold; padding: 0.5rem 1rem; border-radius: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                            <i class="fas {{ $shop->status === 'active' ? 'fa-check-circle' : 
                                           ($shop->status === 'inactive' ? 'fa-pause-circle' : 
                                            ($shop->status === 'suspended' ? 'fa-ban' : 'fa-clock')) }}"></i> 
                            {{ ucfirst($shop->status) }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="text-center mt-3">
                <small style="color: #8B4513; font-style: italic;">"The heart of all operations - Leading with excellence"</small>
            </div>
        </div>
    </div>
    @can('view_subshops')
        <div class=" mb-3">
            <h4 class="h4"><i class="fas fa-store"></i> Branches</h4>
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-light">
                                <i class="fas fa-home"></i> {{ $subshop->name ?? 'N/A' }}
                            </h5>
                            <span class="badge {{ ($subshop->is_active ?? false) ? 'badge-success' : 'badge-secondary' }}">
                                {{ ($subshop->is_active ?? false) ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="subshop-info">
                                <p class="info-item">
                                    <i class="fas fa-phone text-primary"></i>
                                    <strong>Phone:</strong> {{ $subshop->phone ?? 'N/A' }}
                                </p>
                                <p class="info-item">
                                    <i class="fas fa-map-marker-alt text-danger"></i>
                                    <strong>Address:</strong> {{ $subshop->address ?? 'N/A' }}
                                </p>
                                <p class="info-item">
                                    <i class="fas fa-calendar text-info"></i>
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
                            <i class="fas fa-store-slash fa-4x mb-3 text-muted"></i>
                            <h4>No Sub Shops</h4>
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
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSubShopModalLabel">
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
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSubShopModalLabel">
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
                    <div class="modal-header" style="background: linear-gradient(135deg, #FFD700, #FFA500); color: white;">
                        <h5 class="modal-title" id="editMainShopModalLabel">
                            <i class="fas fa-edit"></i> Edit Main Branch Information
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
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
                                               placeholder="Auto-generated if empty" >
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
       CSS ROOT VARIABLES - BADILISHA COLORS HAPA
       ==================================== */
    :root {
        --primary-color: #004e92;
        --secondary-color: #000428;
        --gradient: linear-gradient(90deg, #004e92, #000428);
        --accent-color: #e94560;
        --success-color: #28a745;
        --danger-color: #dc3545;
        --warning-color: #ffc107;
        --info-color: #17a2b8;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
        --white: #ffffff;
        --shadow-light: 0 2px 10px rgba(0, 78, 146, 0.1);
        --shadow-medium: 0 4px 20px rgba(0, 78, 146, 0.15);
        --shadow-heavy: 0 8px 32px rgba(0, 78, 146, 0.2);
        --border-radius: 12px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Main Branches Card - Professional Design */
    .main-shop-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-light);
        overflow: hidden;
        position: relative;
        transition: var(--transition);
        background: var(--white);
    }

    .main-shop-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient);
    }

    .main-shop-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-heavy);
    }

    .main-shop-card .card-header {
        background: var(--gradient);
        color: var(--white);
        font-weight: 600;
        padding: 1.5rem;
        border: none;
        position: relative;
    }

    .main-shop-card .card-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: rgba(255, 255, 255, 0.2);
    }

    .main-shop-card .card-body {
        padding: 2rem;
    }

    .main-shop-card .card-body p {
        margin-bottom: 1rem;
        font-size: 1rem;
        color: var(--dark-color);
        display: flex;
        align-items: center;
    }

    .main-shop-card .card-body p strong {
        min-width: 100px;
        color: var(--primary-color);
        font-weight: 600;
    }

    .main-shop-card .card-body p i {
        margin-right: 0.5rem;
        color: var(--primary-color);
        width: 20px;
    }

    /* SubShop Cards - Modern Grid Design */
    .subshop-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-light);
        transition: var(--transition);
        background: var(--white);
        overflow: hidden;
        position: relative;
        height: 100%;
    }

    .subshop-card:hover {
        transform: translateY(-12px) scale(1.02);
        box-shadow: var(--shadow-heavy);
    }

    .subshop-card .card-header {
        background: var(--sidebar-bg);
        color: var(--white);
        padding: 1.25rem 1.5rem;
        border: none;
        position: relative;
    }

    .subshop-card .card-header h5 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .subshop-card .card-header h5 i {
        margin-right: 0.5rem;
        opacity: 0.9;
    }

    .subshop-card .card-header .badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-weight: 500;
    }

    .subshop-card .card-body {
        padding: 1.5rem;
        flex-grow: 1;
    }

    .subshop-info {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .subshop-info .info-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 8px;
        border-left: 3px solid var(--primary-color);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .subshop-info .info-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: var(--sidebar-bg);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .subshop-info .info-item:hover::before {
        opacity: 0.05;
    }

    .subshop-info .info-item:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(0, 78, 146, 0.1);
    }

    .subshop-info .info-item i {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin-right: 1rem;
        font-size: 0.9rem;
    }

    .subshop-info .info-item i.fa-phone {
        background: linear-gradient(135deg, #17a2b8, #0d7a8a);
        color: white;
    }

    .subshop-info .info-item i.fa-map-marker-alt {
        background: linear-gradient(135deg, #dc3545, #a0262a);
        color: white;
    }

    .subshop-info .info-item i.fa-calendar {
        background: linear-gradient(135deg, #ffc107, #e0a800);
        color: white;
    }

    .subshop-info .info-item strong {
        font-weight: 600;
        color: var(--dark-color);
        min-width: 80px;
    }

    .subshop-card .card-footer {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-top: 1px solid rgba(0, 78, 146, 0.1);
        padding: 1rem 1.5rem;
    }

    .subshop-card .card-footer .btn-group {
        width: 100%;
        gap: 0.75rem;
        padding: 0.5rem;
    }

    .subshop-card .card-footer .btn {
        flex: 1;
        border-radius: 10px;
        font-weight: 600;
        padding: 0.625rem 1rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        border: 2px solid transparent;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(0, 78, 146, 0.15);
    }

    .subshop-card .card-footer .btn i {
        margin-right: 0.5rem;
        font-size: 0.8rem;
        transition: transform 0.3s ease;
    }

    .subshop-card .card-footer .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1), rgba(255,255,255,0.2));
        transition: left 0.5s ease;
        z-index: 1;
    }

    .subshop-card .card-footer .btn::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: rgba(255,255,255,0.3);
        border-radius: 50%;
        transition: all 0.6s ease;
        transform: translate(-50%, -50%);
        z-index: 1;
    }

    .subshop-card .card-footer .btn:hover::before {
        left: 100%;
    }

    .subshop-card .card-footer .btn:active::after {
        width: 300px;
        height: 300px;
    }

    /* Edit Button - Primary Blue Gradient */
    .subshop-card .card-footer .btn-outline-primary {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-color: #007bff;
        color: #007bff;
        position: relative;
    }

    .subshop-card .card-footer .btn-outline-primary::before {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }

    .subshop-card .card-footer .btn-outline-primary:hover {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border-color: #0056b3;
        color: white;
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
    }

    .subshop-card .card-footer .btn-outline-primary:hover i {
        transform: rotate(360deg);
    }

    .subshop-card .card-footer .btn-outline-primary:active {
        transform: translateY(-1px) scale(1.01);
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
    }

    /* Delete Button - Danger Red Gradient */
    .subshop-card .card-footer .btn-outline-danger {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-color: #dc3545;
        color: #dc3545;
        position: relative;
    }

    .subshop-card .card-footer .btn-outline-danger::before {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }

    .subshop-card .card-footer .btn-outline-danger:hover {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        border-color: #c82333;
        color: white;
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
    }

    .subshop-card .card-footer .btn-outline-danger:hover i {
        transform: rotate(360deg);
    }

    .subshop-card .card-footer .btn-outline-danger:active {
        transform: translateY(-1px) scale(1.01);
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
    }

    /* Button Text and Content */
    .subshop-card .card-footer .btn span {
        position: relative;
        z-index: 2;
    }

    .subshop-card .card-footer .btn i {
        position: relative;
        z-index: 2;
    }

    /* Empty State - Professional Design */
    .empty-state {
        border: 2px dashed rgba(0, 78, 146, 0.3);
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-radius: var(--border-radius);
        text-align: center;
        padding: 3rem 2rem;
        position: relative;
        overflow: hidden;
    }

    .empty-state::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: var(--sidebar-bg);
        opacity: 0.02;
        transform: rotate(45deg);
        pointer-events: none;
    }

    .empty-state i {
        font-size: 4rem;
        color: rgba(0, 78, 146, 0.4);
        margin-bottom: 1.5rem;
        display: block;
    }

    .empty-state h4 {
        color: var(--dark-color);
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .empty-state p {
        color: #6c757d;
        font-size: 1rem;
        margin: 0;
    }

    /* Badges */
    .badge-success {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
    }

    .badge-secondary {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
    }

    /* Modal Enhancements */
    .modal-content {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-heavy);
    }

    .modal-header {
        background: var(--sidebar-bg);
        color: var(--white);
        border: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        padding: 1.5rem 2rem;
    }

    .modal-header .close {
        color: rgba(255, 255, 255, 0.8);
        opacity: 1;
        font-size: 1.5rem;
    }

    .modal-header .close:hover {
        color: white;
        opacity: 1;
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .subshop-card {
        animation: fadeInUp 0.6s ease-out;
    }

    .main-shop-card {
        animation: fadeInUp 0.6s ease-out 0.2s both;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .main-shop-card .card-body {
            padding: 1.5rem;
        }

        .subshop-card .card-header {
            padding: 1rem;
        }

        .subshop-card .card-body {
            padding: 1.25rem;
        }

        .subshop-card .card-footer {
            padding: 0.75rem 1rem;
        }

        .subshop-info .info-item {
            padding: 0.5rem;
        }

        .empty-state {
            padding: 2rem 1.5rem;
        }

        .empty-state i {
            font-size: 3rem;
        }
    }

    /* Button Hover Effects */
    .btn-primary {
        background: var(--sidebar-bg);
        border: none;
        border-radius: 8px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, rgba(255,255,255,0.2), rgba(255,255,255,0));
        transition: left 0.3s ease;
    }

    .btn-primary:hover::before {
        left: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-medium);
        background: var(--sidebar-bg);
    }

    /* Alert Enhancements */
    .alert {
        border: none;
        border-radius: var(--border-radius);
        border-left: 4px solid;
        box-shadow: var(--shadow-light);
        position: relative;
        overflow: hidden;
    }

    .alert::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: var(--sidebar-bg);
        opacity: 0.05;
    }

    .alert-success {
        border-left-color: var(--success-color);
    }

    .alert-danger {
        border-left-color: var(--danger-color);
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