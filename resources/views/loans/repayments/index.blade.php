@extends('adminlte::page')

@section('title', 'Loan Repayments - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-cash-register"></i> Loan Repayments</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-cash-register"></i> Loan Repayments</h1>
                    <p class="mb-0 text-light">Branch: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('loans.management') }}" class="btn btn-light btn-sm">
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
                <li class="breadcrumb-item active" aria-current="page">Repayments</li>
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
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['disbursed'] ?? 0) }}</h3>
                            <p>Disbursed</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format($summary['partially_paid'] ?? 0) }}</h3>
                            <p>Partially Paid</p>
                        </div>
                        <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            @php
                                $uniqueBorrowers = $loans->pluck('loanGroup.name')->merge($loans->pluck('customer.name'))->filter()->unique()->count();
                            @endphp
                            <h3 class="mb-0">{{ number_format($uniqueBorrowers) }}</h3>
                            <p>Unique Borrowers</p>
                        </div>
                        <div class="icon"><i class="fas fa-users"></i></div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 col-12">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format((float)($summary['principal_sum'] ?? 0), 0) }}</h3>
                            <p>Total Principal</p>
                        </div>
                        <div class="icon"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
                <div class="col-md-6 col-12">
                    <div class="small-box bg-dark">
                        <div class="inner">
                            <h3 class="mb-0">{{ number_format((float)($summary['outstanding_sum'] ?? 0), 0) }}</h3>
                            <p>Total Outstanding</p>
                        </div>
                        <div class="icon"><i class="fas fa-wallet"></i></div>
                    </div>
                </div>
            </div>

            <form method="get" action="{{ route('loan.repayments.index') }}" class="mb-3">
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

                        <div class="form-group col-md-2">
                            <label class="small mb-1">Penalties</label>
                            <select name="has_penalties" class="form-control">
                                <option value="">All</option>
                                <option value="1" {{ ($hasPenalties ?? '') === '1' ? 'selected' : '' }}>Has Penalties</option>
                                <option value="0" {{ ($hasPenalties ?? '') === '0' ? 'selected' : '' }}>No Penalties</option>
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
                            <a class="btn btn-light border" href="{{ route('loan.repayments.index') }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
            <table class="table table-striped table-hover" id="repaymentsTable">
                <thead class="thead-light" >
                    <tr>
                        <th>Date</th>
                        <th class="text-nowrap">Loan Code</th>
                        <th class="text-nowrap">Borrower</th>
                        <th class="text-nowrap">Product / Cycle</th>
                        <th class="text-right text-nowrap">Amount / Balance</th>
                        <th class="text-nowrap">Payment Progress</th>
                        <th class="text-nowrap">Penalties</th>
                        <th class="text-nowrap">Status</th>
                        <th class="text-nowrap">Timeline</th>
                        <th class="text-center" style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                        @php
                            $repaymentCycle = $loan->loanProduct?->repaymentFrequency?->name ?? '-';
                            $paidAmount = 0;
                            $lastPaymentDate = null;
                            $hasOverdue = false;
                            
                            $loanInstallments = $loan->installments()->get();
                            if ($loanInstallments && $loanInstallments->isNotEmpty()) {
                                $paidAmount = $loanInstallments->sum('amount_paid');
                                $lastPayment = $loanInstallments->where('amount_paid', '>', 0)->sortByDesc('paid_date')->first();
                                $lastPaymentDate = $lastPayment?->paid_date;
                                
                                $today = now()->toDateString();
                                $hasOverdue = $loanInstallments->contains(function($inst) use ($today) {
                                    return $inst->status !== 'paid' && $inst->due_date < $today;
                                });
                            }
                            
                            $statusBadgeClass = match ((string) $loan->status) {
                                'disbursed' => 'badge-primary',
                                'partially_paid' => 'badge-info',
                                default => 'badge-secondary',
                            };
                            
                            // Get penalty summary
                            $loanPenaltySummary = app(\App\Services\Loans\Penalties\PenaltyPaymentService::class)->getPenaltySummary($loan->id);
                            
                            $paymentStatusBadge = $hasOverdue ? 'badge-danger' : 'badge-success';
                            $paymentStatusText = $hasOverdue ? 'Overdue' : ($loan->status === 'paid_off' ? 'Paid Off' : ($loan->status === 'disbursed' || $loan->status === 'partially_paid' ? 'Current' : '-'));
                        @endphp
                        <tr>
                            <td class="text-nowrap">
                               <strong> <i>{{ $loan->created_at->format('Y-m-d') }}</i></strong>
                            </td>
                            <td class="text-nowrap">
                                <strong>{{ $loan->loan_code }}</strong>
                            </td>
                            <td>
                                @if($loan->borrower_type === 'group')
                                    <span class="text-info"><i class="fas fa-users"></i> {{ $loan->loanGroup?->name }}</span>
                                @else
                                    {{ $loan->customer?->name }}
                                @endif
                            </td>
                            <td>
                                <div>{{ $loan->loanProduct?->name }}</div>
                                <small class="text-muted">{{ $repaymentCycle }}</small>
                            </td>
                            <td class="text-right">
                                <div>{{ number_format((float)$loan->principal_amount, 0) }}</div>
                                <small class="{{ (float)$loan->calculated_outstanding > 0 ? 'text-primary font-weight-bold' : 'text-success' }}">
                                    Bal: {{ number_format((float)$loan->calculated_outstanding, 0) }}
                                </small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="badge {{ $paymentStatusBadge }} mr-1">{{ $paymentStatusText }}</span>
                                    <small class="text-muted">
                                        {{ number_format((float)$paidAmount, 0) }} paid
                                    </small>
                                </div>
                                @if($lastPaymentDate)
                                    <small class="text-muted d-block">Last: {{ \Carbon\Carbon::parse($lastPaymentDate)->format('Y-m-d') }}</small>
                                @endif
                            </td>
                            <td>
                                @if($loanPenaltySummary['total_charged'] > 0)
                                    <div class="d-flex flex-column">
                                        <span class="text-danger font-weight-bold">{{ number_format((float)$loanPenaltySummary['total_outstanding'], 0) }}</span>
                                        <small class="text-muted">{{ number_format((float)$loanPenaltySummary['total_charged'], 0) }} charged</small>
                                    </div>
                                    @if($loanPenaltySummary['has_pending'])
                                        <a href="{{ route('loan.penalties.pay.form', $loan) }}" class="badge badge-warning mt-1">Pay Now</a>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $statusBadgeClass }}">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span></td>
                            <td>
                                <small class="d-block">Disbursed: {{ $loan->disbursement_date ? \Carbon\Carbon::parse($loan->disbursement_date)->format('Y-m-d') : '-' }}</small>
                                <small class="d-block text-muted">Maturity: {{ $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('Y-m-d') : '-' }}</small>
                            </td>
                            <td class="text-center text-nowrap">
                                <a href="{{ route('loan.repayments.create', $loan->loan_code) }}" class="btn btn-sm btn-success" title="Pay">
                                    <i class="fas fa-hand-holding-usd"></i> Pay
                                </a>
                                <a href="{{ route('loan.repayments.show', $loan->loan_code) }}" class="btn btn-sm btn-outline-primary" title="History">
                                    <i class="fas fa-history"></i> History
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">No loans eligible for repayment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            
            @if($loans->hasPages())
                <div class="mt-3 d-flex justify-content-center">
                    {{ $loans->appends(request()->query())->links() }}
                </div>
            @endif
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
    if ($('#repaymentsTable').length) {
        $('#repaymentsTable').DataTable({
            responsive: {
                details: {
                    type: 'column',
                    target: -1
                }
            },
            columnDefs: [
                { orderable: false, targets: [-1] },
                { searchable: false, targets: [-1] },
                { className: 'dtr-control', targets: [-1] }
            ],
            order: [[0, 'desc']]
        });
    }
});
</script>
@endpush
