<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Category;
use App\Models\Suppliers;
use App\Models\SubShop;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;

class InventoryReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) { abort(403); }

        // Get the parent shop for this user (owner) or derive via an assigned subshop
        $shop = $user->shop;
        if (!$shop) {
            $firstSubshop = $user->subshops()->first();
            if ($firstSubshop) { $shop = $firstSubshop->shop; }
        }
        if (!$shop) { abort(403, 'No shop found for user'); }

        // Accessible subshops for this user within the shop
        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        // Filter: subshop_id (optional)
        $subshopId = $request->integer('subshop_id');
        if ($subshopId && !in_array($subshopId, $accessibleSubshopIds, true)) {
            abort(403, 'You do not have access to this subshop');
        }

        $subshopFilter = $subshopId ? [$subshopId] : $accessibleSubshopIds;
        if (empty($subshopFilter)) { $subshopFilter = [-1]; } // force empty

        // Saved Views (session-based)
        $savedViews = session('inventory_report_views', []);
        $action = $request->get('view_action'); // save|load|delete
        $viewName = trim((string)$request->get('view_name'));
        if ($action === 'save' && $viewName !== '') {
            $filters = [
                'subshop_id' => $request->integer('subshop_id') ?: null,
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'as_of' => $request->get('as_of'),
                'category_id' => $request->integer('category_id') ?: null,
                'supplier_id' => $request->integer('supplier_id') ?: null,
                'abc' => strtoupper((string)$request->get('abc')) ?: null,
            ];
            $savedViews[$viewName] = $filters;
            session(['inventory_report_views' => $savedViews]);
            return redirect()->route('reports.inventory', array_filter($filters ?? []))->with('success', 'View saved');
        } elseif ($action === 'load' && $viewName !== '' && isset($savedViews[$viewName])) {
            return redirect()->route('reports.inventory', array_filter($savedViews[$viewName] ?? []));
        } elseif ($action === 'delete' && $viewName !== '' && isset($savedViews[$viewName])) {
            unset($savedViews[$viewName]);
            session(['inventory_report_views' => $savedViews]);
            return redirect()->route('reports.inventory')->with('success', 'View deleted');
        }

        // Date filters
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');
        $asOf = $request->date('as_of');

        // Catalog filters
        $categoryId = $request->integer('category_id');
        $supplierId = $request->integer('supplier_id');
        // ABC filter (A|B|C)
        $abcClass = strtoupper((string)$request->get('abc'));
        if (!in_array($abcClass, ['A','B','C'], true)) { $abcClass = null; }

        // Base scopes
        $itemsQuery = Item::query()->whereIn('subshop_id', $subshopFilter);

        // Join batches for aggregates
        $batchesQuery = ItemBatch::query()
            ->select([
                DB::raw('SUM(item_batches.quantity) as qty_sum'),
                DB::raw('SUM(item_batches.quantity * COALESCE(item_batches.cost_price, 0)) as value_cost_sum'),
                DB::raw('SUM(item_batches.quantity * COALESCE(item_batches.selling_price, 0)) as value_retail_sum'),
            ])
            ->join('items', 'items.id', '=', 'item_batches.item_id')
            ->whereIn('items.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId));
        if ($asOf) {
            $batchesQuery->where('item_batches.updated_at', '<=', $asOf->copy()->endOfDay());
        }

        $bAgg = $batchesQuery->first();
        $sohQty = (int) ($bAgg->qty_sum ?? 0);
        $sohValueCost = (float) ($bAgg->value_cost_sum ?? 0);
        $sohValueRetail = (float) ($bAgg->value_retail_sum ?? 0);

        // Low stock and out of stock using per-item aggregated qty from batches
        $perItemAgg = DB::table('item_batches')
            ->select('item_batches.item_id', DB::raw('SUM(item_batches.quantity) as qty'))
            ->join('items', 'items.id', '=', 'item_batches.item_id')
            ->whereIn('items.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId))
            ->when($asOf, function($q) use ($asOf){ $q->where('item_batches.updated_at', '<=', $asOf->copy()->endOfDay()); })
            ->groupBy('item_batches.item_id');

        $lowStockCount = DB::table('items')
            ->leftJoinSub($perItemAgg, 'agg', function($join){ $join->on('items.id', '=', 'agg.item_id'); })
            ->whereIn('items.subshop_id', $subshopFilter)
            ->whereNotNull('items.min_quantity')
            ->where('items.min_quantity', '>', 0)
            ->whereRaw('COALESCE(agg.qty, 0) < items.min_quantity')
            ->count('items.id');

        $outOfStockCount = DB::table('items')
            ->leftJoinSub($perItemAgg, 'agg', function($join){ $join->on('items.id', '=', 'agg.item_id'); })
            ->whereIn('items.subshop_id', $subshopFilter)
            ->whereRaw('COALESCE(agg.qty, 0) <= 0')
            ->count('items.id');

        // Expiry risk
        $now = Carbon::now();
        $soon = $now->copy()->addDays(30)->toDateString();
        $today = $now->toDateString();

        $expiringSoonCount = ItemBatch::query()
            ->join('items', 'items.id', '=', 'item_batches.item_id')
            ->whereIn('items.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId))
            ->whereNotNull('item_batches.expire_date')
            ->whereDate('item_batches.expire_date', '>=', $today)
            ->whereDate('item_batches.expire_date', '<=', $soon)
            ->where('item_batches.quantity', '>', 0)
            ->count();

        $expiredCount = ItemBatch::query()
            ->join('items', 'items.id', '=', 'item_batches.item_id')
            ->whereIn('items.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId))
            ->whereNotNull('item_batches.expire_date')
            ->whereDate('item_batches.expire_date', '<', $today)
            ->where('item_batches.quantity', '>', 0)
            ->count();

        // Per-subshop quick comparison (qty and cost value)
        $perSubshop = ItemBatch::query()
            ->join('items', 'items.id', '=', 'item_batches.item_id')
            ->whereIn('items.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId))
            ->when($asOf, function($q) use ($asOf){ $q->where('item_batches.updated_at', '<=', $asOf->copy()->endOfDay()); })
            ->groupBy('items.subshop_id')
            ->select([
                'items.subshop_id',
                DB::raw('SUM(item_batches.quantity) as qty_sum'),
                DB::raw('SUM(item_batches.quantity * COALESCE(item_batches.cost_price, 0)) as value_cost_sum'),
                DB::raw('SUM(item_batches.quantity * COALESCE(item_batches.selling_price, 0)) as value_retail_sum')
            ])->get();
        $subshopMap = SubShop::whereIn('id', $subshopFilter)->pluck('name', 'id');
        $subshopSummary = $perSubshop->map(function($row) use ($subshopMap){
            return [
                'subshop_id' => (int)$row->subshop_id,
                'name' => $subshopMap[$row->subshop_id] ?? ('Subshop #'.$row->subshop_id),
                'qty' => (int)($row->qty_sum ?? 0),
                'value_cost' => (float)($row->value_cost_sum ?? 0),
                'value_retail' => (float)($row->value_retail_sum ?? 0),
            ];
        })->sortBy('name')->values()->all();

        // Inventory value trend (value at cost) over a period (default last 14 days)
        $trendStart = $dateFrom ? $dateFrom->copy() : Carbon::now()->subDays(13)->startOfDay();
        $trendEnd = $dateTo ? $dateTo->copy()->endOfDay() : Carbon::now()->endOfDay();
        if ($trendEnd->lt($trendStart)) { [$trendStart, $trendEnd] = [$trendEnd->copy()->startOfDay(), $trendStart->copy()->endOfDay()]; }

        $trend = [];
        for ($d = $trendStart->copy(); $d->lte($trendEnd); $d->addDay()) {
            $point = ItemBatch::query()
                ->join('items', 'items.id', '=', 'item_batches.item_id')
                ->whereIn('items.subshop_id', $subshopFilter)
                ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
                ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId))
                ->where('item_batches.updated_at', '<=', $d->copy()->endOfDay())
                ->select([
                    DB::raw('SUM(item_batches.quantity * COALESCE(item_batches.cost_price, 0)) as value_cost_sum'),
                    DB::raw('SUM(item_batches.quantity * COALESCE(item_batches.selling_price, 0)) as value_retail_sum'),
                ])
                ->first();
            $trend[] = [
                'date' => $d->toDateString(),
                'value_cost' => (float)($point->value_cost_sum ?? 0),
                'value_retail' => (float)($point->value_retail_sum ?? 0),
            ];
        }

        // Low stock and out-of-stock lists (top 25)
        // Compute 30-day sales velocity per item (units/day)
        $vStart = Carbon::now()->subDays(30)->startOfDay();
        $sales30 = DB::table('sales_orders_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->whereDate('so.created_at', '>=', $vStart->toDateString())
            ->whereIn('so.subshop_id', $subshopFilter)
            ->groupBy('soi.item_id')
            ->select('soi.item_id', DB::raw('SUM(soi.quantity) as sold_qty_30'));

        $lowStockItems = DB::table('items')
            ->leftJoinSub($perItemAgg, 'agg', function($join){ $join->on('items.id', '=', 'agg.item_id'); })
            ->leftJoinSub($sales30, 's30', function($join){ $join->on('items.id', '=', 's30.item_id'); })
            ->whereIn('items.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId))
            ->whereNotNull('items.min_quantity')
            ->where('items.min_quantity', '>', 0)
            ->whereRaw('COALESCE(agg.qty, 0) > 0 AND COALESCE(agg.qty, 0) < items.min_quantity')
            ->orderByRaw('(items.min_quantity - COALESCE(agg.qty, 0)) DESC')
            ->limit(25)
            ->get(['items.id','items.name','items.subshop_id','items.min_quantity', DB::raw('COALESCE(agg.qty,0) as qty'), DB::raw('COALESCE(s30.sold_qty_30,0) as sold_qty_30')]);

        $oosItems = DB::table('items')
            ->leftJoinSub($perItemAgg, 'agg', function($join){ $join->on('items.id', '=', 'agg.item_id'); })
            ->leftJoinSub($sales30, 's30', function($join){ $join->on('items.id', '=', 's30.item_id'); })
            ->whereIn('items.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId))
            ->whereRaw('COALESCE(agg.qty, 0) <= 0')
            ->orderBy('items.name')
            ->limit(25)
            ->get(['items.id','items.name','items.subshop_id','items.min_quantity', DB::raw('COALESCE(agg.qty,0) as qty'), DB::raw('COALESCE(s30.sold_qty_30,0) as sold_qty_30')]);

        // Decorate low/oos with days of supply and reorder suggestion
        $daysWindow = max(1, Carbon::now()->diffInDays($vStart)); // ~30
        $lowStockItems->transform(function($it){ return (array)$it; });
        $oosItems->transform(function($it){ return (array)$it; });
        $calcDos = function($qty, $sold30, $daysWindow) {
            $daily = $sold30 > 0 ? ($sold30 / $daysWindow) : 0;
            if ($daily <= 0) return null; // unknown or infinite
            return round($qty / $daily, 1);
        };
        $calcReorder = function($qty, $min){
            $def = max(0, (int)$min - (int)$qty);
            return $def;
        };
        $lowStockItems = collect($lowStockItems)->map(function($it) use ($calcDos, $calcReorder, $daysWindow){
            $it['days_of_supply'] = $calcDos((int)($it['qty'] ?? 0), (int)($it['sold_qty_30'] ?? 0), $daysWindow);
            $it['reorder_suggestion'] = $calcReorder((int)($it['qty'] ?? 0), (int)($it['min_quantity'] ?? 0));
            return (object)$it;
        });
        $oosItems = collect($oosItems)->map(function($it) use ($calcDos, $calcReorder, $daysWindow){
            $it['days_of_supply'] = $calcDos((int)($it['qty'] ?? 0), (int)($it['sold_qty_30'] ?? 0), $daysWindow);
            $it['reorder_suggestion'] = $calcReorder((int)($it['qty'] ?? 0), (int)($it['min_quantity'] ?? 0));
            return (object)$it;
        });

        // ABC classification by inventory value (cost) in current scope
        $perItemValueForABC = DB::table('item_batches as ib')
            ->join('items','items.id','=','ib.item_id')
            ->whereIn('items.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId))
            ->groupBy('ib.item_id')
            ->select('ib.item_id', DB::raw('SUM(ib.quantity * COALESCE(ib.cost_price,0)) as value_cost'))
            ->get();
        $sorted = $perItemValueForABC->sortByDesc(function($r){ return (float)$r->value_cost; })->values();
        $totalValue = (float)$sorted->sum('value_cost');
        $abcMap = [];
        $abcCounts = ['A'=>0,'B'=>0,'C'=>0];
        $cum = 0.0;
        $abcDetail = [];
        $itemNamesAll = DB::table('items')->whereIn('id', $sorted->pluck('item_id'))->pluck('name','id');
        $rank = 0;
        foreach ($sorted as $row) {
            $val = (float)($row->value_cost ?? 0);
            $share = $totalValue > 0 ? ($val / $totalValue) : 0.0;
            $cum += $share * 100.0;
            $klass = $cum <= 80.0 ? 'A' : ($cum <= 95.0 ? 'B' : 'C');
            $abcMap[(int)$row->item_id] = $klass;
            $abcCounts[$klass]++;
            $rank++;
            $abcDetail[] = [
                'rank' => $rank,
                'item_id' => (int)$row->item_id,
                'item_name' => (string)($itemNamesAll[$row->item_id] ?? ('Item #'.$row->item_id)),
                'abc' => $klass,
                'value_cost' => $val,
                'share_pct' => $share * 100.0,
                'cum_pct' => $cum,
            ];
        }
        // Attach abc_class to lists and optionally filter
        $lowStockItems = $lowStockItems->map(function($it) use ($abcMap){ $it->abc_class = $abcMap[$it->id] ?? null; return $it; });
        $oosItems = $oosItems->map(function($it) use ($abcMap){ $it->abc_class = $abcMap[$it->id] ?? null; return $it; });
        if ($abcClass) {
            $lowStockItems = $lowStockItems->filter(function($it) use ($abcClass){ return ($it->abc_class ?? null) === $abcClass; })->values();
            $oosItems = $oosItems->filter(function($it) use ($abcClass){ return ($it->abc_class ?? null) === $abcClass; })->values();
        }

        // Inventory aging buckets based on last movement (purchase/sale/write-off)
        $lastMove = DB::query()->fromSub(
            DB::table('items')
                ->leftJoin('purchase_orders_items as poi', 'poi.item_id', '=', 'items.id')
                ->leftJoin('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                ->leftJoin('sales_orders_items as soi', 'soi.item_id', '=', 'items.id')
                ->leftJoin('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
                ->leftJoin('write_offs as w', 'w.item_id', '=', 'items.id')
                ->whereIn('items.subshop_id', $subshopFilter)
                ->select([
                    'items.id as item_id',
                    DB::raw('GREATEST(
                        COALESCE(UNIX_TIMESTAMP(po.created_at), 0),
                        COALESCE(UNIX_TIMESTAMP(so.created_at), 0),
                        COALESCE(UNIX_TIMESTAMP(w.created_at), 0)
                    ) as last_ts')
                ])
            , 'lm')
            ->select('lm.item_id', DB::raw('FROM_UNIXTIME(lm.last_ts) as last_movement_at'))
            ->get()->keyBy('item_id');

        $nowTs = Carbon::now();
        $agingBuckets = [ '0-30'=>0, '31-60'=>0, '61-90'=>0, '90+'=>0 ];
        $agingItems = [];
        $perItemValue = DB::table('item_batches as ib')
            ->join('items','items.id','=','ib.item_id')
            ->whereIn('items.subshop_id', $subshopFilter)
            ->select('ib.item_id', DB::raw('SUM(ib.quantity * COALESCE(ib.cost_price,0)) as value_cost'), DB::raw('SUM(ib.quantity) as qty'))
            ->groupBy('ib.item_id')
            ->get()->keyBy('item_id');
        // Names for aging items
        $itemNames = DB::table('items')->whereIn('id', $perItemValue->keys())->pluck('name','id');
        foreach ($perItemValue as $itemIdKey => $val) {
            $lm = $lastMove->get($itemIdKey);
            $days = $lm && $lm->last_movement_at ? Carbon::parse($lm->last_movement_at)->diffInDays($nowTs) : 9999;
            $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'));
            $agingBuckets[$bucket] = ($agingBuckets[$bucket] ?? 0) + 1;
            $agingItems[] = [ 'item_id'=>$itemIdKey, 'item_name'=>($itemNames[$itemIdKey] ?? 'Item #'.$itemIdKey), 'days'=>$days, 'bucket'=>$bucket, 'qty'=>$val->qty, 'value_cost'=>$val->value_cost ];
        }

        // Top items and categories by value (cost)
        $topItems = DB::table('item_batches as ib')
            ->join('items','items.id','=','ib.item_id')
            ->whereIn('items.subshop_id', $subshopFilter)
            ->when($categoryId, fn($q) => $q->where('items.category_id', $categoryId))
            ->when($supplierId, fn($q) => $q->where('items.supplier_id', $supplierId))
            ->groupBy('ib.item_id','items.name')
            ->select('items.name', DB::raw('SUM(ib.quantity * COALESCE(ib.cost_price,0)) as value_cost'))
            ->orderByDesc('value_cost')->limit(10)->get();
        $topCategories = DB::table('item_batches as ib')
            ->join('items','items.id','=','ib.item_id')
            ->join('categories','categories.id','=','items.category_id')
            ->whereIn('items.subshop_id', $subshopFilter)
            ->groupBy('categories.id','categories.name')
            ->select('categories.name', DB::raw('SUM(ib.quantity * COALESCE(ib.cost_price,0)) as value_cost'))
            ->orderByDesc('value_cost')->limit(10)->get();

        // Export handling
        $export = $request->get('export');
        $type = $request->get('type');
        if ($export && $type) {
            $fileBase = 'inventory_report_'.($type).'_'.Carbon::now()->format('Ymd_His');
            $headers = [];
            $rows = [];
            if ($type === 'summary') {
                $headers = ['Subshop','Qty','Value (Cost)','Value (Retail)'];
                foreach ($subshopSummary as $row) {
                    $rows[] = [$row['name'], $row['qty'], $row['value_cost'], $row['value_retail']];
                }
            } elseif ($type === 'low_stock') {
                $headers = ['Item','Subshop','Qty','Min','Deficit'];
                foreach ($lowStockItems as $it) {
                    $rows[] = [$it->name, ($subshopMap[$it->subshop_id] ?? ''), (int)($it->qty ?? 0), (int)($it->min_quantity ?? 0), max(0, (int)($it->min_quantity ?? 0) - (int)($it->qty ?? 0))];
                }
            } elseif ($type === 'oos') {
                $headers = ['Item','Subshop','Qty'];
                foreach ($oosItems as $it) {
                    $rows[] = [$it->name, ($subshopMap[$it->subshop_id] ?? ''), (int)($it->qty ?? 0)];
                }
            } elseif ($type === 'aging') {
                $headers = ['Item','Days Since Movement','Bucket','Qty','Value (Cost)'];
                foreach ($agingItems as $ai) {
                    $rows[] = [$ai['item_name'], $ai['days'], $ai['bucket'], (int)$ai['qty'], (float)$ai['value_cost']];
                }
            } elseif ($type === 'abc') {
                $headers = ['Rank','Item','Class','Value (Cost)','Share %','Cumulative %'];
                foreach ($abcDetail as $it) {
                    if ($abcClass && ($it['abc'] ?? null) !== $abcClass) { continue; }
                    $rows[] = [
                        (int)$it['rank'],
                        $it['item_name'],
                        $it['abc'],
                        (float)$it['value_cost'],
                        round((float)$it['share_pct'], 2),
                        round((float)$it['cum_pct'], 2),
                    ];
                }
            }

            if ($export === 'csv') {
                $callback = function() use ($headers, $rows) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $headers);
                    foreach ($rows as $r) { fputcsv($out, $r); }
                    fclose($out);
                };
                return response()->streamDownload($callback, $fileBase.'.csv', ['Content-Type' => 'text/csv']);
            }
            if ($export === 'xlsx') {
                $array = array_merge([$headers], $rows);
                return Excel::download(new \App\Exports\ArrayExport($array), $fileBase.'.xlsx');
            }
            if ($export === 'pdf') {
                $pdf = Pdf::loadView('exports.inventory_report_list', [
                    'title' => ucfirst(str_replace('_',' ', $type)),
                    'headers' => $headers,
                    'rows' => $rows,
                ])->setPaper('a4', 'portrait');
                return $pdf->download($fileBase.'.pdf');
            }
        }

        return view('reports.inventory_report', [
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'dateFrom' => $dateFrom ? $dateFrom->toDateString() : null,
            'dateTo' => $dateTo ? $dateTo->toDateString() : null,
            'asOf' => $asOf ? $asOf->toDateString() : null,
            'categoryId' => $categoryId,
            'supplierId' => $supplierId,
            'kpi' => [
                'soh_qty' => $sohQty,
                'soh_value_cost' => $sohValueCost,
                'soh_value_retail' => $sohValueRetail,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'expiring_soon_count' => $expiringSoonCount,
                'expired_count' => $expiredCount,
            ],
            'subshopSummary' => $subshopSummary,
            'trend' => $trend,
            'lowStockItems' => $lowStockItems,
            'oosItems' => $oosItems,
            'subshopNames' => $subshopMap,
            'categories' => Category::orderBy('name')->get(['id','name']),
            'suppliers' => Suppliers::orderBy('name')->get(['id','name']),
            'agingBuckets' => $agingBuckets,
            'agingItems' => $agingItems,
            'topItems' => $topItems,
            'topCategories' => $topCategories,
            'savedViews' => $savedViews,
            'abcCounts' => $abcCounts,
            'abcClass' => $abcClass,
        ]);
    }

    /**
     * Full-page PDF export with charts using headless Chrome (Browsershot).
     * Preserves current filters and forwards the active session cookie for auth.
     */
    public function fullPdf(Request $request)
    {
        $url = route('reports.inventory', $request->all());

        $cookieName = config('session.cookie');
        $sessionId = Session::getId();
        $headers = [
            'Cookie' => $cookieName . '=' . $sessionId,
            'Accept-Language' => $request->header('Accept-Language', 'en-US,en;q=0.9'),
        ];

        $pdf = Browsershot::url($url)
            ->setExtraHttpHeaders($headers)
            ->waitUntilNetworkIdle()
            ->addChromiumArguments(['--no-sandbox'])
            ->timeout(120)
            ->format('A4')
            ->landscape()
            ->margins(10, 10, 10, 10)
            ->pdf();

        $filename = 'inventory-report-' . now()->format('Ymd_His') . '.pdf';
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
