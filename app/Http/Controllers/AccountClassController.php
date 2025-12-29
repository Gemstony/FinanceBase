<?php

namespace App\Http\Controllers;

use App\Models\SubShop;
use App\Models\AccountClass;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AccountClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('accounting.account_class.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        $accountClasses = AccountClass::where('subshop_id', $subshopId)->latest()->get();

        return view('accounting.accounting_settings.account_class', compact('subshop', 'accountClasses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:account_classes,code',
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            AccountClass::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Account class created successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create account class: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $accountClass = AccountClass::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:account_classes,code,' . $id,
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            $accountClass->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Account class updated successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update account class: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $accountClass = AccountClass::findOrFail($id);
            $accountClass->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Account class deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account class: ' . $e->getMessage()
            ], 500);
        }
    }
}
