@extends('adminlte::page')

@section('title', 'Users Management')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-users-cog"></i> Users Management</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-users-cog"></i> Users</h1>
                    <p class="mb-0 text-light">Manage users, roles, and Branches assignments.</p>
                </div>
                <a href="{{ route('users.create') }}" class="btn btn-light btn-sm border">
                    <i class="fas fa-user-plus"></i> Add User
                </a> 
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
            <thead class="thead-light">
                <tr>
                    <th>No.</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Assigned Branches</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $i=1; @endphp
                @foreach($users as $u)
                    <tr>
                        <td>#{{ $i++ }}</td>
                        <td><a href="{{ route('users.show', $u->id) }}">{{ $u->name }}</a></td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->phone_number ?? '—' }}</td>
                        <td>
                            <span class="badge badge-{{ $u->getRoleNames()->first() == 'admin' ? 'danger' : ($u->getRoleNames()->first() == 'shopkeeper' ? 'success' : 'info') }}">
                                {{ $u->getRoleNames()->first() ?? '—' }}
                            </span>
                        </td>
                        <td>
                            @forelse($u->subshops as $ss)
                                <span class="badge badge-info mr-1 mb-1">{{ $ss->name }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('users.show', $u->id) }}" class="btn btn-info btn-sm" title="View">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="{{ route('users.edit', $u->id) }}" class="btn btn-warning btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form action="{{ route('users.destroy', $u->id) }}" method="POST" class="d-inline delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<style>
    #usersTable td, #usersTable th { vertical-align: middle; }
    .btn-group .btn { margin-right: 0; }
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function(){
    $('#usersTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']]
    });

    // Delete confirmation
    $(document).on('submit', '.delete-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const userName = form.closest('tr').find('td:nth-child(2)').text();

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
                form.submit();
            }
        });
    });
});
</script>
@endpush