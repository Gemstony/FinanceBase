<?php

namespace App\Http\Controllers;

use App\Models\InterestMethods;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InterestMethodsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.interest_methods.index')]);
            }
            
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $interestMethods = InterestMethods::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            return view('loans.loans_settings.interest_methods', compact('subshop', 'interestMethods'));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load interest methods: ' . $e->getMessage());
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
                'code' => 'required|string|max:10|unique:interest_methods,code',
                'supports_installment_based' => 'boolean',
                'supports_daily_accrual' => 'boolean',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $interestMethod = InterestMethods::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => $validated['code'],
                'supports_installment_based' => $request->has('supports_installment_based'),
                'supports_daily_accrual' => $request->has('supports_daily_accrual'),
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Interest method created successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create interest method: ' . $e->getMessage())->withInput();
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
            $interestMethod = InterestMethods::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('interest_methods', 'code')->ignore($interestMethod->id)
                ],
                'supports_installment_based' => 'boolean',
                'supports_daily_accrual' => 'boolean',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $interestMethod->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'supports_installment_based' => $request->has('supports_installment_based'),
                'supports_daily_accrual' => $request->has('supports_daily_accrual'),
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Interest method updated successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update interest method: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $interestMethod = InterestMethods::findOrFail($id);
            $interestMethod->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Interest method deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete interest method: ' . $e->getMessage()
            ], 500);
        }
    }
}
