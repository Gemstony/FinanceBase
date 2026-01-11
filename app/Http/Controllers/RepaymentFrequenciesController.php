<?php

namespace App\Http\Controllers;

use App\Models\RepaymentFrequencies;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepaymentFrequenciesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.repayment_frequencies.index')]);
            }
            
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $repaymentFrequencies = RepaymentFrequencies::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            return view('loans.loans_settings.repayment_frequencies', compact('subshop', 'repaymentFrequencies'));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load repayment frequencies: ' . $e->getMessage());
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:10|unique:repayment_frequencies,code',
                'interval_days' => 'required|integer|min:1',
                'max_installments' => 'nullable|integer|min:1|max:255',
                'min_installments' => 'nullable|integer|min:1|max:255',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $repaymentFrequency = RepaymentFrequencies::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => $validated['code'],
                'interval_days' => $validated['interval_days'],
                'is_month_based' => $request->has('is_month_based'),
                'max_installments' => $validated['max_installments'],
                'min_installments' => $validated['min_installments'],
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Repayment frequency created successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create repayment frequency: ' . $e->getMessage())->withInput();
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $repaymentFrequency = RepaymentFrequencies::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('repayment_frequencies', 'code')->ignore($repaymentFrequency->id)
                ],
                'interval_days' => 'required|integer|min:1',
                'max_installments' => 'nullable|integer|min:1|max:255',
                'min_installments' => 'nullable|integer|min:1|max:255',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $repaymentFrequency->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'interval_days' => $validated['interval_days'],
                'is_month_based' => $request->has('is_month_based'),
                'max_installments' => $validated['max_installments'],
                'min_installments' => $validated['min_installments'],
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Repayment frequency updated successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update repayment frequency: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */


    public function destroy($id)
    {
        try {
            $repaymentFrequency = RepaymentFrequencies::findOrFail($id);
            $repaymentFrequency->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Repayment frequency deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete repayment frequency: ' . $e->getMessage()
            ], 500);
        }
    }

}
