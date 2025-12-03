<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubShop;
use App\Models\SalesOrders;
use App\Models\SalesOrdersItems;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Exports\InvoicesExport;
use App\Models\SalesReturns;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\ItemBatch;
use App\Models\User;
use App\Services\SmsService;
use App\Models\PrinterSetting;
use App\Services\ReceiptPrinter;
use App\Jobs\PrintEscposJob;
use Illuminate\Support\Facades\Log;

class InvoicesController extends Controller
{
    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('invoices.index')]);
    }

    /**
     * API: Print invoice to default subshop printer or return dummy payload
     */
    public function apiPrint(Request $request, SalesOrders $order, ReceiptPrinter $printer)
    {
        $subshopId = (int) $request->session()->get('subshop_id');
        if (!$subshopId || $order->subshop_id !== $subshopId) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }
        $ps = PrinterSetting::where('subshop_id', $subshopId)->orderByDesc('is_default')->first();
        if (!$ps) {
            return response()->json(['ok' => false, 'error' => 'No printer configured for this shop'], 422);
        }
        try {
            $dummy = (bool) $request->boolean('dummy');
            if ($dummy) {
                $data = $printer->printInvoice($order, $ps, true);
                return response()->json(['ok' => true, 'dummy' => true, 'data' => $data]);
            }
            $job = new PrintEscposJob('invoice', (int)$order->id, (int)$ps->id);
            $jobId = $job->jobId;
            dispatch($job);
            Log::info('Invoice print queued', ['order_id' => $order->id, 'printer' => $ps->id, 'job_id' => $jobId]);
            return response()->json(['ok' => true, 'dummy' => false, 'queued' => true, 'job_id' => $jobId]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('invoices.index')])
                ->with('error', 'Please select a shop first.');
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        if ($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('invoices.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minTotal = $request->query('min_total');
        $maxTotal = $request->query('max_total');
        $status = $request->query('status');

        $paymentsSub = Transaction::selectRaw('order_id, SUM(total_amount) as paid_total')
            ->where('transaction_type', 'payment')
            ->groupBy('order_id');

        $base = SalesOrders::with(['customer','creator','items'])
            ->where('subshop_id', $subshopId)
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.order_id', '=', 'sales_orders.id');
            })
            ->select('sales_orders.*', \DB::raw('COALESCE(pays.paid_total,0) as paid_total'), \DB::raw('(sales_orders.grand_total - COALESCE(pays.paid_total,0)) as remaining_total'))
            ->when($q, function($query) use ($q){
                $query->where(function($qq) use ($q){
                    $qq->where('sales_orders.order_no', 'like', "%{$q}%")
                       ->orWhere('sales_orders.payment_method', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom){ $query->whereDate('sales_orders.created_at', '>=', $dateFrom); })
            ->when($dateTo, function($query) use ($dateTo){ $query->whereDate('sales_orders.created_at', '<=', $dateTo); })
            ->when(is_numeric($minTotal), function($query) use ($minTotal){ $query->where('sales_orders.grand_total', '>=', $minTotal); })
            ->when(is_numeric($maxTotal), function($query) use ($maxTotal){ $query->where('sales_orders.grand_total', '<=', $maxTotal); })
            ->when($status, function($query) use ($status){
                if ($status === 'paid') {
                    $query->whereRaw('(sales_orders.grand_total - COALESCE(pays.paid_total,0)) <= 0');
                } elseif ($status === 'pending') {
                    $query->whereRaw('COALESCE(pays.paid_total,0) <= 0');
                } elseif ($status === 'partial') {
                    $query->whereRaw('COALESCE(pays.paid_total,0) > 0 AND (sales_orders.grand_total - COALESCE(pays.paid_total,0)) > 0');
                }
            })
            ->orderByDesc('sales_orders.created_at');

        $rows = $base->get();

        if ($format === 'csv') {
            return response()->stream(function () use ($rows) {
                $h = fopen('php://output', 'w');
                fputcsv($h, [
                    'Order No','Date','Customer','Items','Subtotal','VAT','Discount','Grand','Paid','Remaining','Status','Cashier','Notes'
                ]);
                foreach ($rows as $o) {
                    $paid = (float)$o->paid_total; $remain = max(0, (float)$o->grand_total - $paid);
                    $status = $remain <= 0 ? 'PAID' : ($paid <= 0 ? 'PENDING' : 'PARTIAL');
                    fputcsv($h, [
                        $o->order_no,
                        optional($o->created_at)->format('Y-m-d H:i:s'),
                        optional($o->customer)->name ?? '-',
                        $o->items->sum('quantity'),
                        number_format((float)$o->subtotal,2,'.',''),
                        number_format((float)$o->vat_total,2,'.',''),
                        number_format((float)$o->discount_total,2,'.',''),
                        number_format((float)$o->grand_total,2,'.',''),
                        number_format($paid,2,'.',''),
                        number_format($remain,2,'.',''),
                        $status,
                        optional($o->creator)->name ?? '-',
                        $o->notes
                    ]);
                }
                fclose($h);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="invoices_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            return Excel::download(new InvoicesExport($rows), 'invoices_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = SubShop::find($subshopId);
            $summary = [
                'count' => $rows->count(),
                'grand_total' => (float) $rows->sum('grand_total'),
                'paid_total' => (float) $rows->sum('paid_total'),
                'remaining_total' => (float) $rows->sum(function($r){ return max(0, (float)$r->grand_total - (float)$r->paid_total); }),
            ];
            $pdf = PDF::loadView('exports.invoices_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('invoices_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    public function print(SalesOrders $order)
    {
        $order->load(['items','customer','creator','subshop']);
        $paid = (float) Transaction::where('order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);
        $status = $remaining <= 0 ? 'paid' : ($paid <= 0 ? 'pending' : 'partial');

        return view('sales.invoices.receipt', [
            'order' => $order,
            'items' => $order->items,
            'paid' => $paid,
            'remaining' => $remaining,
            'status' => $status,
        ]);
    }

    public function destroy(SalesOrders $order)
    {
        try {
            \DB::transaction(function () use ($order) {
                // delete items (use correct FK and relationship)
                $order->items()->delete();
                // delete transactions linked to this order
                Transaction::where('order_id', $order->id)->delete();
                // delete order
                $order->delete();
            });

            if (request()->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->back()->with('success', 'Invoice deleted successfully.');
        } catch (\Throwable $e) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to delete invoice.');
        }
    }

    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('invoices.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('invoices.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minTotal = $request->query('min_total');
        $maxTotal = $request->query('max_total');
        $status = $request->query('status'); // paid|partial|pending
        $sort = $request->query('sort', 'date_desc'); // date_desc|date_asc|grand_desc|grand_asc|remain_desc|remain_asc

        // payments subquery for sums
        $paymentsSub = Transaction::selectRaw('order_id, SUM(total_amount) as paid_total')
            ->where('transaction_type', 'payment')
            ->groupBy('order_id');

        $base = SalesOrders::with(['items','customer','creator'])
            ->where('subshop_id', $subshopId)
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.order_id', '=', 'sales_orders.id');
            })
            ->select('sales_orders.*', \DB::raw('COALESCE(pays.paid_total,0) as paid_total'), \DB::raw('(sales_orders.grand_total - COALESCE(pays.paid_total,0)) as remaining_total'))
            ->when($q, function($query) use ($q){
                $query->where(function($qq) use ($q){
                    $qq->where('sales_orders.order_no', 'like', "%{$q}%")
                       ->orWhere('sales_orders.payment_method', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom){ $query->whereDate('sales_orders.created_at', '>=', $dateFrom); })
            ->when($dateTo, function($query) use ($dateTo){ $query->whereDate('sales_orders.created_at', '<=', $dateTo); })
            ->when(is_numeric($minTotal), function($query) use ($minTotal){ $query->where('sales_orders.grand_total', '>=', $minTotal); })
            ->when(is_numeric($maxTotal), function($query) use ($maxTotal){ $query->where('sales_orders.grand_total', '<=', $maxTotal); })
            ->when($status, function($query) use ($status){
                if ($status === 'paid') {
                    $query->whereRaw('(sales_orders.grand_total - COALESCE(pays.paid_total,0)) <= 0');
                } elseif ($status === 'pending') {
                    $query->whereRaw('COALESCE(pays.paid_total,0) <= 0');
                } elseif ($status === 'partial') {
                    $query->whereRaw('COALESCE(pays.paid_total,0) > 0 AND (sales_orders.grand_total - COALESCE(pays.paid_total,0)) > 0');
                }
            });

        // Sorting
        switch ($sort) {
            case 'date_asc': $base->orderBy('sales_orders.created_at', 'asc'); break;
            case 'grand_desc': $base->orderBy('sales_orders.grand_total', 'desc'); break;
            case 'grand_asc': $base->orderBy('sales_orders.grand_total', 'asc'); break;
            case 'remain_desc': $base->orderBy('remaining_total', 'desc'); break;
            case 'remain_asc': $base->orderBy('remaining_total', 'asc'); break;
            default: $base->orderBy('sales_orders.created_at', 'desc');
        }

        // Aggregates for summary cards (clone without pagination limits)
        $agg = (clone $base)->get();
        $summary = [
            'count' => $agg->count(),
            'grand_total' => (float) $agg->sum('grand_total'),
            'paid_total' => (float) $agg->sum('paid_total'),
            'remaining_total' => (float) $agg->sum('remaining_total'),
        ];

        // Fetch all filtered orders for frontend (DataTables) pagination
        $orders = $base->get();

        // Paid map for all filtered orders
        $paidMap = [];
        if ($orders->count() > 0) {
            $orderIds = $orders->pluck('id');
            $rows = Transaction::whereIn('order_id', $orderIds)
                ->where('transaction_type', 'payment')
                ->groupBy('order_id')
                ->selectRaw('order_id, SUM(total_amount) as paid_total')
                ->get();
            foreach ($rows as $r) { $paidMap[$r->order_id] = (float)$r->paid_total; }
        }

        // Banks for payment method select
        $banks = \App\Models\Banks::where('is_active', true)->where('subshop_id', $subshopId)->orderBy('name')->get(['id','name']);

        // Include filters and summary in view
        return view('sales.invoices.invoices', compact('subshop', 'orders', 'q', 'paidMap', 'banks', 'dateFrom', 'dateTo', 'minTotal', 'maxTotal', 'status', 'sort', 'summary'));
    }

    public function returnItems(SalesOrders $order)
    {
        $order->load('items');
        // map of already returned per sales_order_item_id
        if (\Schema::hasTable('sales_returns')) {
            $returned = SalesReturns::where('sales_order_id', $order->id)
                ->groupBy('sales_order_item_id')
                ->selectRaw('sales_order_item_id, SUM(quantity_returned) as qty')
                ->pluck('qty','sales_order_item_id');
        } else {
            $returned = collect();
        }
        // Build items payload with available qty
        $items = $order->items->map(function($it) use ($returned){
            $already = (int)($returned[$it->id] ?? 0);
            $available = max(0, (int)$it->quantity - $already);
            return [
                'sales_order_item_id' => $it->id,
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

        $paid = (float) Transaction::where('order_id', $order->id)
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

    public function storeReturn(Request $request, SalesOrders $order)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.sales_order_item_id' => 'required|integer|exists:sales_orders_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:1000',
            'transaction_date' => 'required|date',
            'payment_method' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $order->load('items');
        $itemsById = $order->items->keyBy('id');

        $returned = SalesReturns::where('sales_order_id', $order->id)
            ->groupBy('sales_order_item_id')
            ->selectRaw('sales_order_item_id, SUM(quantity_returned) as qty')
            ->pluck('qty','sales_order_item_id');

        foreach ($data['items'] as $line) {
            $soiId = (int)$line['sales_order_item_id'];
            $qty = (int)$line['quantity'];
            if (!isset($itemsById[$soiId])) {
                return response()->json(['message' => 'Invalid item in request'], 422);
            }
            $orig = (int)$itemsById[$soiId]->quantity;
            $already = (int)($returned[$soiId] ?? 0);
            $available = max(0, $orig - $already);
            if ($qty < 1 || $qty > $available) {
                return response()->json(['message' => 'Invalid quantity for item '.$soiId], 422);
            }
        }

        $refundTotal = 0.0;

        \DB::transaction(function () use ($order, $data, $itemsById, &$refundTotal) {
            $userId = optional(auth()->user())->id;

            foreach ($data['items'] as $line) {
                $soi = $itemsById[(int)$line['sales_order_item_id']];
                $returnQty = (int)$line['quantity'];

                $unitBase = $soi->quantity > 0 ? ((float)$soi->base_amount / (int)$soi->quantity) : 0;
                $unitVat = $soi->quantity > 0 ? ((float)$soi->vat_amount / (int)$soi->quantity) : 0;
                $unitLine = $soi->quantity > 0 ? ((float)$soi->line_total / (int)$soi->quantity) : 0;

                $lineBase = round($unitBase * $returnQty, 2);
                $lineVat = round($unitVat * $returnQty, 2);
                $lineTotal = round($unitLine * $returnQty, 2);

                SalesReturns::create([
                    'subshop_id' => $order->subshop_id,
                    'sales_order_id' => $order->id,
                    'sales_order_item_id' => $soi->id,
                    'item_id' => $soi->item_id,
                    'quantity_returned' => $returnQty,
                    'unit_price' => (float)$soi->unit_price,
                    'base_amount' => $lineBase,
                    'vat_amount' => $lineVat,
                    'line_total' => $lineTotal,
                    'reason' => $data['reason'] ?? null,
                    'processed_by' => $userId,
                ]);

                Item::where('id', $soi->item_id)->increment('quantity', $returnQty);

                // Also increment the specific batch used on the sales line, if any
                if (!empty($soi->batch_id)) {
                    $batch = ItemBatch::where('id', $soi->batch_id)
                        ->lockForUpdate()
                        ->first();
                    if ($batch) {
                        $batch->quantity = (int)$batch->quantity + $returnQty;
                        $batch->save();
                    }
                }

                $newQty = (int)$soi->quantity - $returnQty;
                if ($newQty <= 0) {
                    $soi->delete();
                } else {
                    $soi->quantity = $newQty;
                    $soi->base_amount = round($unitBase * $newQty, 2);
                    $soi->vat_amount = round($unitVat * $newQty, 2);
                    $soi->line_total = round($unitLine * $newQty, 2);
                    $soi->save();
                }

                $refundTotal += $lineTotal;
            }

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

            if ($refundTotal > 0) {
                // Sum paid BEFORE creating refund, using updated order total
                $paidBefore = Transaction::where('order_id', $order->id)
                    ->where('transaction_type', 'payment')
                    ->sum('total_amount');
                // Only refund the overpaid portion (paid - new grand total), capped by returned value
                $excess = max(0, round($paidBefore - $order->grand_total, 2));
                $refundAmount = min(round($refundTotal, 2), $excess);

                if ($refundAmount > 0) {
                    // Determine refund method; require one if not available
                    $method = $data['payment_method'] ?? $order->payment_method;
                    if (!$method) {
                        abort(response()->json(['message' => 'Refund method is required to issue a refund.'], 422));
                    }
                    $txn = Transaction::create([
                        'item_id' => null,
                        'customer_id' => $order->customer_id,
                        'order_id' => $order->id,
                        'transaction_type' => 'payment',
                        'payment_method' => $method,
                        'total_amount' => -1 * $refundAmount,
                        'transaction_date' => $data['transaction_date'],
                        'reference_number' => $data['reference_number'] ?? $order->order_no,
                        'notes' => $data['notes'] ?? ($data['reason'] ?? 'Sales return refund'),
                    ]);

                    SalesReturns::where('sales_order_id', $order->id)
                        ->whereNull('transaction_id')
                        ->update(['transaction_id' => $txn->id]);
                }
            }
        });

        // Send SMS notification to shop owner about sales return
        try {
            $this->sendReturnNotificationToOwner($order, $data, $refundTotal);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send return SMS notification: ' . $e->getMessage());
            // Don't fail the return if SMS fails
        }

        $paid = (float) Transaction::where('order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);
        $status = $remaining <= 0 ? 'paid' : ($paid <= 0 ? 'pending' : 'partial');

        return response()->json([
            'success' => true,
            'paid' => round($paid,2),
            'remaining' => round($remaining,2),
            'status' => $status,
        ]);
    }

    public function apiOrder(SalesOrders $order)
    {
        $order->load('items');
        $paid = (float) Transaction::where('order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);
        $status = $remaining <= 0 ? 'paid' : ($paid <= 0 ? 'pending' : 'partial');
        return response()->json([
            'order' => $order,
            'items' => $order->items,
            'paid' => round($paid, 2),
            'remaining' => round($remaining, 2),
            'status' => $status,
        ]);
    }

    public function storePayment(Request $request, SalesOrders $order)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'payment_method' => 'required|string|max:100',
        ]);

        // Sum existing payments
        $paid = (float) Transaction::where('order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);

        if ($remaining <= 0) {
            return response()->json(['message' => 'Order already fully paid'], 422);
        }

        $amount = min((float)$data['amount'], $remaining);

        Transaction::create([
            'item_id' => null,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'created_by' => auth()->user()->id,
            'transaction_type' => 'payment',
            'payment_method' => $data['payment_method'],
            'total_amount' => $amount,
            'transaction_date' => $data['transaction_date'],
            'reference_number' => $data['reference_number'] ?? $order->order_no,
            'notes' => $data['notes'] ?? null,
        ]);

        $newPaid = $paid + $amount;
        $newRemaining = max(0, (float)$order->grand_total - $newPaid);

        // Send SMS notification to shop owner about payment
        try {
            $this->sendPaymentNotificationToOwner($order, $data, $amount, $newPaid, $newRemaining);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send payment SMS notification: ' . $e->getMessage());
            // Don't fail the payment if SMS fails
        }

        $status = $newRemaining <= 0 ? 'paid' : ($newPaid <= 0 ? 'pending' : 'partial');

        return response()->json([
            'success' => true,
            'paid' => round($newPaid, 2),
            'remaining' => round($newRemaining, 2),
            'status' => $status,
        ]);
    }

    public function payments(SalesOrders $order)
    {
        $payments = Transaction::with('user')->where('order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->orderByDesc('id')
            ->get(['id','total_amount','transaction_date','payment_method','reference_number','notes','created_at','created_by']);
        $paid = (float) $payments->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);
        $status = $remaining <= 0 ? 'paid' : ($paid <= 0 ? 'pending' : 'partial');
        return response()->json([
            'payments' => $payments,
            'summary' => [
                'paid' => round($paid,2),
                'remaining' => round($remaining,2),
                'status' => $status,
            ]
        ]);
    }

    /**
     * Send SMS notification to shop owner about payment received
     */
    private function sendPaymentNotificationToOwner(SalesOrders $order, array $paymentData, float $amount, float $totalPaid, float $remainingAmount)
    {
        \Illuminate\Support\Facades\Log::info('sendPaymentNotificationToOwner called', [
            'order_id' => $order->id,
            'amount' => $amount,
            'total_paid' => $totalPaid,
            'remaining' => $remainingAmount
        ]);
        
        // Get shop owner
        $subshop = $order->subshop;
        if (!$subshop || !$subshop->shop || !$subshop->shop->user) {
            return; // No owner found
        }

        $owner = $subshop->shop->user;
        if (!$owner->phone_number) {
            return; // Owner has no phone number
        }

        // Get payment recorder
        $recorder = auth()->user();

        // Determine status based on remaining amount
        $status = $remainingAmount <= 0 ? 'Complete' : 'Partial';

        // Format SMS message
        $message = "PAYMENT RECEIVED!\n" .
                  "Shop: {$subshop->name}\n" .
                  "Order: {$order->order_no}\n" .
                  "Recorded by: " . ($recorder ? $recorder->name : 'System') . "\n" .
                  "Amount: " . number_format($amount, 2) . " TZS\n" .
                  "Remaining: " . number_format($remainingAmount, 2) . " TZS\n" .
                  "Status: {$status}\n" .
                  "Date: " . \Carbon\Carbon::parse($paymentData['transaction_date'])->format('d/m/Y') . "\n" .
                  "Reference: " . ($paymentData['reference_number'] ?: 'N/A') . "\n" .
                  "Payment Method: {$paymentData['payment_method']}\n" .
                  "Notes: " . ($paymentData['notes'] ?: 'N/A') . "\n" .
                  "Time Recorded: " . now()->format('d/m/Y H:i');

        // Send SMS
        $smsService = new SmsService();
        $result = $smsService->sendSms($owner->phone_number, $message, [
            'shop_id' => $subshop->shop_id ?? null,
            'subshop_id' => $subshop->id ?? null,
            'owner_id' => $owner->id ?? null,
            'type' => 'payment',
            'order_id' => $order->id,
            'amount' => $amount,
            'total_paid' => $totalPaid,
            'remaining' => $remainingAmount,
        ]);
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Payment SMS notification attempted', [
            'order_id' => $order->id,
            'owner_phone' => $owner->phone_number,
            'amount' => $amount,
            'remaining' => $remainingAmount,
            'status' => $status,
            'sms_result' => $result
        ]);
    }

    /**
     * Send SMS notification to shop owner about sales return processed
     */
    private function sendReturnNotificationToOwner(SalesOrders $order, array $returnData, float $refundTotal)
    {
        \Illuminate\Support\Facades\Log::info('sendReturnNotificationToOwner called', [
            'order_id' => $order->id,
            'refund_total' => $refundTotal
        ]);
        
        // Get shop owner
        $subshop = $order->subshop;
        if (!$subshop || !$subshop->shop || !$subshop->shop->user) {
            return; // No owner found
        }

        $owner = $subshop->shop->user;
        if (!$owner->phone_number) {
            return; // Owner has no phone number
        }

        // Get return processor
        $processor = auth()->user();

        // Get return items summary with names
        $returnItems = collect($returnData['items']);
        $totalQuantity = $returnItems->sum('quantity');
        
        // Get item names from sales order items
        $soiIds = $returnItems->pluck('sales_order_item_id')->toArray();
        $salesOrderItems = SalesOrdersItems::whereIn('id', $soiIds)->get()->keyBy('id');
        
        // Format items list (limit for SMS)
        $itemsText = '';
        $items = $returnItems->take(3); // Limit to 3 items for SMS
        foreach ($items as $item) {
            $soi = $salesOrderItems->get($item['sales_order_item_id']);
            $itemName = $soi ? $soi->item_name : 'Item';
            $itemsText .= "{$itemName} ({$item['quantity']}x), ";
        }
        if ($returnItems->count() > 3) {
            $itemsText .= ' +' . ($returnItems->count() - 3) . ' more items';
        } else {
            $itemsText = rtrim($itemsText, ', ');
        }

        // Get customer info
        $customer = $order->customer;

        // Format SMS message
        $message = "SALES RETURN PROCESSED!\n" .
                  "Shop: {$subshop->name}\n" .
                  "Order: {$order->order_no}\n" .
                  "Processed by: " . ($processor ? $processor->name : 'System') . "\n" .
                  "Customer: " . ($customer ? $customer->name : 'N/A') . "\n" .
                  "Items Returned: {$itemsText}\n" .
                  "Total Quantity: {$totalQuantity}\n" .
                  "Refund Amount: " . number_format($refundTotal, 2) . " TZS\n" .
                  "Reason: " . ($returnData['reason'] ?: 'N/A') . "\n" .
                  "Date: " . \Carbon\Carbon::parse($returnData['transaction_date'])->format('d/m/Y') . "\n" .
                  "Reference: " . ($returnData['reference_number'] ?: 'N/A') . "\n" .
                  "Payment Method: " . ($returnData['payment_method'] ?: 'N/A') . "\n" .
                  "Notes: " . ($returnData['notes'] ?: 'N/A') . "\n" .
                  "Time Processed: " . now()->format('d/m/Y H:i');

        // Send SMS
        $smsService = new SmsService();
        $result = $smsService->sendSms($owner->phone_number, $message, [
            'shop_id' => $subshop->shop_id ?? null,
            'subshop_id' => $subshop->id ?? null,
            'owner_id' => $owner->id ?? null,
            'type' => 'sales_return',
        ]);
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Return SMS notification attempted', [
            'order_id' => $order->id,
            'owner_phone' => $owner->phone_number,
            'refund_total' => $refundTotal,
            'total_quantity' => $totalQuantity,
            'sms_result' => $result
        ]);
    }
}
