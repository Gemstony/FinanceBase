@extends('adminlte::page')

@section('title', 'Collections Worklist')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-hand-holding-usd"></i> Collections Worklist</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-hand-holding-usd"></i> Collections</h1>
                <div class="small text-light-50">Prioritizing recoveries and repayment follow-ups</div>
            </div>
            <div class="d-flex">
                <a href="{{ route('collections.actions') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-tasks"></i> Actions</a>
                <a href="{{ route('collections.promises') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-handshake"></i> Promises</a>
                <a href="{{ route('collections.schedule') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-calendar"></i> Schedule</a>
                <a href="{{ route('risk.portfolio') }}" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-chart-line"></i> Portfolio</a>
                <a href="{{ route('risk.delinquent.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-exclamation-triangle"></i> Delinquent</a>
            </div>
        </div>
    </div>

    
    <div class="d-flex justify-content-between align-items-center">
     <nav aria-label="breadcrumb">
         <ol class="breadcrumb">
             <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
             <li class="breadcrumb-item active" aria-current="page">Collections</li>
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
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
        <!-- Filter Panel -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter"></i> Filters</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-2">
                                <select name="risk_category" class="form-control">
                                    <option value="">All Risk Categories</option>
                                    <option value="par30" {{ request('risk_category') == 'par30' ? 'selected' : '' }}>PAR30</option>
                                    <option value="par60" {{ request('risk_category') == 'par60' ? 'selected' : '' }}>PAR60</option>
                                    <option value="par90" {{ request('risk_category') == 'par90' ? 'selected' : '' }}>PAR90</option>
                                    <option value="default" {{ request('risk_category') == 'default' ? 'selected' : '' }}>Default</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="borrower_type" class="form-control">
                                    <option value="">All Borrowers</option>
                                    <option value="individual" {{ request('borrower_type') == 'individual' ? 'selected' : '' }}>Individual</option>
                                    <option value="group" {{ request('borrower_type') == 'group' ? 'selected' : '' }}>Group</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="min_dpd" class="form-control" value="{{ request('min_dpd') }}" placeholder="Min Days Overdue">
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="max_dpd" class="form-control" value="{{ request('max_dpd') }}" placeholder="Max Days Overdue">
                            </div>
                            <div class="col-md-2">
                                <select name="officer" class="form-control">
                                    <option value="">All Officers</option>
                                    @foreach($officers ?? [] as $officer)
                                        <option value="{{ $officer->id }}" {{ request('officer') == $officer->id ? 'selected' : '' }}>{{ $officer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter"></i> Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-danger">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Prioritized Collections Worklist</h3>
                <span class="badge badge-dark">Sorted by Priority Score (Highest First)</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="collectionsTable">
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>Loan ID</th>
                                <th>Borrower</th>
                                <th>Phone</th>
                                <th>Days Overdue</th>
                                <th>Outstanding Balance</th>
                                <th>Risk Category</th>
                                <th>Loan Officer</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loans as $loan)
                                @php
                                    $score = $loan->collection_score ?? 0;
                                    $scoreClass = $score >= 80 ? 'bg-danger' : ($score >= 50 ? 'bg-warning' : 'bg-success');
                                    $scoreText = $score >= 80 ? 'High' : ($score >= 50 ? 'Medium' : 'Low');
                                @endphp
                                <tr @if($loan->max_days_overdue >= 90) style="background-color: #fff5f5;" @endif>
                                    <td>
                                        <span class="badge {{ $scoreClass }}" title="Priority Score: {{ round($score, 1) }}">
                                            {{ round($score, 0) }} - {{ $scoreText }}
                                        </span>
                                    </td>
                                    <td>{{ $loan->loan_code }}</td>
                                    <td>
                                        @if($loan->borrower_type === 'group')
                                            <i class="fas fa-users text-muted mr-1"></i> {{ $loan->loanGroup?->name }}
                                        @else
                                            <i class="fas fa-user text-muted mr-1"></i> {{ $loan->customer?->name }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($loan->borrower_type === 'group')
                                            {{ $loan->loanGroup?->phone ?? 'N/A' }}
                                        @else
                                            {{ $loan->customer?->phone ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-bold {{ $loan->max_days_overdue >= 30 ? 'text-danger' : 'text-warning' }}">
                                            {{ $loan->max_days_overdue }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($loan->outstanding_balance, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $loan->risk_category === 'default' ? 'bg-dark' : ($loan->risk_category === 'par90' ? 'bg-danger' : ($loan->risk_category === 'par60' ? 'bg-orange' : 'bg-warning')) }}">
                                            {{ strtoupper($loan->risk_category) }}
                                        </span>
                                    </td>
                                    <td>{{ $loan->latestDisbursement?->processor?->name ?? 'Unassigned' }}</td>
                                    <td class="text-right">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-toggle="dropdown">
                                                <i class="fas fa-phone"></i> Action
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('loans.loans.show', $loan) }}"><i class="fas fa-eye text-primary"></i> View Loan</a>
                                                <div class="dropdown-divider"></div>
                                                <button type="button" class="dropdown-item" data-toggle="modal" data-target="#recordActionModal" data-loan-id="{{ $loan->id }}" data-customer-id="{{ $loan->customer_id }}" data-action-type="phone_call"><i class="fas fa-phone text-success"></i> Log Phone Call</button>
                                                <button type="button" class="dropdown-item" data-toggle="modal" data-target="#recordActionModal" data-loan-id="{{ $loan->id }}" data-customer-id="{{ $loan->customer_id }}" data-action-type="field_visit"><i class="fas fa-walking text-info"></i> Log Field Visit</button>
                                                <button type="button" class="dropdown-item" data-toggle="modal" data-target="#recordPromiseModal" data-loan-id="{{ $loan->id }}" data-customer-id="{{ $loan->customer_id }}"><i class="fas fa-handshake text-warning"></i> Record Promise to Pay</button>
                                                @if($loan->max_days_overdue >= 90)
                                                    <div class="dropdown-divider"></div>
                                                    <button type="button" class="dropdown-item text-danger"><i class="fas fa-exclamation-triangle"></i> Recommend Write-off</button>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No collections needed at this time.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($loans->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">Total Collections Required:</th>
                                <th colspan="4">{{ number_format($loans->sum('outstanding_balance'), 2) }}</th>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(document).ready(function() {
    if ($('#collectionsTable').length) {
        $('#collectionsTable').DataTable({
            responsive: true,
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            order: [[3, 'desc']],
            pageLength: 10,
            language: {
                searchPlaceholder: "Search collections...",
                search: ""
            }
        });
    }
});
</script>

<!-- Record Action Modal -->
<div class="modal fade" id="recordActionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('collections.create-action') }}">
                @csrf
                <input type="hidden" name="loan_id" id="actionLoanId">
                <input type="hidden" name="customer_id" id="actionCustomerId">
                <input type="hidden" name="action_type" id="actionType">
                <div class="modal-header">
                    <h5 class="modal-title">Record Collection Action</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Action Type</label>
                        <input type="text" id="actionTypeDisplay" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Outcome</label>
                        <select name="outcome" class="form-control" required>
                            <option value="">Select outcome...</option>
                            <option value="successful_payment">Successful Payment</option>
                            <option value="promise_made">Promise Made</option>
                            <option value="no_contact">No Contact</option>
                            <option value="refused_payment">Refused Payment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount Collected</label>
                        <input type="number" name="amount_collected" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Record Action</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Promise Modal -->
<div class="modal fade" id="recordPromiseModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('collections.create-promise') }}">
                @csrf
                <input type="hidden" name="loan_id" id="promiseLoanId">
                <input type="hidden" name="customer_id" id="promiseCustomerId">
                <div class="modal-header">
                    <h5 class="modal-title">Record Promise to Pay</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Amount Promised</label>
                        <input type="number" name="amount_promised" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Promise Date</label>
                        <input type="date" name="promise_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Promise Type</label>
                        <select name="promise_type" class="form-control">
                            <option value="partial_payment">Partial Payment</option>
                            <option value="full_payment">Full Payment</option>
                            <option value="installment_resumption">Resume Installments</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-handshake"></i> Record Promise</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal handlers
$('#recordActionModal').on('show.bs.modal', function(event) {
    var button = $(event.relatedTarget);
    $('#actionLoanId').val(button.data('loan-id'));
    $('#actionCustomerId').val(button.data('customer-id'));
    $('#actionType').val(button.data('action-type'));
    $('#actionTypeDisplay').val(button.data('action-type').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()));
});

$('#recordPromiseModal').on('show.bs.modal', function(event) {
    var button = $(event.relatedTarget);
    $('#promiseLoanId').val(button.data('loan-id'));
    $('#promiseCustomerId').val(button.data('customer-id'));
});
</script>
@endpush
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
.gap-2 { gap: 0.5rem; }
</style>
@endpush
