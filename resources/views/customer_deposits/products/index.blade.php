@extends('adminlte::page')

@section('title', 'Deposit Products')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-box"></i> Deposit Products</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-box"></i> Deposit Products</h1>
                    <p class="mb-0 text-light">Configure savings products and their rules</p>
                </div>
                <div>
                    <a href="{{ route('deposits.products.create') }}" class="btn btn-success border">
                        <i class="fas fa-plus"></i> New Product
                    </a>
                    <a href="{{ route('deposits.index') }}" class="btn btn-light border ml-2">
                        <i class="fas fa-arrow-left"></i> Back to Accounts
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('deposits.index') }}">Customer Deposit Accounts</a></li>
                <li class="breadcrumb-item active" aria-current="page">Products</li>
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

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <form method="GET" action="{{ route('deposits.products.index') }}" class="mb-3">
                    <div class="bg-light p-2 rounded border">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3">
                                <label class="small mb-1">Type</label>
                                <select name="type" class="form-control">
                                    <option value="">All</option>
                                    <option value="savings" @selected(request('type') === 'savings')>Savings</option>
                                    <option value="current" @selected(request('type') === 'current')>Current</option>
                                    <option value="term_deposit" @selected(request('type') === 'term_deposit')>Term Deposit</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small mb-1">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="active" @selected(request('status') === 'active')>Active</option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                                <a class="btn btn-light border" href="{{ route('deposits.products.index') }}"><i class="fas fa-undo"></i> Reset</a>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="productsTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th class="text-right">Interest Rate</th>
                                <th class="text-right">Min Balance</th>
                                <th class="text-right">Withdrawal Fee</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                <tr>
                                    <td>{{ $p->name }}</td>
                                    <td>
                                        @php
                                            $typeBadge = match((string) $p->type) {
                                                'savings' => 'badge-primary',
                                                'current' => 'badge-info',
                                                'term_deposit' => 'badge-warning',
                                                default => 'badge-light',
                                            };
                                        @endphp
                                        <span class="badge {{ $typeBadge }}">{{ ucfirst(str_replace('_', ' ', $p->type)) }}</span>
                                    </td>
                                    <td class="text-right">{{ number_format((float) $p->interest_rate, 2) }}%</td>
                                    <td class="text-right">{{ number_format((float) $p->minimum_balance, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $p->withdrawal_fee, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $p->is_active ? 'badge-success' : 'badge-secondary' }}">
                                            {{ $p->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('deposits.products.edit', $p) }}">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        @if(((int) ($p->deposit_accounts_count ?? 0)) === 0)
                                            <form method="POST" action="{{ route('deposits.products.destroy', $p) }}" class="d-inline js-delete-deposit-product">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No deposit products found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('.js-delete-deposit-product').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Delete this product?',
            text: 'This will permanently delete the deposit product. Only allowed if no accounts are linked to it.',
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

    if ($('#productsTable').length) {
        $('#productsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [6] },
                { searchable: false, targets: [6] }
            ],
            order: [[0, 'asc']]
        });
    }
});
</script>
@endpush
