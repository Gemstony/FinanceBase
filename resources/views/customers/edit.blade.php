@extends('adminlte::page')

@section('title', 'Edit Customer')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-user-edit"></i> Edit Customer</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-user-edit"></i> Edit Customer</h1>
                    <p class="mb-0 text-light">{{ $customer->name }}</p>
                </div>
                <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.update', $customer->id) }}">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Customer Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $customer->name) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-control" required>
                                <option value="" disabled>Choose Gender</option>
                                <option value="M" @selected(old('gender', $customer->gender) === 'M')>Male</option>
                                <option value="F" @selected(old('gender', $customer->gender) === 'F')>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Birth Date <span class="text-danger">*</span></label>
                            <input type="date" name="birth_date" class="form-control" value="{{ old('birth_date', $customer->birth_date) }}" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Alternative Phone</label>
                            <input type="text" name="altenative_phone" class="form-control" value="{{ old('altenative_phone', $customer->altenative_phone) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}">
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Region <span class="text-danger">*</span></label>
                            <input type="text" name="region" class="form-control" value="{{ old('region', $customer->region) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>District <span class="text-danger">*</span></label>
                            <input type="text" name="district" class="form-control" value="{{ old('district', $customer->district) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Ward <span class="text-danger">*</span></label>
                            <input type="text" name="ward" class="form-control" value="{{ old('ward', $customer->ward) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Street <span class="text-danger">*</span></label>
                            <input type="text" name="street" class="form-control" value="{{ old('street', $customer->street) }}" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>House No <span class="text-danger">*</span></label>
                            <input type="text" name="house_no" class="form-control" value="{{ old('house_no', $customer->house_no) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-control" required>
                                <option value="" disabled>Choose Category</option>
                                <option value="borrower" @selected(old('category', $customer->category) === 'borrower')>Borrower</option>
                                <option value="guarantor" @selected(old('category', $customer->category) === 'guarantor')>Guarantor</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" {{ old('is_active', $customer->is_active ? '1' : '') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Work</label>
                            <input type="text" name="work" class="form-control" value="{{ old('work', $customer->work) }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Work Address</label>
                            <input type="text" name="work_address" class="form-control" value="{{ old('work_address', $customer->work_address) }}">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ID Type <span class="text-danger">*</span></label>
                            <select name="id_type" class="form-control" required>
                                <option value="" disabled>Choose ID</option>
                                <option value="NIDA" @selected(old('id_type', $customer->id_type) === 'NIDA')>NIDA Id</option>
                                <option value="Driving Lesence" @selected(old('id_type', $customer->id_type) === 'Driving Lesence')>Driving Lesence Id</option>
                                <option value="Voter Id" @selected(old('id_type', $customer->id_type) === 'Voter Id')>Voter Id</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ID Number <span class="text-danger">*</span></label>
                            <input type="text" name="id_number" class="form-control" value="{{ old('id_number', $customer->id_number) }}" required>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Customer
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
