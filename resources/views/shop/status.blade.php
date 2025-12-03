@extends('adminlte::page')

@section('title', 'Shop Status')



@section('content')
    <div class="row justify-content-center mt-3">
        <div class="col-md-8">
            @php
                $user = auth()->user();
                $shop = $user ? $user->shop : null;
                $assignedShop = null;
                
                // Check if user is a shopkeeper assigned to subshops
                if (!$shop && method_exists($user, 'subshops') && $user->subshops()->exists()) {
                    $assignedSubshop = $user->subshops()->first();
                    if ($assignedSubshop && $assignedSubshop->shop) {
                        $assignedShop = $assignedSubshop->shop;
                    }
                }
                
                $shop_status = session('shop_status') ?: ($shop ? $shop->status : ($assignedShop ? $assignedShop->status : 'no_shop'));
                $shop_name = session('shop_name') ?: ($shop ? $shop->name : ($assignedShop ? $assignedShop->name : 'No Shop Found'));

                // Define message for both shop owners and shopkeepers
                if ($shop) {
                    $message = "Dear Super Admin,\n\nMy shop '{$shop_name}' has '" . ucfirst($shop_status) . "' status and I cannot access it. Please help me resolve this issue.\n\nShop Name: {$shop_name}\nStatus: " . ucfirst($shop_status) . "\nUser ID: " . auth()->id() . "\nUser Type: Shop Owner\nDate: " . now()->format('d/m/Y H:i') . "\n\nThank you.";
                } else {
                    $message = "Dear Super Admin,\n\nThe shop '{$shop_name}' that I am assigned to has '" . ucfirst($shop_status) . "' status and I cannot access it. Please help resolve this issue.\n\nShop Name: {$shop_name}\nStatus: " . ucfirst($shop_status) . "\nUser ID: " . auth()->id() . "\nUser Type: Shopkeeper\nDate: " . now()->format('d/m/Y H:i') . "\n\nThank you.";
                }
            @endphp

            @if($shop_status === 'no_shop' && !$shop && !$assignedShop)
                <!-- No Shop Case - User has no shop ownership or assignments -->
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i> Shop Not Found
                        </h3>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="fas fa-store-slash fa-4x text-warning mb-3"></i>
                        </div>

                        <h4 class="mb-3">No Shop Associated</h4>

                        <div class="alert alert-warning">
                            <h5><i class="icon fas fa-info-circle"></i> What does this mean?</h5>
                            <p>You don't have a shop associated with your account. To access shop features, you need to create a shop first.</p>
                        </div>

                        <div class="mt-4">
                            <a href="{{ route('shop.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Your Shop
                            </a>
                            <a href="{{ route('dashboard') }}" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <!-- Shop Exists but Inactive Case - for both owners and shopkeepers -->
                <div class="card {{ $shop_status === 'suspended' ? 'card-danger' : ($shop_status === 'inactive' ? 'card-secondary' : 'card-warning') }}">
                    <div class="card-header">
                        <h3 class="card-title">
                            @if($shop_status === 'suspended')
                                <i class="fas fa-ban"></i> Shop Suspended
                            @elseif($shop_status === 'inactive')
                                <i class="fas fa-pause-circle"></i> Shop Inactive
                            @elseif($shop_status === 'trial')
                                <i class="fas fa-clock"></i> Trial Period
                            @else
                                <i class="fas fa-exclamation-triangle"></i> Access Restricted
                            @endif
                        </h3>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            @if($shop_status === 'suspended')
                                <i class="fas fa-ban fa-4x text-danger mb-3"></i>
                            @elseif($shop_status === 'inactive')
                                <i class="fas fa-pause-circle fa-4x text-secondary mb-3"></i>
                            @elseif($shop_status === 'trial')
                                <i class="fas fa-clock fa-4x text-warning mb-3"></i>
                            @else
                                <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                            @endif
                        </div>

                        <h4 class="mb-3">{{ $shop_name }}</h4>

                        <div class="mb-4">
                            <span class="badge badge-lg {{ $shop_status === 'suspended' ? 'badge-danger' : ($shop_status === 'inactive' ? 'badge-secondary' : 'badge-warning') }}">
                                <i class="fas {{ $shop_status === 'suspended' ? 'fa-ban' : ($shop_status === 'inactive' ? 'fa-pause-circle' : 'fa-clock') }}"></i>
                                {{ ucfirst($shop_status) }}
                            </span>
                        </div>

                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-info-circle"></i> What does this mean?</h5>
                            @if($shop)
                                <!-- Shop Owner -->
                                @if($shop_status === 'suspended')
                                    <p>Your shop has been suspended by the administrator. This may be due to policy violations, payment issues, or other administrative reasons.</p>
                                @elseif($shop_status === 'inactive')
                                    <p>Your shop is currently inactive. You cannot access shop features until an administrator activates your account.</p>
                                @elseif($shop_status === 'trial')
                                    <p>Your shop is in trial mode. Some features may be limited until you upgrade or your trial period ends.</p>
                                @else
                                    <p>Your shop access has been restricted. Please contact an administrator for more information.</p>
                                @endif
                            @else
                                <!-- Shopkeeper -->
                                @if($shop_status === 'suspended')
                                    <p>The shop you are assigned to has been suspended by the administrator. This may be due to policy violations, payment issues, or other administrative reasons.</p>
                                @elseif($shop_status === 'inactive')
                                    <p>The shop you are assigned to is currently inactive. You cannot access shop features until an administrator activates the shop.</p>
                                @elseif($shop_status === 'trial')
                                    <p>The shop you are assigned to is in trial mode. Some features may be limited until the shop owner upgrades or the trial period ends.</p>
                                @else
                                    <p>The shop you are assigned to has restricted access. Please contact an administrator or your shop owner for more information.</p>
                                @endif
                            @endif
                        </div>

                        <div class="alert alert-warning">
                            <h5><i class="icon fas fa-exclamation-triangle"></i> How to resolve this?</h5>
                            @if($shop)
                                <!-- Shop Owner -->
                                <p>Please contact a system administrator or Super Admin to review and activate your shop access. You can reach out through the support channels or contact form.</p>
                            @else
                                <!-- Shopkeeper -->
                                <p>Please contact your shop owner or a system administrator to resolve this issue. They can help activate the shop or reassign you to an active shop.</p>
                            @endif
                        </div>

                        <div class="mt-4">
                            <p class="text-muted">If you believe this is an error, please include the following information when contacting support:</p>
                            <div class="bg-light p-3 rounded">
                                <small>
                                    <strong>Shop Name:</strong> {{ $shop_name }}<br>
                                    <strong>Status:</strong> {{ ucfirst($shop_status) }}<br>
                                    <strong>User ID:</strong> {{ auth()->id() }}<br>
                                    <strong>User Type:</strong> {{ $shop ? 'Shop Owner' : 'Shopkeeper' }}<br>
                                    <strong>Date:</strong> {{ now()->format('d/m/Y H:i') }}
                                </small>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                            @if($shop)
                                <!-- Shop Owner -->
                                <a href="#" onclick="sendMessageToSuperAdmin(this)" class="btn btn-primary ml-2">
                                    <i class="fas fa-envelope"></i> Message Super Admin
                                </a>
                                <a href="https://wa.me/25553709810?text={{ urlencode($message) }}" target="_blank" class="btn btn-success ml-2">
                                    <i class="fab fa-whatsapp"></i> Contact Super Admin
                                </a>
                            @else
                                <!-- Shopkeeper -->
                                <a href="#" onclick="sendMessageToSuperAdmin(this)" class="btn btn-primary ml-2">
                                    <i class="fas fa-envelope"></i> Message Super Admin
                                </a>
                                <a href="https://wa.me/25553709810?text={{ urlencode($message) }}" target="_blank" class="btn btn-success ml-2">
                                    <i class="fab fa-whatsapp"></i> Contact Super Admin
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    
function sendMessageToSuperAdmin(button) {
    // Get the message content from the PHP variable
    const message = @json($message);
    const subject = "Shop Access Issue - {{ $shop_name }}";

    // Show loading
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    button.disabled = true;

    // Send AJAX request
    fetch('{{ route("messages.send-to-super-admin") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            subject: subject,
            content: message
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Message Sent!',
                text: 'Your message has been sent to the super admin successfully.',
                showConfirmButton: false,
                timer: 3000
            });
        } else {
            // Show error message
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Failed to send message. Please try again.',
                showConfirmButton: true
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while sending the message. Please try again.',
            showConfirmButton: true
        });
    })
    .finally(() => {
        // Restore button
        button.innerHTML = originalHtml;
        button.disabled = false;
    });
}
</script>
@endsection

    @push('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
