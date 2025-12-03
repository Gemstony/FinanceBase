<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route as RouteFacade;
use Dompdf\Dompdf;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // For shop owners
        $shop = $user->shop;

        // For users assigned to subshops
        if (!$shop && $user->subshops()->exists()) {
            // Get the first subshop's shop (assuming a user can be assigned to multiple subshops)
            $subshop = $user->subshops()->first();
            if ($subshop) {
                $shop = $subshop->shop;
            }
        }

        // If still no shop, set to null (handle this case in your view)
        if (!$shop) {
            return view('dashboard', ['shop' => null]);
        }

        // Read optional date range from query; fallback to session; allow clearing
        if ($request->boolean('clear_filters')) {
            session()->forget(['dash_date_from','dash_date_to']);
        }
        $dateFrom = $request->query('date_from', session('dash_date_from'));
        $dateTo = $request->query('date_to', session('dash_date_to'));
        if ($dateFrom && $dateTo) {
            session(['dash_date_from' => $dateFrom, 'dash_date_to' => $dateTo]);
        }

        // Get expiry alerts for user's subshops (respect optional range)
        $expiryAlerts = $this->getExpiryAlerts(
            $user,
            $dateFrom ? \Carbon\Carbon::parse($dateFrom)->startOfDay() : null,
            ($dateFrom && $dateTo) ? \Carbon\Carbon::parse($dateTo)->endOfDay() : null
        );

        // Get subscription expiry alerts for shop owners only
        $subscriptionAlerts = collect();
        if ($user->shop) {
            $subscriptionAlerts = $this->getSubscriptionExpiryAlerts($user->shop);
        }

        // Calculate KPI metrics (default to Today if no filter, but do NOT change global filter/session)
        $kpiFrom = ($dateFrom && $dateTo) ? $dateFrom : now()->toDateString();
        $kpiTo = ($dateFrom && $dateTo) ? $dateTo : now()->toDateString();
        $kpis = $this->calculateKPIs($user, $shop, $kpiFrom, $kpiTo);

        // Get active subshop information
        $activeSubshop = null;
        $activeSubshopId = session('subshop_id');
        if ($activeSubshopId) {
            $activeSubshop = \App\Models\SubShop::find($activeSubshopId);
        }

        // Detailed outstanding lists for active subshop (below charts)
        $outstandingSalesList = [];
        $outstandingPurchasesList = [];
        if ($activeSubshopId) {
            $rangeStart = ($dateFrom && $dateTo) ? \Carbon\Carbon::parse($dateFrom)->startOfDay() : null;
            $rangeEnd = ($dateFrom && $dateTo) ? \Carbon\Carbon::parse($dateTo)->endOfDay() : null;
            $outstandingSalesList = $this->getOutstandingSalesList($activeSubshopId, $rangeStart, $rangeEnd);
            $outstandingPurchasesList = $this->getOutstandingPurchasesList($activeSubshopId, $rangeStart, $rangeEnd);
        }

        return view('dashboard', compact('shop', 'expiryAlerts', 'subscriptionAlerts', 'kpis', 'activeSubshop', 'outstandingSalesList', 'outstandingPurchasesList'));
    }

    /**
     * Analytics: Payments collected per day for last 30 days (active subshop)
     */
    public function paymentsDaily(Request $request)
    {
        $activeSubshopId = session('subshop_id');
        if (!$activeSubshopId) {
            return response()->json(['labels' => [], 'values' => []]);
        }

        $df = $request->query('date_from', session('dash_date_from'));
        $dt = $request->query('date_to', session('dash_date_to'));
        if ($df && $dt) {
            $start = \Carbon\Carbon::parse($df)->startOfDay();
            $end = \Carbon\Carbon::parse($dt)->endOfDay();
        } else {
            $days = (int) ($request->query('days', 30));
            $start = now()->subDays($days - 1)->startOfDay();
            $end = now()->endOfDay();
        }

        $raw = \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->where('sales_orders.subshop_id', $activeSubshopId)
            ->where('transactions.transaction_type', 'payment')
            ->whereBetween('transactions.transaction_date', [$start, $end])
            ->selectRaw('DATE(transactions.transaction_date) as d, SUM(transactions.total_amount) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $labels = [];
        $values = [];
        $cursor = \Carbon\Carbon::parse($start);
        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $labels[] = $cursor->format('M j');
            $values[] = (float) ($raw[$dateStr] ?? 0);
            $cursor->addDay();
        }

        return response()->json(['labels' => $labels, 'values' => $values]);
    }

    /**
     * Export Smart Alerts in given format: pdf|excel|csv
     */
    public function exportAlerts(Request $request, string $format)
    {
        $format = strtolower($format);
        // Reuse alerts data for active subshop
        $data = $this->alerts($request)->getData(true);
        $rows = [];
        $addRows = function($priority, $items) use (&$rows) {
            foreach ($items as $it) {
                $rows[] = [
                    'priority' => $priority,
                    'type' => $it['type'] ?? '',
                    'title' => $it['title'] ?? '',
                    'message' => $it['message'] ?? '',
                    'action_url' => $it['action_url'] ?? '',
                ];
            }
        };
        $addRows('critical', $data['critical'] ?? []);
        $addRows('high', $data['high'] ?? []);
        $addRows('medium', $data['medium'] ?? []);

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="dashboard-alerts.csv"',
            ];
            $callback = function() use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Priority', 'Type', 'Title', 'Message', 'Action URL']);
                foreach ($rows as $r) { fputcsv($out, $r); }
                fclose($out);
            };
            return response()->stream($callback, 200, $headers);
        }

        if ($format === 'excel' || $format === 'xlsx') {
            $export = new class($rows) implements FromArray {
                private $rows; public function __construct($rows){ $this->rows = $rows; }
                public function array(): array { return array_merge([
                    ['Priority','Type','Title','Message','Action URL']
                ], array_map(function($r){ return array_values($r); }, $this->rows)); }
            };
            return Excel::download($export, 'dashboard-alerts.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = null;
            try { $sid = session('subshop_id'); if ($sid) { $subshop = \App\Models\SubShop::find($sid); } } catch (\Throwable $e) {}
            $generatedBy = optional(auth()->user())->name ?? 'System';
            $html = view('exports.alerts_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'generatedBy' => $generatedBy,
            ])->render();
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="dashboard-alerts.pdf"',
            ]);
        }

        return response()->json(['error' => 'Unsupported format'], 400);
    }

    /**
     * Export a quick PDF report summarizing the dashboard (KPIs + Alerts)
     */
    public function exportQuickReport(Request $request)
    {
        $user = auth()->user();
        $shop = $user ? $user->shop : null;
        if (!$shop && $user && $user->subshops()->exists()) {
            $subshop = $user->subshops()->first();
            if ($subshop) { $shop = $subshop->shop; }
        }

        $df = $request->query('date_from', session('dash_date_from'));
        $dt = $request->query('date_to', session('dash_date_to'));
        if ($df && $dt) { session(['dash_date_from' => $df, 'dash_date_to' => $dt]); }

        // KPIs for the current context
        $kpis = $this->calculateKPIs($user, $shop, $df, $dt);

        // Alerts summary (reuse existing endpoint logic)
        $alertsData = $this->alerts($request)->getData(true);

        // Active subshop details
        $subshop = null;
        try { $sid = session('subshop_id'); if ($sid) { $subshop = \App\Models\SubShop::find($sid); } } catch (\Throwable $e) {}

        $generatedBy = optional($user)->name ?? 'System';

        $html = view('exports.quick_report_pdf', [
            'shop' => $shop,
            'subshop' => $subshop,
            'dateFrom' => $df,
            'dateTo' => $dt,
            'kpis' => $kpis,
            'alerts' => $alertsData,
            'generatedBy' => $generatedBy,
        ])->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dashboard-quick-report.pdf"',
        ]);
    }

    /**
     * Export analytics by type (payments|orders|net|aov) to format (pdf|excel|csv)
     */
    public function exportAnalytics(Request $request, string $type, string $format)
    {
        $type = strtolower($type);
        $format = strtolower($format);

        // Build dataset
        $dataset = ['labels' => [], 'columns' => [], 'rows' => []];
        if ($type === 'payments') {
            $json = $this->paymentsDaily($request)->getData(true);
            $dataset['columns'] = ['Date', 'Payments'];
            foreach (($json['labels'] ?? []) as $i => $label) {
                $dataset['rows'][] = [$label, $json['values'][$i] ?? 0];
            }
        } elseif ($type === 'orders') {
            $json = $this->ordersDaily($request)->getData(true);
            $dataset['columns'] = ['Date', 'Orders'];
            foreach (($json['labels'] ?? []) as $i => $label) {
                $dataset['rows'][] = [$label, $json['values'][$i] ?? 0];
            }
        } elseif ($type === 'net') {
            $json = $this->netPaymentsRefunds($request)->getData(true);
            $dataset['columns'] = ['Date', 'Payments', 'Refunds'];
            foreach (($json['labels'] ?? []) as $i => $label) {
                $dataset['rows'][] = [$label, $json['payments'][$i] ?? 0, $json['refunds'][$i] ?? 0];
            }
        } elseif ($type === 'aov') {
            $json = $this->aovDaily($request)->getData(true);
            $dataset['columns'] = ['Date', 'Average Order Value'];
            foreach (($json['labels'] ?? []) as $i => $label) {
                $dataset['rows'][] = [$label, $json['values'][$i] ?? 0];
            }
        } else {
            return response()->json(['error' => 'Unknown analytics type'], 400);
        }

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="dashboard-'.$type.'.csv"',
            ];
            $callback = function() use ($dataset) {
                $out = fopen('php://output', 'w');
                fputcsv($out, $dataset['columns']);
                foreach ($dataset['rows'] as $r) { fputcsv($out, $r); }
                fclose($out);
            };
            return response()->stream($callback, 200, $headers);
        }

        if ($format === 'excel' || $format === 'xlsx') {
            $export = new class($dataset) implements FromArray {
                private $d; public function __construct($d){ $this->d = $d; }
                public function array(): array { return array_merge([
                    $this->d['columns']
                ], $this->d['rows']); }
            };
            return Excel::download($export, 'dashboard-'.$type.'.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = null;
            try { $sid = session('subshop_id'); if ($sid) { $subshop = \App\Models\SubShop::find($sid); } } catch (\Throwable $e) {}
            $generatedBy = optional(auth()->user())->name ?? 'System';
            $title = strtoupper($type) === 'NET' ? 'Net Payments vs Refunds' : (strtoupper($type) === 'AOV' ? 'Average Order Value' : ucfirst($type));
            $html = view('exports.analytics_pdf', [
                'title' => $title,
                'columns' => $dataset['columns'],
                'rows' => $dataset['rows'],
                'subshop' => $subshop,
                'generatedBy' => $generatedBy,
            ])->render();
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="dashboard-'.$type.'.pdf"',
            ]);
        }

        return response()->json(['error' => 'Unsupported format'], 400);
    }

    /**
     * Alerts API: Priority-based smart alerts for the active subshop
     */
    public function alerts(Request $request)
    {
        $df = $request->query('date_from', session('dash_date_from'));
        $dt = $request->query('date_to', session('dash_date_to'));
        $rangeStart = ($df && $dt) ? \Carbon\Carbon::parse($df)->startOfDay() : null;
        $rangeEnd = ($df && $dt) ? \Carbon\Carbon::parse($dt)->endOfDay() : null;
        $activeSubshopId = session('subshop_id');
        if (!$activeSubshopId) {
            // Fallback to first accessible subshop (same behavior as KPIs)
            $user = auth()->user();
            $shop = $user->shop;
            if (!$shop && $user->subshops()->exists()) {
                $subshop = $user->subshops()->first();
                if ($subshop) {
                    $shop = $subshop->shop;
                }
            }
            $accessible = $this->getUserSubshopIds($user, $shop);
            $activeSubshopId = !empty($accessible) ? $accessible[0] : null;
            if (!$activeSubshopId) {
                return response()->json(['critical' => [], 'high' => [], 'medium' => []]);
            }
        }

        $critical = [];
        $high = [];
        $medium = [];
        $error = null;

        try {
            // Critical: Out of stock items
            foreach ($this->collectOutOfStock($activeSubshopId) as $item) {
                $critical[] = [
                    'type' => 'out_of_stock',
                    'title' => 'Out of Stock: ' . $item['name'],
                    'message' => $item['name'] . ' is out of stock.',
                    'priority' => 'critical',
                    'action_url' => $this->safeRoute('items.index', [], route('items.index', [], false) ? route('items.index') : '#') . '?filter=out_of_stock'
                ];
            }

            // Critical: System errors (placeholder if none available)
            foreach ($this->collectSystemErrors() as $err) {
                $critical[] = $err;
            }

            // High: Low stock warnings
            foreach ($this->collectLowStock($activeSubshopId) as $row) {
                $high[] = [
                    'type' => 'low_stock',
                    'title' => 'Low Stock: ' . $row['name'],
                    'message' => $row['name'] . ' has ' . $row['quantity'] . ' remaining (min ' . $row['min'] . ').',
                    'priority' => 'high',
                    'action_url' => $this->safeRoute('items.index', [], '#') . '?filter=low_stock'
                ];
            }

            // High: Expiring products (reuse existing expiry alerts)
            try {
                $expiryAlerts = $this->getExpiryAlerts(auth()->user(), $rangeStart, $rangeEnd);
                foreach ($expiryAlerts as $alert) {
                    $high[] = [
                        'type' => $alert['type'] === 'expired' ? 'expired' : 'expiring_soon',
                        'title' => $alert['title'],
                        'message' => $alert['message'],
                        'priority' => in_array($alert['priority'], ['critical','high']) ? 'high' : 'medium',
                        'action_url' => $alert['action_url'] ?? $this->safeRoute('items.index', [], '#')
                    ];
                }
            } catch (\Throwable $e) {
                // ignore expiry alert errors
            }

            // Medium: Outstanding sales (receivables)
            foreach ($this->collectOutstandingSales($activeSubshopId, $rangeStart, $rangeEnd) as $row) {
                $medium[] = [
                    'type' => 'outstanding_sales',
                    'title' => 'Outstanding Sale: #' . ($row['order_no'] ?? $row['order_id']),
                    'message' => 'Unpaid balance: TZS ' . number_format($row['balance'], 0),
                    'priority' => 'medium',
                    'action_url' => $this->safeRoute('invoices.index', [], '#') . '?status=pending'
                ];
            }

            // Medium: Outstanding purchases (if model exists)
            foreach ($this->collectOutstandingPurchases($activeSubshopId, $rangeStart, $rangeEnd) as $row) {
                $medium[] = [
                    'type' => 'outstanding_purchases',
                    'title' => 'Outstanding Purchase: #' . ($row['ref'] ?? $row['id']),
                    'message' => 'Unpaid bill: TZS ' . number_format($row['balance'], 0),
                    'priority' => 'medium',
                    'action_url' => $this->safeRoute('purchases.index', [], '#') . '?status=pending'
                ];
            }
        } catch (\Throwable $e) {
            \Log::error('Dashboard alerts error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $error = 'internal_error';
        }

        return response()->json([
            'critical' => array_slice($critical, 0, 10),
            'high' => array_slice($high, 0, 10),
            'medium' => array_slice($medium, 0, 10),
            'error' => $error,
        ]);
    }

    private function collectOutOfStock($subshopId)
    {
        $items = \App\Models\Item::where('subshop_id', $subshopId)
            ->with('itemBatches')
            ->get();
        $out = [];
        foreach ($items as $item) {
            $qty = (float) $item->itemBatches->sum('quantity');
            if ($qty <= 0) {
                $out[] = ['name' => $item->name];
            }
        }
        return $out;
    }

    private function collectLowStock($subshopId)
    {
        $items = \App\Models\Item::where('subshop_id', $subshopId)
            ->with('itemBatches')
            ->get();
        $low = [];
        foreach ($items as $item) {
            $qty = (float) $item->itemBatches->sum('quantity');
            if ($qty > 0 && $item->min_quantity && $qty <= (float)$item->min_quantity) {
                $low[] = ['name' => $item->name, 'quantity' => $qty, 'min' => (float)$item->min_quantity];
            }
        }
        return $low;
    }

    private function collectOutstandingSales($subshopId, $rangeStart = null, $rangeEnd = null)
    {
        $paymentsSub = \App\Models\Transaction::selectRaw('order_id, SUM(total_amount) as paid_total')
            ->where('transaction_type', 'payment')
            ->groupBy('order_id');

        $q = \App\Models\SalesOrders::where('subshop_id', $subshopId)
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.order_id', '=', 'sales_orders.id');
            })
            ->selectRaw('sales_orders.id as order_id, sales_orders.order_number as order_no, GREATEST(sales_orders.grand_total - COALESCE(pays.paid_total,0), 0) as balance');
        if ($rangeStart && $rangeEnd) {
            $q->whereBetween('sales_orders.created_at', [$rangeStart, $rangeEnd]);
        }
        $rows = $q->having('balance', '>', 0)
            ->orderByDesc('balance')
            ->limit(10)
            ->get();

        return $rows->map(function($r){
            return ['order_id' => $r->order_id, 'order_no' => $r->order_no, 'balance' => (float)$r->balance];
        })->all();
    }

    private function collectOutstandingPurchases($subshopId, $rangeStart = null, $rangeEnd = null)
    {
        try {
            if (!class_exists('App\\Models\\PurchaseOrders')) {
                return [];
            }
            $paymentsSub = \App\Models\Transaction::selectRaw('purchase_id, SUM(total_amount) as paid_total')
                ->where('transaction_type', 'payment')
                ->groupBy('purchase_id');

            $q = \App\Models\PurchaseOrders::where('subshop_id', $subshopId)
                ->leftJoinSub($paymentsSub, 'pays', function($join){
                    $join->on('pays.purchase_id', '=', 'purchase_orders.id');
                })
                ->selectRaw('purchase_orders.id, purchase_orders.reference as ref, GREATEST(purchase_orders.grand_total - COALESCE(pays.paid_total,0), 0) as balance');
            if ($rangeStart && $rangeEnd) {
                $q->whereBetween('purchase_orders.created_at', [$rangeStart, $rangeEnd]);
            }
            $rows = $q->having('balance', '>', 0)
                ->orderByDesc('balance')
                ->limit(10)
                ->get();

            return $rows->map(function($r){
                return ['id' => $r->id, 'ref' => $r->ref, 'balance' => (float)$r->balance];
            })->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Detailed outstanding sales (invoices) list for a subshop
     */
    private function getOutstandingSalesList($subshopId, $rangeStart = null, $rangeEnd = null)
    {
        $paymentsSub = \App\Models\Transaction::selectRaw('order_id, SUM(total_amount) as paid_total')
            ->where('transaction_type', 'payment')
            ->groupBy('order_id');

        $q = \App\Models\SalesOrders::with(['customer'])
            ->where('subshop_id', $subshopId)
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.order_id', '=', 'sales_orders.id');
            })
            ->select('sales_orders.*', \DB::raw('COALESCE(pays.paid_total,0) as paid_total'));
        if ($rangeStart && $rangeEnd) {
            $q->whereBetween('sales_orders.created_at', [$rangeStart, $rangeEnd]);
        }
        $rows = $q->get();

        $out = [];
        foreach ($rows as $o) {
            $paid = (float) $o->paid_total;
            $remain = max(0, (float)$o->grand_total - $paid);
            if ($remain > 0) {
                $out[] = [
                    'id' => (int) $o->id,
                    'order_no' => $o->order_no,
                    'date' => optional($o->created_at)->format('Y-m-d'),
                    'customer' => optional($o->customer)->name ?? '-',
                    'grand_total' => (float) $o->grand_total,
                    'paid_total' => $paid,
                    'remaining' => $remain,
                ];
            }
        }
        // Sort by remaining desc
        usort($out, function($a,$b){ return $b['remaining'] <=> $a['remaining']; });
        return array_slice($out, 0, 30);
    }

    /**
     * Detailed outstanding purchases list for a subshop
     */
    private function getOutstandingPurchasesList($subshopId, $rangeStart = null, $rangeEnd = null)
    {
        if (!class_exists('App\\Models\\PurchaseOrders')) { return []; }

        $paymentsSub = \App\Models\PurchasesTransactions::selectRaw('purchase_order_id, SUM(total_amount) as paid_total')
            ->where('transaction_type', 'payment')
            ->groupBy('purchase_order_id');

        $q = \App\Models\PurchaseOrders::with(['supplier'])
            ->where('subshop_id', $subshopId)
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.purchase_order_id', '=', 'purchase_orders.id');
            })
            ->select('purchase_orders.*', \DB::raw('COALESCE(pays.paid_total,0) as paid_total'));
        if ($rangeStart && $rangeEnd) {
            $q->whereBetween('purchase_orders.created_at', [$rangeStart, $rangeEnd]);
        }
        $rows = $q->get();

        $out = [];
        foreach ($rows as $o) {
            $paid = (float) $o->paid_total;
            $remain = max(0, (float)$o->grand_total - $paid);
            if ($remain > 0) {
                $out[] = [
                    'id' => (int) $o->id,
                    'order_no' => $o->order_no,
                    'date' => optional($o->created_at)->format('Y-m-d'),
                    'supplier' => optional($o->supplier)->name ?? '-',
                    'grand_total' => (float) $o->grand_total,
                    'paid_total' => $paid,
                    'remaining' => $remain,
                ];
            }
        }
        usort($out, function($a,$b){ return $b['remaining'] <=> $a['remaining']; });
        return array_slice($out, 0, 30);
    }

    private function collectSystemErrors()
    {
        // Placeholder: integrate with monitoring or database logs if available
        return [];
    }

    /**
     * Safely build a route URL if the named route exists; otherwise return fallback.
     */
    private function safeRoute(string $name, array $params = [], string $fallback = '#')
    {
        try {
            if (\Illuminate\Support\Facades\Route::has($name)) {
                return route($name, $params);
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $fallback;
    }

    /**
     * Analytics: Orders created per day for last 30 days (active subshop)
     */
    public function ordersDaily(Request $request)
    {
        $activeSubshopId = session('subshop_id');
        if (!$activeSubshopId) {
            return response()->json(['labels' => [], 'values' => []]);
        }

        $df = $request->query('date_from');
        $dt = $request->query('date_to');
        if ($df && $dt) {
            $start = \Carbon\Carbon::parse($df)->startOfDay();
            $end = \Carbon\Carbon::parse($dt)->endOfDay();
        } else {
            $days = (int) ($request->query('days', 30));
            $start = now()->subDays($days - 1)->startOfDay();
            $end = now()->endOfDay();
        }

        $raw = \App\Models\SalesOrders::where('subshop_id', $activeSubshopId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $values = [];
        $cursor = \Carbon\Carbon::parse($start);
        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $labels[] = $cursor->format('M j');
            $values[] = (int) ($raw[$dateStr] ?? 0);
            $cursor->addDay();
        }

        return response()->json(['labels' => $labels, 'values' => $values]);
    }

    /**
     * Analytics: Net payments vs refunds for last 14 days (active subshop)
     */
    public function netPaymentsRefunds(Request $request)
    {
        $activeSubshopId = session('subshop_id');
        if (!$activeSubshopId) {
            return response()->json(['labels' => [], 'payments' => [], 'refunds' => []]);
        }

        $df = $request->query('date_from');
        $dt = $request->query('date_to');
        if ($df && $dt) {
            $start = \Carbon\Carbon::parse($df)->startOfDay();
            $end = \Carbon\Carbon::parse($dt)->endOfDay();
        } else {
            $days = (int) ($request->query('days', 14));
            $start = now()->subDays($days - 1)->startOfDay();
            $end = now()->endOfDay();
        }

        $raw = \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->where('sales_orders.subshop_id', $activeSubshopId)
            ->where('transactions.transaction_type', 'payment')
            ->whereBetween('transactions.transaction_date', [$start, $end])
            ->selectRaw('DATE(transactions.transaction_date) as d, SUM(CASE WHEN total_amount >= 0 THEN total_amount ELSE 0 END) as pays, SUM(CASE WHEN total_amount < 0 THEN -total_amount ELSE 0 END) as refunds')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $labels = [];
        $payments = [];
        $refunds = [];
        $cursor = \Carbon\Carbon::parse($start);
        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $labels[] = $cursor->format('M j');
            $payments[] = isset($raw[$dateStr]) ? (float) $raw[$dateStr]['pays'] : 0;
            $refunds[] = isset($raw[$dateStr]) ? (float) $raw[$dateStr]['refunds'] : 0;
            $cursor->addDay();
        }

        return response()->json(['labels' => $labels, 'payments' => $payments, 'refunds' => $refunds]);
    }

    /**
     * Analytics: Average Order Value per day for last 30 days (active subshop)
     */
    public function aovDaily(Request $request)
    {
        $activeSubshopId = session('subshop_id');
        if (!$activeSubshopId) {
            return response()->json(['labels' => [], 'values' => []]);
        }

        $df = $request->query('date_from');
        $dt = $request->query('date_to');
        if ($df && $dt) {
            $start = \Carbon\Carbon::parse($df)->startOfDay();
            $end = \Carbon\Carbon::parse($dt)->endOfDay();
        } else {
            $days = (int) ($request->query('days', 30));
            $start = now()->subDays($days - 1)->startOfDay();
            $end = now()->endOfDay();
        }

        $raw = \App\Models\SalesOrders::where('subshop_id', $activeSubshopId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c, SUM(grand_total) as s')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $labels = [];
        $values = [];
        $cursor = \Carbon\Carbon::parse($start);
        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $labels[] = $cursor->format('M j');
            if (isset($raw[$dateStr]) && (int)$raw[$dateStr]['c'] > 0) {
                $values[] = (float) ($raw[$dateStr]['s'] / $raw[$dateStr]['c']);
            } else {
                $values[] = 0;
            }
            $cursor->addDay();
        }

        return response()->json(['labels' => $labels, 'values' => $values]);
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
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Get expiry alerts for the user's active subshop
     */
    private function getExpiryAlerts($user, $rangeStart = null, $rangeEnd = null)
    {
        // Get ACTIVE subshop ID from session
        $activeSubshopId = session('subshop_id');

        if (!$activeSubshopId) {
            return collect();
        }

        // Use only the active subshop for alerts
        $subshopIds = [$activeSubshopId];

        // Get expired batches (already expired)
        $expiredQuery = \App\Models\ItemBatch::with(['item'])
            ->whereHas('item', function($query) use ($subshopIds) {
                $query->whereIn('subshop_id', $subshopIds);
            })
            ->whereNotNull('expire_date')
            ->where('quantity', '>', 0)
            ->orderBy('expire_date', 'asc');
        if ($rangeStart && $rangeEnd) {
            $expiredQuery->whereBetween('expire_date', [$rangeStart, $rangeEnd]);
        } else {
            $expiredQuery->where('expire_date', '<', now());
        }
        $expiredBatches = $expiredQuery->get();

        // Get batches expiring soon (within 30 days)
        $expiringQuery = \App\Models\ItemBatch::with(['item'])
            ->whereHas('item', function($query) use ($subshopIds) {
                $query->whereIn('subshop_id', $subshopIds);
            })
            ->whereNotNull('expire_date')
            ->where('quantity', '>', 0)
            ->orderBy('expire_date', 'asc');
        if ($rangeStart && $rangeEnd) {
            $expiringQuery->whereBetween('expire_date', [$rangeStart, $rangeEnd]);
        } else {
            $expiringQuery->where('expire_date', '>=', now())
                ->where('expire_date', '<=', now()->addDays(30));
        }
        $expiringSoonBatches = $expiringQuery->get();

        $alerts = collect();

        // Add expired alerts
        foreach ($expiredBatches as $batch) {
            $alerts->push([
                'type' => 'expired',
                'priority' => 'critical',
                'title' => 'EXPIRED: ' . $batch->item->name,
                'message' => "Batch {$batch->batch_number} has expired! {$batch->quantity} {$batch->item->unit} remaining.",
                'batch_number' => $batch->batch_number,
                'item_name' => $batch->item->name,
                'quantity' => $batch->quantity,
                'unit' => $batch->item->unit,
                'expire_date' => $batch->expire_date,
                'days_overdue' => $batch->expire_date->diffInDays(now()),
                'subshop_name' => $batch->item->subshop->name,
                'action_url' => route('items.index', ['subshop_id' => $batch->item->subshop_id])
            ]);
        }

        // Add expiring soon alerts
        foreach ($expiringSoonBatches as $batch) {
            $daysRemaining = now()->diffInDays($batch->expire_date);
            $alerts->push([
                'type' => 'expiring_soon',
                'priority' => $daysRemaining <= 7 ? 'high' : 'medium',
                'title' => 'Expiring Soon: ' . $batch->item->name,
                'message' => "Batch {$batch->batch_number} expires in {$daysRemaining} days. {$batch->quantity} {$batch->item->unit} remaining.",
                'batch_number' => $batch->batch_number,
                'item_name' => $batch->item->name,
                'quantity' => $batch->quantity,
                'unit' => $batch->item->unit,
                'expire_date' => $batch->expire_date,
                'days_remaining' => $daysRemaining,
                'subshop_name' => $batch->item->subshop->name,
                'action_url' => route('items.index', ['subshop_id' => $batch->item->subshop_id])
            ]);
        }

        return $alerts->sortBy(function($alert) {
            // Sort by priority: critical first, then high, medium
            $priorityOrder = ['critical' => 1, 'high' => 2, 'medium' => 3];
            return $priorityOrder[$alert['priority']] ?? 99;
        })->take(10); // Limit to top 10 alerts
    }

    /**
     * Get subscription expiry alerts for shop owners
     */
    private function getSubscriptionExpiryAlerts($shop)
    {
        // Get subscriptions expiring soon (within 10 days)
        $expiringSubscriptions = \App\Models\Subscription::with(['plan'])
            ->where('shop_id', $shop->id)
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '>=', now())
            ->where('end_date', '<=', now()->addDays(10))
            ->orderBy('end_date', 'asc')
            ->get();

        // Get subscriptions already expired (end_date < now, excluding cancelled)
        $expiredSubscriptions = \App\Models\Subscription::with(['plan'])
            ->where('shop_id', $shop->id)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->where(function($q){
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['cancelled']);
            })
            ->orderBy('end_date', 'desc')
            ->take(5)
            ->get();

        $alerts = collect();

        // Add subscription expiry alerts (expiring soon)
        foreach ($expiringSubscriptions as $subscription) {
            $daysRemaining = now()->diffInDays($subscription->end_date, false);
            $alerts->push([
                'type' => 'subscription_expiring',
                'priority' => $daysRemaining <= 3 ? 'critical' : ($daysRemaining <= 7 ? 'high' : 'medium'),
                'title' => 'Subscription Expiring: ' . $subscription->plan->name,
                'message' => "Your {$subscription->plan->name} plan expires in {$daysRemaining} days on {$subscription->end_date->format('M j, Y')}. Renew now to avoid service interruption.",
                'plan_name' => $subscription->plan->name,
                'plan_price' => $subscription->plan->price,
                'plan_currency' => $subscription->plan->currency,
                'billing_cycle' => $subscription->plan->billing_cycle,
                'end_date' => $subscription->end_date,
                'days_remaining' => $daysRemaining,
                'shop_name' => $shop->name,
                'action_url' => route('configure.shop', ['id' => $shop->id]) . '#plan-management'
            ]);
        }

        // Add subscription expired alerts
        foreach ($expiredSubscriptions as $subscription) {
            $daysOverdue = $subscription->end_date->diffInDays(now());
            $alerts->push([
                'type' => 'subscription_expired',
                'priority' => 'critical',
                'title' => 'Subscription Expired: ' . ($subscription->plan->name ?? 'Plan'),
                'message' => "Your {$subscription->plan->name} plan expired {$daysOverdue} days ago on {$subscription->end_date->format('M j, Y')}. Renew to restore service.",
                'plan_name' => $subscription->plan->name ?? null,
                'plan_price' => $subscription->plan->price ?? null,
                'plan_currency' => $subscription->plan->currency ?? null,
                'billing_cycle' => $subscription->plan->billing_cycle ?? null,
                'end_date' => $subscription->end_date,
                'days_overdue' => $daysOverdue,
                'shop_name' => $shop->name,
                'action_url' => route('configure.shop', ['id' => $shop->id]) . '#plan-management'
            ]);
        }

        return $alerts->sortBy(function($alert) {
            // Sort by priority: critical first, then high, medium
            $priorityOrder = ['critical' => 1, 'high' => 2, 'medium' => 3];
            return $priorityOrder[$alert['priority']] ?? 99;
        })->take(5); // Limit to top 5 subscription alerts (mix of expiring and expired)
    }

    /**
     * Calculate KPI metrics for dashboard
     */
    private function calculateKPIs($user, $shop, $dateFrom = null, $dateTo = null)
    {
        // Default to today's range if dates are not provided (KPIs should be meaningful by default)
        if (!$dateFrom || !$dateTo) {
            $dateFrom = now()->toDateString();
            $dateTo = now()->toDateString();
        }
        // Get ACTIVE subshop ID from session (set by EnsureSubshopAccess middleware)
        $activeSubshopId = session('subshop_id');

        if (!$activeSubshopId) {
            // Fallback: get the first accessible subshop if no active subshop is set
            $accessibleSubshops = $this->getUserSubshopIds($user, $shop);
            $activeSubshopId = !empty($accessibleSubshops) ? $accessibleSubshops[0] : null;
        }

        if (!$activeSubshopId) {
            // No subshop access - return zeros
            return $this->getEmptyKPIs();
        }

        // Use only the active subshop for calculations
        $subshopIds = [$activeSubshopId];

        // Optional custom range
        $rangeStart = ($dateFrom && $dateTo) ? \Carbon\Carbon::parse($dateFrom)->startOfDay() : null;
        $rangeEnd = ($dateFrom && $dateTo) ? \Carbon\Carbon::parse($dateTo)->endOfDay() : null;

        // Payments collected (today or selected period)
        if ($rangeStart && $rangeEnd) {
            $todayRevenue = (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
                ->whereIn('sales_orders.subshop_id', $subshopIds)
                ->where('transactions.transaction_type', 'payment')
                ->whereBetween('transactions.transaction_date', [$rangeStart, $rangeEnd])
                ->sum('transactions.total_amount');
            $yesterdayRevenue = 0;
            $revenueChange = 0;
        } else {
            $todayRevenue = $this->calculateTodayPaymentsCollected($subshopIds);
            $yesterdayRevenue = $this->calculateYesterdayPaymentsCollected($subshopIds);
            $revenueChange = $this->calculatePercentageChange($todayRevenue, $yesterdayRevenue);
        }

        // Monthly revenue
        $monthlyRevenue = $this->calculateMonthlyRevenue($subshopIds);
        $lastMonthRevenue = $this->calculateLastMonthRevenue($subshopIds);
        $monthlyRevenueChange = $this->calculatePercentageChange($monthlyRevenue, $lastMonthRevenue);

        // Inventory value
        $inventoryValue = $this->calculateInventoryValue($subshopIds);

        // Low stock items count
        $lowStockCount = $this->calculateLowStockCount($subshopIds);

        // Orders count (today or selected period)
        if ($rangeStart && $rangeEnd) {
            $todaySalesCount = (int) \App\Models\SalesOrders::whereIn('subshop_id', $subshopIds)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->count();
            $yesterdaySalesCount = 0;
            $salesCountChange = 0;
        } else {
            $todaySalesCount = $this->calculateTodaySalesCount($subshopIds);
            $yesterdaySalesCount = $this->calculateYesterdaySalesCount($subshopIds);
            $salesCountChange = $this->calculatePercentageChange($todaySalesCount, $yesterdaySalesCount);
        }

        // Outstanding receivables (unpaid portions of sales invoices)
        $outstandingReceivables = $this->calculateOutstandingReceivables($subshopIds);

        // Outstanding payables (unpaid portions of purchase orders)
        $outstandingPayables = $this->calculateOutstandingPayables($subshopIds);

        // Net payments (today or selected period)
        if ($rangeStart && $rangeEnd) {
            $netPaymentsToday = (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
                ->whereIn('sales_orders.subshop_id', $subshopIds)
                ->where('transactions.transaction_type', 'payment')
                ->whereBetween('transactions.transaction_date', [$rangeStart, $rangeEnd])
                ->sum('transactions.total_amount');
            $netPaymentsYesterday = 0;
            $netPaymentsChange = 0;
        } else {
            $netPaymentsToday = $this->calculateNetPaymentsToday($subshopIds);
            $netPaymentsYesterday = $this->calculateNetPaymentsYesterday($subshopIds);
            $netPaymentsChange = $this->calculatePercentageChange($netPaymentsToday, $netPaymentsYesterday);
        }

        // Average order value (today or selected period)
        if ($rangeStart && $rangeEnd) {
            $ordersAgg = \App\Models\SalesOrders::whereIn('subshop_id', $subshopIds)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->selectRaw('COUNT(*) as c, SUM(grand_total) as s')
                ->first();
            $c = (int) ($ordersAgg->c ?? 0); $s = (float) ($ordersAgg->s ?? 0);
            $avgOrderValueToday = $c > 0 ? ($s / $c) : 0;
            $avgOrderValueYesterday = 0;
            $avgOrderValueChange = 0;
        } else {
            $avgOrderValueToday = $this->calculateAverageOrderValueToday($subshopIds);
            $avgOrderValueYesterday = $this->calculateAverageOrderValueYesterday($subshopIds);
            $avgOrderValueChange = $this->calculatePercentageChange($avgOrderValueToday, $avgOrderValueYesterday);
        }

        // Refunds (today or selected period) as absolute amount
        if ($rangeStart && $rangeEnd) {
            $refundsToday = (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
                ->whereIn('sales_orders.subshop_id', $subshopIds)
                ->where('transactions.transaction_type', 'payment')
                ->whereBetween('transactions.transaction_date', [$rangeStart, $rangeEnd])
                ->where('transactions.total_amount', '<', 0)
                ->sum(\DB::raw('ABS(transactions.total_amount)'));
            $refundsYesterday = 0;
            $refundsChange = 0;
        } else {
            $refundsToday = $this->calculateRefundsTodayAmount($subshopIds);
            $refundsYesterday = $this->calculateRefundsYesterdayAmount($subshopIds);
            $refundsChange = $this->calculatePercentageChange($refundsToday, $refundsYesterday);
        }

        // Expenses total (approved) for selected period or today
        if ($rangeStart && $rangeEnd) {
            $expensesTotal = (float) \App\Models\Expenses::whereIn('subshop_id', $subshopIds)
                ->whereIn('status', ['approved','pending'])
                ->where(function($q) use ($dateFrom, $dateTo) {
                    $q->whereBetween(\DB::raw('DATE(expense_date)'), [$dateFrom, $dateTo])
                      ->orWhereBetween(\DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);
                })
                ->sum('amount');
        } else {
            $today = now()->toDateString();
            $expensesTotal = (float) \App\Models\Expenses::whereIn('subshop_id', $subshopIds)
                ->whereIn('status', ['approved','pending'])
                ->where(function($q) use ($today){
                    $q->whereDate('expense_date', $today)
                      ->orWhereDate('created_at', $today);
                })
                ->sum('amount');
        }

        // Write-offs total (approved) for selected period or today
        if ($rangeStart && $rangeEnd) {
            $writeoffsTotal = (float) \App\Models\WriteOff::whereIn('subshop_id', $subshopIds)
                ->whereIn('status', ['approved','pending'])
                ->where(function($q) use ($dateFrom, $dateTo){
                    $q->whereBetween(\DB::raw('DATE(write_off_date)'), [$dateFrom, $dateTo])
                      ->orWhereBetween(\DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);
                })
                ->sum('total_value');
        } else {
            $today = now()->toDateString();
            $writeoffsTotal = (float) \App\Models\WriteOff::whereIn('subshop_id', $subshopIds)
                ->whereIn('status', ['approved','pending'])
                ->where(function($q) use ($today){
                    $q->whereDate('write_off_date', $today)
                      ->orWhereDate('created_at', $today);
                })
                ->sum('total_value');
        }

        // Temporary diagnostics for KPIs
        try {
            $expensesCount = 0;
            $writeoffsCount = 0;
            if ($rangeStart && $rangeEnd) {
                $expensesCount = (int) \App\Models\Expenses::whereIn('subshop_id', $subshopIds)
                    ->whereIn('status', ['approved','pending'])
                    ->where(function($q) use ($dateFrom, $dateTo) {
                        $q->whereBetween(\DB::raw('DATE(expense_date)'), [$dateFrom, $dateTo])
                          ->orWhereBetween(\DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);
                    })->count();
                $writeoffsCount = (int) \App\Models\WriteOff::whereIn('subshop_id', $subshopIds)
                    ->whereIn('status', ['approved','pending'])
                    ->where(function($q) use ($dateFrom, $dateTo){
                        $q->whereBetween(\DB::raw('DATE(write_off_date)'), [$dateFrom, $dateTo])
                          ->orWhereBetween(\DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);
                    })->count();
            } else {
                $today = now()->toDateString();
                $expensesCount = (int) \App\Models\Expenses::whereIn('subshop_id', $subshopIds)
                    ->whereIn('status', ['approved','pending'])
                    ->where(function($q) use ($today){
                        $q->whereDate('expense_date', $today)
                          ->orWhereDate('created_at', $today);
                    })->count();
                $writeoffsCount = (int) \App\Models\WriteOff::whereIn('subshop_id', $subshopIds)
                    ->whereIn('status', ['approved','pending'])
                    ->where(function($q) use ($today){
                        $q->whereDate('write_off_date', $today)
                          ->orWhereDate('created_at', $today);
                    })->count();
            }
            \Log::error('Dashboard KPI diagnostics', [
                'kpi_debug' => true,
                'subshop_ids' => $subshopIds,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'expenses_total' => $expensesTotal,
                'expenses_count' => $expensesCount,
                'writeoffs_total' => $writeoffsTotal,
                'writeoffs_count' => $writeoffsCount,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('KPI diagnostics failed: '.$e->getMessage());
        }

        // Active subscriptions (for shop owners)
        $activeSubscriptions = 0;
        if ($shop) {
            $activeSubscriptions = $shop->subscriptions()->where('status', 'active')->count();
        }

        return [
            'today_revenue' => [
                'value' => $todayRevenue,
                'change' => $revenueChange,
                'change_type' => $revenueChange >= 0 ? 'positive' : 'negative',
                'formatted' => 'TZS ' . number_format($todayRevenue, 0),
                'label' => $rangeStart && $rangeEnd ? 'Payments (Selected Period)' : 'Payments Collected Today'
            ],
            'monthly_revenue' => [
                'value' => $monthlyRevenue,
                'change' => $monthlyRevenueChange,
                'change_type' => $monthlyRevenueChange >= 0 ? 'positive' : 'negative',
                'formatted' => 'TZS ' . number_format($monthlyRevenue, 0),
                'label' => 'This Month'
            ],
            'inventory_value' => [
                'value' => $inventoryValue,
                'change' => 0, // Could compare to previous period
                'change_type' => 'neutral',
                'formatted' => 'TZS ' . number_format($inventoryValue, 0),
                'label' => 'Inventory Value'
            ],
            'low_stock_items' => [
                'value' => $lowStockCount,
                'change' => 0, // Could track changes over time
                'change_type' => 'neutral',
                'formatted' => number_format($lowStockCount),
                'label' => 'Low Stock Items'
            ],
            'today_sales' => [
                'value' => $todaySalesCount,
                'change' => $salesCountChange,
                'change_type' => $salesCountChange >= 0 ? 'positive' : 'negative',
                'formatted' => number_format($todaySalesCount),
                'label' => $rangeStart && $rangeEnd ? 'Orders (Selected Period)' : 'Orders Created Today'
            ],
            'active_subscriptions' => [
                'value' => $activeSubscriptions,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => number_format($activeSubscriptions),
                'label' => 'Active Plans'
            ],
            'outstanding_receivables' => [
                'value' => $outstandingReceivables,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS ' . number_format($outstandingReceivables, 0),
                'label' => 'Outstanding Receivables'
            ],
            'outstanding_payables' => [
                'value' => $outstandingPayables,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS ' . number_format($outstandingPayables, 0),
                'label' => 'Outstanding Payables'
            ],
            'net_payments_today' => [
                'value' => $netPaymentsToday,
                'change' => $netPaymentsChange,
                'change_type' => $netPaymentsChange >= 0 ? 'positive' : 'negative',
                'formatted' => 'TZS ' . number_format($netPaymentsToday, 0),
                'label' => $rangeStart && $rangeEnd ? 'Net Payments (Selected Period)' : 'Net Payments Today'
            ],
            'avg_order_value_today' => [
                'value' => $avgOrderValueToday,
                'change' => $avgOrderValueChange,
                'change_type' => $avgOrderValueChange >= 0 ? 'positive' : 'negative',
                'formatted' => 'TZS ' . number_format($avgOrderValueToday, 0),
                'label' => $rangeStart && $rangeEnd ? 'Average Order Value (Selected Period)' : 'Average Order Value (Today)'
            ],
            'refunds_today' => [
                'value' => $refundsToday,
                'change' => $refundsChange,
                'change_type' => $refundsChange >= 0 ? 'positive' : 'negative',
                'formatted' => 'TZS ' . number_format($refundsToday, 0),
                'label' => $rangeStart && $rangeEnd ? 'Returns/Refunds (Selected Period)' : 'Returns/Refunds (Today)'
            ],
            'expenses_total' => [
                'value' => $expensesTotal,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS ' . number_format($expensesTotal, 0),
                'label' => $rangeStart && $rangeEnd ? 'Expenses (Selected Period)' : 'Expenses (Today)'
            ],
            'writeoffs_total' => [
                'value' => $writeoffsTotal,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS ' . number_format($writeoffsTotal, 0),
                'label' => $rangeStart && $rangeEnd ? 'Write-offs (Selected Period)' : 'Write-offs (Today)'
            ]
        ];
    }

    /**
     * Return empty KPI data when no subshop access
     */
    private function getEmptyKPIs()
    {
        return [
            'today_revenue' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS 0',
                'label' => 'Payments Collected Today'
            ],
            'monthly_revenue' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS 0',
                'label' => 'This Month'
            ],
            'inventory_value' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS 0',
                'label' => 'Inventory Value'
            ],
            'low_stock_items' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => '0',
                'label' => 'Low Stock Items'
            ],
            'today_sales' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => '0',
                'label' => 'Orders Created Today'
            ],
            'active_subscriptions' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => '0',
                'label' => 'Active Plans'
            ],
            'outstanding_receivables' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS 0',
                'label' => 'Outstanding Receivables'
            ],
            'net_payments_today' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS 0',
                'label' => 'Net Payments Today'
            ],
            'avg_order_value_today' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS 0',
                'label' => 'Average Order Value (Today)'
            ],
            'refunds_today' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS 0',
                'label' => 'Returns/Refunds (Today)'
            ],
            'expenses_total' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS 0',
                'label' => 'Expenses (Today)'
            ],
            'writeoffs_total' => [
                'value' => 0,
                'change' => 0,
                'change_type' => 'neutral',
                'formatted' => 'TZS 0',
                'label' => 'Write-offs (Today)'
            ]
        ];
    }

    /**
     * Get subshop IDs user has access to
     */
    private function getUserSubshopIds($user, $shop)
    {
        $subshopIds = [];

        // If user owns a shop, get all their subshops
        if ($shop) {
            $subshopIds = $shop->subshops->pluck('id')->toArray();
        }

        // If user is assigned to specific subshops, get those
        if ($user->subshops()->exists()) {
            $assignedSubshopIds = $user->subshops->pluck('id')->toArray();
            $subshopIds = array_merge($subshopIds, $assignedSubshopIds);
        }

        return array_unique($subshopIds);
    }

    /**
     * Calculate recent revenue (last 7 days)
     */
    private function calculateTodayRevenue($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        return (float) \App\Models\SalesOrders::whereIn('subshop_id', $subshopIds)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->sum('grand_total');
    }

    /**
     * Calculate last week's revenue (8-14 days ago)
     */
    private function calculateYesterdayRevenue($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        $yStart = now()->subDay()->startOfDay();
        $yEnd = now()->subDay()->endOfDay();
        return (float) \App\Models\SalesOrders::whereIn('subshop_id', $subshopIds)
            ->whereBetween('created_at', [$yStart, $yEnd])
            ->sum('grand_total');
    }

    /**
     * Calculate monthly revenue
     */
    private function calculateMonthlyRevenue($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        return (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->whereIn('sales_orders.subshop_id', $subshopIds)
            ->where('transactions.transaction_type', 'payment')
            ->whereMonth('transactions.transaction_date', now()->month)
            ->whereYear('transactions.transaction_date', now()->year)
            ->sum('transactions.total_amount');
    }

    /**
     * Sum of outstanding receivables (unpaid amounts) for orders in subshops
     */
    private function calculateOutstandingReceivables($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        $paymentsSub = \App\Models\Transaction::selectRaw('order_id, SUM(total_amount) as paid_total')
            ->where('transaction_type', 'payment')
            ->groupBy('order_id');

        return (float) \App\Models\SalesOrders::whereIn('subshop_id', $subshopIds)
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.order_id', '=', 'sales_orders.id');
            })
            ->selectRaw('SUM(GREATEST(sales_orders.grand_total - COALESCE(pays.paid_total,0), 0)) as receivables')
            ->value('receivables');
    }

    /**
     * Sum of outstanding payables (unpaid amounts) for purchase orders in subshops
     */
    private function calculateOutstandingPayables($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        if (!class_exists('App\\Models\\PurchaseOrders')) return 0;

        // Aggregate payments per purchase order
        $paymentsSub = \App\Models\PurchasesTransactions::selectRaw('purchase_order_id, SUM(total_amount) as paid_total')
            ->where('transaction_type', 'payment')
            ->groupBy('purchase_order_id');

        return (float) \App\Models\PurchaseOrders::whereIn('subshop_id', $subshopIds)
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.purchase_order_id', '=', 'purchase_orders.id');
            })
            ->selectRaw('SUM(GREATEST(purchase_orders.grand_total - COALESCE(pays.paid_total,0), 0)) as payables')
            ->value('payables');
    }

    /**
     * Net payments today (payments minus refunds)
     */
    private function calculateNetPaymentsToday($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        return (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->whereIn('sales_orders.subshop_id', $subshopIds)
            ->where('transactions.transaction_type', 'payment')
            ->whereDate('transactions.transaction_date', now()->toDateString())
            ->sum('transactions.total_amount');
    }

    private function calculateNetPaymentsYesterday($subshopIds)
    {
        if (empty($subshopIds)) return 0;
        $yesterday = now()->subDay()->toDateString();
        return (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->whereIn('sales_orders.subshop_id', $subshopIds)
            ->where('transactions.transaction_type', 'payment')
            ->whereDate('transactions.transaction_date', $yesterday)
            ->sum('transactions.total_amount');
    }

    /**
     * Average order value (today)
     */
    private function calculateAverageOrderValueToday($subshopIds)
    {
        if (empty($subshopIds)) return 0;
        $query = \App\Models\SalesOrders::whereIn('subshop_id', $subshopIds)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);
        $count = (int) $query->count();
        if ($count === 0) return 0;
        $sum = (float) $query->sum('grand_total');
        return $count > 0 ? ($sum / $count) : 0;
    }

    private function calculateAverageOrderValueYesterday($subshopIds)
    {
        if (empty($subshopIds)) return 0;
        $yStart = now()->subDay()->startOfDay();
        $yEnd = now()->subDay()->endOfDay();
        $query = \App\Models\SalesOrders::whereIn('subshop_id', $subshopIds)
            ->whereBetween('created_at', [$yStart, $yEnd]);
        $count = (int) $query->count();
        if ($count === 0) return 0;
        $sum = (float) $query->sum('grand_total');
        return $count > 0 ? ($sum / $count) : 0;
    }

    /**
     * Refunds amount (absolute) today
     */
    private function calculateRefundsTodayAmount($subshopIds)
    {
        if (empty($subshopIds)) return 0;
        $sum = (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->whereIn('sales_orders.subshop_id', $subshopIds)
            ->where('transactions.transaction_type', 'payment')
            ->whereDate('transactions.transaction_date', now()->toDateString())
            ->where('transactions.total_amount', '<', 0)
            ->sum('transactions.total_amount');
        return abs($sum);
    }

    private function calculateRefundsYesterdayAmount($subshopIds)
    {
        if (empty($subshopIds)) return 0;
        $yesterday = now()->subDay()->toDateString();
        $sum = (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->whereIn('sales_orders.subshop_id', $subshopIds)
            ->where('transactions.transaction_type', 'payment')
            ->whereDate('transactions.transaction_date', $yesterday)
            ->where('transactions.total_amount', '<', 0)
            ->sum('transactions.total_amount');
        return abs($sum);
    }

    /**
     * Calculate last month's revenue
     */
    private function calculateLastMonthRevenue($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        $dt = now()->subMonth();
        return (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->whereIn('sales_orders.subshop_id', $subshopIds)
            ->where('transactions.transaction_type', 'payment')
            ->whereMonth('transactions.transaction_date', $dt->month)
            ->whereYear('transactions.transaction_date', $dt->year)
            ->sum('transactions.total_amount');
    }

    /**
     * Calculate inventory value
     */
    private function calculateInventoryValue($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        // Sum over batches: quantity * selling_price for items in the given subshops
        return (float) \App\Models\ItemBatch::join('items', 'item_batches.item_id', '=', 'items.id')
            ->whereIn('items.subshop_id', $subshopIds)
            ->sum(\DB::raw('(item_batches.quantity * item_batches.selling_price)'));
    }

    /**
     * Calculate low stock items count
     */
    private function calculateLowStockCount($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        return \App\Models\Item::whereIn('subshop_id', $subshopIds)
            ->with('itemBatches')
            ->get()
            ->filter(function ($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                return $totalQuantity > 0 && $item->min_quantity && $totalQuantity <= $item->min_quantity;
            })
            ->count();
    }

    /**
     * Calculate recent sales count (last 7 days)
     */
    private function calculateTodaySalesCount($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        return (int) \App\Models\SalesOrders::whereIn('subshop_id', $subshopIds)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
    }

    /**
     * Calculate last week's sales count (8-14 days ago)
     */
    private function calculateYesterdaySalesCount($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        $yStart = now()->subDay()->startOfDay();
        $yEnd = now()->subDay()->endOfDay();
        return (int) \App\Models\SalesOrders::whereIn('subshop_id', $subshopIds)
            ->whereBetween('created_at', [$yStart, $yEnd])
            ->count();
    }

    /**
     * Payments collected today (sum of payment transactions for orders in subshop)
     */
    private function calculateTodayPaymentsCollected($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        return (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->whereIn('sales_orders.subshop_id', $subshopIds)
            ->where('transactions.transaction_type', 'payment')
            ->whereDate('transactions.transaction_date', now()->toDateString())
            ->sum('transactions.total_amount');
    }

    /**
     * Payments collected yesterday (sum of payment transactions for orders in subshop)
     */
    private function calculateYesterdayPaymentsCollected($subshopIds)
    {
        if (empty($subshopIds)) return 0;

        $yesterday = now()->subDay()->toDateString();
        return (float) \App\Models\Transaction::join('sales_orders', 'transactions.order_id', '=', 'sales_orders.id')
            ->whereIn('sales_orders.subshop_id', $subshopIds)
            ->where('transactions.transaction_type', 'payment')
            ->whereDate('transactions.transaction_date', $yesterday)
            ->sum('transactions.total_amount');
    }
    /**
     * Calculate percentage change
     */
    private function calculatePercentageChange($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
