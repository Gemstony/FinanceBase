@extends('adminlte::page')

@section('title', 'SMS Templates')

@section('content_header')
<div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="d-none d-md-block text-light"><i class="fas fa-file-alt"></i> SMS Templates</h1>
                <h1 class="d-md-none text-light"><i class="fas fa-file-alt"></i> Templates</h1>
                <p class="mb-0 text-light">Manage SMS message templates</p>
            </div>
            <a href="{{ route('settings.sms_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>

        </div>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
        <li class="breadcrumb-item"><a href="{{ route('settings.sms_settings.index') }}">SMS Settings</a></li>
        <li class="breadcrumb-item active" aria-current="page">SMS Templates</li>
    </ol>
</nav>
@stop

@section('content')
<div class="container-fluid">
         @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">SMS Templates</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
                        <div>
                            <a href="{{ route('sms.templates.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Template
                            </a>
                        </div>
                        <div class="d-flex align-items-center">
                            <form method="GET" action="{{ route('sms.templates.index') }}" class="d-flex">
                                <select name="event" class="form-select form-select-sm me-2">
                                    <option value="">All Events</option>
                                    @foreach($events as $event)
                                        <option value="{{ $event }}" {{ request('event') == $event ? 'selected' : '' }}>
                                            {{ $event }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
                                <a href="{{ route('sms.templates.index') }}" class="btn btn-outline-secondary btn-sm ms-2">Reset</a>
                            </form>
                        </div>
                    </div>

                    @if($templates->isEmpty())
                        <div class="alert alert-info">
                            No SMS templates found.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Shop</th>
                                        <th>Name</th>
                                        <th>Event</th>
                                        <th>Variables</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($templates as $template)
                                        <tr>
                                            <td>{{ $template->shop->name }}</td>
                                            <td>{{ $template->name }}</td>
                                            <td>
                                                <span class="badge bg-info">{{ $template->event }}</span>
                                            </td>
                                            <td>
                                                @if(count($template->variables) > 0)
                                                    <span class="badge bg-light text-dark">
                                                        {{ count($template->variables) }} vars
                                                    </span>
                                                    @foreach($template->variables as $variable)
                                                        <span class="badge bg-primary bg-opacity-10 text-primary ms-1">{{ $variable }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">None</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $template->is_active ? 'success' : 'secondary' }}">
                                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('sms.templates.show', $template->id) }}" class="btn btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('sms.templates.edit', $template->id) }}" class="btn btn-outline-secondary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('sms.templates.destroy', $template->id) }}" method="POST" class="d-inline" data-swal-confirm>
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                         <div class="mt-4">
                             {{ $templates->links() }}
                         </div>
                     @endif
                 </div>
             </div>
         </div>
     </div>
 </div>
 @endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteForms = document.querySelectorAll('form[data-swal-confirm]');

        deleteForms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                Swal.fire({
                    title: 'Delete SMS Template',
                    text: 'Are you sure you want to delete this SMS template?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Deleting...',
                            text: 'Please wait while the SMS template is deleted.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        form.submit();
                    }
                });
            });
        });

        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: {!! json_encode(session('success')) !!},
                confirmButtonText: 'OK'
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: {!! json_encode(session('error')) !!},
                confirmButtonText: 'OK'
            });
        @endif

        @if($errors->any())
            const validationErrors = {!! json_encode($errors->all()) !!};
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                html: validationErrors.map(error => `<div class="text-start">&bull; ${error}</div>`).join(''),
                confirmButtonText: 'OK'
            });
        @endif
    });
</script>
@endpush

 @push('css')
 <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
 @endpush