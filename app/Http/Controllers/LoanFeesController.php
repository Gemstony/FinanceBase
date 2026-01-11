<?php

namespace App\Http\Controllers;

use App\Models\LoanFees;
use App\Models\SubShop;
use App\Models\ChartsOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanFeesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.loan_fees.index')]);
            }
            
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $loanFees = LoanFees::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            $incomeAccounts = ChartsOfAccount::whereIn('subshop_id', $shopSubshopIds)
                ->where('is_active', true)
                ->orderBy('account_name')
                ->get();

            // Debug: Log income accounts
            \Log::info('Income accounts for shop (subshops: ' . $shopSubshopIds->implode(',') . '):', $incomeAccounts->toArray());

            if ($incomeAccounts->isEmpty()) {
                return redirect()->back()->with('info', 'No income accounts found for this Branch. Please create income accounts first before adding loan fees.');
            }

            return view('loans.loans_settings.loan_fees', compact('subshop', 'loanFees', 'incomeAccounts'));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load loan fees: ' . $e->getMessage());
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
            // Debug: Log the incoming request data
            \Log::info('Loan fee store request data:', $request->all());
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:10|unique:loan_fees,code',
                'fee_type' => 'required|in:FIXED,PERCENTAGE',
                'amount' => 'required_if:fee_type,FIXED|nullable|numeric|min:0',
                'percentage' => 'required_if:fee_type,PERCENTAGE|nullable|numeric|min:0|max:100',
                'apply_on' => 'required|in:DISBURSEMENT,REPAYMENT,TOP_UP',
                'income_account_id' => 'required|exists:charts_of_accounts,id',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            // Debug: Log validated data
            \Log::info('Validated loan fee data:', $validated);

            $loanFee = LoanFees::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => $validated['code'],
                'fee_type' => $validated['fee_type'],
                'amount' => $validated['amount'],
                'percentage' => $validated['percentage'],
                'apply_on' => $validated['apply_on'],
                'income_account_id' => $validated['income_account_id'],
                'is_active' => $request->has('is_active'),
            ]);

            // Debug: Log successful creation
            \Log::info('Loan fee created successfully:', ['id' => $loanFee->id]);

            return redirect()->back()->with('success', 'Loan fee created successfully!');
            
        } catch (\Exception $e) {
            // Debug: Log the error
            \Log::error('Failed to create loan fee: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->with('error', 'Failed to create loan fee: ' . $e->getMessage())->withInput();
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
            $loanFee = LoanFees::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('loan_fees', 'code')->ignore($loanFee->id)
                ],
                'fee_type' => 'required|in:FIXED,PERCENTAGE',
                'amount' => 'required_if:fee_type,FIXED|nullable|numeric|min:0',
                'percentage' => 'required_if:fee_type,PERCENTAGE|nullable|numeric|min:0|max:100',
                'apply_on' => 'required|in:DISBURSEMENT,REPAYMENT,TOP_UP',
                'income_account_id' => 'required|exists:charts_of_accounts,id',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $loanFee->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'fee_type' => $validated['fee_type'],
                'amount' => $validated['amount'],
                'percentage' => $validated['percentage'],
                'apply_on' => $validated['apply_on'],
                'income_account_id' => $validated['income_account_id'],
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Loan fee updated successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update loan fee: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $loanFee = LoanFees::findOrFail($id);
            $loanFee->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Loan fee deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete loan fee: ' . $e->getMessage()
            ], 500);
        }
    }
}
