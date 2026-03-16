<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="dukabase-logo" href="{{ asset('img/dukabase-logo.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/db-logo.svg') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify OTP - FinanceBase</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="{{ url('/') }}"><b>Finance</b>Base</a>
  </div>

  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">Verify OTP</p>
      <p class="text-muted">Enter the 6-digit code sent to your phone number.</p>

      @if (session('status'))
        <div class="alert alert-success">
          {{ session('status') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form action="{{ route('password.otp.verify.post') }}" method="post">
        @csrf

        <div class="input-group mb-3">
          <input type="text" class="form-control @error('otp') is-invalid @enderror" placeholder="6-digit OTP" name="otp" value="{{ old('otp') }}" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6">
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-key"></span>
            </div>
          </div>
          @error('otp')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">Verify OTP</button>
          </div>
        </div>
      </form>

      <p class="mb-1 mt-3">
        <a href="{{ route('password.request') }}">Request a new OTP</a>
      </p>
      <p class="mb-1">
        <a href="{{ route('login') }}">Back to Login</a>
      </p>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
