@extends('adminlte::page')

@section('title', 'Configure Shop')

@section('content_header')
    <div class="row mb-4 p-2 " style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px; border-radius:5px;">
        <div class="col-sm-12">
            <h1 class="m-0 text-light">
                <i class="fas fa-cog text-light"></i> Configure Shop
            </h1>
            <p class="text-light">Manage shop settings and configurations</p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-sm-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-light">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('shopsmanagement.show') }}"><i class="fas fa-store"></i> Main Shops</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Configure Shop</li>
                </ol>
            </nav>
        </div>
    </div>
@stop

@section('content')
    <!-- Shop Details Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-store"></i> Shop Details</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">Name:</dt>
                                <dd class="col-sm-8">{{ $shop->name ?? 'N/A' }}</dd>

                                <dt class="col-sm-4">Phone:</dt>
                                <dd class="col-sm-8">{{ $shop->phone ?? 'N/A' }}</dd>

                                <dt class="col-sm-4">Address:</dt>
                                <dd class="col-sm-8">{{ $shop->address ?? 'N/A' }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">Status:</dt>
                                <dd class="col-sm-8">
                                    <span class="badge {{ ($shop->is_active ?? false) ? 'badge-success' : 'badge-secondary' }}">
                                        <i class="fas {{ ($shop->is_active ?? false) ? 'fa-check-circle' : 'fa-pause-circle' }}"></i>
                                        {{ ($shop->is_active ?? false) ? 'Active' : 'Inactive' }}
                                    </span>
                                </dd>

                                <dt class="col-sm-4">Created:</dt>
                                <dd class="col-sm-8">{{ $shop->created_at ? $shop->created_at->format('d/m/Y') : 'N/A' }}</dd>

                                @if(!empty($shop->description))
                                    <dt class="col-sm-4">Description:</dt>
                                    <dd class="col-sm-8">{{ $shop->description }}</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subshops Overview Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header bg-gradient-primary text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-store-alt mr-2"></i>Subshops Overview</h3>
                </div>
                <div class="card-body">
                    @php
                        $subshops = $shop->subShops ?? collect();
                    @endphp
                    @if($subshops->count() === 0)
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> This shop has no subshops yet.
                        </div>
                    @else
                        <div class="row">
                            @foreach($subshops as $s)
                                @php
                                    $since = now()->subDays(30);
                                    $itemsAgg = \App\Models\Item::where('subshop_id', $s->id)
                                        ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(quantity),0) as qty, COALESCE(SUM(quantity*price),0) as retail_value, COALESCE(SUM(quantity*cost_price),0) as cost_value')
                                        ->first();

                                    $salesAgg = \App\Models\SalesOrders::where('subshop_id', $s->id)
                                        ->where('created_at', '>=', $since)
                                        ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total')
                                        ->first();

                                    $purchAgg = \App\Models\PurchaseOrders::where('subshop_id', $s->id)
                                        ->where('created_at', '>=', $since)
                                        ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total')
                                        ->first();

                                    $usersCount = method_exists($s, 'users') ? $s->users()->count() : 0;
                                @endphp
                                <div class="col-xl-4 col-lg-6 mb-4">
                                    <div class="card card-outline card-primary h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center bg-gradient-primary text-white">
                                            <h5 class="mb-0"><i class="fas fa-home mr-2"></i>{{ $s->name }}</h5>
                                            <span class="badge {{ ($s->is_active ?? false) ? 'badge-success' : 'badge-secondary' }}">{{ ($s->is_active ?? false) ? 'Active' : 'Inactive' }}</span>
                                        </div>
                                        <div class="card-body py-3">
                                            <ul class="list-unstyled mb-3">
                                                <li class="mb-1"><small class="text-muted"><i class="fas fa-user-friends text-success mr-1"></i>Users:</small> <strong>{{ $usersCount }}</strong></li>
                                                <li class="mb-1"><small class="text-muted"><i class="fas fa-boxes text-primary mr-1"></i>Items:</small> <strong>{{ number_format($itemsAgg->cnt ?? 0) }}</strong></li>
                                                <li class="mb-1"><small class="text-muted"><i class="fas fa-file-invoice-dollar text-success mr-1"></i>Invoices 30d:</small> <strong>{{ number_format($salesAgg->cnt ?? 0) }}</strong> <small class="text-muted">(Total {{ number_format($salesAgg->total ?? 0, 2) }})</small></li>
                                                <li class="mb-1"><small class="text-muted"><i class="fas fa-truck-loading text-info mr-1"></i>Purchases 30d:</small> <strong>{{ number_format($purchAgg->cnt ?? 0) }}</strong> <small class="text-muted">(Total {{ number_format($purchAgg->total ?? 0, 2) }})</small></li>
                                            </ul>
                                            <div class="d-flex justify-content-between">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#subshopDetailsModal" data-subshop-id="{{ $s->id }}">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                                <div class="text-right small text-muted">
                                                    <div><i class="fas fa-phone mr-1"></i>{{ $s->phone ?? 'N/A' }}</div>
                                                    <div class="text-truncate" style="max-width: 180px;"><i class="fas fa-map-marker-alt mr-1"></i>{{ $s->address ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                            <!-- Hidden detailed content -->
                                            @php
                                                $lowStockCount = \App\Models\Item::where('subshop_id', $s->id)->whereColumn('quantity', '<=', 'min_quantity')->count();
                                                $expiredCount = \App\Models\Item::where('subshop_id', $s->id)->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now()->toDateString())->count();
                                                $salesToday = \App\Models\SalesOrders::where('subshop_id', $s->id)->whereDate('created_at', now()->toDateString())->sum('grand_total');
                                                $purchasesToday = \App\Models\PurchaseOrders::where('subshop_id', $s->id)->whereDate('created_at', now()->toDateString())->sum('grand_total');
                                            @endphp
                                            <div id="subshop-details-{{ $s->id }}" class="d-none">
                                                <div class="mb-3">
                                                    <h5 class="mb-2">{{ $s->name }}</h5>
                                                    <p class="mb-1"><i class="fas fa-phone text-primary mr-1"></i>{{ $s->phone ?? 'N/A' }}</p>
                                                    <p class="mb-1"><i class="fas fa-map-marker-alt text-danger mr-1"></i>{{ $s->address ?? 'N/A' }}</p>
                                                    <p class="mb-1"><i class="fas fa-calendar text-info mr-1"></i>Created: {{ $s->created_at ? $s->created_at->format('d/m/Y') : 'N/A' }}</p>
                                                    <p class="mb-0"><i class="fas fa-user-friends text-success mr-1"></i>Assigned Users: {{ $usersCount }}</p>
                                                </div>
                                                @php
                                                    $smsMonthCount = \App\Models\SmsLog::where('subshop_id', $s->id)
                                                        ->where('status', 'sent')
                                                        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                                                        ->count();
                                                @endphp
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <div class="info-box">
                                                            <span class="info-box-icon bg-warning"><i class="fas fa-sms"></i></span>
                                                            <div class="info-box-content">
                                                                <span class="info-box-text">This Month SMS</span>
                                                                <span class="info-box-number">{{ $smsMonthCount }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <div class="info-box">
                                                            <span class="info-box-icon bg-primary"><i class="fas fa-boxes"></i></span>
                                                            <div class="info-box-content">
                                                                <span class="info-box-text">Inventory</span>
                                                                <div class="d-flex justify-content-between flex-wrap">
                                                                    <div>
                                                                        <span class="text-muted small">Items</span>
                                                                        <div class="font-weight-bold">{{ number_format($itemsAgg->cnt ?? 0) }}</div>
                                                                    </div>
                                                                    <div>
                                                                        <span class="text-muted small">On-hand Qty</span>
                                                                        <div class="font-weight-bold">{{ number_format($itemsAgg->qty ?? 0) }}</div>
                                                                    </div>
                                                                    <div>
                                                                        <span class="text-muted small">Retail Value</span>
                                                                        <div class="font-weight-bold">{{ number_format($itemsAgg->retail_value ?? 0, 2) }}</div>
                                                                    </div>
                                                                    <div>
                                                                        <span class="text-muted small">Cost Value</span>
                                                                        <div class="font-weight-bold">{{ number_format($itemsAgg->cost_value ?? 0, 2) }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="info-box">
                                                            <span class="info-box-icon bg-info"><i class="fas fa-heartbeat"></i></span>
                                                            <div class="info-box-content">
                                                                <span class="info-box-text">Health</span>
                                                                <span class="info-box-number">
                                                                    <span class="badge badge-warning mr-2"><i class="fas fa-exclamation-triangle"></i> Low Stock: {{ $lowStockCount }}</span>
                                                                    <span class="badge badge-danger"><i class="fas fa-skull-crossbones"></i> Expired: {{ $expiredCount }}</span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <div class="small-box bg-gradient-success">
                                                            <div class="inner">
                                                                <h4 class="mb-1">{{ number_format($salesAgg->cnt ?? 0) }}</h4>
                                                                <p class="mb-1">Invoices (Last 30 days)</p>
                                                                <small>Total: <strong>{{ number_format($salesAgg->total ?? 0, 2) }}</strong></small>
                                                            </div>
                                                            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="small-box bg-gradient-info">
                                                            <div class="inner">
                                                                <h4 class="mb-1">{{ number_format($purchAgg->cnt ?? 0) }}</h4>
                                                                <p class="mb-1">Purchases (Last 30 days)</p>
                                                                <small>Total: <strong>{{ number_format($purchAgg->total ?? 0, 2) }}</strong></small>
                                                            </div>
                                                            <div class="icon"><i class="fas fa-truck-loading"></i></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-12 mb-2">
                                                        <div class="info-box">
                                                            <span class="info-box-icon bg-secondary"><i class="fas fa-calendar-day"></i></span>
                                                            <div class="info-box-content">
                                                                <span class="info-box-text">Today</span>
                                                                <div class="d-flex justify-content-between">
                                                                    <span><i class="fas fa-cash-register text-success mr-1"></i>Sales: <strong>{{ number_format($salesToday ?? 0, 2) }}</strong></span>
                                                                    <span><i class="fas fa-dolly-flatbed text-info mr-1"></i>Purchases: <strong>{{ number_format($purchasesToday ?? 0, 2) }}</strong></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Subshop Details Modal -->
    <div class="modal fade" id="subshopDetailsModal" tabindex="-1" role="dialog" aria-labelledby="subshopDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title" id="subshopDetailsModalLabel"><i class="fas fa-store-alt mr-2"></i> Subshop Details</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="subshopDetailsBody">
                    <!-- dynamic details will be injected here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('js')
    <script>
      $(document).ready(function(){
        $('#subshopDetailsModal').on('show.bs.modal', function (event) {
          var button = $(event.relatedTarget);
          var subshopId = button.data('subshop-id');
          var detailsHtml = $('#subshop-details-' + subshopId).html() || '<div class="text-muted">No details available.</div>';
          $('#subshopDetailsBody').html(detailsHtml);
        });
        
        // Helper: Recalculate Total Paid from table rows (Completed + Partial)
        window.updateTotalPaidDisplay = function() {
            const currency = '{{ $shop->currency ?? 'TZS' }}';
            let total = 0;
            $('#paymentsHistoryBody tr').each(function() {
                const $row = $(this);
                const statusText = ($row.find('td').eq(4).text() || '').trim().toLowerCase();
                if (statusText.includes('completed') || statusText.includes('partial')) {
                    const amt = parseFloat(($row.find('button.edit-payment').data('amount'))) || 0;
                    total += amt;
                }
            });
            $('#totalPaidValue').text(`${currency} ${total.toFixed(2)}`);
        };

        // Initial compute on load
        updateTotalPaidDisplay();
      });
    </script>
    @endpush

    <!-- Owner Information Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-tie"></i> Shop Owner</h3>
                </div>
                <div class="card-body">
                    @if(isset($owner) && $owner)
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-4">Name:</dt>
                                    <dd class="col-sm-8">{{ $owner->name ?? 'N/A' }}</dd>

                                    <dt class="col-sm-4">Email:</dt>
                                    <dd class="col-sm-8">{{ $owner->email ?? 'N/A' }}</dd>

                                    <dt class="col-sm-4">Phone:</dt>
                                    <dd class="col-sm-8">{{ $owner->phone ?? 'N/A' }}</dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-4">Role:</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge badge-primary">Owner</span>
                                    </dd>

                                    <dt class="col-sm-4">Joined:</dt>
                                    <dd class="col-sm-8">{{ $owner->created_at ? $owner->created_at->format('d/m/Y') : 'N/A' }}</dd>
                                </dl>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <h5><i class="icon fas fa-exclamation-triangle"></i> No Owner Assigned</h5>
                            Assign an owner to this shop.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Shopkeepers Section -->
    <div class="row">
        <div class="col-12">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users"></i> Assigned Shopkeepers</h3>
                </div>
                <div class="card-body">
                    @if(isset($shopkeepers) && $shopkeepers->count() > 0)
                        <div class="row">
                            @foreach($shopkeepers as $shopkeeper)
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <div class="small-box bg-light">
                                        <div class="inner">
                                            <h4 class="text-dark">{{ $shopkeeper->name ?? 'N/A' }}</h4>
                                            <p class="text-muted">Shopkeeper</p>
                                        </div>
                                        <div class="icon">
                                            <i class="fas fa-user text-warning"></i>
                                        </div>
                                        <div class="small-box-footer bg-white">
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <small class="text-muted"><i class="fas fa-envelope text-primary"></i></small><br>
                                                    <small>{{ substr($shopkeeper->email ?? 'N/A', 0, 15) }}{{ strlen($shopkeeper->email ?? '') > 15 ? '...' : '' }}</small>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted"><i class="fas fa-phone text-success"></i></small><br>
                                                    <small>{{ $shopkeeper->phone_number ?? 'N/A' }}</small>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted"><i class="fas fa-calendar text-info"></i></small><br>
                                                    <small>{{ $shopkeeper->created_at ? $shopkeeper->created_at->format('d/m/Y') : 'N/A' }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-info-circle"></i> No Shopkeepers Assigned</h5>
                            Assign shopkeepers to manage this shop.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Communication Tools Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-comments"></i> Communication Tools - Direct Owner Interaction</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" onclick="openMessageModal()">
                            <i class="fas fa-envelope"></i> Send Message
                        </button>
                        <button type="button" class="btn btn-info btn-sm" onclick="openNotificationModal()">
                            <i class="fas fa-bell"></i> Send Notification
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" onclick="openBulkActionModal()">
                            <i class="fas fa-bullhorn"></i> Bulk Actions
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="communication-history" id="communicationHistory">
                                <div class="timeline timeline-inverse">
                                    <!-- Communication history will be loaded here -->
                                    <div class="time-label">
                                        <span class="bg-success">Recent Communications</span>
                                    </div>
                                    <div id="communicationItems">
                                        @php
                                            $recentMessages = \App\Models\Message::where('sender_id', auth()->id())
                                                ->where('shop_id', $shop->id)
                                                ->orderBy('created_at', 'desc')
                                                ->take(10)
                                                ->get();
                                        @endphp

                                        @forelse($recentMessages as $message)
                                            <div class="timeline-item" data-message-id="{{ $message->id }}">
                                                <span class="time">
                                                    <i class="fas fa-clock"></i>
                                                    @if($message->sent_at)
                                                        {{ $message->sent_at->diffForHumans() }}
                                                    @else
                                                        {{ $message->created_at->diffForHumans() }}
                                                    @endif
                                                </span>
                                                <h3 class="timeline-header">
                                                    <span class="badge {{ $message->getPriorityBadgeClass() }}">
                                                        {{ ucfirst($message->priority) }}
                                                        @if($message->is_urgent)
                                                            <i class="fas fa-exclamation-triangle ml-1"></i>
                                                        @endif
                                                    </span>
                                                    <strong>{{ $message->subject }}</strong>
                                                    <div class="float-right">
                                                        @if($message->sent_at && $message->sent_at->diffInHours(now()) <= 24)
                                                            <button type="button" class="btn btn-sm btn-outline-primary edit-message-btn"
                                                                    data-message-id="{{ $message->id }}"
                                                                    data-subject="{{ $message->subject }}"
                                                                    data-content="{{ $message->content }}"
                                                                    data-priority="{{ $message->priority }}"
                                                                    data-urgent="{{ $message->is_urgent ? 'true' : 'false' }}">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        @endif
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-message-btn"
                                                                data-message-id="{{ $message->id }}"
                                                                data-subject="{{ $message->subject }}">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </h3>
                                                <div class="timeline-body">
                                                    <p>{{ Str::limit($message->content, 200) }}</p>
                                                    <small class="text-muted">
                                                        Type: {{ $message->getTypeLabel() }} |
                                                        Recipients: {{ $message->recipients->count() }}
                                                        @if($message->updated_at != $message->created_at)
                                                            (edited {{ $message->updated_at->diffForHumans() }})
                                                        @endif
                                                    </small>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="timeline-item">
                                                <span class="time"><i class="fas fa-clock"></i> Just now</span>
                                                <h3 class="timeline-header">
                                                    <span class="badge badge-info">System</span>
                                                    Communication tools initialized
                                                </h3>
                                                <div class="timeline-body">
                                                    Direct owner interaction tools are now available for managing this shop.
                                                </div>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box bg-light">
                                <div class="info-box-content">
                                    <span class="info-box-text">Quick Actions</span>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-success btn-block mb-2" onclick="sendWelcomeMessage()">
                                            <i class="fas fa-handshake"></i> Send Welcome Message
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning btn-block mb-2" onclick="sendStatusUpdate()">
                                            <i class="fas fa-info-circle"></i> Send Status Update
                                        </button>
                                        <button class="btn btn-sm btn-outline-info btn-block mb-2" onclick="sendSupportInfo()">
                                            <i class="fas fa-life-ring"></i> Send Support Info
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-block" onclick="sendWarning()">
                                            <i class="fas fa-exclamation-triangle"></i> Send Warning
                                        </button>
                                    </div>
                                </div>
                                <span class="info-box-icon"><i class="fas fa-tools text-success"></i></span>
                            </div>

                            <div class="info-box bg-gradient-primary mt-3">
                                <div class="info-box-content">
                                    <span class="info-box-text">Communication Stats</span>
                                    <span class="info-box-number" id="messageCount">{{ $messagesSentThisMonth ?? 0 }}</span>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: {{ $messagesSentThisMonth ? min(($messagesSentThisMonth / 10) * 100, 100) : 0 }}%"></div>
                                    </div>
                                    <span class="progress-description">
                                        Messages sent this month
                                    </span>
                                </div>
                                <span class="info-box-icon"><i class="fas fa-envelope"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Super Admin Settings Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card card-danger">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cogs"></i> Super Admin Settings</h3>
                </div>
                <div class="card-body">
                    <form id="adminSettingsForm">
                        @csrf
                        @method('PUT')

                        <!-- Shop Status Management -->
                        <div class="card card-outline card-primary collapsed-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-toggle-on"></i> Shop Status Management</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="status">Shop Status</label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="active" {{ $shop->status === 'active' ? 'selected' : '' }}>Active</option>
                                                <option value="inactive" {{ $shop->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                                <option value="suspended" {{ $shop->status === 'suspended' ? 'selected' : '' }}>Suspended</option>
                                                <option value="trial" {{ $shop->status === 'trial' ? 'selected' : '' }}>Trial</option>
                                            </select>
                                            <small class="form-text text-muted">Current status: <span class="badge badge-{{ $shop->status === 'active' ? 'success' : ($shop->status === 'suspended' ? 'danger' : 'warning') }}">{{ ucfirst($shop->status) }}</span></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6" id="suspensionReasonContainer" style="display: {{ $shop->status === 'suspended' ? 'block' : 'none' }}">
                                        <div class="form-group">
                                            <label for="suspension_reason">Suspension Reason</label>
                                            <textarea class="form-control" id="suspension_reason" name="suspension_reason" rows="2" maxlength="1000" placeholder="Reason for suspension...">{{ $shop->suspension_reason }}</textarea>
                                            <small class="form-text text-muted">Required when suspending a shop</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Information -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="info-box bg-light">
                                            <div class="info-box-content">
                                                <span class="info-box-text">Activated</span>
                                                <span class="info-box-number">{{ $shop->activated_at ? $shop->activated_at->format('d/m/Y H:i') : 'Not set' }}</span>
                                            </div>
                                            <span class="info-box-icon"><i class="fas fa-calendar-check text-success"></i></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6" style="display: {{ $shop->status === 'suspended' ? 'block' : 'none' }}">
                                        <div class="info-box bg-light">
                                            <div class="info-box-content">
                                                <span class="info-box-text">Suspended</span>
                                                <span class="info-box-number">{{ $shop->suspended_at ? $shop->suspended_at->format('d/m/Y H:i') : 'Not set' }}</span>
                                            </div>
                                            <span class="info-box-icon"><i class="fas fa-ban text-danger"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Subshop Limits -->
                        <div class="card card-outline card-info collapsed-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-store-alt"></i> Subshop Limits & Quotas</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="max_subshops">Maximum Subshops Allowed</label>
                                            <input type="number" class="form-control" id="max_subshops" name="max_subshops" value="{{ $shop->max_subshops }}" min="0" max="100" required>
                                            <small class="form-text text-muted">Set to 0 for unlimited</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Current Usage</label>
                                            <div class="mt-2">
                                                <div class="progress">
                                                    @php
                                                        $currentSubshops = $shop->subShops()->count();
                                                        $maxSubshops = $shop->max_subshops;
                                                        $percentage = $maxSubshops > 0 ? min(($currentSubshops / $maxSubshops) * 100, 100) : 0;
                                                        $progressClass = $percentage >= 90 ? 'bg-danger' : ($percentage >= 70 ? 'bg-warning' : 'bg-success');
                                                    @endphp
                                                    <div class="progress-bar {{ $progressClass }}" role="progressbar" style="width: {{ $percentage }}%" aria-valuenow="{{ $currentSubshops }}" aria-valuemin="0" aria-valuemax="{{ $maxSubshops }}">
                                                        {{ $currentSubshops }}/{{ $maxSubshops > 0 ? $maxSubshops : '∞' }}
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted">
                                                    Currently using {{ $currentSubshops }} of {{ $maxSubshops > 0 ? $maxSubshops : 'unlimited' }} subshops
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="row">
                            <div class="col-12"> 
                                <button type="submit" class="btn btn-danger btn-lg btn-sm">
                                    <i class="fas fa-save"></i> Update Super Admin Settings
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Plan Management - Monetization and Scaling Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-crown"></i> Plan Management - Monetization & Scaling</h3>
                </div>
                <div class="card-body">
                    <!-- Current Plan Status -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="info-box bg-gradient-success">
                                <div class="info-box-content">
                                    <span class="info-box-text">Current Plan</span>
                                    <span class="info-box-number">
                                        @if($shop->currentPlan())
                                            {{ $shop->currentPlan()->name }}
                                        @else
                                            <span class="text-muted">No Active Plan</span>
                                        @endif
                                    </span>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: {{ $shop->hasActiveSubscription() ? '100%' : '0%' }}"></div>
                                    </div>
                                    <span class="progress-description">
                                        @if($shop->hasActiveSubscription())
                                            @php
                                                $subscription = $shop->activeSubscription();
                                                $daysLeft = $subscription->days_until_expiration ?? 0;
                                            @endphp
                                            {{ $daysLeft > 0 ? $daysLeft . ' days remaining' : 'Expired' }}
                                        @else
                                            No active subscription
                                        @endif
                                    </span>
                                </div>
                                <span class="info-box-icon"><i class="fas fa-star"></i></span>
                            </div>
                        </div>
                    </div>

                    <!-- Plan Management Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card card-outline card-success collapsed-card">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-arrow-up"></i> Upgrade Plan</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form id="upgradePlanForm">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="new_plan">Select New Plan *</label>
                                                    <select class="form-control" id="new_plan" name="plan_id" required>
                                                        <option value="">Choose a plan...</option>
                                                        @php
                                                            $plans = \App\Models\Plan::where('status', 'active')->orderBy('sort_order')->get();
                                                        @endphp
                                                        @foreach($plans as $plan)
                                                            @php
                                                                $currentPlan = $shop->currentPlan();
                                                                $currentSubscription = $shop->activeSubscription();
                                                                $isCurrentPlan = $currentPlan && $currentPlan->id == $plan->id;
                                                                $isExpired = $currentSubscription && $currentSubscription->end_date && $currentSubscription->end_date->isPast();
                                                                $shouldDisable = $isCurrentPlan && !$isExpired;
                                                            @endphp
                                                            <option value="{{ $plan->id }}" {{ $shouldDisable ? 'disabled' : '' }}>
                                                                {{ $plan->name }} - {{ $plan->formatted_price }}/{{ $plan->billing_cycle_label }}
                                                                @if($isCurrentPlan && $isExpired)
                                                                    (Current - Expired)
                                                                @elseif($isCurrentPlan)
                                                                    (Current)
                                                                @endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="upgrade_payment_method">Payment Method *</label>
                                                    <select class="form-control" id="upgrade_payment_method" name="payment_method" required>
                                                        <option value="">Choose payment method...</option>
                                                        @foreach($paymentMethods as $method)
                                                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="upgrade_amount">Amount *</label>
                                                    <input type="number" class="form-control" id="upgrade_amount" name="amount" step="0.01" min="0" required>
                                                    <small class="form-text text-muted">Enter the payment amount for the plan</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="upgrade_transaction_id">Transaction ID</label>
                                                    <input type="text" class="form-control" id="upgrade_transaction_id" name="transaction_id" placeholder="Optional reference number">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="upgrade_notes">Payment Notes</label>
                                            <textarea class="form-control" id="upgrade_notes" name="notes" rows="2" placeholder="Any additional notes about this payment..."></textarea>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="upgrade_auto_renew" name="auto_renew" checked>
                                            <label class="form-check-label" for="upgrade_auto_renew">
                                                Enable auto-renewal for this plan
                                            </label>
                                        </div>

                                        <button type="submit" class="btn btn-success btn-lg mt-3 btn-sm">
                                            <i class="fas fa-arrow-up"></i> Upgrade Plan & Record Payment
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card card-outline card-info collapsed-card">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-history"></i> Payment History</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    @php
                                        $payments = $shop->payments()->orderBy('payment_date', 'desc')->take(10)->get();
                                    @endphp

                                    @if($payments->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Plan</th>
                                                        <th>Amount</th>
                                                        <th>Method</th>
                                                        <th>Status</th>
                                                        <th>Transaction ID</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="paymentsHistoryBody">
                                                    @foreach($payments as $payment)
                                                        <tr data-payment-id="{{ $payment->id }}">
                                                            <td>{{ $payment->payment_date->format('M j, Y') }}</td>
                                                            <td>{{ $payment->plan ? $payment->plan->name : 'N/A' }}</td>
                                                            <td>{{ $payment->formatted_amount }}</td>
                                                            <td>
                                                                <span class="badge badge-secondary">
                                                                    {{ $payment->paymentMethod ? $payment->paymentMethod->name : ($payment->payment_method ? ucfirst($payment->payment_method) : 'Unknown') }}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge {{ $payment->status === 'completed' ? 'badge-success' : ($payment->status === 'pending' || $payment->status === 'partial' ? 'badge-warning' : 'badge-danger') }}">
                                                                    {{ ucfirst($payment->status) }}
                                                                </span>
                                                            </td>
                                                            <td>{{ $payment->transaction_id ?? '-' }}</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary edit-payment" 
                                                                        data-id="{{ $payment->id }}"
                                                                        data-amount="{{ $payment->amount }}"
                                                                        data-payment-method="{{ $payment->payment_method_id }}"
                                                                        data-transaction-id="{{ $payment->transaction_id }}"
                                                                        data-notes="{{ $payment->notes }}"
                                                                        data-payment-date="{{ $payment->payment_date->format('Y-m-d') }}">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger delete-payment" data-id="{{ $payment->id }}">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-center mt-3">
                                            <strong>Total Paid: <span id="totalPaidValue">{{ $shop->currency ?? 'TZS' }} {{ number_format($shop->totalPayments(), 2) }}</span></strong>
                                        </div>
                                    @else
                                        <div class="text-center text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>No payment history found for this shop.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Record Manual Payment -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card card-outline card-warning collapsed-card">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-plus-circle"></i> Record Manual Payment</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form id="recordPaymentForm">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="payment_plan">Plan *</label>
                                                    <select class="form-control" id="payment_plan" name="plan_id" required>
                                                        <option value="">Select plan...</option>
                                                        @foreach($plans as $plan)
                                                            <option value="{{ $plan->id }}">{{ $plan->name }} - {{ $plan->formatted_price }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="payment_method">Payment Method *</label>
                                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                                        <option value="">Select payment method...</option>
                                                        @foreach($paymentMethods as $method)
                                                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="payment_amount">Amount *</label>
                                                    <input type="number" class="form-control" id="payment_amount" name="amount" step="0.01" min="0" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="payment_date">Payment Date *</label>
                                                    <input type="date" class="form-control" id="payment_date" name="payment_date" value="{{ date('Y-m-d') }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="payment_transaction_id">Transaction ID</label>
                                                    <input type="text" class="form-control" id="payment_transaction_id" name="transaction_id" placeholder="Reference number">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="payment_notes">Payment Notes</label>
                                            <textarea class="form-control" id="payment_notes" name="notes" rows="2" placeholder="Details about this payment..."></textarea>
                                        </div>

                                        <button type="submit" class="btn btn-warning btn-lg btn-sm">
                                            <i class="fas fa-save"></i> Record Payment
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subscription Management -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card card-outline card-primary collapsed-card">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Subscription Management</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    @php
                                        $subscription = $shop->activeSubscription();
                                    @endphp

                                    @if($subscription)
                                        <div class="row">
                                            <div class="col-md-6">
                                                <dl class="row">
                                                    <dt class="col-sm-4">Plan:</dt>
                                                    <dd class="col-sm-8">{{ $subscription->plan->name ?? 'N/A' }}</dd>

                                                    <dt class="col-sm-4">Status:</dt>
                                                    <dd class="col-sm-8">
                                                        <span class="badge {{ $subscription->status_badge_class }}">{{ ucfirst($subscription->status) }}</span>
                                                    </dd>

                                                    <dt class="col-sm-4">Period:</dt>
                                                    <dd class="col-sm-8">{{ $subscription->subscription_period }}</dd>

                                                    <dt class="col-sm-4">Auto-renew:</dt>
                                                    <dd class="col-sm-8">{{ $subscription->auto_renew ? 'Yes' : 'No' }}</dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-center">
                                                    @if($subscription->isActive())
                                                        <button class="btn btn-danger btn-sm" onclick="cancelSubscription({{ $subscription->id }})">
                                                            <i class="fas fa-times"></i> Cancel Subscription
                                                        </button>
                                                    @elseif($subscription->isExpired())
                                                        <button class="btn btn-success btn-sm" onclick="renewSubscription({{ $subscription->id }})">
                                                            <i class="fas fa-refresh"></i> Renew Subscription
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-center text-muted">
                                            <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                            <p>No active subscription found for this shop.</p>
                                            <p>Create a subscription by upgrading a plan above.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Message Modal -->
    <div class="modal fade" id="sendMessageModal" tabindex="-1" role="dialog" aria-labelledby="sendMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="sendMessageModalLabel">
                        <i class="fas fa-envelope"></i> Send Message to Shop Owner
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="sendMessageForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="message_recipient">Recipient</label>
                                    <input type="text" class="form-control" id="message_recipient" value="{{ $owner ? $owner->name . ' (' . $owner->email . ')' : 'No owner assigned' }}" readonly>
                                    <input type="hidden" id="message_recipient_id" value="{{ $owner ? $owner->id : '' }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="message_type">Message Type</label>
                                    <select class="form-control" id="message_type" name="message_type">
                                        <option value="email">Email Message</option>
                                        <option value="notification">In-App Notification</option>
                                        <option value="both">Email + Notification</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="message_subject">Subject *</label>
                            <input type="text" class="form-control" id="message_subject" name="subject" placeholder="Enter message subject" required>
                        </div>

                        <div class="form-group">
                            <label for="message_content">Message Content *</label>
                            <textarea class="form-control" id="message_content" name="content" rows="6" placeholder="Type your message here..." required></textarea>
                            <small class="form-text text-muted">Use this space to communicate important information, updates, or requests to the shop owner.</small>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="message_urgent" name="urgent">
                            <label class="form-check-label" for="message_urgent">
                                <strong>Mark as Urgent</strong> - This will highlight the message and send immediate notification
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Send Notification Modal -->
    <div class="modal fade" id="sendNotificationModal" tabindex="-1" role="dialog" aria-labelledby="sendNotificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="sendNotificationModalLabel">
                        <i class="fas fa-bell"></i> Send Notification to Shop Owner
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="sendNotificationForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="notification_recipient">Recipient</label>
                                    <input type="text" class="form-control" id="notification_recipient" value="{{ $owner ? $owner->name : 'No owner assigned' }}" readonly>
                                    <input type="hidden" id="notification_recipient_id" value="{{ $owner ? $owner->id : '' }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="notification_priority">Priority Level</label>
                                    <select class="form-control" id="notification_priority" name="priority">
                                        <option value="low">Low Priority</option>
                                        <option value="normal" selected>Normal Priority</option>
                                        <option value="high">High Priority</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notification_title">Notification Title *</label>
                            <input type="text" class="form-control" id="notification_title" name="title" placeholder="Enter notification title" required>
                        </div>

                        <div class="form-group">
                            <label for="notification_message">Notification Message *</label>
                            <textarea class="form-control" id="notification_message" name="message" rows="4" placeholder="Enter your notification message..." required></textarea>
                            <small class="form-text text-muted">This will appear as an in-app notification for the shop owner.</small>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="notification_email" name="send_email" checked>
                            <label class="form-check-label" for="notification_email">
                                Also send as email notification
                            </label>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="notification_persistent" name="persistent">
                            <label class="form-check-label" for="notification_persistent">
                                Make notification persistent (won't auto-dismiss)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-bell"></i> Send Notification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div class="modal fade" id="bulkActionModal" tabindex="-1" role="dialog" aria-labelledby="bulkActionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="bulkActionModalLabel">
                        <i class="fas fa-bullhorn"></i> Bulk Communication Actions
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="bulkActionForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6>Recipients</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bulk_owner" checked disabled>
                                    <label class="form-check-label" for="bulk_owner">
                                        <strong>{{ $owner ? $owner->name : 'No owner' }}</strong> (Shop Owner) - Primary recipient
                                    </label>
                                </div>
                                @if(isset($shopkeepers) && $shopkeepers->count() > 0)
                                    @foreach($shopkeepers as $shopkeeper)
                                        <div class="form-check">
                                            <input class="form-check-input bulk-recipient" type="checkbox" id="bulk_shopkeeper_{{ $shopkeeper->id }}" value="{{ $shopkeeper->id }}">
                                            <label class="form-check-label" for="bulk_shopkeeper_{{ $shopkeeper->id }}">
                                                {{ $shopkeeper->name }} (Shopkeeper)
                                            </label>
                                        </div>
                                    @endforeach
                                @endif

                                <hr>
                                <div class="form-group">
                                    <label for="bulk_action_type">Action Type</label>
                                    <select class="form-control" id="bulk_action_type" name="action_type">
                                        <option value="announcement">General Announcement</option>
                                        <option value="maintenance">Maintenance Notice</option>
                                        <option value="policy_update">Policy Update</option>
                                        <option value="feature_update">Feature Update</option>
                                        <option value="urgent_alert">Urgent Alert</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="bulk_subject">Subject *</label>
                                    <input type="text" class="form-control" id="bulk_subject" name="subject" placeholder="Enter bulk message subject" required>
                                </div>

                                <div class="form-group">
                                    <label for="bulk_content">Message Content *</label>
                                    <textarea class="form-control" id="bulk_content" name="content" rows="8" placeholder="Enter your bulk message..." required></textarea>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h6>Delivery Options</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bulk_email" name="send_email" checked>
                                            <label class="form-check-label" for="bulk_email">
                                                <i class="fas fa-envelope text-primary"></i> Send via Email
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bulk_notification" name="send_notification" checked>
                                            <label class="form-check-label" for="bulk_notification">
                                                <i class="fas fa-bell text-info"></i> Send as Notification
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bulk_sms" name="send_sms">
                                            <label class="form-check-label" for="bulk_sms">
                                                <i class="fas fa-sms text-success"></i> Send via SMS (Premium)
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Schedule</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="schedule_type" id="schedule_now" value="now" checked>
                                            <label class="form-check-label" for="schedule_now">
                                                Send Immediately
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="schedule_type" id="schedule_later" value="later">
                                            <label class="form-check-label" for="schedule_later">
                                                Schedule for later
                                            </label>
                                        </div>
                                        <div class="form-group mt-2" id="schedule_datetime_container" style="display: none;">
                                            <label for="schedule_datetime">Schedule Date & Time</label>
                                            <input type="datetime-local" class="form-control" id="schedule_datetime" name="schedule_datetime">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-paper-plane"></i> Send Bulk Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Message Modal -->
    <div class="modal fade" id="editMessageModal" tabindex="-1" role="dialog" aria-labelledby="editMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editMessageModalLabel">
                        <i class="fas fa-edit"></i> Edit Message
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editMessageForm">
                    <input type="hidden" id="edit_message_id" name="message_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_subject">Subject *</label>
                            <input type="text" class="form-control" id="edit_subject" name="subject" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_content">Message Content *</label>
                            <textarea class="form-control" id="edit_content" name="content" rows="6" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_priority">Priority Level</label>
                                    <select class="form-control" id="edit_priority" name="priority">
                                        <option value="low">Low Priority</option>
                                        <option value="normal">Normal Priority</option>
                                        <option value="high">High Priority</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_urgent">Urgent Message</label>
                                    <div class="form-check">
                                        <input type="hidden" name="is_urgent" value="0">
                                        <input type="checkbox" class="form-check-input" id="edit_urgent" name="is_urgent" value="1">
                                        <label class="form-check-label" for="edit_urgent">
                                            Mark as urgent
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> You can only edit messages within 24 hours of sending them.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@stop
@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        /* Communication Tools Styling */
        .communication-history {
            max-height: 400px;
            overflow-y: auto;
        }

        .timeline {
            position: relative;
            margin: 0 0 30px 0;
            padding: 0;
            list-style: none;
        }

        .timeline:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #ddd;
            left: 31px;
            margin: 0;
            border-radius: 2px;
        }

        .timeline > li {
            position: relative;
            margin-right: 10px;
            margin-bottom: 20px;
        }

        .timeline > li:before,
        .timeline > li:after {
            content: " ";
            display: table;
        }

        .timeline > li:after {
            clear: both;
        }

        .timeline > li > .timeline-item {
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
            border-radius: 3px;
            margin-left: 60px;
            margin-right: 15px;
            margin-top: 0;
            background: #fff;
            color: #444;
            margin-left: 60px;
            margin-right: 15px;
            padding: 0;
            position: relative;
        }

        .timeline > li > .timeline-item > .time {
            color: #999;
            float: right;
            padding: 10px;
            font-size: 12px;
        }

        .timeline > li > .timeline-item > .timeline-header {
            margin: 0;
            color: #555;
            border-bottom: 1px solid #f4f4f4;
            padding: 10px;
            font-size: 16px;
            line-height: 1.1;
        }

        .timeline > li > .timeline-item > .timeline-header > .badge {
            margin-left: 0;
        }

        .timeline > li > .timeline-item > .timeline-body,
        .timeline > li > .timeline-item > .timeline-footer {
            padding: 10px;
        }

        .timeline > li > .fa,
        .timeline > li > .fas,
        .timeline > li > .far,
        .timeline > li > .fab,
        .timeline > li > .glyphicon,
        .timeline > li > .ion {
            width: 30px;
            height: 30px;
            font-size: 15px;
            line-height: 30px;
            position: absolute;
            color: #666;
            background: #d2d6de;
            border-radius: 50%;
            text-align: center;
            left: 18px;
            top: 0;
        }

        .timeline > .time-label > span {
            font-weight: 600;
            padding: 5px;
            display: inline-block;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .timeline-inverse > li > .timeline-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        .timeline-inverse > li > .timeline-item > .timeline-header {
            border-bottom-color: #dee2e6;
        }

        /* Quick Actions Styling */
        .quick-actions .btn {
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .quick-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Communication Stats */
        .communication-stats .progress {
            height: 8px;
            margin-top: 5px;
        }

        /* Modal Enhancements */
        .modal-lg {
            max-width: 800px;
        }

        .modal-xl {
            max-width: 1140px;
        }

        /* Badge enhancements */
        .badge {
            font-size: 0.75em;
        }

        /* Timeline item hover effects */
        .timeline-item {
            transition: all 0.3s ease;
        }

        .timeline-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        /* SweetAlert wide popup */
        .swal-wide {
            width: 600px !important;
        }
    </style>
@endpush

@push('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
@endpush
@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Handle status change to show/hide suspension reason
    $('#status').on('change', function() {
        const selectedStatus = $(this).val();
        const suspensionContainer = $('#suspensionReasonContainer');

        if (selectedStatus === 'suspended') {
            suspensionContainer.slideDown();
            $('#suspension_reason').attr('required', true);
        } else {
            suspensionContainer.slideUp();
            $('#suspension_reason').attr('required', false);
        }
    });

    // Handle bulk action scheduling
    $('input[name="schedule_type"]').on('change', function() {
        const scheduleContainer = $('#schedule_datetime_container');
        if ($(this).val() === 'later') {
            scheduleContainer.slideDown();
            $('#schedule_datetime').attr('required', true);
        } else {
            scheduleContainer.slideUp();
            $('#schedule_datetime').attr('required', false);
        }
    });

    // Reset forms when modals are closed
    $('#sendMessageModal, #sendNotificationModal, #bulkActionModal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
    });

    // Admin Settings Form Submission
    $('#adminSettingsForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const shopId = {{ $shop->id }};

        // Validate suspension reason if status is suspended
        if (formData.get('status') === 'suspended' && !formData.get('suspension_reason').trim()) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Suspension reason is required when suspending a shop.'
            });
            return;
        }

        $.ajax({
            url: '{{ route("shop.update.settings", ":shopId") }}'.replace(':shopId', shopId),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-HTTP-Method-Override': 'PUT'
            },
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while updating settings.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join('\n');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage
                });
            }
        });
    });

    // Communication Tools Functions
    window.openMessageModal = function() {
        $('#sendMessageModal').modal('show');
    };

    window.openNotificationModal = function() {
        $('#sendNotificationModal').modal('show');
    };

    window.openBulkActionModal = function() {
        $('#bulkActionModal').modal('show');
    };

    // Quick Action Functions
    window.sendWelcomeMessage = function() {
        $('#message_subject').val('Welcome to DukaBase!');
        $('#message_content').val(`Dear {{ $owner ? $owner->name : 'Shop Owner' }},

Welcome to DukaBase! We're excited to have you join our platform.

Your shop "{{ $shop->name }}" has been successfully set up and is now ready for use. Here are some quick tips to get started:

1. **Add your first subshop** - Create locations for your business
2. **Set up your inventory** - Add products and manage stock levels
3. **Configure payment methods** - Enable different payment options
4. **Invite team members** - Add shopkeepers to help manage operations

If you need any assistance, feel free to contact our support team.

Best regards,
DukaBase Super Admin Team`);
        $('#sendMessageModal').modal('show');
    };

    window.sendStatusUpdate = function() {
        const currentStatus = '{{ $shop->status }}';
        const statusText = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);

        $('#message_subject').val(`Shop Status Update - ${statusText}`);
        $('#message_content').val(`Dear {{ $owner ? $owner->name : 'Shop Owner' }},

This is an important update regarding your shop "{{ $shop->name }}".

Current Status: ${statusText}

{{ $shop->status === 'active' ? 'Your shop is currently active and fully operational.' : '' }}
{{ $shop->status === 'inactive' ? 'Your shop is currently inactive. Some features may be limited until activation.' : '' }}
{{ $shop->status === 'suspended' ? 'Your shop has been suspended. Please contact support for more information.' : '' }}
{{ $shop->status === 'trial' ? 'Your shop is in trial mode. Consider upgrading for full features.' : '' }}

If you have any questions about this status or need assistance, please don't hesitate to contact us.

Best regards,
DukaBase Super Admin Team`);
        $('#sendMessageModal').modal('show');
    };

    window.sendSupportInfo = function() {
        $('#message_subject').val('DukaBase Support Information');
        $('#message_content').val(`Dear {{ $owner ? $owner->name : 'Shop Owner' }},

Here are the support resources available to help you with your DukaBase experience:

📧 **Email Support**: support@dukabase.com
📞 **Phone Support**: +1 (555) 123-4567
💬 **Live Chat**: Available 9 AM - 6 PM EST
📚 **Help Center**: https://help.dukabase.com
🎥 **Video Tutorials**: https://videos.dukabase.com

Common Support Topics:
• Setting up your first subshop
• Inventory management
• Point of sale operations
• Reporting and analytics
• User management

We're here to help you succeed!

Best regards,
DukaBase Support Team`);
        $('#sendMessageModal').modal('show');
    };

    window.sendWarning = function() {
        $('#message_subject').val('IMPORTANT: Action Required');
        $('#message_content').val(`Dear {{ $owner ? $owner->name : 'Shop Owner' }},

This is an important notification regarding your shop "{{ $shop->name }}".

⚠️ **ACTION REQUIRED**

We have noticed the following issue(s) that need your immediate attention:

[Please specify the issue here]

Failure to address this may result in:
• Temporary suspension of services
• Limited access to certain features
• Additional fees or penalties

Please respond to this message within 48 hours or contact support immediately.

Best regards,
DukaBase Compliance Team`);
        $('#message_urgent').prop('checked', true);
        $('#sendMessageModal').modal('show');
    };

    // Handle Message Form Submission
    $('#sendMessageForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const isUrgent = $('#message_urgent').is(':checked');

        // Prepare data for the API
        const messageType = formData.get('message_type');
        let deliveryMethods = [];

        if (messageType === 'both') {
            deliveryMethods = ['email', 'in_app'];
        } else if (messageType === 'email') {
            deliveryMethods = ['email'];
        } else if (messageType === 'notification') {
            deliveryMethods = ['in_app'];
        } else {
            deliveryMethods = ['in_app']; // default
        }

        const messageData = {
            subject: formData.get('subject'),
            content: formData.get('content'),
            type: messageType === 'both' ? 'email' : messageType,
            priority: isUrgent ? 'urgent' : 'normal',
            is_urgent: isUrgent, // Always send boolean value
            delivery_methods: deliveryMethods,
            shop_id: {{ $shop->id }} // Add shop_id for configure shop context
        };

        // Show loading
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: '{{ route("messages.store") }}',
            method: 'POST',
            data: messageData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                // Add to timeline
                addCommunicationItem('message', messageData.subject, messageData.content, messageData.type);
                updateMessageCount();

                $('#sendMessageModal').modal('hide');
                submitBtn.prop('disabled', false).html(originalText);

                Swal.fire({
                    icon: 'success',
                    title: 'Message Sent!',
                    text: response.message || 'Your message has been sent successfully.',
                    timer: 2000,
                    showConfirmButton: false
                });
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while sending the message.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                    if (xhr.responseJSON.errors) {
                        const errors = [];
                        for (const field in xhr.responseJSON.errors) {
                            errors.push(xhr.responseJSON.errors[field].join(', '));
                        }
                        errorMessage += '\n\nDetails:\n' + errors.join('\n');
                    }
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join('\n');
                }

                submitBtn.prop('disabled', false).html(originalText);

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage,
                    customClass: {
                        popup: 'swal-wide'
                    }
                });
            }
        });
    });

    // Handle Notification Form Submission
    $('#sendNotificationForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const sendEmail = $('#notification_email').is(':checked');

        // Prepare data for the API
        const messageData = {
            subject: `Notification: ${formData.get('title')}`,
            content: formData.get('message'),
            type: 'notification',
            priority: formData.get('priority'),
            is_urgent: false,
            delivery_methods: sendEmail ? ['in_app', 'email'] : ['in_app'],
            shop_id: {{ $shop->id }} // Add shop_id for configure shop context
        };

        // Show loading
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: '{{ route("messages.store") }}',
            method: 'POST',
            data: messageData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                // Add to timeline
                const priorityClass = getPriorityClass(formData.get('priority'));
                addCommunicationItem('notification', formData.get('title'), formData.get('message'), 'notification', priorityClass);
                updateMessageCount();

                $('#sendNotificationModal').modal('hide');
                submitBtn.prop('disabled', false).html(originalText);

                Swal.fire({
                    icon: 'success',
                    title: 'Notification Sent!',
                    text: response.message || 'Your notification has been sent successfully.',
                    timer: 2000,
                    showConfirmButton: false
                });
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while sending the notification.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                    if (xhr.responseJSON.errors) {
                        const errors = [];
                        for (const field in xhr.responseJSON.errors) {
                            errors.push(xhr.responseJSON.errors[field].join(', '));
                        }
                        errorMessage += '\n\nDetails:\n' + errors.join('\n');
                    }
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join('\n');
                }

                submitBtn.prop('disabled', false).html(originalText);

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage,
                    customClass: {
                        popup: 'swal-wide'
                    }
                });
            }
        });
    });

    // Handle Bulk Action Form Submission
    $('#bulkActionForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        // Get selected recipients (shopkeepers that are checked)
        const recipients = [];
        $('.bulk-recipient:checked').each(function() {
            recipients.push(parseInt($(this).val()));
        });

        // Always include owner if they exist
        const ownerId = {{ $owner ? $owner->id : 'null' }};
        if (ownerId && !recipients.includes(ownerId)) {
            recipients.unshift(ownerId); // Add owner at the beginning
        }

        if (recipients.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Recipients',
                text: 'Please select at least one recipient.'
            });
            return;
        }

        // Prepare data for the API
        const messageData = {
            subject: formData.get('subject'),
            content: formData.get('content'),
            type: 'bulk',
            priority: 'normal',
            is_urgent: false,
            delivery_methods: ['in_app', 'email'],
            recipients: recipients,
            shop_id: {{ $shop->id }} // Add shop_id for configure shop context
        };

        // Show loading
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: '{{ route("messages.store") }}',
            method: 'POST',
            data: messageData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                // Add to timeline
                const actionTypeText = getActionTypeText(formData.get('action_type'));
                addCommunicationItem('bulk', formData.get('subject'), `${actionTypeText} sent to ${recipients.length} recipient(s)`, 'bulk');

                updateMessageCount();

                $('#bulkActionModal').modal('hide');
                submitBtn.prop('disabled', false).html(originalText);

                Swal.fire({
                    icon: 'success',
                    title: 'Bulk Message Sent!',
                    text: `Your message has been sent to ${recipients.length} recipient(s).`,
                    timer: 2000,
                    showConfirmButton: false
                });
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while sending the bulk message.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                    if (xhr.responseJSON.errors) {
                        const errors = [];
                        for (const field in xhr.responseJSON.errors) {
                            errors.push(xhr.responseJSON.errors[field].join(', '));
                        }
                        errorMessage += '\n\nDetails:\n' + errors.join('\n');
                    }
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join('\n');
                }

                submitBtn.prop('disabled', false).html(originalText);

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage,
                    customClass: {
                        popup: 'swal-wide'
                    }
                });
            }
        });
    });
});

// Load recent communications on page load
loadRecentCommunications();

// Helper Functions
function addCommunicationItem(type, title, content, messageType, priorityClass = '') {
    const now = new Date();
    const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    const dateString = now.toLocaleDateString();

    let iconClass = 'fas fa-envelope';
    let badgeClass = 'badge-info';
    let typeLabel = 'Message';

    switch(type) {
        case 'message':
            iconClass = messageType === 'email' ? 'fas fa-envelope' : 'fas fa-bell';
            badgeClass = messageType === 'email' ? 'badge-success' : 'badge-info';
            typeLabel = messageType === 'email' ? 'Email' : 'Notification';
            break;
        case 'notification':
            iconClass = 'fas fa-bell';
            badgeClass = priorityClass || 'badge-info';
            typeLabel = 'Notification';
            break;
        case 'bulk':
            iconClass = 'fas fa-bullhorn';
            badgeClass = 'badge-warning';
            typeLabel = 'Bulk Message';
            break;
    }

    const timelineItem = `
        <div class="timeline-item">
            <span class="time"><i class="fas fa-clock"></i> ${timeString}</span>
            <h3 class="timeline-header">
                <span class="badge ${badgeClass}"><i class="${iconClass}"></i> ${typeLabel}</span>
                ${title}
            </h3>
            <div class="timeline-body">
                ${content.length > 100 ? content.substring(0, 100) + '...' : content}
            </div>
        </div>
    `;

    $('#communicationItems').prepend(timelineItem);
}

function loadRecentCommunications() {
    $.ajax({
        url: '{{ route("api.messages.recent") }}',
        method: 'GET',
        data: {
            shop_id: {{ $shop->id }} // Pass shop_id for configure shop context
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.messages && response.messages.length > 0) {
                // Clear existing items except the initial system message
                $('#communicationItems').find('.timeline-item').not(':first').remove();

                // Add recent messages to timeline
                response.messages.forEach(function(message) {
                    addMessageToTimeline(message);
                });
            }
        },
        error: function(xhr) {
            console.log('Failed to load recent communications');
        }
    });
}

function addMessageToTimeline(message) {
    let iconClass = 'fas fa-envelope';
    let badgeClass = 'badge-info';
    let typeLabel = 'Message';

    switch(message.type) {
        case 'email':
            iconClass = 'fas fa-envelope';
            badgeClass = 'badge-success';
            typeLabel = 'Email';
            break;
        case 'notification':
            iconClass = 'fas fa-bell';
            badgeClass = message.badge_class || 'badge-info';
            typeLabel = 'Notification';
            break;
        case 'bulk':
            iconClass = 'fas fa-bullhorn';
            badgeClass = 'badge-warning';
            typeLabel = 'Bulk Message';
            break;
    }

    const timelineItem = `
        <div class="timeline-item">
            <span class="time"><i class="fas fa-clock"></i> ${message.created_at}</span>
            <h3 class="timeline-header">
                <span class="badge ${badgeClass}"><i class="${iconClass}"></i> ${typeLabel}</span>
                ${message.subject}
            </h3>
            <div class="timeline-body">
                From: ${message.sender}
            </div>
        </div>
    `;

    $('#communicationItems').prepend(timelineItem);
}

function updateMessageCount() {
    const currentCount = parseInt($('#messageCount').text()) || 0;
    const newCount = currentCount + 1;
    $('#messageCount').text(newCount);

    const progressBar = $('#messageCount').closest('.info-box').find('.progress-bar');
    const newWidth = Math.min((newCount / 10) * 100, 100); // Assuming max 10 messages for demo
    progressBar.css('width', newWidth + '%');
}

function getPriorityClass(priority) {
    switch(priority) {
        case 'urgent': return 'badge-danger';
        case 'high': return 'badge-warning';
        case 'normal': return 'badge-info';
        case 'low': return 'badge-secondary';
        default: return 'badge-info';
    }
}

function getActionTypeText(actionType) {
    const types = {
        'announcement': 'General Announcement',
        'maintenance': 'Maintenance Notice',
        'policy_update': 'Policy Update',
        'feature_update': 'Feature Update',
        'urgent_alert': 'Urgent Alert'
    };
    return types[actionType] || 'Message';
}

// Plan Management Functions
$(document).ready(function() {
    // Handle Upgrade Plan Form Submission
    $('#upgradePlanForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const shopId = {{ $shop->id }};

        // Show loading
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: `/shops/${shopId}/upgrade-plan`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#upgradePlanForm')[0].reset();
                submitBtn.prop('disabled', false).html(originalText);

                Swal.fire({
                    icon: 'success',
                    title: 'Plan Upgraded!',
                    text: response.message || 'Plan has been upgraded and payment recorded successfully.',
                    timer: 3000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while upgrading the plan.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join('\n');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                submitBtn.prop('disabled', false).html(originalText);

                Swal.fire({
                    icon: 'error',
                    title: 'Upgrade Failed!',
                    text: errorMessage
                });
            }
        });
    });

    // Handle Record Payment Form Submission
    $('#recordPaymentForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const shopId = {{ $shop->id }};

        // Show loading
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Recording...');

        $.ajax({
            url: `/shops/${shopId}/record-payment`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#recordPaymentForm')[0].reset();
                $('#payment_date').val('{{ date('Y-m-d') }}');
                submitBtn.prop('disabled', false).html(originalText);

                // Insert new payment row at the top of the history table
                const payment = response.payment || {};
                const currency = payment.currency || '{{ $shop->currency ?? 'TZS' }}';
                const amountText = `${currency} ${Number(payment.amount || 0).toFixed(2)}`;
                const methodLabel = (payment.paymentMethod && payment.paymentMethod.name) ? payment.paymentMethod.name : 'Unknown';
                const status = (payment.status || '').toLowerCase();
                const statusClass = status === 'completed' ? 'badge-success' : (status === 'pending' || status === 'partial' ? 'badge-warning' : 'badge-danger');
                const dateText = payment.payment_date ? (new Date(payment.payment_date)).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '';

                const planSelect = document.getElementById('payment_plan');
                const planName = planSelect && planSelect.options[planSelect.selectedIndex] ? planSelect.options[planSelect.selectedIndex].text.split(' - ')[0] : 'N/A';

                const $tbody = $('#paymentsHistoryBody');
                const rowHtml = `
                    <tr data-payment-id="${payment.id}">
                        <td>${dateText}</td>
                        <td>${planName}</td>
                        <td>${amountText}</td>
                        <td><span class="badge badge-secondary">${methodLabel}</span></td>
                        <td><span class="badge ${statusClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
                        <td>${payment.transaction_id || '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-payment"
                                data-id="${payment.id}"
                                data-amount="${payment.amount}"
                                data-payment-method="${payment.payment_method_id || (payment.paymentMethod ? payment.paymentMethod.id : '')}"
                                data-transaction-id="${payment.transaction_id || ''}"
                                data-notes="${(payment.notes || '').toString().replace(/"/g, '&quot;')}"
                                data-payment-date="${payment.payment_date ? new Date(payment.payment_date).toISOString().slice(0,10) : ''}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-payment" data-id="${payment.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                $tbody.prepend(rowHtml);

                // Recompute Total Paid after adding
                updateTotalPaidDisplay();

                Swal.fire({
                    icon: 'success',
                    title: 'Payment Recorded!',
                    text: response.message || 'Payment has been recorded successfully.',
                    timer: 2000,
                    showConfirmButton: false
                });
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while recording the payment.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join('\n');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                submitBtn.prop('disabled', false).html(originalText);

                Swal.fire({
                    icon: 'error',
                    title: 'Recording Failed!',
                    text: errorMessage
                });
            }
        });
    });
});

// Cancel Subscription Function
function cancelSubscription(subscriptionId) {
    Swal.fire({
        title: 'Cancel Subscription?',
        text: 'Are you sure you want to cancel this subscription? This action cannot be undone.',
        icon: 'warning',
        input: 'textarea',
        inputPlaceholder: 'Reason for cancellation (optional)',
        inputAttributes: {
            'aria-label': 'Reason for cancellation'
        },
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Cancel Subscription',
        cancelButtonText: 'Keep Subscription'
    }).then((result) => {
        if (result.isConfirmed) {
            const cancellationReason = result.value;

            $.ajax({
                url: `/subscriptions/${subscriptionId}/cancel`,
                method: 'POST',
                data: {
                    reason: cancellationReason,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Subscription Cancelled',
                        text: response.message || 'Subscription has been cancelled successfully.',
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function(xhr) {
                    let errorMessage = 'An error occurred while cancelling the subscription.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Cancellation Failed!',
                        text: errorMessage
                    });
                }
            });
        }
    });
}

// Renew Subscription Function
function renewSubscription(subscriptionId) {
    Swal.fire({
        title: 'Renew Subscription?',
        text: 'This will extend the subscription for another billing period.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Renew Now',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/subscriptions/${subscriptionId}/renew`,
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Subscription Renewed!',
                        text: response.message || 'Subscription has been renewed successfully.',
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function(xhr) {
                    let errorMessage = 'An error occurred while renewing the subscription.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Renewal Failed!',
                        text: errorMessage
                    });
                }
            });
        }
    });
}

// Auto-fill amount when plan is selected in upgrade form
$(document).on('change', '#new_plan', function() {
    const planId = $(this).val();
    if (planId) {
        // Find the selected option text and extract price
        const optionText = $(this).find('option:selected').text();
        const priceMatch = optionText.match(/\$([0-9.]+)/);
        if (priceMatch && priceMatch[1]) {
            $('#upgrade_amount').val(priceMatch[1]);
        }
    }
});

// Auto-fill amount when plan is selected in payment form
$(document).on('change', '#payment_plan', function() {
    const planId = $(this).val();
    if (planId) {
        // Find the selected option text and extract price
        const optionText = $(this).find('option:selected').text();
        const priceMatch = optionText.match(/\$([0-9.]+)/);
        if (priceMatch && priceMatch[1]) {
            $('#payment_amount').val(priceMatch[1]);
        }
    }
});

// Edit Message Function - Updated to use data attributes
$(document).on('click', '.edit-message-btn', function(e) {
    e.preventDefault();

    const button = $(this);
    const messageId = button.data('message-id');
    const subject = button.data('subject');
    const content = button.data('content');
    const priority = button.data('priority');
    const isUrgent = button.data('urgent') === 'true';

    $('#edit_message_id').val(messageId);
    $('#edit_subject').val(subject);
    $('#edit_content').val(content);
    $('#edit_priority').val(priority);
    $('#edit_urgent').prop('checked', isUrgent);
    $('#editMessageModal').modal('show');
});

// Delete Message Function - Updated to use data attributes
$(document).on('click', '.delete-message-btn', function(e) {
    e.preventDefault();

    const button = $(this);
    const messageId = button.data('message-id');
    const subject = button.data('subject');

    Swal.fire({
        title: 'Delete Message?',
        text: `Are you sure you want to delete the message "${subject}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Delete Message',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/messages/${messageId}`,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    // Remove the message from the timeline
                    $(`.timeline-item[data-message-id="${messageId}"]`).fadeOut(300, function() {
                        $(this).remove();
                    });

                    updateMessageCount();

                    Swal.fire({
                        icon: 'success',
                        title: 'Message Deleted',
                        text: response.message || 'Message has been deleted successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                error: function(xhr) {
                    let errorMessage = 'An error occurred while deleting the message.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Deletion Failed!',
                        text: errorMessage
                    });
                }
            });
        }
    });
});

// Handle Edit Message Form Submission
$('#editMessageForm').on('submit', function(e) {
    e.preventDefault();

    const messageId = $('#edit_message_id').val();
    const formData = {
        subject: $('#edit_subject').val(),
        content: $('#edit_content').val(),
        priority: $('#edit_priority').val(),
        is_urgent: $('#edit_urgent').is(':checked') ? 1 : 0
    };

    // Show loading
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

    $.ajax({
        url: `/messages/${messageId}`,
        method: 'PUT',
        data: formData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            // Update the message in the timeline
            const messageItem = $(`.timeline-item[data-message-id="${messageId}"]`);
            const priorityBadge = messageItem.find('.badge');
            const headerText = messageItem.find('.timeline-header strong');
            const bodyText = messageItem.find('.timeline-body p');

            // Update priority badge
            priorityBadge.removeClass('badge-info badge-warning badge-danger badge-secondary')
                        .addClass(response.message_data.priority === 'urgent' ? 'badge-danger' :
                                 response.message_data.priority === 'high' ? 'badge-warning' : 'badge-info')
                        .html(`${response.message_data.priority.charAt(0).toUpperCase() + response.message_data.priority.slice(1)}${response.message_data.is_urgent ? ' <i class="fas fa-exclamation-triangle ml-1"></i>' : ''}`);

            // Update subject and content
            headerText.text(response.message_data.subject);
            bodyText.text(response.message_data.content.substring(0, 200) + (response.message_data.content.length > 200 ? '...' : ''));

            // Add edited indicator
            const editedIndicator = messageItem.find('.timeline-body small .text-muted');
            if (editedIndicator.length === 0) {
                messageItem.find('.timeline-body small').append(` <span class="text-muted">(edited ${response.message_data.updated_at})</span>`);
            }

            $('#editMessageModal').modal('hide');
            submitBtn.prop('disabled', false).html(originalText);

            Swal.fire({
                icon: 'success',
                title: 'Message Updated!',
                text: response.message,
                timer: 2000,
                showConfirmButton: false
            });
        },
        error: function(xhr) {
            let errorMessage = 'An error occurred while updating the message.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                const errors = Object.values(xhr.responseJSON.errors).flat();
                errorMessage = errors.join('\n');
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }

            submitBtn.prop('disabled', false).html(originalText);

            Swal.fire({
                icon: 'error',
                title: 'Update Failed!',
                text: errorMessage
            });
        }
    });
});

// Update message count function
function updateMessageCount() {
    const currentCount = parseInt($('#messageCount').text()) || 0;
    $('#messageCount').text(currentCount + 1);

    // Update progress bar
    const percentage = Math.min((currentCount + 1) / 10 * 100, 100);
    $('.communication-stats .progress-bar').css('width', percentage + '%');
}

// Reset edit form when modal is closed
$('#editMessageModal').on('hidden.bs.modal', function () {
    $('#editMessageForm')[0].reset();
});

// Handle edit payment button click
$(document).on('click', '.edit-payment', function() {
    const paymentId = $(this).data('id');
    const paymentDate = $(this).data('payment-date');
    const amount = parseFloat($(this).data('amount'));
    const paymentMethod = $(this).data('payment-method');
    const transactionId = $(this).data('transaction-id');
    const notes = $(this).data('notes');
    
    // Populate the edit modal
    $('#editPaymentModal').modal('show');
    $('#edit_payment_id').val(paymentId);
    $('#edit_payment_date').val(paymentDate);
    $('#edit_amount').val(amount.toFixed(2));
    $('#edit_payment_method').val(paymentMethod);
    $('#edit_transaction_id').val(transactionId || '');
    $('#edit_notes').val(notes || '');
});
// Handling payment edit form submission (use delegated binding to ensure handler attaches even if form is rendered later)
$(document).on('submit', '#editPaymentForm', function(e) {
    e.preventDefault();
    
    // Prevent multiple submissions
    const $form = $(this);
    if ($form.data('submitting')) return false;
    $form.data('submitting', true);

    const paymentId = $('#edit_payment_id').val();
    const formData = new FormData(this);
    formData.append('_method', 'PUT');

    // Show loading state
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: `/payments/${paymentId}`,
        type: 'POST', // Laravel will treat this as PUT due to _method=PUT
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                toastr.success('Payment updated successfully');
                $('#editPaymentModal').modal('hide');
                // Reset form and update UI
                submitBtn.prop('disabled', false).html(originalBtnText);
                $form.data('submitting', false);
                // Update the corresponding row in the Payment History table
                const payment = response.payment || {};
                const $row = $(`tr[data-payment-id="${paymentId}"]`);
                if ($row.length) {
                    // Formatters
                    const currency = payment.currency || '{{ $shop->currency ?? 'TZS' }}';
                    const amountText = `${currency} ${Number(payment.amount || 0).toFixed(2)}`;
                    const methodLabel = (payment.payment_method && typeof payment.payment_method === 'string') ? payment.payment_method : (payment.payment_method_label || (payment.paymentMethod ? payment.paymentMethod.name : 'Unknown'));
                    const status = (payment.status || '').toLowerCase();
                    const statusClass = status === 'completed' ? 'badge-success' : (status === 'pending' || status === 'partial' ? 'badge-warning' : 'badge-danger');
                    const dateText = payment.payment_date ? (new Date(payment.payment_date)).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : $row.find('td').eq(0).text();

                    // Set columns: 0 Date, 2 Amount, 3 Method, 4 Status, 5 Transaction
                    $row.find('td').eq(0).text(dateText);
                    $row.find('td').eq(2).text(amountText);
                    $row.find('td').eq(3).find('span.badge').text(methodLabel);
                    $row.find('td').eq(4).find('span.badge').removeClass('badge-success badge-warning badge-danger').addClass(statusClass).text(status.charAt(0).toUpperCase() + status.slice(1));
                    $row.find('td').eq(5).text(payment.transaction_id || '-');

                    // Update edit button data attributes for future edits
                    const $editBtn = $row.find('button.edit-payment');
                    $editBtn.data('amount', payment.amount);
                    $editBtn.data('payment-method', payment.payment_method_id || (payment.paymentMethod ? payment.paymentMethod.id : $editBtn.data('payment-method')));
                    $editBtn.data('transaction-id', payment.transaction_id || '');
                    $editBtn.data('notes', payment.notes || '');
                    if (payment.payment_date) {
                        // Ensure date in YYYY-MM-DD
                        const d = new Date(payment.payment_date);
                        const y = d.getFullYear();
                        const m = ('0' + (d.getMonth() + 1)).slice(0, 2);
                        const day = ('0' + d.getDate()).slice(0, 2);
                        $editBtn.data('payment-date', `${y}-${('0' + (d.getMonth()+1)).slice(-2)}-${('0' + d.getDate()).slice(-2)}`);
                    }
                }
                // Recompute Total Paid after edit
                updateTotalPaidDisplay();
            } else {
                toastr.error(response.message || 'Failed to update payment');
                submitBtn.prop('disabled', false).html(originalBtnText);
                $form.data('submitting', false);
            }
        },
        error: function(xhr) {
            let errorMsg = 'An error occurred while updating the payment';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                } catch (e) {
                    errorMsg = xhr.responseText || errorMsg;
                }
            }
            toastr.error('Payment update error: ' + (xhr.responseJSON?.message || 'Unknown error'));
            submitBtn.prop('disabled', false).html(originalBtnText);
            
            // Log the full error to console for debugging
            console.error('Payment update error:', xhr);
            
            // Reset submitting state
            $form.data('submitting', false);
            return false;
        }
    });
});

// Handle delete payment button click
$(document).on('click', '.delete-payment', function() {
    const paymentId = $(this).data('id');
    const paymentRow = $(this).closest('tr');
    
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently delete this payment record!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/payments/${paymentId}`,
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row from the table
                        paymentRow.fadeOut(400, function() {
                            $(this).remove();
                            // Recompute Total Paid after delete
                            updateTotalPaidDisplay();
                        });
                        toastr.success('Payment deleted successfully');
                    } else {
                        toastr.error(response.message || 'Failed to delete payment');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.message || 'An error occurred while deleting the payment';
                    toastr.error(errorMsg);
                }
            });
        }
    });
});

</script>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" role="dialog" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPaymentModalLabel">Edit Payment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editPaymentForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" id="edit_payment_id" name="id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_payment_date">Payment Date</label>
                        <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_amount">Amount</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">{{ $shop->currency ?? 'TZS' }}</span>
                            </div>
                            <input type="number" step="0.01" class="form-control" id="edit_amount" name="amount" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_payment_method">Payment Method</label>
                        <select class="form-control" id="edit_payment_method" name="payment_method_id" required>
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_transaction_id">Transaction ID</label>
                        <input type="text" class="form-control" id="edit_transaction_id" name="transaction_id">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select class="form-control" id="edit_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="completed" selected>Completed</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_notes">Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

@stop