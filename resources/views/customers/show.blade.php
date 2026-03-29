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
                    <a href="{{ route('customers.export.single', ['id' => $customer->id, 'format' => 'excel']) }}" class="btn btn-outline-light border ml-2">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="{{ route('customers.export.single', ['id' => $customer->id, 'format' => 'pdf']) }}" class="btn btn-outline-light border ml-2">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
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
            <!-- Customer Information Card -->
            <div class="card shadow-sm border-0 mb-4" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white"><strong>Customer Information</strong></div>
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

            <!-- Loan History Section -->
            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-history"></i> Loan History</strong>
                    <span class="badge badge-primary">{{ $allLoans->count() }} Total</span>
                </div>
                <div class="card-body p-0">
                    @if($allLoans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="pl-3">Loan Code</th>
                                        <th>Product</th>
                                        <th>Status</th>
                                        <th class="text-right">Principal</th>
                                        <th class="text-right">Outstanding</th>
                                        <th>Disbursed</th>
                                        <th>Maturity</th>
                                        <th>Installments</th>
                                        <th class="text-center">DPD</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($allLoans as $loan)
                                        <tr>
                                            <td class="pl-3">
                                                <a href="{{ route('loans.loans.show', $loan->loan_code) }}" class="text-primary font-weight-bold">
                                                    {{ $loan->loan_code }}
                                                </a>
                                            </td>
                                            <td>{{ $loan->loanProduct->name ?? 'N/A' }}</td>
                                            <td>
                                                @switch($loan->status)
                                                    @case('pending')
                                                        <span class="badge badge-warning">Pending</span>
                                                        @break
                                                    @case('approved')
                                                        <span class="badge badge-info">Approved</span>
                                                        @break
                                                    @case('disbursed')
                                                        @if($loan->is_written_off)
                                                            <span class="badge badge-dark">Written Off</span>
                                                        @elseif($loan->days_past_due > 0)
                                                            <span class="badge badge-danger">Overdue</span>
                                                        @else
                                                            <span class="badge badge-success">Active</span>
                                                        @endif
                                                        @break
                                                    @case('partially_paid')
                                                        @if($loan->is_written_off)
                                                            <span class="badge badge-dark">Written Off</span>
                                                        @elseif($loan->days_past_due > 0)
                                                            <span class="badge badge-danger">Overdue</span>
                                                        @else
                                                            <span class="badge badge-primary">Partial</span>
                                                        @endif
                                                        @break
                                                    @case('defaulted')
                                                        <span class="badge badge-danger">Defaulted</span>
                                                        @break
                                                    @case('paid')
                                                        <span class="badge badge-secondary">Paid</span>
                                                        @break
                                                    @case('rejected')
                                                        <span class="badge badge-secondary">Rejected</span>
                                                        @break
                                                    @case('closed')
                                                        <span class="badge badge-secondary">Closed</span>
                                                        @break
                                                    @default
                                                        <span class="badge badge-secondary">{{ $loan->status }}</span>
                                                @endswitch
                                            </td>
                                            <td class="text-right">{{ number_format($loan->principal_amount, 2) }}</td>
                                            <td class="text-right">
                                                @if($loan->calculated_outstanding > 0)
                                                    <span class="{{ $loan->days_past_due > 0 ? 'text-danger font-weight-bold' : '' }}">
                                                        {{ number_format($loan->calculated_outstanding, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">0.00</span>
                                                @endif
                                            </td>
                                            <td>{{ $loan->disbursement_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td>{{ $loan->maturity_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td class="text-center">
                                                {{ $loan->installments_paid ?? 0 }}/{{ $loan->installments ?? 0 }}
                                            </td>
                                            <td class="text-center">
                                                @if($loan->days_past_due > 0)
                                                    <span class="badge badge-danger">{{ $loan->days_past_due }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('loans.loans.show', $loan->loan_code) }}" class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-hand-holding-usd fa-3x mb-3"></i>
                            <p>No loans found for this customer</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Loan Statistics Cards -->
            <div class="row">
                <div class="col-6 col-md-12 mb-2">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 class="customer-stat-value">{{ (int) ($stats['loans_count'] ?? 0) }}</h3>
                            <p>Total Loans</p>
                        </div>
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-12 mb-2">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 class="customer-stat-value customer-stat-value--money">{!! str_replace(',', ',<wbr>', number_format((float) ($stats['outstanding_balance'] ?? 0), 2)) !!}</h3>
                            <p>Outstanding Balance</p>
                        </div>
                        <div class="icon"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-12 mb-2">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 class="customer-stat-value">{{ (int) ($stats['active_loans_count'] ?? 0) }}</h3>
                            <p>Active Loans</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-12 mb-2">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 class="customer-stat-value">{{ (int) ($stats['overdue_loans_count'] ?? 0) }}</h3>
                            <p>Overdue Loans</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-12 mb-2">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3 class="customer-stat-value">{{ (int) ($stats['closed_loans_count'] ?? 0) }}</h3>
                            <p>Closed Loans</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-double"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-12 mb-2">
                    <div class="small-box bg-dark">
                        <div class="inner">
                            <h3 class="customer-stat-value">{{ (int) ($stats['written_off_count'] ?? 0) }}</h3>
                            <p>Written Off</p>
                        </div>
                        <div class="icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>
            </div>

            <!-- Financial Summary Card -->
            <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white"><strong><i class="fas fa-chart-line"></i> Financial Summary</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Disbursed</span>
                        <span class="font-weight-bold">{{ number_format($stats['total_principal'] ?? 0, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Repaid</span>
                        <span class="font-weight-bold text-success">{{ number_format($stats['total_repaid'] ?? 0, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Outstanding</span>
                        <span class="font-weight-bold {{ ($stats['outstanding_balance'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ number_format($stats['outstanding_balance'] ?? 0, 2) }}</span>
                    </div>
                    @if(($stats['total_principal'] ?? 0) > 0)
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Recovery Rate</span>
                            <span class="font-weight-bold text-primary">
                                {{ round(($stats['total_repaid'] ?? 0) / ($stats['total_principal'] ?? 1) * 100, 1) }}%
                            </span>
                        </div>
                    @endif
                    @if(($stats['overdue_amount'] ?? 0) > 0)
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Overdue Amount</span>
                            <span class="font-weight-bold text-danger">{{ number_format($stats['overdue_amount'] ?? 0, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Max Days PD</span>
                            <span class="font-weight-bold text-danger">{{ $stats['max_days_past_due'] ?? 0 }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Loan Status Summary -->
            <div class="card shadow-sm border-0 mb-3" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white"><strong><i class="fas fa-chart-pie"></i> Loan Status</strong></div>
                <div class="card-body">
                    <div class="row text-center">
                        @if(($stats['loan_status_summary']['disbursed'] ?? 0) > 0)
                        <div class="col-4 mb-2">
                            <div class="h4 mb-0 text-success">{{ $stats['loan_status_summary']['disbursed'] ?? 0 }}</div>
                            <small class="text-muted">Active</small>
                        </div>
                        @endif
                        @if(($stats['loan_status_summary']['partially_paid'] ?? 0) > 0)
                        <div class="col-4 mb-2">
                            <div class="h4 mb-0 text-primary">{{ $stats['loan_status_summary']['partially_paid'] ?? 0 }}</div>
                            <small class="text-muted">Partial</small>
                        </div>
                        @endif
                        @if(($stats['loan_status_summary']['defaulted'] ?? 0) > 0)
                        <div class="col-4 mb-2">
                            <div class="h4 mb-0 text-danger">{{ $stats['loan_status_summary']['defaulted'] ?? 0 }}</div>
                            <small class="text-muted">Defaulted</small>
                        </div>
                        @endif
                        @if(($stats['loan_status_summary']['paid'] ?? 0) > 0)
                        <div class="col-4 mb-2">
                            <div class="h4 mb-0 text-secondary">{{ $stats['loan_status_summary']['paid'] ?? 0 }}</div>
                            <small class="text-muted">Paid</small>
                        </div>
                        @endif
                        @if(($stats['loan_status_summary']['pending'] ?? 0) > 0)
                        <div class="col-4 mb-2">
                            <div class="h4 mb-0 text-warning">{{ $stats['loan_status_summary']['pending'] ?? 0 }}</div>
                            <small class="text-muted">Pending</small>
                        </div>
                        @endif
                        @if(($stats['loan_status_summary']['written_off'] ?? 0) > 0)
                        <div class="col-4 mb-2">
                            <div class="h4 mb-0 text-dark">{{ $stats['loan_status_summary']['written_off'] ?? 0 }}</div>
                            <small class="text-muted">W/Off</small>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Links Card -->
            <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
                <div class="card-header bg-white"><strong>Quick Links</strong></div>
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

    .table td, .table th {
        vertical-align: middle;
    }

    .table .text-right {
        text-align: right;
    }

    @media (max-width: 575.98px) {
        .customer-stat-value {
            font-size: 1.1rem;
        }
    }
</style>
@endpush
