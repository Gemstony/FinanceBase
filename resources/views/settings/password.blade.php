@extends('adminlte::page')

@section('title', 'Change Password')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-shield-alt"></i> Security Settings</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-shield-alt"></i> Security</h1>
                    <p class="mb-0 text-light">Manage your account password and security options.</p>
                </div>
                <a href="{{ route('settings.profile.show') }}" class="btn btn-light">
                    <i class="fas fa-user-cog mr-1"></i> Profile Settings
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('settings.profile.show') }}">Settings</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">Password</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="fas fa-key mr-2"></i>
                <span>Change Password</span>
            </div>
            <form id="changePasswordForm" action="{{ route('settings.profile.password') }}" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="input-group password-field">
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary toggle-visibility" type="button" data-target="#current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        @error('current_password')
                            <small class="text-danger d-block mt-1">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="form-group mt-2">
                        <label for="password">New Password</label>
                        <div class="input-group password-field">
                            <input type="password" id="password" name="password" class="form-control" required>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary toggle-visibility" type="button" data-target="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="progress" style="height: 6px;">
                                <div id="pwStrengthBar" class="progress-bar bg-danger" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small id="pwStrengthText" class="form-text text-muted mt-1">Enter a strong password.</small>
                        </div>
                        @error('password')
                            <small class="text-danger d-block mt-1">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="form-group mt-2">
                        <label for="password_confirmation">Confirm New Password</label>
                        <div class="input-group password-field">
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary toggle-visibility" type="button" data-target="#password_confirmation">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        @error('password_confirmation')
                            <small class="text-danger d-block mt-1">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div class="text-muted small">Multiple failed attempts may trigger temporary lockout.</div>
                    <button id="submitChangePassword" type="button" class="btn btn-primary" data-toggle="modal" data-target="#confirmChangeModal">
                        <i class="fas fa-save mr-1"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
 </div>

<!-- Confirm Submit Modal -->
<div class="modal fade" id="confirmChangeModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-shield-alt mr-1"></i> Confirm Password Update</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Are you sure you want to update your account password?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
        <button id="confirmSubmitBtn" type="button" class="btn btn-primary">
            <i class="fas fa-check mr-1"></i> Yes, Update
        </button>
      </div>
    </div>
  </div>
</div>

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        .password-field .btn { border-color: #ced4da; }
        .password-field .btn:hover { background: #f8f9fa; }
        #pwStrengthBar.bg-danger { background-color: #dc3545 !important; }
        #pwStrengthBar.bg-warning { background-color: #ffc107 !important; }
        #pwStrengthBar.bg-success { background-color: #28a745 !important; }
    </style>
@endpush
@stop

@section('js')
<script>
    document.querySelectorAll('.toggle-visibility').forEach(function(btn){
        btn.addEventListener('click', function(){
            var target = document.querySelector(this.getAttribute('data-target'));
            if (!target) return;
            var isPwd = target.getAttribute('type') === 'password';
            target.setAttribute('type', isPwd ? 'text' : 'password');
            var icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }
        });
    });

    var pwInput = document.getElementById('password');
    var bar = document.getElementById('pwStrengthBar');
    var text = document.getElementById('pwStrengthText');
    function evaluateStrength(pw){
        var score = 0;
        if (!pw) return 0;
        if (pw.length >= 8) score += 1;
        if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score += 1;
        if (/\d/.test(pw)) score += 1;
        if (/[^A-Za-z0-9]/.test(pw)) score += 1;
        if (pw.length >= 12) score += 1;
        return Math.min(score, 4);
    }
    function renderStrength(score){
        var widths = ['10%','35%','60%','85%','100%'];
        var labels = ['Very weak','Weak','Fair','Strong','Very strong'];
        var classes = ['bg-danger','bg-danger','bg-warning','bg-success','bg-success'];
        bar.style.width = widths[score];
        bar.classList.remove('bg-danger','bg-warning','bg-success');
        bar.classList.add(classes[score]);
        text.textContent = labels[score];
    }
    if (pwInput) {
        pwInput.addEventListener('input', function(){
            renderStrength(evaluateStrength(this.value));
        });
    }

    var confirmBtn = document.getElementById('confirmSubmitBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function(){
            var form = document.getElementById('changePasswordForm');
            if (form) form.submit();
        });
    }
</script>
@stop