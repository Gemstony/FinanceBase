<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubShop;
use App\Models\Banks;
use App\Models\Suppliers;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\PurchaseOrders;
use App\Models\PurchasesTransactions;
use App\Models\PurchaseOrdersItems;
use Illuminate\Support\Facades\DB;
use App\Services\SmsService;

class PurchasesController extends Controller
{
    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('purchases.index')]);
    }

    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('purchases.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('purchases.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }
        $banks = Banks::where('is_active', true)->where('subshop_id', $subshopId)->orderBy('name')->get();
        return view('purchases.purchase.purchase', compact('subshop', 'banks'));
    }

    public function apiSuppliers(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string'
        ]);
        
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return response()->json(['error' => 'No subshop selected'], 400);
        }
        
        $q = $request->get('q');
        
        $suppliers = Suppliers::where('subshop_id', $subshopId)
            ->where(function($query) use ($q) {
                if ($q) {
                    $query->where('name', 'like', "%$q%")
                          ->orWhere('contact_person', 'like', "%$q%")
                          ->orWhere('phone', 'like', "%$q%");
                }
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'contact_person', 'phone', 'email']);
            
        return response()->json($suppliers);
    }

    public function apiItems(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string'
        ]);
        
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return response()->json(['error' => 'No subshop selected'], 400);
        }
        
        $q = $request->get('q');
        
        $items = Item::with('category')
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->where(function($query) use ($q) {
                if ($q) {
                    $query->where('name', 'like', "%$q%")
                          ->orWhere('barcode', 'like', "%$q%");
                }
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'barcode', 'price', 'cost_price', 'quantity', 'category_id', 'unit']);
            
        // Add batch information for each item
        $itemsWithBatches = $items->map(function($item) {
            $batches = ItemBatch::where('item_id', $item->id)
                ->where('quantity', '>', 0)
                ->orderBy('expire_date', 'asc') // FIFO: oldest first
                ->get(['id', 'batch_number', 'quantity', 'expire_date', 'cost_price', 'selling_price']);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'barcode' => $item->barcode,
                'purchase_price' => $item->cost_price, // Using cost_price as purchase_price
                'selling_price' => $item->price,       // Using price as selling_price
                'price' => $item->price,               // For frontend compatibility
                'quantity' => $item->quantity,
                'category_id' => $item->category_id,
                'unit' => $item->unit ?? 'piece',      // Default to 'piece' if null
                'batch' => null,                       // No batch column in the table
                'batches' => $batches->map(function($batch) {
                    return [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'quantity' => $batch->quantity,
                        'expire_date' => $batch->expire_date?->format('Y-m-d'),
                        'cost_price' => $batch->cost_price,
                        'selling_price' => $batch->selling_price,
                    ];
                })
            ];
        });
        return response()->json($itemsWithBatches);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subshop_id' => 'required|exists:sub_shops,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'payment_method' => 'nullable|string',
            'amount_paid' => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_cash' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:items,id',
            'items.*.name' => 'required|string',
            'items.*.unit' => 'nullable|string',
            'items.*.cost_price' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.batch_number' => 'required|string|max:255',
            'items.*.expire_date' => 'nullable|date|after:today',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.vatType' => 'required|in:none,inclusive,exclusive',
        ]);

        $subshop = SubShop::findOrFail($validated['subshop_id']);
        if ($subshop->is_active != 1) {
            return response()->json(['message' => 'Shop not active'], 422);
        }

        $VAT_RATE = 18; // percent

        return DB::transaction(function () use ($validated, $VAT_RATE) {
            $subtotal = 0; // base
            $vatTotal = 0;

            $lineData = [];
            foreach ($validated['items'] as $row) {
                /** @var Item $item */
                $item = Item::lockForUpdate()->findOrFail($row['id']);
                if ($item->subshop_id != $validated['subshop_id']) {
                    return response()->json(['message' => 'Item does not belong to this shop'], 422);
                }

                $qty = (int)$row['qty'];
                $costPrice = (float)$row['cost_price'];
                $sellingPrice = (float)$row['selling_price'];
                $batchNumber = $row['batch_number'];
                $expireDate = $row['expire_date'] ? $row['expire_date'] : null;
                $base = $costPrice * $qty;
                $vatAmt = 0.0;
                if ($row['vatType'] === 'exclusive') {
                    $vatAmt = $base * ($VAT_RATE/100);
                } else {
                    $vatAmt = 0.0;
                }

                $subtotal += $base;
                $vatTotal += $vatAmt;

                $lineData[] = [
                    'item' => $item,
                    'payload' => $row,
                    'qty' => $qty,
                    'cost_price' => $costPrice,
                    'selling_price' => $sellingPrice,
                    'batch_number' => $batchNumber,
                    'expire_date' => $expireDate,
                    'base' => $base,
                    'vat_amount' => $vatAmt,
                    'line_total' => $base + $vatAmt,
                ];
            }

            $discountPercent = (float)($validated['discount_percent'] ?? 0);
            if ($discountPercent < 0) $discountPercent = 0; if ($discountPercent > 100) $discountPercent = 100;
            $discountCash = (float)($validated['discount_cash'] ?? 0);
            $gross = $subtotal + $vatTotal;
            $discountFromPercent = $gross * ($discountPercent/100);
            $maxCash = max(0, $gross - $discountFromPercent);
            if ($discountCash > $maxCash) { $discountCash = $maxCash; }
            $discountTotal = $discountFromPercent + $discountCash;
            $grand = max(0, $gross - $discountTotal);

            $amountPaid = (float)$validated['amount_paid'];
            $change = max(0, $amountPaid - $grand);

            $order = PurchaseOrders::create([
                'subshop_id' => $validated['subshop_id'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'created_by' => auth()->id(),
                'order_no' => self::generateOrderNo(),
                'subtotal' => round($subtotal, 2),
                'vat_total' => round($vatTotal, 2),
                'discount_percent' => round($discountPercent, 2),
                'discount_cash' => round($discountCash, 2),
                'discount_total' => round($discountTotal, 2),
                'grand_total' => round($grand, 2),
                'payment_method' => $validated['payment_method'] ?? null,
                'amount_paid' => round($amountPaid, 2),
                'change_amount' => round($change, 2),
                'status' => 'completed',
            ]);

            // Save line items and create batches
            foreach ($lineData as $ld) {
                $purchaseItem = PurchaseOrdersItems::create([
                    'purchase_order_id' => $order->id,
                    'item_id' => $ld['item']->id,
                    'item_name' => $ld['item']->name,
                    'unit' => $ld['item']->unit,
                    'unit_price' => round($ld['cost_price'], 2),
                    'quantity' => (int)$ld['qty'],
                    'vat_amount' => round($ld['vat_amount'], 2),
                    'base_amount' => round($ld['base'], 2),
                    'line_total' => round($ld['line_total'], 2),
                    'batch_number' => $ld['batch_number'],
                    'expire_date' => $ld['expire_date'],
                    'cost_price' => round($ld['cost_price'], 2),
                    'selling_price' => round($ld['selling_price'], 2),
                ]);

                // Create ItemBatch
                ItemBatch::create([
                    'item_id' => $ld['item']->id,
                    'batch_number' => $ld['batch_number'],
                    'quantity' => $ld['qty'],
                    'cost_price' => round($ld['cost_price'], 2),
                    'selling_price' => round($ld['selling_price'], 2),
                    'expire_date' => $ld['expire_date'],
                ]);
            }

            // If some amount paid, record a single payment transaction for this purchase order
            if ($order->amount_paid > 0) {
                PurchasesTransactions::create([
                    'supplier_id' => $validated['supplier_id'] ?? null,
                    'purchase_order_id' => $order->id,
                    'transaction_type' => 'payment',
                    'payment_method' => $validated['payment_method'] ?? null,
                    'total_amount' => min($order->amount_paid, $order->grand_total),
                    'transaction_date' => date('Y-m-d'),
                    'reference_number' => $order->order_no,
                    'notes' => 'Purchase payment recorded at order creation',
                ]);
            }

            // Send SMS notification to shop owner about completed purchase
            try {
                $order->load(['subshop.shop.user', 'creator', 'supplier', 'items']);
                $this->sendPurchaseNotificationToOwner($order);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send purchase SMS notification: ' . $e->getMessage());
                // Don't fail the purchase if SMS fails
            }

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'grand_total' => $order->grand_total,
                'change' => $order->change_amount,
            ]);
        });
    }

    /**
     * Send SMS notification to shop owner about completed purchase
     */
    private function sendPurchaseNotificationToOwner(PurchaseOrders $order)
    {
        \Illuminate\Support\Facades\Log::info('=== START sendPurchaseNotificationToOwner ===', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
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
            \Illuminate\Support\Facades\Log::warning('No shop owner found for purchase', ['order_id' => $order->id]);
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

        // Get purchase processor
        $processor = $order->creator; // Use relationship instead of auth()->user()

        // Get supplier info
        $supplier = $order->supplier;

        // Get purchase items summary
        $items = $order->items;
        $itemsSummary = '';
        $totalItems = 0;
        foreach ($items as $item) {
            $itemsSummary .= $item->item_name . ' (' . $item->quantity . 'x ' . number_format((float)$item->unit_price, 0) . '), ';
            $totalItems += $item->quantity;
        }
        $itemsSummary = rtrim($itemsSummary, ', ');

        // Calculate remaining amount
        $remainingAmount = max(0, (float)$order->grand_total - (float)$order->amount_paid);

        // Format SMS message
        $message = "NEW PURCHASE COMPLETED!\n" .
                  "Shop: {$subshop->name}\n" .
                  "Order: {$order->order_no}\n" .
                  "Processed by: " . ($processor ? $processor->name : 'System') . "\n" .
                  "Supplier: " . ($supplier ? $supplier->name : 'N/A') . "\n" .
                  "Items: {$itemsSummary}\n" .
                  "Purchase Value: " . number_format((float)$order->grand_total, 2) . " TZS\n" .
                  "Amount Paid: " . number_format((float)$order->amount_paid, 2) . " TZS\n" .
                  "Remaining Amount: " . number_format($remainingAmount, 2) . " TZS\n" .
                  "Payment Method: " . ($order->payment_method ?: 'N/A') . "\n" .
                  "Date: " . $order->created_at->format('d/m/Y') . "\n" .
                  "Time: " . $order->created_at->format('d/m/Y H:i');

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
            'type' => 'purchase',
        ]);
        
        \Illuminate\Support\Facades\Log::info('SMS send result', [
            'result' => $result,
            'phone_number' => $owner->phone_number,
            'message_length' => strlen($message)
        ]);
        
        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Purchase SMS notification attempted', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'owner_phone' => $owner->phone_number,
            'items_detail' => $itemsSummary,
            'total_items' => $totalItems,
            'purchase_value' => $order->grand_total,
            'amount_paid' => $order->amount_paid,
            'remaining_amount' => $remainingAmount,
            'sms_result' => $result
        ]);
    }

    public function apiNextBatchNumber(Request $request)
    {
        $request->validate([
            'item_id' => 'nullable|exists:items,id'
        ]);

        $itemId = $request->get('item_id');
        $nextBatch = ItemBatch::generateBatchNumber($itemId);
        return response()->json(['batch_number' => $nextBatch]);
    }

    private static function generateOrderNo(): string
    {
        $prefix = 'PO-' . date('Ymd');
        $last = PurchaseOrders::where('order_no', 'like', $prefix.'%')->orderByDesc('id')->first();
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->order_no, $m)) {
            $seq = intval($m[1]) + 1;
        }
        return sprintf('%s-%04d', $prefix, $seq);
    }
}
