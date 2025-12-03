<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SmsService;

class ShopsManagementController extends Controller
{
    /**
     * Display owners management page.
     */
    public function owners(Request $request)
    {
        $search = $request->input('search');

        $owners = User::query()
            ->whereHas('shop')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhereHas('shop', function ($shopQuery) use ($search) {
                              $shopQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%");
                          });
                });
            })
            ->with(['shop.subShops.users', 'roles'])
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('shops_management.owners', compact('owners', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created owner in storage.
     */
    public function storeOwner(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'shop_name' => 'required|string|max:255',
            'shop_phone' => 'nullable|string|max:20',
            'shop_address' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'phone_number' => $request->phone_number,
            ]);

            // Assign owner role
            $user->assignRole('owner');

            // Create the shop
            Shop::create([
                'user_id' => $user->id,
                'name' => $request->shop_name,
                'phone' => $request->shop_phone,
                'address' => $request->shop_address,
                'is_active' => true,
                'status' => 'trial',
            ]);

            DB::commit();

            return redirect()->route('owners.index')->with('success', 'Owner created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Failed to create owner: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        // $auth = $request->user();
        // if (!$auth || !$this->isSuperAdmin($auth)) {
        //     abort(403);
        // }
        $search = $request->input('search');

        $shops = Shop::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%")
                          ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        // Fetch plans and payment methods
        $plans = Plan::orderBy('sort_order')->orderByDesc('created_at')->get();
        $paymentMethods = PaymentMethod::orderByDesc('created_at')->get();

        return view('shops_management.main_shops', compact('shops', 'search'));
    }

    /**
     * Display plans and payment methods management page.
     */
    public function payments()
    {
        // Fetch plans and payment methods
        $plans = Plan::orderBy('sort_order')->orderByDesc('created_at')->paginate(10);
        $paymentMethods = PaymentMethod::orderByDesc('created_at')->paginate(10);

        return view('shops_management.payments', compact('plans', 'paymentMethods'));
    }

    /**
     * Configure the specified shop.
     */
    public function configure($id)
    {
        $shop = Shop::with(['owner', 'subShops.users'])->findOrFail($id);

        $owner = $shop->owner;

        // Get all unique shopkeepers assigned to any subshop of this main shop
        $shopkeepers = collect();
        foreach ($shop->subShops as $subShop) {
            $shopkeepers = $shopkeepers->merge($subShop->users);
        }
        $shopkeepers = $shopkeepers->unique('id')->values();

        // Count messages sent this month for this shop
        $messagesSentThisMonth = Message::where('shop_id', $shop->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
            
        // Get active payment methods for the forms
        $paymentMethods = PaymentMethod::where('status', true)->orderBy('name')->get();

        return view('shops_management.configure_shop', compact('shop', 'owner', 'shopkeepers', 'messagesSentThisMonth', 'paymentMethods'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified owner in storage.
     */
    public function updateOwner(Request $request, $ownerId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $ownerId,
            'phone_number' => 'nullable|string|max:20',
            'role' => 'required|string|in:owner,Super Admin',
            'shop_name' => 'required|string|max:255',
            'shop_phone' => 'nullable|string|max:20',
            'shop_address' => 'nullable|string|max:500',
            'shop_status' => 'required|in:active,inactive,suspended,trial',
        ]);

        $user = User::findOrFail($ownerId);
        $shop = $user->shop;

        if (!$shop) {
            return redirect()->route('owners.index')->with('error', 'Owner does not have a shop.');
        }

        DB::beginTransaction();

        try {
            // Update user
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
            ]);

            // Update user roles
            $user->syncRoles([$request->role]);

            // Prepare shop data
            $shopData = [
                'name' => $request->shop_name,
                'phone' => $request->shop_phone,
                'address' => $request->shop_address,
                'status' => $request->shop_status,
            ];

            // Handle status changes similar to updateSettings
            if ($request->shop_status === 'suspended') {
                $shopData['is_active'] = false;
                $shopData['suspended_at'] = now();
            } elseif ($request->shop_status === 'active') {
                $shopData['is_active'] = true;
                $shopData['suspended_at'] = null;
                $shopData['activated_at'] = now();
            } elseif ($request->shop_status === 'inactive') {
                $shopData['is_active'] = false;
            } elseif ($request->shop_status === 'trial') {
                $shopData['is_active'] = true;
            }

            // Update shop
            $shop->update($shopData);

            DB::commit();

            return redirect()->route('owners.index')->with('success', 'Owner updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('owners.index')->with('error', 'Failed to update owner: ' . $e->getMessage());
        }
    }
    private function isSuperAdmin(User $user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return true;
        }
        // Treat users who own at least one shop as owners
        return Shop::where('user_id', $user->id)->exists();
    }

    /**
     * Update shop status and limits.
     */
    public function updateSettings(Request $request, $shopId)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,suspended,trial',
            'suspension_reason' => 'nullable|string|max:1000',
            'max_subshops' => 'required|integer|min:0|max:100',
        ]);

        $shop = Shop::findOrFail($shopId);

        $data = [
            'status' => $request->status,
            'max_subshops' => $request->max_subshops,
        ];

        // Handle suspension
        if ($request->status === 'suspended') {
            $data['suspension_reason'] = $request->suspension_reason;
            $data['suspended_at'] = now();

            // Also set is_active to false when suspended
            $data['is_active'] = false;
        } elseif ($request->status === 'active') {
            $data['suspension_reason'] = null;
            $data['suspended_at'] = null;
            $data['activated_at'] = now();
            $data['is_active'] = true;
        } elseif ($request->status === 'inactive') {
            $data['is_active'] = false;
        } elseif ($request->status === 'trial') {
            $data['is_active'] = true;
        }

        $shop->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Shop settings updated successfully.',
            'shop' => $shop->fresh()
        ]);
    }

    /**
     * Upgrade plan and record payment for a shop
     */
    public function upgradePlan(Request $request, $shopId)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000'
        ]);

        $shop = Shop::findOrFail($shopId);
        $plan = Plan::findOrFail($request->plan_id);

        DB::beginTransaction();

        try {
            // Cancel any existing active subscription
            $existingSubscription = $shop->activeSubscription();
            if ($existingSubscription) {
                $existingSubscription->cancel('Plan upgraded to ' . $plan->name);
            }

            // Create new subscription
            $subscription = Subscription::create([
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => $plan->calculateEndDate(),
                'status' => 'active',
                'auto_renew' => $request->boolean('auto_renew', true),
                'payment_method_id' => $request->payment_method,
                'last_payment_date' => now()
            ]);

            // Determine payment status based on cumulative payments vs plan price
            $alreadyPaid = Payment::where('shop_id', $shop->id)
                ->where('plan_id', $plan->id)
                ->sum('amount');
            $newTotal = $alreadyPaid + (float) $request->amount;
            $status = $newTotal < (float) $plan->price ? 'partial' : 'completed';

            // Record payment
            Payment::create([
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'amount' => $request->amount,
                'currency' => $plan->currency,
                'payment_method_id' => $request->payment_method,
                'transaction_id' => $request->transaction_id,
                'status' => $status,
                'payment_date' => now(),
                'notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plan upgraded successfully and payment recorded.',
                'subscription' => $subscription,
                'plan' => $plan
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to upgrade plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record a manual payment for a shop
     */
    public function recordPayment(Request $request, $shopId)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000'
        ]);

        $shop = Shop::findOrFail($shopId);
        $plan = Plan::findOrFail($request->plan_id);

        // Determine status based on cumulative payments vs plan price
        $alreadyPaid = Payment::where('shop_id', $shop->id)
            ->where('plan_id', $plan->id)
            ->sum('amount');
        $newTotal = $alreadyPaid + (float) $request->amount;
        $status = $newTotal < (float) $plan->price ? 'partial' : 'completed';

        $payment = Payment::create([
            'shop_id' => $shop->id,
            'plan_id' => $plan->id,
            'amount' => $request->amount,
            'currency' => $plan->currency,
            'payment_method_id' => $request->payment_method,
            'transaction_id' => $request->transaction_id,
            'status' => $status,
            'payment_date' => $request->payment_date,
            'notes' => $request->notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully.',
            'payment' => $payment->load('paymentMethod')
        ]);
    }

    /**
     * Remove the specified owner from storage.
     */
    public function destroyOwner($ownerId)
    {
        $user = User::findOrFail($ownerId);
        $shop = $user->shop;

        if (!$shop) {
            return redirect()->route('owners.index')->with('error', 'Owner does not have a shop.');
        }

        DB::beginTransaction();

        try {
            // Delete shop (this will cascade to subshops and other related data)
            $shop->delete();

            // Remove owner role
            $user->removeRole('owner');

            // Soft delete the user
            $user->delete();

            DB::commit();

            return redirect()->route('owners.index')->with('success', 'Owner deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('owners.index')->with('error', 'Failed to delete owner: ' . $e->getMessage());
        }
    }

    /**
     * Reset the password for the specified owner.
     */
    public function resetPassword(Request $request, $ownerId)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($ownerId);

        if (!$user->shop) {
            return redirect()->route('owners.index')->with('error', 'Owner does not have a shop.');
        }

        try {
            $user->update([
                'password' => bcrypt($request->password),
            ]);

            // Send SMS notification if user has phone number
            if ($user->phone_number) {
                $smsService = new SmsService();
                $smsService->sendPasswordResetSms($user->phone_number, $request->password);
            }

            return redirect()->route('owners.index')->with('success', 'Password reset successfully for ' . $user->name . '.');
        } catch (\Exception $e) {
            return redirect()->route('owners.index')->with('error', 'Failed to reset password: ' . $e->getMessage());
        }
    }
}
