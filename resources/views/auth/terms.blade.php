<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="dukabase-logo" href="{{ asset('img/dukabase-logo.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/db-logo.svg') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Terms and Conditions - FinanceBase</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box" style="max-width: 800px; width: 100%;">
  <div class="login-logo">
    <a href="{{ url('/') }}"><b>Duka</b>Base</a>
  </div>
  <!-- /.login-logo -->
  <div class="card">
    <div class="card-body login-card-body">
      <h1 class="text-center mb-4">Terms and Conditions</h1>

      <div class="terms-content" style="max-height: 60vh; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.25rem; padding: 1rem; background-color: #f8f9fa; margin-bottom: 1rem;">
        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 0;">1. Introduction</h2>
        <p style="margin-bottom: 1rem;">
          DukaBase is a multi-shop management system designed to help users manage shops, products, sales, users, and business transactions in one place.
        </p>
        <p style="margin-bottom: 1rem;">
          By accessing or using DukaBase, you confirm that you have read, understood, and agree to these Terms and Conditions. If you do not agree, please do not use the system.
        </p>

        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 1.5rem;">2. User Eligibility</h2>
        <ul style="margin-bottom: 1rem;">
          <li>Users must provide accurate, current, and complete information when using the system.</li>
          <li>Users are responsible for ensuring that their use of the system is appropriate for their business needs.</li>
          <li>Users are responsible for all activity carried out under their accounts.</li>
        </ul>

        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 1.5rem;">3. Account Registration &amp; Security</h2>
        <ul style="margin-bottom: 1rem;">
          <li>Users must keep login credentials (such as usernames, passwords, and verification codes) confidential.</li>
          <li>Users should use strong passwords and take reasonable steps to prevent unauthorized access.</li>
          <li>
            The system owner is not responsible for losses or issues caused by unauthorized access that results from user negligence,
            such as sharing credentials or failing to secure devices.
          </li>
          <li>Users should notify support promptly if they suspect unauthorized access to their accounts.</li>
        </ul>

        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 1.5rem;">4. System Usage</h2>
        <ul style="margin-bottom: 1rem;">
          <li>DukaBase is intended for lawful business management purposes, such as inventory tracking, sales recording, and user administration.</li>
          <li>Users must not misuse the system, including attempts to disrupt services, bypass security, or access data they are not authorized to view.</li>
          <li>Users must not upload or transmit harmful content, including malware, or use the system in a way that could compromise performance or reliability.</li>
        </ul>

        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 1.5rem;">5. Data &amp; Privacy</h2>
        <p style="margin-bottom: 1rem;">
          DukaBase stores business and user data entered into the system, such as product details, sales records, user profiles, and related operational information.
        </p>
        <ul style="margin-bottom: 1rem;">
          <li>Reasonable measures are used to help protect data against unauthorized access, loss, or misuse.</li>
          <li>Users are responsible for the accuracy, legality, and quality of data they enter or manage within the system.</li>
          <li>Users should maintain appropriate backups or exports where necessary for their business continuity needs.</li>
        </ul>

        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 1.5rem;">6. Multi-Shop Management</h2>
        <ul style="margin-bottom: 1rem;">
          <li>Users may manage one or multiple shops under a single account, depending on the features provided.</li>
          <li>Each shop's data is managed independently within the system, based on the structure and permissions configured by the user.</li>
          <li>Users are responsible for assigning roles and access permissions appropriately to protect sensitive business information.</li>
        </ul>

        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 1.5rem;">7. Service Availability</h2>
        <ul style="margin-bottom: 1rem;">
          <li>Service availability is provided on a best-effort basis, and uninterrupted access cannot be guaranteed at all times.</li>
          <li>Maintenance, updates, or technical issues may occasionally cause temporary downtime or reduced functionality.</li>
          <li>Where practical, updates may be performed to improve performance, security, and features.</li>
        </ul>

        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 1.5rem;">8. Termination</h2>
        <ul style="margin-bottom: 1rem;">
          <li>Accounts may be suspended or restricted if there is a reasonable basis to believe these terms have been violated.</li>
          <li>Users may stop using the system at any time.</li>
          <li>Termination or suspension does not remove the user's responsibility for activities performed before termination.</li>
        </ul>

        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 1.5rem;">9. Changes to Terms</h2>
        <p style="margin-bottom: 1rem;">
          These Terms and Conditions may be updated from time to time to reflect improvements to the system, operational changes, or updated requirements.
        </p>
        <p style="margin-bottom: 1rem;">
          Continued use of DukaBase after updates are published means you accept the revised terms.
        </p>

        <h2 style="color: #007bff; font-size: 1.25rem; margin-top: 1.5rem;">10. Contact Information</h2>
        <p style="margin-bottom: 1rem;">
          If you have questions, concerns, or need assistance regarding these Terms and Conditions or your use of DukaBase, please contact support through the
          available help channels within the system.
        </p>
      </div>

      <div class="row mt-4">
        <div class="col-4">
          <a href="javascript:history.back()" class="btn btn-secondary btn-block">Back</a>
        </div>
        <div class="col-4">
          <a href="{{ route('privacy') }}" class="btn btn-info btn-block">Privacy Policy</a>
        </div>
        <div class="col-4">
          <a href="{{ route('register') }}" class="btn btn-primary btn-block">Register</a>
        </div>
      </div>
    </div>
    <!-- /.login-card-body -->
  </div>
</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>