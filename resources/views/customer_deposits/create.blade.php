@extends('adminlte::page')

@section('title', 'Create Deposit Account')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-plus"></i> Create Deposit Account</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-plus"></i> Create Deposit Account</h1>
                    <p class="mb-0 text-light">Open a new savings account for a customer</p>
                </div>
                <a href="{{ route('deposits.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('deposits.index') }}">Customer Deposit Accounts</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><strong>New Deposit Account</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('deposits.store') }}">
                            @csrf

                            <div class="form-group">
                                <label for="customer_id">Customer</label>
                                <select name="customer_id" id="customer_id" class="form-control select2" required>
                                    <option value="">Select customer</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="deposit_product_id">Deposit Product</label>
                                <select name="deposit_product_id" id="deposit_product_id" class="form-control select2" required>
                                    <option value="">Select product</option>
                                    @foreach($depositProducts as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} – {{ ucfirst($p->type) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="account_number">Account Number (optional)</label>
                                <input type="text" name="account_number" id="account_number" class="form-control" placeholder="Leave blank to auto-generate">
                                <small class="text-muted">If left blank, the system will generate a unique account number.</small>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Create Account
                                </button>
                                <a href="{{ route('deposits.index') }}" class="btn btn-light ml-2">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
@push('css')
<link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.css') }}">
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
    .select2-container--default .select2-selection--single {
        height: calc(2.25rem + 2px);
        padding: .375rem .75rem;
        border: 1px solid #ced4da;
        border-radius: .25rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
        padding-left: 0;
        padding-right: 0;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem + 2px);
        top: 0;
        right: 4px;
    }

    .select2-container--default .select2-selection--multiple {
        min-height: calc(2.25rem + 2px);
        border: 1px solid #ced4da;
        border-radius: .25rem;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        padding: .375rem .75rem;
    }
</style>
@endpush

@push('js')
<script src="{{ asset('vendor/select2/js/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    if (window.jQuery && $.fn && $.fn.select2) {
        $('#customer_id').select2({
            width: '100%',
            placeholder: 'Search customer by name',
            allowClear: true
        });

        $('#deposit_product_id').select2({
            width: '100%',
            placeholder: 'Search deposit product',
            allowClear: true
        });
    }

    // Optional: Auto-generate account number preview if left blank
    $('#account_number').on('blur', function() {
        const val = $(this).val().trim();
        if (!val) {
            // Simple preview format; actual generation will be server-side
            const preview = 'SAV' + new Date().getFullYear() + 'XXXX';
            $(this).attr('placeholder', 'Will be auto-generated, e.g., ' + preview);
        }
    });
});
</script>
@endpush
