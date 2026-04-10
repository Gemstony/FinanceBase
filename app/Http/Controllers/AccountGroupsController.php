<?php

namespace App\Http\Controllers;

use App\Models\AccountClass;
use App\Models\AccountGroups;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountGroupsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('accounting.account_groups.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

        $accountGroups = AccountGroups::with('class')
            ->whereIn('subshop_id', $shopSubshopIds)
            ->latest()
            ->get();
        $account_classes = AccountClass::whereIn('subshop_id', $shopSubshopIds)
            ->latest()
            ->get();

        return view('accounting.accounting_settings.account_groups', compact('subshop', 'accountGroups', 'account_classes'));
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
        $validated = $request->validate([
            'class_id' => 'required|exists:account_classes,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

                    // Group-based prefix (optional smart generation)
                
                $prefix = AccountClass::where([
                    'id' => $validated['class_id'],
                    'subshop_id' => session('subshop_id')
                ])->value('code');
                                        

                // Tafuta last account kwenye group hii
                $last = AccountGroups::where('class_id', $validated['class_id'])
                              ->where('subshop_id', session('subshop_id'))
                              ->orderBy('code', 'DESC')
                              ->first();

                if ($last) {
                    // Increase last code by 1
                    $number = intval(substr($last->code, strlen($prefix))) + 1;
                } else {
                    // Start from 1
                    $number = 1;
                }

                // Format: PREFIX + padded number
                // Example: ASSET → 101, INCOME → 301
                $lastCode = AccountGroups::where('subshop_id', session('subshop_id'))
                    ->where('code', 'like', $prefix . '%')
                    ->max('code');

                $nextNumber = $lastCode ? intval(substr($lastCode, strlen($prefix))) + 1 : 1;
                $groupCode = $prefix . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);

        try {
            DB::beginTransaction();
            
            AccountGroups::create([
                'subshop_id' => session('subshop_id'),
                'class_id' => $validated['class_id'],
                'name' => $validated['name'],
                'code' => $groupCode,
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Account group created successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create account group: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $accountClass = AccountGroups::findOrFail($id);
        
        $validated = $request->validate([
            'class_id' => 'required|exists:account_classes,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            $accountClass->update([
                'class_id' => $validated['class_id'],
                'name' => $validated['name'],
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Account group updated successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update account group: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $accountGroup = AccountGroups::findOrFail($id);
            $accountGroup->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Account group deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account group: ' . $e->getMessage()
            ], 500);
        }
    }
}
