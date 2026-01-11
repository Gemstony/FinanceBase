<?php

namespace App\Http\Controllers;

use App\Models\InterestCycles;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InterestCycleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.interest_cycles.index')]);
            }
            
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $interestCycles = InterestCycles::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            return view('loans.loans_settings.interest_cycles', compact('subshop', 'interestCycles'));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load interest cycles: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:10|unique:interest_cycles,code',
                'interval_days' => 'nullable|integer|min:1',
                'is_installment_based' => 'boolean',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $interestCycle = InterestCycles::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => $validated['code'],
                'interval_days' => $validated['interval_days'],
                'is_installment_based' => $request->has('is_installment_based'),
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Interest cycle created successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create interest cycle: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $interestCycle = InterestCycles::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('interest_cycles', 'code')->ignore($interestCycle->id)
                ],
                'interval_days' => 'nullable|integer|min:1',
                'is_installment_based' => 'boolean',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $interestCycle->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'interval_days' => $validated['interval_days'],
                'is_installment_based' => $request->has('is_installment_based'),
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Interest cycle updated successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update interest cycle: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $interestCycle = InterestCycles::findOrFail($id);
            $interestCycle->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Interest cycle deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete interest cycle: ' . $e->getMessage()
            ], 500);
        }
    }
}
