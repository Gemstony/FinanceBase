<?php

namespace App\Http\Controllers;

use App\Models\Banks;
use Illuminate\Http\Request;
use App\Models\SubShop;
use App\Models\Customers;
use App\Models\ItemBatch;
use App\Models\Item;
use App\Models\SalesOrders;
use App\Models\SalesOrdersItems;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('pos.index')]);
    }

    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('pos.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('pos.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }
        $banks = Banks::where('is_active', true)->where('subshop_id', $subshopId)->orderBy('name')->get();
        return view('sales.pos.pos', compact('subshop', 'banks'));
    }

    // Lightweight search endpoints for Select2
    public function apiCustomers(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string'
        ]);
        
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return response()->json(['error' => 'No subshop selected'], 400);
        }
        
        $q = $request->get('q');
        
        $customers = Customers::where('subshop_id', $subshopId)
            ->where(function($query) use ($q) {
                if ($q) {
                    $query->where('name', 'like', "%$q%")
                          ->orWhere('phone', 'like', "%$q%")
                          ->orWhere('email', 'like', "%$q%");
                }
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'email', 'address']);
            
        return response()->json($customers);
    }

    public function apiItems(Request $request)
    {
        $request->validate([
            'subshop_id' => 'required|exists:sub_shops,id',
            'q' => 'nullable|string'
        ]);
        $q = $request->get('q');
        $subshopId = $request->get('subshop_id');
        $items = Item::where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->when($q, function($query) use ($q){
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('batch', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id','name','price','quantity','unit','batch']);

        // Add batch information for each item
        $itemsWithBatches = $items->map(function($item) {
            $batches = ItemBatch::where('item_id', $item->id)
                ->where('quantity', '>', 0)
                ->orderBy('expire_date', 'asc') // FIFO: oldest first
                ->get(['id', 'batch_number', 'quantity', 'expire_date', 'cost_price', 'selling_price']);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'batch' => $item->batch,
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
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method' => 'nullable|string',
            'amount_paid' => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_cash' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:items,id',
            'items.*.name' => 'required|string',
            'items.*.unit' => 'nullable|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.vatType' => 'required|in:none,inclusive,exclusive',
            'items.*.batch_id' => 'required|exists:item_batches,id',
        ]);

        $subshop = SubShop::findOrFail($validated['subshop_id']);
        if ($subshop->is_active != 1) {
            return response()->json(['message' => 'Shop not active'], 422);
        }

        $VAT_RATE = 18; // percent

        return DB::transaction(function () use ($validated, $VAT_RATE) {
            $subtotal = 0; // base
            $vatTotal = 0;

            // Validate stock and compute lines
            $lineData = [];
            foreach ($validated['items'] as $row) {
                /** @var Item $item */
                $item = Item::lockForUpdate()->findOrFail($row['id']);

                if ($item->subshop_id != $validated['subshop_id']) {
                    return response()->json(['message' => 'Item does not belong to this shop'], 422);
                }

                // Validate batch exists and belongs to this item
                $batch = ItemBatch::lockForUpdate()->findOrFail($row['batch_id']);
                if ($batch->item_id != $row['id']) {
                    return response()->json(['message' => 'Batch does not belong to this item'], 422);
                }

                if ($row['qty'] > $batch->quantity) {
                    return response()->json(['message' => "Insufficient stock in batch {$batch->batch_number}. Available: {$batch->quantity}"], 422);
                }

                $qty = (int)$row['qty'];
                $price = (float)$row['price'];
                $base = $price * $qty; // base amount from unit price
                $vatAmt = 0.0;
                if ($row['vatType'] === 'exclusive') {
                    // Add VAT on top for exclusive
                    $vatAmt = $base * ($VAT_RATE/100);
                } else {
                    // inclusive or none -> do not add VAT on top
                    $vatAmt = 0.0;
                }

                $subtotal += $base;
                $vatTotal += $vatAmt;

                $lineData[] = [
                    'item' => $item,
                    'batch' => $batch,
                    'payload' => $row,
                    'qty' => $qty,
                    'unit_price' => $price,
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
            // Clamp discount cash to not exceed remaining after percent
            $maxCash = max(0, $gross - $discountFromPercent);
            if ($discountCash > $maxCash) { $discountCash = $maxCash; }
            $discountTotal = $discountFromPercent + $discountCash;
            $grand = max(0, $gross - $discountTotal);

            $amountPaid = (float)$validated['amount_paid'];
            $change = max(0, $amountPaid - $grand);

            $order = SalesOrders::create([
                'subshop_id' => $validated['subshop_id'],
                'customer_id' => $validated['customer_id'] ?? null,
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

            foreach ($lineData as $ld) {
                SalesOrdersItems::create([
                    'sales_order_id' => $order->id,
                    'item_id' => $ld['item']->id,
                    'batch_id' => $ld['batch']->id,
                    'item_name' => $ld['payload']['name'],
                    'unit' => $ld['payload']['unit'] ?? $ld['item']->unit,
                    'unit_price' => round($ld['unit_price'], 2),
                    'quantity' => $ld['qty'],
                    'vat_type' => $ld['payload']['vatType'],
                    'vat_rate' => $ld['payload']['vatType'] === 'exclusive' ? $VAT_RATE : 0,
                    'vat_amount' => round($ld['vat_amount'], 2),
                    'base_amount' => round($ld['base'], 2),
                    'line_total' => round($ld['line_total'], 2),
                ]);

                // Reduce batch quantity
                $ld['batch']->decrement('quantity', $ld['qty']);
            }

            // If some amount paid, record a single payment transaction for this invoice
            if ($order->amount_paid > 0) {
                \App\Models\Transaction::create([
                    'customer_id' => $validated['customer_id'] ?? null,
                    'order_id' => $order->id,
                    'transaction_type' => 'payment',
                    'payment_method' => $validated['payment_method'] ?? null,
                    // Record the amount actually collected now (cannot exceed grand total)
                    'total_amount' => min($order->amount_paid, $order->grand_total),
                    'transaction_date' => date('Y-m-d'),
                    'reference_number' => $order->order_no,
                    'notes' => 'POS payment received at order creation',
                ]);
            }

            // Send SMS notification to shop owner
            try {
                $this->sendSaleNotificationToOwner($order);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send sale SMS notification: ' . $e->getMessage());
                // Don't fail the sale if SMS fails
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
     * Send SMS notification to shop owner about new sale
     */
    private function sendSaleNotificationToOwner(SalesOrders $order)
    {
        // Get shop owner
        $subshop = $order->subshop;
        if (!$subshop || !$subshop->shop || !$subshop->shop->user) {
            return; // No owner found
        }

        $owner = $subshop->shop->user;
        if (!$owner->phone_number) {
            return; // Owner has no phone number
        }

        // Get sale details
        $seller = $order->creator; // User who created the sale
        $customer = $order->customer;

        // Format items list (limit to avoid SMS length issues)
        $itemsText = '';
        $items = $order->items->take(5); // Limit to 5 items for SMS
        foreach ($items as $item) {
            $itemsText .= $item->item_name . ' (' . $item->quantity . 'x' . number_format($item->unit_price, 2) . '), ';
        }
        if ($order->items->count() > 5) {
            $itemsText .= ' +' . ($order->items->count() - 5) . ' more items';
        } else {
            $itemsText = rtrim($itemsText, ', ');
        }

        // Calculate remaining amount
        $remaining = max(0, $order->grand_total - $order->amount_paid);

        // Format SMS message
        $message = "NEW SALE ALERT!\n" .
                  "Shop: {$subshop->name}\n" .
                  "Order: {$order->order_no}\n" .
                  "Sold by: " . ($seller ? $seller->name : 'Unknown') . "\n" .
                  "Customer: " . ($customer ? $customer->name : 'Walk-in') . " " . ($customer && $customer->phone ? "(" . $customer->phone . ")" : "") . "\n" .
                  "Items: {$itemsText}\n" .
                  "Grand Total: " . number_format($order->grand_total, 2) . " TZS\n" .
                  "VAT: " . number_format($order->vat_total, 2) . " TZS\n" .
                  "Discount: " . number_format($order->discount_total, 2) . " TZS\n" .
                  "Payment Method: " . ($order->payment_method ?: 'Not specified') . "\n" .
                  "Amount Paid: " . number_format($order->amount_paid, 2) . " TZS\n" .
                  "Remaining: " . number_format($remaining, 2) . " TZS\n" .
                  "Time: " . $order->created_at->format('d/m/Y H:i');

        // Send SMS
        $smsService = new SmsService();
        $smsService->sendSms($owner->phone_number, $message, [
            'shop_id' => $subshop->shop_id ?? null,
            'subshop_id' => $subshop->id ?? null,
            'owner_id' => $owner->id ?? null,
            'type' => 'pos_sale',
        ]);
    }

    private static function generateOrderNo(): string
    {
        $prefix = 'SO-' . date('Ymd');
        $last = SalesOrders::where('order_no', 'like', $prefix.'%')->orderByDesc('id')->first();
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->order_no, $m)) {
            $seq = intval($m[1]) + 1;
        }
        return sprintf('%s-%04d', $prefix, $seq);
    }
}
