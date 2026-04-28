@extends('adminlte::page')

@section('title', 'Loan Penalties - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-exclamation-triangle"></i> Loan Penalties</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-exclamation-triangle"></i> Penalties</h1>
                <p class="mb-0 text-light">Managing loan penalties for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('loans.loans_settings.index') }}">Loans settings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Loan Penalties</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary @if($incomeAccounts->isEmpty()) disabled @endif" 
            @if(!$incomeAccounts->isEmpty()) data-toggle="modal" data-target="#addLoanPenaltyModal" @endif
            @if($incomeAccounts->isEmpty()) title="No income accounts available" @endif>
        <i class="fas fa-plus"></i> New Penalty
    </button>
</div>
@stop

@section('content')
<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    
    @if($incomeAccounts->isEmpty())
        <div class="alert alert-warning">
            <h5><i class="fas fa-exclamation-triangle"></i> No Income Accounts Available</h5>
            <p>You need to create income accounts for this subshop before you can add loan penalties. Please contact your administrator to set up income accounts.</p>
        </div>
    @endif
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="loanPenaltyTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Penalty Type</th>
                            <th>Amount/Percentage</th>
                            <th>Grace Period</th>
                            <th>Frequency</th>
                            <th>Income Account</th>
                            <th>Receivable Account</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loanPenalties as $index => $penalty)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge badge-info">{{ $penalty->code }}</span></td>
                            <td>{{ $penalty->name }}</td>
                            <td>
                                <span class="badge {{ $penalty->penalty_type == 'FIXED' ? 'badge-primary' : 'badge-warning' }}">
                                    {{ $penalty->penalty_type }}
                                </span>
                            </td>
                            <td>
                                @if($penalty->penalty_type == 'FIXED')
                                    <span class="text-success">{{ number_format($penalty->amount, 2) }}</span>
                                @else
                                    <span class="text-info">{{ number_format($penalty->percentage, 2) }}%</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-secondary">{{ $penalty->grace_period_days }} days</span>
                            </td>
                            <td>
                                <span class="badge badge-light">{{ $penalty->frequency ?? 'once' }}</span>
                            </td>
                            <td>
                                <small class="text-muted">{{ $penalty->incomeAccount->account_name ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <small class="text-muted">{{ $penalty->receivableAccount->account_name ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $penalty->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $penalty->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" 
                                        data-id="{{ $penalty->id }}"
                                        data-name="{{ $penalty->name }}"
                                        data-code="{{ $penalty->code }}"
                                        data-penalty-type="{{ $penalty->penalty_type }}"
                                        data-amount="{{ $penalty->amount ?? '' }}"
                                        data-percentage="{{ $penalty->percentage ?? '' }}"
                                        data-grace-period-days="{{ $penalty->grace_period_days }}"
                                        data-frequency="{{ $penalty->frequency ?? 'once' }}"
                                        data-income-account-id="{{ $penalty->income_account_id }}"
                                        data-receivable-account-id="{{ $penalty->receivable_account_id }}"
                                        data-is-active="{{ $penalty->is_active }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $penalty->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center">No loan penalties found. Click 'New Penalty' to add one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Loan Penalty Modal -->
<div class="modal fade" id="addLoanPenaltyModal" tabindex="-1" role="dialog" aria-labelledby="addLoanPenaltyModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addLoanPenaltyForm" action="{{ route('loans.loan_penalties.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addLoanPenaltyModalLabel">Add New Loan Penalty</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required 
                               placeholder="e.g. LATE, OVERDUE, BOUNCE">
                    </div>
                    <div class="form-group">
                        <label for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g. Late Payment Penalty, Overdue Penalty">
                    </div>
                    <div class="form-group">
                        <label for="penalty_type">Penalty Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="penalty_type" name="penalty_type" required>
                            <option value="">Select Penalty Type</option>
                            <option value="FIXED">Fixed Amount</option>
                            <option value="DAILY_PERCENTAGE">Daily Percentage</option>
                        </select>
                    </div>
                    <div class="form-group" id="amount_group">
                        <label for="amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group" id="percentage_group" style="display: none;">
                        <label for="percentage">Percentage <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="percentage" name="percentage" 
                               step="0.01" min="0" max="100" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="grace_period_days">Grace Period Days <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="grace_period_days" name="grace_period_days" 
                               min="0" value="0" placeholder="Number of days before penalty applies">
                        <small class="form-text text-muted">Number of days before penalty starts being applied</small>
                    </div>
                    <div class="form-group">
                        <label for="frequency">Frequency <span class="text-danger">*</span></label>
                        <select class="form-control" id="frequency" name="frequency" required>
                            <option value="once" selected>Once</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="per_installment">Per Installment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="income_account_id">Income Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="income_account_id" name="income_account_id" required>
                            <option value="">Select Income Account</option>
                            @foreach($incomeAccountsOnly as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="receivable_account_id">Receivable Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="receivable_account_id" name="receivable_account_id" required>
                            <option value="">Select Receivable Account</option>
                            @foreach($receivableAccountsOnly as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Loan Penalty Modal -->
<div class="modal fade" id="editLoanPenaltyModal" tabindex="-1" role="dialog" aria-labelledby="editLoanPenaltyModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editLoanPenaltyForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editLoanPenaltyModalLabel">Edit Loan Penalty</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_code" name="code" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_penalty_type">Penalty Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_penalty_type" name="penalty_type" required>
                            <option value="">Select Penalty Type</option>
                            <option value="FIXED">Fixed Amount</option>
                            <option value="DAILY_PERCENTAGE">Daily Percentage</option>
                        </select>
                    </div>
                    <div class="form-group" id="edit_amount_group">
                        <label for="edit_amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_amount" name="amount" 
                               step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group" id="edit_percentage_group" style="display: none;">
                        <label for="edit_percentage">Percentage <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_percentage" name="percentage" 
                               step="0.01" min="0" max="100" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="edit_grace_period_days">Grace Period Days <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_grace_period_days" name="grace_period_days" 
                               min="0" placeholder="Number of days before penalty applies">
                        <small class="form-text text-muted">Number of days before penalty starts being applied</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_frequency">Frequency <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_frequency" name="frequency" required>
                            <option value="once">Once</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="per_installment">Per Installment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_income_account_id">Income Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_income_account_id" name="income_account_id" required>
                            <option value="">Select Income Account</option>
                            @foreach($incomeAccountsOnly as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_receivable_account_id">Receivable Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_receivable_account_id" name="receivable_account_id" required>
                            <option value="">Select Receivable Account</option>
                            @foreach($receivableAccountsOnly as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">

<style>
    .table th, .table td {
        vertical-align: middle;
    }
    .action-buttons {
        white-space: nowrap;
    }
</style>
@endpush




@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#loanPenaltyTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [0, 10] }, // Disable sorting on action column
            { searchable: false, targets: [0, 9, 10] } // Disable search on action and status columns
        ],
        order: [[1, 'asc']] // Sort by code by default
    });

    // Handle penalty type change for add form
    $('#penalty_type').change(function() {
        var penaltyType = $(this).val();
        console.log('Penalty type changed to:', penaltyType);
        
        if (penaltyType === 'FIXED') {
            $('#amount_group').show();
            $('#percentage_group').hide();
            $('#amount').prop('required', true);
            $('#percentage').prop('required', false);
            console.log('Showing amount field, hiding percentage field');
        } else if (penaltyType === 'DAILY_PERCENTAGE') {
            $('#amount_group').hide();
            $('#percentage_group').show();
            $('#amount').prop('required', false);
            $('#percentage').prop('required', true);
            console.log('Showing percentage field, hiding amount field');
        } else {
            $('#amount_group').hide();
            $('#percentage_group').hide();
            $('#amount').prop('required', false);
            $('#percentage').prop('required', false);
            console.log('Hiding both amount and percentage fields');
        }
    });

    // Debug: Add form submission handler
    $('#addLoanPenaltyForm').submit(function(e) {
        console.log('Form submitted');
        console.log('Form data:', $(this).serialize());
        
        // Check if required fields are filled
        var penaltyType = $('#penalty_type').val();
        var amount = $('#amount').val();
        var percentage = $('#percentage').val();
        var gracePeriodDays = $('#grace_period_days').val();
        
        console.log('Penalty type:', penaltyType);
        console.log('Amount:', amount);
        console.log('Percentage:', percentage);
        console.log('Grace period days:', gracePeriodDays);
        
        if (penaltyType === 'FIXED' && !amount) {
            console.error('Amount is required for FIXED penalty type');
            e.preventDefault();
            alert('Amount is required for Fixed penalty type');
            return false;
        }
        
        if (penaltyType === 'DAILY_PERCENTAGE' && !percentage) {
            console.error('Percentage is required for DAILY_PERCENTAGE penalty type');
            e.preventDefault();
            alert('Percentage is required for Daily Percentage penalty type');
            return false;
        }
        
        if (!gracePeriodDays || gracePeriodDays < 0) {
            console.error('Grace period days is required and must be >= 0');
            e.preventDefault();
            alert('Grace period days is required and must be 0 or greater');
            return false;
        }
        
        return true;
    });

    // Handle penalty type change for edit form
    $('#edit_penalty_type').change(function() {
        var penaltyType = $(this).val();
        if (penaltyType === 'FIXED') {
            $('#edit_amount_group').show();
            $('#edit_percentage_group').hide();
            $('#edit_amount').prop('required', true);
            $('#edit_percentage').prop('required', false);
        } else if (penaltyType === 'DAILY_PERCENTAGE') {
            $('#edit_amount_group').hide();
            $('#edit_percentage_group').show();
            $('#edit_amount').prop('required', false);
            $('#edit_percentage').prop('required', true);
        } else {
            $('#edit_amount_group').hide();
            $('#edit_percentage_group').hide();
            $('#edit_amount').prop('required', false);
            $('#edit_percentage').prop('required', false);
        }
    });

    // Handle edit button click
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var penaltyType = $(this).data('penalty-type');
        var amount = $(this).data('amount');
        var percentage = $(this).data('percentage');
        var gracePeriodDays = $(this).data('grace-period-days');
        var frequency = $(this).data('frequency');
        var incomeAccountId = $(this).data('income-account-id');
        var receivableAccountId = $(this).data('receivable-account-id');
        var isActive = $(this).data('is-active');
        
        $('#editLoanPenaltyForm').attr('action', '/loans/loans_settings/loan_penalties/' + id);
        $('#edit_code').val(code);
        $('#edit_name').val(name);
        $('#edit_penalty_type').val(penaltyType).trigger('change');
        $('#edit_amount').val(amount);
        $('#edit_percentage').val(percentage);
        $('#edit_grace_period_days').val(gracePeriodDays);
        $('#edit_frequency').val(frequency || 'once');
        $('#edit_income_account_id').val(incomeAccountId);
        $('#edit_receivable_account_id').val(receivableAccountId);
        $('#edit_is_active').prop('checked', isActive);
        
        $('#editLoanPenaltyModal').modal('show');
    });

    // Handle delete button click
    $('.delete-btn').click(function() {
        var id = $(this).data('id');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/loans/loans_settings/loan_penalties/' + id,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.message) {
                            Swal.fire(
                                'Deleted!',
                                response.message,
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                'Unexpected response format',
                                'error'
                            );
                        }
                    },
                    error: function(xhr) {
                        Swal.fire(
                            'Error!',
                            'An error occurred while deleting the loan penalty.',
                            'error'
                        );
                    }
                });
            }
        });
    });

    // Show success/error messages
    @if(session('success'))
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '{{ session('success') }}',
        showConfirmButton: true,
        timerProgressBar: true,
        timer: 3000
    });
    @endif

    @if(session('error'))
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '{{ session('error') }}',
        showConfirmButton: true

    });
    @endif
});
</script>
@endpush