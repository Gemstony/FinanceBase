<?php

namespace App\Http\Controllers;

use App\Models\LoanPenalties;
use App\Models\SubShop;
use App\Models\ChartsOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanPenaltiesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.loan_penalties.index')]);
            }
            
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $loanPenalties = LoanPenalties::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            $incomeAccounts = ChartsOfAccount::whereIn('subshop_id', $shopSubshopIds)
                ->where('is_active', true)
                ->orderBy('account_name')
                ->get();

            // Debug: Log income accounts
            \Log::info('Income accounts for shop (subshops: ' . $shopSubshopIds->implode(',') . '):', $incomeAccounts->toArray());

            if ($incomeAccounts->isEmpty()) {
                return redirect()->back()->with('info', 'No income accounts found for this Branch. Please create income accounts first before adding loan penalties.');
            }

            return view('loans.loans_settings.loan_penalties', compact('subshop', 'loanPenalties', 'incomeAccounts'));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load loan penalties: ' . $e->getMessage());
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
            \Log::info('Loan penalty store request data:', $request->all());
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:10|unique:loan_penalties,code',
                'penalty_type' => 'required|in:FIXED,DAILY_PERCENTAGE',
                'amount' => 'required_if:penalty_type,FIXED|nullable|numeric|min:0',
                'percentage' => 'required_if:penalty_type,DAILY_PERCENTAGE|nullable|numeric|min:0|max:100',
                'grace_period_days' => 'required|integer|min:0',
                'frequency' => 'nullable|in:once,daily,weekly,monthly,per_installment',
                'income_account_id' => 'required|exists:charts_of_accounts,id',
                'receivable_account_id' => 'required|exists:charts_of_accounts,id',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            // Debug: Log validated data
            \Log::info('Validated loan penalty data:', $validated);

            $loanPenalty = LoanPenalties::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => $validated['code'],
                'penalty_type' => $validated['penalty_type'],
                'amount' => $validated['amount'],
                'percentage' => $validated['percentage'],
                'grace_period_days' => $validated['grace_period_days'],
                'frequency' => $validated['frequency'] ?? 'once',
                'income_account_id' => $validated['income_account_id'],
                'receivable_account_id' => $validated['receivable_account_id'],
                'is_active' => $request->has('is_active'),
            ]);

            // Debug: Log successful creation
            \Log::info('Loan penalty created successfully:', ['id' => $loanPenalty->id]);

            return redirect()->back()->with('success', 'Loan penalty created successfully!');
            
        } catch (\Exception $e) {
            // Debug: Log the error
            \Log::error('Failed to create loan penalty: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->with('error', 'Failed to create loan penalty: ' . $e->getMessage())->withInput();
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
            $loanPenalty = LoanPenalties::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('loan_penalties', 'code')->ignore($loanPenalty->id)
                ],
                'penalty_type' => 'required|in:FIXED,DAILY_PERCENTAGE',
                'amount' => 'required_if:penalty_type,FIXED|nullable|numeric|min:0',
                'percentage' => 'required_if:penalty_type,DAILY_PERCENTAGE|nullable|numeric|min:0|max:100',
                'grace_period_days' => 'required|integer|min:0',
                'frequency' => 'nullable|in:once,daily,weekly,monthly,per_installment',
                'income_account_id' => 'required|exists:charts_of_accounts,id',
                'receivable_account_id' => 'required|exists:charts_of_accounts,id',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $loanPenalty->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'penalty_type' => $validated['penalty_type'],
                'amount' => $validated['amount'],
                'percentage' => $validated['percentage'],
                'grace_period_days' => $validated['grace_period_days'],
                'frequency' => $validated['frequency'] ?? $loanPenalty->frequency ?? 'once',
                'income_account_id' => $validated['income_account_id'],
                'receivable_account_id' => $validated['receivable_account_id'],
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Loan penalty updated successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update loan penalty: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $loanPenalty = LoanPenalties::findOrFail($id);
            $loanPenalty->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Loan penalty deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete loan penalty: ' . $e->getMessage()
            ], 500);
        }
    }
}
