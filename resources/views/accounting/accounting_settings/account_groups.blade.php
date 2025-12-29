@extends('adminlte::page')

@section('title', 'Account Groups - ' . $subshop->name)

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-layer-group"></i> Account Groups</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-layer-group"></i> Account Groups</h1>
                <p class="mb-0 text-light">Managing account groups for: <strong>{{ $subshop->name }}</strong></p>
            </div>
            <a href="{{ route('categories.subshops') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Change Branch
            </a>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i>
                    Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('accounting.accounting_settings.index') }}">Accounting
                    settings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Account Groups</li>
        </ol>
    </nav>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAccountGroupModal">
        <i class="fas fa-plus"></i> New Account Group
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
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="accountClassesTable">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Class</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accountGroups as $index => $group)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge badge-info">{{ $group->code }}</span></td>
                            <td><span class="badge badge-success">{{ $group->class->name }}</span></td>
                            <td>{{ $group->name }}</td>
                            <td>{{ $group->description ?? 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $group->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $group->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ $group->created_at->format('M d, Y') }}</td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" data-id="{{ $group->id }}"
                                    data-class-name="{{ $group->class->name }}" data-class-id="{{ $group->class->id }}" data-name="{{ $group->name }}"
                                    data-code="{{ $group->code }}" data-description="{{ $group->description }}"
                                    data-is-active="{{ $group->is_active }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $group->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No account groups found. Click 'New Account Group' to
                                add one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Account Group Modal -->
<div class="modal fade" id="addAccountGroupModal" tabindex="-1" role="dialog"
    aria-labelledby="addAccountGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addAccountGroupForm" action="{{ route('accounting.account_groups.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addAccountGroupModalLabel">Add New Account Groups</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- <div class="form-group">
                        <label for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required
                            placeholder="e.g. 1001, 2002, etc.">
                    </div> -->
                    <div class="form-group">
                        <label for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required
                            placeholder="e.g. Cash and Bank, Customer Deposit, etc.">
                    </div>
                    <div class="form-group">
                        <label for="class">Group Class <span class="text-danger">*</span></label>
                        <select name="class_id" class="form-control" required>
                            <option value="" disabled selected>-- Select Type --</option>
                            @foreach ($account_classes as $class)
                              <option value="{{ $class->id }}">{{ $class->name }}</option>
                            @endforeach
                          
                         
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Enter description (optional)"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                            checked>
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

<!-- Edit Account Groups Modal -->
<div class="modal fade" id="editAccountGroupModal" tabindex="-1" role="dialog"
    aria-labelledby="editAccountGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editAccountGroupForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editAccountGroupModalLabel">Edit Account Group</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_code">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_code" name="code" required disabled>
                    </div>
                    <div class="form-group">
                        <label for="edit_name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_class">Group Class <span class="text-danger">*</span></label>
                        <select name="class_id" id="edit_class" class="form-control" required >
                            <option value="" disabled selected >Change class</option>
                            @foreach ($account_classes as $class)
                              <option value="{{ $class->id }}">{{ $class->name }}</option>
                            @endforeach
                          
                         
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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
.table th,
.table td {
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
    $('#accountClassesTable').DataTable({
        responsive: true,
        columnDefs: [{
                orderable: false,
                targets: [0, 7]
            }, // Disable sorting on action column
            {
                searchable: false,
                targets: [0, 4, 5, 6,  7]
            } // Disable search on action and status columns
        ],
        order: [
            [1, 'asc']
        ] // Sort by code by default
    });

    // Handle edit button click
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var class_id = $(this).data('class-id')
         var class_name = $(this).data('class-name')
        var name = $(this).data('name');
        var code = $(this).data('code');
        var description = $(this).data('description');
        var isActive = $(this).data('is-active');


        $('#editAccountGroupForm').attr('action', '/accounting/account_groups/' + id);
        $('#edit_code').val(code);
        $('#edit_class').val(class_id);
        $('#edit_name').val(name);
        $('#edit_description').val(description);
        $('#edit_is_active').prop('checked', isActive);

        $('#editAccountGroupModal').modal('show');
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
                    url: '/accounting/account_groups/' + id,
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
                    error: function(xhr) {
                        Swal.fire(
                            'Error!',
                            'An error occurred while deleting the account group.',
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
        showConfirmButton: false,
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