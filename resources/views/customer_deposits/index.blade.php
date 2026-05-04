@extends('adminlte::page')

@section('title', 'Customer Deposit Accounts')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-wallet"></i> Customer Deposit Accounts</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-wallet"></i> Customer Deposit Accounts</h1>
                    <p class="mb-0 text-light">Manage savings accounts, deposits, withdrawals, and loan payments</p>
                </div>
                <div>
                    <a href="{{ route('deposits.create') }}" class="btn btn-success border">
                        <i class="fas fa-plus"></i> New Account
                    </a>
                    <a href="{{ route('dashboard') }}" class="btn btn-light border ml-2">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Customer Deposit Accounts</li>
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

        <div class="row">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Total Accounts</div>
                                <div class="h4 mb-0">{{ number_format((int) ($summaryTotalAccounts ?? 0)) }}</div>
                            </div>
                            <i class="fas fa-wallet fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Active</div>
                                <div class="h4 mb-0">{{ number_format((int) ($summaryActiveAccounts ?? 0)) }}</div>
                            </div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Total Balance</div>
                                <div class="h4 mb-0">{{ number_format((float) ($summaryTotalBalance ?? 0), 2) }}</div>
                            </div>
                            <i class="fas fa-coins fa-2x text-muted"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small">Other Status</div>
                                <div class="h4 mb-0">
                                    {{ number_format((int) ($summaryFrozenAccounts ?? 0) + (int) ($summaryDormantAccounts ?? 0) + (int) ($summaryClosedAccounts ?? 0)) }}
                                </div>
                            </div>
                            <i class="fas fa-layer-group fa-2x text-muted"></i>
                        </div>
                        <div class="small text-muted mt-2">
                            Frozen: {{ number_format((int) ($summaryFrozenAccounts ?? 0)) }}
                            | Dormant: {{ number_format((int) ($summaryDormantAccounts ?? 0)) }}
                            | Closed: {{ number_format((int) ($summaryClosedAccounts ?? 0)) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <form method="GET" action="{{ route('deposits.index') }}" class="mb-3">
                    <div class="bg-light p-2 rounded border">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3">
                                <label class="small mb-1">Borrower</label>
                                <input type="text" name="borrower" class="form-control" value="{{ request('borrower') }}" placeholder="Borrower name">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small mb-1">Product</label>
                                <input type="text" name="product" class="form-control" value="{{ request('product') }}" placeholder="Product name">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small mb-1">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="active" @selected(request('status') === 'active')>Active</option>
                                    <option value="frozen" @selected(request('status') === 'frozen')>Frozen</option>
                                    <option value="dormant" @selected(request('status') === 'dormant')>Dormant</option>
                                    <option value="closed" @selected(request('status') === 'closed')>Closed</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                                <a class="btn btn-light border" href="{{ route('deposits.index') }}"><i class="fas fa-undo"></i> Reset</a>

                            </div>
                        </div>
                    </div>
                </form>

                <div class="mb-3">
                    <a href="{{ route('deposits.products.index') }}" class="btn btn-outline-info">
                        <i class="fas fa-box"></i> Manage Products
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="depositAccountsTable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Account</th>
                                <th>Borrower</th>
                                <th>Product</th>
                                <th class="text-right">Balance</th>
                                <th>Status</th>
                                <th>Opened</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $counter = 1;
                            @endphp
                            @forelse($accounts as $a)
                                <tr>
                                    <td>{{ $counter++ }}</td>
                                    <td>{{ $a->account_number }}</td>
                                    <td>{{ $a->customer?->name ?? '—' }}</td>
                                    <td>{{ $a->depositProduct?->name ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $a->balance, 2) }}</td>
                                    <td>
                                        @php
                                            $cls = match((string) $a->status) {
                                                'active' => 'badge-success',
                                                'frozen' => 'badge-warning',
                                                'dormant' => 'badge-secondary',
                                                'closed' => 'badge-dark',
                                                default => 'badge-light',
                                            };
                                        @endphp
                                        <span class="badge {{ $cls }}">{{ ucfirst($a->status) }}</span>
                                    </td>
                                    <td>{{ $a->opened_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        @if($a->customer)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('deposits.show', $a->customer) }}">
                                                <i class="fas fa-user"></i> Borrower
                                            </a>
                                        @endif
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('deposits.transactions', $a) }}">
                                            <i class="fas fa-list"></i> Ledger
                                        </a>
                                        @if(round((float) $a->balance, 2) === 0.0)
                                            <form method="POST" action="{{ route('deposits.destroy', $a->id) }}" class="d-inline js-delete-deposit-account">
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
                                    <td colspan="8" class="text-center text-muted">No deposit accounts found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $accounts->links() }}
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
    $('.js-delete-deposit-account').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'Delete this account?',
            text: 'This will permanently delete the deposit account (only allowed when balance is 0.00).',
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

    if ($('#depositAccountsTable').length) {
        $('#depositAccountsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [7] },
                { searchable: false, targets: [7] }
            ],
            order: [[6, 'desc']]
        });
    }
});
</script>
@endpush
