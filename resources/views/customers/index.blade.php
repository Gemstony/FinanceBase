@extends('adminlte::page')

@section('title', 'Customers - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-users"></i> Customers</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-users"></i> Customers</h1>
                    <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <div>
                    @can('add_customers')
                        <a href="{{ route('customers.create') }}" class="btn btn-success border">
                            <i class="fas fa-plus"></i> New Customer
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Customers</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <!-- Summary Cards -->
    <div class="row mb-3">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3 class="customer-stat-value">{{ number_format($summary['total_customers'] ?? 0) }}</h3>
                    <p>Total Customers</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3 class="customer-stat-value">{{ number_format($summary['active_customers'] ?? 0) }}</h3>
                    <p>Active Customers</p>
                </div>
                <div class="icon"><i class="fas fa-user-check"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3 class="customer-stat-value">{{ number_format($summary['total_loans'] ?? 0) }}</h3>
                    <p>Total Loans</p>
                </div>
                <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3 class="customer-stat-value customer-stat-value--money">{!! str_replace(',', ',<wbr>', number_format((float) ($summary['total_outstanding'] ?? 0), 2)) !!}</h3>
                    <p>Total Outstanding</p>
                </div>
                <div class="icon"><i class="fas fa-coins"></i></div>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3" >
        <div class="card-body">
            <form method="GET" action="{{ route('customers.index') }}" class="mb-0">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Search</label>
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name / Email / Phone">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="active" @selected(request('status') === 'active')>Active</option>
                                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Sort</label>
                            <select name="sort" class="form-control">
                                <option value="date_desc" @selected(request('sort') === 'date_desc' || !request('sort'))>Date: New → Old</option>
                                <option value="date_asc" @selected(request('sort') === 'date_asc')>Date: Old → New</option>
                                <option value="name_asc" @selected(request('sort') === 'name_asc')>Name: A → Z</option>
                                <option value="name_desc" @selected(request('sort') === 'name_desc')>Name: Z → A</option>
                                <option value="status" @selected(request('sort') === 'status')>Status</option>
                            </select>
                        </div>
                        <div class="form-group col-md-1">
                            <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-filter"></i> Apply</button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            @can('export_customers')
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="{{ route('customers.export', ['format' => 'csv'] + request()->query()) }}">
                                            <i class="fas fa-file-csv mr-1 text-success"></i> CSV
                                        </a>
                                        <a class="dropdown-item" href="{{ route('customers.export', ['format' => 'excel'] + request()->query()) }}">
                                            <i class="fas fa-file-excel mr-1 text-success"></i> Excel
                                        </a>
                                        <a class="dropdown-item" href="{{ route('customers.export', ['format' => 'pdf'] + request()->query()) }}">
                                            <i class="fas fa-file-pdf mr-1 text-danger"></i> PDF
                                        </a>
                                    </div>
                                </div>
                            @endcan
                        </div>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('customers.index') }}">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-body table-responsive">
            <table class="table table-hover text-nowrap" id="customersTable">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Category</th>
                        <th>Region</th>
                        <th>Files</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $counter = 1;
                    @endphp
                    @forelse($customers as $customer)
                        <tr>
                            <td>{{ $counter++ }}</td>
                            <td>
                                <img src="{{ $customer->avatar_url }}" 
                                     alt="{{ $customer->name }}" 
                                     class="rounded-circle"
                                     style="width: 40px; height: 40px; object-fit: cover;">
                            </td>
                            <td>
                                <a href="{{ route('customers.show', $customer->id) }}"><strong>{{ $customer->name }}</strong></a>
                                @if($customer->email)
                                    <br><small class="text-muted"><i class="fas fa-envelope"></i> {{ $customer->email }}</small>
                                @endif
                            </td>
                            <td>{{ $customer->gender ?? '—' }}</td>
                            <td>{{ $customer->phone ?? '—' }}</td>
                            <td>{{ $customer->category ?? '—' }}</td>
                            <td>{{ $customer->region ?? '—' }}</td>
                            <td>
                                <span class="badge badge-info">{{ $customer->files_count ?? 0 }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $customer->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('customers.show', $customer->id) }}">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @can('edit_customers')
                                    <a class="btn btn-sm btn-outline-info" href="{{ route('customers.edit', $customer->id) }}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endcan
                                @can('delete_customers')
                                    <form method="POST" action="{{ route('customers.destroy', $customer->id) }}" class="d-inline js-delete-customer">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">No customers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($customers->hasPages())
                <div class="d-flex justify-content-center mt-3">
                    {{ $customers->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
    .small-box .inner {
        padding-right: 90px;
    }

    .customer-stat-value {
        display: block;
        max-width: 100%;
        font-size: clamp(1rem, 2.2vw, 2rem);
        line-height: 1.05;
        white-space: normal !important;
        overflow-wrap: anywhere;
        word-break: normal;
        margin-bottom: .25rem;
    }

    .customer-stat-value--money {
        font-size: clamp(.95rem, 2vw, 1.65rem);
    }

    @media (max-width: 575.98px) {
        .customer-stat-value {
            font-size: 1.1rem;
        }
    }
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('.js-delete-customer').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Delete this customer?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
@endpush
