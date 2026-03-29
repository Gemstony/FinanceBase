@extends('adminlte::page')

@section('title', 'Loan Products - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Loan Products</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Loan Products</h1>
                <div class="small text-light-50">Shop: {{ $subshop->name }}</div>
            </div>
            <a href="{{ route('loans.loan_products.create') }}" class="btn btn-outline-light"><i class="fas fa-plus"></i> New Product</a>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['count'] ?? 0) }}</h3>
                            <p>Total Products</p>
                        </div>
                        <div class="icon"><i class="fas fa-list"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['active'] ?? 0) }}</h3>
                            <p>Active</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['visible'] ?? 0) }}</h3>
                            <p>Visible</p>
                        </div>
                        <div class="icon"><i class="fas fa-eye"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['requires_approval'] ?? 0) }}</h3>
                            <p>Requires Approval</p>
                        </div>
                        <div class="icon"><i class="fas fa-user-check"></i></div>
                    </div>
                </div>
            </div>

            <form method="get" action="{{ route('loans.loan_products.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Search</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                                <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Name / Code">
                            </div>
                        </div>

                        <div class="form-group col-md-3">
                            <label class="small mb-1">Product Type</label>
                            <select name="loan_product_type_id" class="form-control">
                                <option value="">All</option>
                                @foreach($loanProductTypes as $t)
                                    <option value="{{ $t->id }}" {{ (string)($typeId ?? '') === (string)$t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label class="small mb-1">Active</label>
                            <select name="is_active" class="form-control">
                                <option value="">All</option>
                                <option value="1" {{ ($isActive ?? '') === '1' ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ ($isActive ?? '') === '0' ? 'selected' : '' }}>No</option>
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label class="small mb-1">Visible</label>
                            <select name="is_visible" class="form-control">
                                <option value="">All</option>
                                <option value="1" {{ ($isVisible ?? '') === '1' ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ ($isVisible ?? '') === '0' ? 'selected' : '' }}>No</option>
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label class="small mb-1">Approval</label>
                            <select name="requires_approval" class="form-control">
                                <option value="">All</option>
                                <option value="1" {{ ($requiresApproval ?? '') === '1' ? 'selected' : '' }}>Required</option>
                                <option value="0" {{ ($requiresApproval ?? '') === '0' ? 'selected' : '' }}>Not required</option>
                            </select>
                        </div>

                        <div class="form-group col-md-12">
                            <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
                            <a class="btn btn-light border" href="{{ route('loans.loan_products.index') }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover" id="LoanProductsTable">
                    <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Interest</th>
                            <th>Repayment</th>
                            <th class="text-right">Min Amount</th>
                            <th class="text-right">Max Amount</th>
                            <th class="text-center">Flags</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Updated</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $counter = 1;
                        @endphp
                        @forelse($loanProducts as $p)
                        <tr>
                            <td>{{ $counter++ }}</td>
                            <td>
                                <div class="font-weight-bold">{{ $p->name }}</div>
                                <div class="small text-muted">{{ $p->description ? \Illuminate\Support\Str::limit($p->description, 80) : '' }}</div>
                            </td>
                            <td><span class="badge badge-secondary">{{ $p->code }}</span></td>
                            <td>
                                <div class="small"><strong>Method:</strong> {{ $p->interestMethod->name ?? '-' }}</div>
                                <div class="small"><strong>Cycle:</strong> {{ $p->interestCycle->name ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="small"><strong>Frequency:</strong> {{ $p->repaymentFrequency->name ?? '-' }}</div>
                                <div class="small"><strong>Installments:</strong>
                                    {{ $p->min_installments ?? '-' }} - {{ $p->max_installments ?? '-' }}
                                </div>
                            </td>
                            <td class="text-right">{{ number_format((float)($p->rules->min_loan_amount ?? 0), 2) }}</td>
                            <td class="text-right">{{ number_format((float)($p->rules->max_loan_amount ?? 0), 2) }}</td>
                            <td class="text-center">
                                @if($p->supports_collateral)
                                    <span class="badge badge-info">Collateral</span>
                                @endif
                                @if($p->requires_approval)
                                    <span class="badge badge-warning">Approval</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($p->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                                @if(!$p->is_visible)
                                    <span class="badge badge-dark">Hidden</span>
                                @endif
                            </td>
                            <td class="text-right">{{ $p->updated_at ? $p->updated_at->format('d M Y, H:i') : '' }}</td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('loans.loan_products.show', $p->id) }}"><i class="fas fa-eye"></i> View</a>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('loans.loan_products.edit', $p->id) }}"><i class="fas fa-edit"></i> Edit</a>
                                    <form method="POST" action="{{ route('loans.loan_products.destroy', $p->id) }}" style="display:inline-block;" class="delete-loan-product-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger delete-loan-product-btn" data-name="{{ $p->name }}">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5"><i class="fas fa-inbox"></i> No loan products found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                {{ $loanProducts->links() }}
            </div>

            
        </div>
    </div>
</div>

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
@if(session('success'))
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: @json(session('success'))
});
@endif
@if(session('error'))
Swal.fire({
    icon: 'error',
    title: 'Error!',
    text: @json(session('error'))
});
@endif

document.addEventListener('submit', function(e){
    const form = e.target.closest('.delete-loan-product-form');
    if(!form) return;
    e.preventDefault();

    const btn = form.querySelector('.delete-loan-product-btn');
    const name = btn ? (btn.getAttribute('data-name') || '') : '';

    Swal.fire({
        icon: 'warning',
        title: `Delete ${name}?`,
        text: 'This will remove the loan product and its configurations.',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
    }).then((res) => {
        if(res.isConfirmed){ form.submit(); }
    });
});
</script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#LoanProductsTable').DataTable({
        responsive: true,
     
        order: [
            [9, 'desc']
        ] // Sort by code by default
    });
});
 </script>
@stop