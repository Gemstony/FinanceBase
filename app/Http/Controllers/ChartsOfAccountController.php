<?php

namespace App\Http\Controllers;

use App\Models\AccountClass;
use App\Models\AccountGroups;
use App\Models\ChartsOfAccount;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDF;

class ChartsOfAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('sales.transactions.index')]);
    }

    /**
     * Export charts of accounts in CSV or Excel-compatible format.
     */
    public function export(Request $request, string $format)
    {
        $format = strtolower($format);
        if (!in_array($format, ['csv','excel','pdf'])) {
            return back()->with('error', 'Unsupported export format.');
        }

        $query = ChartsOfAccount::query();
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('account_name', 'like', "%$search%");
        }

        $rows = $query->with(['accountClass', 'accountGroup'])
            ->orderBy('account_code')
            ->get([
                'account_code','account_name','description','account_class_id','account_group_id',
                'cash_flow_impact','cash_flow_category','equity_impact','equity_category',
                'is_customer_account','is_system_account','is_active','created_at'
            ]);

        if ($format === 'pdf') {
            $subshop = SubShop::find(session('subshop_id'));
            $summary = [
                'count' => $rows->count(),
                'active' => (int) $rows->where('is_active', 1)->count(),
                'system' => (int) $rows->where('is_system_account', 1)->count(),
                'user' => (int) $rows->where('is_system_account', 0)->count(),
            ];
            $pdf = PDF::loadView('exports.charts_of_account_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('charts_of_accounts_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        $filename = 'charts_of_accounts_' . now()->format('Ymd_His') . ($format === 'excel' ? '.xls' : '.csv');

        $headers = [
            'Content-Type' => $format === 'excel'
                ? 'application/vnd.ms-excel; charset=UTF-8'
                : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                'Account Code','Account Name','Description','Account Class','Account Group','Cash Flow Impact','Cash Flow Category','Equity Impact','Equity Category','Customer Account','System Account','Active','Created At'
            ]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->account_code,
                    $r->account_name,
                    $r->description ?? '',
                    $r->accountClass->name ?? '',
                    $r->accountGroup->name ?? '',
                    $r->cash_flow_impact ?? 'NONE',
                    $r->cash_flow_category ?? '',
                    $r->equity_impact ?? 'NONE',
                    $r->equity_category ?? '',
                    $r->is_customer_account ? 'Yes' : 'No',
                    $r->is_system_account ? 'Yes' : 'No',
                    ($r->is_active ?? 1) ? 'Yes' : 'No',
                    optional($r->created_at)->toDateTimeString(),
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');

        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('sales.transactions.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);
        if ($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('sales.transactions.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

       


        // Get dropdown options
        $accountClasses = AccountClass::where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        $accountGroups = AccountGroups::where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get all charts of account for display
        $charts_of_accounts = ChartsOfAccount::with(['accountClass', 'accountGroup'])
            ->where('subshop_id', $subshopId)
            ->when(request('search'), function ($query) {
            $query->where('account_name', 'like', '%' . request('search') . '%');
        })->paginate(10)->appends(request()->query());

        // Summary counts
        $total_accounts = ChartsOfAccount::count();
        $active_accounts = ChartsOfAccount::where('is_active', 1)->count();
        $inactive_accounts = ChartsOfAccount::where('is_active', 0)->count();
        $system_accounts = ChartsOfAccount::where('is_system_account', 1)->count();
        $user_accounts = ChartsOfAccount::where('is_system_account', 0)->count();


        return view('accounting.charts_of_account.charts_of_accounts', compact(
            'charts_of_accounts',
            'subshop',
            'accountClasses',
            'accountGroups',
            'total_accounts',
            'active_accounts',
            'inactive_accounts',
            'system_accounts',
            'user_accounts'
        ));
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
            'account_name'        => 'required|string|max:255',
            'description'         => 'nullable|string',

            'account_class_id'    => 'required|exists:account_classes,id',
            'account_group_id'    => 'required|exists:account_groups,id',

            'cash_flow_impact'    => 'required|in:IN,OUT,NONE',
            'cash_flow_category'  => 'nullable|in:OPERATING,INVESTING,FINANCING',

            'equity_impact'       => 'required|in:INCREASE,DECREASE,NONE',
            'equity_category'     => 'nullable|in:CAPITAL,RETAINED_EARNINGS,RESERVES',

            'is_customer_account' => 'boolean',
            'is_system_account'   => 'boolean',
            'is_active'           => 'boolean',
        ]);

        // ===========================
        // AUTO ACCOUNT CODE GENERATION
        // ===========================

        // 1. Get class prefix code (e.g 1, 2, 3, 4, 5)
        $prefix = AccountClass::where([
            'id' => $validated['account_class_id'],
            'subshop_id' => session('subshop_id')
        ])->value('code');

        if (!$prefix) {
            return response()->json(['error' => 'Invalid Account Class'], 422);
        }

        // 2. Get last account code within same group & class
        $lastAccount = ChartsOfAccount::where('account_group_id', $validated['account_group_id'])
            ->where('account_class_id', $validated['account_class_id'])
            ->where('subshop_id', session('subshop_id'))
            ->orderBy('account_code', 'DESC')
            ->first();

        if ($lastAccount) {
            $number = intval(substr($lastAccount->account_code, strlen($prefix))) + 1;
        } else {
            $number = 1;
        }

        // Example: 1 + 01 → 101
        $accountCode = $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);

        // ===========================
        // CREATE ACCOUNT
        // ===========================


        try {
            $account = ChartsOfAccount::create([
            'account_code'        => $accountCode,
            'account_name'        => $validated['account_name'],
            'description'         => $validated['description'] ?? null,

            'account_class_id'    => $validated['account_class_id'],
            'account_group_id'    => $validated['account_group_id'],

            'cash_flow_impact'    => $validated['cash_flow_impact'],
            'cash_flow_category'  => $validated['cash_flow_category'] ?? null,

            'equity_impact'       => $validated['equity_impact'],
            'equity_category'     => $validated['equity_category'] ?? null,

            'is_customer_account' => $validated['is_customer_account'] ?? false,
            'is_system_account'   => $validated['is_system_account'] ?? false,
            'is_active'           => $validated['is_active'] ?? true,

            'subshop_id'          => session('subshop_id'),
            'created_by'          => Auth::id(),
        ]);

            return redirect()->route('accounting.charts_of_account.index')->with('success', 'Chart of Account created successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create Chart of account.');
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
        $validated = $request->validate([
            'account_name'        => 'required|string|max:255',
            'description'         => 'nullable|string',

            'account_class_id'    => 'required|exists:account_classes,id',
            'account_group_id'    => 'required|exists:account_groups,id',

            'cash_flow_impact'    => 'required|in:IN,OUT,NONE',
            'cash_flow_category'  => 'nullable|in:OPERATING,INVESTING,FINANCING',

            'equity_impact'       => 'required|in:INCREASE,DECREASE,NONE',
            'equity_category'     => 'nullable|in:CAPITAL,RETAINED_EARNINGS,RESERVES',

            'is_customer_account' => 'boolean',
            'is_system_account'   => 'boolean',
            'is_active'           => 'boolean',
        ]);

        $account = ChartsOfAccount::findOrFail($id);

        // Prevent changing account_class_id and account_group_id if it would affect account_code
        // You might want to regenerate account_code if these change, but for now we'll keep the existing code
        $payload = [
            'account_name'        => $validated['account_name'],
            'description'         => $validated['description'] ?? null,

            'account_class_id'    => $validated['account_class_id'],
            'account_group_id'    => $validated['account_group_id'],

            'cash_flow_impact'    => $validated['cash_flow_impact'],
            'cash_flow_category'  => $validated['cash_flow_category'] ?? null,

            'equity_impact'       => $validated['equity_impact'],
            'equity_category'     => $validated['equity_category'] ?? null,

            'is_customer_account' => $validated['is_customer_account'] ?? false,
            'is_system_account'   => $validated['is_system_account'] ?? false,
            'is_active'           => $validated['is_active'] ?? true,

            'updated_by'          => Auth::id(),
        ];

        try {
            $account->update($payload);
            return redirect()->route('accounting.charts_of_account.index')->with('success', 'Account updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update account.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $account = ChartsOfAccount::findOrFail($id);

        if ($account->is_system_account) {
            return back()->with('error', 'System account cannot be deleted.');
        }

        try {
            $account->delete();
            return redirect()->route('accounting.charts_of_account.index')->with('success', 'Account deleted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete account.');
        }
    }
}
