@extends('adminlte::page')

@section('title', 'Banks - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-university"></i> Banks Management</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-university"></i> Banks</h1>
                    <p class="mb-0 text-light">Managing banks for: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('banks.subshops') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Change Shop
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('banks.subshops') }}">Choose Shop</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Banks</li>
            </ol>
        </nav>
    </div>
@stop



@section('content')
<div class="container-fluid">
    @if(isset($bankSummaryTotals))
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="mr-3 text-info"><i class="fas fa-piggy-bank fa-2x"></i></div>
                        <div>
                            <div class="text-muted small">Opening Balances</div>
                            <div class="h5 mb-0 text-info">TSh {{ number_format((float)($bankSummaryTotals['opening_total'] ?? 0), 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="mr-3 text-primary"><i class="fas fa-arrow-down fa-2x"></i></div>
                        <div>
                            <div class="text-muted small">Total Inflows</div>
                            <div class="h5 mb-0 text-primary">TSh {{ number_format((float)($bankSummaryTotals['inflow_total'] ?? 0), 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="mr-3 text-danger"><i class="fas fa-arrow-up fa-2x"></i></div>
                        <div>
                            <div class="text-muted small">Total Outflows</div>
                            <div class="h5 mb-0 text-danger">TSh {{ number_format((float)($bankSummaryTotals['outflow_total'] ?? 0), 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-wallet fa-2x"></i></div>
                        <div>
                            <div class="text-muted small">Total Current Balance</div>
                            <div class="h5 mb-0 text-success">TSh {{ number_format((float)($bankSummaryTotals['current_total'] ?? 0), 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    <!-- Search and Add Button -->
    <div class="row mb-3">
        <div class="col-md-6">
            <form method="GET" action="{{ route('banks.index') }}">
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search banks..." value="{{ request('search') }}">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-md-6 text-right">
            @can('add_banks')
            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addBankModal">
                <i class="fas fa-plus"></i> Add Bank
            </button>
            @endcan
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-8">
            <div class="form-row">
                <div class="col-sm-4 mb-2">
                    <input type="date" class="form-control" id="filter_date_from" placeholder="Date From">
                </div>
                <div class="col-sm-4 mb-2">
                    <input type="date" class="form-control" id="filter_date_to" placeholder="Date To">
                </div>
                <div class="col-sm-4 mb-2">
                    <select id="filter_bank_id" class="form-control">
                        <option value="">All Banks</option>
                        @foreach($banks as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" value="1" id="filter_include_pending">
                <label class="form-check-label" for="filter_include_pending">Include Pending Expenses</label>
            </div>
        </div>
        <div class="col-md-4 text-right">
            @can('export_banks')
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-file-export"></i> Export
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <h6 class="dropdown-header">Statement</h6>
                    <a class="dropdown-item" href="#" onclick="exportBank('statement','csv'); return false;">CSV</a>
                    <a class="dropdown-item" href="#" onclick="exportBank('statement','excel'); return false;">Excel</a>
                    <a class="dropdown-item" href="#" onclick="exportBank('statement','pdf'); return false;">PDF</a>
                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header">Performance</h6>
                    <a class="dropdown-item" href="#" onclick="exportBank('summary','csv'); return false;">CSV</a>
                    <a class="dropdown-item" href="#" onclick="exportBank('summary','excel'); return false;">Excel</a>
                    <a class="dropdown-item" href="#" onclick="exportBank('summary','pdf'); return false;">PDF</a>
                </div>
            </div>
            @endcan
        </div>
    </div>

    <!-- Banks Table -->
    <div class="card">
        <div class="card-body table-responsive p-3">
            <table class="table table-hover text-nowrap" id="banksTable">
                <thead class="thead-dark">
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Account Name</th>
                        <th>Account Number</th>
                        <th>Opening Balance</th>
                        <th>Current Balance</th>
                        <th>Branch</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody><?php $count = 1; ?>
                    @forelse($banks as $bank)
                        <tr>
                            <td>#<?= $count++ ?></td>
                            <td>{{ $bank->name }}</td>
                            <td>{{ $bank->account_name ?? 'N/A' }}</td>
                            <td>{{ $bank->account_number }}</td>
                            <td class="text-primary">TSh {{ number_format((float)($bank->opening_balance ?? 0), 2) }}</td>
                            <?php
                                $payments = isset($bankTotals) ? (float) ($bankTotals[$bank->name] ?? 0) : 0;
                                $currentBalance = (float)($bank->opening_balance ?? 0) + $payments;
                            ?>
                            <td class="text-success">TSh {{ number_format($currentBalance, 2) }}</td>
                            <td>{{ $bank->branch ?? 'N/A' }}</td>
                            <td>{{ $bank->email ?? 'N/A' }}</td>
                            <td>{{ $bank->phone ?? 'N/A' }}</td>
                            <td>{{ $bank->notes ? \Illuminate\Support\Str::limit($bank->notes, 30) : 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $bank->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $bank->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ $bank->created_at->format('d/m/Y') }}</td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary view-bank"
                                        data-id="{{ $bank->id }}"
                                        data-name="{{ $bank->name }}"
                                        data-account_name="{{ $bank->account_name }}"
                                        data-account_number="{{ $bank->account_number }}"
                                        data-branch="{{ $bank->branch }}"
                                        data-email="{{ $bank->email }}"
                                        data-phone="{{ $bank->phone }}"
                                        data-opening_balance="{{ number_format((float)($bank->opening_balance ?? 0), 2) }}"
                                        data-current_balance="{{ number_format($currentBalance, 2) }}"
                                        data-notes="{{ $bank->notes }}"
                                        data-created_at="{{ $bank->created_at->format('M d, Y') }}"
                                        data-status="{{ $bank->is_active ? 'Active' : 'Inactive' }}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    @can('edit_banks')
                                    <button type="button" class="btn btn-sm btn-info" title="Edit" data-toggle="modal" data-target="#editBankModal"
                                        data-id="{{ $bank->id }}"
                                        data-name="{{ $bank->name }}"
                                        data-account_name="{{ $bank->account_name }}"
                                        data-account_number="{{ $bank->account_number }}"
                                        data-branch="{{ $bank->branch }}"
                                        data-email="{{ $bank->email }}"
                                        data-phone="{{ $bank->phone }}"
                                        data-opening_balance="{{ (float)($bank->opening_balance ?? 0) }}"
                                        data-notes="{{ $bank->notes }}"
                                        data-active="{{ $bank->is_active ? '1' : '0' }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    @endcan
                                    @can('delete_banks')
                                    <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="deleteBank({{ $bank->id }}, '{{ $bank->name }}')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center">No banks found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $banks->appends(request()->query())->links() }}
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteBankForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- View Bank Modal -->
<div class="modal fade" id="viewBankModal" tabindex="-1" role="dialog" aria-labelledby="viewBankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewBankModalLabel">Bank Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">Bank Information</h5>
                        <table class="table table-sm">
                            <tr><th style="width: 40%;">Name:</th><td id="view-bank-name"></td></tr>
                            <tr><th>Account Name:</th><td id="view-account-name"></td></tr>
                            <tr><th>Account Number:</th><td id="view-account-number"></td></tr>
                            <tr><th>Branch:</th><td id="view-branch"></td></tr>
                            <tr><th>Opening Balance:</th><td id="view-opening-balance" class=" text-primary"></td></tr>
                            <tr><th>Current Balance:</th><td id="view-current-balance" class="text-success"></td></tr>
                            <tr><th>Email:</th><td id="view-email"></td></tr>
                            <tr><th>Phone:</th><td id="view-phone"></td></tr>
                            <tr><th>Notes:</th><td id="view-notes"></td></tr>
                            <tr><th>Status:</th><td><span class="badge" id="view-status"></span></td></tr>
                            <tr><th>Created:</th><td id="view-created-at"></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                @can('edit_banks')
                <button type="button" class="btn btn-primary" id="editBankBtn">
                    <i class="fas fa-edit"></i> Edit Bank
                </button>
                @endcan
            </div>
        </div>
    </div>
</div>

<!-- Add Bank Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1" role="dialog" aria-labelledby="addBankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('banks.store') }}" method="POST" id="addBankForm">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBankModalLabel">Add New Bank</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Bank Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="account_name">Account Name</label>
                                <input type="text" class="form-control" id="account_name" name="account_name">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="account_number">Account Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="account_number" name="account_number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="branch">Branch</label>
                                <input type="text" class="form-control" id="branch" name="branch">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="opening_balance">Opening Balance <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="opening_balance" name="opening_balance" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <input type="text" class="form-control" id="notes" name="notes" placeholder="Optional notes">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" checked>
                            <label class="custom-control-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Bank</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bank Modal -->
<div class="modal fade" id="editBankModal" tabindex="-1" role="dialog" aria-labelledby="editBankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="editBankForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editBankModalLabel">Edit Bank</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_name">Bank Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_account_name">Account Name</label>
                                <input type="text" class="form-control" id="edit_account_name" name="account_name">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_account_number">Account Number</label>
                                <input type="text" class="form-control" id="edit_account_number" name="account_number" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_branch">Branch</label>
                                <input type="text" class="form-control" id="edit_branch" name="branch">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_opening_balance">Opening Balance</label>
                                <input type="number" step="0.01" class="form-control" id="edit_opening_balance" name="opening_balance">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_notes">Notes</label>
                                <input type="text" class="form-control" id="edit_notes" name="notes">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_email">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_phone">Phone</label>
                                <input type="text" class="form-control" id="edit_phone" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="edit_is_active" name="is_active" value="1">
                            <label class="custom-control-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Bank</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
$(function () {
    $('#banksTable').DataTable({
        order: [[1, 'asc']],
        pageLength: 25,
        responsive: true
    });
});

function exportBank(view, format){
    var params = new URLSearchParams();
    params.set('view', view);
    var df = document.getElementById('filter_date_from').value;
    var dt = document.getElementById('filter_date_to').value;
    var bankId = document.getElementById('filter_bank_id').value;
    var includePending = document.getElementById('filter_include_pending').checked ? '1' : '0';
    if(df) params.set('date_from', df);
    if(dt) params.set('date_to', dt);
    if(bankId) params.set('bank_id', bankId);
    if(includePending === '1') params.set('include_pending', '1');
    var url = '{{ route('banks.export', ['format' => 'FMT']) }}'.replace('FMT', format) + '?' + params.toString();
    window.location.href = url;
}

// View Bank Modal Handler
$(document).on('click', '.view-bank', function() {
    var bankId = $(this).data('id');
    $('#view-bank-name').text($(this).data('name'));
    $('#view-account-name').text($(this).data('account_name') || 'N/A');
    $('#view-account-number').text($(this).data('account_number') || 'N/A');
    $('#view-branch').text($(this).data('branch') || 'N/A');
    $('#view-email').text($(this).data('email') || 'N/A');
    $('#view-phone').text($(this).data('phone') || 'N/A');
    $('#view-opening-balance').text($(this).data('opening_balance') ? 'TSh ' + $(this).data('opening_balance') : 'TSh 0.00');
    $('#view-current-balance').text($(this).data('current_balance') ? 'TSh ' + $(this).data('current_balance') : 'TSh 0.00');
    $('#view-notes').text($(this).data('notes') || 'N/A');
    $('#view-created-at').text($(this).data('created_at'));

    var status = $(this).data('status');
    var statusBadge = $('<span>').addClass('badge ' + (status === 'Active' ? 'badge-success' : 'badge-secondary')).text(status);
    $('#view-status').empty().append(statusBadge);

    $('#editBankBtn').off('click').on('click', function() {
        $('#viewBankModal').modal('hide');
        $('.view-bank').filter(function() { return $(this).data('id') === bankId; }).closest('.btn-group').find('.btn-info').click();
    });

    $('#viewBankModal').modal('show');
});

// Fill Edit Modal
$('#editBankModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var modal = $(this);
    modal.find('#editBankForm').attr('action', '{{ route('banks.update', ['bank' => 'BANK_ID']) }}'.replace('BANK_ID', button.data('id')));
    modal.find('#edit_name').val(button.data('name'));
    modal.find('#edit_account_name').val(button.data('account_name'));
    modal.find('#edit_account_number').val(button.data('account_number'));
    modal.find('#edit_branch').val(button.data('branch'));
    modal.find('#edit_email').val(button.data('email'));
    modal.find('#edit_phone').val(button.data('phone'));
    modal.find('#edit_opening_balance').val(button.data('opening_balance'));
    modal.find('#edit_notes').val(button.data('notes'));
    modal.find('#edit_is_active').prop('checked', button.data('active') == '1');
});

// Delete Bank
function deleteBank(id, name){
    Swal.fire({
        title: 'Delete Bank?',
        text: 'Are you sure you want to delete ' + name + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route('banks.destroy', ['bank' => 'BANK_ID']) }}'.replace('BANK_ID', id),
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