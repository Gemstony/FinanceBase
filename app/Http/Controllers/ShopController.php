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
use Illuminate\Support\Facades\Storage;

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
                ->with('error', 'You already have a Finance Branch. You cannot create another one.');
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
            'short_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255|unique:shops,registration_number',
            'license_number' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'email' => 'nullable|string|max:255',
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

            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('shop-logos', 'public');
            }

            // Create main shop
            $shop = Shop::create([
                'user_id' => Auth::id(),
                'name' => $validated['shop_name'],
                'short_name' => $validated['short_name'] ?: null,
                'registration_number' => $validated['registration_number'] ?: null,
                'license_number' => $validated['license_number'] ?: null,
                'tin' => $validated['tin'] ?: null,
                'website' => $validated['website'] ?: null,
                'country' => $validated['country'] ?: null,
                'region' => $validated['region'] ?: null,
                'district' => $validated['district'] ?: null,
                'street' => $validated['street'] ?: null,
                'currency' => $validated['currency'] ?: null,
                'logo' => $logoPath,
                'email' => $validated['email'] ?: null,
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
                $paymentMethod = PaymentMethod::first(); // Get first available payment method
                
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
                ->with('success', 'Congratulations! Your Finance Branch has been created successfully');

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
                    'message' => 'Branch not found'
                ], 404);
            }
            return redirect()->route('shop.create');
        }

        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255|unique:shops,registration_number,' . $shop->id,
            'license_number' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'email' => 'nullable|string|max:255',
            'shop_phone' => 'required|string|max:20',
            'shop_address' => 'required|string|max:500',
            'shop_description' => 'nullable|string|max:1000',
        ]);



        try {
            if ($request->hasFile('logo')) {
                $shop->logo = $request->file('logo')->store('shop-logos', 'public');
            }

            $shop->update([
                'name' => $validated['shop_name'],
                'short_name' => $validated['short_name'] ?: null,
                'registration_number' => $request->filled('registration_number') ? $validated['registration_number'] : $shop->registration_number,
                'license_number' => $validated['license_number'] ?: null,
                'tin' => $validated['tin'] ?: null,
                'website' => $validated['website'] ?: null,
                'country' => $validated['country'] ?: null,
                'region' => $validated['region'] ?: null,
                'district' => $validated['district'] ?: null,
                'street' => $validated['street'] ?: null,
                'email' => $validated['email'] ?: null,
                'currency' => $validated['currency'] ?: null,
                'phone' => $validated['shop_phone'],
                'address' => $validated['shop_address'],
                'description' => $validated['shop_description'] ?? null,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Branch information updated successfully',
                    'shop' => [
                        'name' => $shop->name,
                        'short_name' => $shop->short_name,
                        'registration_number' => $shop->registration_number,
                        'license_number' => $shop->license_number,
                        'tin' => $shop->tin,
                        'website' => $shop->website,
                        'country' => $shop->country,
                        'region' => $shop->region,
                        'district' => $shop->district,
                        'street' => $shop->street,
                        'currency' => $shop->currency,
                        'logo' => $shop->logo,
                        'logo_url' => $shop->logo ? Storage::url($shop->logo) : null,
                        'email' => $shop->email,
                        'phone' => $shop->phone,
                        'address' => $shop->address,
                        'description' => $shop->description,
                    ]
                ]);
            }

            return back()->with('success', 'Branch information updated successfully');

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