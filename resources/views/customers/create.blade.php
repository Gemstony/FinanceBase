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
@stop

@section('content')
<div class="container-fluid">
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.store') }}">
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
                            <select name="id_type" class="form-control" required>
                                <option value="" disabled @selected(old('id_type') === null)>Choose ID</option>
                                <option value="NIDA" @selected(old('id_type') === 'NIDA')>NIDA Id</option>
                                <option value="Driving Lesence" @selected(old('id_type') === 'Driving Lesence')>Driving Lesence Id</option>
                                <option value="Voter Id" @selected(old('id_type') === 'Voter Id')>Voter Id</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ID Number <span class="text-danger">*</span></label>
                            <input type="text" name="id_number" class="form-control" value="{{ old('id_number') }}" required>
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
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
