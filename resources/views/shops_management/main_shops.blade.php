@extends('adminlte::page')

@section('title', 'Main Shops Management')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body text-center">
            <h1 class="d-none d-md-block text-light"><i class="fas fa-store text-warning"></i> <strong>DB</strong> Main Shops Management Panel</h1>
            <h1 class="d-md-none text-light"><i class="fas fa-store text-warning"></i> <strong>DB</strong> Main Shops</h1>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item active text-dark d-none d-md-inline" aria-current="page">Main Shops Management Panel</li>
                <li class="breadcrumb-item active text-dark d-md-none" aria-current="page">Main Shops</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <!-- SweetAlert Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Flash Messages -->
    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Imefanikiwa!',
                text: "{{ session('success') }}",
                showConfirmButton: false,
                timer: 2500
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Kuna Tatizo!',
                text: "{{ session('error') }}",
                showConfirmButton: true
            });
        </script>
    @endif

    @if (session('warning'))
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Angalizo!',
                text: "{{ session('warning') }}",
                showConfirmButton: true
            });
        </script>
    @endif

    @if (session('info'))
        <script>
            Swal.fire({
                icon: 'info',
                title: 'Taarifa',
                text: "{{ session('info') }}",
                showConfirmButton: false,
                timer: 2500
            });
        </script>
    @endif

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-primary">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="ml-3">
                            <h6 class="card-title mb-1">Total Shops</h6>
                            <h3 class="mb-0">{{ $shops->total() ?? 0 }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="ml-3">
                            <h6 class="card-title mb-1">Active Shops</h6>
                            <h3 class="mb-0">{{ $shops->where('is_active', true)->count() ?? 0 }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-warning">
                            <i class="fas fa-pause-circle"></i>
                        </div>
                        <div class="ml-3">
                            <h6 class="card-title mb-1">Inactive Shops</h6>
                            <h3 class="mb-0">{{ $shops->where('is_active', false)->count() ?? 0 }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-info">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="ml-3">
                            <h6 class="card-title mb-1">This Month</h6>
                            <h3 class="mb-0">{{ $shops->where('created_at', '>=', now()->startOfMonth())->count() ?? 0 }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Actions Section -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
            <h5 class="mb-0"><i class="fas fa-search"></i> Shop Management</h5>
        </div>
        <div class="card-body">
            <!-- Search Bar -->
            <div class="row mb-3 align-items-center search-toolbar">
                <div class="col-lg-6 col-md-8 ">
                    <form method="GET" action="{{ route('shopsmanagement.show') }}" class="d-flex">
                        <div class="input-group input-group ">
                            <div class="input-group-prepend d-none d-sm-flex">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" name="search" class="form-control" placeholder="Search by shop name, phone, or address" value="{{ request('search') }}" aria-label="Search shops">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                                @if(request('search'))
                                    <a href="{{ route('shopsmanagement.show') }}" class="btn btn-outline-secondary ml-1" title="Clear search">
                                        <i class="fas fa-times"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4 col-md-12 text-lg-right mt-3 mt-lg-0">
                    @if(isset($shops) && method_exists($shops, 'total'))
                        <span class="text-muted">
                            <strong>{{ $shops->firstItem() ?? 0 }}–{{ $shops->lastItem() ?? 0 }}</strong> of <strong>{{ $shops->total() }}</strong> shops
                        </span>
                    @endif
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start mb-4">
                <a href="{{ route('payments') }}" class="btn btn-success btn-sm m-2">
                    <i class="fas fa-credit-card"></i> Manage Plans & Payment Methods
                </a>
                <button type="button" class="btn btn-primary btn-sm m-2" onclick="refreshPage()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Main Shops Table -->
    <div class="card mt-4">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 " id="shopsTable">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width: 40px">#</th>
                            <th><i class="fas fa-store"></i> Shop</th>
                            <th><i class="fas fa-phone"></i> Phone</th>
                            <th><i class="fas fa-map-marker-alt"></i> Address</th>
                            <th><i class="fas fa-calendar"></i> Created</th>
                            <th><i class="fas fa-tags"></i> Plan</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th><i class="fas fa-sms"></i> SMS (This Month)</th>
                            <th class="text-right" style="width: 160px"><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shops as $index => $shop)
                            <tr>
                                <td>{{ isset($shops) && method_exists($shops, 'firstItem') ? $shops->firstItem() + $index : ($index + 1) }}</td>
                                <td title="{{ $shop->name ?? 'N/A' }}">
                                    <div class="d-flex align-items-center" style="min-width: 200px;">
                                        <div class="avatar-circle bg-primary text-white mr-2" style="width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px;">
                                            {{ strtoupper(substr($shop->name ?? 'N', 0, 1)) }}
                                        </div>
                                        <div class="text-truncate" style="max-width: 260px;">
                                            <strong>{{ $shop->name ?? 'N/A' }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-truncate" title="{{ $shop->phone ?? 'N/A' }}">{{ $shop->phone ?? 'N/A' }}</td>
                                <td class="text-truncate" title="{{ $shop->address ?? 'N/A' }}">{{ $shop->address ?? 'N/A' }}</td>
                                <td>{{ $shop->created_at ? $shop->created_at->format('M d, Y') : 'N/A' }}</td>
                                <td>
                                    @php
                                        $plan = method_exists($shop, 'currentPlan') ? $shop->currentPlan() : null;
                                        $activeSub = method_exists($shop, 'activeSubscription') ? $shop->activeSubscription() : null;
                                        $statusBadge = null; $statusText = null;
                                        if ($activeSub && method_exists($activeSub, 'isActive') && $activeSub->isActive()) {
                                            $statusBadge = 'badge-success';
                                            $statusText = 'Active';
                                        } else {
                                            // Find the most relevant recent subscription: prefer with end_date, then latest created
                                            $lastSub = null;
                                            if (method_exists($shop, 'subscriptions')) {
                                                $lastSub = $shop->subscriptions()
                                                    ->orderByRaw('end_date IS NULL') // non-null end_date first
                                                    ->orderByDesc('end_date')
                                                    ->orderByDesc('created_at')
                                                    ->first();
                                            }
                                            $expiredDetected = false;
                                            if ($lastSub && method_exists($lastSub, 'isExpired') && $lastSub->isExpired()) {
                                                $expiredDetected = true;
                                            } elseif (method_exists($shop, 'subscriptions')) {
                                                // Fallback: any sub with past end_date or explicit status expired
                                                $expiredDetected = $shop->subscriptions()
                                                    ->where(function($q){
                                                        $q->whereNotNull('end_date')->where('end_date', '<', now())
                                                          ->orWhere('status', 'expired');
                                                    })
                                                    ->exists();
                                            }
                                            if ($expiredDetected) {
                                                $statusBadge = 'badge-danger';
                                                $statusText = 'Expired';
                                            }
                                        }
                                    @endphp
                                    @if($plan)
                                        <div class="d-flex align-items-center flex-wrap" style="gap: .25rem;">
                                            <span class="badge badge-info">{{ $plan->name ?? 'Assigned' }}</span>
                                            @if($statusBadge && $statusText)
                                                <span class="badge {{ $statusBadge }}">{{ $statusText }}</span>
                                            @endif
                                            @php
                                                $daysLeft = isset($activeSub) && $activeSub ? $activeSub->days_until_expiration : null;
                                            @endphp
                                            @if(isset($activeSub) && $activeSub && method_exists($activeSub,'isActive') && $activeSub->isActive() && $daysLeft !== null && $daysLeft < 10)
                                                <span class="badge badge-warning" title="{{ $activeSub->end_date ? 'Ends '.$activeSub->end_date->format('M d, Y') : '' }}">
                                                    Expiring Soon{{ $daysLeft !== null ? ' ('.$daysLeft.'d)' : '' }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        @if($statusBadge === 'badge-danger')
                                            <span class="badge badge-danger">Expired</span>
                                        @else
                                            <span class="badge badge-secondary">No Plan</span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-pill {{ ($shop->is_active ?? false) ? 'badge-success' : 'badge-secondary' }} badge-status">
                                        {{ ($shop->is_active ?? false) ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $smsCount = \App\Models\SmsLog::where('shop_id', $shop->id)
                                            ->where('status', 'sent')
                                            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                                            ->count();
                                    @endphp
                                    <span class="badge badge-info">{{ $smsCount }}</span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('configure.shop', ['id' => $shop->id]) }}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-cog"></i> Configure
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-store-slash fa-4x text-muted mb-4"></i>
                                        <h4 class="text-muted">No Main Shops Found</h4>
                                        <p class="text-muted mb-4">There are no shops registered in the system yet.</p>
                                        <div class="text-center">
                                            <button type="button" class="btn btn-primary" onclick="refreshPage()">
                                                <i class="fas fa-sync-alt"></i> Refresh Page
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    @if(isset($shops) && method_exists($shops, 'links'))
        <div class="d-flex justify-content-center mt-4">
            {{ $shops->links() }}
        </div>
    @endif

@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush

<style>
    /* ====================================
       CSS ROOT VARIABLES - MAIN SHOPS DESIGN
       ==================================== */
    :root {
        --primary-color: #FFA500;
        --secondary-color: #FF6347;
        --gradient: linear-gradient(135deg, #FFA500, #FF6347);
        --accent-color: #e94560;
        --success-color: #28a745;
        --danger-color: #dc3545;
        --warning-color: #ffc107;
        --info-color: #17a2b8;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
        --white: #ffffff;
        --shadow-light: 0 2px 10px rgba(0, 78, 146, 0.1);
        --shadow-medium: 0 4px 20px rgba(0, 78, 146, 0.15);
        --shadow-heavy: 0 8px 32px rgba(0, 78, 146, 0.2);
        --border-radius: 12px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Statistics Cards */
    .stats-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-light);
        transition: var(--transition);
        background: var(--white);
        overflow: hidden;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
    }

    .stats-card .card-body {
        padding: 1.5rem;
    }

    .stats-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .stats-card h3 {
        font-weight: 700;
        color: var(--dark-color);
    }

    .stats-card h6 {
        color: #6c757d;
        font-weight: 600;
    }

    /* Main Shop Cards */
    .main-shop-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-light);
        overflow: hidden;
        position: relative;
        transition: var(--transition);
        background: var(--white);
    }

    .main-shop-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient);
    }

    .main-shop-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-heavy);
    }

    .main-shop-card .card-header {
        font-weight: 600;
        padding: 1rem 1.25rem;
        border: none;
    }

    .main-shop-card .card-body {
        padding: 1.25rem;
    }

    .shop-info {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .shop-info .info-item {
        display: flex;
        align-items: center;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 3px solid var(--primary-color);
        transition: var(--transition);
    }

    .shop-info .info-item:hover {
        transform: translateX(3px);
        background: #e9ecef;
    }

    .shop-info .info-item i {
        width: 20px;
        margin-right: 0.75rem;
        color: var(--primary-color);
        font-size: 0.9rem;
    }

    .main-shop-card .card-footer {
        padding: 0.75rem 1.25rem;
        border-top: 1px solid rgba(0,0,0,0.1);
    }

    /* Empty State */
    .empty-state {
        border: 2px dashed rgba(0, 78, 146, 0.3);
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-radius: var(--border-radius);
        text-align: center;
        padding: 4rem 2rem;
        position: relative;
        overflow: hidden;
    }

    .empty-state::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: var(--gradient);
        opacity: 0.02;
        transform: rotate(45deg);
        pointer-events: none;
    }

    .empty-state i {
        color: rgba(0, 78, 146, 0.4);
        margin-bottom: 1.5rem;
    }

    .empty-state h4 {
        color: var(--dark-color);
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .empty-state p {
        color: #6c757d;
        font-size: 1rem;
        margin-bottom: 2rem;
    }

    /* Search Toolbar */
    .search-toolbar .input-group .form-control,
    .search-toolbar .input-group .input-group-text,
    .search-toolbar .input-group .btn {
        height: 40px;
    }

    .search-toolbar .input-group .input-group-text {
        background: #fff;
        border-right: 0;
    }

    .search-toolbar .input-group .form-control {
        border-left: 0;
        box-shadow: none !important;
    }

    .search-toolbar .input-group .btn {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    .search-toolbar .input-group .form-control,
    .search-toolbar .input-group .input-group-text {
        border-color: #ced4da;
    }

    /* Badge Styles */
    .badge-success {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
    }

    .badge-secondary {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
    }

    .badge-status {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        line-height: 1;
        font-weight: 600;
    }

    /* Button Enhancements */
    .btn-primary {
        background: var(--gradient);
        border: none;
        border-radius: 8px;
        padding: 0.5rem 1.5rem;
        font-weight: 600;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, rgba(255,255,255,0.2), rgba(255,255,255,0));
        transition: left 0.3s ease;
    }

    .btn-primary:hover::before {
        left: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
        background: var(--gradient);
    }

    /* Utilities */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .main-shop-card .card-body {
            padding: 1rem;
        }

        .main-shop-card .card-footer {
            padding: 0.75rem 1rem;
        }

        .shop-info .info-item {
            padding: 0.4rem 0.5rem;
        }

        .empty-state {
            padding: 3rem 1.5rem;
        }

        .empty-state i {
            font-size: 3rem;
        }

        .stats-card .card-body {
            padding: 1.25rem;
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            font-size: 1.25rem;
        }
    }
</style>

@section('js')
<script>
    // Auto-hide alerts after 5 seconds
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });

    $(function () {
    $('#shopsTable').DataTable({
        order: [[1, 'asc']],
        pageLength: 25,
        responsive: true
    });
});

    // Refresh page function
    function refreshPage() {
        window.location.reload();
    }

    // View shop details function (placeholder)
    function viewShopDetails(shopId) {
        // You can implement a modal to show shop details here
        window.location.href = '/shops/configure/' + shopId;
    }
</script>
@stop