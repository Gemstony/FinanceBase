<?php

namespace App\Http\Controllers;

use App\Models\Customers;
use App\Models\DepositAccount;
use App\Models\Loans;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use App\Models\SubShop;
use App\Models\SalesOrders;
use App\Models\SalesOrdersItems;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    public function __construct(
        private readonly PortfolioRiskCalculator $portfolioRisk,
    ) {
    }
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
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('customers.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $customers = Customers::whereIn('customers.subshop_id', $shopSubshopIds)
            ->select('customers.*');

        // Search by name, email, phone, contact person
        if ($request->filled('search')) {
            $search = $request->search;
            $customers->where(function($q) use ($search){
                $q->where('customers.name', 'like', "%{$search}%")
                  ->orWhere('customers.email', 'like', "%{$search}%")
                  ->orWhere('customers.phone', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status') && in_array($request->status, ['active','inactive'])) {
            $customers->where('customers.is_active', $request->status === 'active' ? 1 : 0);
        }

        // Date range filter (customer registration date)
        if ($request->filled('date_from')) {
            $customers->whereDate('customers.created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $customers->whereDate('customers.created_at', '<=', $request->input('date_to'));
        }

        // Sorting
        $sort = $request->input('sort');
        if ($sort === 'name_asc') {
            $customers->orderBy('customers.name', 'asc');
        } elseif ($sort === 'name_desc') {
            $customers->orderBy('customers.name', 'desc');
        } elseif ($sort === 'date_asc') {
            $customers->orderBy('customers.created_at', 'asc');
        } elseif ($sort === 'status') {
            $customers->orderBy('customers.is_active', 'desc');
        } else {
            // default by date desc
            $customers->orderBy('customers.created_at', 'desc');
        }

        $customers = $customers->paginate(10)->appends($request->query());

        // Summary stats for cards
        $summary = [
            'total_customers' => Customers::whereIn('subshop_id', $shopSubshopIds)->count(),
            'active_customers' => Customers::whereIn('subshop_id', $shopSubshopIds)->where('is_active', true)->count(),
            'inactive_customers' => Customers::whereIn('subshop_id', $shopSubshopIds)->where('is_active', false)->count(),
            'total_loans' => Loans::whereIn('subshop_id', $shopSubshopIds)->count(),
            'total_outstanding' => $this->portfolioRisk->calculateTotalPortfolioOutstandingForSubshops($shopSubshopIds),
        ];

        return view('customers.index', compact('customers', 'subshop', 'summary'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('customers.create')]);
        }

        $subshop = SubShop::findOrFail($subshopId);
        if ($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('customers.create')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        return view('customers.create', compact('subshop'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No subshop selected. Please select a shop first.'
                    ], 400);
                }

                return redirect()->route('subshops.choose', ['intended' => route('customers.create')]);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'altenative_phone'  => 'nullable|string|max:20',
                'gender'  => 'required|string|max:20',
                'birth_date'  => 'required|date',
                'region' => 'required|string|max:255',
                'district' => 'required|string|max:255',
                'ward' => 'required|string|max:255',
                'street' => 'required|string|max:255',
                'house_no' => 'required|string|max:20',
                'work'  => 'nullable|string|max:20',
                'work_address'  => 'nullable|string|max:20',
                'id_type'  => 'required|string|max:20',
                'id_number'  => 'required|string|max:50',
                'category'  => 'required|string|max:20',
            
            ]);

            $data = $request->only([

                'name',
                'email',
                'phone',
                'altenative_phone',
                'gender',
                'birth_date',
                'region',
                'district',
                'ward',
                'street',
                'house_no',
                'work',
                'work_address',
                'id_type',
                'id_number',
                'category',
            
            
            ]);
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

            return redirect()->route('customers.show', $customer->id)->with('success', $message);
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
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('customers.show', $id)]);
        }

        $customer = Customers::withTrashed()->findOrFail($id);

        if ((int) $customer->subshop_id !== (int) $subshopId) {
            abort(404);
        }

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json($customer);
        }

        // Get all loans for this customer (not just active)
        $allLoans = Loans::where('customer_id', $customer->id)
            ->with(['loanProduct', 'latestDisbursement'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate loan statistics
        $totalLoans = $allLoans->count();
        $activeLoans = $allLoans->whereIn('status', ['disbursed', 'partially_paid', 'defaulted'])->where('is_active', true);
        $closedLoans = $allLoans->filter(function($loan) {
            return in_array($loan->status, ['paid_off', 'closed']) || 
                   ($loan->status === 'disbursed' && $loan->installments_paid >= $loan->installments && $loan->installments > 0);
        });
        $writtenOffLoans = $allLoans->where('is_written_off', true);
        
        // Calculate financial totals using actual outstanding from installments
        $totalDisbursed = 0;
        $totalRepaid = 0;
        $totalOutstanding = 0;
        $overdueLoansCount = 0;
        $overdueAmount = 0;
        $maxDaysPastDue = 0;

        foreach ($allLoans as $loan) {
            $totalDisbursed += (float) $loan->principal_amount;
            
            // Calculate actual outstanding from installments
            $loan->calculated_outstanding = $this->portfolioRisk->calculateLoanOutstanding($loan);
            
            // Calculate repaid (disbursed - outstanding)
            $repaid = (float) $loan->principal_amount - $loan->calculated_outstanding;
            $totalRepaid += $repaid;
            $totalOutstanding += $loan->calculated_outstanding;
            
            // Check for overdue installments to get days past due
            $overdueInstallment = $loan->installments()
                ->where('is_active', true)
                ->where('status', 'overdue')
                ->orderBy('due_date', 'asc')
                ->first();
            
            if ($overdueInstallment) {
                $daysPastDue = now()->diffInDays($overdueInstallment->due_date, false);
                $loan->days_past_due = abs($daysPastDue);
                
                if ($loan->days_past_due > 0) {
                    $overdueLoansCount++;
                    $overdueAmount += $loan->calculated_outstanding;
                    $maxDaysPastDue = max($maxDaysPastDue, $loan->days_past_due);
                }
            } else {
                $loan->days_past_due = 0;
            }
        }

        // Loan status summary
        $loanStatusSummary = [
            'disbursed' => $allLoans->where('status', 'disbursed')->count(),
            'partially_paid' => $allLoans->where('status', 'partially_paid')->count(),
            'defaulted' => $allLoans->where('status', 'defaulted')->count(),
            'paid' => $allLoans->where('status', 'paid')->count(),
            'pending' => $allLoans->where('status', 'pending')->count(),
            'rejected' => $allLoans->where('status', 'rejected')->count(),
            'written_off' => $writtenOffLoans->count(),
        ];

        $stats = [
            'loans_count' => $totalLoans,
            'active_loans_count' => $activeLoans->count(),
            'closed_loans_count' => $closedLoans->count(),
            'written_off_count' => $writtenOffLoans->count(),
            'total_principal' => $totalDisbursed,
            'total_repaid' => $totalRepaid,
            'outstanding_balance' => $totalOutstanding,
            'overdue_loans_count' => $overdueLoansCount,
            'overdue_amount' => $overdueAmount,
            'max_days_past_due' => $maxDaysPastDue,
            'loan_status_summary' => $loanStatusSummary,
            'deposit_accounts_count' => (int) DepositAccount::query()
                ->where('subshop_id', $subshopId)
                ->where('customer_id', $customer->id)
                ->count(),
        ];

        return view('customers.show', compact('customer', 'stats', 'allLoans'));
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
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('customers.edit', $id)]);
        }

        $customer = Customers::findOrFail($id);
        if ((int) $customer->subshop_id !== (int) $subshopId) {
            abort(404);
        }

        return view('customers.edit', compact('customer'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $subshopId = session('subshop_id');
            if (!$subshopId) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No subshop selected. Please select a shop first.'
                    ], 400);
                }

                return redirect()->route('subshops.choose', ['intended' => route('customers.edit', $id)]);
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
                'altenative_phone'  => 'nullable|string|max:20',
                'gender'  => 'required|string|max:20',
                'birth_date'  => 'required|date',
                'region' => 'required|string|max:255',
                'district' => 'required|string|max:255',
                'ward' => 'required|string|max:255',
                'street' => 'required|string|max:255',
                'house_no' => 'required|string|max:20',
                'work'  => 'nullable|string|max:20',
                'work_address'  => 'nullable|string|max:20',
                'id_type'  => 'required|string|max:20',
                'id_number'  => 'required|string|max:50',
                'category'  => 'required|string|max:20',
            ]);

            $data = $request->only([
                'name',
                'email',
                'phone',
                'altenative_phone',
                'gender',
                'birth_date',
                'region',
                'district',
                'ward',
                'street',
                'house_no',
                'work',
                'work_address',
                'id_type',
                'id_number',
                'category',
                        
            ]);
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

            return redirect()->route('customers.show', $customer->id)->with('success', 'Customer updated successfully.');
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
                return redirect()->route('subshops.choose', ['intended' => route('customers.index')]);
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

            return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
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
                'generatedBy' => optional(auth()->guard()->user())->name ?? 'System',
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
                'email',
                'phone',
                'altenative_phone',
                'gender',
                'birth_date',
                'region',
                'district',
                'ward',
                'street',
                'house_no',
                'work',
                'work_address',
                'id_type',
                'id_number',
                'category',
                'is_active' // 1 or 0
            ]);

            // Sample rows (avoid commas in address for better CSV compatibility)
            fputcsv($handle, ['Customer 1', 'customer1@gmail.com', '0600000001','0700000002', 'M', '2002-01-25', 'Dar es salaam', 'Ubungo', 'Ubungo Maziwa', 'makuburi', '57', 'Teacher', 'Ubungo, Makuburi primary school', 'NIDA', '20030125-00000-00000-00', 'Borrower', '1' ]);
            fputcsv($handle, ['customer 2', '', '0600000001','', 'F', '2002-01-25', 'Dar es salaam', 'Ubungo', 'Ubungo Maziwa', 'makuburi', '57', '', '', 'NIDA', '20030125-00000-00000-00', 'Guarantor', '1' ]);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import customers from CSV with transaction support
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Import failed: Please upload a valid CSV file. Maximum file size is 2MB.'
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        $subshopId = session('subshop_id');
        if (!$subshopId) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import failed: No shop selected. Please select a shop first before importing customers.'
                ], 400);
            }
            return redirect()->route('subshops.choose', ['intended' => route('customers.create')]);
        }

        $file = $request->file('csv_file');
        $imported = 0;
        $errors = [];

        try {
            if (($handle = fopen($file->getRealPath(), 'r')) === false) {
                throw new \Exception('Import failed: Unable to open the uploaded CSV file. Please ensure the file is not corrupted and try again.');
            }

            $rowIndex = 0;
            $headers = [];
            $rows = [];

            // Read all rows first
            while (($row = fgetcsv($handle)) !== false) {
                $rowIndex++;
                
                // Strip BOM on first column for every row
                if (isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
                }

                // First row is headers
                if ($rowIndex === 1) {
                    $headers = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $row);
                    continue;
                }

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $rows[] = ['index' => $rowIndex, 'data' => $row];
            }
            fclose($handle);

            if (empty($rows)) {
                $errorMsg = 'Import failed: No data rows found in the CSV file. Please ensure your CSV file contains a header row and at least one data row.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMsg
                    ], 400);
                }
                return redirect()->back()->with('error', $errorMsg);
            }

            // Validate headers
            $requiredHeaders = ['name', 'gender', 'birth_date', 'phone', 'region', 'district', 'ward', 'street', 'house_no', 'id_type', 'id_number', 'category'];
            $missingHeaders = array_diff($requiredHeaders, $headers);
            
            if (!empty($missingHeaders)) {
                $errorMsg = 'Import failed: Missing required columns in CSV file: ' . implode(', ', $missingHeaders) . '. Please download the template and ensure all required columns are present.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMsg
                    ], 400);
                }
                return redirect()->back()->with('error', $errorMsg);
            }

            // Process rows with transaction
            DB::beginTransaction();

            foreach ($rows as $rowInfo) {
                $rowIndex = $rowInfo['index'];
                $row = $rowInfo['data'];

                // Map row data to headers
                $rowData = [];
                foreach ($headers as $i => $header) {
                    $rowData[$header] = isset($row[$i]) ? trim($row[$i]) : null;
                }

                // Validate required fields
                $rowErrors = [];
                
                if (empty($rowData['name'])) {
                    $rowErrors[] = "name is required and cannot be empty";
                }
                if (empty($rowData['gender'])) {
                    $rowErrors[] = "gender is required and cannot be empty";
                } elseif (!in_array(strtoupper($rowData['gender']), ['M', 'F'])) {
                    $rowErrors[] = "gender must be M (Male) or F (Female), found '{$rowData['gender']}'";
                }
                if (empty($rowData['birth_date'])) {
                    $rowErrors[] = "birth_date is required and cannot be empty";
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rowData['birth_date'])) {
                    $rowErrors[] = "birth_date must be in YYYY-MM-DD format, found '{$rowData['birth_date']}'";
                }
                if (empty($rowData['phone'])) {
                    $rowErrors[] = "phone is required and cannot be empty";
                }
                if (empty($rowData['region'])) {
                    $rowErrors[] = "region is required and cannot be empty";
                }
                if (empty($rowData['district'])) {
                    $rowErrors[] = "district is required and cannot be empty";
                }
                if (empty($rowData['ward'])) {
                    $rowErrors[] = "ward is required and cannot be empty";
                }
                if (empty($rowData['street'])) {
                    $rowErrors[] = "street is required and cannot be empty";
                }
                if (empty($rowData['house_no'])) {
                    $rowErrors[] = "house_no is required and cannot be empty";
                }
                if (empty($rowData['id_type'])) {
                    $rowErrors[] = "id_type is required and cannot be empty";
                } elseif (!in_array($rowData['id_type'], ['NIDA', 'Driving License', 'Voter Id', 'Other'])) {
                    $rowErrors[] = "id_type must be NIDA, Driving License, Voter Id, or Other, found '{$rowData['id_type']}'";
                }
                if (empty($rowData['id_number'])) {
                    $rowErrors[] = "id_number is required and cannot be empty";
                }
                if (empty($rowData['category'])) {
                    $rowErrors[] = "category is required and cannot be empty";
                } elseif (!in_array(strtolower($rowData['category']), ['borrower', 'guarantor'])) {
                    $rowErrors[] = "category must be borrower or guarantor, found '{$rowData['category']}'";
                }

                if (!empty($rowErrors)) {
                    $errors[] = "Row {$rowIndex}: " . implode('; ', $rowErrors);
                    continue;
                }

                try {
                    // Prepare data for insertion
                    $data = [
                        'subshop_id' => $subshopId,
                        'name' => $rowData['name'],
                        'email' => !empty($rowData['email']) ? $rowData['email'] : null,
                        'phone' => $rowData['phone'],
                        'altenative_phone' => !empty($rowData['altenative_phone']) ? $rowData['altenative_phone'] : null,
                        'gender' => strtoupper($rowData['gender']),
                        'birth_date' => $rowData['birth_date'],
                        'region' => $rowData['region'],
                        'district' => $rowData['district'],
                        'ward' => $rowData['ward'],
                        'street' => $rowData['street'],
                        'house_no' => $rowData['house_no'],
                        'work' => !empty($rowData['work']) ? $rowData['work'] : null,
                        'work_address' => !empty($rowData['work_address']) ? $rowData['work_address'] : null,
                        'id_type' => $rowData['id_type'],
                        'id_number' => $rowData['id_number'],
                        'category' => strtolower($rowData['category']),
                        'is_active' => isset($rowData['is_active']) ? (int)$rowData['is_active'] : 1,
                    ];

                    // Check for duplicate email in same subshop
                    if (!empty($data['email'])) {
                        $existing = Customers::where('subshop_id', $subshopId)
                            ->where('email', $data['email'])
                            ->first();
                        
                        if ($existing) {
                            $errors[] = "Row {$rowIndex}: A customer with email '{$data['email']}' already exists in this shop. Please use a different email address.";
                            continue;
                        }
                    }

                    Customers::create($data);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowIndex}: Database error - " . $e->getMessage();
                }
            }

            // If there are any errors, rollback the transaction
            if (!empty($errors)) {
                DB::rollBack();
                
                $errorCount = count($errors);
                $errorMessage = "Import failed due to {$errorCount} error(s). All changes have been rolled back. Please fix the following issues and try again:";
                
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'errors' => $errors,
                        'imported' => 0,
                    ], 400);
                }
                
                return redirect()->back()
                    ->with('error', $errorMessage)
                    ->with('import_errors', $errors);
            }

            // Commit the transaction if all rows are successful
            DB::commit();

            $message = "Successfully imported {$imported} customer(s). All data has been saved to the database.";
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'imported' => $imported,
                ]);
            }

            return redirect()->route('customers.index')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $errorMessage = 'Import failed: ' . $e->getMessage();
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 500);
            }
            
            return redirect()->back()->with('error', $errorMessage);
        }
    }

    /**
     * Bulk import customers from CSV (redirects to create page)
     */
    public function bulkImport(Request $request)
    {
        return $this->import($request);
    }

    /**
     * Download CSV template for customer import
     */
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=customer_import_template.csv',
        ];

$callback = function() {
    // Clear any existing output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    $handle = fopen('php://output', 'w');

    fputcsv($handle, [
        'name','email',
        'phone',
        'altenative_phone',
        'gender',
        'birth_date',
        'region',
        'district',
        'ward',
        'street',
        'house_no',
        'work',
        'work_address',
        'id_type',
        'id_number',
        'category',
        'is_active'
    ]);

    fputcsv($handle, [
        'John Doe',
        'john.doe@example.com',
        '0712345678',
        '0712345679',
        'M',
        '1990-01-15',
        'Dar es Salaam',
        'Kinondoni',
        'Masaki',
        'Slipway Road',
        '123',
        'Engineer',
        'Slipway,
         Dar es Salaam',
        'NIDA',
        '19900115-12345-12345-12',
        'borrower',
        '1'
    ]);

    fputcsv($handle, [
        'Jane Smith',
        'jane.smith@example.com',
        '0712345680',
        '',
        'F',
        '1985-05-20',
        'Dar es Salaam',
        'Ilala',
        'Upanga',
        'Samora Avenue',
        '456',
        'Teacher',
        'Upanga Primary School',
        'Driving License',
        'DL123456789',
        'guarantor',
        '1'
    ]);

    fclose($handle);
};

        return response()->stream($callback, 200, $headers);
    }

}
