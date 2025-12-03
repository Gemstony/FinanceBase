<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubShop;
use App\Models\PurchaseOrders;
use App\Models\PurchasesTransactions;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PurchaseOrdersExport;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Services\SmsService;
use App\Models\PrinterSetting;
use App\Services\ReceiptPrinter;
use App\Jobs\PrintEscposJob;
use Illuminate\Support\Facades\Log;

class PurchaseOrdersController extends Controller
{
    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('purchase_orders.index')]);
    }

    /**
     * API: Print purchase order via ESC/POS or return dummy payload
     */
    public function apiPrint(Request $request, PurchaseOrders $order, ReceiptPrinter $printer)
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
                $data = $printer->printPurchase($order, $ps, true);
                return response()->json(['ok' => true, 'dummy' => true, 'data' => $data]);
            }
            $job = new PrintEscposJob('purchase', (int)$order->id, (int)$ps->id);
            $jobId = $job->jobId;
            dispatch($job);
            Log::info('Purchase order print queued', ['order_id' => $order->id, 'printer' => $ps->id, 'job_id' => $jobId]);
            return response()->json(['ok' => true, 'dummy' => false, 'queued' => true, 'job_id' => $jobId]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('purchase_orders.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('purchase_orders.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minTotal = $request->query('min_total');
        $maxTotal = $request->query('max_total');
        $status = $request->query('status'); // paid|partial|pending
        $sort = $request->query('sort', 'date_desc'); // date_desc|date_asc|grand_desc|grand_asc|remain_desc|remain_asc

        $paymentsSub = PurchasesTransactions::selectRaw('purchase_order_id, SUM(total_amount) as paid_total')
            ->where('transaction_type', 'payment')
            ->groupBy('purchase_order_id');

        $base = PurchaseOrders::with(['supplier','creator'])
            ->where('subshop_id', $subshopId)
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.purchase_order_id', '=', 'purchase_orders.id');
            })
            ->select('purchase_orders.*', \DB::raw('COALESCE(pays.paid_total,0) as paid_total'), \DB::raw('(purchase_orders.grand_total - COALESCE(pays.paid_total,0)) as remaining_total'))
            ->when($q, function($query) use ($q){
                $query->where(function($qq) use ($q){
                    $qq->where('purchase_orders.order_no', 'like', "%{$q}%")
                       ->orWhere('purchase_orders.payment_method', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom){ $query->whereDate('purchase_orders.created_at', '>=', $dateFrom); })
            ->when($dateTo, function($query) use ($dateTo){ $query->whereDate('purchase_orders.created_at', '<=', $dateTo); })
            ->when(is_numeric($minTotal), function($query) use ($minTotal){ $query->where('purchase_orders.grand_total', '>=', $minTotal); })
            ->when(is_numeric($maxTotal), function($query) use ($maxTotal){ $query->where('purchase_orders.grand_total', '<=', $maxTotal); })
            ->when($status, function($query) use ($status){
                if ($status === 'paid') {
                    $query->whereRaw('(purchase_orders.grand_total - COALESCE(pays.paid_total,0)) <= 0');
                } elseif ($status === 'pending') {
                    $query->whereRaw('COALESCE(pays.paid_total,0) <= 0');
                } elseif ($status === 'partial') {
                    $query->whereRaw('COALESCE(pays.paid_total,0) > 0 AND (purchase_orders.grand_total - COALESCE(pays.paid_total,0)) > 0');
                }
            });

        switch ($sort) {
            case 'date_asc': $base->orderBy('purchase_orders.created_at', 'asc'); break;
            case 'grand_desc': $base->orderBy('purchase_orders.grand_total', 'desc'); break;
            case 'grand_asc': $base->orderBy('purchase_orders.grand_total', 'asc'); break;
            case 'remain_desc': $base->orderBy('remaining_total', 'desc'); break;
            case 'remain_asc': $base->orderBy('remaining_total', 'asc'); break;
            default: $base->orderBy('purchase_orders.created_at', 'desc');
        }

        $agg = (clone $base)->get();
        $summary = [
            'count' => $agg->count(),
            'grand_total' => (float) $agg->sum('grand_total'),
            'paid_total' => (float) $agg->sum('paid_total'),
            'remaining_total' => (float) $agg->sum('remaining_total'),
        ];

        $orders = $base->paginate(15)->withQueryString();

        $paidMap = [];
        if ($orders->count() > 0) {
            $orderIds = $orders->pluck('id');
            $rows = PurchasesTransactions::whereIn('purchase_order_id', $orderIds)
                ->where('transaction_type', 'payment')
                ->groupBy('purchase_order_id')
                ->selectRaw('purchase_order_id, SUM(total_amount) as paid_total')
                ->get();
            foreach ($rows as $r) { $paidMap[$r->purchase_order_id] = (float)$r->paid_total; }
        }

        $banks = \App\Models\Banks::where('is_active', true)->where('subshop_id', $subshopId)->orderBy('name')->get(['id','name']);

        return view('purchases.purchase_orders.purchase_history', compact('subshop','orders','q','paidMap','banks','dateFrom','dateTo','minTotal','maxTotal','status','sort','summary'));
    }

    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('purchase_orders.index')])
                ->with('error', 'Please select a shop first');
        }

        $q = $request->query('q');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minTotal = $request->query('min_total');
        $maxTotal = $request->query('max_total');
        $status = $request->query('status');

        $paymentsSub = PurchasesTransactions::selectRaw('purchase_order_id, SUM(total_amount) as paid_total')
            ->where('transaction_type', 'payment')
            ->groupBy('purchase_order_id');

        $base = PurchaseOrders::with(['supplier','creator'])
            ->where('subshop_id', $subshopId)
            ->leftJoinSub($paymentsSub, 'pays', function($join){
                $join->on('pays.purchase_order_id', '=', 'purchase_orders.id');
            })
            ->select('purchase_orders.*', \DB::raw('COALESCE(pays.paid_total,0) as paid_total'), \DB::raw('(purchase_orders.grand_total - COALESCE(pays.paid_total,0)) as remaining_total'))
            ->when($q, function($query) use ($q){
                $query->where(function($qq) use ($q){
                    $qq->where('purchase_orders.order_no', 'like', "%{$q}%")
                       ->orWhere('purchase_orders.payment_method', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom){ $query->whereDate('purchase_orders.created_at', '>=', $dateFrom); })
            ->when($dateTo, function($query) use ($dateTo){ $query->whereDate('purchase_orders.created_at', '<=', $dateTo); })
            ->when(is_numeric($minTotal), function($query) use ($minTotal){ $query->where('purchase_orders.grand_total', '>=', $minTotal); })
            ->when(is_numeric($maxTotal), function($query) use ($maxTotal){ $query->where('purchase_orders.grand_total', '<=', $maxTotal); })
            ->when($status, function($query) use ($status){
                if ($status === 'paid') {
                    $query->whereRaw('(purchase_orders.grand_total - COALESCE(pays.paid_total,0)) <= 0');
                } elseif ($status === 'pending') {
                    $query->whereRaw('COALESCE(pays.paid_total,0) <= 0');
                } elseif ($status === 'partial') {
                    $query->whereRaw('COALESCE(pays.paid_total,0) > 0 AND (purchase_orders.grand_total - COALESCE(pays.paid_total,0)) > 0');
                }
            })
            ->orderByDesc('purchase_orders.created_at');

        $rows = $base->get();

        if ($format === 'csv') {
            return response()->stream(function () use ($rows) {
                $h = fopen('php://output', 'w');
                fputcsv($h, [
                    'Order No','Date','Supplier','Subtotal','VAT','Discount','Grand','Paid','Remaining','Status','Created By','Notes'
                ]);
                foreach ($rows as $o) {
                    $paid = (float)($o->paid_total ?? 0); $remain = max(0, (float)$o->grand_total - $paid);
                    $status = $remain <= 0 ? 'PAID' : ($paid <= 0 ? 'PENDING' : 'PARTIAL');
                    fputcsv($h, [
                        $o->order_no,
                        optional($o->created_at)->format('Y-m-d H:i:s'),
                        optional($o->supplier)->name ?? '-',
                        number_format((float)$o->subtotal,2,'.',''),
                        number_format((float)$o->vat_total,2,'.',''),
                        number_format((float)$o->discount_total,2,'.',''),
                        number_format((float)$o->grand_total,2,'.',''),
                        number_format($paid,2,'.',''),
                        number_format($remain,2,'.',''),
                        $status,
                        optional($o->creator)->name ?? '-',
                        $o->notes,
                    ]);
                }
                fclose($h);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="purchase_orders_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            return Excel::download(new PurchaseOrdersExport($rows), 'purchase_orders_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = SubShop::find($subshopId);
            $summary = [
                'count' => $rows->count(),
                'grand_total' => (float) $rows->sum('grand_total'),
                'paid_total' => (float) $rows->sum('paid_total'),
                'remaining_total' => (float) $rows->sum(function($r){ return max(0, (float)$r->grand_total - (float)($r->paid_total ?? 0)); }),
            ];
            $pdf = PDF::loadView('exports.purchase_orders_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('purchase_orders_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    public function apiOrder(PurchaseOrders $order)
    {
        $order->load(['items','supplier','creator']);
        $paid = (float) PurchasesTransactions::where('purchase_order_id', $order->id)
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

    public function payments(PurchaseOrders $order)
    {
        $payments = PurchasesTransactions::with('user')->where('purchase_order_id', $order->id)
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

    public function storePayment(Request $request, PurchaseOrders $order)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'payment_method' => 'required|string|max:100',
        ]);

        $paid = (float) PurchasesTransactions::where('purchase_order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);
        if ($remaining <= 0) {
            return response()->json(['message' => 'Order already fully paid'], 422);
        }

        $amount = min((float)$data['amount'], $remaining);

        PurchasesTransactions::create([
            'supplier_id' => $order->supplier_id,
            'purchase_order_id' => $order->id,
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
        $status = $newRemaining <= 0 ? 'paid' : ($newPaid <= 0 ? 'pending' : 'partial');

        // Send SMS notification to shop owner about payment recording
        try {
            $order->load(['subshop.shop.user', 'supplier']);
            $this->sendPurchasePaymentNotificationToOwner($order, $amount, $data['payment_method'], $newRemaining);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send purchase payment SMS notification: ' . $e->getMessage());
            // Don't fail the payment recording if SMS fails
        }

        return response()->json([
            'success' => true,
            'paid' => round($newPaid, 2),
            'remaining' => round($newRemaining, 2),
            'status' => $status,
        ]);
    }

    public function print(PurchaseOrders $order)
    {
        $order->load(['items','supplier','creator','subshop']);
        $paid = (float) PurchasesTransactions::where('purchase_order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);
        $status = $remaining <= 0 ? 'paid' : ($paid <= 0 ? 'pending' : 'partial');

        return view('purchases.purchase_orders.receipt', [
            'order' => $order,
            'items' => $order->items,
            'paid' => $paid,
            'remaining' => $remaining,
            'status' => $status,
        ]);
    }

    /**
     * Send SMS notification to shop owner about purchase payment recording
     */
    private function sendPurchasePaymentNotificationToOwner(PurchaseOrders $order, float $paymentAmount, string $paymentMethod, float $newRemaining)
    {
        \Illuminate\Support\Facades\Log::info('=== START sendPurchasePaymentNotificationToOwner ===', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'payment_amount' => $paymentAmount,
            'new_remaining' => $newRemaining,
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
            \Illuminate\Support\Facades\Log::warning('No shop owner found for purchase payment', ['order_id' => $order->id]);
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

        // Get payment recorder
        $recorder = auth()->user();

        // Get supplier info
        $supplier = $order->supplier;

        // Calculate total paid so far
        $totalPaid = (float) PurchasesTransactions::where('purchase_order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');

        // Format SMS message
        $message = "PURCHASE PAYMENT RECORDED!\n" .
                  "Shop: {$subshop->name}\n" .
                  "Order: {$order->order_no}\n" .
                  "Recorded by: " . ($recorder ? $recorder->name : 'System') . "\n" .
                  "Supplier: " . ($supplier ? $supplier->name : 'N/A') . "\n" .
                  "Payment Amount: " . number_format($paymentAmount, 2) . " TZS\n" .
                  "Payment Method: {$paymentMethod}\n" .
                  "Total Paid: " . number_format($totalPaid, 2) . " TZS\n" .
                  "Remaining Balance: " . number_format($newRemaining, 2) . " TZS\n" .
                  "Order Total: " . number_format((float)$order->grand_total, 2) . " TZS\n" .
                  "Date Recorded: " . now()->format('d/m/Y') . "\n" .
                  "Time Recorded: " . now()->format('d/m/Y H:i');

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
            'type' => 'purchase_payment',
        ]);
        
        \Illuminate\Support\Facades\Log::info('SMS send result', [
            'result' => $result,
            'phone_number' => $owner->phone_number,
            'message_length' => strlen($message)
        ]);
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Purchase payment SMS notification attempted', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'payment_amount' => $paymentAmount,
            'payment_method' => $paymentMethod,
            'total_paid' => $totalPaid,
            'remaining_balance' => $newRemaining,
            'owner_phone' => $owner->phone_number,
            'sms_result' => $result
        ]);
    }

    public function destroy(Request $request, PurchaseOrders $order)
    {
        try {
            $order->delete();
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Purchase order deleted']);
            }
            return redirect()->back()->with('success', 'Purchase order deleted');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to delete order'], 500);
            }
            return redirect()->back()->with('error', 'Failed to delete order');
        }
    }
}
