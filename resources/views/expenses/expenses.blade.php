@extends('adminlte::page')

@section('title', 'Expenses - ' . $subshop->name)
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

@section('content_header')
    <meta name="base-url" content="{{ url('/') }}">
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-receipt"></i> Expenses Management</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-receipt"></i> Expenses</h1>
                    <p class="mb-0 text-light">Managing expenses for: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('expenses.subshops') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Change Shop
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('expenses.subshops') }}">Choose Shop</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Expenses</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 col-12">
            <div class="callout callout-info">
                <h5><i class="fas fa-list mr-1"></i> Total Expenses</h5>
                <p class="h3 mb-0">{{ $expenses->total() }}</p>
                <small class="text-muted">All time records</small>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12">
            <div class="callout callout-warning">
                <h5><i class="far fa-clock mr-1"></i> Pending</h5>
                <p class="h3 mb-0">{{ $expenses->where('status','pending')->count() }}</p>
                <small class="text-muted">Awaiting review</small>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12">
            <div class="callout callout-success">
                <h5><i class="far fa-check-circle mr-1"></i> Approved</h5>
                <p class="h3 mb-0">{{ $expenses->where('status','approved')->count() }}</p>
                <small class="text-muted">Completed</small>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12">
            <div class="callout callout-danger">
                <h5><i class="far fa-times-circle mr-1"></i> Rejected</h5>
                <p class="h3 mb-0">{{ $expenses->where('status','rejected')->count() }}</p>
                <small class="text-muted">Not approved</small>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-receipt"></i> Expenses Management</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('expenses.index') }}" class="mb-3">
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="bg-light p-2 rounded border">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Search</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Title / Notes / Category">
                            </div>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Date To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Min Amount</label>
                            <input type="number" step="0.01" name="min_amount" value="{{ request('min_amount') }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Max Amount</label>
                            <input type="number" step="0.01" name="max_amount" value="{{ request('max_amount') }}" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="pending" {{ request('status')==='pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ request('status')==='approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('status')==='rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Category</label>
                            <select name="category" class="form-control">
                                <option value="">All</option>
                                @php $cats = ['Electricity','Food','Insurance','Rent','Salaries','Transport','Utilities','Waste','Water','Other']; @endphp
                                @foreach($cats as $c)
                                    <option value="{{ $c }}" {{ request('category')===$c ? 'selected' : '' }}>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Recorded By</label>
                            <input type="text" name="recorded_by" value="{{ request('recorded_by') }}" class="form-control" placeholder="User name">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Sort</label>
                            <select name="sort" class="form-control">
                                <option value="date_desc" {{ request('sort')==='date_desc' ? 'selected' : '' }}>Date: New → Old</option>
                                <option value="date_asc" {{ request('sort')==='date_asc' ? 'selected' : '' }}>Date: Old → New</option>
                                <option value="amount_desc" {{ request('sort')==='amount_desc' ? 'selected' : '' }}>Amount: High → Low</option>
                                <option value="amount_asc" {{ request('sort')==='amount_asc' ? 'selected' : '' }}>Amount: Low → High</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <button class="btn btn-primary mr-1" type="submit"><i class="fas fa-filter"></i> Apply</button>
                            <a class="btn btn-light border" href="{{ route('expenses.index', ['subshop_id'=>$subshop->id]) }}"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </form>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted small">Filtered results: {{ number_format($expenses->total()) }}</div>
                <div class="d-flex align-items-center">

                    @can('export_expenses')
                    <div class="dropdown mr-2">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="{{ route('expenses.export', ['format' => 'csv'] + request()->query()) }}">
                                <i class="fas fa-file-csv mr-1 text-success"></i> CSV
                            </a>
                            <a class="dropdown-item" href="{{ route('expenses.export', ['format' => 'excel'] + request()->query()) }}">
                                <i class="fas fa-file-excel mr-1 text-success"></i> Excel
                            </a>
                            <a class="dropdown-item" href="{{ route('expenses.export', ['format' => 'pdf'] + request()->query()) }}">
                                <i class="fas fa-file-pdf mr-1 text-danger"></i> PDF
                            </a>
                        </div>
                    </div>
                    @endcan
                    @can('add_expenses')
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addExpenseModal">
                        <i class="fas fa-plus"></i> Record Expense
                    </button>
                    @endcan
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Expenses List</h3></div>
                <div class="card-body p-2">
                    <div class="table-responsive" id="expensesTableWrapper" style="margin: 0 -1px">
                        <table id="expensesTable" class="table table-bordered table-hover table-striped m-0" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Shop</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th class="text-right">Amount</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Recorded By</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($expenses as $expense)
                                <tr class="{{ $expense->status === 'approved' ? 'table-success' : ($expense->status === 'pending' ? 'table-warning' : 'table-danger') }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td><i class="fas fa-store text-muted mr-1"></i> {{ $expense->subshop->name ?? 'N/A' }}</td>
                                    <td>{{ $expense->title }}</td>
                                    <td>{{ $expense->category ?? 'N/A' }}</td>
                                    <td class="text-right font-weight-bold text-danger">{{ number_format($expense->amount, 2) }}</td>
                                    <td>{{ $expense->paymentBank->name ?? '-' }}</td>
                                    <td><i class="far fa-calendar-alt text-muted mr-1"></i> {{ optional($expense->expense_date)->format('d/m/Y') ?? 'N/A' }}</td>
                                    <td><i class="fas fa-user text-muted mr-1"></i> {{ $expense->creator->name ?? 'System' }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $expense->status === 'approved' ? 'badge-success' : ($expense->status === 'pending' ? 'badge-warning' : 'badge-danger') }}" style="min-width: 80px;">
                                            <i class="fas {{ $expense->status === 'approved' ? 'fa-check-circle' : ($expense->status === 'pending' ? 'fa-clock' : 'fa-times-circle') }} mr-1"></i>
                                            {{ ucfirst($expense->status) }}
                                        </span>
                                        @if($expense->status !== 'pending' && $expense->reviewed)
                                            <div><small class="text-muted"><i class="fas fa-user-edit"></i> {{ $expense->reviewed->name }}</small></div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-info" title="View Details" data-toggle="modal" data-target="#viewExpenseModal"
                                                data-title="{{ $expense->title }}"
                                                data-subshop="{{ $expense->subshop->name ?? 'N/A' }}"
                                                data-category="{{ $expense->category ?? 'N/A' }}"
                                                data-amount="{{ number_format($expense->amount, 2) }}"
                                                data-payment-method="{{ $expense->paymentBank->name ?? '-' }}"
                                                data-date="{{ optional($expense->expense_date)->format('d/m/Y') ?? 'N/A' }}"
                                                data-recorded-by="{{ $expense->creator->name ?? 'System' }}"
                                                data-status="{{ $expense->status }}"
                                                data-notes="{{ $expense->description ?? 'No additional notes' }}">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            @if($expense->status === 'pending')
                                            @can('approve_expenses')
                                                <button class="btn btn-success" title="Approve" onclick="updateExpenseStatus({{ $expense->id }}, 'approved')"><i class="fas fa-check"></i></button>
                                            @endcan
                                            @can('reject_expenses')
                                                <button class="btn btn-warning" title="Reject" onclick="updateExpenseStatus({{ $expense->id }}, 'rejected')"><i class="fas fa-times"></i></button>
                                            @endcan
                                            @can('delete_expenses')
                                                <button type="button" class="btn btn-danger" title="Delete" onclick="deleteExpense({{ $expense->id }})"><i class="fas fa-trash"></i></button>
                                            @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center">
        {{ $expenses->appends(request()->query())->links() }}
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="expenseDeleteForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
    </form>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" role="dialog" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8, #0d7a8a); color: white;">
                <h5 class="modal-title" id="addExpenseModalLabel"><i class="fas fa-plus-circle"></i> Record Expense</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="expenseForm" action="{{ route('expenses.store') }}" method="POST">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="title">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="amount">Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="expense_date">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="expense_date" name="expense_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-control" id="payment_method" name="payment_method" required>
                                    <option value="">Select Payment Method</option>
                                    @foreach(($banks ?? []) as $b)
                                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Electricity">Electricity</option>
                                    <option value="Food">Food</option>
                                    <option value="Insurance">Insurance</option>
                                    <option value="Rent">Rent</option>
                                    <option value="Salaries">Salaries</option>
                                    <option value="Transport">Transport</option>
                                    <option value="Utilities">Utilities</option>
                                    <option value="Waste">Waste</option>
                                    <option value="Water">Water</option>
                                    <option value="Other">Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="description">Notes</label>
                                <input type="text" class="form-control" id="description" name="description" placeholder="Optional notes">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-save"></i> Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Expense Modal -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1" role="dialog" aria-labelledby="viewExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewExpenseModalLabel">Expense Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tr><th style="width: 40%;">Title:</th><td id="view-title"></td></tr>
                        <tr><th>Category:</th><td id="view-category"></td></tr>
                        <tr><th>Amount:</th><td id="view-amount"></td></tr>
                        <tr><th>Payment Method:</th><td id="view-payment-method"></td></tr>
                        <tr><th>Date:</th><td id="view-date"></td></tr>
                        <tr><th>Recorded By:</th><td id="view-recorded-by"></td></tr>
                        <tr><th>Status:</th><td id="view-status"></td></tr>
                        <tr><th>Notes:</th><td id="view-notes"></td></tr>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script> -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<script>
@if (session('success'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: "{{ session('success') }}",
            showConfirmButton: false,
            timer: 2500
        });
    });
@endif
$(document).ready(function() {
    var table = $('#expensesTable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            infoFiltered: '(filtered from _MAX_ total entries)',
            zeroRecords: 'No matching records found',
            paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' }
        },
        columnDefs: [
            { orderable: false, targets: [9] },
            { className: 'text-center', targets: [8] },
            { className: 'text-right', targets: [4] }
        ]
    });

    $('#viewExpenseModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        $('#view-title').text(button.data('title'));
        $('#view-category').text(button.data('category'));
        $('#view-amount').text('TZS ' + button.data('amount'));
        $('#view-date').text(button.data('date'));
        $('#view-payment-method').text(button.data('paymentMethod'));
        $('#view-recorded-by').text(button.data('recordedBy'));
        $('#view-status').text(button.data('status'));
        $('#view-notes').text(button.data('notes'));
    });

    // Show loading while recording a new expense (modal form submit)
    $('#expenseForm').on('submit', function(){
        try {
            if (window.Swal) {
                Swal.fire({
                    title: 'Recording expense...',
                    html: 'Please wait.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => { Swal.showLoading(); }
                });
            }
        } catch(e) {}
        // prevent double submit
        $(this).find('button[type="submit"]').prop('disabled', true);
    });
});

function updateExpenseStatus(id, status){
    Swal.fire({
        title: 'Confirm',
        text: 'Are you sure you want to ' + status + ' this expense?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes',
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route('expenses.updateStatus', ['expense' => 'EXPENSE_ID']) }}'.replace('EXPENSE_ID', id),
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', status: status },
                success: function(res){
                    Swal.fire('Updated', res.message, 'success').then(() => window.location.reload());
                },
                error: function(xhr){
                    Swal.fire('Error', xhr.responseJSON?.message || 'Failed to update', 'error');
                }
            });
        }
    });
}

function deleteExpense(id){
    Swal.fire({
        title: 'Delete Expense?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route('expenses.destroy', ['expense' => 'EXPENSE_ID']) }}'.replace('EXPENSE_ID', id),
                method: 'POST',
                data: { _method: 'DELETE', _token: '{{ csrf_token() }}' },
                success: function(res){
                    Swal.fire('Deleted', res.message, 'success').then(() => window.location.reload());
                },
                error: function(xhr){
                    Swal.fire('Error', xhr.responseJSON?.message || 'Failed to delete', 'error');
                }
            });
        }
    });
}
</script>
@stop