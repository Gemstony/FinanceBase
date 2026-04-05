@extends('adminlte::page')

@section('title', 'New Customer')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-user-plus"></i> New Customer</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-user-plus"></i> New Customer</h1>
                    <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('customers.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
        <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
                <li class="breadcrumb-item active" aria-current="page">New Customer</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="container-fluid">
    @if(session('error'))
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle"></i> Error</h5>
            <p class="mb-0">{{ session('error') }}</p>
        </div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">
            <h5><i class="fas fa-check-circle"></i> Success</h5>
            <p class="mb-0">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('import_errors'))
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle"></i> Import Errors</h5>
            <p class="mb-2">The following errors were found in your CSV file. Please fix them and try again:</p>
            <ul class="mb-0">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="customerTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="true">
                        <i class="fas fa-user-plus"></i> Single Customer
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab" aria-controls="bulk" aria-selected="false">
                        <i class="fas fa-file-csv"></i> Bulk Import
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="customerTabsContent">
                <!-- Single Customer Tab -->
                <div class="tab-pane fade show active" id="single" role="tabpanel" aria-labelledby="single-tab">
                    <form method="POST" action="{{ route('customers.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Customer Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-control" required>
                                <option value="" disabled @selected(old('gender') === null)>Choose Gender</option>
                                <option value="M" @selected(old('gender') === 'M')>Male</option>
                                <option value="F" @selected(old('gender') === 'F')>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Birth Date <span class="text-danger">*</span></label>
                            <input type="date" name="birth_date" class="form-control" value="{{ old('birth_date') }}" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Alternative Phone</label>
                            <input type="text" name="altenative_phone" class="form-control" value="{{ old('altenative_phone') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Region <span class="text-danger">*</span></label>
                            <input type="text" name="region" class="form-control" value="{{ old('region') }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>District <span class="text-danger">*</span></label>
                            <input type="text" name="district" class="form-control" value="{{ old('district') }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Ward <span class="text-danger">*</span></label>
                            <input type="text" name="ward" class="form-control" value="{{ old('ward') }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Street <span class="text-danger">*</span></label>
                            <input type="text" name="street" class="form-control" value="{{ old('street') }}" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>House No <span class="text-danger">*</span></label>
                            <input type="text" name="house_no" class="form-control" value="{{ old('house_no') }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-control" required>
                                <option value="" disabled @selected(old('category') === null)>Choose Category</option>
                                <option value="borrower" @selected(old('category') === 'borrower')>Borrower</option>
                                <option value="guarantor" @selected(old('category') === 'guarantor')>Guarantor</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Work</label>
                            <input type="text" name="work" class="form-control" value="{{ old('work') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Work Address</label>
                            <input type="text" name="work_address" class="form-control" value="{{ old('work_address') }}">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ID Type <span class="text-danger">*</span></label>
                            <select name="id_type" id="id_type" class="form-control" required>
                                <option value="" disabled @selected(old('id_type') === null)>Choose ID</option>
                                <option value="NIDA" @selected(old('id_type') === 'NIDA')>NIDA ID</option>
                                <option value="Driving License" @selected(old('id_type') === 'Driving License')>Driving License Id</option>
                                <option value="Voter ID" @selected(old('id_type') === 'Voter ID')>Voter ID</option>
                                <option value="Other" @selected(old('id_type') === 'Other')>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ID Number <span class="text-danger">*</span></label>
                            <input type="text" name="id_number" id="id_number" class="form-control" value="{{ old('id_number') }}" required>
                            <small class="text-muted" id="id-format-hint"></small>
                            <div class="invalid-feedback" id="id_number_error"></div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Customer Image</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="customer_image" name="customer_image" accept="image/jpeg,image/png,image/webp">
                                <label class="custom-file-label" for="customer_image">Choose image (max 2MB)</label>
                            </div>
                            <small class="text-muted">Allowed: jpg, jpeg, png, webp</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Customer Files</label>
                            <div id="file-inputs-container">
                                <div class="input-group mb-2">
                                    <input type="file" name="customer_files[]" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-success add-file-btn" title="Add more files"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted">Allowed: pdf, doc, docx, jpg, jpeg, png (max 5 files, 5MB each)</small>
                            <div class="mt-2" id="selected-files-count"></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Customer
                    </button>
                </div>
                    </form>
                </div>

                <!-- Bulk Import Tab -->
                <div class="tab-pane fade" id="bulk" role="tabpanel" aria-labelledby="bulk-tab">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card border">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-upload"></i> Bulk Import Customers</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('customers.bulk-import') }}" enctype="multipart/form-data">
                                        @csrf
                                        
                                        <div class="mb-4">
                                            <label for="csv_file" class="form-label fw-bold">Select CSV File <span class="text-danger">*</span></label>
                                            <input type="file" class="form-control @error('csv_file') is-invalid @enderror"
                                                   id="csv_file" name="csv_file" accept=".csv" required>
                                            @error('csv_file')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">Only CSV files are allowed. Maximum file size: 2MB</small>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <a href="{{ route('customers.download-template') }}" class="btn btn-outline-success">
                                                <i class="fas fa-download"></i> Download CSV Template
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-file-import"></i> Import Customers
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Import Instructions</h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="fw-bold">Required Columns:</h6>
                                    <ul class="small">
                                        <li><strong>name</strong> - Customer full name</li>
                                        <li><strong>gender</strong> - M (Male) or F (Female)</li>
                                        <li><strong>birth_date</strong> - Format: YYYY-MM-DD</li>
                                        <li><strong>phone</strong> - Phone number</li>
                                        <li><strong>region</strong> - Region/Province</li>
                                        <li><strong>district</strong> - District</li>
                                        <li><strong>ward</strong> - Ward</li>
                                        <li><strong>street</strong> - Street name</li>
                                        <li><strong>house_no</strong> - House number</li>
                                        <li><strong>id_type</strong> - NIDA, Driving Lesence, Voter Id, or Other</li>
                                        <li><strong>id_number</strong> - ID number</li>
                                        <li><strong>category</strong> - borrower or guarantor</li>
                                    </ul>

                                    <h6 class="fw-bold mt-3">Optional Columns:</h6>
                                    <ul class="small">
                                        <li><strong>email</strong> - Email address</li>
                                        <li><strong>altenative_phone</strong> - Alternative phone</li>
                                        <li><strong>work</strong> - Work/Occupation</li>
                                        <li><strong>work_address</strong> - Work address</li>
                                    </ul>

                                    <h6 class="fw-bold mt-3">Important Rules:</h6>
                                    <ul class="small text-danger">
                                        <li>First row must be the header</li>
                                        <li>All required fields must be filled</li>
                                        <li>Gender must be M or F</li>
                                        <li>Date format: YYYY-MM-DD</li>
                                        <li>ID type must match allowed values</li>
                                        <li>Category must be borrower or guarantor</li>
                                        <li>If any row fails, all imports will be rolled back</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var fileCount = 1;
    var maxFiles = 5;
    
    // ID Number validation patterns

    var idPatterns = {
        'NIDA': { 
            pattern: /^\d{8}-\d{5}-\d{5}-\d{2}$/, 
            hint: 'Format: YYYYMMDD-XXXXX-XXXXX-XX', 
            example: '19760517-37227-00002-17',
            maxLength: 23
        },
        'Driving License': { 
            // Allowing 7-12 alphanumeric to cover older and new versions
            pattern: /^[A-Z0-9]{7,12}$/i, 
            hint: 'Enter 7 to 12 characters', 
            example: '1234567890',
            maxLength: 12
        },
        'Voter ID': { 
            // Allowing T or Z for Zanzibar/Mainland
            pattern: /^[T|Z]-\d{4}-\d{4}-\d{3}-\d{1}$/, 
            hint: 'Format: T-XXXX-XXXX-XXX-X', 
            example: 'T-1234-5678-901-2',
            maxLength: 17
        },
        'Other': { 
            pattern: /^.+$/, 
            hint: 'Enter your ID number', 
            example: '',
            maxLength: 50
        }
    };

    
    var isFormatting = false;
    
    function formatIdNumber(idType, value) {
        if (!idType || !value) return value;
        
        // Clean input: Keep only digits for NIDA, Alphanumeric for others
        var digits = value.replace(/\D/g, '');

        if (idType === 'NIDA') {
            let parts = [];
            if (digits.length > 0) parts.push(digits.substring(0, 8));
            if (digits.length > 8) parts.push(digits.substring(8, 13));
            if (digits.length > 13) parts.push(digits.substring(13, 18));
            if (digits.length > 18) parts.push(digits.substring(18, 20));
            return parts.join('-');
        }
        
        if (idType === 'Voter ID') {
            // Ensure it starts with T (or Z), then add dashes between digit groups
            let prefix = value.toUpperCase().startsWith('Z') ? 'Z' : 'T';
            let parts = [prefix]; 
            
            if (digits.length > 0) parts.push(digits.substring(0, 4));
            if (digits.length > 4) parts.push(digits.substring(4, 8));
            if (digits.length > 8) parts.push(digits.substring(8, 11));
            if (digits.length > 11) parts.push(digits.substring(11, 12));
            
            // This joins them as T-XXXX-XXXX-XXX-X
            return parts.join('-');
        }
        
        return value;
    }

    
    function autoFormatIdNumber(idType, value) {
        if (isFormatting) return value;
        isFormatting = true;
        var formatted = formatIdNumber(idType, value);
        isFormatting = false;
        return formatted;
    }
    
    function validateIdNumber() {
        var idType = $('#id_type').val();
        var idNumber = $('#id_number').val();
        var hintEl = $('#id-format-hint');
        var errorEl = $('#id_number_error');
        var inputEl = $('#id_number');
        
        hintEl.text('');
        errorEl.text('');
        inputEl.removeClass('is-invalid');
        
        if (!idType) {
            return { valid: true, data: null };
        }
        
        if (!idNumber) {
            return { valid: true, data: null };
        }
        
        var pattern = idPatterns[idType];
        if (pattern) {
            // Set max length
            inputEl.attr('maxlength', pattern.maxLength);
            hintEl.text(pattern.hint);
            
            if (!pattern.pattern.test(idNumber)) {
                inputEl.addClass('is-invalid');
                errorEl.text('Example: ' + pattern.example);
                return { valid: false, data: pattern };
            }
        }
        
        return { valid: true, data: null };
    }
    
    // When ID type changes, reformat the ID number
    $('#id_type').on('change', function() {
        var idType = $(this).val();
        var inputEl = $('#id_number');
        var pattern = idPatterns[idType];
        
        if (pattern) {
            inputEl.attr('maxlength', pattern.maxLength);
            $('#id-format-hint').text(pattern.hint);
        }
        
        validateIdNumber();
    });
    
    // Auto-format while typing and validate
    $('#id_number').on('input', function() {
        var idType = $('#id_type').val();
        var value = $(this).val();
        
        // Auto-format
        var formatted = autoFormatIdNumber(idType, value);
        if (formatted !== value) {
            $(this).val(formatted);
        }
        
        // Validate
        validateIdNumber();
    });
    
    // Prevent form submission if invalid
    $('form').on('submit', function(e) {
        var result = validateIdNumber();
        if (!result.valid) {
            e.preventDefault();
            $('#id_number').focus();
            return false;
        }
    });
    
    // File inputs handling
    $('#file-inputs-container').on('click', '.add-file-btn', function() {
        if (fileCount >= maxFiles) {
            alert('Maximum ' + maxFiles + ' files allowed.');
            return;
        }
        fileCount++;
        var html = '<div class="input-group mb-2">' +
            '<input type="file" name="customer_files[]" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">' +
            '<div class="input-group-append">' +
            '<button type="button" class="btn btn-danger remove-file-btn" title="Remove"><i class="fas fa-minus"></i></button>' +
            '</div></div>';
        $('#file-inputs-container').append(html);
        updateFileCount();
    });
    
    $('#file-inputs-container').on('click', '.remove-file-btn', function() {
        $(this).closest('.input-group').remove();
        fileCount--;
        updateFileCount();
    });
    
    function updateFileCount() {
        var count = $('#file-inputs-container input[type="file"]').length;
        var filled = 0;
        $('#file-inputs-container input[type="file"]').each(function() {
            if (this.files.length > 0) filled++;
        });
        $('#selected-files-count').text(filled + ' file(s) selected');
    }
    
    $('#file-inputs-container').on('change', 'input[type="file"]', function() {
        updateFileCount();
    });
});
</script>
@endpush
