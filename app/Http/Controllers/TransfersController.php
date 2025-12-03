<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\SubShop;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\TransferItemBatch;
use App\Models\TransferAudit;
use App\Notifications\TransferDispatchedNotification;
use App\Notifications\TransferReceivedNotification;
use App\Models\WriteOff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TransfersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request)
    {
        $request->validate([
            'destination_subshop_id' => 'required|exists:sub_shops,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.batches' => 'required|array|min:1',
            'items.*.batches.*.batch_id' => 'required|exists:item_batches,id',
            'items.*.batches.*.qty' => 'required|numeric|min:1',
        ]);

        $sourceSubshopId = session('subshop_id');
        if (!$sourceSubshopId) {
            return response()->json(['success' => false, 'message' => 'No source subshop in session'], 400);
        }

        $destinationSubshop = SubShop::findOrFail($request->destination_subshop_id);
        $sourceSubshop = SubShop::findOrFail($sourceSubshopId);

        if ($destinationSubshop->shop_id !== $sourceSubshop->shop_id) {
            return response()->json(['success' => false, 'message' => 'Transfers must be within the same shop'], 422);
        }
        if ($destinationSubshop->id == $sourceSubshop->id) {
            return response()->json(['success' => false, 'message' => 'Cannot transfer to the same subshop'], 422);
        }

        $userId = auth()->id();

        $transfer = DB::transaction(function () use ($request, $sourceSubshop, $destinationSubshop, $userId) {
            $transfer = Transfer::create([
                'shop_id' => $sourceSubshop->shop_id,
                'source_subshop_id' => $sourceSubshop->id,
                'destination_subshop_id' => $destinationSubshop->id,
                'status' => 'draft',
                'requested_by_id' => $userId,
                'notes' => $request->input('notes'),
            ]);

            TransferAudit::create([
                'transfer_id' => $transfer->id,
                'action' => 'created',
                'user_id' => $userId,
                'meta' => ['notes' => $request->input('notes')]
            ]);

            foreach ($request->items as $itemRow) {
                $sourceItem = Item::where('id', $itemRow['item_id'])
                    ->where('subshop_id', $sourceSubshop->id)
                    ->firstOrFail();

                // Ensure destination item exists or create
                // Try to find by exact SKU within destination subshop first
                $destItem = Item::where('subshop_id', $destinationSubshop->id)
                    ->where('name', $sourceItem->name)
                    // ->Where('id'  ,$itemRow['item_id'])
                    ->first();

                if (!$destItem) {
                    // Create fresh item with a NEW SKU (and barcode) to avoid global unique SKU collisions
                    $generatedSku = Item::generateSKU($destinationSubshop->id);
                    $generatedBarcode = Item::generateBarcode();
                    $destItem = Item::create([
                        'subshop_id' => $destinationSubshop->id,
                        'sku' => $generatedSku,
                        'name' => $sourceItem->name,
                        'description' => $sourceItem->description,
                        'category_id' => $sourceItem->category_id,
                        'supplier_id' => $sourceItem->supplier_id,
                        'barcode' => $generatedBarcode,
                        'price' => $sourceItem->price,
                        'cost_price' => $sourceItem->cost_price,
                        'quantity' => 0,
                        'min_quantity' => $sourceItem->min_quantity,
                        'max_quantity' => $sourceItem->max_quantity,
                        'unit' => $sourceItem->unit,
                        'is_active' => $sourceItem->is_active,
                    ]);
                }

                $plannedTotal = 0;
                foreach ($itemRow['batches'] as $b) {
                    $plannedTotal += (float)$b['qty'];
                }

                $ti = TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'source_item_id' => $sourceItem->id,
                    'destination_item_id' => $destItem->id,
                    'planned_qty' => $plannedTotal,
                    'uom' => $sourceItem->unit,
                    'item_name_snapshot' => $sourceItem->name,
                    'sku_snapshot' => $sourceItem->sku,
                ]);

                // FEFO enforcement: sort provided batches by expire_date asc
                $batches = collect($itemRow['batches'])
                    ->map(function ($b) {
                        $ib = ItemBatch::findOrFail($b['batch_id']);
                        return [
                            'model' => $ib,
                            'qty' => (float)$b['qty'],
                            'expire_date' => $ib->expire_date,
                        ];
                    })
                    ->sortBy(function ($x) {
                        $ed = $x['expire_date'] ?? null;
                        if (!$ed) { return PHP_INT_MAX; }
                        // Handle Carbon instance or string
                        if (method_exists($ed, 'getTimestamp')) { return $ed->getTimestamp(); }
                        $ts = strtotime((string)$ed);
                        return $ts ?: PHP_INT_MAX;
                    })
                    ->values();

                foreach ($batches as $row) {
                    $ib = $row['model'];
                    $qty = $row['qty'];

                    if ($ib->item_id !== $sourceItem->id) {
                        throw new \Exception('Batch does not belong to the selected item');
                    }
                    if ($ib->quantity < $qty) {
                        throw new \Exception('Insufficient quantity in batch ' . $ib->batch_number);
                    }

                    TransferItemBatch::create([
                        'transfer_item_id' => $ti->id,
                        'source_item_batch_id' => $ib->id,
                        'batch_number' => $ib->batch_number,
                        'expire_date' => $ib->expire_date,
                        'cost_price' => $ib->cost_price,
                        'selling_price_snapshot' => $ib->selling_price,
                        'planned_qty' => $qty,
                    ]);
                }
            }

            return $transfer;
        });

        if ($request->boolean('dispatch_now')) {
            // Auto-approve then dispatch
            $transfer->status = 'approved';
            $transfer->approved_by_id = $userId;
            $transfer->save();
            TransferAudit::create([
                'transfer_id' => $transfer->id,
                'action' => 'approved',
                'user_id' => $userId,
                'meta' => ['auto' => true],
            ]);
            return $this->dispatch($request, $transfer);
        }

        return response()->json(['success' => true, 'transfer_id' => $transfer->id, 'status' => $transfer->status]);
    }

    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('transfers.index')]);
        }

        $query = Transfer::with(['sourceSubshop', 'destinationSubshop', 'items'])
            ->where(function($q) use ($subshopId){
                $q->where('source_subshop_id', $subshopId)
                  ->orWhere('destination_subshop_id', $subshopId);
            })
            ->orderBy('id', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $transfers = $query->paginate(20);

        return view('inventory.transfers.index', compact('transfers', 'subshopId'));
    }

    public function approve(Request $request, Transfer $transfer)
    {
        if ($transfer->status !== 'draft') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only draft transfers can be approved'], 422);
            }
            return redirect()->back()->with('error', 'Only draft transfers can be approved');
        }
        $transfer->status = 'approved';
        $transfer->approved_by_id = auth()->id();
        $transfer->save();
        TransferAudit::create([
            'transfer_id' => $transfer->id,
            'action' => 'approved',
            'user_id' => auth()->id(),
            'meta' => null,
        ]);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'status' => $transfer->status]);
        }
        return redirect()->back()->with('success', 'Transfer approved');
    }

    public function dispatch(Request $request, Transfer $transfer)
    {
        if ($transfer->status !== 'approved') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only approved transfers can be dispatched'], 422);
            }
            return redirect()->back()->with('error', 'Only approved transfers can be dispatched');
        }

        DB::transaction(function () use ($transfer) {
            $transfer->load('items.batches');
            foreach ($transfer->items as $ti) {
                $dispatchedTotal = 0;
                foreach ($ti->batches as $tib) {
                    $sourceBatch = ItemBatch::findOrFail($tib->source_item_batch_id);
                    $qty = (float)$tib->planned_qty; // dispatch planned amount
                    if ($sourceBatch->quantity < $qty) {
                        throw new \Exception('Insufficient stock in batch ' . $sourceBatch->batch_number);
                    }
                    $sourceBatch->quantity -= $qty;
                    $sourceBatch->save();

                    $tib->dispatched_qty = $qty;
                    $tib->save();
                    $dispatchedTotal += $qty;
                }
                $ti->dispatched_qty = $dispatchedTotal;
                $ti->save();
            }
            $transfer->status = 'dispatched';
            $transfer->dispatched_by_id = auth()->id();
            $transfer->save();
        });

        // Audit & Notify destination users
        TransferAudit::create([
            'transfer_id' => $transfer->id,
            'action' => 'dispatched',
            'user_id' => auth()->id(),
            'meta' => null,
        ]);
        $transfer->loadMissing(['destinationSubshop.users']);
        if ($transfer->destinationSubshop && $transfer->destinationSubshop->users) {
            Notification::send($transfer->destinationSubshop->users, new TransferDispatchedNotification($transfer));
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'transfer_id' => $transfer->id, 'status' => $transfer->status]);
        }
        return redirect()->back()->with('success', 'Transfer dispatched');
    }

    public function receive(Request $request, Transfer $transfer)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.transfer_item_batch_id' => 'required|exists:transfer_item_batches,id',
            'items.*.received_qty' => 'required|numeric|min:0',
            'items.*.damaged_qty' => 'nullable|numeric|min:0',
        ]);

        if (!in_array($transfer->status, ['dispatched','partially_received'])) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Transfer not in receivable state'], 422);
            }
            return redirect()->back()->with('error', 'Transfer not in receivable state');
        }

        DB::transaction(function () use ($request, $transfer) {
            $totalPlanned = 0; $totalReceived = 0;
            foreach ($request->items as $row) {
                $tib = TransferItemBatch::findOrFail($row['transfer_item_batch_id']);
                $ti = TransferItem::findOrFail($tib->transfer_item_id);

                $receiveQty = (float)$row['received_qty'];
                $damagedQty = (float)($row['damaged_qty'] ?? 0);

                if ($receiveQty + $damagedQty > ($tib->dispatched_qty - $tib->received_qty - $tib->damaged_qty)) {
                    throw new \Exception('Receiving more than dispatched remaining for batch ' . $tib->batch_number);
                }

                // Destination batch: merge by batch_number or create
                $destBatch = ItemBatch::where('item_id', $ti->destination_item_id)
                    ->where('batch_number', $tib->batch_number)
                    ->first();
                if (!$destBatch) {
                    $destBatch = ItemBatch::create([
                        'item_id' => $ti->destination_item_id,
                        'batch_number' => $tib->batch_number,
                        'quantity' => 0,
                        'cost_price' => $tib->cost_price,
                        'selling_price' => $tib->selling_price_snapshot,
                        'expire_date' => $tib->expire_date,
                    ]);
                }

                // increase destination stock by received qty
                if ($receiveQty > 0) {
                    $destBatch->quantity += $receiveQty;
                    $destBatch->save();
                }

                // Update transfer batch tallies
                $tib->destination_item_batch_id = $destBatch->id;
                $tib->received_qty += $receiveQty;
                $tib->damaged_qty += $damagedQty;
                $tib->save();

                // Update item totals
                $ti->received_qty += $receiveQty;
                $ti->save();

                $totalPlanned += (float)$tib->planned_qty;
                $totalReceived += $receiveQty;
            }

            // Update transfer status
            $allBatches = TransferItemBatch::whereIn('transfer_item_id', $transfer->items()->pluck('id'))
                ->get();
            $allReceived = $allBatches->sum('received_qty') + $allBatches->sum('damaged_qty');
            $allDispatched = $allBatches->sum('dispatched_qty');

            if ($allReceived >= $allDispatched && $allDispatched > 0) {
                $transfer->status = 'received';
            } else {
                $transfer->status = 'partially_received';
            }
            $transfer->received_by_id = auth()->id();
            $transfer->save();
        });

        // Audit & Notify source users
        TransferAudit::create([
            'transfer_id' => $transfer->id,
            'action' => $transfer->status, // 'received' or 'partially_received'
            'user_id' => auth()->id(),
            'meta' => null,
        ]);
        $transfer->loadMissing(['sourceSubshop.users']);
        if ($transfer->sourceSubshop && $transfer->sourceSubshop->users) {
            Notification::send($transfer->sourceSubshop->users, new TransferReceivedNotification($transfer));
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'transfer_id' => $transfer->id, 'status' => $transfer->status]);
        }
        return redirect()->route('transfers.index')->with('success', $transfer->status === 'received' ? 'Transfer fully received' : 'Transfer partially received');
    }

    public function receiveForm(Request $request, Transfer $transfer)
    {
        if (!in_array($transfer->status, ['dispatched','partially_received'])) {
            return redirect()->route('transfers.index')->with('error', 'Transfer is not ready for receiving');
        }
        $transfer->load(['items.batches', 'sourceSubshop', 'destinationSubshop']);
        return view('inventory.transfers.receive', compact('transfer'));
    }

    public function show(Request $request, Transfer $transfer)
    {
        $transfer->load(['items.batches', 'sourceSubshop', 'destinationSubshop', 'audits']);
        return view('inventory.transfers.show', compact('transfer'));
    }

    public function printNote(Request $request, Transfer $transfer)
    {
        $transfer->load(['items.batches', 'sourceSubshop', 'destinationSubshop']);
        return view('inventory.transfers.print', compact('transfer'));
    }

    public function cancel(Request $request, Transfer $transfer)
    {
        // Allowed: draft, approved, dispatched, partially_received
        $writeOffs = []; // Track any auto-created write-offs

        if (in_array($transfer->status, ['received','cancelled'])) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Cannot cancel a fully received or already cancelled transfer'], 422);
            }
            return redirect()->back()->with('error', 'Cannot cancel a fully received or already cancelled transfer');
        }

        DB::transaction(function () use ($transfer) {
            $transfer->load('items.batches');

            // 1) Reverse any received quantities from destination batches
            foreach ($transfer->items as $ti) {
                foreach ($ti->batches as $tib) {
                    $rcv = (float)($tib->received_qty ?? 0);
                    if ($rcv > 0) {
                        if ($tib->destination_item_batch_id) {
                            $destBatch = ItemBatch::find($tib->destination_item_batch_id);
                            if (!$destBatch) {
                                throw new \Exception('Destination batch not found for reversal (batch '.$tib->batch_number.')');
                            }
                            if ($destBatch->quantity < $rcv) {
                                // Softer path: write-off the shortage at destination
                                $shortage = $rcv - (float)$destBatch->quantity;
                                $writeOffQty = (int) round($shortage);
                                if ($writeOffQty > 0) {
                                    // Create write-off for the shortage
                                    WriteOff::create([
                                        'subshop_id' => $transfer->destination_subshop_id,
                                        'created_by' => auth()->id(),
                                        'item_id' => $ti->destination_item_id,
                                        'batch_id' => $destBatch->id,
                                        'quantity' => $writeOffQty,
                                        'reason' => 'Transfer cancellation reversal shortage',
                                        'write_off_date' => now(),
                                        'description' => 'Transfer #'.$transfer->id.' batch '.$tib->batch_number.' shortage during reversal',
                                        'unit_price' => $tib->cost_price ?? $destBatch->cost_price ?? 0,
                                        'total_value' => ($tib->cost_price ?? $destBatch->cost_price ?? 0) * $writeOffQty,
                                        'status' => 'approved',
                                    ]);
                                    $writeOffs[] = [
                                        'item_name' => $ti->item_name_snapshot,
                                        'batch' => $tib->batch_number,
                                        'qty' => $writeOffQty,
                                        'cost' => $tib->cost_price ?? $destBatch->cost_price ?? 0,
                                        'total' => ($tib->cost_price ?? $destBatch->cost_price ?? 0) * $writeOffQty,
                                    ];
                                }
                                // Zero destination batch, remaining will be reversed by write-off
                                $destBatch->quantity = 0;
                            } else {
                                $destBatch->quantity -= $rcv;
                            }
                            $destBatch->save();
                        } else {
                            // Should not happen normally because receive sets destination_item_batch_id
                            throw new \Exception('Cannot cancel: missing destination batch linkage for batch '.$tib->batch_number);
                        }
                        // Zero out received and damaged on the transfer batch
                        $tib->received_qty = 0;
                        $tib->damaged_qty = 0;
                        $tib->save();
                    }
                }
            }

            // 2) Return any dispatched quantities back to source batches
            foreach ($transfer->items as $ti) {
                foreach ($ti->batches as $tib) {
                    $disp = (float)($tib->dispatched_qty ?? 0);
                    if ($disp > 0) {
                        $sourceBatch = ItemBatch::find($tib->source_item_batch_id);
                        if ($sourceBatch) {
                            $sourceBatch->quantity += $disp;
                            $sourceBatch->save();
                        }
                        $tib->dispatched_qty = 0;
                        $tib->save();
                    }
                }
                $ti->dispatched_qty = 0;
                $ti->received_qty = 0;
                $ti->save();
            }

            // 3) Mark transfer cancelled
            $transfer->status = 'cancelled';
            $transfer->save();
        });

        TransferAudit::create([
            'transfer_id' => $transfer->id,
            'action' => 'cancelled',
            'user_id' => auth()->id(),
            'meta' => [
                'reversed' => true,
                'write_offs' => $writeOffs,
            ],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'status' => $transfer->status]);
        }
        return redirect()->back()->with('success', 'Transfer cancelled and stock reversed');
    }
}
