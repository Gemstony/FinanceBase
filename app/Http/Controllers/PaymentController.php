<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class PaymentController extends Controller
{
    /**
     * Update the specified payment in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'transaction_id' => 'nullable|string|max:255',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,completed,failed,refunded,cancelled,partial',
        ]);

        try {
            DB::beginTransaction();

            // Auto-calculate status relative to plan price (partial vs completed)
            // Only if payment is linked to a plan
            if ($payment->plan_id) {
                $plan = \App\Models\Plan::find($payment->plan_id);
                if ($plan) {
                    $otherPaid = Payment::where('shop_id', $payment->shop_id)
                        ->where('plan_id', $payment->plan_id)
                        ->where('id', '!=', $payment->id)
                        ->sum('amount');
                    $newTotal = $otherPaid + ($validated['amount'] ?? $payment->amount);
                    $validated['status'] = $newTotal < $plan->price ? 'partial' : 'completed';
                }
            }

            $payment->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'payment' => $payment->load('paymentMethod')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update payment: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'error' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified payment from storage.
     */
    public function destroy(Payment $payment)
    {
        try {
            DB::beginTransaction();

            // Check if this payment is linked to a subscription
            if ($payment->subscription_id) {
                // You might want to handle subscription updates here if needed
                // For now, we'll just allow the deletion
            }

            $payment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete payment: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'error' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment: ' . $e->getMessage()
            ], 500);
        }
    }
}
