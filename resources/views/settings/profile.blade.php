@extends('adminlte::page')

@section('title', 'Your Profile')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-id-card"></i> Profile</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-id-card"></i> Profile</h1>
                    <p class="mb-0 text-light">Manage your public info and account details.</p>
                </div>
                <a href="{{ route('settings.password.show') }}" class="btn btn-light">
                    <i class="fas fa-key mr-1"></i> Password Settings
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('settings.profile.show') }}">Settings</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">Profile</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    @php($user = auth()->user())

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img src="{{ $user->profile_image ? asset('storage/'.$user->profile_image) : 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=0D8ABC&color=fff' }}" alt="Profile Image" class="img-fluid rounded-circle" style="width: 140px; height: 140px; object-fit: cover;">
                    </div>
                    <form action="{{ route('settings.profile.photo') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="input-group mb-2">
                            <input type="file" name="profile_image" class="form-control" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-camera mr-1"></i> Change Photo</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><strong><i class="fas fa-shield-alt mr-1"></i> Roles & Permissions</strong></div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-shield mr-2"></i> Role</span>
                            <span class="badge bg-primary"><i class="fas fa-user-tag mr-1"></i> 
                                @auth
                                    @if (Auth::user()->roles->count())
                                        {{ e(Auth::user()->roles->pluck('name')->join(', ')) }}
                                    @else
                                        User
                                    @endif
                                @else
                                    Guest
                                @endauth
                        </span>
                        </li>
                        @if(count($permissionNames))
                            <li class="list-group-item">
                                <i class="fas fa-check-circle text-success mr-1"></i> <strong>Permissions:</strong>
                                <div class="mt-2 d-flex flex-wrap gap-1">
                                    @foreach($permissionNames as $permission)
                                        <span class="badge badge-primary">{{ ucwords(str_replace('_', ' ', $permission)) }}</span>
                                    @endforeach
                                </div>
                            </li>
                        @else
                            <li class="list-group-item">
                                <i class="fas fa-times-circle text-muted mr-1"></i> No specific permissions assigned
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><strong><i class="fas fa-user mr-1"></i> Full Name</strong></div>
                        <div class="card-body">{{ $user->name }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><strong><i class="fas fa-envelope mr-1"></i> Email Address</strong></div>
                        <div class="card-body">{{ $user->email }}</div>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="card">
                        <div class="card-header"><strong><i class="fas fa-calendar-plus mr-1"></i> Account Created</strong></div>
                        <div class="card-body">{{ $user->created_at?->format('M d, Y H:i') }}</div>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="card">
                        <div class="card-header"><strong><i class="fas fa-hourglass-half mr-1"></i> Account Age</strong></div>
                        <div class="card-body">{{ $user->created_at?->diffForHumans() }}</div>
                    </div>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="card">
                        <div class="card-header"><strong><i class="fas fa-phone mr-1"></i> Phone Number</strong></div>
                        <div class="card-body">{{ $user->phone_number ?? 'Not set' }}</div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-user-cog mr-1"></i> Edit Profile</strong>
                    <button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#changePasswordModal"><i class="fas fa-key mr-1"></i> Change Password</button>
                </div>
                <div class="card-body">
                    <form action="{{ route('settings.profile.update') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="form-group mt-2">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="form-group mt-2">
                            <label for="phone_number">Phone Number</label>
                            <input type="text" id="phone_number" name="phone_number" class="form-control" value="{{ old('phone_number', $user->phone_number) }}">
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save mr-1"></i> Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key mr-1"></i> Change Password</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('settings.profile.password') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group mt-2">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group mt-2">
                            <label for="password_confirmation">Confirm New Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Password</button>
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
@stop