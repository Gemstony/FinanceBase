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
            <h4 class="mb-2" style="color: #2d3748; font-weight: 600;">No Branches Available</h4>
            <p class="mb-4">Create your first branch to begin operations.</p>
            <p class="small text-muted mb-4">Branches enable you to manage loans, loan products, staff, and operations by location.</p>
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
              <label class="font-weight-semibold">Select a Branch</label>
              <p class="text-muted small mb-2">This sets your active workspace. You may switch at any time.</p>
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
            <p class="small text-muted mt-3 mb-0">Note: If your branch is not listed, click "Add New Branch" to create it.</p>
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
          <span class="info-box-text">Branch Utilization</span>
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
              <i class="fas fa-check-circle text-success"></i> {{ $maxSubshops - $currentSubshops }} branch(es) remaining
            @else
              <i class="fas fa-exclamation-triangle text-danger"></i> Branch limit reached. Please contact administrator to increase limit.
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
                        <small class="form-text text-muted">Please use a clear, unique name for easy identification.</small>
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
                        <small class="form-text text-muted">Please include country code if applicable.</small>
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
                        <small class="form-text text-muted">Street, area, and city details are helpful for reports.</small>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="subshop_is_active" 
                                   name="is_active" value="1" checked>
                            <label class="custom-control-label" for="subshop_is_active">
                                Branch is Active?
                            </label>
                        </div>
                        <small class="form-text text-muted">Inactive branches are hidden from selection until reactivated.</small>
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
      /* CSS ROOT VARIABLES - Formal Microfinance Theme */
      :root {
        --primary-color: #1e3a5f;
        --secondary-color: #2c5282;
        --accent-color: #3182ce;
        --success-color: #276749;
        --danger-color: #c53030;
        --warning-color: #c05621;
        --info-color: #2b6cb0;
        --light-color: #f7fafc;
        --dark-color: #2d3748;
        --white: #ffffff;
        --gray-100: #f7fafc;
        --gray-200: #edf2f7;
        --gray-300: #e2e8f0;
        --gray-400: #cbd5e0;
        --gray-500: #a0aec0;
        --gray-600: #718096;
        --gray-700: #4a5568;
        --gray-800: #2d3748;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --border-radius: 6px;
        --border-radius-lg: 8px;
        --transition: all 0.2s ease;
      }

      /* Professional background */
      .content-wrapper {
        background: var(--gray-100);
        min-height: 100vh;
      }

      /* Formal card styling */
      .shop-selection-card {
        border: 1px solid var(--gray-300);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: var(--transition);
        background: white;
      }

      .shop-selection-card:hover {
        box-shadow: var(--shadow-md);
      }

      /* Card header - Professional navy blue */
      .shop-selection-card .card-header {
        background: var(--primary-color);
        padding: 1.25rem 1.5rem;
        border: none;
      }

      .shop-selection-card .card-header h3 {
        color: white;
        font-weight: 600;
        margin: 0;
        font-size: 1.125rem;
        letter-spacing: 0.025em;
      }

      .shop-selection-card .card-header .fas {
        opacity: 0.9;
      }

      /* Formal form styling */
      .custom-select {
        height: calc(2.5rem + 2px);
        border: 1px solid var(--gray-300);
        border-radius: var(--border-radius);
        font-size: 0.9375rem;
        transition: var(--transition);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%231e3a5f' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
      }

      .custom-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.1);
        outline: none;
      }

      /* Formal button styling */
      .btn-primary {
        background: var(--primary-color);
        border: 1px solid var(--primary-color);
        border-radius: var(--border-radius);
        padding: 0.625rem 1.5rem;
        font-weight: 500;
        font-size: 0.9375rem;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
      }

      .btn-primary:hover:not(:disabled) {
        background: var(--secondary-color);
        border-color: var(--secondary-color);
        box-shadow: var(--shadow);
      }

      .btn-primary:active:not(:disabled) {
        background: var(--secondary-color);
      }

      .btn-primary:disabled {
        background: var(--gray-400);
        border-color: var(--gray-400);
        box-shadow: none;
        cursor: not-allowed;
      }

      .btn-outline-secondary {
        border: 1px solid var(--gray-400);
        color: var(--gray-700);
        border-radius: var(--border-radius);
        padding: 0.625rem 1.5rem;
        font-weight: 500;
        font-size: 0.9375rem;
        transition: var(--transition);
        background: white;
      }

      .btn-outline-secondary:hover:not(:disabled) {
        background: var(--gray-100);
        border-color: var(--gray-500);
        color: var(--gray-800);
      }

      .btn-outline-secondary:disabled {
        border-color: var(--gray-300);
        color: var(--gray-400);
        cursor: not-allowed;
      }

      /* Empty state styling */
      .empty-state-icon {
        color: var(--gray-400);
      }

      /* Info Box Styling - Professional */
      .info-box {
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow);
        padding: 1rem;
        color: white;
        display: flex;
        align-items: center;
      }

      .info-box-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
        border-radius: var(--border-radius);
        background: rgba(255, 255, 255, 0.2);
        margin-right: 1rem;
      }

      .info-box-content {
        flex: 1;
      }

      .info-box-text {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        opacity: 0.9;
      }

      .info-box-number {
        display: block;
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0.25rem 0;
      }

      .info-box-number small {
        font-size: 0.8125rem;
        font-weight: 400;
        opacity: 0.9;
      }

      .info-box .progress {
        height: 6px;
        margin: 0.5rem 0;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
      }

      .info-box .progress-bar {
        border-radius: 3px;
      }

      .info-box .progress-description {
        font-size: 0.8125rem;
        margin-top: 0.5rem;
        opacity: 0.95;
      }

      /* Alert styling - Professional */
      .alert {
        border: none;
        border-radius: var(--border-radius);
        padding: 1rem 1.25rem;
        box-shadow: var(--shadow-sm);
      }

      .alert-info {
        background: var(--info-color);
        color: white;
      }

      .alert-danger {
        background: var(--danger-color);
        color: white;
      }

      /* Form label styling */
      .font-weight-semibold {
        font-weight: 600;
        color: var(--gray-800);
        font-size: 0.9375rem;
      }

      /* Modal enhancements - Professional */
      .modal-content {
        border: 1px solid var(--gray-300);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-lg);
      }

      .modal-header {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
        padding: 1.25rem 1.5rem;
      }

      .modal-header .modal-title {
        font-weight: 600;
        font-size: 1.125rem;
        letter-spacing: 0.025em;
      }

      .modal-header .close {
        color: white;
        opacity: 0.8;
        text-shadow: none;
        transition: var(--transition);
      }

      .modal-header .close:hover {
        opacity: 1;
      }

      .modal-body {
        padding: 1.5rem;
      }

      .modal-footer {
        border: none;
        border-top: 1px solid var(--gray-200);
        padding: 1rem 1.5rem;
        background: var(--gray-50);
      }

      /* Input group styling - Professional */
      .input-group-text {
        background: var(--gray-100);
        color: var(--gray-700);
        border: 1px solid var(--gray-300);
        border-radius: var(--border-radius) 0 0 var(--border-radius);
      }

      .input-group .form-control {
        border: 1px solid var(--gray-300);
        border-left: none;
        border-radius: 0 var(--border-radius) var(--border-radius) 0;
        transition: var(--transition);
      }

      .input-group .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.1);
      }

      /* Custom switch styling - Professional */
      .custom-switch .custom-control-label::before {
        background-color: var(--gray-300);
        border: none;
        transition: var(--transition);
      }

      .custom-switch .custom-control-input:checked ~ .custom-control-label::before {
        background: var(--primary-color);
      }

      /* Tip text styling */
      .text-muted {
        color: var(--gray-600) !important;
      }

      /* Danger text */
      .text-danger {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      /* Page header enhancement */
      .content-header h1 {
        color: var(--gray-800);
        font-weight: 600;
      }

      .content-header .text-muted {
        font-size: 0.9375rem;
      }

      /* Responsive adjustments */
      @media (max-width: 768px) {
        .btn-primary, .btn-outline-secondary {
          padding: 0.5rem 1rem;
          font-size: 0.875rem;
        }

        .custom-select {
          height: calc(2.25rem + 2px);
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
    // Custom SweetAlert2 styling - Formal for Microfinance
    const swalCustom = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-primary mx-2',
            cancelButton: 'btn btn-secondary mx-2'
        },
        buttonsStyling: false
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
                    title: 'Success',
                    text: 'Branch has been added successfully.',
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
                        title: 'Error',
                        text: response.error,
                        confirmButtonText: 'OK'
                    });
                } else {
                    // Generic error
                    swalCustom.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred. Please try again.',
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
                        title: 'Success',
                        text: response.message,
                        timer: 1000,
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
                        text: 'An unexpected error occurred. Please try again.',
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
