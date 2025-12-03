<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SubShop;
use App\Exports\Reports\SalesReportExportAll;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) { abort(403); }

        // Determine shop context and accessible subshops
        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) { abort(403, 'No shop found for user'); }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        // If subshop_id is explicitly provided in the request, use it; otherwise, use all accessible subshops
        $subshopId = $request->integer('subshop_id');
        if ($subshopId) {
            if (!in_array($subshopId, $accessibleSubshopIds, true)) {
                abort(403, 'You do not have access to this subshop');
            }
            $subshopFilter = [$subshopId];
        } else {
            // Default to all accessible subshops when no specific subshop is selected
            $subshopFilter = $accessibleSubshopIds ?: [-1];
        }
        
        // Clear any session-stored subshop_id to ensure we're not using it
        //session()->forget('subshop_id');

        // Date range (default last 30 days)
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        // Orders base (matches invoices grand_total behavior)
        $ordersBase = DB::table('sales_orders as so')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereDate('so.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('so.created_at', '<=', $dateTo->toDateString());

        $orders = (clone $ordersBase)->count('so.id');
        $sumGrand = (float) ((clone $ordersBase)->sum('so.grand_total'));

        // Units and COGS from current items attached to the filtered orders
        $itemsAgg = DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereDate('so.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('so.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(soi.quantity) as units_sold')
            ->selectRaw('SUM(COALESCE(soi.base_amount, soi.line_total, soi.quantity * COALESCE(soi.unit_price, COALESCE(ib.selling_price, COALESCE(items.price, 0))))) as revenue_base')
            ->selectRaw('SUM(soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs');
        $itemsAggRow = (array) $itemsAgg->first();

        // Returns totals (VAT-included, to show in card), aligned to returns records timeframe
        $qReturns = DB::table('sales_returns as sr')
            ->leftJoin('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('sr.subshop_id', $subshopFilter)
            ->whereDate('sr.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('sr.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(COALESCE(sr.quantity_returned,0)) as units_returned')
            ->selectRaw('SUM(COALESCE(sr.line_total, COALESCE(sr.quantity_returned,0) * COALESCE(NULLIF(soi.line_total,0)/NULLIF(soi.quantity,0), COALESCE(soi.unit_price, COALESCE(ib.selling_price, COALESCE(items.price, 0))), 0))) as returned_line_total')
            ->selectRaw('SUM(COALESCE(sr.quantity_returned,0) * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs_returned');
        $returns = (array) $qReturns->first();

        $unitsSold = (int)($itemsAggRow['units_sold'] ?? 0);
        $revenueBase = (float)($itemsAggRow['revenue_base'] ?? 0);
        $cogsSold = (float)($itemsAggRow['cogs'] ?? 0);
        $unitsReturned = (int)($returns['units_returned'] ?? 0);
        $revenueReturned = (float)($returns['returned_line_total'] ?? 0);
        $cogsReturned = (float)($returns['cogs_returned'] ?? 0);

        // KPIs
        $netUnits = max(0, $unitsSold - $unitsReturned);
        // Net Sales to match invoices "Grand Total": sum of orders grand_total within range
        $netSales = max(0.0, $sumGrand);
        // Use base revenue for profit calc; subtract COGS net of returns
        $netCogs = max(0.0, $cogsSold - $cogsReturned);
        $grossProfit = max(0.0, $revenueBase - $netCogs);
        $marginPct = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0.0;
        $aov = $orders > 0 ? round($netSales / $orders, 2) : 0.0;

        // Prepare data for charts
        $chartData = [
            'salesTrend' => $this->getSalesTrendData($dateFrom, $dateTo, $subshopFilter),
            'subshopComparison' => $this->getSubshopComparisonData($dateFrom, $dateTo, $allSubshops, $accessibleSubshopIds),
            'topCategories' => $this->getTopCategoriesData($dateFrom, $dateTo, $subshopFilter)
        ];

        return view('reports.sales_report', [
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'kpi' => [
                'net_sales' => $netSales,
                'orders' => $orders,
                'aov' => $aov,
                'units' => $netUnits,
                'gross_profit' => $grossProfit,
                'margin_pct' => $marginPct,
                'returns_amount' => $revenueReturned,
            ],
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'chartData' => $chartData,
            'ordersList' => $this->getOrdersList($dateFrom, $dateTo, $subshopFilter),
            'productPerformance' => $this->getProductPerformance($dateFrom, $dateTo, $subshopFilter),
            'returnsList' => $this->getReturnsList($dateFrom, $dateTo, $subshopFilter),
            'exportUrl' => route('reports.sales.export', [
                'format' => 'xlsx',
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_id' => $subshopId
            ]),
            'pdfUrl' => route('reports.sales.export', [
                'format' => 'pdf',
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_id' => $subshopId
            ]),
            'csvUrl' => route('reports.sales.export', [
                'format' => 'csv',
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_id' => $subshopId
            ]),
        ]);
    }

    /**
     * Get sales trend data for the chart
     */
    private function getSalesTrendData($dateFrom, $dateTo, $subshopFilter)
    {
        $dateFormat = $this->getDateFormatForRange($dateFrom, $dateTo);
        
        $salesData = DB::table('sales_orders as so')
            ->select(
                DB::raw("DATE_FORMAT(so.created_at, '{$dateFormat}') as period"),
                DB::raw('SUM(so.grand_total) as revenue'),
                DB::raw('SUM(soi.quantity * COALESCE(ib.cost_price, items.cost_price, 0)) as cogs')
            )
            ->join('sales_orders_items as soi', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $labels = [];
        $revenueData = [];
        $marginData = [];

        foreach ($salesData as $data) {
            $labels[] = $data->period;
            $revenueData[] = (float) $data->revenue;
            $margin = $data->revenue - $data->cogs;
            $marginData[] = $margin > 0 ? $margin : 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenueData,
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.1)',
                    'tension' => 0.3,
                    'fill' => true
                ],
                [
                    'label' => 'Gross Profit',
                    'data' => $marginData,
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.1)',
                    'tension' => 0.3,
                    'fill' => true
                ]
            ]
        ];
    }

    /**
     * Get subshop comparison data
     */
    private function getSubshopComparisonData($dateFrom, $dateTo, $allSubshops, $accessibleSubshopIds)
    {
        $subshopSales = DB::table('sales_orders as so')
            ->select(
                'sub_shops.id',
                'sub_shops.name',
                DB::raw('SUM(so.grand_total) as total_sales'),
                DB::raw('COUNT(DISTINCT so.id) as order_count')
            )
            ->join('sub_shops', 'sub_shops.id', '=', 'so.subshop_id')
            ->whereIn('so.subshop_id', $accessibleSubshopIds)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->groupBy('sub_shops.id', 'sub_shops.name')
            ->orderBy('total_sales', 'desc')
            ->get();

        $labels = [];
        $salesData = [];
        $orderData = [];
        $colors = [];
        $borderColors = [
            'rgba(54, 162, 235, 1)',
            'rgba(255, 99, 132, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 159, 64, 1)',
            'rgba(199, 199, 199, 1)'
        ];

        foreach ($subshopSales as $index => $data) {
            $labels[] = $data->name;
            $salesData[] = (float) $data->total_sales;
            $orderData[] = (int) $data->order_count;
            $colors[] = $borderColors[$index % count($borderColors)];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total Sales',
                    'data' => $salesData,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 1
                ]
            ]
        ];
    }

    /**
     * Get top categories data
     */
    private function getTopCategoriesData($dateFrom, $dateTo, $subshopFilter)
    {
        $topCategories = DB::table('sales_orders as so')
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(soi.quantity) as total_quantity'),
                DB::raw('SUM(soi.line_total) as total_revenue'),
                DB::raw('SUM(soi.quantity * COALESCE(ib.cost_price, items.cost_price, 0)) as total_cost')
            )
            ->join('sales_orders_items as soi', 'so.id', '=', 'soi.sales_order_id')
            ->join('items', 'items.id', '=', 'soi.item_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->groupBy('categories.name')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get();

        $labels = [];
        $revenueData = [];
        $profitData = [];
        $colors = [
            'rgba(255, 99, 132, 0.7)',
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)',
            'rgba(199, 199, 199, 0.7)',
            'rgba(83, 102, 255, 0.7)',
            'rgba(40, 208, 148, 0.7)',
            'rgba(255, 99, 132, 0.7)'
        ];

        foreach ($topCategories as $category) {
            $labels[] = $category->category_name ?: 'Uncategorized';
            $revenueData[] = (float) $category->total_revenue;
            $profitData[] = (float) ($category->total_revenue - $category->total_cost);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenueData,
                    'backgroundColor' => $colors,
                    'borderColor' => array_map(function($color) {
                        return str_replace('0.7', '1', $color);
                    }, $colors),
                    'borderWidth' => 1
                ],
                [
                    'label' => 'Profit',
                    'data' => $profitData,
                    'backgroundColor' => array_map(function($color) {
                        return str_replace('0.7', '0.4', $color);
                    }, $colors),
                    'borderColor' => array_map(function($color) {
                        return str_replace('0.7', '1', $color);
                    }, $colors),
                    'borderWidth' => 1,
                    'type' => 'bar'
                ]
            ]
        ];
    }

    /**
     * Determine the appropriate date format for the given date range
     */
    private function getDateFormatForRange($dateFrom, $dateTo)
    {
        $daysDifference = $dateFrom->diffInDays($dateTo);
        
        if ($daysDifference <= 1) {
            return '%H:00'; // Hourly
        } elseif ($daysDifference <= 7) {
            return '%a %e %b'; // Daily with day name
        } elseif ($daysDifference <= 31) {
            return '%e %b'; // Daily with date
        } elseif ($daysDifference <= 90) {
            return 'Week %v, %Y'; // Weekly
        } else {
            return '%b %Y'; // Monthly
        }
    }

    /**
     * Get recent orders for the orders list table
     */
    private function getOrdersList($dateFrom, $dateTo, $subshopFilter)
    {
        return DB::table('sales_orders as so')
            ->select(
                'so.id',
                'so.order_no as invoice_number',
                DB::raw('COALESCE(customers.name, "Walk-in Customer") as customer_name'),
                'sub_shops.name as subshop_name',
                'so.grand_total',
                'so.payment_method as payment_status',
                'so.status',
                'so.created_at',
                'users.name as cashier_name'
            )
            ->leftJoin('sub_shops', 'sub_shops.id', '=', 'so.subshop_id')
            ->leftJoin('users', 'users.id', '=', 'so.created_by')
            ->leftJoin('customers', 'customers.id', '=', 'so.customer_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->orderBy('so.created_at', 'desc')
            ->limit(15)
            ->get();
    }

    /**
     * Get product performance data
     */
    private function getProductPerformance($dateFrom, $dateTo, $subshopFilter)
    {
        return DB::table('sales_orders_items as soi')
            ->select(
                'soi.item_name as product_name',
                'items.sku',
                'categories.name as category_name',
                DB::raw('SUM(soi.quantity) as quantity_sold'),
                DB::raw('SUM(soi.line_total) as revenue'),
                DB::raw('SUM(soi.quantity * COALESCE(ib.cost_price, items.cost_price, 0)) as cogs'),
                DB::raw('SUM(soi.line_total - (soi.quantity * COALESCE(ib.cost_price, items.cost_price, 0))) as profit')
            )
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereBetween('so.created_at', [$dateFrom, $dateTo])
            ->groupBy('soi.item_id', 'soi.item_name', 'items.sku', 'categories.name')
            ->orderBy('revenue', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Get returns list
     */
    private function getReturnsList($dateFrom, $dateTo, $subshopFilter)
    {
        return DB::table('sales_returns as sr')
            ->select(
                'sr.id',
                'so.order_no as invoice_number',
                'soi.item_name as product_name',
                'sr.quantity_returned',
                'sr.line_total as return_amount',
                'sr.reason',
                'sr.processed_by',
                'sr.created_at',
                'users.name as processed_by_name'
            )
            ->join('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
            ->join('sales_orders as so', 'so.id', '=', 'sr.sales_order_id')
            ->leftJoin('users', 'users.id', '=', 'sr.processed_by')
            ->whereIn('sr.subshop_id', $subshopFilter)
            ->whereBetween('sr.created_at', [$dateFrom, $dateTo])
            ->orderBy('sr.created_at', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Export sales report
     */
    public function export(Request $request, $format = 'xlsx')
    {
        $user = Auth::user();
        if (!$user) { abort(403); }

        // Get filter parameters
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();
        $subshopId = $request->integer('subshop_id');

        // Determine shop context and accessible subshops
        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) { abort(403, 'No shop found for user'); }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        // If subshop_id is explicitly provided in the request, use it; otherwise, use all accessible subshops
        if ($subshopId) {
            if (!in_array($subshopId, $accessibleSubshopIds, true)) {
                abort(403, 'You do not have access to this subshop');
            }
            $subshopFilter = [$subshopId];
            $subshopName = $allSubshops->where('id', $subshopId)->first()->name ?? null;
        } else {
            // Default to all accessible subshops when no specific subshop is selected
            $subshopFilter = $accessibleSubshopIds ?: [-1];
            $subshopName = null;
        }

        // Get data for export with the same logic as the main report
        $ordersBase = DB::table('sales_orders as so')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereDate('so.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('so.created_at', '<=', $dateTo->toDateString());

        $orders = (clone $ordersBase)->count('so.id');
        $sumGrand = (float) ((clone $ordersBase)->sum('so.grand_total'));

        // Get items data for units and COGS
        $itemsAgg = DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('so.subshop_id', $subshopFilter)
            ->whereDate('so.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('so.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(soi.quantity) as units_sold')
            ->selectRaw('SUM(COALESCE(soi.base_amount, soi.line_total, soi.quantity * COALESCE(soi.unit_price, COALESCE(ib.selling_price, COALESCE(items.price, 0))))) as revenue')
            ->selectRaw('SUM(soi.quantity * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs');
        $itemsAggRow = (array) $itemsAgg->first();

        // Get returns data
        $qReturns = DB::table('sales_returns as sr')
            ->leftJoin('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->leftJoin('items', 'items.id', '=', 'soi.item_id')
            ->whereIn('sr.subshop_id', $subshopFilter)
            ->whereDate('sr.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('sr.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(COALESCE(sr.quantity_returned,0)) as units_returned')
            ->selectRaw('SUM(COALESCE(sr.line_total, COALESCE(sr.quantity_returned,0) * COALESCE(NULLIF(soi.line_total,0)/NULLIF(soi.quantity,0), COALESCE(soi.unit_price, COALESCE(ib.selling_price, COALESCE(items.price, 0))), 0))) as returned_line_total')
            ->selectRaw('SUM(COALESCE(sr.quantity_returned,0) * COALESCE(ib.cost_price, COALESCE(items.cost_price, 0))) as cogs_returned');
        $returns = (array) $qReturns->first();

        // Calculate KPIs
        $unitsSold = (int)($itemsAggRow['units_sold'] ?? 0);
        $revenueBase = (float)($itemsAggRow['revenue'] ?? 0);
        $cogsSold = (float)($itemsAggRow['cogs'] ?? 0);
        $unitsReturned = (int)($returns['units_returned'] ?? 0);
        $revenueReturned = (float)($returns['returned_line_total'] ?? 0);
        $cogsReturned = (float)($returns['cogs_returned'] ?? 0);

        // Calculate final metrics
        $netUnits = max(0, $unitsSold - $unitsReturned);
        $netSales = max(0.0, $sumGrand);
        $netCogs = max(0.0, $cogsSold - $cogsReturned);
        $grossProfit = max(0.0, $revenueBase - $netCogs);
        $marginPct = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0.0;
        $aov = $orders > 0 ? round($netSales / $orders, 2) : 0.0;

        // Get detailed data for export
        $ordersList = $this->getOrdersList($dateFrom, $dateTo, $subshopFilter);
        $productPerformance = $this->getProductPerformance($dateFrom, $dateTo, $subshopFilter);
        $returnsList = $this->getReturnsList($dateFrom, $dateTo, $subshopFilter);

        // Prepare KPI data for export
        $kpi = [
            'net_sales' => $netSales,
            'orders' => $orders,
            'aov' => $aov,
            'units' => $netUnits,
            'gross_profit' => $grossProfit,
            'margin_pct' => $marginPct,
            'returns_amount' => $revenueReturned,
        ];

        // Create export instance
        $export = new SalesReportExportAll(
            $ordersList,
            $productPerformance,
            $returnsList,
            $kpi,
            $dateFrom,
            $dateTo,
            $subshopName
        );

        // Generate filename
        $filename = 'sales-report-' . now()->format('Y-m-d-His');
        
        // Handle different export formats
        switch (strtolower($format)) {
            case 'csv':
                $filename .= '.csv';
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV, [
                    'Content-Type' => 'text/csv',
                ]);
                
            case 'pdf':
                // For PDF, we'll use a custom view
                $data = [
                    'ordersList' => $ordersList,
                    'productPerformance' => $productPerformance,
                    'returnsList' => $returnsList,
                    'kpi' => $kpi,
                    'dateFrom' => $dateFrom->format('Y-m-d'),
                    'dateTo' => $dateTo->format('Y-m-d'),
                    'subshopName' => $subshopName,
                    'generatedAt' => now()->format('Y-m-d H:i:s'),
                ];
                
                $pdf = PDF::loadView('reports.pdf.sales_report', $data);
                return $pdf->download($filename . '.pdf');
                
            case 'xlsx':
            default:
                $filename .= '.xlsx';
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::XLSX);
        }
    }
}
