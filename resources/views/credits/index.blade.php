@extends('adminlte::page')

@section('title', 'Customer Credits')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-wallet"></i> Customer Credits</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-wallet"></i> Customer Credits</h1>
                    <p class="mb-0 text-light">Manage overpayments and credit balances</p>
                </div>
                <a href="{{ route('dashboard') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Customer Credits</li>
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
                <form method="GET" action="{{ route('credits.index') }}" class="mb-3">
                    <div class="bg-light p-2 rounded border">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-4">
                                <label class="small mb-1">Borrower</label>
                                <input type="text" name="borrower" class="form-control" value="{{ request('borrower') }}" placeholder="Borrower name">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="available" @selected(request('status') === 'available')>Available</option>
                                    <option value="applied" @selected(request('status') === 'applied')>Applied</option>
                                    <option value="refunded" @selected(request('status') === 'refunded')>Refunded</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">From</label>
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-1">To</label>
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                            <div class="form-group col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <!-- Liability Account Configuration -->
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            @php
                                $subshopId = session('subshop_id');
                                $liabilityConfigured = \App\Models\CustomerCreditLiabilityAccount::isConfiguredForSubshop($subshopId);
                                $liabilityAccount = \App\Models\CustomerCreditLiabilityAccount::forSubshop($subshopId);
                            @endphp
                            
                            @if($liabilityConfigured && $liabilityAccount)
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> 
                                    Liability Account: {{ $liabilityAccount->chartOfAccount?->account_name ?? 'N/A' }}
                                </span>
                            @else
                                <span class="badge badge-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    Liability Account Not Configured
                                </span>
                            @endif
                            
                            <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#liabilityAccountModal">
                                <i class="fas fa-cog"></i> Configure Liability Account
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="creditsTable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Borrower</th>
                                <th>Loan Source</th>
                                <th class="text-right">Amount</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $counter = 1;
                            @endphp
                            @foreach($credits as $credit)
                                <tr>
                                    <td>{{ $counter++ }}</td>
                                    <td>{{ $credit->customer?->name ?? '—' }}</td>
                                    <td>{{ $credit->loan?->loan_code ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $credit->amount, 2) }}</td>
                                    <td>
                                        @php
                                            $cls = match((string) $credit->status) {
                                                'available' => 'badge-success',
                                                'applied' => 'badge-info',
                                                'refunded' => 'badge-secondary',
                                                default => 'badge-light',
                                            };
                                        @endphp
                                        <span class="badge {{ $cls }}">{{ ucfirst($credit->status) }}</span>
                                    </td>
                                    <td>{{ $credit->created_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        @if($credit->customer)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('credits.show', $credit->customer) }}">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $credits->links() }}
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

@push('js')
<script>
$(document).ready(function() {
    if ($('#creditsTable').length) {
        $('#creditsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [5] },
                { searchable: false, targets: [5] }
            ],
            order: [[5, 'desc']]
        });
    }
});
</script>

<!-- Liability Account Configuration Modal -->
<div class="modal fade" id="liabilityAccountModal" tabindex="-1" role="dialog" aria-labelledby="liabilityAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="liabilityAccountModalLabel">
                    <i class="fas fa-cog"></i> Configure Customer Credit Liability Account
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('credits.liability-account.configure') }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> This account will be used as the liability account for all customer credit refunds in this branch. 
                        It should be a liability account (Account Class 2) that represents customer credit obligations.
                    </div>
                    
                    <div class="form-group">
                        <label for="chart_of_account_id">Select Liability Account <span class="text-danger">*</span></label>
                        <select name="chart_of_account_id" id="chart_of_account_id" class="form-control" required>
                            <option value="">Select a liability account...</option>
                            @php
                                $subshopId = session('subshop_id');

                                $liabilityAccounts = \App\Models\ChartsOfAccount::with('accountClass')
                                    ->where('subshop_id', $subshopId)
                                    ->whereHas('accountClass', function ($query) {
                                        $query->where('code', 2);
                                    })
                                    ->where('is_active', true)
                                    ->orderBy('account_name')
                                    ->get();
                            @endphp
                            @foreach($liabilityAccounts as $account)
                                <option value="{{ $account->id }}" 
                                    @if($liabilityAccount && $liabilityAccount->chart_of_account_id == $account->id) selected @endif>
                                    {{ $account->account_code }} - {{ $account->account_name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Only liability accounts (Account Class 2) are shown.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Any additional notes about this configuration...">{{ $liabilityAccount->notes ?? '' }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush
