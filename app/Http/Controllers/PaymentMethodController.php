<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Shop;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentMethodController extends Controller
{
    public function store(Request $request)
    {
        // Get current shop from session
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose')->with('error', 'Please select a shop first.');
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:payment_methods,code,NULL,id,shop_id,' . $shopId,
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'account_type' => 'required|in:asset,liability',
            'requires_bank_account' => 'boolean',
            'requires_phone' => 'boolean',
            'is_repayment_method' => 'boolean',
            'is_deposit_method' => 'boolean',
            'is_refund_method' => 'boolean',
            'is_withdrawal_method' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $paymentMethod = PaymentMethod::create([
                'shop_id' => $shopId,
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'status' => $request->status === 'active',
                'account_type' => $request->account_type ?? 'asset',
                'requires_bank_account' => $request->boolean('requires_bank_account'),
                'requires_phone' => $request->boolean('requires_phone'),
                'is_repayment_method' => $request->boolean('is_repayment_method', true),
                'is_deposit_method' => $request->boolean('is_deposit_method', true),
                'is_refund_method' => $request->boolean('is_refund_method', true),
                'is_withdrawal_method' => $request->boolean('is_withdrawal_method', true),
                'sort_order' => $request->sort_order ?? 0,
            ]);

            // Clear payment methods cache for this shop
            Cache::forget('active_payment_methods_shop_' . $shopId);

            return redirect()->back()->with('success', 'Payment method created successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create payment method: ' . $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        // Verify payment method belongs to current shop
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose')->with('error', 'Please select a shop first.');
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        
        if ($paymentMethod->shop_id !== $shopId) {
            return redirect()->back()->with('error', 'Payment method not found for your shop.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:payment_methods,code,' . $paymentMethod->id . ',id,shop_id,' . $shopId,
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'account_type' => 'required|in:asset,liability',
            'requires_bank_account' => 'boolean',
            'requires_phone' => 'boolean',
            'is_repayment_method' => 'boolean',
            'is_deposit_method' => 'boolean',
            'is_refund_method' => 'boolean',
            'is_withdrawal_method' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $paymentMethod->update([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'status' => $request->status === 'active',
                'account_type' => $request->account_type ?? 'asset',
                'requires_bank_account' => $request->boolean('requires_bank_account'),
                'requires_phone' => $request->boolean('requires_phone'),
                'is_repayment_method' => $request->boolean('is_repayment_method', true),
                'is_deposit_method' => $request->boolean('is_deposit_method', true),
                'is_refund_method' => $request->boolean('is_refund_method', true),
                'is_withdrawal_method' => $request->boolean('is_withdrawal_method', true),
                'sort_order' => $request->sort_order ?? 0,
            ]);

            // Clear payment methods cache for this shop
            Cache::forget('active_payment_methods_shop_' . $shopId);

            return redirect()->back()->with('success', 'Payment method updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update payment method: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        // Verify payment method belongs to current shop
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return response()->json(['success' => false, 'message' => 'Invalid session.'], 403);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        
        if ($paymentMethod->shop_id !== $shopId) {
            return response()->json(['success' => false, 'message' => 'Payment method not found for your shop.'], 403);
        }

        try {
            // Check if payment method is being used in payments or subscriptions
            if ($paymentMethod->payments()->count() > 0 || $paymentMethod->subscriptions()->count() > 0) {
                return response()->json(['success' => false, 'message' => 'Cannot delete payment method that is currently in use by payments or subscriptions.']);
            }

            $paymentMethod->delete();

            // Clear payment methods cache for this shop
            Cache::forget('active_payment_methods_shop_' . $shopId);

            return response()->json(['success' => true, 'message' => 'Payment method deleted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete payment method: ' . $e->getMessage()]);
        }
    }
}
