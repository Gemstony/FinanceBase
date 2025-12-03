<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\SubShop;
use App\Models\User;
use App\Models\WriteOff;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class WriteOffsController extends Controller
{
    /**
     * Display a listing of the resource.
     */

public function index(Request $request)
{
    $subshopId = session('subshop_id');
    if (!$subshopId) {
        return redirect()->route('subshops.choose', ['intended' => route('writeoffs.index')]);
    }

    $subshop = SubShop::findOrFail($subshopId);

    if($subshop->is_active != 1) {
        session()->forget('subshop_id');
        return redirect()->route('subshops.choose', ['intended' => route('writeoffs.index')])
            ->with('error', 'Shop is not active. Please contact the owner to activate it.');
    }

    // Products for dropdown with batches
    $products = Item::with(['itemBatches' => function($q){
            $q->orderBy('id', 'desc');
        }])
        ->where('subshop_id', $subshopId)
        ->where(function($q){
            $q->whereNull('quantity')->orWhere('quantity', '>', 0);
        })
        ->orderBy('name')
        ->get();

    // Start query (query builder)
    $query = WriteOff::with(['product', 'creator', 'reviewed', 'subshop'])
        ->select('write_offs.*', 'items.name as product_name', 'sub_shops.name as subshop_name')
        ->join('items', 'items.id', '=', 'write_offs.item_id')
        ->join('sub_shops', 'sub_shops.id', '=', 'write_offs.subshop_id')
        ->where('write_offs.subshop_id', $subshopId);

    // Search
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($qq) use ($search){
            $qq->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                })
               ->orWhere('write_offs.reason', 'like', "%{$search}%")
               ->orWhere('write_offs.description', 'like', "%{$search}%");
        });
    }

    // Status filter
    if ($request->filled('status') && in_array($request->status, ['pending','approved','rejected'])) {
        $query->where('write_offs.status', $request->status);
    }

    // Reason filter
    if ($request->filled('reason')) {
        $query->where('write_offs.reason', $request->input('reason'));
    }

    // Date range filters
    if ($request->filled('date_from')) {
        $query->whereDate('write_offs.write_off_date', '>=', $request->input('date_from'));
    }
    if ($request->filled('date_to')) {
        $query->whereDate('write_offs.write_off_date', '<=', $request->input('date_to'));
    }

    // Quantity range filters
    if ($request->filled('min_qty')) {
        $query->where('write_offs.quantity', '>=', (int)$request->input('min_qty'));
    }
    if ($request->filled('max_qty')) {
        $query->where('write_offs.quantity', '<=', (int)$request->input('max_qty'));
    }

    // Total value range filters
    if ($request->filled('min_total')) {
        $query->where('write_offs.total_value', '>=', (float)$request->input('min_total'));
    }
    if ($request->filled('max_total')) {
        $query->where('write_offs.total_value', '<=', (float)$request->input('max_total'));
    }

    // Recorded by (creator name) filter
    if ($request->filled('recorded_by')) {
        $name = $request->input('recorded_by');
        $query->whereHas('creator', function($q) use ($name){
            $q->where('name', 'like', "%{$name}%");
        });
    }

    // Sorting
    $sort = $request->input('sort');
    if ($sort === 'date_asc') {
        $query->orderBy('write_offs.write_off_date', 'asc');
    } elseif ($sort === 'total_desc') {
        $query->orderBy('write_offs.total_value', 'desc');
    } elseif ($sort === 'total_asc') {
        $query->orderBy('write_offs.total_value', 'asc');
    } elseif ($sort === 'qty_desc') {
        $query->orderBy('write_offs.quantity', 'desc');
    } elseif ($sort === 'qty_asc') {
        $query->orderBy('write_offs.quantity', 'asc');
    } else {
        // default by date desc if column exists else id desc
        if (Schema::hasColumn('write_offs', 'write_off_date')) {
            $query->orderBy('write_offs.write_off_date', 'desc');
        } else {
            $query->orderBy('write_offs.id', 'desc');
        }
    }

    // Paginate (still a query builder here)
    $writeoffs = $query->paginate(15)->appends($request->query());

    return view('inventory.writeoffs.writeoffs', compact('writeoffs', 'subshop', 'products'));
}

    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('writeoffs.index')])
                ->with('error', 'Please select a shop first');
        }

        $base = WriteOff::with(['product','creator','subshop'])
            ->where('subshop_id', $subshopId);

        if ($request->filled('search')) {
            $search = $request->search;
            $base->where(function($qq) use ($search){
                $qq->whereHas('product', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('barcode', 'like', "%{$search}%");
                    })
                   ->orWhere('reason', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status') && in_array($request->status, ['pending','approved','rejected'])) {
            $base->where('status', $request->status);
        }
        if ($request->filled('reason')) {
            $base->where('reason', $request->input('reason'));
        }
        if ($request->filled('date_from')) { $base->whereDate('write_off_date', '>=', $request->input('date_from')); }
        if ($request->filled('date_to'))   { $base->whereDate('write_off_date', '<=', $request->input('date_to')); }
        if ($request->filled('min_qty'))   { $base->where('quantity', '>=', (int)$request->input('min_qty')); }
        if ($request->filled('max_qty'))   { $base->where('quantity', '<=', (int)$request->input('max_qty')); }
        if ($request->filled('min_total')) { $base->where('total_value', '>=', (float)$request->input('min_total')); }
        if ($request->filled('max_total')) { $base->where('total_value', '<=', (float)$request->input('max_total')); }
        if ($request->filled('recorded_by')){
            $name = $request->input('recorded_by');
            $base->whereHas('creator', function($q) use ($name){ $q->where('name','like', "%{$name}%"); });
        }

        // default sort by date desc
        $base->orderByDesc('write_off_date');

        $rows = $base->get();

        if ($format === 'csv') {
            return response()->stream(function () use ($rows) {
                $h = fopen('php://output', 'w');
                fputcsv($h, ['Date','Product','Reason','Qty','Unit Price','Total Value','Status','Recorded By','Notes']);
                foreach ($rows as $e) {
                    fputcsv($h, [
                        optional($e->write_off_date)->format('Y-m-d'),
                        optional($e->product)->name ?? '-',
                        $e->reason,
                        (int)$e->quantity,
                        number_format((float)$e->unit_price, 2, '.', ''),
                        number_format((float)$e->total_value, 2, '.', ''),
                        strtoupper($e->status),
                        optional($e->creator)->name ?? '-',
                        $e->description,
                    ]);
                }
                fclose($h);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="writeoffs_'.now()->format('Y-m-d_H-i-s').'.csv"',
            ]);
        }

        if ($format === 'excel') {
            $exportRows = $rows->map(function($e){
                return [
                    'Date' => optional($e->write_off_date)->format('Y-m-d'),
                    'Product' => optional($e->product)->name ?? '-',
                    'Reason' => $e->reason,
                    'Qty' => (int)$e->quantity,
                    'Unit Price' => (float)$e->unit_price,
                    'Total Value' => (float)$e->total_value,
                    'Status' => strtoupper($e->status),
                    'Recorded By' => optional($e->creator)->name ?? '-',
                    'Notes' => $e->description,
                ];
            });
            return Excel::download(new \App\Exports\GenericArrayExport($exportRows->toArray(), 'Write-offs'), 'writeoffs_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }

        if ($format === 'pdf') {
            $subshop = SubShop::find($subshopId);
            $summary = [
                'count' => $rows->count(),
                'total_value' => (float) $rows->sum('total_value'),
                'approved_total' => (float) $rows->where('status','approved')->sum('total_value'),
                'pending_total' => (float) $rows->where('status','pending')->sum('total_value'),
                'rejected_total' => (float) $rows->where('status','rejected')->sum('total_value'),
                'approved_count' => (int) $rows->where('status','approved')->count(),
                'pending_count' => (int) $rows->where('status','pending')->count(),
                'rejected_count' => (int) $rows->where('status','rejected')->count(),
            ];
            $pdf = PDF::loadView('exports.writeoffs_pdf', [
                'rows' => $rows,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->user())->name ?? 'System',
            ]);
            return $pdf->download('writeoffs_'.now()->format('Y-m-d_H-i-s').'.pdf');
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subshop_id' => 'required|exists:sub_shops,id',
            'product_id' => 'required|exists:items,id',
            'batch_id'   => 'required|exists:item_batches,id',
            'quantity' => 'required|integer|min:1',
            'writeoff_date' => 'required|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Start transaction to ensure data consistency
        return DB::transaction(function () use ($validated, $request) {
            // Get the product and batch with lock for update
            $product = Item::where('id', $validated['product_id'])
                ->where('subshop_id', $validated['subshop_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $batch = ItemBatch::where('id', $validated['batch_id'])
                ->where('item_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Check if there's enough quantity in the batch
            if ($batch->quantity < $validated['quantity']) {
                $available = (int) $batch->quantity;
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock in selected batch. Only ' . $available . ' items available in this batch.',
                        'errors' => ['quantity' => ['Insufficient stock in selected batch.']],
                    ], 422);
                }
                return back()->with('error', 'Insufficient stock in selected batch. Only ' . $available . ' items available.');
            }

            // Calculate total value using batch selling price
            $unit_price = (float) ($batch->selling_price ?? $product->price);
            $total_value = $unit_price * (int)$validated['quantity'];

            // Create the write-off record
            $writeoff = new WriteOff([
                'subshop_id' => $validated['subshop_id'],
                'item_id' => $validated['product_id'],
                'batch_id' => $validated['batch_id'],
                'quantity' => (int)$validated['quantity'],
                'write_off_date' => $validated['writeoff_date'],
                'reason' => $validated['reason'],
                'description' => $validated['notes'] ?? null,
                'unit_price' => (float)$unit_price,
                'total_value' => (float)$total_value,
                'status'=> 'pending',
                'created_by' => Auth::id(),
            ]);
            $writeoff->save();

            // Decrement batch quantity (and product cached quantity if used)
            $batch->decrement('quantity', (int)$validated['quantity']);
            $batch->save();
            if (Schema::hasColumn('items', 'quantity')) {
                $product->decrement('quantity', (int)$validated['quantity']);
                $product->save();
            }

            // Check if the request is an AJAX request
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Write-off recorded successfully and pending approval.',
                        'redirect' => route('writeoffs.index')
                ]);
            }

            return redirect()
                ->route('writeoffs.index')
                ->with('success', 'Write-off recorded successfully and pending approval.');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
public function updateStatus(Request $request, $id)
{
    $validated = $request->validate([
        'status' => 'required|in:approved,rejected',
        'notes' => 'nullable|string',
    ]);

    $writeoff = WriteOff::with(['product'])->findOrFail($id);
    
    if ($writeoff->status !== 'pending') {
        return response()->json([
            'success' => false,
            'message' => 'Only pending write-offs can be updated.'
        ], 422);
    }

    return DB::transaction(function () use ($writeoff, $validated) {
      
        if ($validated['status'] === 'rejected') {
            if (isset($writeoff->batch_id)) {
                $batch = ItemBatch::lockForUpdate()->find($writeoff->batch_id);
                if ($batch) {
                    $batch->increment('quantity', (int)$writeoff->quantity);
                    $batch->save();
                }
            }
            if ($writeoff->product && Schema::hasColumn('items', 'quantity')) {
                $writeoff->product->increment('quantity', (int)$writeoff->quantity);
            }
        }

        if ($validated['status'] === 'rejected'){
            $writeoff->quantity = 0 ;
        }


        $writeoff->status = $validated['status'];
        $writeoff->reviewed_by = Auth::id();
        $writeoff->reviewed_at = now();
        $writeoff->review_notes = $validated['notes'] ?? null;
        $writeoff->save();

     
        return response()->json([
            'success' => true,
            'message' => 'Write-off has been ' . $validated['status'] . '.',
            'status' => $validated['status']
        ]);
    });
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $writeoff = WriteOff::findOrFail($id);
        
        // Only allow deletion of pending write-offs
        if ($writeoff->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending write-offs can be deleted.'
            ], 422);
        }

        return DB::transaction(function () use ($writeoff) {
            // Return the quantity to the batch (and product cached quantity if used)
            if (isset($writeoff->batch_id)) {
                $batch = ItemBatch::lockForUpdate()->find($writeoff->batch_id);
                if ($batch) {
                    $batch->increment('quantity', (int)$writeoff->quantity);
                    $batch->save();
                }
            }
            if ($writeoff->product && Schema::hasColumn('items', 'quantity')) {
                $writeoff->product->increment('quantity', (int)$writeoff->quantity);
            }
            
            $writeoff->delete();

            return response()->json([
                'success' => true,
                'message' => 'Write-off has been deleted.'
            ]);
        });
    }

    public function subshops()
    {
        return redirect()->route('subshops.choose', ['intended' => route('writeoffs.index')]);
    }

    /**
     * Quickly write-off an expired batch: create a write-off for the batch's full remaining quantity
     * and delete the batch. Intended to be triggered from the items list Earliest Expiry action.
     */
    public function writeOffExpiredBatch(Request $request)
    {
        $validated = $request->validate([
            'subshop_id' => 'required|exists:sub_shops,id',
            'item_id' => 'required|exists:items,id',
            'batch_id' => 'required|exists:item_batches,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $product = Item::where('id', $validated['item_id'])
                ->where('subshop_id', $validated['subshop_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $batch = ItemBatch::where('id', $validated['batch_id'])
                ->where('item_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $qty = (int)($batch->quantity ?? 0);
            if ($qty <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch has no remaining quantity to write off.'
                ], 422);
            }

            $unit_price = (float) ($batch->selling_price ?? $product->price ?? 0);
            $total_value = $unit_price * $qty;

            $writeoff = new WriteOff([
                'subshop_id' => $validated['subshop_id'],
                'item_id' => $validated['item_id'],
                'batch_id' => $validated['batch_id'],
                'quantity' => $qty,
                'write_off_date' => now(),
                'reason' => 'expiry',
                'description' => 'Auto write-off: expired batch',
                'unit_price' => $unit_price,
                'total_value' => $total_value,
                'status' => 'approved',
                'created_by' => Auth::id(),
            ]);
            $writeoff->save();

            if (Schema::hasColumn('items', 'quantity')) {
                $product->decrement('quantity', $qty);
                $product->save();
            }

            $batch->delete();

            return response()->json([
                'success' => true,
                'message' => 'Expired batch written off and removed.',
            ]);
        });
    }
}
