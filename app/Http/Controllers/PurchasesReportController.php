<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SubShop;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericArrayExport;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchasesReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) { abort(403); }

        // Determine shop context and accessible subshops (mirror SalesReportController)
        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) { abort(403, 'No shop found for user'); }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        // Subshop filter
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

        // Query params
        $q = $request->query('q');
        $sort = $request->query('sort', 'date_desc'); // date_desc|date_asc|grand_desc|grand_asc|paid_desc|paid_asc|remain_desc|remain_asc|net_desc|net_asc

        // Base purchases query (purchase orders)
        $ordersBase = DB::table('purchase_orders as po')
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereDate('po.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('po.created_at', '<=', $dateTo->toDateString())
            ->when($q, function($query) use ($q) {
                $query->where(function($qq) use ($q){
                    $qq->where('po.order_no', 'like', "%{$q}%");
                });
            });

        $orders = (clone $ordersBase)->count('po.id');
        $sumGrand = (float) ((clone $ordersBase)->sum('po.grand_total'));
        $sumVat = (float) ((clone $ordersBase)->sum('po.vat_total'));
        $sumDiscount = (float) ((clone $ordersBase)->sum('po.discount_total'));

        // Payments aggregation for outstanding A/P
        $paymentsSub = DB::table('purchases_transactions as pt')
            ->selectRaw('pt.purchase_order_id, SUM(pt.total_amount) as paid_total')
            ->where('pt.transaction_type', 'payment')
            ->groupBy('pt.purchase_order_id');

        $paidAndOutstanding = DB::table('purchase_orders as po')
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.purchase_order_id', '=', 'po.id');
            })
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereDate('po.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('po.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(COALESCE(pays.paid_total,0)) as total_paid')
            ->selectRaw('SUM(CASE WHEN (po.grand_total - COALESCE(pays.paid_total,0)) > 0 THEN (po.grand_total - COALESCE(pays.paid_total,0)) ELSE 0 END) as outstanding')
            ->first();

        $totalPaid = (float) ($paidAndOutstanding->total_paid ?? 0);
        $outstandingAP = (float) ($paidAndOutstanding->outstanding ?? 0);

        // KPIs (6)
        $apv = $orders > 0 ? round($sumGrand / $orders, 2) : 0.0; // Average Purchase Value

        $kpi = [
            'total_purchases' => $sumGrand,
            'orders' => $orders,
            'apv' => $apv,
            'taxes' => $sumVat,
            'discounts' => $sumDiscount,
            'outstanding_ap' => $outstandingAP,
        ];

        // Purchases list with payments (paginated)
        $paymentsSubList = DB::table('purchases_transactions as pt')
            ->selectRaw('pt.purchase_order_id, SUM(pt.total_amount) as paid_total')
            ->where('pt.transaction_type', 'payment')
            ->groupBy('pt.purchase_order_id');

        $refundsSubList = DB::table('purchases_transactions as rt')
            ->selectRaw('rt.purchase_order_id, SUM(CASE WHEN rt.total_amount < 0 THEN -rt.total_amount ELSE 0 END) as refunds_total')
            ->where('rt.transaction_type', 'payment')
            ->groupBy('rt.purchase_order_id');

        $returnsSubList = DB::table('purchase_returns as pr')
            ->selectRaw('pr.purchase_order_id, SUM(COALESCE(pr.line_total,0)) as returns_total')
            ->groupBy('pr.purchase_order_id');

        $listBase = DB::table('purchase_orders as po')
            ->leftJoinSub($paymentsSubList, 'pays', function($join){
                $join->on('pays.purchase_order_id', '=', 'po.id');
            })
            ->leftJoinSub($refundsSubList, 'refs', function($join){
                $join->on('refs.purchase_order_id', '=', 'po.id');
            })
            ->leftJoinSub($returnsSubList, 'rtns', function($join){
                $join->on('rtns.purchase_order_id', '=', 'po.id');
            })
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('sub_shops as ss', 'ss.id', '=', 'po.subshop_id')
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereDate('po.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('po.created_at', '<=', $dateTo->toDateString())
            ->when($q, function($query) use ($q){
                $query->where(function($qq) use ($q){
                    $qq->where('po.order_no', 'like', "%{$q}%")
                       ->orWhere('s.name', 'like', "%{$q}%");
                });
            })
            ->select([
                'po.id', 'po.order_no', 'po.created_at', 'po.subtotal', 'po.vat_total', 'po.discount_total', 'po.grand_total', 'po.status', 'po.supplier_id',
                DB::raw('COALESCE(pays.paid_total,0) as paid_total'),
                DB::raw('COALESCE(refs.refunds_total,0) as refunds_total'),
                DB::raw('COALESCE(rtns.returns_total,0) as returns_total'),
                DB::raw('(po.grand_total - COALESCE(rtns.returns_total,0)) as net_spend'),
                DB::raw('GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0) as net_paid'),
                DB::raw('GREATEST((po.grand_total - COALESCE(rtns.returns_total,0)) - GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0), 0) as net_remaining'),
                DB::raw('(po.grand_total - COALESCE(pays.paid_total,0)) as remaining_total'),
                DB::raw('COALESCE(s.name, "-") as supplier_name'),
                DB::raw('COALESCE(ss.name, "-") as subshop_name'),
            ])
            ;

        // Sorting
        switch ($sort) {
            case 'date_asc': $listBase->orderBy('po.created_at', 'asc'); break;
            case 'grand_desc': $listBase->orderBy('po.grand_total', 'desc'); break;
            case 'grand_asc': $listBase->orderBy('po.grand_total', 'asc'); break;
            case 'paid_desc': $listBase->orderBy(DB::raw('COALESCE(pays.paid_total,0)'), 'desc'); break;
            case 'paid_asc': $listBase->orderBy(DB::raw('COALESCE(pays.paid_total,0)'), 'asc'); break;
            case 'remain_desc': $listBase->orderBy(DB::raw('GREATEST((po.grand_total - COALESCE(rtns.returns_total,0)) - GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0), 0)'), 'desc'); break;
            case 'remain_asc': $listBase->orderBy(DB::raw('GREATEST((po.grand_total - COALESCE(rtns.returns_total,0)) - GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0), 0)'), 'asc'); break;
            case 'net_desc': $listBase->orderBy(DB::raw('(po.grand_total - COALESCE(rtns.returns_total,0))'), 'desc'); break;
            case 'net_asc': $listBase->orderBy(DB::raw('(po.grand_total - COALESCE(rtns.returns_total,0))'), 'asc'); break;
            default: $listBase->orderBy('po.created_at', 'desc');
        }

        $purchasesList = $listBase->paginate(15)->withQueryString();

        // Overall aggregates for current filter
        $agg = DB::table('purchase_orders as po')
            ->leftJoinSub($paymentsSubList, 'pays', function($join){
                $join->on('pays.purchase_order_id', '=', 'po.id');
            })
            ->leftJoinSub($refundsSubList, 'refs', function($join){
                $join->on('refs.purchase_order_id', '=', 'po.id');
            })
            ->leftJoinSub($returnsSubList, 'rtns', function($join){
                $join->on('rtns.purchase_order_id', '=', 'po.id');
            })
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereDate('po.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('po.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(po.grand_total) as sum_grand')
            ->selectRaw('SUM(COALESCE(pays.paid_total,0)) as sum_paid')
            ->selectRaw('SUM(COALESCE(refs.refunds_total,0)) as sum_refunds')
            ->selectRaw('SUM(COALESCE(rtns.returns_total,0)) as sum_returns')
            ->selectRaw('SUM(GREATEST((po.grand_total - COALESCE(rtns.returns_total,0)) - GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0), 0)) as sum_net_remaining')
            ->selectRaw('SUM((po.grand_total - COALESCE(rtns.returns_total,0))) as sum_net_spend')
            ->selectRaw('SUM(GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0)) as sum_net_paid')
            ->first();

        $overallTotals = [
            'grand' => (float)($agg->sum_grand ?? 0),
            'paid' => (float)($agg->sum_paid ?? 0),
            'refunds' => (float)($agg->sum_refunds ?? 0),
            'returns' => (float)($agg->sum_returns ?? 0),
            'net_spend' => (float)($agg->sum_net_spend ?? 0),
            'net_paid' => (float)($agg->sum_net_paid ?? 0),
            'remaining' => (float)($agg->sum_net_remaining ?? 0),
        ];

        // Current page totals
        $pageTotals = [
            'grand' => (float) $purchasesList->sum('grand_total'),
            'paid' => (float) $purchasesList->sum('paid_total'),
            'refunds' => (float) $purchasesList->sum('refunds_total'),
            'returns' => (float) $purchasesList->sum('returns_total'),
            'net_spend' => (float) $purchasesList->sum('net_spend'),
            'net_paid' => (float) $purchasesList->sum('net_paid'),
            'remaining' => (float) $purchasesList->sum('net_remaining'),
        ];

        return view('reports.purchases_report', [
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'kpi' => $kpi,
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'purchasesList' => $purchasesList,
            'overallTotals' => $overallTotals,
            'pageTotals' => $pageTotals,
            'q' => $q,
            'sort' => $sort,
            'exportUrl' => route('reports.purchases.export', [
                'format' => 'xlsx',
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_id' => $subshopId,
            ]),
            'pdfUrl' => route('reports.purchases.export', [
                'format' => 'pdf',
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_id' => $subshopId,
            ]),
            'csvUrl' => route('reports.purchases.export', [
                'format' => 'csv',
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_id' => $subshopId,
            ]),
            // Table export (current table with net metrics)
            'tableExportXlsxUrl' => route('reports.purchases.export', [
                'format' => 'xlsx',
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_id' => $subshopId,
                'q' => $q,
                'sort' => $sort,
                'scope' => 'table',
            ]),
            'tableExportCsvUrl' => route('reports.purchases.export', [
                'format' => 'csv',
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_id' => $subshopId,
                'q' => $q,
                'sort' => $sort,
                'scope' => 'table',
            ]),
            'tableExportPdfUrl' => route('reports.purchases.export', [
                'format' => 'pdf',
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'subshop_id' => $subshopId,
                'q' => $q,
                'sort' => $sort,
                'scope' => 'table',
            ]),
        ]);
    }

    /**
     * Export purchases report summary
     */
    public function export(Request $request, $format = 'xlsx')
    {
        $user = Auth::user();
        if (!$user) { abort(403); }

        // Filters
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();
        $subshopId = $request->integer('subshop_id');

        // Shop context & access
        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) { abort(403, 'No shop found for user'); }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        if ($subshopId) {
            if (!in_array($subshopId, $accessibleSubshopIds, true)) {
                abort(403, 'You do not have access to this subshop');
            }
            $subshopFilter = [$subshopId];
            $subshopName = optional($allSubshops->firstWhere('id', $subshopId))->name;
        } else {
            $subshopFilter = $accessibleSubshopIds ?: [-1];
            $subshopName = null;
        }

        // Aggregations (same as index)
        $ordersBase = \DB::table('purchase_orders as po')
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereDate('po.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('po.created_at', '<=', $dateTo->toDateString());

        $orders = (clone $ordersBase)->count('po.id');
        $sumGrand = (float) ((clone $ordersBase)->sum('po.grand_total'));
        $sumVat = (float) ((clone $ordersBase)->sum('po.vat_total'));
        $sumDiscount = (float) ((clone $ordersBase)->sum('po.discount_total'));

        $paymentsSub = \DB::table('purchases_transactions as pt')
            ->selectRaw('pt.purchase_order_id, SUM(pt.total_amount) as paid_total')
            ->where('pt.transaction_type', 'payment')
            ->groupBy('pt.purchase_order_id');

        $paidAndOutstanding = \DB::table('purchase_orders as po')
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.purchase_order_id', '=', 'po.id');
            })
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereDate('po.created_at', '>=', $dateFrom->toDateString())
            ->whereDate('po.created_at', '<=', $dateTo->toDateString())
            ->selectRaw('SUM(COALESCE(pays.paid_total,0)) as total_paid')
            ->selectRaw('SUM(CASE WHEN (po.grand_total - COALESCE(pays.paid_total,0)) > 0 THEN (po.grand_total - COALESCE(pays.paid_total,0)) ELSE 0 END) as outstanding')
            ->first();

        $totalPaid = (float) ($paidAndOutstanding->total_paid ?? 0);
        $outstandingAP = (float) ($paidAndOutstanding->outstanding ?? 0);
        $apv = $orders > 0 ? round($sumGrand / $orders, 2) : 0.0;

        $summaryRows = [
            ['Metric' => 'Total Purchases', 'Value' => $sumGrand],
            ['Metric' => 'Orders', 'Value' => $orders],
            ['Metric' => 'Average Purchase Value', 'Value' => $apv],
            ['Metric' => 'Taxes', 'Value' => $sumVat],
            ['Metric' => 'Discounts', 'Value' => $sumDiscount],
            ['Metric' => 'Outstanding A/P', 'Value' => $outstandingAP],
        ];

        $filename = 'purchases-report-' . now()->format('Y-m-d-His');

        // If exporting current table with net metrics (XLSX/CSV/PDF)
        if ($request->query('scope') === 'table') {
            $q = $request->query('q');
            $sort = $request->query('sort', 'date_desc');

            $paymentsSubList = \DB::table('purchases_transactions as pt')
                ->selectRaw('pt.purchase_order_id, SUM(pt.total_amount) as paid_total')
                ->where('pt.transaction_type', 'payment')
                ->groupBy('pt.purchase_order_id');
            $refundsSubList = \DB::table('purchases_transactions as rt')
                ->selectRaw('rt.purchase_order_id, SUM(CASE WHEN rt.total_amount < 0 THEN -rt.total_amount ELSE 0 END) as refunds_total')
                ->where('rt.transaction_type', 'payment')
                ->groupBy('rt.purchase_order_id');
            $returnsSubList = \DB::table('purchase_returns as pr')
                ->selectRaw('pr.purchase_order_id, SUM(COALESCE(pr.line_total,0)) as returns_total')
                ->groupBy('pr.purchase_order_id');

            $listBase = \DB::table('purchase_orders as po')
                ->leftJoinSub($paymentsSubList, 'pays', function($join){ $join->on('pays.purchase_order_id', '=', 'po.id'); })
                ->leftJoinSub($refundsSubList, 'refs', function($join){ $join->on('refs.purchase_order_id', '=', 'po.id'); })
                ->leftJoinSub($returnsSubList, 'rtns', function($join){ $join->on('rtns.purchase_order_id', '=', 'po.id'); })
                ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
                ->leftJoin('sub_shops as ss', 'ss.id', '=', 'po.subshop_id')
                ->whereIn('po.subshop_id', $subshopFilter)
                ->whereDate('po.created_at', '>=', $dateFrom->toDateString())
                ->whereDate('po.created_at', '<=', $dateTo->toDateString())
                ->when($q, function($query) use ($q){
                    $query->where(function($qq) use ($q){
                        $qq->where('po.order_no', 'like', "%{$q}%")
                           ->orWhere('s.name', 'like', "%{$q}%");
                    });
                })
                ->select([
                    'po.order_no', 'po.created_at',
                    \DB::raw('COALESCE(s.name, "-") as supplier_name'),
                    \DB::raw('COALESCE(ss.name, "-") as subshop_name'),
                    'po.subtotal', 'po.vat_total', 'po.discount_total', 'po.grand_total',
                    \DB::raw('COALESCE(rtns.returns_total,0) as returns_total'),
                    \DB::raw('COALESCE(refs.refunds_total,0) as refunds_total'),
                    \DB::raw('(po.grand_total - COALESCE(rtns.returns_total,0)) as net_spend'),
                    \DB::raw('GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0) as net_paid'),
                    \DB::raw('GREATEST((po.grand_total - COALESCE(rtns.returns_total,0)) - GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0), 0) as net_remaining'),
                ]);

            switch ($sort) {
                case 'date_asc': $listBase->orderBy('po.created_at', 'asc'); break;
                case 'grand_desc': $listBase->orderBy('po.grand_total', 'desc'); break;
                case 'grand_asc': $listBase->orderBy('po.grand_total', 'asc'); break;
                case 'paid_desc': $listBase->orderBy(\DB::raw('COALESCE(pays.paid_total,0)'), 'desc'); break;
                case 'paid_asc': $listBase->orderBy(\DB::raw('COALESCE(pays.paid_total,0)'), 'asc'); break;
                case 'remain_desc': $listBase->orderBy(\DB::raw('GREATEST((po.grand_total - COALESCE(rtns.returns_total,0)) - GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0), 0)'), 'desc'); break;
                case 'remain_asc': $listBase->orderBy(\DB::raw('GREATEST((po.grand_total - COALESCE(rtns.returns_total,0)) - GREATEST(COALESCE(pays.paid_total,0) - COALESCE(refs.refunds_total,0), 0), 0)'), 'asc'); break;
                case 'net_desc': $listBase->orderBy(\DB::raw('(po.grand_total - COALESCE(rtns.returns_total,0))'), 'desc'); break;
                case 'net_asc': $listBase->orderBy(\DB::raw('(po.grand_total - COALESCE(rtns.returns_total,0))'), 'asc'); break;
                default: $listBase->orderBy('po.created_at', 'desc');
            }

            $rows = $listBase->get()->map(function($r){
                return [
                    'Order No' => $r->order_no,
                    'Date' => \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'Supplier' => $r->supplier_name,
                    'Subshop' => $r->subshop_name,
                    'Subtotal' => (float)$r->subtotal,
                    'VAT' => (float)$r->vat_total,
                    'Discount' => (float)$r->discount_total,
                    'Grand' => (float)$r->grand_total,
                    'Returns' => (float)$r->returns_total,
                    'Refunds' => (float)$r->refunds_total,
                    'Net Spend' => (float)$r->net_spend,
                    'Net Paid' => (float)$r->net_paid,
                    'Net Remaining' => (float)$r->net_remaining,
                ];
            })->toArray();

            if (strtolower($format) === 'pdf') {
                $totals = [
                    'Returns' => array_sum(array_column($rows, 'Returns')),
                    'Refunds' => array_sum(array_column($rows, 'Refunds')),
                    'Net Spend' => array_sum(array_column($rows, 'Net Spend')),
                    'Net Paid' => array_sum(array_column($rows, 'Net Paid')),
                    'Net Remaining' => array_sum(array_column($rows, 'Net Remaining')),
                ];
                $data = [
                    'rows' => $rows,
                    'totals' => $totals,
                    'dateFrom' => $dateFrom->format('Y-m-d'),
                    'dateTo' => $dateTo->format('Y-m-d'),
                    'subshopName' => $subshopName,
                    'generatedAt' => now()->format('Y-m-d H:i:s'),
                ];
                $pdf = Pdf::loadView('reports.pdf.purchases_table', $data);
                return $pdf->download('purchases_table_' . now()->format('Y-m-d-His') . '.pdf');
            }

            if (strtolower($format) === 'csv') {
                return response()->stream(function () use ($rows) {
                    $h = fopen('php://output', 'w');
                    fputcsv($h, array_keys($rows[0] ?? [ 'Order No','Date','Supplier','Subshop','Subtotal','VAT','Discount','Grand','Returns','Refunds','Net Spend','Net Paid','Net Remaining' ]));
                    foreach ($rows as $row) { fputcsv($h, $row); }
                    fclose($h);
                }, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="purchases_table_' . now()->format('Y-m-d_H-i-s') . '.csv"',
                ]);
            }
            $export = new GenericArrayExport($rows, 'Purchases');
            return Excel::download($export, 'purchases_table_' . now()->format('Y-m-d-His') . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
        }

        switch (strtolower($format)) {
            case 'csv':
                return response()->stream(function () use ($summaryRows) {
                    $h = fopen('php://output', 'w');
                    fputcsv($h, ['Metric', 'Value']);
                    foreach ($summaryRows as $row) {
                        fputcsv($h, [$row['Metric'], number_format((float)$row['Value'], 2, '.', '')]);
                    }
                    fclose($h);
                }, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="purchases_report_' . now()->format('Y-m-d_H-i-s') . '.csv"',
                ]);

            case 'pdf':
                $data = [
                    'kpi' => [
                        'total_purchases' => $sumGrand,
                        'orders' => $orders,
                        'apv' => $apv,
                        'taxes' => $sumVat,
                        'discounts' => $sumDiscount,
                        'outstanding_ap' => $outstandingAP,
                    ],
                    'dateFrom' => $dateFrom->format('Y-m-d'),
                    'dateTo' => $dateTo->format('Y-m-d'),
                    'subshopName' => $subshopName,
                    'generatedAt' => now()->format('Y-m-d H:i:s'),
                ];
                $pdf = Pdf::loadView('reports.pdf.purchases_report', $data);
                return $pdf->download($filename . '.pdf');

            case 'xlsx':
            default:
                $export = new GenericArrayExport($summaryRows, 'Summary');
                return Excel::download($export, $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
        }
    }

    // Analytics endpoints for charts
    public function analyticsSpendOverTime(Request $request)
    {
        $user = Auth::user(); if (!$user) abort(403);
        $shop = $user->shop ?: optional($user->subshops()->first())->shop; if (!$shop) abort(403);
        $allSubshops = SubShop::where('shop_id', $shop->id)->get();
        $accessible = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();
        $subshopId = $request->integer('subshop_id');
        $subshopFilter = $subshopId ? (in_array($subshopId, $accessible, true) ? [$subshopId] : abort(403)) : ($accessible ?: [-1]);
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        $paymentsSub = DB::table('purchases_transactions as pt')
            ->selectRaw('pt.purchase_order_id, SUM(pt.total_amount) as paid_total')
            ->where('pt.transaction_type', 'payment')
            ->groupBy('pt.purchase_order_id');
        $returnsSub = DB::table('purchase_returns as pr')
            ->selectRaw('pr.purchase_order_id, SUM(COALESCE(pr.line_total,0)) as returns_total')
            ->groupBy('pr.purchase_order_id');

        $rows = DB::table('purchase_orders as po')
            ->leftJoinSub($paymentsSub, 'pays', fn($j)=>$j->on('pays.purchase_order_id','=','po.id'))
            ->leftJoinSub($returnsSub, 'rtns', fn($j)=>$j->on('rtns.purchase_order_id','=','po.id'))
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereBetween('po.created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(po.created_at) as d')
            ->selectRaw('SUM(po.grand_total - COALESCE(rtns.returns_total,0)) as net_spend')
            ->selectRaw('SUM(COALESCE(pays.paid_total,0)) as paid_total')
            ->groupBy('d')->orderBy('d')->get();

        $labels = $rows->pluck('d')->map(fn($d)=>Carbon::parse($d)->format('Y-m-d'))->values();
        $netSpend = $rows->pluck('net_spend')->map(fn($v)=>(float)$v)->values();
        $paid = $rows->pluck('paid_total')->map(fn($v)=>(float)$v)->values();
        $remaining = $netSpend->zip($paid)->map(fn($p)=>max($p[0]-$p[1],0))->values();
        return response()->json(['labels'=>$labels,'net_spend'=>$netSpend,'paid'=>$paid,'remaining'=>$remaining]);
    }

    public function analyticsOrdersVsApv(Request $request)
    {
        $user = Auth::user(); if (!$user) abort(403);
        $shop = $user->shop ?: optional($user->subshops()->first())->shop; if (!$shop) abort(403);
        $allSubshops = SubShop::where('shop_id', $shop->id)->get();
        $accessible = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();
        $subshopId = $request->integer('subshop_id');
        $subshopFilter = $subshopId ? (in_array($subshopId, $accessible, true) ? [$subshopId] : abort(403)) : ($accessible ?: [-1]);
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        $rows = DB::table('purchase_orders as po')
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereBetween('po.created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(po.created_at) as d')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(po.grand_total) as sum_grand')
            ->groupBy('d')->orderBy('d')->get();

        $labels = $rows->pluck('d')->map(fn($d)=>Carbon::parse($d)->format('Y-m-d'))->values();
        $orders = $rows->pluck('orders')->map(fn($v)=>(int)$v)->values();
        $apv = $rows->map(fn($r)=>$r->orders>0?round($r->sum_grand/$r->orders,2):0)->values();
        return response()->json(['labels'=>$labels,'orders'=>$orders,'apv'=>$apv]);
    }

    public function analyticsApAging(Request $request)
    {
        $user = Auth::user(); if (!$user) abort(403);
        $shop = $user->shop ?: optional($user->subshops()->first())->shop; if (!$shop) abort(403);
        $allSubshops = SubShop::where('shop_id', $shop->id)->get();
        $accessible = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();
        $subshopId = $request->integer('subshop_id');
        $subshopFilter = $subshopId ? (in_array($subshopId, $accessible, true) ? [$subshopId] : abort(403)) : ($accessible ?: [-1]);
        $asOf = $request->date('date_to') ?: Carbon::now()->endOfDay();

        $paymentsSub = DB::table('purchases_transactions as pt')
            ->selectRaw('pt.purchase_order_id, SUM(pt.total_amount) as paid_total')
            ->where('pt.transaction_type', 'payment')
            ->where('pt.created_at', '<=', $asOf)
            ->groupBy('pt.purchase_order_id');

        $base = DB::table('purchase_orders as po')
            ->leftJoinSub($paymentsSub,'pays',fn($j)=>$j->on('pays.purchase_order_id','=','po.id'))
            ->whereIn('po.subshop_id', $subshopFilter)
            ->where('po.created_at','<=',$asOf)
            ->select('po.id','po.created_at','po.grand_total',DB::raw('COALESCE(pays.paid_total,0) as paid_total'))
            ->get()->map(function($r){
                $age = Carbon::parse($r->created_at)->diffInDays(Carbon::now());
                $remaining = max($r->grand_total - $r->paid_total, 0);
                return ['age'=>$age,'remaining'=>$remaining];
            });

        $b0_30 = $base->filter(fn($x)=>$x['age']<=30)->sum('remaining');
        $b31_60 = $base->filter(fn($x)=>$x['age']>30 && $x['age']<=60)->sum('remaining');
        $b61_90 = $base->filter(fn($x)=>$x['age']>60 && $x['age']<=90)->sum('remaining');
        $b90p = $base->filter(fn($x)=>$x['age']>90)->sum('remaining');
        return response()->json(['labels'=>['0-30','31-60','61-90','90+'],'data'=>[(float)$b0_30,(float)$b31_60,(float)$b61_90,(float)$b90p]]);
    }

    public function analyticsSupplierPareto(Request $request)
    {
        $user = Auth::user(); if (!$user) abort(403);
        $shop = $user->shop ?: optional($user->subshops()->first())->shop; if (!$shop) abort(403);
        $allSubshops = SubShop::where('shop_id', $shop->id)->get();
        $accessible = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();
        $subshopId = $request->integer('subshop_id');
        $subshopFilter = $subshopId ? (in_array($subshopId, $accessible, true) ? [$subshopId] : abort(403)) : ($accessible ?: [-1]);
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();
        $limit = (int)($request->query('limit', 10));

        $returnsSub = DB::table('purchase_returns as pr')
            ->selectRaw('pr.purchase_order_id, SUM(COALESCE(pr.line_total,0)) as returns_total')
            ->groupBy('pr.purchase_order_id');

        $rows = DB::table('purchase_orders as po')
            ->leftJoin('suppliers as s','s.id','=','po.supplier_id')
            ->leftJoinSub($returnsSub,'rtns',fn($j)=>$j->on('rtns.purchase_order_id','=','po.id'))
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereBetween('po.created_at', [$dateFrom, $dateTo])
            ->selectRaw('COALESCE(s.name,"-") as supplier_name')
            ->selectRaw('SUM(po.grand_total - COALESCE(rtns.returns_total,0)) as net_spend')
            ->groupBy('supplier_name')
            ->orderByDesc('net_spend')->limit($limit)->get();

        $labels = $rows->pluck('supplier_name');
        $data = $rows->pluck('net_spend')->map(fn($v)=>(float)$v);
        $total = max($data->sum(), 0.0001);
        $cum = [];$running=0;foreach($data as $v){$running+=$v;$cum[] = round(($running/$total)*100,2);}        
        return response()->json(['labels'=>$labels,'spend'=>$data,'cumulative'=>$cum]);
    }

    public function analyticsReturnsRate(Request $request)
    {
        $user = Auth::user(); if (!$user) abort(403);
        $shop = $user->shop ?: optional($user->subshops()->first())->shop; if (!$shop) abort(403);
        $allSubshops = SubShop::where('shop_id', $shop->id)->get();
        $accessible = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();
        $subshopId = $request->integer('subshop_id');
        $subshopFilter = $subshopId ? (in_array($subshopId, $accessible, true) ? [$subshopId] : abort(403)) : ($accessible ?: [-1]);
        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        $ordersAgg = DB::table('purchase_orders as po')
            ->whereIn('po.subshop_id', $subshopFilter)
            ->whereBetween('po.created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(po.created_at) as d, SUM(po.grand_total) as sum_grand')
            ->groupBy('d');

        $returnsAgg = DB::table('purchase_returns as pr')
            ->selectRaw('DATE(pr.created_at) as d, SUM(COALESCE(pr.line_total,0)) as returns_total')
            ->whereBetween('pr.created_at', [$dateFrom, $dateTo])
            ->groupBy('d');

        $rows = DB::query()->fromSub($ordersAgg, 'o')
            ->leftJoinSub($returnsAgg, 'r', function($j){ $j->on('o.d','=','r.d'); })
            ->selectRaw('o.d, o.sum_grand, COALESCE(r.returns_total,0) as returns_total')
            ->orderBy('o.d')
            ->get();

        $labels = $rows->pluck('d')->map(fn($d)=>Carbon::parse($d)->format('Y-m-d'))->values();
        $returns = $rows->pluck('returns_total')->map(fn($v)=>(float)$v)->values();
        $returnsRate = $rows->map(fn($r)=>($r->sum_grand>0? round(($r->returns_total/$r->sum_grand)*100,2):0))->values();
        return response()->json(['labels'=>$labels,'returns'=>$returns,'returns_rate'=>$returnsRate]);
    }
}
