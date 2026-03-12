@extends('adminlte::page')

@section('title', 'Customer - ' . $customer->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-user"></i> {{ $customer->name }}</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-user"></i> {{ $customer->name }}</h1>
                    <p class="mb-0 text-light">Customer Profile</p>
                </div>
                <div>
                    @can('edit_customers')
                        <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-outline-light border">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    @endcan
                    <a href="{{ route('customers.index') }}" class="btn btn-light border ml-2">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
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
        <div class="col-md-8">
            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header"><strong>Customer Information</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="small text-muted">Phone</div>
                            <div><strong>{{ $customer->phone ?? '—' }}</strong></div>
                            @if($customer->altenative_phone)
                                <div class="small text-muted mt-2">Alternative Phone</div>
                                <div><strong>{{ $customer->altenative_phone }}</strong></div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Email</div>
                            <div><strong>{{ $customer->email ?? '—' }}</strong></div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="small text-muted">Gender</div>
                            <div><strong>{{ $customer->gender ?? '—' }}</strong></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Birth Date</div>
                            <div><strong>{{ $customer->birth_date ?? '—' }}</strong></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Category</div>
                            <div><strong>{{ $customer->category ?? '—' }}</strong></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Status</div>
                            <div>
                                <span class="badge {{ $customer->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="small text-muted">Region</div>
                            <div><strong>{{ $customer->region ?? '—' }}</strong></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">District</div>
                            <div><strong>{{ $customer->district ?? '—' }}</strong></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Ward</div>
                            <div><strong>{{ $customer->ward ?? '—' }}</strong></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Street</div>
                            <div><strong>{{ $customer->street ?? '—' }}</strong></div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-3">
                            <div class="small text-muted">House No</div>
                            <div><strong>{{ $customer->house_no ?? '—' }}</strong></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Work</div>
                            <div><strong>{{ $customer->work ?? '—' }}</strong></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Work Address</div>
                            <div><strong>{{ $customer->work_address ?? '—' }}</strong></div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="small text-muted">ID Type</div>
                            <div><strong>{{ $customer->id_type ?? '—' }}</strong></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">ID Number</div>
                            <div><strong>{{ $customer->id_number ?? '—' }}</strong></div>
                        </div>
                    </div>

                    <hr>

                    <div class="small text-muted">Created At</div>
                    <div><strong>{{ $customer->created_at?->format('Y-m-d H:i') ?? '—' }}</strong></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="row">
                <div class="col-6 col-md-12">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 class="customer-stat-value">{{ (int) ($stats['loans_count'] ?? 0) }}</h3>
                            <p>Total Loans</p>
                        </div>
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-12">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 class="customer-stat-value customer-stat-value--money">{!! str_replace(',', ',<wbr>', number_format((float) ($stats['outstanding_balance'] ?? 0), 2)) !!}</h3>
                            <p>Outstanding Balance</p>
                        </div>
                        <div class="icon"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-12">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 class="customer-stat-value">{{ (int) ($stats['deposit_accounts_count'] ?? 0) }}</h3>
                            <p>Deposit Accounts</p>
                        </div>
                        <div class="icon"><i class="fas fa-piggy-bank"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-12">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 class="customer-stat-value">{{ (int) ($stats['active_loans_count'] ?? 0) }}</h3>
                            <p>Active Loans</p>
                        </div>
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header"><strong>Quick Links</strong></div>
                <div class="card-body">
                    <a href="{{ route('credits.show', $customer->id) }}" class="btn btn-outline-success btn-block mb-2">
                        <i class="fas fa-wallet"></i> Credits
                    </a>
                    <a href="{{ route('deposits.show', $customer->id) }}" class="btn btn-outline-primary btn-block mb-2">
                        <i class="fas fa-piggy-bank"></i> Deposit Accounts
                    </a>
                    <a href="{{ route('security-deposits.borrower', $customer->id) }}" class="btn btn-outline-secondary btn-block">
                        <i class="fas fa-shield-alt"></i> Security Deposits
                    </a>
                </div>
            </div>
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
