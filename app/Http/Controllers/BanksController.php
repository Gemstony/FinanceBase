<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Banks;
use App\Models\SubShop;
use App\Models\Transaction;
use App\Models\PurchasesTransactions;
use App\Models\Expenses;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class BanksController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('banks.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('banks.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $banks = Banks::where('subshop_id', $subshopId);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $banks->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('account_name', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhere('branch', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $banks = $banks->orderBy('name')->paginate(10)->appends($request->query());

        // Build per-bank net deltas for this page (sales + returns - purchases - expenses)
        $pageBanks = $banks->getCollection();
        $bankNames = $pageBanks->pluck('name')->filter()->unique()->values();
        $bankIds = $pageBanks->pluck('id')->unique()->values();

        $salesByName = collect();
        $purchasesByName = collect();
        $expensesById = collect();

        if ($bankNames->isNotEmpty()) {
            // Sales transactions (include payments and refunds if recorded here; amounts may be negative for refunds)
            $salesRows = Transaction::leftJoin('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
                ->leftJoin('customers', 'transactions.customer_id', '=', 'customers.id')
                ->whereIn('transactions.payment_method', $bankNames)
                ->where(function ($q) use ($subshopId) {
                    $q->where('sales_orders.subshop_id', $subshopId)
                      ->orWhere('customers.subshop_id', $subshopId);
                })
                ->groupBy('transactions.payment_method')
                ->selectRaw('transactions.payment_method, SUM(transactions.total_amount) as total_amount')
                ->get();
            $salesByName = $salesRows->pluck('total_amount', 'payment_method');

            // Purchases transactions (payments reduce balance; refunds may be negative and will offset)
            $purchaseRows = PurchasesTransactions::leftJoin('purchase_orders', 'purchases_transactions.purchase_order_id', '=', 'purchase_orders.id')
                ->whereIn('purchases_transactions.payment_method', $bankNames)
                ->where('purchase_orders.subshop_id', $subshopId)
                ->groupBy('purchases_transactions.payment_method')
                ->selectRaw('purchases_transactions.payment_method, SUM(purchases_transactions.total_amount) as total_amount')
                ->get();
            $purchasesByName = $purchaseRows->pluck('total_amount', 'payment_method');
        }

        if ($bankIds->isNotEmpty()) {
            // Expenses (approved only) reduce balance; payment_method stores bank id
            $expenseRows = Expenses::where('subshop_id', $subshopId)
                ->whereIn('payment_method', $bankIds)
                ->where('status', 'approved')
                ->groupBy('payment_method')
                ->selectRaw('payment_method, SUM(amount) as total_amount')
                ->get();
            $expensesById = $expenseRows->pluck('total_amount', 'payment_method');
        }

        // Compute net delta per bank name for the page
        $bankTotals = collect();
        foreach ($pageBanks as $b) {
            $sales = (float) ($salesByName[$b->name] ?? 0);
            $purch = (float) ($purchasesByName[$b->name] ?? 0);
            $exps  = (float) ($expensesById[$b->id] ?? 0);
            $netDelta = $sales - $purch - $exps;
            $bankTotals[$b->name] = $netDelta;
        }

        // ------------------------------------------------------------------
        // Overall totals across ALL banks in this subshop (not just the page)
        // Inflows: Sales payments (+) + Supplier refunds (purchase transactions negative -> inflow)
        // Outflows: Purchase payments (+) + Expenses (approved) + Sales refunds (negative sales -> outflow)
        // Current Total Balance: Sum(opening balances) + (Inflows - Outflows)
        // ------------------------------------------------------------------
        $allBanks = Banks::where('subshop_id', $subshopId)->get(['id','name','opening_balance']);
        $allBankNames = $allBanks->pluck('name')->filter()->unique()->values();
        $allBankIds = $allBanks->pluck('id')->unique()->values();
        $openingTotal = (float) $allBanks->sum(function($b){ return (float)($b->opening_balance ?? 0); });

        $salesPos = 0.0;      // sales payments increase bank
        $salesNegAbs = 0.0;   // sales refunds decrease bank
        if ($allBankNames->isNotEmpty()) {
            // Positive sales (payments)
            $salesPos = (float) Transaction::leftJoin('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
                ->leftJoin('customers', 'transactions.customer_id', '=', 'customers.id')
                ->whereIn('transactions.payment_method', $allBankNames)
                ->where(function ($q) use ($subshopId) {
                    $q->where('sales_orders.subshop_id', $subshopId)
                      ->orWhere('customers.subshop_id', $subshopId);
                })
                ->where('transactions.total_amount', '>', 0)
                ->sum('transactions.total_amount');

            // Negative sales (refunds) counted as outflow
            $salesNegAbs = (float) Transaction::leftJoin('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
                ->leftJoin('customers', 'transactions.customer_id', '=', 'customers.id')
                ->whereIn('transactions.payment_method', $allBankNames)
                ->where(function ($q) use ($subshopId) {
                    $q->where('sales_orders.subshop_id', $subshopId)
                      ->orWhere('customers.subshop_id', $subshopId);
                })
                ->where('transactions.total_amount', '<', 0)
                ->sum(DB::raw('ABS(transactions.total_amount)'));
        }

        $purchPos = 0.0;     // purchase payments reduce bank -> outflow
        $purchNegAbs = 0.0;  // supplier refunds increase bank -> inflow
        if ($allBankNames->isNotEmpty()) {
            $purchPos = (float) PurchasesTransactions::leftJoin('purchase_orders', 'purchases_transactions.purchase_order_id', '=', 'purchase_orders.id')
                ->whereIn('purchases_transactions.payment_method', $allBankNames)
                ->where('purchase_orders.subshop_id', $subshopId)
                ->where('purchases_transactions.total_amount', '>', 0)
                ->sum('purchases_transactions.total_amount');

            $purchNegAbs = (float) PurchasesTransactions::leftJoin('purchase_orders', 'purchases_transactions.purchase_order_id', '=', 'purchase_orders.id')
                ->whereIn('purchases_transactions.payment_method', $allBankNames)
                ->where('purchase_orders.subshop_id', $subshopId)
                ->where('purchases_transactions.total_amount', '<', 0)
                ->sum(DB::raw('ABS(purchases_transactions.total_amount)'));
        }

        $expensesApproved = 0.0;
        if ($allBankIds->isNotEmpty()) {
            $expensesApproved = (float) Expenses::where('subshop_id', $subshopId)
                ->whereIn('payment_method', $allBankIds)
                ->where('status', 'approved')
                ->sum('amount');
        }

        $inflowTotal = (float) ($salesPos + $purchNegAbs);
        $outflowTotal = (float) ($purchPos + $expensesApproved + $salesNegAbs);
        $netTotal = (float) ($inflowTotal - $outflowTotal);
        $currentTotal = (float) ($openingTotal + $netTotal);

        $bankSummaryTotals = [
            'opening_total' => $openingTotal,
            'inflow_total' => $inflowTotal,
            'outflow_total' => $outflowTotal,
            'net_total' => $netTotal,
            'current_total' => $currentTotal,
        ];

        return view('banks.banks', compact('banks', 'subshop', 'bankTotals', 'bankSummaryTotals'));
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
        $request->validate([
            'subshop_id' => 'required|exists:sub_shops,id',
            'name' => 'required|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'required|string|max:100',
            'branch' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'opening_balance' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $data = $request->only(['subshop_id','name','account_name','account_number','branch','email','phone','opening_balance','notes']);
        $data['is_active'] = $request->has('is_active');

        // Unique per subshop account number (soft-deleted can be restored)
        $existing = Banks::withTrashed()
            ->where('subshop_id', $data['subshop_id'])
            ->where('account_number', $data['account_number'])
            ->first();

        if ($existing && $existing->trashed()) {
            $existing->restore();
            $existing->update($data);
            $bank = $existing;
            $message = 'Bank restored and updated successfully.';
        } else {
            $bank = Banks::create($data);
            $message = 'Bank created successfully.';
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'bank' => $bank]);
        }

        return redirect()->back()->with('success', $message);
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
        $request->validate([
            'name' => 'required|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'opening_balance' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $bank = Banks::findOrFail($id);
        $data = $request->only(['name','account_name','branch','email','phone','opening_balance','notes']);
        $data['is_active'] = $request->has('is_active');
        $bank->update($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Bank updated successfully.', 'bank' => $bank]);
        }

        return redirect()->back()->with('success', 'Bank updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $bank = Banks::findOrFail($id);
        $bank->delete();

        return response()->json(['success' => true, 'message' => 'Bank deleted successfully.']);
    }

    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('banks.index')]);
    }

    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('banks.index')])
                ->with('error', 'Please select a shop first');
        }

        $viewType = $request->input('view', 'statement'); // statement | summary
        $includePending = (bool) $request->boolean('include_pending', false);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $banksQ = Banks::where('subshop_id', $subshopId);
        if ($request->filled('bank_id')) {
            $banksQ->where('id', (int)$request->input('bank_id'));
        }
        $banks = $banksQ->orderBy('name')->get();
        if ($banks->isEmpty()) {
            return back()->with('error', 'No banks found for export.');
        }

        // Helper to fetch normalized ledger rows for a set of banks
        $rows = collect();
        foreach ($banks as $bank) {
            $bankId = $bank->id;
            $bankName = $bank->name;

            // Sales/Returns from transactions (by bank name)
            $sales = Transaction::leftJoin('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
                ->leftJoin('customers', 'transactions.customer_id', '=', 'customers.id')
                ->where('transactions.payment_method', $bankName)
                ->where(function ($q) use ($subshopId) {
                    $q->where('sales_orders.subshop_id', $subshopId)
                      ->orWhere('customers.subshop_id', $subshopId);
                });
            if ($dateFrom) { $sales->whereDate('transactions.created_at', '>=', $dateFrom); }
            if ($dateTo)   { $sales->whereDate('transactions.created_at', '<=', $dateTo); }
            $salesRows = $sales->get(['transactions.id','transactions.created_at','transactions.total_amount']);

            foreach ($salesRows as $s) {
                $amount = (float)$s->total_amount;
                $rows->push([
                    'bank_id' => $bankId,
                    'bank_name' => $bankName,
                    'date' => optional($s->created_at)->format('Y-m-d'),
                    'source' => $amount >= 0 ? 'Sales Payment' : 'Sales Refund',
                    'reference' => 'TX-'.$s->id,
                    'description' => null,
                    'inflow' => $amount > 0 ? $amount : 0,
                    'outflow' => $amount < 0 ? abs($amount) : 0,
                ]);
            }

            // Purchases/Refunds from purchases_transactions (by bank name)
            $purch = PurchasesTransactions::leftJoin('purchase_orders', 'purchases_transactions.purchase_order_id', '=', 'purchase_orders.id')
                ->where('purchases_transactions.payment_method', $bankName)
                ->where('purchase_orders.subshop_id', $subshopId);
            if ($dateFrom) { $purch->whereDate('purchases_transactions.created_at', '>=', $dateFrom); }
            if ($dateTo)   { $purch->whereDate('purchases_transactions.created_at', '<=', $dateTo); }
            $purchRows = $purch->get(['purchases_transactions.id','purchases_transactions.created_at','purchases_transactions.total_amount']);

            foreach ($purchRows as $p) {
                $amount = (float)$p->total_amount;
                $rows->push([
                    'bank_id' => $bankId,
                    'bank_name' => $bankName,
                    'date' => optional($p->created_at)->format('Y-m-d'),
                    'source' => $amount >= 0 ? 'Purchase Payment' : 'Supplier Refund',
                    'reference' => 'PTX-'.$p->id,
                    'description' => null,
                    'inflow' => $amount < 0 ? abs($amount) : 0, // refunds increase bank
                    'outflow' => $amount > 0 ? $amount : 0,     // payments decrease bank
                ]);
            }

            // Expenses by bank id
            $exp = Expenses::where('subshop_id', $subshopId)
                ->where('payment_method', $bankId);
            if (!$includePending) { $exp->where('status', 'approved'); }
            else { $exp->whereIn('status', ['approved','pending']); }
            if ($dateFrom) { $exp->whereDate('expense_date', '>=', $dateFrom); }
            if ($dateTo)   { $exp->whereDate('expense_date', '<=', $dateTo); }
            $expRows = $exp->get(['id','expense_date','amount','title']);
            foreach ($expRows as $e) {
                $rows->push([
                    'bank_id' => $bankId,
                    'bank_name' => $bankName,
                    'date' => optional($e->expense_date)->format('Y-m-d'),
                    'source' => 'Expense',
                    'reference' => 'EXP-'.$e->id,
                    'description' => $e->title,
                    'inflow' => 0,
                    'outflow' => (float)$e->amount,
                ]);
            }
        }

        // If statement view and single bank, compute running balance and optional opening-at-start
        $rows = $rows->sortBy(['bank_name','date'])->values();

        if ($viewType === 'statement') {
            // Expecting single bank for statement; if multiple, we still include bank column
            // Compute opening_at_start per bank. Default to bank's opening balance when no date_from.
            $openingByBank = collect();
            foreach ($banks as $bank) {
                $bankId = $bank->id;
                $bankName = $bank->name;
                // baseline opening is the configured opening_balance
                $opening = (float)($bank->opening_balance ?? 0);
                if ($dateFrom) {
                    // inflows/outflows before dateFrom
                    $salesBefore = Transaction::leftJoin('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
                        ->leftJoin('customers', 'transactions.customer_id', '=', 'customers.id')
                        ->where('transactions.payment_method', $bankName)
                        ->where(function ($q) use ($subshopId) {
                            $q->where('sales_orders.subshop_id', $subshopId)
                              ->orWhere('customers.subshop_id', $subshopId);
                        })
                        ->whereDate('transactions.created_at', '<', $dateFrom)
                        ->sum('transactions.total_amount');

                    $purchBefore = PurchasesTransactions::leftJoin('purchase_orders', 'purchases_transactions.purchase_order_id', '=', 'purchase_orders.id')
                        ->where('purchases_transactions.payment_method', $bankName)
                        ->where('purchase_orders.subshop_id', $subshopId)
                        ->whereDate('purchases_transactions.created_at', '<', $dateFrom)
                        ->sum('purchases_transactions.total_amount');

                    $expBeforeQ = Expenses::where('subshop_id', $subshopId)
                        ->where('payment_method', $bankId)
                        ->whereDate('expense_date', '<', $dateFrom);
                    if (!$includePending) { $expBeforeQ->where('status', 'approved'); }
                    else { $expBeforeQ->whereIn('status', ['approved','pending']); }
                    $expBefore = (float)$expBeforeQ->sum('amount');

                    $opening = $opening + (float)$salesBefore - (float)$purchBefore - (float)$expBefore;
                }
                $openingByBank[$bankId] = $opening;
            }

            // Compute running balances per bank
            $runningByBank = collect();
            $statementRows = $rows->map(function($r) use ($runningByBank, $openingByBank) {
                $bankId = $r['bank_id'];
                if (!$runningByBank->has($bankId)) {
                    $runningByBank[$bankId] = (float) ($openingByBank[$bankId] ?? 0);
                }
                $running = $runningByBank[$bankId] + (float)$r['inflow'] - (float)$r['outflow'];
                $runningByBank[$bankId] = $running;
                return $r + ['running_balance' => $running];
            });

            // Export
            if ($format === 'csv') {
                return response()->stream(function () use ($statementRows, $dateFrom) {
                    $h = fopen('php://output', 'w');
                    fputcsv($h, ['Bank','Date','Source','Reference','Description','In','Out','Running Balance']);
                    foreach ($statementRows as $row) {
                        fputcsv($h, [
                            $row['bank_name'], $row['date'], $row['source'], $row['reference'], $row['description'],
                            number_format((float)$row['inflow'], 2, '.', ''),
                            number_format((float)$row['outflow'], 2, '.', ''),
                            number_format((float)$row['running_balance'], 2, '.', ''),
                        ]);
                    }
                    fclose($h);
                }, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="bank_statement_'.now()->format('Y-m-d_H-i-s').'.csv"',
                ]);
            }

            if ($format === 'excel') {
                $arr = $statementRows->map(function($row){
                    return [
                        'Bank' => $row['bank_name'],
                        'Date' => $row['date'],
                        'Source' => $row['source'],
                        'Reference' => $row['reference'],
                        'Description' => $row['description'],
                        'In' => (float)$row['inflow'],
                        'Out' => (float)$row['outflow'],
                        'Running Balance' => (float)$row['running_balance'],
                    ];
                });
                return Excel::download(new \App\Exports\GenericArrayExport($arr->toArray(), 'Bank Statement'), 'bank_statement_'.now()->format('Y-m-d_H-i-s').'.xlsx');
            }

            if ($format === 'pdf') {
                $subshop = SubShop::find($subshopId);
                // Build summary per bank
                $stmtSummary = $statementRows->groupBy('bank_id')->map(function($items) use ($openingByBank) {
                    $bankId = $items->first()['bank_id'];
                    $bankName = $items->first()['bank_name'];
                    $in = (float)$items->sum('inflow');
                    $out = (float)$items->sum('outflow');
                    $closing = (float)optional($items->last())['running_balance'] ?? (float)($openingByBank[$bankId] ?? 0);
                    $opening = (float)($openingByBank[$bankId] ?? 0);
                    return [
                        'bank_id' => $bankId,
                        'bank_name' => $bankName,
                        'opening' => $opening,
                        'inflow' => $in,
                        'outflow' => $out,
                        'net' => $in - $out,
                        'closing' => $closing,
                        'count' => $items->count(),
                    ];
                })->values();

                $pdf = PDF::loadView('exports.banks_statement_pdf', [
                    'rows' => $statementRows,
                    'summary' => $stmtSummary,
                    'subshop' => $subshop,
                    'banks' => $banks,
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                    'generatedBy' => optional(auth()->user())->name ?? 'System',
                ]);
                return $pdf->download('bank_statement_'.now()->format('Y-m-d_H-i-s').'.pdf');
            }
        }

        // Summary view
        $summary = $rows->groupBy('bank_id')->map(function($items) {
            $bankName = optional($items->first())['bank_name'] ?? '';
            $inflow = (float) $items->sum('inflow');
            $outflow = (float) $items->sum('outflow');
            return [
                'bank_id' => $items->first()['bank_id'],
                'bank_name' => $bankName,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'net' => $inflow - $outflow,
            ];
        })->values();

        if ($format === 'csv') {
            return response()->stream(function () use ($summary) {
                $h = fopen('php://output', 'w');
                fputcsv($h, ['Bank','Inflows','Outflows','Net']);
                foreach ($summary as $row) {
                    fputcsv($h, [
                        $row['bank_name'],
                        number_format((float)$row['inflow'], 2, '.', ''),
                        number_format((float)$row['outflow'], 2, '.', ''),
                        number_format((float)$row['net'], 2, '.', ''),
                    ]);
                }
                fclose($h);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="bank_performance_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            $arr = $summary->map(function($row){
                return [
                    'Bank' => $row['bank_name'],
                    'Inflows' => (float)$row['inflow'],
                    'Outflows' => (float)$row['outflow'],
                    'Net' => (float)$row['net'],
                ];
            });
            return Excel::download(new \App\Exports\GenericArrayExport($arr->toArray(), 'Bank Performance'), 'bank_performance_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = SubShop::find($subshopId);
            $totals = [
                'inflow' => (float) $summary->sum('inflow'),
                'outflow' => (float) $summary->sum('outflow'),
                'net' => (float) $summary->sum('net'),
                'count' => (int) $summary->count(),
            ];
            $pdf = PDF::loadView('exports.banks_performance_pdf', [
                'rows' => $summary,
                'totals' => $totals,
                'subshop' => $subshop,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('bank_performance_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }
}
