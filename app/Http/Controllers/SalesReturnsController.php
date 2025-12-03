<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubShop;
use App\Models\SalesReturns;
use App\Models\SalesOrdersItems;
use App\Models\SalesOrders;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\ItemBatch;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\SmsService;
use App\Models\PrinterSetting;
use App\Services\ReceiptPrinter;
use App\Jobs\PrintEscposJob;
use Illuminate\Support\Facades\Log;

class SalesReturnsController extends Controller
{
    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('returns.index')]);
    }

    /**
     * API: Print sales return via ESC/POS or return dummy payload
     */
    public function apiPrint(Request $request, SalesReturns $return, ReceiptPrinter $printer)
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
                $data = $printer->printReturn($return, $ps, true);
                return response()->json(['ok' => true, 'dummy' => true, 'data' => $data]);
            }
            $job = new PrintEscposJob('sales_return', (int)$return->id, (int)$ps->id);
            $jobId = $job->jobId;
            dispatch($job);
            Log::info('Sales return print queued', ['return_id' => $return->id, 'printer' => $ps->id, 'job_id' => $jobId]);
            return response()->json(['ok' => true, 'dummy' => false, 'queued' => true, 'job_id' => $jobId]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function export(Request $request, $format)
    {
        $subshopId = $request->query('subshop_id');
        if (!$subshopId) {
            return redirect()->route('returns.subshops');
        }
        $subshop = SubShop::findOrFail($subshopId);

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minTotal = $request->query('min_total');
        $maxTotal = $request->query('max_total');

        $base = SalesReturns::query()
            ->where('sales_returns.subshop_id', $subshopId)
            ->leftJoin('sales_orders', 'sales_orders.id', '=', 'sales_returns.sales_order_id')
            ->leftJoin('sales_orders_items', 'sales_orders_items.id', '=', 'sales_returns.sales_order_item_id')
            ->leftJoin('items', 'items.id', '=', 'sales_returns.item_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->leftJoin('users', 'users.id', '=', 'sales_returns.processed_by')
            ->leftJoin('transactions', 'transactions.id', '=', 'sales_returns.transaction_id')
            ->select(
                'sales_returns.*',
                'sales_orders.order_no',
                \DB::raw("COALESCE(sales_orders_items.item_name, items.name, CONCAT('Item #', IFNULL(sales_returns.item_id, 'N/A'))) as item_name"),
                \DB::raw("COALESCE(sales_orders_items.unit, items.unit, '-') as item_unit"),
                'customers.name as customer_name',
                'users.name as processed_by_name',
                'transactions.total_amount as refund_amount',
                'transactions.payment_method as refund_method',
                'transactions.transaction_date as refund_date'
            )
            ->when($q, function($query) use ($q){
                $query->where(function($qq) use ($q){
                    $qq->where('sales_orders.order_no', 'like', "%{$q}%")
                       ->orWhere('sales_returns.item_id', 'like', "%{$q}%")
                       ->orWhere('sales_returns.reason', 'like', "%{$q}%")
                       ->orWhere('transactions.payment_method', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom){ $query->whereDate('sales_returns.created_at', '>=', $dateFrom); })
            ->when($dateTo, function($query) use ($dateTo){ $query->whereDate('sales_returns.created_at', '<=', $dateTo); })
            ->when(is_numeric($minTotal), function($query) use ($minTotal){ $query->where('sales_returns.line_total', '>=', $minTotal); })
            ->when(is_numeric($maxTotal), function($query) use ($maxTotal){ $query->where('sales_returns.line_total', '<=', $maxTotal); })
            ->orderByDesc('sales_returns.created_at');

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
                'Content-Disposition' => 'attachment; filename="sales_returns_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            return Excel::download(new SalesReturnsExport($rows, $subshop, $summary), 'sales_returns_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $pdf = PDF::loadView('exports.sales_returns_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('sales_returns_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('returns.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('returns.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minTotal = $request->query('min_total');
        $maxTotal = $request->query('max_total');
        $sort = $request->query('sort', 'date_desc');

        $base = SalesReturns::query()
            ->where('sales_returns.subshop_id', $subshopId)
            ->leftJoin('sales_orders', 'sales_orders.id', '=', 'sales_returns.sales_order_id')
            ->leftJoin('sales_orders_items', 'sales_orders_items.id', '=', 'sales_returns.sales_order_item_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->leftJoin('users', 'users.id', '=', 'sales_returns.processed_by')
            ->leftJoin('transactions', 'transactions.id', '=', 'sales_returns.transaction_id')
            ->select(
                'sales_returns.*',
                'sales_orders.order_no',
                'sales_orders_items.item_name',
                'sales_orders_items.unit as item_unit',
                'customers.name as customer_name',
                'users.name as processed_by_name',
                'transactions.total_amount as refund_amount',
                'transactions.payment_method as refund_method',
                'transactions.transaction_date as refund_date'
            )
            ->when($q, function($query) use ($q){
                $query->where(function($qq) use ($q){
                    $qq->where('sales_orders.order_no', 'like', "%{$q}%")
                       ->orWhere('sales_returns.item_id', 'like', "%{$q}%")
                       ->orWhere('sales_returns.reason', 'like', "%{$q}%")
                       ->orWhere('transactions.payment_method', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom){ $query->whereDate('sales_returns.created_at', '>=', $dateFrom); })
            ->when($dateTo, function($query) use ($dateTo){ $query->whereDate('sales_returns.created_at', '<=', $dateTo); })
            ->when(is_numeric($minTotal), function($query) use ($minTotal){ $query->where('sales_returns.line_total', '>=', $minTotal); })
            ->when(is_numeric($maxTotal), function($query) use ($maxTotal){ $query->where('sales_returns.line_total', '<=', $maxTotal); });

        switch ($sort) {
            case 'amount_desc': $base->orderBy('sales_returns.line_total', 'desc'); break;
            case 'amount_asc': $base->orderBy('sales_returns.line_total', 'asc'); break;
            case 'date_asc': $base->orderBy('sales_returns.created_at', 'asc'); break;
            default: $base->orderBy('sales_returns.created_at', 'desc');
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

        return view('sales.returns.sales_returns', compact('subshop','returns','q','dateFrom','dateTo','minTotal','maxTotal','sort','summary'));
    }

    public function destroy(Request $request, SalesReturns $return)
    {
        \Illuminate\Support\Facades\Log::info('=== CONTROLLER DESTROY CALLED ===', [
            'return_id' => $return->id,
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString()
        ]);
        
        \Illuminate\Support\Facades\Log::info('SalesReturnsController destroy called', [
            'return_id' => $return->id,
            'request_wants_json' => $request->wantsJson()
        ]);
        
        try {
            \Illuminate\Support\Facades\Log::info('=== DESTROY METHOD STARTED ===', [
                'return_id' => $return->id,
                'user_id' => auth()->id(),
                'timestamp' => now()
            ]);
            
            $order = null;
            \DB::transaction(function () use ($return, &$order) {
                $order = SalesOrders::findOrFail($return->sales_order_id);

                // Load relationships before deletion for SMS notification
                $return->load(['subshop.shop.user', 'processor', 'salesOrderItem', 'item', 'order.customer', 'transaction']);

                // 1) Reverse stock increase: decrement item and batch quantities
                if ($return->item_id) {
                    Item::where('id', $return->item_id)->decrement('quantity', $return->quantity_returned);
                }

                // 2) Restore/recreate order item
                $soi = SalesOrdersItems::where('id', $return->sales_order_item_id)
                    ->where('sales_order_id', $order->id)
                    ->first();

                if (!$soi) {
                    // Recreate the missing order item from the return record and item catalogue
                    $itm = $return->item_id ? Item::find($return->item_id) : null;
                    $soi = new SalesOrdersItems();
                    $soi->sales_order_id = $order->id;
                    $soi->item_id = $return->item_id;
                    $soi->item_name = $itm->name ?? ('Item #'.$return->item_id);
                    $soi->unit = $itm->unit ?? null;
                    $soi->unit_price = (float)$return->unit_price;
                    $soi->quantity = 0; // will be set below
                    // Set VAT fields to neutral defaults; amounts set below
                    $soi->vat_type = 'none';
                    $soi->vat_rate = 0;
                    $soi->vat_amount = 0;
                    $soi->base_amount = 0;
                    $soi->line_total = 0;
                    $soi->save();
                }

                // Decrement the specific batch used on the sales line if present
                if (!empty($soi->batch_id)) {
                    $batch = ItemBatch::where('id', $soi->batch_id)->lockForUpdate()->first();
                    if ($batch) {
                        $newQty = max(0, (int)$batch->quantity - (int)$return->quantity_returned);
                        $batch->quantity = $newQty;
                        $batch->save();
                    }
                }

                $origQty = max(1, (int)$soi->quantity);
                // per-unit values from current line
                // If current line has zero amounts (newly created), derive from return record
                $unitBase = $origQty > 0 ? ((float)$soi->base_amount / $origQty) : 0;
                $unitVat = $origQty > 0 ? ((float)$soi->vat_amount / $origQty) : 0;
                $unitLine = $origQty > 0 ? ((float)$soi->line_total / $origQty) : 0;
                if ($soi->base_amount == 0 && $soi->vat_amount == 0 && $soi->line_total == 0 && (int)$return->quantity_returned > 0) {
                    $unitBase = round(((float)$return->base_amount) / (int)$return->quantity_returned, 2);
                    $unitVat = round(((float)$return->vat_amount) / (int)$return->quantity_returned, 2);
                    $unitLine = round(((float)$return->line_total) / (int)$return->quantity_returned, 2);
                }

                $newQty = (int)$soi->quantity + (int)$return->quantity_returned;
                $soi->quantity = $newQty;
                $soi->base_amount = round($unitBase * $newQty, 2);
                $soi->vat_amount = round($unitVat * $newQty, 2);
                $soi->line_total = round($unitLine * $newQty, 2);
                $soi->save();

                // 3) Recalculate order totals
                $items = SalesOrdersItems::where('sales_order_id', $order->id)->get();
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

                // 4) If this return created a standalone refund transaction, remove it
                if ($return->transaction_id) {
                    $countLinked = SalesReturns::where('transaction_id', $return->transaction_id)->count();
                    if ($countLinked === 1) {
                        Transaction::where('id', $return->transaction_id)->delete();
                    }
                }

                // 5) Finally delete the return record
                $return->delete();
            });

            // Send SMS notification to shop owner about return deletion
            try {
                $this->sendReturnDeletionNotificationToOwner($return, $order);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send return deletion SMS notification: ' . $e->getMessage());
                // Don't fail the deletion if SMS fails
            }

            if ($request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->back()->with('success', 'Sales return deleted successfully.');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function print(SalesReturns $return)
    {
        $order = SalesOrders::with(['customer','creator','subshop'])->findOrFail($return->sales_order_id);

        $itemName = null; $unit = null;
        $soi = SalesOrdersItems::where('id', $return->sales_order_item_id)->first();
        if ($soi) {
            $itemName = $soi->item_name;
            $unit = $soi->unit;
        } else if ($return->item_id) {
            $it = Item::withTrashed()->find($return->item_id);
            if ($it) { $itemName = $it->name; $unit = $it->unit; }
        }
        if (!$itemName) { $itemName = $return->item_id ? ('Item #'.$return->item_id) : 'Item'; }

        $refund = null;
        if ($return->transaction_id) {
            $refund = Transaction::find($return->transaction_id);
        }

        $line = (object) [
            'item_name' => $itemName,
            'unit' => $unit,
            'unit_price' => (float)$return->unit_price,
            'quantity' => (int)$return->quantity_returned,
            'line_total' => (float)$return->line_total,
        ];

        return view('sales.returns.receipt', [
            'order' => $order,
            'return' => $return,
            'line' => $line,
            'refund' => $refund,
        ]);
    }

    /**
     * Send SMS notification to shop owner about sales return deletion (which restores order state)
     */
    private function sendReturnDeletionNotificationToOwner(SalesReturns $return, SalesOrders $order)
    {
        \Illuminate\Support\Facades\Log::info('=== START sendReturnDeletionNotificationToOwner ===', [
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
            \Illuminate\Support\Facades\Log::warning('No shop owner found for return', ['return_id' => $return->id]);
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
        $salesOrderItem = $return->salesOrderItem;
        if ($salesOrderItem && $salesOrderItem->item_name) {
            $itemName = $salesOrderItem->item_name;
        } elseif ($return->item) {
            $itemName = $return->item->name;
        }

        // Get customer info from order relationship
        $order = $return->order;
        $customer = $order ? $order->customer : null;

        // Get refund information using transaction relationship
        $refundAmount = 0;
        if ($return->transaction) {
            $refundAmount = abs($return->transaction->total_amount); // Refund amounts are negative
        }

        // Format SMS message
        $message = "SALES RETURN CANCELLED!\n" .
                  "Shop: {$subshop->name}\n" .
                  "Order: {$order->order_no}\n" .
                  "Cancelled by: " . ($processor ? $processor->name : 'System') . "\n" .
                  "Customer: " . ($customer ? $customer->name : 'N/A') . "\n" .
                  "Item Returned: {$itemName} ({$return->quantity_returned}x)\n" .
                  "Return Value: " . number_format($return->line_total, 2) . " TZS\n" .
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
            'type' => 'sales_return_cancel',
        ]);
        
        \Illuminate\Support\Facades\Log::info('SMS send result', [
            'result' => $result,
            'phone_number' => $owner->phone_number,
            'message_length' => strlen($message)
        ]);
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Return deletion SMS notification attempted', [
            'return_id' => $return->id,
            'order_id' => $order->id,
            'owner_phone' => $owner->phone_number,
            'item_name' => $itemName,
            'quantity' => $return->quantity_returned,
            'return_value' => $return->line_total,
            'refund_reversed' => $refundAmount,
            'sms_result' => $result
        ]);
    }
}
