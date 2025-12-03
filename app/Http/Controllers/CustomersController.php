<?php

namespace App\Http\Controllers;

use App\Models\Customers;
use App\Models\SubShop;
use App\Models\SalesOrders;
use App\Models\SalesOrdersItems;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('customers.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);

        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('customers.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        // Aggregate subquery: orders count and total spent per customer in current subshop (with optional date range)
        $statsSub = SalesOrders::selectRaw('customer_id, COUNT(*) as orders_count, COALESCE(SUM(grand_total),0) as total_spent')
            ->where('subshop_id', $subshopId);

        // Apply date range to orders for stats (so filters affect counts/totals)
        if ($request->filled('date_from')) {
            $statsSub->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $statsSub->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $statsSub->groupBy('customer_id');

        // Base query with stats
        $customers = Customers::where('customers.subshop_id', $subshopId)
            ->leftJoinSub($statsSub, 'stats', function($join){
                $join->on('stats.customer_id', '=', 'customers.id');
            })
            ->select('customers.*', DB::raw('COALESCE(stats.orders_count,0) as orders_count'), DB::raw('COALESCE(stats.total_spent,0) as total_spent'));

        // Search by name, email, phone, contact person
        if ($request->filled('search')) {
            $search = $request->search;
            $customers->where(function($q) use ($search){
                $q->where('customers.name', 'like', "%{$search}%")
                  ->orWhere('customers.email', 'like', "%{$search}%")
                  ->orWhere('customers.phone', 'like', "%{$search}%")
                  ->orWhere('customers.contact_person', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status') && in_array($request->status, ['active','inactive'])) {
            $customers->where('customers.is_active', $request->status === 'active' ? 1 : 0);
        }

        // If date range provided, show only customers with activity in that range
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $customers->whereRaw('COALESCE(stats.orders_count,0) > 0');
        }

        // Orders count range
        if ($request->filled('min_orders')) {
            $customers->whereRaw('COALESCE(stats.orders_count,0) >= ?', [(int)$request->input('min_orders')]);
        }
        if ($request->filled('max_orders')) {
            $customers->whereRaw('COALESCE(stats.orders_count,0) <= ?', [(int)$request->input('max_orders')]);
        }

        // Total spent range
        if ($request->filled('min_spent')) {
            $customers->whereRaw('COALESCE(stats.total_spent,0) >= ?', [(float)$request->input('min_spent')]);
        }
        if ($request->filled('max_spent')) {
            $customers->whereRaw('COALESCE(stats.total_spent,0) <= ?', [(float)$request->input('max_spent')]);
        }

        // Sorting
        $sort = $request->input('sort');
        if ($sort === 'name_asc') {
            $customers->orderBy('customers.name', 'asc');
        } elseif ($sort === 'name_desc') {
            $customers->orderBy('customers.name', 'desc');
        } elseif ($sort === 'date_asc') {
            $customers->orderBy('customers.created_at', 'asc');
        } elseif ($sort === 'orders_desc') {
            $customers->orderByRaw('COALESCE(stats.orders_count,0) desc');
        } elseif ($sort === 'orders_asc') {
            $customers->orderByRaw('COALESCE(stats.orders_count,0) asc');
        } elseif ($sort === 'spent_desc') {
            $customers->orderByRaw('COALESCE(stats.total_spent,0) desc');
        } elseif ($sort === 'spent_asc') {
            $customers->orderByRaw('COALESCE(stats.total_spent,0) asc');
        } elseif ($sort === 'status') {
            $customers->orderBy('customers.is_active', 'desc');
        } else {
            // default by date desc
            $customers->orderBy('customers.created_at', 'desc');
        }

        $customers = $customers->paginate(10)->appends($request->query());

        return view("sales.customers.customers", compact("customers", "subshop"));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Not used as we're using modals
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subshop selected. Please select a shop first.'
                ], 400);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'contact_person' => 'nullable|string|max:255',
            ]);

            $data = $request->only(['name', 'email', 'phone', 'address', 'contact_person']);
            $data['subshop_id'] = $subshopId;
            $data['is_active'] = $request->has('is_active');

            // Check if there's a soft deleted customer with the same email in the same shop
            $existingCustomer = null;
            if ($data['email']) {
                $existingCustomer = Customers::withTrashed()
                    ->where('email', $data['email'])
                    ->where('subshop_id', $data['subshop_id'])
                    ->first();
            }

            if ($existingCustomer && $existingCustomer->trashed()) {
                // Restore the soft deleted customer and update its data
                $existingCustomer->restore();
                $existingCustomer->update($data);

                $message = 'Customer restored and updated successfully.';
                $customer = $existingCustomer;
            } else {
                // Create new customer (email can be duplicate across different shops)
                $customer = Customers::create($data);
                $message = 'Customer created successfully.';
            }

            // Check if this is an AJAX request
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'customer' => $customer
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed.'
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while saving the customer.'
                ], 500);
            }
            return redirect()->back()->with('error', 'An error occurred while saving the customer.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $customer = Customers::withTrashed()->findOrFail($id);
        return response()->json($customer);
    }

    /**
     * API: Customer sales history list
     */
    public function apiSales(Customers $customer)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return response()->json(['error' => 'No subshop selected'], 400);
            }

            // Verify customer belongs to the current subshop
            if ($customer->subshop_id != $subshopId) {
                return response()->json(['error' => 'Customer not found in current subshop'], 404);
            }
            
            // Debug: Log the request and customer ID
            \Log::info('API Sales Request', [
                'customer_id' => $customer->id,
                'subshop_id' => $subshopId,
                'request_data' => request()->all()
            ]);

            // Sum of payments per order
            $paymentsSub = \App\Models\Transaction::selectRaw('order_id, SUM(total_amount) as paid_total')
                ->where('transaction_type', 'payment')
                ->where('customer_id', $customer->id)
                ->groupBy('order_id');

            // First, get the sales with payment info
            $sales = SalesOrders::select([
                    'sales_orders.id',
                    'sales_orders.order_no',
                    'sales_orders.created_at',
                    'sales_orders.grand_total',
                    'sales_orders.status',
                    'payments.paid_total'
                ])
                ->leftJoinSub($paymentsSub, 'payments', function($join) {
                    $join->on('sales_orders.id', '=', 'payments.order_id');
                })
                ->where('sales_orders.customer_id', $customer->id)
                ->where('sales_orders.subshop_id', $subshopId)
                ->orderBy('sales_orders.created_at', 'desc')
                ->paginate(10);

            // Get the order IDs to fetch items count
            $orderIds = $sales->pluck('id')->toArray();
            
            if (!empty($orderIds)) {
                // Get items count for each order from sales_orders_items table
                $itemsCount = DB::table('sales_orders_items')
                    ->select(
                        'sales_order_id',
                        DB::raw('COALESCE(SUM(quantity), 0) as total_items')
                    )
                    ->whereIn('sales_order_id', $orderIds)
                    ->groupBy('sales_order_id')
                    ->pluck('total_items', 'sales_order_id')
                    ->toArray();

                // Add items_count to each sale
                $sales->each(function($sale) use ($itemsCount) {
                    $sale->items_count = isset($itemsCount[$sale->id]) ? (int)$itemsCount[$sale->id] : 0;
                });
            } else {
                // If no orders, set items_count to 0 for all sales
                $sales->each(function($sale) {
                    $sale->items_count = 0;
                });
            }

            // Debug: Log the raw sales data
            \Log::info('Raw Sales Data', [
                'sales' => $sales->toArray(),
                'first_item' => $sales->first() ? $sales->first()->toArray() : null
            ]);

            $formattedSales = $sales->map(function($sale) {
                // Debug: Log each sale item
                \Log::info('Processing Sale', [
                    'sale_id' => $sale->id,
                    'sale_data' => $sale->toArray()
                ]);

                $grandTotal = (float)($sale->grand_total ?? 0);
                $paid = (float)($sale->paid_total ?? 0);
                $remain = $grandTotal - $paid;
                
                // Determine payment status based on amounts
                $status = 'pending';
                if ($paid <= 0) {
                    $status = 'pending';
                } elseif ($remain <= 0) {
                    $status = 'paid';
                } elseif ($paid > 0 && $remain > 0) {
                    $status = 'partial';
                }
                
                // Get items count for this sale
                $itemsCount = DB::table('sales_orders_items')
                    ->where('sales_order_id', $sale->id)
                    ->sum('quantity');
                
                return [
                    'id' => $sale->id,
                    'order_no' => $sale->order_no,
                    'date' => $sale->created_at ? $sale->created_at->toDateTimeString() : now()->toDateTimeString(),
                    'grand_total' => (float)$sale->grand_total,
                    'paid' => $paid,
                    'remaining' => $remain,
                    'status' => $status,
                    'items_count' => (int)$itemsCount,
                    'created_at' => $sale->created_at ? $sale->created_at->toDateTimeString() : null
                ];
            });

            $response = [
                'success' => true,
                'data' => $formattedSales,
                'pagination' => [
                    'total' => $sales->total(),
                    'per_page' => $sales->perPage(),
                    'current_page' => $sales->currentPage(),
                    'last_page' => $sales->lastPage(),
                    'from' => $sales->firstItem(),
                    'to' => $sales->lastItem(),
                ],
                'debug' => [
                    'customer_id' => $customer->id,
                    'subshop_id' => $subshopId,
                    'total_sales' => $sales->total(),
                    'first_sale' => $formattedSales->first()
                ]
            ];
            
            \Log::info('API Sales Response', $response);
            
            return response()->json($response, 200, [], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return response()->json(['sales' => [], 'error' => true, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * API: Customer statistics (quick stats, monthly spending, top categories, recent activity)
     */
    public function apiStats(Customers $customer)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return response()->json(['error' => 'No subshop selected'], 400);
            }

            // Verify customer belongs to the current subshop
            if ($customer->subshop_id != $subshopId) {
                return response()->json(['error' => 'Customer not found in current subshop'], 404);
            }

            // Payments per order subquery
            $paymentsSub = \App\Models\Transaction::selectRaw('order_id, SUM(total_amount) as paid_total')
                ->where('transaction_type', 'payment')
                ->groupBy('order_id');

            // Base orders query for this customer
            $base = SalesOrders::where('customer_id', $customer->id)
                ->where('subshop_id', $subshopId)  // Ensure we only get orders from current subshop
                ->leftJoinSub($paymentsSub, 'pays', function($join){
                    $join->on('pays.order_id', '=', 'sales_orders.id');
                })
                ->select('sales_orders.*', DB::raw('COALESCE(pays.paid_total,0) as paid_total'));

            $orders = (clone $base)->get();

            $totalOrders = $orders->count();
            $totalSpent = (float) $orders->sum('grand_total');
            $avgOrder = $totalOrders > 0 ? ($totalSpent / $totalOrders) : 0.0;
            $lastOrder = optional($orders->sortByDesc('created_at')->first())->created_at;

            // Monthly spending for last 12 months (DB-agnostic: aggregate in PHP)
            $since = now()->subMonths(11)->startOfMonth();
            $monthlyOrders = SalesOrders::where('customer_id', $customer->id)
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
                // $key is Y-m; build label from that (take month part)
                $labels[] = \Carbon\Carbon::createFromFormat('Y-m', $key)->format('M');
                $values[] = (float) $val;
            }

            // Top categories by amount from order items
            $catRows = SalesOrdersItems::query()
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_orders_items.sales_order_id')
                ->leftJoin('items', 'items.id', '=', 'sales_orders_items.item_id')
                ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
                ->where('sales_orders.customer_id', $customer->id)
                ->groupBy('categories.name')
                ->selectRaw("COALESCE(categories.name, 'Uncategorized') as category, SUM(sales_orders_items.line_total) as total")
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            $catTotal = (float) ($catRows->sum('total') ?: 1);
            $categories = $catRows->map(function($r) use ($catTotal){
                return [ 'label' => $r->category, 'value' => round(((float)$r->total / $catTotal) * 100, 2) ];
            });

            // Recent activity: last 5 orders
            $recent = SalesOrders::where('customer_id', $customer->id)
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
                'categories' => $categories,
                'recent' => $recent,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'quick' => [ 'total_orders' => 0, 'total_spent' => 0, 'avg_order' => 0, 'last_order' => null ],
                'monthly' => [ 'labels' => [], 'values' => [] ],
                'categories' => [],
                'recent' => [],
                'error' => true,
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // Not used as we're using modals
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subshop selected. Please select a shop first.'
                ], 400);
            }

            $customer = Customers::findOrFail($id);
            
            // Verify customer belongs to the current subshop
            if ($customer->subshop_id != $subshopId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found in current subshop.'
                ], 404);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'contact_person' => 'nullable|string|max:255',
            ]);

            $data = $request->only(['name', 'email', 'phone', 'address', 'contact_person']);
            $data['is_active'] = $request->has('is_active');

            // Check if the email is being changed and if it already exists in the same shop
            if ($request->has('email') && $request->email !== $customer->email) {
                $existingCustomer = Customers::where('email', $request->email)
                    ->where('subshop_id', $subshopId)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingCustomer) {
                    throw new \Exception('A customer with this email already exists in this shop.');
                }
            }

            $customer->update($data);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Customer updated successfully.',
                    'customer' => $customer
                ]);
            }

            return redirect()->back()->with('success', 'Customer updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed.'
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                if (request()->ajax() || request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No subshop selected. Please select a shop first.'
                    ], 400);
                }
                return redirect()->back()->with('error', 'No subshop selected. Please select a shop first.');
            }

            $customer = Customers::findOrFail($id);
            
            // Verify customer belongs to the current subshop
            if ($customer->subshop_id != $subshopId) {
                if (request()->ajax() || request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Customer not found in current subshop.'
                    ], 404);
                }
                return redirect()->back()->with('error', 'Customer not found in current subshop.');
            }
            
            $customer->delete();

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Customer deleted successfully.'
                ]);
            }

            return redirect()->back()->with('success', 'Customer deleted successfully.');
        } catch (\Exception $e) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while deleting the customer.'
                ], 500);
            }
            return redirect()->back()->with('error', 'An error occurred while deleting the customer.');
        }
    }

    /**
     * Show the subshops selection page
     */
    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('dashboard')]);
    }

    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('customers.index')])
                ->with('error', 'Please select a shop first');
        }

        // Stats subquery with optional date range on orders
        $statsSub = SalesOrders::selectRaw('customer_id, COUNT(*) as orders_count, COALESCE(SUM(grand_total),0) as total_spent')
            ->where('subshop_id', $subshopId);
        if ($request->filled('date_from')) {
            $statsSub->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $statsSub->whereDate('created_at', '<=', $request->input('date_to'));
        }
        $statsSub->groupBy('customer_id');

        // Base customers query with stats
        $base = Customers::where('customers.subshop_id', $subshopId)
            ->leftJoinSub($statsSub, 'stats', function($join){
                $join->on('stats.customer_id', '=', 'customers.id');
            })
            ->select('customers.*', DB::raw('COALESCE(stats.orders_count,0) as orders_count'), DB::raw('COALESCE(stats.total_spent,0) as total_spent'));

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $base->where(function($q) use ($search){
                $q->where('customers.name', 'like', "%{$search}%")
                  ->orWhere('customers.email', 'like', "%{$search}%")
                  ->orWhere('customers.phone', 'like', "%{$search}%")
                  ->orWhere('customers.contact_person', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status') && in_array($request->status, ['active','inactive'])) {
            $base->where('customers.is_active', $request->status === 'active' ? 1 : 0);
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

        // Sorting for export
        $sort = $request->input('sort');
        if ($sort === 'name_asc') {
            $base->orderBy('customers.name', 'asc');
        } elseif ($sort === 'name_desc') {
            $base->orderBy('customers.name', 'desc');
        } elseif ($sort === 'date_asc') {
            $base->orderBy('customers.created_at', 'asc');
        } elseif ($sort === 'orders_desc') {
            $base->orderByRaw('COALESCE(stats.orders_count,0) desc');
        } elseif ($sort === 'orders_asc') {
            $base->orderByRaw('COALESCE(stats.orders_count,0) asc');
        } elseif ($sort === 'spent_desc') {
            $base->orderByRaw('COALESCE(stats.total_spent,0) desc');
        } elseif ($sort === 'spent_asc') {
            $base->orderByRaw('COALESCE(stats.total_spent,0) asc');
        } elseif ($sort === 'status') {
            $base->orderBy('customers.is_active', 'desc');
        } else {
            $base->orderBy('customers.created_at', 'desc');
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
                'Content-Disposition' => 'attachment; filename="customers_'.now()->format('Y-m-d_H-i-s').'.csv"',
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
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\GenericArrayExport($exportRows->toArray(), 'Customers'), 'customers_'.now()->format('Y-m-d_H-i-s').'.xlsx');
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
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.customers_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('customers_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    /**
     * Download a sample CSV for customers import
     */
    public function downloadSample()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=customers_import_sample.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            // BOM for Excel
            fputs($handle, "\xEF\xBB\xBF");
            // Headers (exact column keys expected by importer)
            fputcsv($handle, [
                'name',
                'contact_person',
                'email',
                'phone',
                'address',
                'is_active' // 1 or 0
            ]);

            // Sample rows (avoid commas in address for better CSV compatibility)
            fputcsv($handle, ['Customer 1', 'Jane', 'john@example.com', '0712345678', 'Dar es Salaam TZ', '1']);
            fputcsv($handle, ['customer 2', '', '', '', '', '']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import customers from CSV
     */
    public function import(Request $request)
    {
        if (!$request->hasFile('import_file')) {
            return response()->json([
                'success' => false,
                'message' => 'No file was uploaded.'
            ], 400);
        }

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

            $rowIndex = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $rowIndex++;
                // Strip BOM on first column for every row
                if (isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
                }

                // Skip first row if flagged as headers
                if ($rowIndex === 1 && $hasHeaders) {
                    continue;
                }

                // Also skip any row that looks like headers
                $first = isset($row[0]) ? strtolower(trim($row[0])) : '';
                if (in_array($first, ['name', 'name*'])) {
                    continue;
                }

                // Normalize to at least 6 columns
                for ($i = 0; $i < 6; $i++) {
                    if (!isset($row[$i])) { $row[$i] = null; }
                }

                [$name, $contactPerson, $email, $phone, $address, $isActive] = $row;

                if (!$name || trim($name) === '') {
                    $skipped++;
                    $errors[] = "Row {$rowIndex}: Missing required 'name'";
                    continue;
                }

                try {
                    // Default active when blank
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

                    // Upsert by email within subshop if email provided
                    if (!empty($data['email'])) {
                        $existing = Customers::withTrashed()
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

                    Customers::create($data);
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

}
