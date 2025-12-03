<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\SubShop;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    /**
     * Display shop setup form
     */
    public function create()
    {
        // Check if user already has a shop
        $user = Auth::user();
        if ($user->shop) {
            return redirect()->route('dashboard');
        }

        return view('shop.setup');
    }

    /**
     * Store shop and subshops
     */
    public function store(Request $request)
    {
        // Check if user already has a shop
        $user = Auth::user();
        if ($user->shop) {
            return redirect()->route('dashboard')
                ->with('error', 'You already have a shop. You cannot create another one.');
        }

        // Debug: Log the request data
        \Log::info('Shop creation attempt', [
            'user_id' => Auth::id(),
            'request_all' => $request->all(),
            'request_method' => $request->method(),
            'has_csrf' => $request->has('_token'),
            'csrf_valid' => $request->has('_token') ? 'present' : 'missing'
        ]);

        // Validation
        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'shop_phone' => 'required|string|max:20',
            'shop_address' => 'required|string|max:500',
            'shop_description' => 'nullable|string|max:1000',
            'subshops' => 'required|array|min:1|max:2',
            'subshops.*.name' => 'required|string|max:255',
            'subshops.*.phone' => 'required|string|max:20',
            'subshops.*.address' => 'required|string|max:500',
        ], [
            'shop_name.required' => 'Shop name is required',
            'shop_phone.required' => 'Phone number is required',
            'shop_address.required' => 'Address is required',
            'subshops.required' => 'You need to create at least one sub shop',
            'subshops.min' => 'You need to create at least one sub shop',
            'subshops.max' => 'Unaweza kuongeza subshops zisizozidi mbili (2)',
            'subshops.*.name.required' => 'Sub shop name is required',
            'subshops.*.phone.required' => 'Sub shop phone number is required',
            'subshops.*.address.required' => 'Sub shop address is required',
        ]);

        \Log::info('Validation passed', ['validated_data' => $validated]);

        try {
            DB::beginTransaction();

            // Create main shop
            $shop = Shop::create([
                'user_id' => Auth::id(),
                'name' => $validated['shop_name'],
                'phone' => $validated['shop_phone'],
                'address' => $validated['shop_address'],
                'description' => $validated['shop_description'] ?? null,
                'is_active' => false,
                'status' => 'inactive',
            ]);

            // Create subshops
            foreach ($validated['subshops'] as $subshopData) {
                SubShop::create([
                    'shop_id' => $shop->id,
                    'name' => $subshopData['name'],
                    'phone' => $subshopData['phone'],
                    'created_by' => Auth::id(),
                    'address' => $subshopData['address'],
                    'is_active' => true,
                    
                ]);
            }

            // Assign free trial plan to the shop
            $trialPlan = Plan::where('slug', 'free-trial')->first();
            if ($trialPlan) {
                $paymentMethod = \App\Models\PaymentMethod::first(); // Get first available payment method
                
                Subscription::create([
                    'shop_id' => $shop->id,
                    'plan_id' => $trialPlan->id,
                    'start_date' => now(),
                    'end_date' => now()->addDays(14), // 14-day trial
                    'status' => 'trial',
                    'auto_renew' => false, // Trial doesn't auto-renew
                    'payment_method_id' => $paymentMethod ? $paymentMethod->id : null,
                    'notes' => 'Free trial subscription created automatically'
                ]);
            }

            DB::commit();

            return redirect()->route('dashboard')
                ->with('success', 'Congratulations! Your shop has been created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the detailed error for debugging
            \Log::error('Shop creation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred. Please try again.']);
        }
    }

    /**
     * Display shop details
     */
    public function show()
    {
        $user = Auth::user();
        $shop = $user->shop()->with('subShops')->first();

        if (!$shop) {
            return redirect()->route('subshops.choose');
        }

        return view('shop.shops', compact('shop'));
    }

    /**
     * Show edit form
     */
    public function edit()
    {
        $user = Auth::user();
        $shop = $user->shop()->with('subShops')->first();

        if (!$shop) {
            return redirect()->route('shop.create');
        }

        return view('shop.edit', compact('shop'));
    }

    /**
     * Update shop
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $shop = $user->shop;

        if (!$shop) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not found'
                ], 404);
            }
            return redirect()->route('shop.create');
        }

        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'shop_phone' => 'required|string|max:20',
            'shop_address' => 'required|string|max:500',
            'shop_description' => 'nullable|string|max:1000',
        ]);

        try {
            $shop->update([
                'name' => $validated['shop_name'],
                'phone' => $validated['shop_phone'],
                'address' => $validated['shop_address'],
                'description' => $validated['shop_description'] ?? null,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Shop information updated successfully',
                    'shop' => [
                        'name' => $shop->name,
                        'phone' => $shop->phone,
                        'address' => $shop->address,
                        'description' => $shop->description,
                    ]
                ]);
            }

            return back()->with('success', 'Shop information updated successfully');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred. Please try again.'
                ], 500);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred. Please try again.']);
        }
    }
}