<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CollateralTypes;
use App\Models\SubShop;
use Illuminate\Validation\Rule;

class CollateralTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.collateral_types.index')]);
            }

            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $collateralTypes = CollateralTypes::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            return view('loans.loans_settings.collateral_types', compact('subshop', 'collateralTypes'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load collateral types: ' . $e->getMessage());
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
                'code' => 'required|string|max:50|unique:collateral_types,code,NULL,id,subshop_id,' . session('subshop_id'),
                'description' => 'nullable|string',
                'requires_valuation' => 'boolean',
                'default_ltv_ratio' => 'nullable|numeric|min:0|max:100',
                'depreciates' => 'boolean',
                'revaluation_interval_days' => 'nullable|integer|min:1',
                'requires_ownership_proof' => 'boolean',
                'requires_insurance' => 'boolean',
                'allow_multiple_per_loan' => 'boolean',
                'is_active' => 'boolean',
            ]);

            CollateralTypes::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $validated['description'] ?? null,
                'requires_valuation' => $request->has('requires_valuation'),
                'default_ltv_ratio' => $validated['default_ltv_ratio'] ?? null,
                'depreciates' => $request->has('depreciates'),
                'revaluation_interval_days' => $validated['revaluation_interval_days'] ?? null,
                'requires_ownership_proof' => $request->has('requires_ownership_proof'),
                'requires_insurance' => $request->has('requires_insurance'),
                'allow_multiple_per_loan' => $request->has('allow_multiple_per_loan'),
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Collateral type created successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create collateral type: ' . $e->getMessage())->withInput();
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
            $collateralType = CollateralTypes::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('collateral_types', 'code')->ignore($collateralType->id)
                ],
                'description' => 'nullable|string',
                'requires_valuation' => 'boolean',
                'default_ltv_ratio' => 'nullable|numeric|min:0|max:100',
                'depreciates' => 'boolean',
                'revaluation_interval_days' => 'nullable|integer|min:1',
                'requires_ownership_proof' => 'boolean',
                'requires_insurance' => 'boolean',
                'allow_multiple_per_loan' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $collateralType->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $validated['description'] ?? null,
                'requires_valuation' => $request->has('requires_valuation'),
                'default_ltv_ratio' => $validated['default_ltv_ratio'] ?? null,
                'depreciates' => $request->has('depreciates'),
                'revaluation_interval_days' => $validated['revaluation_interval_days'] ?? null,
                'requires_ownership_proof' => $request->has('requires_ownership_proof'),
                'requires_insurance' => $request->has('requires_insurance'),
                'allow_multiple_per_loan' => $request->has('allow_multiple_per_loan'),
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Collateral type updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update collateral type: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $collateralType = CollateralTypes::findOrFail($id);
            $collateralType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Collateral type deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete collateral type: ' . $e->getMessage()
            ], 500);
        }
    }
}
