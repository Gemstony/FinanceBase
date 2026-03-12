<?php

namespace App\Http\Controllers;

use App\Models\BankAccounts;
use App\Models\SubShop;
use App\Models\ChartsOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankAccountsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('accounting.bank_accounts.index')]);
            }
            
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $bankAccounts = BankAccounts::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            $chartAccounts = ChartsOfAccount::whereIn('subshop_id', $shopSubshopIds)
                ->where('is_active', true)
                ->orderBy('account_name')
                ->get();

            if ($chartAccounts->isEmpty()) {
                return redirect()->back()->with('info', 'No chart of accounts found for this Branch. Please create accounts first before adding bank accounts.');
            }

            return view('accounting.bank_accounts.index', compact('subshop', 'bankAccounts', 'chartAccounts'));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load bank accounts: ' . $e->getMessage());
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
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('accounting.bank_accounts.index')]);
            }

            $validated = $request->validate([
                'account_name' => 'required|string|max:255',
                'account_type' => 'required|in:bank,cash,mobile_money',
                'bank_name' => 'required_if:account_type,bank,mobile_money|nullable|string|max:255',
                'account_number' => 'required_if:account_type,bank,mobile_money|nullable|string|max:255',
                'opening_balance' => 'required|numeric|min:0',
                'currency_code' => 'required|string|max:3',
                'chart_of_account_id' => 'required|exists:charts_of_accounts,id',
                'is_active' => 'sometimes|boolean',
                'description' => 'nullable|string|max:1000',
            ]);

            $bankAccount = BankAccounts::create([
                'subshop_id' => $subshopId,
                'account_name' => $validated['account_name'],
                'account_type' => $validated['account_type'],
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'opening_balance' => $validated['opening_balance'],
                'currency_code' => $validated['currency_code'],
                'chart_of_account_id' => $validated['chart_of_account_id'],
                'is_active' => $request->boolean('is_active'),
                'description' => $validated['description'],
                'created_by' => auth()->id(),
            ]);

            return redirect()->back()->with('success', 'Bank account created successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create bank account: ' . $e->getMessage())->withInput();
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
            $bankAccount = BankAccounts::findOrFail($id);

            $validated = $request->validate([
                'account_name' => 'required|string|max:255',
                'account_type' => 'required|in:bank,cash,mobile_money',
                'bank_name' => 'required_if:account_type,bank,mobile_money|nullable|string|max:255',
                'account_number' => 'required_if:account_type,bank,mobile_money|nullable|string|max:255',
                'opening_balance' => 'required|numeric|min:0',
                'currency_code' => 'required|string|max:3',
                'chart_of_account_id' => 'required|exists:charts_of_accounts,id',
                'is_active' => 'sometimes|boolean',
                'description' => 'nullable|string|max:1000',
            ]);

            $bankAccount->update([
                'account_name' => $validated['account_name'],
                'account_type' => $validated['account_type'],
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'opening_balance' => $validated['opening_balance'],
                'currency_code' => $validated['currency_code'],
                'chart_of_account_id' => $validated['chart_of_account_id'],
                'is_active' => $request->boolean('is_active'),
                'description' => $validated['description'],
                'updated_by' => auth()->id(),
            ]);

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bank account updated successfully!',
                ]);
            }

            return redirect()->back()->with('success', 'Bank account updated successfully!');
            
        } catch (\Exception $e) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update bank account: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to update bank account: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $bankAccount = BankAccounts::findOrFail($id);
            $bankAccount->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Bank account deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank account: ' . $e->getMessage()
            ], 500);
        }
    }
}
