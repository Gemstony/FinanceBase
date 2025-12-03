<?php

namespace App\Http\Controllers;

use App\Models\Suppliers;
use App\Models\SubShop;
use App\Models\PurchaseOrders;
use App\Models\PurchaseOrdersItems;
use App\Models\PurchasesTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SuppliersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('suppliers.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('suppliers.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        // Build stats subquery from purchase orders with optional date filters
        $statsSub = PurchaseOrders::selectRaw('supplier_id, COUNT(*) as orders_count, COALESCE(SUM(grand_total),0) as total_spent')
            ->where('subshop_id', $subshopId);
        if ($request->filled('date_from')) {
            $statsSub->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $statsSub->whereDate('created_at', '<=', $request->input('date_to'));
        }
        $statsSub->groupBy('supplier_id');

        // Base suppliers query with stats
        $suppliers = Suppliers::where('suppliers.subshop_id', $subshopId)
            ->leftJoinSub($statsSub, 'stats', function($join){
                $join->on('stats.supplier_id', '=', 'suppliers.id');
            })
            ->select('suppliers.*', DB::raw('COALESCE(stats.orders_count,0) as orders_count'), DB::raw('COALESCE(stats.total_spent,0) as total_spent'));

        // Filters
        if ($request->filled('search')) {
            $search = $request->search;
            $suppliers->where(function($q) use ($search){
                $q->where('suppliers.name', 'like', "%{$search}%")
                  ->orWhere('suppliers.email', 'like', "%{$search}%")
                  ->orWhere('suppliers.phone', 'like', "%{$search}%")
                  ->orWhere('suppliers.contact_person', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status') && in_array($request->status, ['active','inactive'])) {
            $suppliers->where('suppliers.is_active', $request->status === 'active' ? 1 : 0);
        }
        if ($request->filled('min_orders')) {
            $suppliers->whereRaw('COALESCE(stats.orders_count,0) >= ?', [(int)$request->input('min_orders')]);
        }
        if ($request->filled('max_orders')) {
            $suppliers->whereRaw('COALESCE(stats.orders_count,0) <= ?', [(int)$request->input('max_orders')]);
        }
        if ($request->filled('min_spent')) {
            $suppliers->whereRaw('COALESCE(stats.total_spent,0) >= ?', [(float)$request->input('min_spent')]);
        }
        if ($request->filled('max_spent')) {
            $suppliers->whereRaw('COALESCE(stats.total_spent,0) <= ?', [(float)$request->input('max_spent')]);
        }
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $suppliers->whereRaw('COALESCE(stats.orders_count,0) > 0');
        }

        // Sorting
        $sort = $request->input('sort');
        if ($sort === 'name_asc') {
            $suppliers->orderBy('suppliers.name', 'asc');
        } elseif ($sort === 'name_desc') {
            $suppliers->orderBy('suppliers.name', 'desc');
        } elseif ($sort === 'date_asc') {
            $suppliers->orderBy('suppliers.created_at', 'asc');
        } elseif ($sort === 'orders_desc') {
            $suppliers->orderByRaw('COALESCE(stats.orders_count,0) desc');
        } elseif ($sort === 'orders_asc') {
            $suppliers->orderByRaw('COALESCE(stats.orders_count,0) asc');
        } elseif ($sort === 'spent_desc') {
            $suppliers->orderByRaw('COALESCE(stats.total_spent,0) desc');
        } elseif ($sort === 'spent_asc') {
            $suppliers->orderByRaw('COALESCE(stats.total_spent,0) asc');
        } elseif ($sort === 'status') {
            $suppliers->orderBy('suppliers.is_active', 'desc');
        } else {
            $suppliers->orderBy('suppliers.created_at', 'desc');
        }

        $suppliers = $suppliers->paginate(10)->appends($request->query());

        return view("inventory.suppliers.suppliers", compact("suppliers", "subshop"));
    }

    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('suppliers.index')])
                ->with('error', 'Please select a shop first');
        }

        $statsSub = PurchaseOrders::selectRaw('supplier_id, COUNT(*) as orders_count, COALESCE(SUM(grand_total),0) as total_spent')
            ->where('subshop_id', $subshopId);
        if ($request->filled('date_from')) {
            $statsSub->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $statsSub->whereDate('created_at', '<=', $request->input('date_to'));
        }
        $statsSub->groupBy('supplier_id');

        $base = Suppliers::where('suppliers.subshop_id', $subshopId)
            ->leftJoinSub($statsSub, 'stats', function($join){
                $join->on('stats.supplier_id', '=', 'suppliers.id');
            })
            ->select('suppliers.*', DB::raw('COALESCE(stats.orders_count,0) as orders_count'), DB::raw('COALESCE(stats.total_spent,0) as total_spent'));

        if ($request->filled('search')) {
            $search = $request->search;
            $base->where(function($q) use ($search){
                $q->where('suppliers.name', 'like', "%{$search}%")
                  ->orWhere('suppliers.email', 'like', "%{$search}%")
                  ->orWhere('suppliers.phone', 'like', "%{$search}%")
                  ->orWhere('suppliers.contact_person', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status') && in_array($request->status, ['active','inactive'])) {
            $base->where('suppliers.is_active', $request->status === 'active' ? 1 : 0);
        }
        if ($request->filled('min_orders')) {
            $base->whereRaw('COALESCE(stats.orders_count,0) >= ?', [(int)$request->input('min_orders')]);
        }
        if ($request->filled('max_orders')) {
            $base->whereRaw('COALESCE(stats.orders_count,0) <= ?', [(int)$request->input('max_orders')]);
        }
        if ($request->filled('min_spent')) {
            $base->whereRaw('COALESCE(stats.total_spent,0) >= ?', [(float)$request->input('min_spent')]);
        }
        if ($request->filled('max_spent')) {
            $base->whereRaw('COALESCE(stats.total_spent,0) <= ?', [(float)$request->input('max_spent')]);
        }
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $base->whereRaw('COALESCE(stats.orders_count,0) > 0');
        }

        $sort = $request->input('sort');
        if ($sort === 'name_asc') {
            $base->orderBy('suppliers.name', 'asc');
        } elseif ($sort === 'name_desc') {
            $base->orderBy('suppliers.name', 'desc');
        } elseif ($sort === 'date_asc') {
            $base->orderBy('suppliers.created_at', 'asc');
        } elseif ($sort === 'orders_desc') {
            $base->orderByRaw('COALESCE(stats.orders_count,0) desc');
        } elseif ($sort === 'orders_asc') {
            $base->orderByRaw('COALESCE(stats.orders_count,0) asc');
        } elseif ($sort === 'spent_desc') {
            $base->orderByRaw('COALESCE(stats.total_spent,0) desc');
        } elseif ($sort === 'spent_asc') {
            $base->orderByRaw('COALESCE(stats.total_spent,0) asc');
        } elseif ($sort === 'status') {
            $base->orderBy('suppliers.is_active', 'desc');
        } else {
            $base->orderBy('suppliers.created_at', 'desc');
        }

        $rows = $base->get();

        if ($format === 'csv') {
            return response()->stream(function () use ($rows) {
                $h = fopen('php://output', 'w');
                fputcsv($h, ['Name','Contact Person','Email','Phone','Status','Orders','Total Spent','Joined']);
                foreach ($rows as $e) {
                    fputcsv($h, [
                        $e->name,
                        $e->contact_person,
                        $e->email,
                        $e->phone,
                        $e->is_active ? 'ACTIVE' : 'INACTIVE',
                        (int)($e->orders_count ?? 0),
                        number_format((float)($e->total_spent ?? 0), 2, '.', ''),
                        optional($e->created_at)->format('Y-m-d'),
                    ]);
                }
                fclose($h);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="suppliers_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            $exportRows = $rows->map(function($e){
                return [
                    'Name' => $e->name,
                    'Contact Person' => $e->contact_person,
                    'Email' => $e->email,
                    'Phone' => $e->phone,
                    'Status' => $e->is_active ? 'ACTIVE' : 'INACTIVE',
                    'Orders' => (int)($e->orders_count ?? 0),
                    'Total Spent' => (float)($e->total_spent ?? 0),
                    'Joined' => optional($e->created_at)->format('Y-m-d'),
                ];
            });
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\GenericArrayExport($exportRows->toArray(), 'Suppliers'), 'suppliers_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = SubShop::find($subshopId);
            $summary = [
                'count' => $rows->count(),
                'total_orders' => (int) $rows->sum(function($r){ return (int)($r->orders_count ?? 0); }),
                'total_spent' => (float) $rows->sum(function($r){ return (float)($r->total_spent ?? 0); }),
                'active_count' => (int) $rows->where('is_active', true)->count(),
                'inactive_count' => (int) $rows->where('is_active', false)->count(),
            ];
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.suppliers_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('suppliers_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    /**
     * Download a sample CSV file for suppliers import
     */
    public function downloadSample()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=suppliers_import_sample.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            // BOM for Excel
            fputs($handle, "\xEF\xBB\xBF");
            // Headers (name is required)
            fputcsv($handle, [
                'name*',
                'contact_person',
                'email',
                'phone',
                'address',
                'is_active' // 1 or 0
            ]);

            // Sample rows
            fputcsv($handle, ['Supplier 1', 'John Doe', 'abc@example.com', '0712345678', 'Kariakoo, Dar es Salaam', '1']);
            fputcsv($handle, ['Supplier 2', '', '', '', '', '']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import suppliers from CSV
     */
    public function import(Request $request)
    {
        if (!$request->hasFile('import_file')) {
            return response()->json([
                'success' => false,
                'message' => 'No file was uploaded.'
            ], 400);
        }

        // Resolve subshop
        $subshopId = (int) ($request->input('subshop_id') ?: session('subshop_id'));
        if (!$subshopId) {
            return response()->json([
                'success' => false,
                'message' => 'No subshop selected. Please choose a shop first.'
            ], 400);
        }

        $hasHeaders = filter_var($request->input('has_headers', true), FILTER_VALIDATE_BOOLEAN);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        try {
            $file = $request->file('import_file');
            if (($handle = fopen($file->getRealPath(), 'r')) === false) {
                throw new \Exception('Unable to open uploaded file.');
            }

            // Read rows
            $rowIndex = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $rowIndex++;
                // Normalize to avoid BOM issues on first cell (apply on every row)
                if (isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
                }

                // Skip header row if indicated
                if ($rowIndex === 1 && $hasHeaders) {
                    continue;
                }

                // Fallback: if a row looks like header, skip it (handles stray blank line before headers)
                $first = isset($row[0]) ? strtolower(trim($row[0])) : '';
                if (in_array($first, ['name', 'name*'])) {
                    // additional sanity: second column may be contact_person
                    // skip this row as header
                    continue;
                }

                // Normalize row to at least 6 columns
                for ($i = 0; $i < 6; $i++) {
                    if (!isset($row[$i])) { $row[$i] = null; }
                }

                [$name, $contactPerson, $email, $phone, $address, $isActive] = $row;

                // Basic validation
                if (!$name || trim($name) === '') {
                    $skipped++;
                    $errors[] = "Row {$rowIndex}: Missing required 'name'";
                    continue;
                }

                try {
                    // Determine is_active: default to 1 when missing or blank
                    $active = 1;
                    if (isset($isActive) && trim((string)$isActive) !== '') {
                        $active = (trim((string)$isActive) == '1') ? 1 : 0;
                    }

                    $data = [
                        'subshop_id' => $subshopId,
                        'name' => trim($name),
                        'contact_person' => $contactPerson ? trim($contactPerson) : null,
                        'email' => $email ? trim($email) : null,
                        'phone' => $phone ? trim($phone) : null,
                        'address' => $address ? trim($address) : null,
                        'is_active' => $active,
                    ];

                    // If email present, upsert within same subshop, else create new
                    if (!empty($data['email'])) {
                        $existing = Suppliers::withTrashed()
                            ->where('subshop_id', $subshopId)
                            ->where('email', $data['email'])
                            ->first();
                        if ($existing) {
                            if ($existing->trashed()) { $existing->restore(); }
                            $existing->update($data);
                            $imported++;
                            continue;
                        }
                    }

                    Suppliers::create($data);
                    $imported++;
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = "Row {$rowIndex}: " . $e->getMessage();
                }
            }
            fclose($handle);

            $total = $imported + $skipped;
            if ($total === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found in the CSV file.',
                    'total_rows' => 0,
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => []
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully processed {$total} rows. Imported: {$imported}, Skipped: {$skipped}",
                'total_rows' => $total,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'errors' => [$e->getMessage()],
                'total_rows' => 0,
                'imported' => 0,
                'skipped' => 0,
            ], 400);
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
            $request->validate([
                'subshop_id' => 'required|exists:sub_shops,id',
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'contact_person' => 'nullable|string|max:255',
            ]);

            $data = $request->only(['subshop_id', 'name', 'email', 'phone', 'address', 'contact_person']);
            $data['is_active'] = $request->has('is_active');

            // Check if there's a soft deleted supplier with the same email in the same shop
            $existingSupplier = null;
            if ($data['email']) {
                $existingSupplier = Suppliers::withTrashed()
                    ->where('email', $data['email'])
                    ->where('subshop_id', $data['subshop_id'])
                    ->first();
            }

            if ($existingSupplier && $existingSupplier->trashed()) {
                // Restore the soft deleted supplier and update its data
                $existingSupplier->restore();
                $existingSupplier->update($data);

                $message = 'Supplier restored and updated successfully.';
                $supplier = $existingSupplier;
            } else {
                // Create new supplier (email can be duplicate across different shops)
                $supplier = Suppliers::create($data);
                $message = 'Supplier created successfully.';
            }

            // Check if this is an AJAX request
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'supplier' => $supplier
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $supplier = Suppliers::withTrashed()->findOrFail($id);
        return response()->json($supplier);
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
    public function update(Request $request, Suppliers $supplier)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
        ]);

        $data = $request->only(['name', 'email', 'phone', 'address', 'contact_person']);
        $data['is_active'] = $request->has('is_active');

        $supplier->update($data);

        return redirect()->back()->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Suppliers $supplier)
    {
        $supplier->delete();

        return redirect()->back()->with('success', 'Supplier deleted successfully.');
    }

    public function subshops(){
        return redirect()->route('subshops.choose', ['intended' => route('suppliers.index')]);
    }

    /**
     * API: Supplier purchases (purchase orders) history list
     */
    public function apiPurchases(Suppliers $supplier)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return response()->json(['error' => 'No subshop selected'], 400);
            }

            // Verify supplier belongs to the current subshop
            if ($supplier->subshop_id != $subshopId) {
                return response()->json(['error' => 'Supplier not found in current subshop'], 404);
            }

            $paymentsSub = PurchasesTransactions::selectRaw('purchase_order_id, SUM(total_amount) as paid_total')
                ->groupBy('purchase_order_id');

            // First, get the purchase orders with payment info
            $purchases = PurchaseOrders::select([
                    'purchase_orders.id',
                    'purchase_orders.order_no',
                    'purchase_orders.created_at',
                    'purchase_orders.grand_total',
                    'purchase_orders.status',
                    'purchase_orders.amount_paid',
                    'payments.paid_total',
                    DB::raw('(SELECT COUNT(*) FROM purchase_orders_items WHERE purchase_orders_items.purchase_order_id = purchase_orders.id) as items_count')
                ])
                ->leftJoinSub($paymentsSub, 'payments', function($join) {
                    $join->on('purchase_orders.id', '=', 'payments.purchase_order_id');
                })
                ->where('purchase_orders.supplier_id', $supplier->id)
                ->where('purchase_orders.subshop_id', $subshopId)
                ->orderBy('purchase_orders.created_at', 'desc')
                ->get()
                ->map(function($o) {
                    $paid = (float)($o->paid_total ?? 0);
                    $remain = max(0, (float)$o->grand_total - $paid);
                    $status = strtolower($o->status) ?? 'pending';
                    
                    // Map status to a more user-friendly format if needed
                    $statusMap = [
                        'completed' => 'completed',
                        'pending' => 'pending',
                        'cancelled' => 'cancelled',
                        'partial' => 'partial',
                        'paid' => 'completed',
                        'unpaid' => 'pending'
                    ];
                    
                    $displayStatus = $statusMap[$status] ?? $status;
                    
                    return [
                        'id' => $o->id,
                        'order_no' => $o->order_no,
                        'date' => optional($o->created_at)->format('Y-m-d H:i:s'),
                        'items' => $o->items_count ?? 0,
                        'grand_total' => (float)$o->grand_total,
                        'paid' => $paid,
                        'remaining' => $remain,
                        'status' => $displayStatus,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $purchases,
                'pagination' => [
                    'total' => $purchases->count(),
                    'per_page' => $purchases->count(),
                    'current_page' => 1,
                    'last_page' => 1,
                    'from' => 1,
                    'to' => $purchases->count(),
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json(['purchases' => [], 'error' => true, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * API: Supplier statistics (quick stats, monthly purchases, top products, recent activity)
     */
    public function apiStats(Suppliers $supplier)
    {
        try {
            $paymentsSub = PurchasesTransactions::selectRaw('purchase_order_id, SUM(total_amount) as paid_total')
                ->where('transaction_type', 'payment')
                ->groupBy('purchase_order_id');

            $base = PurchaseOrders::where('supplier_id', $supplier->id)
                ->leftJoinSub($paymentsSub, 'pays', function($join){
                    $join->on('pays.purchase_order_id', '=', 'purchase_orders.id');
                })
                ->select('purchase_orders.*', DB::raw('COALESCE(pays.paid_total,0) as paid_total'));

            $orders = (clone $base)->get();

            $totalOrders = $orders->count();
            $totalSpent = (float) $orders->sum('grand_total');
            $avgOrder = $totalOrders > 0 ? ($totalSpent / $totalOrders) : 0.0;
            $lastOrder = optional($orders->sortByDesc('created_at')->first())->created_at;

            // Monthly purchases for last 12 months
            $since = now()->subMonths(11)->startOfMonth();
            $monthlyOrders = PurchaseOrders::where('supplier_id', $supplier->id)
                ->where('created_at', '>=', $since)
                ->get(['grand_total','created_at']);

            $monthMap = [];
            for($i=11; $i>=0; $i--){
                $m = now()->subMonths($i);
                $key = $m->format('Y-m');
                $monthMap[$key] = 0.0;
            }
            foreach($monthlyOrders as $o){
                $key = optional($o->created_at)->format('Y-m');
                if(isset($monthMap[$key])){
                    $monthMap[$key] += (float)$o->grand_total;
                }
            }
            $labels = [];
            $values = [];
            foreach($monthMap as $key => $val){
                $labels[] = \Carbon\Carbon::createFromFormat('Y-m', $key)->format('M');
                $values[] = (float) $val;
            }

            // Top products by amount from purchase order items
            $prodRows = PurchaseOrdersItems::query()
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_orders_items.purchase_order_id')
                ->leftJoin('items', 'items.id', '=', 'purchase_orders_items.item_id')
                ->where('purchase_orders.supplier_id', $supplier->id)
                ->groupBy('items.name')
                ->selectRaw("COALESCE(items.name, 'Unnamed') as product, SUM(purchase_orders_items.line_total) as total")
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            $prodTotal = (float) ($prodRows->sum('total') ?: 1);
            $products = $prodRows->map(function($r) use ($prodTotal){
                return [ 'label' => $r->product, 'value' => round(((float)$r->total / $prodTotal) * 100, 2) ];
            });

            // Recent activity: last 5 purchase orders
            $recent = PurchaseOrders::where('supplier_id', $supplier->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id','order_no','grand_total','created_at'])
                ->map(function($o){
                    return [
                        'order_no' => $o->order_no,
                        'grand_total' => (float)$o->grand_total,
                        'date' => optional($o->created_at)->format('Y-m-d H:i'),
                    ];
                });

            return response()->json([
                'quick' => [
                    'total_orders' => $totalOrders,
                    'total_spent' => $totalSpent,
                    'avg_order' => $avgOrder,
                    'last_order' => $lastOrder ? $lastOrder->format('Y-m-d') : null,
                ],
                'monthly' => [ 'labels' => $labels, 'values' => $values ],
                'products' => $products,
                'recent' => $recent,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'quick' => [ 'total_orders' => 0, 'total_spent' => 0, 'avg_order' => 0, 'last_order' => null ],
                'monthly' => [ 'labels' => [], 'values' => [] ],
                'products' => [],
                'recent' => [],
                'error' => true,
                'message' => $e->getMessage(),
            ], 200);
        }
    }
}
