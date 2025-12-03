@extends('adminlte::page')

@section('title', 'Read Message')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-envelope-open"></i> Read Message</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-envelope-open"></i> Read</h1>
                    <p class="mb-0 text-light">View message details and delivery status.</p>
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
                <li class="breadcrumb-item active text-dark" aria-current="page">Read</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <a href="{{ route('messages.create') }}" class="btn btn-primary btn-block mb-3">
                <i class="fas fa-plus"></i> Compose
            </a>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Folders</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a href="{{ route('messages.index') }}" class="nav-link">
                                <i class="fas fa-inbox"></i> Inbox
                                <span class="badge bg-primary float-right">{{ auth()->user()->unreadMessagesCount() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('messages.index', ['folder' => 'sent']) }}" class="nav-link">
                                <i class="fas fa-paper-plane"></i> Sent
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('messages.index', ['status' => 'unread']) }}" class="nav-link">
                                <i class="far fa-envelope"></i> Unread
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">{{ $message->subject }}</h3>

                    <div class="card-tools">
                        <span class="badge {{ $message->getPriorityBadgeClass() }}">
                            <i class="{{ $message->getTypeIcon() }}"></i> {{ $message->getTypeLabel() }}
                        </span>
                        @if($isSender ?? false)
                            @php
                                $deliveredCount = $message->recipients->where('delivery_status', 'delivered')->count();
                                $totalRecipients = $message->recipients->count();
                            @endphp
                            @if($deliveredCount == $totalRecipients)
                                <span class="badge badge-success">Delivered to all</span>
                            @elseif($deliveredCount > 0)
                                <span class="badge badge-warning">Delivered to {{ $deliveredCount }}/{{ $totalRecipients }}</span>
                            @else
                                <span class="badge badge-secondary">Pending delivery</span>
                            @endif
                        @else
                            @if($recipient && !$recipient->is_read)
                                <span class="badge badge-primary">Unread</span>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    <div class="mailbox-read-info">
                        <h5>From: {{ $message->sender->name }}
                            <span class="mailbox-read-time float-right">{{ $message->created_at->format('d M Y H:i') }}</span>
                        </h5>
                        @if($isSender ?? false)
                            <h6>To:
                                @php
                                    $recipientNames = $message->recipients->map(function($r) {
                                        return $r->user->name;
                                    })->implode(', ');
                                @endphp
                                {{ $recipientNames }}
                            </h6>
                        @endif
                    </div>

                    <div class="mailbox-read-message">
                        {!! nl2br(e($message->content)) !!}
                    </div>
                </div>

                <div class="card-footer">
                    <div class="float-right">
                        <a href="{{ ($isSender ?? false) ? route('messages.index', ['folder' => 'sent']) : route('messages.index') }}" class="btn btn-default">
                            <i class="fas fa-arrow-left"></i> {{ ($isSender ?? false) ? 'Back to Sent' : 'Back to Inbox' }}
                        </a>
                        @if(!$isSender && $recipient && !$recipient->is_read)
                            <button type="button" class="btn btn-primary mark-as-read-btn" data-url="{{ route('messages.read', $message) }}">
                                <i class="fas fa-check"></i> Mark as Read
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
$(document).ready(function() {
    $('.mark-as-read-btn').on('click', function() {
        const btn = $(this);
        const url = btn.data('url');

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Marking...');

        $.post(url, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                btn.remove();
                location.reload();
            }
        })
        .fail(function() {
            btn.prop('disabled', false).html('<i class="fas fa-check"></i> Mark as Read');
            alert('Failed to mark message as read. Please try again.');
        });
    });
});
</script>
@endpush

@push('css')
<style>
.mailbox-read-info {
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.mailbox-read-info h5 {
    margin: 0;
    color: #333;
}

.mailbox-read-time {
    color: #666;
    font-size: 0.9em;
}

.mailbox-read-message {
    padding: 10px 0;
    line-height: 1.6;
    white-space: pre-wrap;
}

.badge {
    font-size: 0.75em;
}
</style>
@endpush
@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

