<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:payment_methods,code',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive'
        ]);

        try {
            $paymentMethod = PaymentMethod::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'status' => $request->status === 'active'
            ]);

            return redirect()->back()->with('success', 'Payment method created successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create payment method: ' . $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:payment_methods,code,' . $paymentMethod->id,
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive'
        ]);

        try {
            $paymentMethod->update([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'status' => $request->status === 'active'
            ]);

            return redirect()->back()->with('success', 'Payment method updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update payment method: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        try {
            // Check if payment method is being used in payments or subscriptions
            if ($paymentMethod->payments()->count() > 0 || $paymentMethod->subscriptions()->count() > 0) {
                return redirect()->back()->with('error', 'Cannot delete payment method that is currently in use by payments or subscriptions.');
            }

            $paymentMethod->delete();
            return redirect()->back()->with('success', 'Payment method deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete payment method: ' . $e->getMessage());
        }
    }}
