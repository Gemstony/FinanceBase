@extends('adminlte::page')

@section('title', 'Users Management')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-users-cog"></i> Users Management</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-users-cog"></i> Users</h1>
                    <p class="mb-0 text-light">Manage users, roles, and subshop assignments.</p>
                </div>
                <button class="btn btn-light" data-toggle="modal" data-target="#addUserModal">
                    <i class="fas fa-user-plus"></i> Add User
                </button> 
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">Users</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="card">
    <div class="card-body table-responsive p-3">
        <table class="table table-hover" id="usersTable">
            <thead class="thead-dark">
                <tr>
                    <th>No.</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Assigned Subshops</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $i=1; @endphp
                @foreach($users as $u)
                    <tr>
                        <td>#{{ $i++ }}</td>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->phone_number ?? '—' }}</td>
                        <td>{{ $u->getRoleNames()->first() ?? '—' }}</td>
                        <td>
                            @forelse($u->subshops as $ss)
                                <span class="badge badge-info mr-1 mb-1">{{ $ss->name }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info mr-1 view-btn"
                                data-id="{{ $u->id }}"
                                data-name="{{ $u->name }}">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-sm btn-warning mr-1 edit-btn"
                                data-id="{{ $u->id }}"
                                data-name="{{ $u->name }}"
                                data-email="{{ $u->email }}"
                                data-phone="{{ $u->phone_number }}"
                                data-role="{{ $u->getRoleNames()->first() }}"
                                data-subshops='@json($u->subshops->pluck("id"))'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-primary mr-1 assign-btn"
                                data-id="{{ $u->id }}"
                                data-name="{{ $u->name }}"
                                data-subshops='@json($u->subshops->pluck("id"))'>
                                <i class="fas fa-sitemap"></i> Assign Subshops
                            </button>
                            <button class="btn btn-sm btn-outline-warning mr-1 reset-password-btn"
                                data-id="{{ $u->id }}"
                                data-name="{{ $u->name }}">
                                <i class="fas fa-key"></i> Reset Password
                            </button>
                            <form action="{{ url('/admin/users/' . $u->id) }}" method="POST" style="display:inline;" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger delete-btn">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
 </div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="addUserForm" method="POST" action="{{ url('/admin/users') }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Add New User</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Name</label>
                <input type="text" class="form-control" name="name" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Email</label>
                <input type="email" class="form-control" name="email" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Phone</label>
                <input type="text" class="form-control" name="phone_number">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Role</label>
                <select class="form-control" name="role" required>
                  @foreach($roles as $role)
                    @if($role->name !== 'Super Admin' && $role->name !== 'owner')
                      <option value="{{ $role->name }}" {{ $role->name === 'shopkeeper' ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endif
                  @endforeach
                </select>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Password</label>
                <input type="password" class="form-control" name="password" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" class="form-control" name="password_confirmation" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Assign Subshops (optional)</label>
            <select class="form-control" name="subshop_ids[]" id="create_subshops" multiple>
              @foreach($subshops as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary add-user-btn">Save User</button>
        </div>
      </form>
    </div>
  </div>
 </div>

<!-- Assign Subshops Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" role="dialog" aria-labelledby="assignTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="assignForm" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="assignTitle">Assign Subshops</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="user_id" id="assign_user_id">
          <div class="form-group">
            <label for="assign_subshops">Subshops</label>
            <select class="form-control" name="subshop_ids[]" id="assign_subshops" multiple required>
              @foreach($subshops as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
              @endforeach
            </select>
            <small class="form-text text-muted">Select one or more subshops to assign to this user</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary assign-submit-btn">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="editForm" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="_method" value="PUT">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Name</label>
                <input type="text" class="form-control" name="name" id="edit_name" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Email</label>
                <input type="email" class="form-control" name="email" id="edit_email" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Phone</label>
                <input type="text" class="form-control" name="phone_number" id="edit_phone">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Role</label>
                <select class="form-control" name="role" id="edit_role" required>
                  @foreach($roles as $role)
                    @if($role->name !== 'Super Admin' && $role->name !== 'owner')
                      <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endif
                  @endforeach
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Assign Subshops</label>
            <select class="form-control" name="subshop_ids[]" id="edit_subshops" multiple>
              @foreach($subshops as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Update User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">User Details: <span id="view_user_name"></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <!-- Basic Information Card -->
        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-user"></i> Basic Information</h6>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <p><i class="fas fa-signature"></i> <strong>Name:</strong> <span id="view_name"></span></p>
                <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <span id="view_email"></span></p>
              </div>
              <div class="col-md-6">
                <p><i class="fas fa-phone"></i> <strong>Phone:</strong> <span id="view_phone"></span></p>
                <p><i class="fas fa-user-tag"></i> <strong>Role:</strong> <span id="view_role"></span></p>
              </div>
            </div>
            <p><i class="fas fa-store"></i> <strong>Assigned Subshops:</strong> <span id="view_subshops"></span></p>
          </div>
        </div>

        <!-- Performance Statistics -->
        <h6 class="text-center mb-3"><i class="fas fa-chart-bar"></i> Performance Statistics by Subshop</h6>
        <div id="performance_stats_container" class="row">
          <!-- Cards will be dynamically inserted here -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="resetPasswordModalLabel"><i class="fas fa-key"></i> Reset Password for <span id="reset_user_name"></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="resetPasswordForm" method="POST">
        @csrf
        <div class="modal-body">
          <div class="form-group">
            <label for="reset_password">New Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="reset_password" name="password" required minlength="8">
            <small class="form-text text-muted">Password must be at least 8 characters long.</small>
          </div>
          <div class="form-group">
            <label for="reset_password_confirmation">Confirm New Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="reset_password_confirmation" name="password_confirmation" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Reset Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        #usersTable td, #usersTable th { vertical-align: middle; }
    </style>
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script> -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(function(){
  $('#usersTable').DataTable({
    pageLength: 25
  });
  $('#assign_subshops, #create_subshops, #edit_subshops').select2({ width: '100%' });

  // Function to handle assign button click
  function handleAssignClick(e) {
    e.preventDefault();
    e.stopPropagation();

    console.log('Assign button clicked');

    const button = $(this);
    const id = button.data('id');
    const name = button.data('name');
    const subshops = button.data('subshops') || [];

    console.log('User ID:', id);
    console.log('User Name:', name);
    console.log('Current subshops:', subshops);

    $('#assign_user_id').val(id);
    $('#assignTitle').text('Assign Subshops to ' + name);
    $('#assign_subshops').val(subshops).trigger('change');
    $('#assignForm').attr('action', '/admin/users/' + id + '/assign-subshops');

    // Manually show the modal
    $('#assignModal').modal('show');
    console.log('Modal should be visible now');
  }

  // Attach event handlers
  $(document).on('click','.assign-btn', handleAssignClick);

  // Also attach directly to existing buttons (for immediate binding)
  $('.assign-btn').on('click', handleAssignClick);

  // Reset form when modal is closed
  $('#assignModal').on('hidden.bs.modal', function () {
    $('#assignForm')[0].reset();
  });

  $('#addUserModal').on('hidden.bs.modal', function () {
    $('#addUserForm')[0].reset();
    $('#create_subshops').val(null).trigger('change');
  });

  $('#editModal').on('hidden.bs.modal', function () {
    $('#editForm')[0].reset();
    $('#edit_subshops').val(null).trigger('change');
  });

  // Function to handle edit button click
  $(document).on('click', '.edit-btn', function(e) {
    e.preventDefault();
    const button = $(this);
    const id = button.data('id');
    const name = button.data('name');
    const email = button.data('email');
    const phone = button.data('phone');
    const role = button.data('role');
    const subshops = button.data('subshops') || [];

    $('#edit_name').val(name);
    $('#edit_email').val(email);
    $('#edit_phone').val(phone);
    $('#edit_role').val(role);
    $('#edit_subshops').val(subshops).trigger('change');
    $('#editForm').attr('action', '/admin/users/' + id);

    $('#editModal').modal('show');
  });

  // Function to handle view button click
  $(document).on('click', '.view-btn', function(e) {
    e.preventDefault();
    const button = $(this);
    const id = button.data('id');
    const name = button.data('name');

    $('#view_user_name').text(name);

    // Fetch user data
    $.ajax({
      url: '/admin/users/' + id,
      method: 'GET',
      success: function(response) {
        $('#view_name').text(response.user.name);
        $('#view_email').text(response.user.email);
        $('#view_phone').text(response.user.phone_number || '—');
        $('#view_role').text(response.user.role || '—');
        $('#view_subshops').text(response.subshops.map(s => s.name).join(', ') || '—');

        // Clear previous stats
        $('#performance_stats_container').empty();

        // Generate cards for each subshop's stats
        if (Array.isArray(response.stats)) {
          let chartIndex = 0;
          const chartIds = [];
          response.stats.forEach(function(stat) {
            const chartId = 'chart-' + chartIndex++;
            chartIds.push(chartId);
            const progressClass = stat.participation_percentage >= 50 ? 'bg-success' : stat.participation_percentage >= 25 ? 'bg-warning' : 'bg-danger';
            const cardHtml = `
              <div class="col-md-6 mb-4">
                <div class="card shadow-sm h-100">
                  <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-store"></i> ${stat.subshop_name}</h6>
                  </div>
                  <div class="card-body">
                    <div class="mb-3">
                      <strong>Participation in Shop Revenue</strong>
                      <div class="progress mt-2">
                        <div class="progress-bar ${progressClass}" style="width: ${stat.participation_percentage}%">${stat.participation_percentage}%</div>
                      </div>
                    </div>
                    <div class="row text-center mb-3">
                      <div class="col-6">
                        <i class="fas fa-boxes text-primary"></i>
                        <p class="mb-0"><strong>${stat.items_sold}</strong></p>
                        <small>Items Sold</small>
                      </div>
                      <div class="col-6">
                        <i class="fas fa-money-bill-wave text-success"></i>
                        <p class="mb-0"><strong>Tsh${stat.sales_value}</strong></p>
                        <small>Sales Value</small>
                      </div>
                    </div>
                    <div class="row text-center mb-3">
                      <div class="col-6">
                        <i class="fas fa-shopping-cart text-info"></i>
                        <p class="mb-0"><strong>${stat.sales_transactions}</strong></p>
                        <small>Sales Transactions</small>
                      </div>
                      <div class="col-6">
                        <i class="fas fa-truck text-warning"></i>
                        <p class="mb-0"><strong>${stat.purchase_transactions}</strong></p>
                        <small>Purchase Transactions</small>
                      </div>
                    </div>
                    <div class="row text-center mb-3">
                      <div class="col-6">
                        <i class="fas fa-minus-circle text-danger"></i>
                        <p class="mb-0"><strong>${stat.writeoffs}</strong></p>
                        <small>Writeoffs</small>
                      </div>
                      <div class="col-6">
                        <i class="fas fa-receipt text-secondary"></i>
                        <p class="mb-0"><strong>${stat.expenses}</strong></p>
                        <small>Expenses</small>
                      </div>
                    </div>
                    <div class="chart-container" style="height: 100px;">
                      <canvas id="${chartId}"></canvas>
                    </div>
                  </div>
                </div>
              </div>
            `;
            $('#performance_stats_container').append(cardHtml);
          });

          // Initialize charts after appending
          chartIds.forEach(function(chartId, index) {
            const stat = response.stats[index];
            const ctx = document.getElementById(chartId).getContext('2d');
            new Chart(ctx, {
              type: 'bar',
              data: {
                labels: ['Sales Trans.', 'Purch. Trans.', 'Writeoffs', 'Expenses'],
                datasets: [{
                  label: 'Transactions',
                  data: [stat.sales_transactions, stat.purchase_transactions, stat.writeoffs, stat.expenses],
                  backgroundColor: ['#007bff', '#28a745', '#dc3545', '#6c757d'],
                  borderWidth: 1
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                  y: {
                    beginAtZero: true,
                    ticks: {
                      stepSize: 1
                    }
                  }
                },
                plugins: {
                  legend: {
                    display: false
                  }
                }
              }
            });
          });
        } else if (response.stats) {
          // Fallback for object format (single subshop or old response)
          const stat = response.stats;
          const subshopName = response.subshops && response.subshops.length > 0 ? response.subshops[0].name : 'Subshop';
          const progressClass = stat.participation_percentage >= 50 ? 'bg-success' : stat.participation_percentage >= 25 ? 'bg-warning' : 'bg-danger';
          const cardHtml = `
            <div class="col-md-6 mb-4">
              <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white">
                  <h6 class="mb-0"><i class="fas fa-store"></i> ${subshopName}</h6>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <strong>Participation in Shop Revenue</strong>
                    <div class="progress mt-2">
                      <div class="progress-bar ${progressClass}" style="width: ${stat.participation_percentage}%">${stat.participation_percentage}%</div>
                    </div>
                  </div>
                  <div class="row text-center mb-3">
                    <div class="col-6">
                      <i class="fas fa-boxes text-primary"></i>
                      <p class="mb-0"><strong>${stat.items_sold}</strong></p>
                      <small>Items Sold</small>
                    </div>
                    <div class="col-6">
                      <i class="fas fa-money-bill-wave text-success"></i>
                      <p class="mb-0"><strong>Tsh${stat.sales_value}</strong></p>
                      <small>Sales Value</small>
                    </div>
                  </div>
                  <div class="row text-center mb-3">
                    <div class="col-6">
                      <i class="fas fa-shopping-cart text-info"></i>
                      <p class="mb-0"><strong>${stat.sales_transactions}</strong></p>
                      <small>Sales Transactions</small>
                    </div>
                    <div class="col-6">
                      <i class="fas fa-truck text-warning"></i>
                      <p class="mb-0"><strong>${stat.purchase_transactions}</strong></p>
                      <small>Purchase Transactions</small>
                    </div>
                  </div>
                  <div class="row text-center mb-3">
                    <div class="col-6">
                      <i class="fas fa-minus-circle text-danger"></i>
                      <p class="mb-0"><strong>${stat.writeoffs}</strong></p>
                      <small>Writeoffs</small>
                    </div>
                    <div class="col-6">
                      <i class="fas fa-receipt text-secondary"></i>
                      <p class="mb-0"><strong>${stat.expenses}</strong></p>
                      <small>Expenses</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;
          $('#performance_stats_container').append(cardHtml);
        } else {
          $('#performance_stats_container').append('<p>No performance stats available.</p>');
        }

        $('#viewModal').modal('show');
      },
      error: function(xhr, status, error) {
        let message = 'Failed to load user details.';
        if (xhr.responseJSON && xhr.responseJSON.error) {
          message = xhr.responseJSON.error;
        } else if (xhr.status) {
          message += ' (HTTP ' + xhr.status + ')';
        }
        Swal.fire(
          'Error!',
          message,
          'error'
        );
      }
    });
  });

  // Handle add user form submission
  $(document).on('submit', '#addUserForm', function(e) {
    e.preventDefault();
    const form = $(this);
    $.ajax({
      url: form.attr('action'),
      method: 'POST',
      data: form.serialize(),
      success: function(response) {
        Swal.fire(
          'Success!',
          'User added successfully.',
          'success'
        ).then(() => {
          $('#addUserModal').modal('hide');
          location.reload();
        });
      },
      error: function(xhr) {
        Swal.fire(
          'Error!',
          'Failed to add user. Please check the form and try again.',
          'error'
        );
      }
    });
  });

  // Handle assign form submission
  $(document).on('submit', '#assignForm', function(e) {
    e.preventDefault();
    const form = $(this);
    $.ajax({
      url: form.attr('action'),
      method: 'POST',
      data: form.serialize(),
      success: function(response) {
        Swal.fire(
          'Success!',
          'Subshops assigned successfully.',
          'success'
        ).then(() => {
          $('#assignModal').modal('hide');
          location.reload();
        });
      },
      error: function(xhr) {
        Swal.fire(
          'Error!',
          'Failed to assign subshops. Please try again.',
          'error'
        );
      }
    });
  });

  // Handle edit form submission
  $(document).on('submit', '#editForm', function(e) {
    e.preventDefault();
    const form = $(this);
    $.ajax({
      url: form.attr('action'),
      method: 'POST',
      data: form.serialize(),
      success: function(response) {
        Swal.fire(
          'Success!',
          'User updated successfully.',
          'success'
        ).then(() => {
          $('#editModal').modal('hide');
          location.reload();
        });
      },
      error: function(xhr) {
        Swal.fire(
          'Error!',
          'Failed to update user. Please check the form and try again.',
          'error'
        );
      }
    });
  });

  // Function to handle delete button click
  $(document).on('click', '.delete-btn', function(e) {
    e.preventDefault();
    const form = $(this).closest('form');
    const userName = $(this).closest('tr').find('td:nth-child(2)').text(); // Get user name from table
    Swal.fire({
      title: 'Are you sure?',
      text: `You are about to delete user "${userName}". This action cannot be undone!`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: form.attr('action'),
          method: 'POST',
          data: form.serialize(),
          success: function(response) {
            Swal.fire(
              'Deleted!',
              'The user has been deleted successfully.',
              'success'
            ).then(() => {
              location.reload(); // Reload page to update table
            });
          },
          error: function(xhr) {
            Swal.fire(
              'Error!',
              'Failed to delete the user. Please try again.',
              'error'
            );
          }
        });
      }
    });
  });

  // Function to handle reset password button click
  $(document).on('click', '.reset-password-btn', function(e) {
    e.preventDefault();
    const button = $(this);
    const id = button.data('id');
    const name = button.data('name');

    $('#reset_user_name').text(name);
    $('#resetPasswordForm').attr('action', '/admin/users/' + id + '/reset-password');
    $('#resetPasswordForm')[0].reset(); // Reset form fields
    $('#resetPasswordModal').modal('show');
  });

  // Handle reset password form submission
  $(document).on('submit', '#resetPasswordForm', function(e) {
    e.preventDefault();
    const form = $(this);
    $.ajax({
      url: form.attr('action'),
      method: 'POST',
      data: form.serialize(),
      success: function(response) {
        Swal.fire(
          'Success!',
          'Password reset successfully.',
          'success'
        ).then(() => {
          $('#resetPasswordModal').modal('hide');
        });
      },
      error: function(xhr) {
        Swal.fire(
          'Error!',
          'Failed to reset password. Please check the form and try again.',
          'error'
        );
      }
    });
  });

  // Reset form when modal is closed
  $('#resetPasswordModal').on('hidden.bs.modal', function () {
    $('#resetPasswordForm')[0].reset();
  });
});
</script>
@stop