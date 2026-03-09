@extends('adminlte::page')

@section('title', 'Loan Disbursement - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Loan Disbursement</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Loan Disbursement</h1>
                    <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('categories.subshops') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Change Branch
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('loans.management') }}"><i class="fas fa-university"></i> Loan Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Disbursement</li>
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

        @php
            $blockingCount = 0;
            foreach ($loans as $loan) {
                $coll = $loan->collateral_status_badge ?? ['class' => 'bg-secondary'];
                $gua = $loan->guarantor_status_badge ?? ['class' => 'bg-secondary'];

                if (($coll['class'] ?? '') === 'bg-danger' || ($gua['class'] ?? '') === 'bg-danger') {
                    $blockingCount++;
                }
            }
        @endphp

        <div class="card shadow-sm border-0" style="box-shadow: 0 6px 20px rgba(0,0,0,.06);">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 col-12">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3 class="mb-0">{{ number_format($loans->count()) }}</h3>
                                <p>Approved Pending Disbursement</p>
                            </div>
                            <div class="icon"><i class="fas fa-check-circle"></i></div>
                        </div>
                    </div>

                    <div class="col-md-6 col-12">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3 class="mb-0">{{ number_format($blockingCount) }}</h3>
                                <p>Loans With Missing Requirements</p>
                            </div>
                            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                        </div>
                    </div>
                </div>

                <form method="GET" action="{{ route('loans.disbursement.index') }}" class="mb-3">
                    <div class="bg-light p-2 rounded border">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-4">
                                <label class="small mb-1">Loan Product</label>
                                <select name="loan_product_id" class="form-control">
                                    <option value="">All</option>
                                    @foreach($loanProducts as $product)
                                        <option value="{{ $product->id }}" {{ request('loan_product_id') == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label class="small mb-1">Approval From</label>
                                <input type="date" name="approval_date_from" class="form-control" value="{{ request('approval_date_from') }}">
                            </div>

                            <div class="form-group col-md-3">
                                <label class="small mb-1">Approval To</label>
                                <input type="date" name="approval_date_to" class="form-control" value="{{ request('approval_date_to') }}">
                            </div>

                            <div class="form-group col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                            </div>

                            <div class="form-group col-md-12">
                                <a href="{{ route('loans.disbursement.index') }}" class="btn btn-light border">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="disbursementsTable">
                        <thead class="thead-light" style="background: linear-gradient(90deg, #f7f9fc, #eef3fb); border-bottom: 1px solid #e5ecf6;">
                            <tr>
                                <th>#</th>
                                <th>Borrower</th>
                                <th>Product</th>
                                <th>Principal</th>
                                <th>Approval Date</th>
                                <th>Collateral</th>
                                <th>Guarantors</th>
                                <th>Fees</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $loanCounter = 1;
                            @endphp
                            @forelse($loans as $loan)
                                @php
                                    $coll = $loan->collateral_status_badge ?? ['status' => '—', 'class' => 'bg-secondary'];
                                    $gua = $loan->guarantor_status_badge ?? ['status' => '—', 'class' => 'bg-secondary'];
                                    $fee = $loan->fees_status_badge ?? ['status' => '—', 'class' => 'bg-secondary'];

                                    $hasBlocking = in_array($coll['class'], ['bg-danger'], true)
                                        || in_array($gua['class'], ['bg-danger'], true);
                                @endphp
                                <tr @if($hasBlocking) style="background-color: #fff5f5;" @endif>
                                    <td><strong>{{ $loanCounter++ }}</strong></td>
                                    <td>
                                        @if($loan->borrower_type === 'group')
                                            <i class="fa fa-users"></i> {{ $loan->loanGroup?->name ?? '—' }}
                                        @else
                                            {{ $loan->customer?->name ?? '—' }}
                                        @endif
                                    </td>
                                    <td>{{ $loan->loanProduct?->name ?? '—' }}</td>
                                    <td>{{ number_format((float) $loan->principal_amount, 2) }}</td>
                                    <td>{{ $loan->approval_date ? \Carbon\Carbon::parse($loan->approval_date)->format('Y-m-d') : '—' }}</td>
                                    <td><span class="badge {{ $coll['class'] }}">{{ $coll['status'] }}</span></td>
                                    <td><span class="badge {{ $gua['class'] }}">{{ $gua['status'] }}</span></td>
                                    <td><span class="badge {{ $fee['class'] }}">{{ $fee['status'] }}</span></td>
                                    <td class="text-right">
                                        <a href="{{ route('loans.disbursement.show', $loan) }}" class="btn btn-sm btn-outline-primary mr-1">
                                            View
                                        </a>
                                        <a href="{{ route('loans.disbursement.show', $loan) }}" class="btn btn-sm btn-success" @if($hasBlocking) disabled @endif>
                                            Disburse
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No approved loans pending disbursement.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($loans, 'links'))
                    <div class="mt-4 d-flex justify-content-center">
                        {{ $loans->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(document).ready(function() {
    if ($('#disbursementsTable').length) {
        $('#disbursementsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [8] },
                { searchable: false, targets: [8] }
            ],
            order: [[0, 'asc']]
        });
    }
});
</script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

