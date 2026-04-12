@extends('adminlte::page')

@section('title', 'Payment Method Accounts - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-credit-card"></i> Payment Method Accounts</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-credit-card"></i> Payment Method Accounts</h1>
                <p class="mb-0 text-light">Managing payment method mappings for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('accounting.accounting_settings.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('accounting.accounting_settings.index') }}">Accounting settings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Payment Method Accounts</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPaymentMethodAccountModal">
        <i class="fas fa-plus"></i> New Mapping
    </button>
</div>
@stop

@section('content')
<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle"></i> Error</h5>
            <p class="mb-0">{{ session('error') }}</p>
        </div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">
            <h5><i class="fas fa-check-circle"></i> Success</h5>
            <p class="mb-0">{{ session('success') }}</p>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="paymentMethodAccountsTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Payment Method</th>
                            <th>GL Account</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paymentMethodAccounts as $index => $mapping)
                            @php
                                $allAccounts = $assetsAccounts->concat($liabilityAccounts);
                                $acc = $allAccounts->firstWhere('id', $mapping->chart_of_account_id);
                                $accLabel = $acc ? (($acc->account_code ?? '') . ' - ' . ($acc->account_name ?? '')) : 'N/A';
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><span class="badge badge-secondary">{{ $mapping->subshop->name }}</span></td>
                                <td><span class="badge badge-info">{{ $mapping->payment_method }}</span></td>
                                <td>{{ $accLabel }}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-btn"
                                            data-id="{{ $mapping->id }}"
                                            data-payment-method="{{ $mapping->payment_method }}"
                                            data-chart-of-account-id="{{ $mapping->chart_of_account_id }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $mapping->id }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No payment method accounts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Mapping Modal -->
<div class="modal fade" id="addPaymentMethodAccountModal" tabindex="-1" role="dialog" aria-labelledby="addPaymentMethodAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addPaymentMethodAccountForm" action="{{ route('accounting.payment_method_accounts.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addPaymentMethodAccountModalLabel">Add Payment Method Mapping</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="" disabled selected>-- Select Payment Method --</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="azampay">AzamPay</option>
                            <option value="savings">Savings</option>
                            <option value="customer_credit">Customer Credit</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="chart_of_account_id">GL Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="chart_of_account_id" name="chart_of_account_id" required>
                            <option value="" disabled selected>-- Select GL Account --</option>
                            @foreach($assetsAccounts as $account)
                                <option value="{{ $account->id }}" data-account-class-code="1">
                                    {{ $account->account_code ?? '' }} - {{ $account->account_name ?? '' }} (Class: {{ $account->accountClass->name }})
                                </option>
                            @endforeach
                            @foreach($liabilityAccounts as $account)
                                <option value="{{ $account->id }}" data-account-class-code="2">
                                    {{ $account->account_code ?? '' }} - {{ $account->account_name ?? '' }}  (Class: {{ $account->accountClass->name }})
                                </option>
                            @endforeach
                        </select>
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

<!-- Edit Mapping Modal -->
<div class="modal fade" id="editPaymentMethodAccountModal" tabindex="-1" role="dialog" aria-labelledby="editPaymentMethodAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editPaymentMethodAccountForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editPaymentMethodAccountModalLabel">Edit Payment Method Mapping</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_payment_method">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_payment_method" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="azampay">AzamPay</option>
                            <option value="savings">Savings</option>
                            <option value="customer_credit">Customer Credit</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_chart_of_account_id">GL Account <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_chart_of_account_id" name="chart_of_account_id" required>
                            <option value="" disabled selected>-- Select GL Account --</option>
                            @foreach($assetsAccounts as $account)
                                <option value="{{ $account->id }}" data-account-class-code="1">
                                    {{ $account->account_code ?? '' }} - {{ $account->account_name ?? '' }} (Class: {{ $account->accountClass->name }})
                                </option>
                            @endforeach
                            @foreach($liabilityAccounts as $account)
                                <option value="{{ $account->id }}" data-account-class-code="2">
                                    {{ $account->account_code ?? '' }} - {{ $account->account_name ?? '' }} (Class: {{ $account->accountClass->name }})
                                </option>
                            @endforeach
                        </select>
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
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    if ($('#paymentMethodAccountsTable').length) {
        $('#paymentMethodAccountsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [0, 4] },
                { searchable: false, targets: [0, 4] }
            ],
            order: [[2, 'asc']]
        });
    }

    function requiredAccountClassCodeForPaymentMethod(pm) {
        pm = String(pm || '').toLowerCase().trim();
        if (pm === 'savings' || pm === 'customer_credit') return '2';
        return '1';
    }

    function filterAccountOptions(selectEl, expectedCode) {
        if (!selectEl) return;

        if (!selectEl._allOptions) {
            selectEl._allOptions = Array.from(selectEl.querySelectorAll('option')).map(o => o.cloneNode(true));
        }

        const currentValue = String(selectEl.value || '');
        selectEl.innerHTML = '';

        selectEl._allOptions.forEach(opt => {
            const classCode = opt.getAttribute('data-account-class-code');
            if (!classCode) {
                selectEl.appendChild(opt.cloneNode(true));
                return;
            }
            if (String(classCode) === String(expectedCode)) {
                selectEl.appendChild(opt.cloneNode(true));
            }
        });

        if (currentValue) {
            const stillExists = !!selectEl.querySelector(`option[value="${currentValue}"]`);
            selectEl.value = stillExists ? currentValue : '';
        }
    }

    function setupAccountFiltering(paymentMethodSelectId, coaSelectId) {
        const pmEl = document.getElementById(paymentMethodSelectId);
        const coaEl = document.getElementById(coaSelectId);
        if (!pmEl || !coaEl) return;

        const apply = () => {
            const expected = requiredAccountClassCodeForPaymentMethod(pmEl.value);
            filterAccountOptions(coaEl, expected);
        };

        $(pmEl).on('change', apply);
        apply();
    }

    setupAccountFiltering('payment_method', 'chart_of_account_id');
    setupAccountFiltering('edit_payment_method', 'edit_chart_of_account_id');

    $('#addPaymentMethodAccountModal').on('shown.bs.modal', function() {
        const pmEl = document.getElementById('payment_method');
        const coaEl = document.getElementById('chart_of_account_id');
        if (!pmEl || !coaEl) return;
        const expected = requiredAccountClassCodeForPaymentMethod(pmEl.value);
        filterAccountOptions(coaEl, expected);
    });

    $('#editPaymentMethodAccountModal').on('shown.bs.modal', function() {
        const pmEl = document.getElementById('edit_payment_method');
        const coaEl = document.getElementById('edit_chart_of_account_id');
        if (!pmEl || !coaEl) return;
        const expected = requiredAccountClassCodeForPaymentMethod(pmEl.value);
        filterAccountOptions(coaEl, expected);
    });

    $(document).on('click', '.edit-btn', function() {
        var id = $(this).data('id');
        var paymentMethod = $(this).data('payment-method');
        var coaId = $(this).data('chart-of-account-id');

        $('#editPaymentMethodAccountForm').attr('action', '/accounting/payment_method_accounts/' + id);
        $('#edit_payment_method').val(paymentMethod);
        $('#edit_chart_of_account_id').val(coaId);

        var expected = requiredAccountClassCodeForPaymentMethod(paymentMethod);
        filterAccountOptions(document.getElementById('edit_chart_of_account_id'), expected);

        $('#editPaymentMethodAccountModal').modal('show');
    });

    $(document).on('click', '.delete-btn', function() {
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
                    url: '/accounting/payment_method_accounts/' + id,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
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
                                response.message,
                                'error'
                            );
                        }
                    },
                    error: function() {
                        Swal.fire(
                            'Error!',
                            'An error occurred while deleting the mapping.',
                            'error'
                        );
                    }
                });
            }
        });
    });

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
