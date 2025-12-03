<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SubShop;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\GenericArrayExport;

class ProfitAndLossReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) { abort(403); }

        // Shop context and accessible subshops (same pattern as Sales/Purchases)
        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) { abort(403, 'No shop found for user'); }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        $subshopId = $request->integer('subshop_id');
        if ($subshopId) {
            if (!in_array($subshopId, $accessibleSubshopIds, true)) {
                abort(403, 'You do not have access to this subshop');
            }
            $subshopFilter = [$subshopId];
        } else {
            $subshopFilter = $accessibleSubshopIds ?: [-1];
        }

        // Date range (default last 30 days)
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        // Basis: accrual (default) or cash (future enhancement)
        $basis = $request->query('basis', 'accrual');
        // Comparison: none | prev_period | prev_year
        $compare = $request->query('compare', 'none');

        // Revenue (Gross Sales)
        if ($basis === 'cash') {
            // Use transactions (customer payments) as revenue on cash basis
            $paymentsQ = DB::table('transactions as t')
                ->leftJoin('sales_orders', 't.order_id', '=', 'sales_orders.id')
                ->leftJoin('customers', 't.customer_id', '=', 'customers.id')
                ->where(function($q) use ($subshopFilter) {
                    $q->whereIn('sales_orders.subshop_id', $subshopFilter)
                      ->orWhereIn('customers.subshop_id', $subshopFilter);
                })
                ->where('t.transaction_type', 'payment')
                ->whereDate('t.created_at', '>=', $dateFrom->toDateString())
                ->whereDate('t.created_at', '<=', $dateTo->toDateString());
            $grossSales = (float) $paymentsQ->sum('t.total_amount');
            $salesReturns = 0.0; // cash basis revenue already reflects refunds if recorded; don't double count
        } else {
            $ordersBase = DB::table('sales_orders as so')
                ->whereIn('so.subshop_id', $subshopFilter)
                ->whereDate('so.created_at', '>=', $dateFrom->toDateString())
                ->whereDate('so.created_at', '<=', $dateTo->toDateString());
            $grossSales = (float) ((clone $ordersBase)->sum('so.grand_total'));
            $salesReturns = (float) DB::table('sales_returns as sr')
                ->whereIn('sr.subshop_id', $subshopFilter)
                ->whereDate('sr.created_at', '>=', $dateFrom->toDateString())
                ->whereDate('sr.created_at', '<=', $dateTo->toDateString())
                ->sum(DB::raw('COALESCE(sr.line_total, 0)'));
        }

        // COGS from sales lines (more accurate than purchase proxy)
        $itemsAgg = DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereDate('so.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('so.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs_sold')
            ->first();
        $cogsSold = (float) ($itemsAgg->cogs_sold ?? 0);

        // COGS returned (reduce COGS)
        $cogsReturnedAgg = DB::table('sales_returns as sr')
            ->leftJoin('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('sr.subshop_id', $subshopFilter)
            ->whereDate('sr.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('sr.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(COALESCE(sr.quantity_returned,0) * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs_returned')
            ->first();
        $cogsReturned = (float) ($cogsReturnedAgg->cogs_returned ?? 0);

        $netSales = max(0.0, $grossSales - $salesReturns);
        $netCogs = max(0.0, $cogsSold - $cogsReturned);
        $grossProfit = max(0.0, $netSales - $netCogs);
        $grossMarginPct = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0.0;

        // Operating Expenses (if Expenses table exists, align with DashboardController logic)
        try {
            $expQ = \App\Models\Expenses::whereIn('subshop_id', $subshopFilter);
            if ($basis === 'cash') {
                // Cash basis: only approved and based on expense_date
                $expQ->where('status', 'approved')
                    ->whereBetween(DB::raw('DATE(expense_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()]);
            } else {
                // Accrual approximation: include approved/pending by expense_date or creation fallback
                $expQ->whereIn('status', ['approved','pending'])
                    ->where(function($q) use ($dateFrom, $dateTo) {
                        $q->whereBetween(DB::raw('DATE(expense_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()])
                          ->orWhereBetween(DB::raw('DATE(created_at)'), [$dateFrom->toDateString(), $dateTo->toDateString()]);
                    });
            }
            $expensesTotal = (float) $expQ->sum('amount');
        } catch (\Throwable $e) { $expensesTotal = 0.0; }

        $netProfit = $grossProfit - $expensesTotal;

        $kpi = [
            'gross_sales' => $grossSales,
            'sales_returns' => $salesReturns,
            'net_sales' => $netSales,
            'cogs' => $netCogs,
            'gross_profit' => $grossProfit,
            'gross_margin_pct' => $grossMarginPct,
            'operating_expenses' => $expensesTotal,
            'net_profit' => $netProfit,
        ];
        // Compute comparison metrics if requested
        $compareData = null;
        if ($compare !== 'none') {
            // Determine previous range
            $start = Carbon::parse($dateFrom)->startOfDay();
            $end = Carbon::parse($dateTo)->endOfDay();
            if ($compare === 'prev_period') {
                $days = $start->diffInDays($end) + 1;
                $cEnd = (clone $start)->subDay();
                $cStart = (clone $cEnd)->subDays($days - 1)->startOfDay();
            } else { // prev_year
                $cStart = (clone $start)->subYear();
                $cEnd = (clone $end)->subYear();
            }

            // Reuse same aggregation for comparison window per basis
            if ($basis === 'cash') {
                $paymentsQC = DB::table('transactions as t')
                    ->leftJoin('sales_orders', 't.order_id', '=', 'sales_orders.id')
                    ->leftJoin('customers', 't.customer_id', '=', 'customers.id')
                    ->where(function($q) use ($subshopFilter) {
                        $q->whereIn('sales_orders.subshop_id', $subshopFilter)
                          ->orWhereIn('customers.subshop_id', $subshopFilter);
                    })
                    ->where('t.transaction_type', 'payment')
                    ->whereBetween('t.created_at', [$cStart, $cEnd]);
                $grossSalesC = (float) $paymentsQC->sum('t.total_amount');
                $salesReturnsC = 0.0;
            } else {
                $ordersBaseC = DB::table('sales_orders as so')
                    ->whereIn('so.subshop_id', $subshopFilter)
                    ->whereBetween('so.created_at', [$cStart, $cEnd]);
                $grossSalesC = (float) ((clone $ordersBaseC)->sum('so.grand_total'));
                $salesReturnsC = (float) DB::table('sales_returns as sr')
                    ->whereIn('sr.subshop_id', $subshopFilter)
                    ->whereBetween('sr.created_at', [$cStart, $cEnd])
                    ->sum(DB::raw('COALESCE(sr.line_total, 0)'));
            }
            $cogsSoldC = (float) DB::table('sales_orders_items as soi')
                ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
                ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
                ->leftJoin('items', 'items.id', '=', 'soi.item_id')
                ->whereIn('so.subshop_id', $subshopFilter)
                ->whereBetween('so.created_at', [$cStart, $cEnd])
                ->sum(DB::raw('soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))'));
            $cogsReturnedC = (float) DB::table('sales_returns as sr')
                ->leftJoin('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
                ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
                ->leftJoin('items', 'items.id', '=', 'soi.item_id')
                ->whereIn('sr.subshop_id', $subshopFilter)
                ->whereBetween('sr.created_at', [$cStart, $cEnd])
                ->sum(DB::raw('COALESCE(sr.quantity_returned,0) * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))'));
            $expensesC = 0.0;
            try {
                $expQC = \App\Models\Expenses::whereIn('subshop_id', $subshopFilter);
                if ($basis === 'cash') {
                    $expQC->where('status', 'approved')
                        ->whereBetween(DB::raw('DATE(expense_date)'), [$cStart->toDateString(), $cEnd->toDateString()]);
                } else {
                    $expQC->whereIn('status', ['approved','pending'])
                        ->where(function($q) use ($cStart, $cEnd) {
                            $q->whereBetween(DB::raw('DATE(expense_date)'), [$cStart->toDateString(), $cEnd->toDateString()])
                              ->orWhereBetween(DB::raw('DATE(created_at)'), [$cStart->toDateString(), $cEnd->toDateString()]);
                        });
                }
                $expensesC = (float) $expQC->sum('amount');
            } catch (\Throwable $e) { $expensesC = 0.0; }

            $netSalesC = max(0.0, $grossSalesC - $salesReturnsC);
            $netCogsC = max(0.0, $cogsSoldC - $cogsReturnedC);
            $grossProfitC = max(0.0, $netSalesC - $netCogsC);
            $grossMarginPctC = $netSalesC > 0 ? round(($grossProfitC / $netSalesC) * 100, 2) : 0.0;
            $netProfitC = $grossProfitC - $expensesC;

            $compareData = [
                'window' => $compare,
                'range' => [$cStart->toDateString(), $cEnd->toDateString()],
                'values' => [
                    'net_sales' => ['current'=>$netSales, 'prev'=>$netSalesC],
                    'gross_profit' => ['current'=>$grossProfit, 'prev'=>$grossProfitC],
                    'net_profit' => ['current'=>$netProfit, 'prev'=>$netProfitC],
                    'gross_margin_pct' => ['current'=>$grossMarginPct, 'prev'=>$grossMarginPctC],
                ],
            ];
        }

        // Subshop breakdown using aggregated subqueries
        $ordersAgg = DB::table('sales_orders as so')
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->selectRaw('so.subshop_id as sid, SUM(so.grand_total) as gross_sales')
            ->groupBy('sid');
        $returnsAgg = DB::table('sales_returns as sr')
            ->whereBetween('sr.created_at', [$dateFrom, $dateTo])
            ->selectRaw('sr.subshop_id as sid, SUM(COALESCE(sr.line_total,0)) as sales_returns')
            ->groupBy('sid');
        $cogsAgg = DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->selectRaw('so.subshop_id as sid, SUM(soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price,0))) as cogs')
            ->groupBy('sid');
        $cogsRetAgg = DB::table('sales_returns as sr')
            ->leftJoin('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereBetween('sr.created_at', [$dateFrom, $dateTo])
            ->selectRaw('sr.subshop_id as sid, SUM(COALESCE(sr.quantity_returned,0) * COALESCE(ib.cost_price, COALESCE(items.cost_price,0))) as cogs_returns')
            ->groupBy('sid');

        $breakdown = DB::table('sub_shops as ss')
            ->leftJoinSub($ordersAgg, 'o', function($j){ $j->on('o.sid','=','ss.id'); })
            ->leftJoinSub($returnsAgg, 'r', function($j){ $j->on('r.sid','=','ss.id'); })
            ->leftJoinSub($cogsAgg, 'c', function($j){ $j->on('c.sid','=','ss.id'); })
            ->leftJoinSub($cogsRetAgg, 'cr', function($j){ $j->on('cr.sid','=','ss.id'); })
            ->when(!empty($subshopFilter), function($q) use ($subshopFilter){ $q->whereIn('ss.id', $subshopFilter); })
            ->orderBy('ss.name')
            ->get([
                'ss.name',
                DB::raw('COALESCE(o.gross_sales,0) as gross_sales'),
                DB::raw('COALESCE(r.sales_returns,0) as sales_returns'),
                DB::raw('COALESCE(c.cogs,0) as cogs'),
                DB::raw('COALESCE(cr.cogs_returns,0) as cogs_returns'),
            ])
            ->map(function($r){
                $netSales = max(0.0, ($r->gross_sales - $r->sales_returns));
                $netCogs = max(0.0, ($r->cogs - $r->cogs_returns));
                $grossProfit = max(0.0, ($netSales - $netCogs));
                return [
                    'subshop' => $r->name,
                    'gross_sales' => (float)$r->gross_sales,
                    'sales_returns' => (float)$r->sales_returns,
                    'net_sales' => (float)$netSales,
                    'cogs' => (float)$netCogs,
                    'gross_profit' => (float)$grossProfit,
                ];
            });

        return view('reports.profit-and-loss-report', [
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'basis' => $basis,
            'compare' => $compare,
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'kpi' => $kpi,
            'kpiCompare' => $compareData,
            'subshopBreakdown' => $breakdown,
        ]);
    }

    public function export(Request $request, $format = 'xlsx')
    {
        $user = Auth::user();
        if (!$user) { abort(403); }

        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) { abort(403, 'No shop found for user'); }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        $subshopId = $request->integer('subshop_id');
        if ($subshopId) {
            if (!in_array($subshopId, $accessibleSubshopIds, true)) abort(403);
            $subshopFilter = [$subshopId];
            $subshopName = optional($allSubshops->firstWhere('id', $subshopId))->name;
        } else {
            $subshopFilter = $accessibleSubshopIds ?: [-1];
            $subshopName = null;
        }

        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();
        $basis = $request->query('basis', 'accrual');
        $scope = $request->query('scope', 'summary'); // summary | daily

        // Base metrics (same as index) - revenue per basis
        if ($basis === 'cash') {
            $paymentsQ = DB::table('transactions as t')
                ->leftJoin('sales_orders', 't.order_id', '=', 'sales_orders.id')
                ->leftJoin('customers', 't.customer_id', '=', 'customers.id')
                ->where(function($q) use ($subshopFilter) {
                    $q->whereIn('sales_orders.subshop_id', $subshopFilter)
                      ->orWhereIn('customers.subshop_id', $subshopFilter);
                })
                ->where('t.transaction_type', 'payment')
                ->whereBetween('t.created_at', [$dateFrom, $dateTo]);
            $grossSales = (float) $paymentsQ->sum('t.total_amount');
            $salesReturns = 0.0;
        } else {
            $ordersBase = DB::table('sales_orders as so')
                ->whereIn('so.subshop_id', $subshopFilter)
                ->whereDate('so.created_at', '>=', $dateFrom->toDateString())
                ->whereDate('so.created_at', '<=', $dateTo->toDateString());
            $grossSales = (float) ((clone $ordersBase)->sum('so.grand_total'));
            $salesReturns = (float) DB::table('sales_returns as sr')
                ->whereIn('sr.subshop_id', $subshopFilter)
                ->whereDate('sr.created_at', '>=', $dateFrom->toDateString())
                ->whereDate('sr.created_at', '<=', $dateTo->toDateString())
                ->sum(DB::raw('COALESCE(sr.line_total, 0)'));
        }

        $itemsAgg = DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereDate('so.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('so.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs_sold')
            ->first();
        $cogsSold = (float) ($itemsAgg->cogs_sold ?? 0);

        $cogsReturnedAgg = DB::table('sales_returns as sr')
            ->leftJoin('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('sr.subshop_id', $subshopFilter)
            ->whereDate('sr.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('sr.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(COALESCE(sr.quantity_returned,0) * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs_returned')
            ->first();
        $cogsReturned = (float) ($cogsReturnedAgg->cogs_returned ?? 0);

        try {
            $expQ = \App\Models\Expenses::whereIn('subshop_id', $subshopFilter);
            if ($basis === 'cash') {
                $expQ->where('status', 'approved')
                    ->whereBetween(DB::raw('DATE(expense_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()]);
            } else {
                $expQ->whereIn('status', ['approved','pending'])
                    ->where(function($q) use ($dateFrom, $dateTo) {
                        $q->whereBetween(DB::raw('DATE(expense_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()])
                          ->orWhereBetween(DB::raw('DATE(created_at)'), [$dateFrom->toDateString(), $dateTo->toDateString()]);
                    });
            }
            $expensesTotal = (float) $expQ->sum('amount');
        } catch (\Throwable $e) {
            $expensesTotal = 0.0;
        }

        $netSales = max(0.0, $grossSales - $salesReturns);
        $netCogs = max(0.0, $cogsSold - $cogsReturned);
        $grossProfit = max(0.0, $netSales - $netCogs);
        $grossMarginPct = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0.0;
        $netProfit = $grossProfit - $expensesTotal;

        // Summary dataset
        $summaryRows = [
            ['Section' => 'Revenue', 'Name' => 'Gross Sales', 'Amount' => $grossSales],
            ['Section' => 'Revenue', 'Name' => 'Sales Returns', 'Amount' => -1 * $salesReturns],
            ['Section' => 'Revenue', 'Name' => 'Net Sales', 'Amount' => $netSales],
            ['Section' => 'Cost of Sales', 'Name' => 'COGS', 'Amount' => -1 * $netCogs],
            ['Section' => 'Gross', 'Name' => 'Gross Profit', 'Amount' => $grossProfit],
            ['Section' => 'Operating', 'Name' => 'Operating Expenses', 'Amount' => -1 * $expensesTotal],
            ['Section' => 'Net', 'Name' => 'Net Profit', 'Amount' => $netProfit],
        ];

        // Daily detailed dataset
        if ($basis === 'cash') {
            $ordersDaily = DB::table('transactions as t')
                ->leftJoin('sales_orders', 't.order_id', '=', 'sales_orders.id')
                ->leftJoin('customers', 't.customer_id', '=', 'customers.id')
                ->where(function($q) use ($subshopFilter) {
                    $q->whereIn('sales_orders.subshop_id', $subshopFilter)
                      ->orWhereIn('customers.subshop_id', $subshopFilter);
                })
                ->where('t.transaction_type', 'payment')
                ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(t.created_at) as d, SUM(t.total_amount) as gross_sales')
                ->groupBy('d');
            $returnsDaily = DB::table('sales_returns as sr')
                ->whereRaw('1=0'); // no returns on cash basis
        } else {
            $ordersDaily = DB::table('sales_orders as so')
                ->whereIn('so.subshop_id', $subshopFilter)
                ->whereBetween('so.created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(so.created_at) as d, SUM(so.grand_total) as gross_sales')
                ->groupBy('d');
            $returnsDaily = DB::table('sales_returns as sr')
                ->whereIn('sr.subshop_id', $subshopFilter)
                ->whereBetween('sr.created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(sr.created_at) as d, SUM(COALESCE(sr.line_total,0)) as sales_returns')
                ->groupBy('d');
        }

        $cogsDaily = DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(so.created_at) as d, SUM(soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs')
            ->groupBy('d');

        $cogsReturnedDaily = DB::table('sales_returns as sr')
            ->leftJoin('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('sr.subshop_id', $subshopFilter)
            ->whereBetween('sr.created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(sr.created_at) as d, SUM(COALESCE(sr.quantity_returned,0) * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs_returns')
            ->groupBy('d');

        // Expenses by date (optional)
        $expensesDaily = collect();
        try {
            $expDQ = \App\Models\Expenses::whereIn('subshop_id', $subshopFilter);
            if ($basis === 'cash') {
                $expDQ->where('status', 'approved')
                    ->whereBetween(DB::raw('DATE(expense_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()])
                    ->selectRaw('DATE(expense_date) as d');
            } else {
                $expDQ->whereIn('status', ['approved','pending'])
                    ->where(function($q) use ($dateFrom, $dateTo) {
                        $q->whereBetween(DB::raw('DATE(expense_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()])
                          ->orWhereBetween(DB::raw('DATE(created_at)'), [$dateFrom->toDateString(), $dateTo->toDateString()]);
                    })
                    ->selectRaw('DATE(COALESCE(expense_date, created_at)) as d');
            }
            $expensesDaily = $expDQ
                ->selectRaw('SUM(amount) as expenses')
                ->groupBy('d')
                ->get()
                ->keyBy('d');
        } catch (\Throwable $e) { $expensesDaily = collect(); }

        $daily = DB::query()->fromSub($ordersDaily, 'o')
            ->leftJoinSub($returnsDaily, 'r', function($j){ $j->on('o.d','=','r.d'); })
            ->leftJoinSub($cogsDaily, 'c', function($j){ $j->on('o.d','=','c.d'); })
            ->leftJoinSub($cogsReturnedDaily, 'cr', function($j){ $j->on('o.d','=','cr.d'); })
            ->selectRaw('o.d as date, COALESCE(o.gross_sales,0) as gross_sales, COALESCE(r.sales_returns,0) as sales_returns, COALESCE(c.cogs,0) as cogs, COALESCE(cr.cogs_returns,0) as cogs_returns')
            ->orderBy('date')
            ->get()
            ->map(function($row) use ($expensesDaily) {
                $net_sales = max(0, $row->gross_sales - $row->sales_returns);
                $net_cogs = max(0, $row->cogs - $row->cogs_returns);
                $gross_profit = max(0, $net_sales - $net_cogs);
                $exp = (float) ($expensesDaily[$row->date]->expenses ?? 0);
                $net_profit = $gross_profit - $exp;
                return [
                    'Date' => Carbon::parse($row->date)->format('Y-m-d'),
                    'Gross Sales' => (float)$row->gross_sales,
                    'Sales Returns' => -(float)$row->sales_returns,
                    'Net Sales' => (float)$net_sales,
                    'COGS' => -(float)$net_cogs,
                    'Gross Profit' => (float)$gross_profit,
                    'Operating Expenses' => -(float)$exp,
                    'Net Profit' => (float)$net_profit,
                ];
            })->values()->all();

        $filenameBase = 'profit-and-loss-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $data = [
                'dateFrom' => $dateFrom->toDateString(),
                'dateTo' => $dateTo->toDateString(),
                'subshopName' => $subshopName,
                'kpi' => [
                    'gross_sales' => $grossSales,
                    'sales_returns' => $salesReturns,
                    'net_sales' => $netSales,
                    'cogs' => $netCogs,
                    'gross_profit' => $grossProfit,
                    'gross_margin_pct' => $grossMarginPct,
                    'operating_expenses' => $expensesTotal,
                    'net_profit' => $netProfit,
                ],
                'scope' => $scope,
                'daily' => $daily,
            ];
            $pdf = Pdf::loadView('reports.pdf.profit_and_loss', $data);
            return $pdf->download($filenameBase . '.pdf');
        }

        // XLSX/CSV
        if ($scope === 'daily') {
            $export = new GenericArrayExport($daily, 'P&L Detailed');
        } else {
            $export = new GenericArrayExport($summaryRows, 'P&L Summary');
        }

        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        return Excel::download($export, $filenameBase . '.' . $ext, strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
    }

    public function analyticsSalesVsCogs(Request $request)
    {
        $user = Auth::user(); if(!$user) abort(403);
        $shop = $user->shop ?: optional($user->subshops()->first())->shop; if(!$shop) abort(403);
        $allSubshops = SubShop::where('shop_id', $shop->id)->get();
        $accessible = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();
        $subshopId = $request->integer('subshop_id');
        $subshopFilter = $subshopId ? (in_array($subshopId, $accessible, true) ? [$subshopId] : abort(403)) : ($accessible ?: [-1]);
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        $basis = $request->query('basis', 'accrual');
        if ($basis === 'cash') {
            $orders = DB::table('transactions as t')
                ->leftJoin('sales_orders', 't.order_id', '=', 'sales_orders.id')
                ->leftJoin('customers', 't.customer_id', '=', 'customers.id')
                ->where(function($q) use ($subshopFilter) {
                    $q->whereIn('sales_orders.subshop_id', $subshopFilter)
                      ->orWhereIn('customers.subshop_id', $subshopFilter);
                })
                ->where('t.transaction_type', 'payment')
                ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(t.created_at) as d, SUM(t.total_amount) as revenue')
                ->groupBy('d');
        } else {
            $orders = DB::table('sales_orders as so')
                ->whereIn('so.subshop_id', $subshopFilter)
                ->whereBetween('so.created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(so.created_at) as d, SUM(so.grand_total) as revenue')
                ->groupBy('d');
        }

        $cogs = DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(so.created_at) as d, SUM(soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs')
            ->groupBy('d');

        $rows = DB::query()->fromSub($orders, 'o')
            ->leftJoinSub($cogs, 'c', function($j){ $j->on('o.d','=','c.d'); })
            ->orderBy('o.d')
            ->get(['o.d','o.revenue',DB::raw('COALESCE(c.cogs,0) as cogs')]);

        $labels = $rows->pluck('d')->map(fn($d)=>Carbon::parse($d)->format('Y-m-d'));
        return response()->json([
            'labels'=>$labels,
            'revenue'=>$rows->pluck('revenue')->map(fn($v)=>(float)$v),
            'cogs'=>$rows->pluck('cogs')->map(fn($v)=>(float)$v),
        ]);
    }

    public function analyticsMargin(Request $request)
    {
        $user = Auth::user(); if(!$user) abort(403);
        $shop = $user->shop ?: optional($user->subshops()->first())->shop; if(!$shop) abort(403);
        $allSubshops = SubShop::where('shop_id', $shop->id)->get();
        $accessible = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();
        $subshopId = $request->integer('subshop_id');
        $subshopFilter = $subshopId ? (in_array($subshopId, $accessible, true) ? [$subshopId] : abort(403)) : ($accessible ?: [-1]);
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        $orders = DB::table('sales_orders as so')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(so.created_at) as d, SUM(so.grand_total) as revenue')
            ->groupBy('d');
        $cogs = DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(so.created_at) as d, SUM(soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs')
            ->groupBy('d');

        $rows = DB::query()->fromSub($orders, 'o')
            ->leftJoinSub($cogs, 'c', function($j){ $j->on('o.d','=','c.d'); })
            ->selectRaw('o.d, o.revenue, COALESCE(c.cogs,0) as cogs')
            ->orderBy('o.d')->get();

        $labels = $rows->pluck('d')->map(fn($d)=>Carbon::parse($d)->format('Y-m-d'));
        $margin = $rows->map(function($r){ $gp = max(($r->revenue - $r->cogs),0); return $r->revenue>0 ? round(($gp/$r->revenue)*100,2) : 0; });
        return response()->json(['labels'=>$labels,'margin_pct'=>$margin]);
    }

    public function analyticsWaterfall(Request $request)
    {
        $user = Auth::user(); if(!$user) abort(403);
        $shop = $user->shop ?: optional($user->subshops()->first())->shop; if(!$shop) abort(403);
        $allSubshops = SubShop::where('shop_id', $shop->id)->get();
        $accessible = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();
        $subshopId = $request->integer('subshop_id');
        $subshopFilter = $subshopId ? (in_array($subshopId, $accessible, true) ? [$subshopId] : abort(403)) : ($accessible ?: [-1]);
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        $basis = $request->query('basis', 'accrual');
        if ($basis === 'cash') {
            $grossSales = (float) DB::table('transactions as t')
                ->leftJoin('sales_orders', 't.order_id', '=', 'sales_orders.id')
                ->leftJoin('customers', 't.customer_id', '=', 'customers.id')
                ->where(function($q) use ($subshopFilter) {
                    $q->whereIn('sales_orders.subshop_id', $subshopFilter)
                      ->orWhereIn('customers.subshop_id', $subshopFilter);
                })
                ->where('t.transaction_type', 'payment')
                ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                ->sum('t.total_amount');
            $salesReturns = 0.0;
        } else {
            $grossSales = (float) DB::table('sales_orders as so')
                ->whereIn('so.subshop_id', $subshopFilter)
                ->whereBetween('so.created_at', [$dateFrom, $dateTo])
                ->sum('so.grand_total');
            $salesReturns = (float) DB::table('sales_returns as sr')
                ->whereIn('sr.subshop_id', $subshopFilter)
                ->whereBetween('sr.created_at', [$dateFrom, $dateTo])
                ->sum(DB::raw('COALESCE(sr.line_total,0)'));
        }
        $cogsSold = (float) DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->sum(DB::raw('soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))'));
        $cogsReturned = (float) DB::table('sales_returns as sr')
            ->leftJoin('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('sr.subshop_id', $subshopFilter)
            ->whereBetween('sr.created_at', [$dateFrom, $dateTo])
            ->sum(DB::raw('COALESCE(sr.quantity_returned,0) * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))'));
        $expenses = 0.0;
        try {
            $expW = \App\Models\Expenses::whereIn('subshop_id', $subshopFilter);
            if ($basis === 'cash') {
                $expW->where('status', 'approved')
                    ->whereBetween(DB::raw('DATE(expense_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()]);
            } else {
                $expW->whereIn('status', ['approved','pending'])
                    ->where(function($q) use ($dateFrom, $dateTo) {
                        $q->whereBetween(DB::raw('DATE(expense_date)'), [$dateFrom->toDateString(), $dateTo->toDateString()])
                          ->orWhereBetween(DB::raw('DATE(created_at)'), [$dateFrom->toDateString(), $dateTo->toDateString()]);
                    });
            }
            $expenses = (float) $expW->sum('amount');
        } catch (\Throwable $e) {
            $expenses = 0.0;
        }

        $netSales = max(0.0, $grossSales - $salesReturns);
        $netCogs = max(0.0, $cogsSold - $cogsReturned);
        $grossProfit = max(0.0, $netSales - $netCogs);
        $netProfit = $grossProfit - $expenses;

        return response()->json([
            'labels' => ['Gross Sales','Sales Returns','Net Sales','COGS','Gross Profit','Operating Expenses','Net Profit'],
            'values' => [
                (float)$grossSales,
                -(float)$salesReturns,
                (float)$netSales,
                -(float)$netCogs,
                (float)$grossProfit,
                -(float)$expenses,
                (float)$netProfit,
            ]
        ]);
    }
}
