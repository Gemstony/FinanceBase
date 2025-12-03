<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseOrders;
use App\Models\PurchaseOrdersItems;
use App\Models\PurchaseReturns;
use App\Models\PurchasesTransactions;
use App\Models\PrinterSetting;
use App\Services\ReceiptPrinter;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\SubShop;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PurchaseReturnsExport;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Services\SmsService;
use App\Jobs\PrintEscposJob;
use Illuminate\Support\Facades\Log;

class PurchaseReturnsController extends Controller
{
    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('purchase_returns.index')]);
    }

    /**
     * API: Print purchase return via ESC/POS or return dummy payload
     */
    public function apiPrint(Request $request, PurchaseReturns $return, ReceiptPrinter $printer)
    {
        $subshopId = (int) $request->session()->get('subshop_id');
        if (!$subshopId || $return->subshop_id !== $subshopId) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }
        $ps = PrinterSetting::where('subshop_id', $subshopId)->orderByDesc('is_default')->first();
        if (!$ps) {
            return response()->json(['ok' => false, 'error' => 'No printer configured for this shop'], 422);
        }
        try {
            $dummy = (bool) $request->boolean('dummy');
            if ($dummy) {
                $data = $printer->printPurchaseReturn($return, $ps, true);
                return response()->json(['ok' => true, 'dummy' => true, 'data' => $data]);
            }
            $job = new PrintEscposJob('purchase_return', (int)$return->id, (int)$ps->id);
            $jobId = $job->jobId;
            dispatch($job);
            Log::info('Purchase return print queued', ['return_id' => $return->id, 'printer' => $ps->id, 'job_id' => $jobId]);
            return response()->json(['ok' => true, 'dummy' => false, 'queued' => true, 'job_id' => $jobId]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function export(Request $request, $format)
    {
        $subshopId = $request->query('subshop_id');
        if (!$subshopId) {
            return redirect()->route('purchase_returns.subshops');
        }
        $subshop = SubShop::findOrFail($subshopId);

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minTotal = $request->query('min_total');
        $maxTotal = $request->query('max_total');

        $base = PurchaseReturns::query()
            ->where('purchase_returns.subshop_id', $subshopId)
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'purchase_returns.purchase_order_id')
            ->leftJoin('purchase_orders_items', 'purchase_orders_items.id', '=', 'purchase_returns.purchase_order_item_id')
            ->leftJoin('items', 'items.id', '=', 'purchase_returns.item_id')
            ->leftJoin('users', 'users.id', '=', 'purchase_returns.processed_by')
            ->leftJoin('purchases_transactions', 'purchases_transactions.id', '=', 'purchase_returns.transaction_id')
            ->select(
                'purchase_returns.*',
                'purchase_orders.order_no',
                DB::raw("COALESCE(purchase_orders_items.item_name, items.name, CONCAT('Item #', IFNULL(purchase_returns.item_id, 'N/A'))) as item_name"),
                DB::raw("COALESCE(purchase_orders_items.unit, items.unit, '-') as item_unit"),
                'users.name as processed_by_name',
                'purchases_transactions.total_amount as refund_amount',
                'purchases_transactions.payment_method as refund_method',
                'purchases_transactions.transaction_date as refund_date'
            )
            ->when($q, function($query) use ($q){
                $query->where(function($qq) use ($q){
                    $qq->where('purchase_orders.order_no', 'like', "%{$q}%")
                       ->orWhere('purchase_returns.item_id', 'like', "%{$q}%")
                       ->orWhere('purchase_returns.reason', 'like', "%{$q}%")
                       ->orWhere('purchases_transactions.payment_method', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom){ $query->whereDate('purchase_returns.created_at', '>=', $dateFrom); })
            ->when($dateTo, function($query) use ($dateTo){ $query->whereDate('purchase_returns.created_at', '<=', $dateTo); })
            ->when(is_numeric($minTotal), function($query) use ($minTotal){ $query->where('purchase_returns.line_total', '>=', $minTotal); })
            ->when(is_numeric($maxTotal), function($query) use ($maxTotal){ $query->where('purchase_returns.line_total', '<=', $maxTotal); })
            ->orderByDesc('purchase_returns.created_at');

        $rows = $base->get();
        if ($rows->count()) {
            $missingIds = $rows->filter(function($r){ return empty($r->item_name) && !empty($r->item_id); })
                                ->pluck('item_id')->unique()->values();
            if ($missingIds->count()) {
                $itemsMap = Item::withTrashed()->whereIn('id', $missingIds)->pluck('name','id');
                $rows->transform(function($r) use ($itemsMap){
                    if (empty($r->item_name) && !empty($r->item_id)) {
                        $r->item_name = $itemsMap[$r->item_id] ?? ('Item #'.$r->item_id);
                    }
                    return $r;
                });
            }
        }
        $summary = [
            'count' => $rows->count(),
            'returned_total' => (float) $rows->sum('line_total'),
            'refunded_total' => (float) $rows->sum(function($r){ $amt = (float)($r->refund_amount ?? 0); return $amt < 0 ? -$amt : 0; }),
        ];

        if ($format === 'csv') {
            return response()->stream(function () use ($rows) {
                $h = fopen('php://output', 'w');
                fputcsv($h, ['Date','Order No','Item','Unit Price','Returned','Base','VAT','Line Total','Refunded','Method','Processed By','Reason']);
                foreach ($rows as $r) {
                    $refAmt = (float)($r->refund_amount ?? 0);
                    fputcsv($h, [
                        optional($r->created_at)->format('Y-m-d H:i:s'),
                        $r->order_no,
                        $r->item_id.' — '.($r->item_name ?? ''),
                        number_format((float)$r->unit_price,2,'.',''),
                        (int)$r->quantity_returned,
                        number_format((float)$r->base_amount,2,'.',''),
                        number_format((float)$r->vat_amount,2,'.',''),
                        number_format((float)$r->line_total,2,'.',''),
                        $refAmt < 0 ? number_format(-$refAmt,2,'.','') : '',
                        $r->refund_method ?? '',
                        $r->processed_by_name ?? '',
                        $r->reason ?? '',
                    ]);
                }
                fclose($h);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="purchase_returns_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            $summary = [
                'count' => $rows->count(),
                'returned_total' => (float) $rows->sum('line_total'),
                'refunded_total' => (float) $rows->sum(function($r){ $amt = (float)($r->refund_amount ?? 0); return $amt < 0 ? -$amt : 0; }),
            ];
            return Excel::download(new PurchaseReturnsExport($rows, $subshop, $summary), 'purchase_returns_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $summary = [
                'count' => $rows->count(),
                'returned_total' => (float) $rows->sum('line_total'),
                'refunded_total' => (float) $rows->sum(function($r){ $amt = (float)($r->refund_amount ?? 0); return $amt < 0 ? -$amt : 0; }),
            ];
            $pdf = PDF::loadView('exports.purchase_returns_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('purchase_returns_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('purchase_returns.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('purchase_returns.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minTotal = $request->query('min_total');
        $maxTotal = $request->query('max_total');
        $sort = $request->query('sort', 'date_desc');

        $base = PurchaseReturns::query()
            ->where('purchase_returns.subshop_id', $subshopId)
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'purchase_returns.purchase_order_id')
            ->leftJoin('purchase_orders_items', 'purchase_orders_items.id', '=', 'purchase_returns.purchase_order_item_id')
            ->leftJoin('items', 'items.id', '=', 'purchase_returns.item_id')
            ->leftJoin('users', 'users.id', '=', 'purchase_returns.processed_by')
            ->leftJoin('purchases_transactions', 'purchases_transactions.id', '=', 'purchase_returns.transaction_id')
            ->select(
                'purchase_returns.*',
                'purchase_orders.order_no',
                DB::raw("COALESCE(purchase_orders_items.item_name, items.name, CONCAT('Item #', IFNULL(purchase_returns.item_id, 'N/A'))) as item_name"),
                DB::raw("COALESCE(purchase_orders_items.unit, items.unit, '-') as item_unit"),
                'users.name as processed_by_name',
                'purchases_transactions.total_amount as refund_amount',
                'purchases_transactions.payment_method as refund_method',
                'purchases_transactions.transaction_date as refund_date'
            )
            ->when($q, function($query) use ($q){
                $query->where(function($qq) use ($q){
                    $qq->where('purchase_orders.order_no', 'like', "%{$q}%")
                       ->orWhere('purchase_returns.item_id', 'like', "%{$q}%")
                       ->orWhere('purchase_returns.reason', 'like', "%{$q}%")
                       ->orWhere('purchases_transactions.payment_method', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom){ $query->whereDate('purchase_returns.created_at', '>=', $dateFrom); })
            ->when($dateTo, function($query) use ($dateTo){ $query->whereDate('purchase_returns.created_at', '<=', $dateTo); })
            ->when(is_numeric($minTotal), function($query) use ($minTotal){ $query->where('purchase_returns.line_total', '>=', $minTotal); })
            ->when(is_numeric($maxTotal), function($query) use ($maxTotal){ $query->where('purchase_returns.line_total', '<=', $maxTotal); });

        switch ($sort) {
            case 'amount_desc': $base->orderBy('purchase_returns.line_total', 'desc'); break;
            case 'amount_asc': $base->orderBy('purchase_returns.line_total', 'asc'); break;
            case 'date_asc': $base->orderBy('purchase_returns.created_at', 'asc'); break;
            default: $base->orderBy('purchase_returns.created_at', 'desc');
        }

        $agg = (clone $base)->get();
        $summary = [
            'count' => $agg->count(),
            'returned_total' => (float) $agg->sum('line_total'),
            'refunded_total' => (float) $agg->sum(function($r){ $amt = (float)($r->refund_amount ?? 0); return $amt < 0 ? -$amt : 0; }),
        ];

        $returns = $base->paginate(15)->withQueryString();
        if ($returns->count()) {
            $coll = $returns->getCollection();
            $missingIds = $coll->filter(function($r){ return empty($r->item_name) && !empty($r->item_id); })
                               ->pluck('item_id')->unique()->values();
            if ($missingIds->count()) {
                $itemsMap = Item::withTrashed()->whereIn('id', $missingIds)->pluck('name','id');
                $coll = $coll->transform(function($r) use ($itemsMap){
                    if (empty($r->item_name) && !empty($r->item_id)) {
                        $r->item_name = $itemsMap[$r->item_id] ?? ('Item #'.$r->item_id);
                    }
                    return $r;
                });
                $returns->setCollection($coll);
            }
        }

        return view('purchases.returns.purchase_returns', compact('subshop','returns','q','dateFrom','dateTo','minTotal','maxTotal','sort','summary'));
    }

    public function destroy(Request $request, PurchaseReturns $return)
    {
        try {
            DB::transaction(function () use ($return) {
                $order = PurchaseOrders::findOrFail($return->purchase_order_id);

                // 1) Restore/recreate purchase order item (needed to know batch)
                $poi = PurchaseOrdersItems::where('id', $return->purchase_order_item_id)
                    ->where('purchase_order_id', $order->id)
                    ->first();

                if (!$poi) {
                    $itm = $return->item_id ? Item::find($return->item_id) : null;
                    $poi = new PurchaseOrdersItems();
                    $poi->purchase_order_id = $order->id;
                    $poi->item_id = $return->item_id;
                    $poi->item_name = $itm->name ?? ('Item #'.$return->item_id);
                    $poi->unit = $itm->unit ?? null;
                    $poi->unit_price = (float)$return->unit_price;
                    $poi->quantity = 0; // will be set below
                    // neutral defaults; amounts set below
                    $poi->vat_amount = 0;
                    $poi->base_amount = 0;
                    $poi->line_total = 0;
                    $poi->save();
                }

                // 2) Reverse stock decrease from purchase return: increment item and batch quantities back
                if ($return->item_id) {
                    // Item quantity (kept for backward-compat with existing logic)
                    Item::where('id', $return->item_id)->increment('quantity', $return->quantity_returned);

                    // Batch quantity (if we can identify the batch from PO item)
                    if (!empty($poi->batch_number)) {
                        $batch = ItemBatch::where('item_id', $return->item_id)
                            ->where('batch_number', $poi->batch_number)
                            ->first();
                        if ($batch) {
                            $batch->quantity = (int)$batch->quantity + (int)$return->quantity_returned;
                            $batch->save();
                        }
                    }
                }

                $origQty = max(1, (int)$poi->quantity);
                $unitBase = $origQty > 0 ? ((float)$poi->base_amount / $origQty) : 0;
                $unitVat = $origQty > 0 ? ((float)$poi->vat_amount / $origQty) : 0;
                $unitLine = $origQty > 0 ? ((float)$poi->line_total / $origQty) : 0;
                if ($poi->base_amount == 0 && $poi->vat_amount == 0 && $poi->line_total == 0 && (int)$return->quantity_returned > 0) {
                    $unitBase = round(((float)$return->base_amount) / (int)$return->quantity_returned, 2);
                    $unitVat = round(((float)$return->vat_amount) / (int)$return->quantity_returned, 2);
                    $unitLine = round(((float)$return->line_total) / (int)$return->quantity_returned, 2);
                }

                $newQty = (int)$poi->quantity + (int)$return->quantity_returned;
                $poi->quantity = $newQty;
                $poi->base_amount = round($unitBase * $newQty, 2);
                $poi->vat_amount = round($unitVat * $newQty, 2);
                $poi->line_total = round($unitLine * $newQty, 2);
                $poi->save();

                // 3) Recalculate order totals
                $items = PurchaseOrdersItems::where('purchase_order_id', $order->id)->get();
                $subtotal = (float)$items->sum('base_amount');
                $vat = (float)$items->sum('vat_amount');
                $discPercent = (float)($order->discount_percent ?? 0);
                $discCash = (float)($order->discount_cash ?? 0);
                $discountFromPercent = round($subtotal * ($discPercent/100), 2);
                $discountTotal = round($discountFromPercent + $discCash, 2);
                $grand = round($subtotal + $vat - $discountTotal, 2);
                $order->subtotal = round($subtotal, 2);
                $order->vat_total = round($vat, 2);
                $order->discount_total = $discountTotal;
                $order->grand_total = $grand;
                $order->save();

                // 4) If this return created a standalone refund transaction, remove it when only linked here
                if ($return->transaction_id) {
                    $countLinked = PurchaseReturns::where('transaction_id', $return->transaction_id)->count();
                    if ($countLinked === 1) {
                        PurchasesTransactions::where('id', $return->transaction_id)->delete();
                    }
                }

                // 5) Finally delete the return record
                $return->delete();
            });

            // Send SMS notification to shop owner about return deletion
            try {
                $order = PurchaseOrders::findOrFail($return->purchase_order_id);
                $return->load(['subshop.shop.user', 'processor', 'purchaseOrderItem', 'item', 'transaction']);
                $this->sendPurchaseReturnDeletionNotificationToOwner($return, $order);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send purchase return deletion SMS notification: ' . $e->getMessage());
                // Don't fail the deletion if SMS fails
            }

            if ($request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->back()->with('success', 'Purchase return deleted successfully.');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Send SMS notification to shop owner about purchase return deletion (which restores order state)
     */
    private function sendPurchaseReturnDeletionNotificationToOwner(PurchaseReturns $return, PurchaseOrders $order)
    {
        \Illuminate\Support\Facades\Log::info('=== START sendPurchaseReturnDeletionNotificationToOwner ===', [
            'return_id' => $return->id,
            'order_id' => $order->id,
            'return_exists' => $return->exists,
            'return_data' => $return->toArray()
        ]);
        
        // Get shop owner
        $subshop = $return->subshop;
        \Illuminate\Support\Facades\Log::info('Subshop check', [
            'subshop_exists' => $subshop ? true : false,
            'subshop_id' => $subshop ? $subshop->id : null,
            'shop_exists' => $subshop && $subshop->shop ? true : false,
            'user_exists' => $subshop && $subshop->shop && $subshop->shop->user ? true : false
        ]);
        
        if (!$subshop || !$subshop->shop || !$subshop->shop->user) {
            \Illuminate\Support\Facades\Log::warning('No shop owner found for purchase return', ['return_id' => $return->id]);
            return; // No owner found
        }

        $owner = $subshop->shop->user;
        \Illuminate\Support\Facades\Log::info('Owner check', [
            'owner_id' => $owner->id,
            'owner_name' => $owner->name,
            'phone_number' => $owner->phone_number
        ]);
        
        if (!$owner->phone_number) {
            \Illuminate\Support\Facades\Log::info('Shop owner has no phone number', ['return_id' => $return->id]);
            return; // Owner has no phone number
        }

        // Get return processor
        $processor = $return->processor; // Use relationship instead of auth()->user()

        // Get item information using relationships
        $itemName = 'Item';
        $purchaseOrderItem = $return->purchaseOrderItem;
        if ($purchaseOrderItem && $purchaseOrderItem->item_name) {
            $itemName = $purchaseOrderItem->item_name;
        } elseif ($return->item) {
            $itemName = $return->item->name;
        }

        // Get supplier info from order relationship
        $supplier = $order->supplier;

        // Get refund information using transaction relationship
        $refundAmount = 0;
        if ($return->transaction) {
            $refundAmount = abs((float)$return->transaction->total_amount); // Refund amounts are negative
        }

        // Format SMS message
        $message = "PURCHASE RETURN CANCELLED!\n" .
                  "Shop: {$subshop->name}\n" .
                  "Order: {$order->order_no}\n" .
                  "Cancelled by: " . ($processor ? $processor->name : 'System') . "\n" .
                  "Supplier: " . ($supplier ? $supplier->name : 'N/A') . "\n" .
                  "Item Restored: {$itemName} ({$return->quantity_returned}x)\n" .
                  "Return Value Restored: " . number_format((float)$return->line_total, 2) . " TZS\n" .
                  "Refund Reversed: " . number_format($refundAmount, 2) . " TZS\n" .
                  "Reason: " . ($return->reason ?: 'N/A') . "\n" .
                  "Date Cancelled: " . now()->format('d/m/Y') . "\n" .
                  "Time Cancelled: " . now()->format('d/m/Y H:i');

        \Illuminate\Support\Facades\Log::info('SMS message formatted', [
            'message_length' => strlen($message),
            'message_preview' => substr($message, 0, 100) . '...',
            'phone_number' => $owner->phone_number
        ]);

        // Send SMS
        $smsService = new SmsService();
        $result = $smsService->sendSms($owner->phone_number, $message, [
            'shop_id' => $subshop->shop_id ?? null,
            'subshop_id' => $subshop->id ?? null,
            'owner_id' => $owner->id ?? null,
            'type' => 'purchase_return_cancel',
        ]);
        
        \Illuminate\Support\Facades\Log::info('SMS send result', [
            'result' => $result,
            'phone_number' => $owner->phone_number,
            'message_length' => strlen($message)
        ]);
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Purchase return deletion SMS notification attempted', [
            'return_id' => $return->id,
            'order_id' => $order->id,
            'owner_phone' => $owner->phone_number,
            'item_name' => $itemName,
            'quantity' => $return->quantity_returned,
            'return_value_restored' => $return->line_total,
            'refund_reversed' => $refundAmount,
            'sms_result' => $result
        ]);
    }

    public function items(PurchaseOrders $order)
    {
        $order->load('items');
        // map already returned per purchase_order_item_id
        $returned = PurchaseReturns::where('purchase_order_id', $order->id)
            ->groupBy('purchase_order_item_id')
            ->selectRaw('purchase_order_item_id, SUM(quantity_returned) as qty')
            ->pluck('qty','purchase_order_item_id');

        $items = $order->items->map(function($it) use ($returned){
            $already = (int)($returned[$it->id] ?? 0);
            $available = max(0, (int)$it->quantity - $already);
            return [
                'purchase_order_item_id' => $it->id,
                'item_id' => $it->item_id,
                'item_name' => $it->item_name,
                'unit' => $it->unit,
                'unit_price' => (float)$it->unit_price,
                'quantity' => (int)$it->quantity,
                'already_returned' => $already,
                'available' => $available,
                'vat_amount' => (float)$it->vat_amount,
                'base_amount' => (float)$it->base_amount,
                'line_total' => (float)$it->line_total,
            ];
        })->values();

        $paid = (float) PurchasesTransactions::where('purchase_order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);
        $status = $remaining <= 0 ? 'paid' : ($paid <= 0 ? 'pending' : 'partial');

        return response()->json([
            'order' => $order,
            'items' => $items,
            'summary' => [
                'paid' => round($paid, 2),
                'remaining' => round($remaining, 2),
                'status' => $status,
            ]
        ]);
    }

    public function store(Request $request, PurchaseOrders $order)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|integer|exists:purchase_orders_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:1000',
            'transaction_date' => 'required|date',
            'payment_method' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $order->load('items');
        $itemsById = $order->items->keyBy('id');

        $returned = PurchaseReturns::where('purchase_order_id', $order->id)
            ->groupBy('purchase_order_item_id')
            ->selectRaw('purchase_order_item_id, SUM(quantity_returned) as qty')
            ->pluck('qty','purchase_order_item_id');

        foreach ($data['items'] as $line) {
            $poiId = (int)$line['purchase_order_item_id'];
            $qty = (int)$line['quantity'];
            if (!isset($itemsById[$poiId])) {
                return response()->json(['message' => 'Invalid item in request'], 422);
            }
            $orig = (int)$itemsById[$poiId]->quantity;
            $already = (int)($returned[$poiId] ?? 0);
            $available = max(0, $orig - $already);
            if ($qty < 1 || $qty > $available) {
                return response()->json(['message' => 'Invalid quantity for item '.$poiId], 422);
            }
        }

        $returnTotal = 0.0;

        \DB::transaction(function () use ($order, $data, $itemsById, &$returnTotal) {
            $userId = optional(auth()->user())->id;

            foreach ($data['items'] as $line) {
                $poi = $itemsById[(int)$line['purchase_order_item_id']];
                $returnQty = (int)$line['quantity'];

                // per-unit amounts
                $unitBase = $poi->quantity > 0 ? ((float)$poi->base_amount / (int)$poi->quantity) : 0;
                $unitVat = $poi->quantity > 0 ? ((float)$poi->vat_amount / (int)$poi->quantity) : 0;
                $unitLine = $poi->quantity > 0 ? ((float)$poi->line_total / (int)$poi->quantity) : 0;

                $lineBase = round($unitBase * $returnQty, 2);
                $lineVat = round($unitVat * $returnQty, 2);
                $lineTotal = round($unitLine * $returnQty, 2);

                PurchaseReturns::create([
                    'subshop_id' => $order->subshop_id,
                    'purchase_order_id' => $order->id,
                    'purchase_order_item_id' => $poi->id,
                    'item_id' => $poi->item_id,
                    'quantity_returned' => $returnQty,
                    'unit_price' => (float)$poi->unit_price,
                    'base_amount' => $lineBase,
                    'vat_amount' => $lineVat,
                    'line_total' => $lineTotal,
                    'reason' => $data['reason'] ?? null,
                    'processed_by' => $userId,
                ]);

                // Decrease stock for purchase return
                if ($poi->item_id) {
                    // Item quantity (kept for backward-compat with existing logic)
                    Item::where('id', $poi->item_id)->decrement('quantity', $returnQty);

                    // Decrement from the specific batch used in the PO item, if available
                    if (!empty($poi->batch_number)) {
                        $batch = ItemBatch::where('item_id', $poi->item_id)
                            ->where('batch_number', $poi->batch_number)
                            ->lockForUpdate()
                            ->first();
                        if ($batch) {
                            $newQty = max(0, (int)$batch->quantity - $returnQty);
                            $batch->quantity = $newQty;
                            $batch->save();
                        }
                    }
                }

                // Update or delete purchase order item
                $newQty = (int)$poi->quantity - $returnQty;
                if ($newQty <= 0) {
                    $poi->delete();
                } else {
                    $poi->quantity = $newQty;
                    $poi->base_amount = round($unitBase * $newQty, 2);
                    $poi->vat_amount = round($unitVat * $newQty, 2);
                    $poi->line_total = round($unitLine * $newQty, 2);
                    $poi->save();
                }

                $returnTotal += $lineTotal;
            }

            // Recalculate order totals
            $items = PurchaseOrdersItems::where('purchase_order_id', $order->id)->get();
            $subtotal = (float)$items->sum('base_amount');
            $vat = (float)$items->sum('vat_amount');

            $discPercent = (float)($order->discount_percent ?? 0);
            $discCash = (float)($order->discount_cash ?? 0);
            $discountFromPercent = round($subtotal * ($discPercent/100), 2);
            $discountTotal = round($discountFromPercent + $discCash, 2);

            $grand = round($subtotal + $vat - $discountTotal, 2);

            $order->subtotal = (float)round($subtotal, 2);
            $order->vat_total = (float)round($vat, 2);
            $order->discount_total = (float)$discountTotal;
            $order->grand_total = (float)$grand;
            $order->save();

            if ($returnTotal > 0) {
                // Sum paid BEFORE creating refund/credit
                $paidBefore = PurchasesTransactions::where('purchase_order_id', $order->id)
                    ->where('transaction_type', 'payment')
                    ->sum('total_amount');
                // If paid exceeds new grand, record supplier refund up to returned value
                $excess = max(0, round($paidBefore - $order->grand_total, 2));
                $refundAmount = min(round($returnTotal, 2), $excess);

                if ($refundAmount > 0) {
                    $method = $data['payment_method'] ?? $order->payment_method;
                    if (!$method) {
                        abort(response()->json(['message' => 'Refund method is required to issue a refund.'], 422));
                    }
                    $txn = PurchasesTransactions::create([
                        'supplier_id' => $order->supplier_id,
                        'purchase_order_id' => $order->id,
                        'transaction_type' => 'payment',
                        'payment_method' => $method,
                        'total_amount' => -1 * $refundAmount,
                        'transaction_date' => $data['transaction_date'],
                        'reference_number' => $data['reference_number'] ?? $order->order_no,
                        'notes' => $data['notes'] ?? ($data['reason'] ?? 'Purchase return refund'),
                    ]);

                    PurchaseReturns::where('purchase_order_id', $order->id)
                        ->whereNull('transaction_id')
                        ->update(['transaction_id' => $txn->id]);
                }
            }
        });

        $paid = (float) PurchasesTransactions::where('purchase_order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);
        $status = $remaining <= 0 ? 'paid' : ($paid <= 0 ? 'pending' : 'partial');

        // Send SMS notification to shop owner about purchase return
        try {
            // Collect returned items information for SMS
            $returnedItems = [];
            foreach ($data['items'] as $line) {
                $poi = $itemsById[(int)$line['purchase_order_item_id']];
                $returnedItems[] = [
                    'name' => $poi->item_name,
                    'quantity' => (int)$line['quantity'],
                    'unit_price' => (float)$poi->unit_price,
                    'reason' => $data['reason'] ?? null,
                ];
            }
            
            // Calculate actual refund issued (negative amount in transactions)
            $refundAmount = 0;
            if ($returnTotal > 0) {
                $refundTxn = PurchasesTransactions::where('purchase_order_id', $order->id)
                    ->where('transaction_type', 'payment')
                    ->where('total_amount', '<', 0)
                    ->where('created_at', '>=', now()->subMinutes(1)) // Recently created
                    ->first();
                if ($refundTxn) {
                    $refundAmount = abs((float)$refundTxn->total_amount);
                }
            }
            
            $order->load(['subshop.shop.user', 'supplier']);
            $this->sendPurchaseReturnNotificationToOwner($order, $returnedItems, $returnTotal, $refundAmount);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send purchase return SMS notification: ' . $e->getMessage());
            // Don't fail the return recording if SMS fails
        }

        return response()->json([
            'success' => true,
            'paid' => round($paid,2),
            'remaining' => round($remaining,2),
            'status' => $status,
        ]);
    }

    /**
     * Send SMS notification to shop owner about purchase return recording
     */
    private function sendPurchaseReturnNotificationToOwner(PurchaseOrders $order, array $returnedItems, float $returnTotal, float $refundAmount = 0)
    {
        \Illuminate\Support\Facades\Log::info('=== START sendPurchaseReturnNotificationToOwner ===', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'return_total' => $returnTotal,
            'refund_amount' => $refundAmount,
            'items_count' => count($returnedItems),
        ]);
        
        // Get shop owner
        $subshop = $order->subshop;
        \Illuminate\Support\Facades\Log::info('Subshop check', [
            'subshop_exists' => $subshop ? true : false,
            'subshop_id' => $subshop ? $subshop->id : null,
            'shop_exists' => $subshop && $subshop->shop ? true : false,
            'user_exists' => $subshop && $subshop->shop && $subshop->shop->user ? true : false
        ]);
        
        if (!$subshop || !$subshop->shop || !$subshop->shop->user) {
            \Illuminate\Support\Facades\Log::warning('No shop owner found for purchase return', ['order_id' => $order->id]);
            return; // No owner found
        }

        $owner = $subshop->shop->user;
        \Illuminate\Support\Facades\Log::info('Owner check', [
            'owner_id' => $owner->id,
            'owner_name' => $owner->name,
            'phone_number' => $owner->phone_number
        ]);
        
        if (!$owner->phone_number) {
            \Illuminate\Support\Facades\Log::info('Shop owner has no phone number', ['order_id' => $order->id]);
            return; // Owner has no phone number
        }

        // Get return processor
        $processor = auth()->user();

        // Get supplier info
        $supplier = $order->supplier;

        // Format returned items summary
        $itemsSummary = '';
        foreach ($returnedItems as $item) {
            $itemsSummary .= $item['name'] . ' (' . $item['quantity'] . 'x ' . number_format((float)$item['unit_price'], 0) . '), ';
        }
        $itemsSummary = rtrim($itemsSummary, ', ');

        // Format SMS message
        $message = "PURCHASE RETURN RECORDED!\n" .
                  "Shop: {$subshop->name}\n" .
                  "Order: {$order->order_no}\n" .
                  "Processed by: " . ($processor ? $processor->name : 'System') . "\n" .
                  "Supplier: " . ($supplier ? $supplier->name : 'N/A') . "\n" .
                  "Items Returned: {$itemsSummary}\n" .
                  "Return Value: " . number_format($returnTotal, 2) . " TZS\n" .
                  "Refund Issued: " . number_format($refundAmount, 2) . " TZS\n" .
                  "Reason: " . (isset($returnedItems[0]['reason']) ? $returnedItems[0]['reason'] : 'N/A') . "\n" .
                  "Date Recorded: " . now()->format('d/m/Y') . "\n" .
                  "Time Recorded: " . now()->format('d/m/Y H:i');

        \Illuminate\Support\Facades\Log::info('SMS message formatted', [
            'message_length' => strlen($message),
            'message_preview' => substr($message, 0, 100) . '...',
            'phone_number' => $owner->phone_number
        ]);

        // Send SMS
        $smsService = new SmsService();
        $result = $smsService->sendSms($owner->phone_number, $message);
        
        \Illuminate\Support\Facades\Log::info('SMS send result', [
            'result' => $result,
            'phone_number' => $owner->phone_number,
            'message_length' => strlen($message)
        ]);
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Purchase return SMS notification attempted', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'return_value' => $returnTotal,
            'refund_issued' => $refundAmount,
            'items_returned' => $itemsSummary,
            'owner_phone' => $owner->phone_number,
            'sms_result' => $result
        ]);
    }

    public function print(PurchaseReturns $return)
    {
        $order = PurchaseOrders::with(['supplier','creator','subshop'])->findOrFail($return->purchase_order_id);

        $itemName = null; $unit = null;
        $poi = PurchaseOrdersItems::where('id', $return->purchase_order_item_id)->first();
        if ($poi) {
            $itemName = $poi->item_name;
            $unit = $poi->unit;
        } else if ($return->item_id) {
            $it = Item::withTrashed()->find($return->item_id);
            if ($it) { $itemName = $it->name; $unit = $it->unit; }
        }
        if (!$itemName) { $itemName = $return->item_id ? ('Item #'.$return->item_id) : 'Item'; }

        $refund = null;
        if ($return->transaction_id) {
            $refund = PurchasesTransactions::find($return->transaction_id);
        }

        $line = (object) [
            'item_name' => $itemName,
            'unit' => $unit,
            'unit_price' => (float)$return->unit_price,
            'quantity' => (int)$return->quantity_returned,
            'line_total' => (float)$return->line_total,
        ];

        return view('purchases.returns.receipt', [
            'order' => $order,
            'return' => $return,
            'line' => $line,
            'refund' => $refund,
        ]);
    }
}
