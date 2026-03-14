@extends('adminlte::page')

@section('title', 'Loans - ' . $subshop->name)

@section('content_header')
 <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
     <div class="card-body">
         <div class="d-flex justify-content-between align-items-center">
             <div>
                 <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Loans</h1>
                 <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Loans</h1>
                 <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
             </div>
            <a href="{{ route('loans.management') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
         </div>
     </div>
 </div>
 <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>

             <li class="breadcrumb-item active" aria-current="page">Loans</li>
         </ol>
     </nav>
     <a href="{{ route('loans.loans.create') }}" class="btn btn-primary">
         <i class="fas fa-plus"></i> New Loan
     </a>
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
            <div class="row mb-3">
                <div class="col-md-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['total'] ?? 0) }}</h3>
                            <p>Total Loans</p>
                        </div>
                        <div class="icon"><i class="fas fa-list"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['pending'] ?? 0) }}</h3>
                            <p>Pending</p>
                        </div>
                        <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['disbursed'] ?? 0) }}</h3>
                            <p>Disbursed</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['paid_off'] ?? 0) }}</h3>
                            <p>Paid Off</p>
                        </div>
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 col-12">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format((float)($summary['principal_sum'] ?? 0), 2) }}</h3>
                            <p>Total Principal</p>
                        </div>
                        <div class="icon"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
                <div class="col-md-6 col-12">
                    <div class="small-box bg-dark">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format((float)($summary['outstanding_sum'] ?? 0), 2) }}</h3>
                            <p>Total Outstanding</p>
                        </div>
                        <div class="icon"><i class="fas fa-wallet"></i></div>
                    </div>
                </div>
            </div>

            <form method="get" action="{{ route('loans.loans.index') }}" class="mb-3">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Search</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                                <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Loan Code / Borrower / Product">
                            </div>
                        </div>

                        <div class="form-group col-md-2">
                            <label class="small mb-1">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                @foreach(($statuses ?? []) as $s)
                                    <option value="{{ $s }}" {{ (string)($status ?? '') === (string)$s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label class="small mb-1">Borrower Type</label>
                            <select name="borrower_type" class="form-control">
                                <option value="">All</option>
                                <option value="individual" {{ ($borrowerType ?? '') === 'individual' ? 'selected' : '' }}>Individual</option>
                                <option value="group" {{ ($borrowerType ?? '') === 'group' ? 'selected' : '' }}>Group</option>
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label class="small mb-1">Loan Product</label>
                            <select name="loan_product_id" class="form-control">
                                <option value="">All</option>
                                @foreach(($loanProducts ?? collect()) as $p)
                                    @if(is_object($p))
                                        <option value="{{ $p->id }}" {{ (string)($loanProductId ?? '') === (string)$p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-2">
                            <label class="small mb-1">From</label>
                            <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" class="form-control">
                        </div>

                        <div class="form-group col-md-2">
                            <label class="small mb-1">To</label>
                            <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" class="form-control">
                        </div>

                        <div class="form-group col-md-12">
                            <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
                            <a class="btn btn-light border" href="{{ route('loans.loans.index') }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
            <table class="table table-striped table-hover" id="loansTable">
                <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Borrower</th>
                        <th>Principal</th>
                        <th>Status</th>
                        <th>Disbursement</th>
                        <th>Maturity</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $loanCounter = 1;
                    @endphp
                    @forelse($loans as $loan)
                        <tr>
                            <td>{{ $loanCounter++ }}</td>
                         
                            <td>{{ $loan->loanProduct?->name }}</td>
                            <td>
                                @if($loan->borrower_type === 'group')
                                    Group: {{ $loan->loanGroup?->name }}
                                @else
                                    {{ $loan->customer?->name }}
                                @endif
                            </td>
                            <td>{{ number_format((float)$loan->principal_amount, 2) }}</td>
                            @php
                                $statusBadgeClass = match ((string) $loan->status) {
                                    'pending' => 'badge-warning',
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    'disbursed' => 'badge-primary',
                                    'partially_paid' => 'badge-info',
                                    'paid_off' => 'badge-success',
                                    'defaulted' => 'badge-dark',
                                    'written_off' => 'badge-secondary',
                                    default => 'badge-secondary',
                                };
                            @endphp
                            <td><span class="badge {{ $statusBadgeClass }}">{{ $loan->status }}</span></td>
                            <td>{{ $loan->disbursement_date ? \Carbon\Carbon::parse($loan->disbursement_date)->format('Y-m-d') : '-' }}</td>
                            <td>{{ $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('Y-m-d') : '-' }}</td>
                            <td class="text-right">
                                @if($loan->status === 'pending' && (int)($loan->installments_paid ?? 0) === 0)
                                    <a href="{{ route('loans.loans.edit', $loan->loan_code) }}" class="btn btn-sm btn-outline-secondary mr-1">Edit</a>
                                @endif
                                <a href="{{ route('loans.loans.show', $loan->loan_code) }}" class="btn btn-sm btn-outline-primary mr-1">View</a>
                                @if(in_array($loan->status, ['pending']) && (int)($loan->installments_paid ?? 0) === 0)
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-loan-btn" data-loan-code="{{ $loan->loan_code }}" data-loan-id="{{ $loan->id }}">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No loans found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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
    if ($('#loansTable').length) {
        $('#loansTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [7] },
                { searchable: false, targets: [7] }
            ],
            order: [[0, 'asc']]
        });
    }

    document.querySelectorAll('.delete-loan-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const loanCode = btn.dataset.loanCode;
            const loanId = btn.dataset.loanId;

            Swal.fire({
                title: 'Delete Loan?',
                html: `<p>You are about to delete loan <strong>${loanCode}</strong>.</p><p>This action cannot be undone.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `{{ route('loans.loans.destroy', ':loan_code') }}`.replace(':loan_code', loanCode);
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = '{{ csrf_token() }}';
                    form.appendChild(csrf);
                    const method = document.createElement('input');
                    method.type = 'hidden';
                    method.name = '_method';
                    method.value = 'DELETE';
                    form.appendChild(method);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush

