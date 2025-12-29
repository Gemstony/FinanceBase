<?php

namespace App\Http\Controllers;

use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubShopController extends Controller
{
    /**
     * Store a new subshop
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $shop = $user->shop;

        if (!$shop) {
            return redirect()->route('shop.create')->withErrors(['error' => 'Please create a Branch first']);
        }

        // Check Branches limit
        $currentSubshopsCount = $shop->subShops()->count();
        $maxSubshops = $shop->max_subshops;

        if ($maxSubshops > 0 && $currentSubshopsCount >= $maxSubshops) {
            return redirect()->back()->withErrors([
                'error' => "You have reached the maximum limit of {$maxSubshops} Branches. Please contact an administrator to increase your Branches limit."
            ])->withInput();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'Branch name is required',
            'phone.required' => 'Phone number is required',
            'address.required' => 'Address is required',
        ]);

        try {
            SubShop::create([
                'shop_id' => $shop->id,
                'created_by' => Auth::id(),
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return redirect()->route('subshops.choose')->with('success', 'Branch added successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred. Please try again.']);
        }
    }

    /**
     * Create a new Branches via AJAX (for modal)
     */
    public function createModal(Request $request)
    {
        $user = Auth::user();

        // Check if user has owner or super admin role
        if (!$user->hasRole(['owner', 'Super Admin'])) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'You do not have permission to create Branches.'], 403);
            }
            return redirect()->route('subshops.choose')->withErrors(['error' => 'You do not have permission to create Branches.']);
        }

        $shop = $user->shop;

        if (!$shop) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Please create a shop first'], 422);
            }
            return redirect()->route('subshops.choose')->withErrors(['error' => 'Please create a Branch first']);
        }

        // Check if Branch is active
        if ($shop->status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Your Branch is not active. Please contact support.'], 422);
            }
            return redirect()->route('subshops.choose')->withErrors(['error' => 'Your Branch is not active. Please contact support.']);
        }

        // Check Branches limit
        $currentSubshopsCount = $shop->subShops()->count();
        $maxSubshops = $shop->max_subshops;

        if ($maxSubshops > 0 && $currentSubshopsCount >= $maxSubshops) {
            $errorMsg = "You have reached the maximum limit of {$maxSubshops} Branches. Please contact an administrator to increase your Branches limit.";
            if ($request->expectsJson()) {
                return response()->json(['error' => $errorMsg], 422);
            }
            return redirect()->route('subshops.choose')->withErrors(['error' => $errorMsg])->withInput();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'Branch name is required',
            'phone.required' => 'Phone number is required',
            'address.required' => 'Address is required',
        ]);

        try {
            SubShop::create([
                'shop_id' => $shop->id,
                'created_by' => Auth::id(),
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Branch added successfully']);
            }
            return redirect()->route('subshops.choose')->with('success', 'Branch added successfully');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'An error occurred. Please try again.'], 500);
            }
            return redirect()->route('subshops.choose')->withErrors(['error' => 'An error occurred. Please try again.'])->withInput();
        }
    }

    /**
     * Update an existing subshop
     */
    public function update(Request $request, SubShop $subshop)
    {
        $user = Auth::user();
        $shop = $user->shop;

        if (!$shop || $subshop->shop_id !== $shop->id) {
            return redirect()->route('shop.show')->withErrors(['error' => 'You do not have permission to do this']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'Branch name is required',
            'phone.required' => 'Phone number is required',
            'address.required' => 'Address is required',
        ]);

        try {
            $subshop->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'is_active' => $validated['is_active'] ?? false,
            ]);

            return redirect()->back()->with('success', 'Branch updated successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred. Please try again.']);
        }
    }

    /**
     * Delete a Branches
     */
    public function destroy(SubShop $subshop)
    {
        $user = Auth::user();
        $shop = $user->shop;

        if (!$shop || $subshop->shop_id !== $shop->id) {
            return redirect()->route('shop.show')->withErrors(['error' => 'You do not have permission to do this']);
        }

        try {
            $subshop->delete();

            return redirect()->back()->with('success', 'Branch deleted successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while deleting.']);
        }
    }
}
