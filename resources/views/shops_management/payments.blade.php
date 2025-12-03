@extends('adminlte::page')

@section('title', 'Plans & Payment Methods Management')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body text-center">
            <h1 class="d-none d-md-block text-light"><i class="fas fa-credit-card text-warning"></i> <strong>DB</strong> Plans & Payment Methods Management Panel</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-credit-card text-warning"></i> <strong>DB</strong> Plans & Payment Methods</h1>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active text-dark d-none d-md-inline" aria-current="page">Plans & Payment Methods Management Panel</li>
                <li class="breadcrumb-item active text-dark d-md-none" aria-current="page">Plans & Payment Methods</li>
            </ol>
        </nav>

    </div>
@stop
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


@section('content')
    <!-- Success Message -->

<script>
    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: "{{ session('success') }}",
            showConfirmButton: false,
            timer: 2500
        });
    @endif

    @if (session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: "{{ session('error') }}",
            showConfirmButton: true
        });
    @endif

    @if (session('warning'))
        Swal.fire({
            icon: 'warning',
            title: 'Angalizo!',
            text: "{{ session('warning') }}",
            showConfirmButton: true
        });
    @endif

    @if (session('info'))
        Swal.fire({
            icon: 'info',
            title: 'Taarifa',
            text: "{{ session('info') }}",
            showConfirmButton: false,
            timer: 2500
        });
    @endif
</script>



    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                <button type="button" class="btn btn-success btn-lg btn-sm m-2" data-toggle="modal" data-target="#createPlanModal">
                    <i class="fas fa-plus-circle"></i> Create Plan
                </button>
                <button type="button" class="btn btn-info btn-lg btn-sm m-2" data-toggle="modal" data-target="#createPaymentMethodModal">
                    <i class="fas fa-credit-card"></i> Create Payment Method
                </button>
            </div>
        </div>
    </div>

    <!-- Create Plan Modal -->
    <div class="modal fade" id="createPlanModal" tabindex="-1" role="dialog" aria-labelledby="createPlanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPlanModalLabel"><i class="fas fa-plus-circle"></i> Create New Plan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('plans.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="plan_name">Plan Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="plan_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="plan_slug">Slug <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="plan_slug" name="slug" required>
                                    <small class="form-text text-muted">URL-friendly identifier (e.g., basic-plan)</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="plan_description">Description</label>
                            <textarea class="form-control" id="plan_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="plan_price">Price <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="plan_price" name="price" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="plan_currency">Currency <span class="text-danger">*</span></label>
                                    <select class="form-control" id="plan_currency" name="currency" required>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="KES">KES</option>
                                        <option value="TZS">TZS</option>
                                        <option value="UGX">UGX</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="plan_billing_cycle">Billing Cycle <span class="text-danger">*</span></label>
                                    <select class="form-control" id="plan_billing_cycle" name="billing_cycle" required>
                                        <option value="monthly">Monthly</option>
                                        <option value="2_months">2 Months</option>
                                        <option value="3_months">3 Months</option>
                                        <option value="6_months">6 Months</option>
                                        <option value="yearly">Yearly</option>
                                        <option value="one_time">One Time</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="plan_status">Status <span class="text-danger">*</span></label>
                                    <select class="form-control" id="plan_status" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="plan_sort_order">Sort Order</label>
                                    <input type="number" class="form-control" id="plan_sort_order" name="sort_order" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="plan_is_popular" name="is_popular" value="1">
                                <label class="custom-control-label" for="plan_is_popular">Mark as Popular Plan</label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="plan_features">Features (JSON)</label>
                                    <textarea class="form-control" id="plan_features" name="features" rows="3" placeholder='["Feature 1", "Feature 2", "Feature 3"]'></textarea>
                                    <small class="form-text text-muted">Enter features as JSON array</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="plan_limits">Limits (JSON)</label>
                                    <textarea class="form-control" id="plan_limits" name="limits" rows="3" placeholder='{"max_subshops": 5, "max_users": 10}'></textarea>
                                    <small class="form-text text-muted">Enter limits as JSON object</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Payment Method Modal -->
    <div class="modal fade" id="createPaymentMethodModal" tabindex="-1" role="dialog" aria-labelledby="createPaymentMethodModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPaymentMethodModalLabel"><i class="fas fa-credit-card"></i> Create Payment Method</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('payment-methods.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="payment_method_name">Payment Method Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="payment_method_name" name="name" required>
                            <small class="form-text text-muted">e.g., Credit Card, PayPal, M-Pesa, etc.</small>
                        </div>
                        <div class="form-group">
                            <label for="payment_method_description">Description</label>
                            <textarea class="form-control" id="payment_method_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="payment_method_status">Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="payment_method_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="payment_method_code">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="payment_method_code" name="code" required>
                            <small class="form-text text-muted">Unique code for the payment method (e.g., card, paypal, mpesa)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Create Payment Method</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Plan Modal -->
    <div class="modal fade" id="editPlanModal" tabindex="-1" role="dialog" aria-labelledby="editPlanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPlanModalLabel"><i class="fas fa-edit"></i> Edit Plan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editPlanForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_plan_name">Plan Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_plan_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_plan_slug">Slug <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_plan_slug" name="slug" required>
                                    <small class="form-text text-muted">URL-friendly identifier (e.g., basic-plan)</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_plan_description">Description</label>
                            <textarea class="form-control" id="edit_plan_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_plan_price">Price <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="edit_plan_price" name="price" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_plan_currency">Currency <span class="text-danger">*</span></label>
                                    <select class="form-control" id="edit_plan_currency" name="currency" required>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="KES">KES</option>
                                        <option value="TZS">TZS</option>
                                        <option value="UGX">UGX</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_plan_billing_cycle">Billing Cycle <span class="text-danger">*</span></label>
                                    <select class="form-control" id="edit_plan_billing_cycle" name="billing_cycle" required>
                                        <option value="monthly">Monthly</option>
                                        <option value="2_months">2 Months</option>
                                        <option value="3_months">3 Months</option>
                                        <option value="6_months">6 Months</option>
                                        <option value="yearly">Yearly</option>
                                        <option value="one_time">One Time</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_plan_status">Status <span class="text-danger">*</span></label>
                                    <select class="form-control" id="edit_plan_status" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_plan_sort_order">Sort Order</label>
                                    <input type="number" class="form-control" id="edit_plan_sort_order" name="sort_order" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="edit_plan_is_popular" name="is_popular" value="1">
                                <label class="custom-control-label" for="edit_plan_is_popular">Mark as Popular Plan</label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_plan_features">Features (JSON)</label>
                                    <textarea class="form-control" id="edit_plan_features" name="features" rows="3" placeholder='["Feature 1", "Feature 2", "Feature 3"]'></textarea>
                                    <small class="form-text text-muted">Enter features as JSON array</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_plan_limits">Limits (JSON)</label>
                                    <textarea class="form-control" id="edit_plan_limits" name="limits" rows="3" placeholder='{"max_subshops": 5, "max_users": 10}'></textarea>
                                    <small class="form-text text-muted">Enter limits as JSON object</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Payment Method Modal -->
    <div class="modal fade" id="editPaymentMethodModal" tabindex="-1" role="dialog" aria-labelledby="editPaymentMethodModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPaymentMethodModalLabel"><i class="fas fa-edit"></i> Edit Payment Method</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editPaymentMethodForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_payment_method_name">Payment Method Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_payment_method_name" name="name" required>
                            <small class="form-text text-muted">e.g., Credit Card, PayPal, M-Pesa, etc.</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_payment_method_description">Description</label>
                            <textarea class="form-control" id="edit_payment_method_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_payment_method_status">Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_payment_method_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_payment_method_code">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_payment_method_code" name="code" required>
                            <small class="form-text text-muted">Unique code for the payment method (e.g., card, paypal, mpesa)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Update Payment Method</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Plans Management Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                    <h5 class="mb-0"><i class="fas fa-crown"></i> Plans Management</h5>
                </div>
                <div class="card-body">
                    @if($plans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Cycle</th>
                                        <th>Status</th>
                                        <th>Popular</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($plans as $plan)
                                        <tr>
                                            <td>
                                                <strong>{{ $plan->name }}</strong>
                                                <br><small class="text-muted">{{ $plan->slug }}</small>
                                            </td>
                                            <td>{{ $plan->formatted_price }}</td>
                                            <td><span class="badge badge-info">{{ $plan->billing_cycle_label }}</span></td>
                                            <td>
                                                <span class="badge badge-{{ $plan->status === 'active' ? 'success' : ($plan->status === 'inactive' ? 'secondary' : 'warning') }}">
                                                    {{ ucfirst($plan->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($plan->is_popular)
                                                    <i class="fas fa-star text-warning"></i> Yes
                                                @else
                                                    No
                                                @endif
                                            </td>
                                            <td>{{ $plan->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editPlan({{ $plan->id }})">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form id="deletePlanForm{{ $plan->id }}" action="{{ route('plans.destroy', $plan) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-plan-btn" data-plan-id="{{ $plan->id }}" data-plan-name="{{ $plan->name }}">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-crown fa-3x text-muted mb-3"></i>
                            <h5>No plans registered yet</h5>
                            <p class="text-muted">Create your first plan using the button above.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Methods Management Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #007bff, #6610f2); color: white;">
                    <h5 class="mb-0"><i class="fas fa-credit-card"></i> Payment Methods Management</h5>
                </div>
                <div class="card-body">
                    @if($paymentMethods->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Name</th>
                                        <th>Code</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($paymentMethods as $method)
                                        <tr>
                                            <td><strong>{{ $method->name }}</strong></td>
                                            <td><code>{{ $method->code }}</code></td>
                                            <td>{{ Str::limit($method->description, 50) ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge badge-{{ $method->status ? 'success' : 'secondary' }}">
                                                    {{ $method->status ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td>{{ $method->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editPaymentMethod({{ $method->id }})">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form id="deletePaymentMethodForm{{ $method->id }}" action="{{ route('payment-methods.destroy', $method) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-payment-method-btn" data-method-id="{{ $method->id }}" data-method-name="{{ $method->name }}">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                            <h5>No payment methods registered yet</h5>
                            <p class="text-muted">Create your first payment method using the button above.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination for Plans -->
    @if($plans->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $plans->links() }}
    </div>
    @endif


    <!-- Pagination for Payment Methods -->
    @if($paymentMethods->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $paymentMethods->links() }}
    </div>
    @endif


@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush


@section('js')
<script>
    // Auto-hide alerts after 5 seconds
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });

    // Plans data for editing
    const plansData = @json($plans->getCollection()->toArray());

    // Payment methods data for editing
    const paymentMethodsData = @json($paymentMethods->getCollection()->toArray());

    // Edit Plan function
    function editPlan(planId) {
        const plan = plansData.find(p => p.id == planId);
        if (!plan) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Plan not found!'
            });
            return;
        }

        // Populate form fields
        $('#edit_plan_name').val(plan.name);
        $('#edit_plan_slug').val(plan.slug);
        $('#edit_plan_description').val(plan.description || '');
        $('#edit_plan_price').val(plan.price);
        $('#edit_plan_currency').val(plan.currency);
        $('#edit_plan_billing_cycle').val(plan.billing_cycle);
        $('#edit_plan_status').val(plan.status);
        $('#edit_plan_sort_order').val(plan.sort_order || 0);
        $('#edit_plan_is_popular').prop('checked', plan.is_popular);

        // Handle JSON fields
        $('#edit_plan_features').val(plan.features ? JSON.stringify(plan.features, null, 2) : '');
        $('#edit_plan_limits').val(plan.limits ? JSON.stringify(plan.limits, null, 2) : '');

        // Update form action
        $('#editPlanForm').attr('action', `/plans/${planId}`);

        // Show modal
        $('#editPlanModal').modal('show');
    }

    // Edit Payment Method function
    function editPaymentMethod(methodId) {
        const method = paymentMethodsData.find(m => m.id == methodId);
        if (!method) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Payment method not found!'
            });
            return;
        }

        // Populate form fields
        $('#edit_payment_method_name').val(method.name);
        $('#edit_payment_method_code').val(method.code);
        $('#edit_payment_method_description').val(method.description || '');
        $('#edit_payment_method_status').val(method.status ? 'active' : 'inactive');

        // Update form action
        $('#editPaymentMethodForm').attr('action', `/payment-methods/${methodId}`);

        // Show modal
        $('#editPaymentMethodModal').modal('show');
    }

    // Delete Plan with SweetAlert
    $(document).on('click', '.delete-plan-btn', function() {
        const planId = $(this).data('plan-id');
        const planName = $(this).data('plan-name');

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete the plan "${planName}". This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $(`#deletePlanForm${planId}`).submit();
            }
        });
    });

    // Delete Payment Method with SweetAlert
    $(document).on('click', '.delete-payment-method-btn', function() {
        const methodId = $(this).data('method-id');
        const methodName = $(this).data('method-name');

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete the payment method "${methodName}". This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $(`#deletePaymentMethodForm${methodId}`).submit();
            }
        });
    });
</script>
@stop