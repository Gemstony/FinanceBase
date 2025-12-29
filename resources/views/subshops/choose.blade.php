@extends('adminlte::page')

@section('title', 'Choose Branch')

@section('content_header')
<!-- <div class="d-flex align-items-center justify-content-between">
  <div>
    <h1 class="mb-1"><i class="fas fa-store mr-2"></i>Choose a Shop</h1>
    <p class="text-muted mb-0">Select the shop you want to work in. You can add a new one if it's not listed.</p>
  </div>
</div> -->
@stop

@section('content')
@php
  $user = auth()->user();
  $shop = $user ? $user->shop : null;
  $maxSubshops = $shop ? ($shop->max_subshops ?? 0) : 0; // 0 => unlimited
  $currentSubshops = $shop ? $shop->subShops()->count() : 0;
  $canAddMore = ($maxSubshops == 0) || ($currentSubshops < $maxSubshops);
  $canCreate = $user && method_exists($user, 'hasRole') ? $user->hasRole(['owner','Super Admin']) : false;
@endphp
<div class="row justify-content-center">
  <div class="col-lg-8">
    @if(session('info'))
      <div class="alert alert-info d-flex align-items-center">
        <i class="fas fa-info-circle mr-2"></i>
        <span>{{ session('info') }}</span>
      </div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger d-flex align-items-center">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <span>{{ session('error') }}</span>
      </div>
    @endif


    <div class="card shop-selection-card">
      <div class="card-header">
        <h3 class="card-title mb-0"><i class="fas fa-shopping-bag mr-2"></i>Branch Selection</h3>
      </div>
      <div class="card-body" style="padding: 2rem;">
        @if($subshops->isEmpty())
          <div class="text-center text-muted py-5">
            <i class="fas fa-store-slash fa-4x mb-4 empty-state-icon"></i>
            <h4 class="mb-2" style="color: #2d3748; font-weight: 600;">No Braches yet</h4>
            <p class="mb-4">Create your first Branch to get started.</p>
            <p class="small text-muted mb-4">Branches help you separate loans, loans products, staff and etc, by location.</p>
            @if($canCreate)
              <button type="button" class="btn btn-primary btn-lg"
                @if($canAddMore)
                  data-toggle="modal" data-target="#addSubShopModal"
                @else
                  disabled
                @endif>
                <i class="fas fa-plus"></i> Add New Branch
                @unless($canAddMore)
                  <i class="fas fa-lock ml-1"></i>
                @endunless
              </button>
              @unless($canAddMore)
                <p class="small text-danger mt-3 mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>Branches limit reached. Please contact admin to increase your limit.</p>
              @endunless
            @endif
          </div>
        @else
          <form method="POST" action="{{ route('subshops.choose.store') }}" id="chooseSubShopForm">
            @csrf
            <input type="hidden" name="intended" value="{{ $intended }}">
            <div class="form-group">
              <label class="font-weight-semibold">Select a branch</label>
              <p class="text-muted small mb-2">This sets your active workspace. You can switch anytime.</p>
              <select name="subshop_id" class="custom-select" required>
                @foreach($subshops as $s)
                  <option value="{{ $s->id }}" @selected(session('subshop_id')===$s->id)>{{ $s->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="d-flex align-items-center">
              <button type="submit" class="btn btn-primary mr-2">
                <i class="fas fa-arrow-right"></i> Continue
              </button>
              @if($canCreate)
                <button type="button" class="btn btn-outline-secondary"
                  @if($canAddMore)
                    data-toggle="modal" data-target="#addSubShopModal"
                  @else
                    disabled
                  @endif>
                  <i class="fas fa-plus"></i> Add New Branch
                  @unless($canAddMore)
                    <i class="fas fa-lock ml-1"></i>
                  @endunless
                </button>
              @endif
            </div>
            <p class="small text-muted mt-3 mb-0">Tip: If you don't see your Branch, click "Add New Branch" to create it.</p>
            @if($canCreate && !$canAddMore)
              <p class="small text-danger mt-2 mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>Branch limit reached. Please contact admin to increase your limit.</p>
            @endif
          </form>
        @endif
      </div>
    </div>



    <!-- Branches Usage Info (if applicable) -->
    @if($canCreate && $maxSubshops > 0)
      @php
        $usagePercentage = min(($currentSubshops / $maxSubshops) * 100, 100);
      @endphp
      <div class="info-box {{ $usagePercentage >= 90 ? 'bg-danger' : ($usagePercentage >= 70 ? 'bg-warning' : 'bg-info') }} mb-3">
        <span class="info-box-icon"><i class="fas fa-chart-bar"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Branch Usage</span>
          <span class="info-box-number">
            {{ $currentSubshops }}/{{ $maxSubshops }}
            <small>({{ number_format($usagePercentage, 1) }}% used)</small>
          </span>
          <div class="progress">
            <div class="progress-bar {{ $usagePercentage >= 90 ? 'bg-danger' : ($usagePercentage >= 70 ? 'bg-warning' : 'bg-success') }}" 
                 role="progressbar" 
                 style="width: {{ $usagePercentage }}%" 
                 aria-valuenow="{{ $currentSubshops }}" 
                 aria-valuemin="0" 
                 aria-valuemax="{{ $maxSubshops }}">
            </div>
          </div>
          <div class="progress-description">
            @if($canAddMore)
              <i class="fas fa-check-circle text-success"></i> {{ $maxSubshops - $currentSubshops }} Branches remaining
            @else
              <i class="fas fa-exclamation-triangle text-danger"></i> Branch limit reached - Contact admin to increase limit
            @endif
          </div>
        </div>
      </div>
    @endif

    
  </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="addSubShopModal" tabindex="-1" role="dialog" aria-labelledby="addSubShopModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('subshops.create-modal') }}" method="POST" id="addSubShopForm">
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
                        <small class="form-text text-muted">Use a clear, unique name for easy identification.</small>
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
                        <small class="form-text text-muted">Include country code if needed.</small>
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
                        <small class="form-text text-muted">Street, area, and city are helpful for reports.</small>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="subshop_is_active" 
                                   name="is_active" value="1" checked>
                            <label class="custom-control-label" for="subshop_is_active">
                                Branch is Active?
                            </label>
                        </div>
                        <small class="form-text text-muted">Inactive Branches are hidden from selection until reactivated.</small>
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

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
      /* CSS ROOT VARIABLES - Main Blue Theme */
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

      /* Modern gradient background */
      .content-wrapper {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
      }

      /* Enhanced card styling */
      .shop-selection-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-light);
        overflow: hidden;
        transition: var(--transition);
        background: white;
      }

      .shop-selection-card:hover {
        box-shadow: var(--shadow-heavy);
        transform: translateY(-5px);
      }

      /* Card header with blue gradient */
      .shop-selection-card .card-header {
        background: var(--sidebar-bg);
        padding: 1.5rem;
        border: none;
      }

      .shop-selection-card .card-header h3 {
        color: white;
        font-weight: 600;
        margin: 0;
        font-size: 1.25rem;
      }

      .shop-selection-card .card-header .fas {
        opacity: 1;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
      }

      /* Enhanced form styling */
      .custom-select {
        height: calc(2.75rem + 2px);
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 1rem;
        transition: var(--transition);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23004e92' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
      }

      .custom-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(0, 78, 146, 0.25);
        outline: none;
      }

      /* Modern button styling with blue gradient */
      .btn-primary {
        background: var(--gradient);
        border: none;
        border-radius: 10px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: var(--transition);
        box-shadow: 0 4px 15px rgba(0, 78, 146, 0.4);
      }

      .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 78, 146, 0.6);
        background: linear-gradient(90deg, #000428, #004e92);
      }

      .btn-primary:active:not(:disabled) {
        transform: translateY(0);
      }

      .btn-primary:disabled {
        background: #cbd5e0;
        box-shadow: none;
        cursor: not-allowed;
      }

      .btn-outline-secondary {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        border-radius: 10px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: var(--transition);
        background: white;
      }

      .btn-outline-secondary:hover:not(:disabled) {
        background: var(--sidebar-bg);
        color: white;
        border-color: transparent;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 78, 146, 0.4);
      }

      .btn-outline-secondary:disabled {
        border-color: #cbd5e0;
        color: #cbd5e0;
        cursor: not-allowed;
      }

      /* Empty state styling */
      .empty-state-icon {
        animation: float 3s ease-in-out infinite;
        color: #a0aec0;
      }

      @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
      }

      /* Info Box Styling - Matches shops.blade.php */
      .info-box {
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-medium);
        padding: 1rem;
        color: white;
        display: flex;
        align-items: center;
        animation: slideInDown 0.5s ease;
      }

      .info-box-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 70px;
        height: 70px;
        font-size: 2rem;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.2);
        margin-right: 1rem;
      }

      .info-box-content {
        flex: 1;
      }

      .info-box-text {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        text-transform: uppercase;
        opacity: 0.9;
      }

      .info-box-number {
        display: block;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0.25rem 0;
      }

      .info-box-number small {
        font-size: 0.875rem;
        font-weight: 400;
        opacity: 0.9;
      }

      .info-box .progress {
        height: 4px;
        margin: 0.5rem 0;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 2px;
      }

      .info-box .progress-bar {
        border-radius: 2px;
      }

      .info-box .progress-description {
        font-size: 0.875rem;
        margin-top: 0.5rem;
        opacity: 0.95;
      }

      /* Alert enhancements */
      .alert {
        border: none;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        animation: slideInDown 0.5s ease;
      }

      @keyframes slideInDown {
        from {
          opacity: 0;
          transform: translateY(-20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .alert-info {
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        color: white;
      }

      .alert-danger {
        background: linear-gradient(135deg, #fc8181 0%, #f56565 100%);
        color: white;
      }

      /* Form label styling */
      .font-weight-semibold {
        font-weight: 600;
        color: #2d3748;
        font-size: 1rem;
      }

      /* Lock icon animation */
      .fa-lock {
        animation: shake 0.5s ease;
      }

      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
      }

      /* Modal enhancements - Blue Theme */
      .modal-content {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      }

      .modal-header {
        background: var(--sidebar-bg);
        color: white;
        border: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        padding: 1.5rem;
      }

      .modal-header .modal-title {
        font-weight: 600;
        font-size: 1.25rem;
      }

      .modal-header .close {
        color: white;
        opacity: 1;
        text-shadow: none;
        transition: transform 0.3s ease;
      }

      .modal-header .close:hover {
        transform: rotate(90deg);
      }

      .modal-body {
        padding: 2rem;
      }

      .modal-footer {
        border: none;
        padding: 1.5rem 2rem;
        background: #f7fafc;
      }

      /* Input group styling - Blue Theme */
      .input-group-text {
        background: var(--sidebar-bg);
        color: white;
        border: none;
        border-radius: 8px 0 0 8px;
      }

      .input-group .form-control {
        border: 2px solid #e2e8f0;
        border-left: none;
        border-radius: 0 8px 8px 0;
        transition: var(--transition);
      }

      .input-group .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(0, 78, 146, 0.15);
      }

      /* Custom switch styling - Blue Theme */
      .custom-switch .custom-control-label::before {
        background-color: #cbd5e0;
        border: none;
        transition: var(--transition);
      }

      .custom-switch .custom-control-input:checked ~ .custom-control-label::before {
        background: var(--sidebar-bg);
      }

      /* Tip text styling */
      .text-muted {
        color: #718096 !important;
      }

      /* Danger text with icon */
      .text-danger {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        animation: pulse 2s ease-in-out infinite;
      }

      @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
      }

      /* Page header enhancement */
      .content-header h1 {
        color: #2d3748;
        font-weight: 700;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.05);
      }

      .content-header .text-muted {
        font-size: 1rem;
      }

      /* Responsive adjustments */
      @media (max-width: 768px) {
        .btn-primary, .btn-outline-secondary {
          padding: 0.625rem 1.5rem;
          font-size: 0.9rem;
        }

        .custom-select {
          height: calc(2.5rem + 2px);
        }
      }
    </style>
@endpush

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Custom SweetAlert2 styling
    const swalCustom = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-primary mx-2',
            cancelButton: 'btn btn-secondary mx-2'
        },
        buttonsStyling: false,
        showClass: {
            popup: 'animate__animated animate__fadeInDown animate__faster'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp animate__faster'
        }
    });

    $('#addSubShopForm').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var formData = new FormData(this);
        
        // Disable submit button
        form.find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                'Accept': 'application/json'
            },
            success: function(response) {
                // Close modal
                $('#addSubShopModal').modal('hide');
                
                // Reset form
                form[0].reset();
                
                // Show success message with custom styling
                swalCustom.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Branch added successfully!',
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false
                });
                
                // Reload page to show the select
                setTimeout(function() {
                    location.reload();
                }, 1000);
            },
            error: function(xhr) {
                var response = xhr.responseJSON;
                
                // Clear previous errors
                form.find('.text-danger').remove();
                form.find('.is-invalid').removeClass('is-invalid');
                
                if (response && response.errors) {
                    // Validation errors
                    $.each(response.errors, function(field, messages) {
                        var input = form.find('[name="' + field + '"]');
                        input.addClass('is-invalid');
                        input.after('<div class="text-danger small mt-1">' + messages[0] + '</div>');
                    });
                } else if (response && response.error) {
                    // Custom error (e.g., limit exceeded)
                    swalCustom.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: response.error,
                        confirmButtonText: 'OK'
                    });
                } else {
                    // Generic error
                    swalCustom.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred. Please try again.',
                        confirmButtonText: 'OK'
                    });
                }
            },
            complete: function() {
                // Re-enable submit button
                form.find('button[type="submit"]').prop('disabled', false).html('<i class="fas fa-save"></i> Save');
            }
        });
    });

    $('#chooseSubShopForm').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var formData = new FormData(this);
        
        // Disable submit button with loading animation
        form.find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                'Accept': 'application/json'
            },
            success: function(response) {
                if (response.success) {
                    swalCustom.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = response.redirect || '{{ route("dashboard") }}';
                    });
                } else {
                    swalCustom.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message,
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function(xhr) {
                var response = xhr.responseJSON;
                if (response && response.message) {
                    swalCustom.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message,
                        confirmButtonText: 'OK'
                    });
                } else {
                    swalCustom.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred. Please try again.',
                        confirmButtonText: 'OK'
                    });
                }
            },
            complete: function() {
                // Re-enable submit button
                form.find('button[type="submit"]').prop('disabled', false).html('<i class="fas fa-arrow-right"></i> Continue');
            }
        });
    });
});
</script>
@endpush

@stop
