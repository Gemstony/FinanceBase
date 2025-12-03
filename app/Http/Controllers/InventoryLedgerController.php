<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\PurchaseOrders;
use App\Models\PurchaseOrdersItems;
use App\Models\PurchaseReturns;
use App\Models\SalesOrdersItems;
use App\Models\SalesReturns;
use App\Models\SubShop;
use App\Models\Category;
use App\Models\Suppliers;
use App\Models\WriteOff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class InventoryLedgerController extends Controller
{
    public function index(Request $request)
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
        if ($subshopId && !in_array($subshopId, $accessibleSubshopIds, true)) {
            abort(403, 'You do not have access to this subshop');
        }
        $subshopFilter = $subshopId ? [$subshopId] : ($accessibleSubshopIds ?: [-1]);

        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');
        $categoryId = $request->integer('category_id');
        $supplierId = $request->integer('supplier_id');
        $itemId = $request->integer('item_id');
        $eventType = $request->get('event'); // purchase|sale|sales_return|purchase_return|write_off

        // Subshop map for later
        $subshopMap = SubShop::whereIn('id', $subshopFilter)->pluck('name', 'id');

        // Base constraints helpers
        $dateBetween = function($col) use ($dateFrom, $dateTo){
            return function($q) use ($col, $dateFrom, $dateTo){
                if ($dateFrom) { $q->whereDate($col, '>=', $dateFrom->toDateString()); }
                if ($dateTo) { $q->whereDate($col, '<=', $dateTo->toDateString()); }
            };
        };

        // Purchases (increase)
        $qPurch = \DB::table('purchase_orders_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->join('items', 'items.id', '=', 'poi.item_id')
            ->select([
                \DB::raw("po.created_at as date"),
                \DB::raw("po.subshop_id as subshop_id"),
                \DB::raw("'purchase' as event_type"),
                \DB::raw("poi.item_id as item_id"),
                \DB::raw("items.name as item_name"),
                \DB::raw("poi.batch_number as batch_number"),
                \DB::raw("poi.quantity as qty_change"),
                \DB::raw("COALESCE(poi.cost_price, 0) as unit_cost"),
                \DB::raw("COALESCE(poi.selling_price, 0) as unit_price"),
                \DB::raw("(poi.quantity * COALESCE(poi.cost_price, 0)) as value_cost_change"),
                \DB::raw("(poi.quantity * COALESCE(poi.selling_price, 0)) as value_retail_change"),
                \DB::raw("po.order_no as reference")
            ])
            ->whereIn('po.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q)=>$q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q)=>$q->where('po.supplier_id', $supplierId))
            ->when($itemId, fn($q)=>$q->where('poi.item_id', $itemId))
            ->when($eventType === 'purchase', fn($q)=>$q)
            ->where($dateBetween('po.created_at'));

        // Sales (decrease)
        $qSales = \DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->join('items', 'items.id', '=', 'soi.item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->select([
                \DB::raw("so.created_at as date"),
                \DB::raw("so.subshop_id as subshop_id"),
                \DB::raw("'sale' as event_type"),
                \DB::raw("soi.item_id as item_id"),
                \DB::raw("items.name as item_name"),
                \DB::raw("COALESCE(ib.batch_number, '') as batch_number"),
                \DB::raw("-1 * soi.quantity as qty_change"),
                \DB::raw("COALESCE(ib.cost_price, 0) as unit_cost"),
                \DB::raw("COALESCE(ib.selling_price, 0) as unit_price"),
                \DB::raw("(-1 * soi.quantity * COALESCE(ib.cost_price, 0)) as value_cost_change"),
                \DB::raw("(-1 * soi.quantity * COALESCE(ib.selling_price, 0)) as value_retail_change"),
                \DB::raw("so.order_no as reference")
            ])
            ->whereIn('so.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q)=>$q->where('items.category_id', $categoryId))
            ->when($itemId, fn($q)=>$q->where('soi.item_id', $itemId))
            ->when($eventType === 'sale', fn($q)=>$q)
            ->where($dateBetween('so.created_at'));

        // Sales returns (increase)
        $qSalesRet = \DB::table('sales_returns as sr')
            ->join('items', 'items.id', '=', 'sr.item_id')
            ->leftJoin('sales_orders_items as soi', 'soi.id', '=', 'sr.sales_order_item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'soi.batch_id')
            ->select([
                \DB::raw("sr.created_at as date"),
                \DB::raw("sr.subshop_id as subshop_id"),
                \DB::raw("'sales_return' as event_type"),
                \DB::raw("sr.item_id as item_id"),
                \DB::raw("items.name as item_name"),
                \DB::raw("COALESCE(ib.batch_number, '') as batch_number"),
                \DB::raw("sr.quantity_returned as qty_change"),
                \DB::raw("COALESCE(ib.cost_price, 0) as unit_cost"),
                \DB::raw("COALESCE(ib.selling_price, 0) as unit_price"),
                \DB::raw("(sr.quantity_returned * COALESCE(ib.cost_price, 0)) as value_cost_change"),
                \DB::raw("(sr.quantity_returned * COALESCE(ib.selling_price, 0)) as value_retail_change"),
                \DB::raw("CONCAT('SR#', sr.id) as reference")
            ])
            ->whereIn('sr.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q)=>$q->where('items.category_id', $categoryId))
            ->when($itemId, fn($q)=>$q->where('sr.item_id', $itemId))
            ->when($eventType === 'sales_return', fn($q)=>$q)
            ->where($dateBetween('sr.created_at'));

        // Purchase returns (decrease)
        $qPurchRet = \DB::table('purchase_returns as pr')
            ->join('items', 'items.id', '=', 'pr.item_id')
            ->leftJoin('purchase_orders_items as poi', 'poi.id', '=', 'pr.purchase_order_item_id')
            ->select([
                \DB::raw("pr.created_at as date"),
                \DB::raw("pr.subshop_id as subshop_id"),
                \DB::raw("'purchase_return' as event_type"),
                \DB::raw("pr.item_id as item_id"),
                \DB::raw("items.name as item_name"),
                \DB::raw("COALESCE(poi.batch_number, '') as batch_number"),
                \DB::raw("-1 * pr.quantity_returned as qty_change"),
                \DB::raw("COALESCE(poi.cost_price, 0) as unit_cost"),
                \DB::raw("COALESCE(poi.selling_price, 0) as unit_price"),
                \DB::raw("(-1 * pr.quantity_returned * COALESCE(poi.cost_price, 0)) as value_cost_change"),
                \DB::raw("(-1 * pr.quantity_returned * COALESCE(poi.selling_price, 0)) as value_retail_change"),
                \DB::raw("CONCAT('PR#', pr.id) as reference")
            ])
            ->whereIn('pr.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q)=>$q->where('items.category_id', $categoryId))
            ->when($itemId, fn($q)=>$q->where('pr.item_id', $itemId))
            ->when($eventType === 'purchase_return', fn($q)=>$q)
            ->where($dateBetween('pr.created_at'));

        // Write-offs (decrease)
        $qWriteOff = \DB::table('write_offs as w')
            ->join('items', 'items.id', '=', 'w.item_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'w.batch_id')
            ->select([
                \DB::raw("COALESCE(w.write_off_date, w.created_at) as date"),
                \DB::raw("w.subshop_id as subshop_id"),
                \DB::raw("'write_off' as event_type"),
                \DB::raw("w.item_id as item_id"),
                \DB::raw("items.name as item_name"),
                \DB::raw("COALESCE(ib.batch_number, '') as batch_number"),
                \DB::raw("-1 * w.quantity as qty_change"),
                \DB::raw("COALESCE(ib.cost_price, 0) as unit_cost"),
                \DB::raw("COALESCE(ib.selling_price, 0) as unit_price"),
                \DB::raw("(-1 * w.quantity * COALESCE(ib.cost_price, 0)) as value_cost_change"),
                \DB::raw("(-1 * w.quantity * COALESCE(ib.selling_price, 0)) as value_retail_change"),
                \DB::raw("CONCAT('WO#', w.id) as reference")
            ])
            ->whereIn('w.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q)=>$q->where('items.category_id', $categoryId))
            ->when($itemId, fn($q)=>$q->where('w.item_id', $itemId))
            ->when($eventType === 'write_off', fn($q)=>$q)
            ->where($dateBetween('w.created_at'));

        // Combine
        $union = $qPurch
            ->unionAll($qSales)
            ->unionAll($qSalesRet)
            ->unionAll($qPurchRet)
            ->unionAll($qWriteOff);

        $rowsQuery = \DB::query()->fromSub($union, 'ledger')
            ->when($eventType && in_array($eventType, ['purchase','sale','sales_return','purchase_return','write_off'], true), fn($q)=>$q->where('event_type', $eventType))
            ->orderByDesc('date');

        // Export
        if ($request->filled('export')) {
            $data = $rowsQuery->get();
            $headers = ['Date','Subshop','Event','Item','Batch','Qty +/-','Unit Cost','Unit Retail','Value (Cost)','Value (Retail)','Ref'];
            $rows = [];
            foreach ($data as $r) {
                $rows[] = [
                    Carbon::parse($r->date)->toDateTimeString(),
                    ($subshopMap[$r->subshop_id] ?? ''),
                    $r->event_type,
                    $r->item_name,
                    $r->batch_number,
                    (int)$r->qty_change,
                    (float)$r->unit_cost,
                    (float)$r->unit_price,
                    (float)$r->value_cost_change,
                    (float)$r->value_retail_change,
                    $r->reference,
                ];
            }
            $fileBase = 'inventory_ledger_'.Carbon::now()->format('Ymd_His');
            $type = $request->get('export');
            if ($type === 'csv') {
                $callback = function() use ($headers, $rows) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $headers);
                    foreach ($rows as $r) { fputcsv($out, $r); }
                    fclose($out);
                };
                return response()->streamDownload($callback, $fileBase.'.csv', ['Content-Type' => 'text/csv']);
            } elseif ($type === 'xlsx') {
                $array = array_merge([$headers], $rows);
                return Excel::download(new \App\Exports\ArrayExport($array), $fileBase.'.xlsx');
            } elseif ($type === 'pdf') {
                $subshopTitle = $subshopId ? ($subshopMap[$subshopId] ?? 'Subshop') : 'All Subshops';
                $filters = [
                    'date_from' => $dateFrom ? $dateFrom->toDateString() : null,
                    'date_to' => $dateTo ? $dateTo->toDateString() : null,
                    'event' => $eventType,
                    'category' => $categoryId ? optional(Category::find($categoryId))->name : null,
                    'supplier' => $supplierId ? optional(\App\Models\Suppliers::find($supplierId))->name : null,
                    'item' => $itemId ? optional(\App\Models\Item::find($itemId))->name : null,
                ];
                $pdf = Pdf::loadView('exports.inventory_ledger_pdf', [
                    'rows' => $rows,
                    'subshopTitle' => $subshopTitle,
                    'filters' => $filters,
                    'generatedBy' => optional($user)->name,
                ])->setPaper('a4', 'landscape');
                return $pdf->download($fileBase.'.pdf');
            }
        }

        $rows = $rowsQuery->paginate(25)->appends($request->query());

        return view('reports.inventory_ledger', [
            'rows' => $rows,
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'categories' => Category::orderBy('name')->get(['id','name']),
            'suppliers' => Suppliers::orderBy('name')->get(['id','name']),
            'categoryId' => $categoryId,
            'supplierId' => $supplierId,
            'itemId' => $itemId,
            'eventType' => $eventType,
        ]);
    }
}
