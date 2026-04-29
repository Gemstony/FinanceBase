@extends('adminlte::page')

@section('title', 'Messages')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-envelope"></i> Messages</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-envelope"></i> Messages</h1>
                    <p class="mb-0 text-light">View your inbox and sent messages.</p>
                </div>
                <a href="{{ route('messages.create') }}" class="btn btn-light">
                    <i class="fas fa-plus"></i> Compose
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">Messages</li>
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
                            <a href="{{ route('messages.index') }}" class="nav-link {{ !request('folder') || request('folder') == 'inbox' ? 'active' : '' }}">
                                <i class="fas fa-inbox"></i> Inbox
                                <span class="badge bg-primary float-right">{{ auth()->user()->unreadMessagesCount() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('messages.index', ['folder' => 'sent']) }}" class="nav-link {{ $folder == 'sent' ? 'active' : '' }}">
                                <i class="fas fa-paper-plane"></i> Sent
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('messages.index', ['status' => 'unread']) }}" class="nav-link">
                                <i class="far fa-envelope"></i> Unread
                                <span class="badge bg-warning float-right">{{ auth()->user()->unreadMessagesCount() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('messages.index', ['type' => 'system']) }}" class="nav-link">
                                <i class="fas fa-cog"></i> System
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('messages.index', ['type' => 'notification']) }}" class="nav-link">
                                <i class="fas fa-bell"></i> Notifications
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">{{ $folder == 'sent' ? 'Sent Messages' : 'Inbox' }}</h3>

                    <div class="card-tools">
                        <div class="input-group input-group-sm">
                            <form method="GET" class="d-flex">
                                @if($folder)
                                    <input type="hidden" name="folder" value="{{ $folder }}">
                                @endif
                                <select name="type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="email" {{ request('type') == 'email' ? 'selected' : '' }}>Email</option>
                                    <option value="notification" {{ request('type') == 'notification' ? 'selected' : '' }}>Notification</option>
                                    <option value="system" {{ request('type') == 'system' ? 'selected' : '' }}>System</option>
                                    <option value="bulk" {{ request('type') == 'bulk' ? 'selected' : '' }}>Bulk</option>
                                </select>
                                <select name="status" class="form-control ml-1" {{ $folder == 'sent' ? 'style="display: none;"' : '' }}>
                                    <option value="">All Messages</option>
                                    <option value="unread" {{ request('status') == 'unread' ? 'selected' : '' }}>Unread</option>
                                    <option value="read" {{ request('status') == 'read' ? 'selected' : '' }}>Read</option>
                                </select>
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive mailbox-messages">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Type</th>
                                    @if(($canMonitor ?? false))
                                        <th>From</th>
                                        <th>To</th>
                                    @else
                                        <th>{{ $folder == 'sent' ? 'Recipients' : 'Sender' }}</th>
                                    @endif
                                    <th>Subject</th>
                                    <th style="width: 40px;">Status</th>
                                    <th style="width: 120px;">Date</th>
                                    <th style="width: 100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($messages as $message)
                                    @php
                                        $recipient = $message->recipients->where('user_id', auth()->id())->first();
                                    @endphp
                                    <tr class="{{ $recipient && !$recipient->is_read ? 'unread' : '' }}">
                                        <td class="mailbox-star">
                                            <span class="badge {{ $message->getPriorityBadgeClass() }}">
                                                <i class="{{ $message->getTypeIcon() }}"></i>
                                            </span>
                                        </td>
                                        @if(($canMonitor ?? false))
                                            <td class="mailbox-name">
                                                <a href="{{ route('messages.show', $message) }}">
                                                    {{ $message->sender->name ?? '-' }}
                                                </a>
                                            </td>
                                            <td class="mailbox-name">
                                                @php
                                                    $recipientNames = $message->recipients->map(function($r) {
                                                        return $r->user->name;
                                                    })->take(2)->implode(', ');
                                                    $totalRecipients = $message->recipients->count();
                                                @endphp
                                                <a href="{{ route('messages.show', $message) }}" title="{{ $message->recipients->pluck('user.name')->implode(', ') }}">
                                                    {{ $recipientNames }}{{ $totalRecipients > 2 ? ' +' . ($totalRecipients - 2) . ' more' : '' }}
                                                </a>
                                            </td>
                                        @else
                                            <td class="mailbox-name">
                                                @if($folder == 'sent')
                                                    @php
                                                        $recipientNames = $message->recipients->map(function($r) {
                                                            return $r->user->name;
                                                        })->take(2)->implode(', ');
                                                        $totalRecipients = $message->recipients->count();
                                                    @endphp
                                                    <span title="{{ $message->recipients->pluck('user.name')->implode(', ') }}">
                                                        {{ $recipientNames }}{{ $totalRecipients > 2 ? ' +' . ($totalRecipients - 2) . ' more' : '' }}
                                                    </span>
                                                @else
                                                    <a href="{{ route('messages.show', $message) }}">
                                                        {{ $message->sender->name }}
                                                    </a>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="mailbox-subject">
                                            <a href="{{ route('messages.show', $message) }}">
                                                <b>{{ $message->subject }}</b> -
                                                {{ Str::limit(strip_tags($message->content), 50) }}
                                            </a>
                                        </td>
                                        <td class="mailbox-attachment">
                                            @if($folder == 'sent')
                                                @php
                                                    $deliveredCount = $message->recipients->where('delivery_status', 'delivered')->count();
                                                    $totalRecipients = $message->recipients->count();
                                                @endphp
                                                @if($deliveredCount == $totalRecipients)
                                                    <i class="fas fa-check-circle text-success" title="Delivered to all recipients"></i>
                                                @elseif($deliveredCount > 0)
                                                    <i class="fas fa-clock text-warning" title="Delivered to {{ $deliveredCount }}/{{ $totalRecipients }} recipients"></i>
                                                @else
                                                    <i class="fas fa-clock text-muted" title="Pending delivery"></i>
                                                @endif
                                            @else
                                                @if($recipient && !$recipient->is_read)
                                                    <i class="fas fa-circle text-primary"></i>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="mailbox-date">
                                            {{ $message->created_at->diffForHumans() }}
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('messages.show', $message) }}" class="btn btn-info btn-sm" title="View Message">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-danger btn-sm delete-message" 
                                                        data-message-id="{{ $message->id }}" 
                                                        data-message-subject="{{ $message->subject }}"
                                                        data-url="{{ $message->delete_url }}"
                                                        title="Delete Message">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ ($canMonitor ?? false) ? 7 : 6 }}" class="text-center py-4">
                                            <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">{{ $folder == 'sent' ? 'No sent messages' : 'No messages found' }}</h5>
                                            <p class="text-muted">{{ $folder == 'sent' ? 'You haven\'t sent any messages yet.' : 'You don\'t have any messages yet.' }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer p-0">
                    <div class="mailbox-controls">
                        <div class="float-right">
                            {{ $messages->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Handle delete message
    $('.delete-message').on('click', function() {
        const messageId = $(this).data('message-id');
        const messageSubject = $(this).data('message-subject');
        const deleteUrl = $(this).data('url');
        
        Swal.fire({
            title: 'Delete Message',
            text: `Are you sure you want to delete "${messageSubject}"? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                const deleteBtn = $(this);
                const originalHtml = deleteBtn.html();
                deleteBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Message has been deleted successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Remove the row from table
                        deleteBtn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if table is empty
                            if ($('tbody tr').length === 0) {
                                location.reload(); // Reload to show empty state
                            }
                        });
                    },
                    error: function(xhr) {
                        deleteBtn.prop('disabled', false).html(originalHtml);
                        
                        let errorMessage = 'Failed to delete message.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage = xhr.responseJSON.error;
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: errorMessage
                        });
                    }
                });
            }
        });
    });
});
</script>
@endpush
<style>
.mailbox-messages table tbody tr.unread {
    font-weight: bold;
    background-color: #f8f9fa;
}

.mailbox-messages table tbody tr:hover {
    background-color: #e9ecef;
}

.mailbox-star {
    width: 40px;
}

.mailbox-name {
    width: 150px;
}

.mailbox-subject {
    min-width: 200px;
}

.mailbox-attachment {
    width: 40px;
    text-align: center;
}

.mailbox-date {
    width: 120px;
    text-align: right;
}

.badge {
    font-size: 0.75em;
}

/* Simple Bootstrap pagination styling */
.mailbox-controls .pagination {
    margin: 0;
}

/* Hide Previous/Next buttons to make pagination more compact */
.mailbox-controls .pagination .page-item:first-child,
.mailbox-controls .pagination .page-item:last-child {
    display: none;
}

/* Remove custom pagination styles to use default Bootstrap styling */

/* Table styling improvements */
.table th {
    background-color: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.table td {
    vertical-align: middle;
    padding: 0.75rem;
}

/* Button group styling */
.btn-group .btn {
    border-radius: 0.25rem !important;
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

/* Responsive table adjustments */
@media (max-width: 768px) {
    .mailbox-name,
    .mailbox-subject,
    .mailbox-date {
        width: auto;
        min-width: auto;
    }
    
    .mailbox-subject {
        max-width: 200px;
    }
    
    .mailbox-subject a {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}
</style>
@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

