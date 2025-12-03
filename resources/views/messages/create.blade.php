@extends('adminlte::page')

@section('title', 'Compose Message')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-paper-plane"></i> Compose Message</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-paper-plane"></i> Compose</h1>
                    <p class="mb-0 text-light">Send a new message to one or more recipients.</p>
                </div>
                <a href="{{ route('messages.index') }}" class="btn btn-light">
                    <i class="fas fa-inbox"></i> Back to Inbox
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('messages.index') }}">Messages</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">Compose</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <a href="{{ route('messages.index') }}" class="btn btn-secondary btn-block mb-3">
                <i class="fas fa-arrow-left"></i> Back to Inbox
            </a>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a href="#" onclick="sendWelcomeMessage()" class="nav-link">
                                <i class="fas fa-handshake"></i> Welcome Message
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" onclick="sendStatusUpdate()" class="nav-link">
                                <i class="fas fa-info-circle"></i> Status Update
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" onclick="sendSupportInfo()" class="nav-link">
                                <i class="fas fa-question-circle"></i> Support Info
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Compose New Message</h3>
                </div>

                <form id="composeForm" method="POST" action="{{ route('messages.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="recipients">Recipients</label>
                            <select id="recipients" name="recipients[]" class="form-control select2" multiple required>
                                @foreach($recipients as $recipient)
                                    <option value="{{ $recipient->id }}">{{ $recipient->name }} ({{ $recipient->roles->first()?->name ?? 'Shopkeeper' }})</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Select one or more recipients</small>
                        </div>

                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" class="form-control" placeholder="Message subject" required>
                        </div>

                        <div class="form-group">
                            <label for="content">Message</label>
                            <textarea id="content" name="content" class="form-control" rows="10" placeholder="Type your message here..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="type">Message Type</label>
                            <select id="type" name="type" class="form-control" required>
                                <option value="email">Email</option>
                                <option value="notification" selected>In-App Notification</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority" class="form-control" required>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" id="is_urgent" name="is_urgent" value="1" class="custom-control-input">
                                <label class="custom-control-label" for="is_urgent">Mark as urgent</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Delivery Methods</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" id="in_app" name="delivery_methods[]" value="in_app" class="custom-control-input" checked>
                                        <label class="custom-control-label" for="in_app">In-App Notification</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" id="email" name="delivery_methods[]" value="email" class="custom-control-input">
                                        <label class="custom-control-label" for="email">Email</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" id="sms" name="delivery_methods[]" value="sms" class="custom-control-input">
                                        <label class="custom-control-label" for="sms">SMS</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" id="scheduleGroup" style="display: none;">
                            <label for="scheduled_at">Schedule For Later</label>
                            <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="form-control">
                            <small class="form-text text-muted">Leave empty to send immediately</small>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="float-right">
                            <a href="{{ route('messages.index') }}" class="btn btn-default">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 for recipients
    $('.select2').select2({
        placeholder: 'Select recipients',
        allowClear: true
    });

    // Handle form submission
    $('#composeForm').on('submit', function(e) {
        e.preventDefault();

        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();

        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Message Sent!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '{{ route("messages.index") }}';
                });
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html(originalText);

                let errorMessage = 'Failed to send message. Please try again.';

                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                }

                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join('\n');
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage
                });
            }
        });
    });
});

// Quick action functions
function sendWelcomeMessage() {
    $('#subject').val('Welcome to DukaBase!');
    $('#content').val(`Dear Team Member,

Welcome to DukaBase! We're excited to have you join our platform.

Your account has been set up and you now have access to all the tools you need to manage your shop effectively.

If you need any assistance, feel free to contact our support team.

Best regards,
DukaBase Management Team`);

    // Select all recipients
    $('.select2').val(null).trigger('change');
    $('.select2').val(['{{ $recipients->pluck('id')->implode("','") }}']).trigger('change');
}

function sendStatusUpdate() {
    $('#subject').val('Account Status Update');
    $('#content').val(`Dear Team Member,

This is an important update regarding your account status.

Please review your account settings and ensure all information is up to date.

If you have any questions, please don't hesitate to contact us.

Best regards,
DukaBase Management Team`);
}

function sendSupportInfo() {
    $('#subject').val('DukaBase Support Resources');
    $('#content').val(`Dear Team Member,

Here are the support resources available to help you:

📧 Email Support: support@dukabase.com
📞 Phone Support: +1 (555) 123-4567
💬 Live Chat: Available 9 AM - 6 PM EST
📚 Help Center: https://help.dukabase.com

We're here to help you succeed!

Best regards,
DukaBase Support Team`);
}
</script>
@endpush

@push('css')
<style>
.select2-container--default .select2-selection--multiple {
    min-height: 38px;
}

.form-group label {
    font-weight: 600;
}
</style>
@endpush
@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

