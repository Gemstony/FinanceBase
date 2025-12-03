<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Cancel a subscription
     */
    public function cancel(Request $request, $subscriptionId)
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000'
        ]);

        $subscription = Subscription::findOrFail($subscriptionId);

        // Check if user has permission to cancel this subscription
        // For now, allow any authenticated user, but you might want to add more specific checks

        $subscription->cancel($request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully.',
            'subscription' => $subscription
        ]);
    }

    /**
     * Renew a subscription
     */
    public function renew(Request $request, $subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);

        // Check if user has permission to renew this subscription
        // For now, allow any authenticated user, but you might want to add more specific checks

        if (!$subscription->renew()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to renew subscription. Plan may not support renewal.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription renewed successfully.',
            'subscription' => $subscription
        ]);
    }
}
