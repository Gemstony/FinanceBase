<?php

namespace App\Http\Controllers;

use App\Models\LoanProductTypes;
use App\Models\SubShop;
use App\Models\AccountClass;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class LoanProductTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('loans.loan_product_types.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
        $loanProductTypes = LoanProductTypes::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

        return view('loans.loans_settings.loan_product_types', compact('subshop', 'loanProductTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:loan_product_types,code',
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            LoanProductTypes::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Loan product type created successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create loan product type: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $loanProductType = LoanProductTypes::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:account_classes,code,' . $id,
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            $loanProductType->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Loan product type updated successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update loan product type: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $loanProductType = LoanProductTypes::findOrFail($id);
            $loanProductType->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Loan product type deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete loan product type: ' . $e->getMessage()
            ], 500);
        }
    }
}
