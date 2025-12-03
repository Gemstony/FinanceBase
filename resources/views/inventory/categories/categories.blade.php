@extends('adminlte::page')

@section('title', 'Categories - ' . $subshop->name)

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-tags"></i> Categories Management</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-tags"></i> Categories</h1>
                    <p class="mb-0 text-light">Managing categories for: <strong>{{ $subshop->name }}</strong></p>
                </div>
                <a href="{{ route('categories.subshops') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Change Shop
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('categories.subshops') }}">Choose Shop</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">{{ $subshop->name }} - Categories</li>
            </ol>
        </nav>
    </div>
@stop



@section('content')
<div class="container-fluid">
    <!-- Search and Add Button -->
    <div class="row mb-3">
        <div class="col-md-6">
            <form method="GET" action="{{ route('categories.index') }}">
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search categories..." value="{{ request('search') }}">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-6 text-right">
            @can('add_category')
            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addCategoryModal">
                <i class="fas fa-plus"></i> Add Category
            </button>
            @endcan
        </div>
    </div>

    <!-- Categories Table -->
    <div class="card">
        <div class="card-body table-responsive p-2">
            <table class="table table-hover text-nowrap" id="categoriesTable">
                <thead class="thead-dark">
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody><?php $count = 1;?>
                    @forelse($categories as $category)
                        <tr>
                            <td>#<?=$count++ ?></td>
                            <td>{{ $category->name }}</td>
                            <td>{{ Str::limit($category->description, 50) }}</td>
                            <td>
                                <span class="badge {{ $category->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ $category->created_at->format('d/m/Y') }}</td>
                            <td>
                                @can('edit_category')
                                <button class="btn btn-sm btn-info" title="Edit" data-toggle="modal" data-target="#editCategoryModal" data-id="{{ $category->id }}" data-name="{{ $category->name }}" data-description="{{ $category->description ?? '' }}" data-active="{{ $category->is_active ? '1' : '0' }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @endcan
                                @can('delete_category')
                                <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="deleteCategory({{ $category->id }}, '{{ $category->name }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No categories found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $categories->appends(request()->query())->links() }}
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('categories.store') }}" method="POST">
                @csrf
                <input type="hidden" name="subshop_id" value="{{ $subshop->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editCategoryForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name">Category Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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
                    <button type="submit" class="btn btn-primary">Update Category</button>
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

   $(function () {
    // Initialize DataTable
        $('#categoriesTable').DataTable();
    });

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

@if (session('error'))
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: "{{ session('error') }}",
            showConfirmButton: true
        });
    });
@endif

$(document).ready(function() {
    $('#editCategoryModal').on('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const description = button.getAttribute('data-description');
        const isActive = button.getAttribute('data-active') === '1';

        document.getElementById('edit_name').value = name;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_is_active').checked = isActive;
        document.getElementById('editCategoryForm').action = '{{ url("/admin/inventory/categories") }}/' + id;
    });

    window.deleteCategory = function(id, name) {
        Swal.fire({
            title: 'Una uhakika?',
            text: `Unataka kufuta category "${name}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ndio, Futa!',
            cancelButtonText: 'Ghairi'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm').action = '{{ url("/admin/inventory/categories") }}/' + id;
                document.getElementById('deleteForm').submit();
            }
        });
    };
});
</script>
@stop