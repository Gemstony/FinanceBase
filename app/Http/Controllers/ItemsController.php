<?php

namespace App\Http\Controllers;

use App\Exports\ItemsExport;
use App\Services\ItemImportService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\SubShop;
use App\Models\Category;
use App\Models\Suppliers;
use App\Models\WriteOff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('items.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('items.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $items = Item::where('subshop_id', $subshopId)
            ->with(['category', 'supplier', 'writeOffs', 'itemBatches'])
            ->orderBy('id', 'DESC');

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $items->where(function($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('sku', 'like', '%' . $request->search . '%')
                      ->orWhere('barcode', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Apply advanced filters
        if ($request->filled('category_id')) {
            $items->where('category_id', $request->category_id);
        }

        if ($request->filled('supplier_id')) {
            $items->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'active') {
                $items->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $items->where('is_active', false);
            }
        }

        if ($request->filled('min_price')) {
            $items->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $items->where('price', '<=', $request->max_price);
        }

        if ($request->filled('min_quantity')) {
            $items->where('quantity', '>=', $request->min_quantity);
        }

        if ($request->filled('max_quantity')) {
            $items->where('quantity', '<=', $request->max_quantity);
        }

        if ($request->filled('created_from')) {
            $items->whereDate('created_at', '>=', $request->created_from);
        }

        if ($request->filled('created_to')) {
            $items->whereDate('created_at', '<=', $request->created_to);
        }

        $items = $items->get();

        // Summary statistics (overall, not filtered)
        $allItems = Item::where('subshop_id', $subshopId)->with('itemBatches')->get();
        $stats = [
            'total_items' => $allItems->count(),
            'total_value' => $allItems->sum(function ($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
                return $avgSellingPrice * $totalQuantity;
            }),
            'items_in_stock' => $allItems->filter(function ($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                return $totalQuantity > 0;
            })->count(),
            'items_out_of_stock' => $allItems->filter(function ($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                return $totalQuantity <= 0;
            })->count(),
            'low_stock_items' => $allItems->filter(function ($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                return $totalQuantity > 0 && $item->min_quantity && $totalQuantity <= $item->min_quantity;
            })->count(),
            'active_items' => $allItems->where('is_active', true)->count(),
            'total_categories' => $allItems->pluck('category_id')->unique()->filter()->count(),
            'total_suppliers' => $allItems->pluck('supplier_id')->unique()->filter()->count(),
        ];

        // Get categories and suppliers for filter dropdowns
        $categories = Category::where('subshop_id', $subshopId)->get();
        $suppliers = Suppliers::where('subshop_id', $subshopId)->get();

        $destinationSubshops = SubShop::where('shop_id', $subshop->shop_id)
            ->active()
            ->where('id', '!=', $subshop->id)
            ->orderBy('name')
            ->get(['id','name']);

        return view("inventory.items.items", compact("items", "subshop", "stats", "categories", "suppliers", "destinationSubshops"));
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
        try {
            $validated = $request->validate([
                'subshop_id' => 'required|exists:sub_shops,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'nullable|exists:categories,id',
                'supplier_id' => 'nullable|exists:suppliers,id',
                'price' => 'required|numeric|min:0',
                'cost_price' => 'required|numeric|min:0',
                'quantity' => 'required|integer|min:0',
                'min_quantity' => 'required|integer|min:0',
                'max_quantity' => 'nullable|integer|min:0',
                'unit' => 'required|string|max:50',
                'expiry_date' => 'nullable|date|after:today',
            ]);

            $data = $request->only([
                'subshop_id', 'name', 'description', 'category_id', 'supplier_id',
                'price', 'cost_price', 'quantity', 'min_quantity',
                'max_quantity', 'unit', 'expiry_date'
            ]);

            // Ensure subshop_id is properly set and valid
            if (!isset($data['subshop_id']) || !is_numeric($data['subshop_id'])) {
                throw new \Exception('Invalid subshop ID provided');
            }
            
            // Verify the subshop exists
            $subshop = SubShop::find($data['subshop_id']);
            if (!$subshop) {
                throw new \Exception('Selected subshop does not exist');
            }
            
            $data['is_active'] = $request->has('is_active');
            
            // Handle potential race conditions with SKU generation
            $maxAttempts = 5;
            $attempts = 0;
            $item = null;
            $lastError = null;
            
            DB::beginTransaction();
            
            try {
                while ($attempts < $maxAttempts) {
                    try {
                        // Generate SKU and barcode with the verified subshop_id
            $data['sku'] = Item::generateSKU($data['subshop_id']);
            $data['barcode'] = Item::generateBarcode();

                        // Log the data being saved for debugging
                        \Log::info('Creating item with data:', [
                            'subshop_id' => $data['subshop_id'],
                            'sku' => $data['sku'],
                            'name' => $data['name']
                        ]);
                        
                        // Attempt to create the item
            $item = Item::create($data);

                        // Also create an initial batch for this item
                        $batch = ItemBatch::create([
                            'item_id' => $item->id,
                            'batch_number' => ItemBatch::generateBatchNumber($item->id),
                            'quantity' => $data['quantity'] ?? 0,
                            'cost_price' => $data['cost_price'] ?? null,
                            'selling_price' => $data['price'] ?? null,
                            'expire_date' => $data['expiry_date'] ?? null,
                        ]);

                        DB::commit();

            // Check if this is an AJAX request
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Item created successfully.',
                    'item' => $item,
                    'batch' => $batch ?? null,
                ]);
            }

                        return redirect()->route('items.index')
                            ->with('success', 'Item created successfully.');
                            
                    } catch (\Illuminate\Database\QueryException $e) {
                        DB::rollBack();
                        
                        // Log the error for debugging
                        \Log::error('Error creating item: ' . $e->getMessage(), [
                            'subshop_id' => $data['subshop_id'] ?? null,
                            'attempt' => $attempts + 1,
                            'exception' => $e
                        ]);
                        
                        // If it's a duplicate entry error for SKU, retry
                        if (str_contains($e->getMessage(), 'Duplicate entry') && 
                            (str_contains($e->getMessage(), 'items_sku_unique') || 
                             str_contains($e->getMessage(), 'sku'))) {
                            $attempts++;
                            $lastError = 'Failed to generate a unique SKU. Please try again.';
                            
                            if ($attempts >= $maxAttempts) {
                                throw new \Exception('Failed to generate a unique SKU after ' . $maxAttempts . ' attempts. Please try again.');
                            }
                            
                            // Add a small delay before retrying
                            usleep(100000); // 100ms
                            continue;
                        }
                        
                        // If it's a different error, re-throw it with more context
                        throw new \Exception('Database error: ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
            // If we get here, we've exceeded max attempts
            throw new \Exception($lastError ?? 'Failed to create item. Please try again.');
            
            // Note: The success case should be handled before any exception is thrown
            // The redirect should happen in the try block after successful item creation
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;
        }
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
    public function update(Request $request, Item $item)
    {
        // Handle both JSON and form data
        $input = $request->isJson() ? $request->json()->all() : $request->all();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0|lte:price',
            'quantity' => 'required|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'expiry_date' => 'nullable|date',
        ]);

        $data = [
            'name' => $input['name'] ?? null,
            'description' => $input['description'] ?? null,
            'category_id' => $input['category_id'] ?? null,
            'supplier_id' => $input['supplier_id'] ?? null,
            'price' => $input['price'] ?? null,
            'cost_price' => $input['cost_price'] ?? null,
            'quantity' => $input['quantity'] ?? null,
            'min_quantity' => $input['min_quantity'] ?? null,
            'max_quantity' => $input['max_quantity'] ?? null,
            'unit' => $input['unit'] ?? null,
            'expiry_date' => $input['expiry_date'] ?? null,
        ];

        $data['is_active'] = isset($input['is_active']) ? ($input['is_active'] === '1' || $input['is_active'] === true) : false;

        \Log::info('Update Item Input:', $input);
        \Log::info('Update Item Data to save:', $data);

        $item->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully.'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        DB::transaction(function () use ($item) {
            // Delete all batches linked to this item first
            ItemBatch::where('item_id', $item->id)->delete();
            // Then delete the item itself
            $item->delete();
        });

        return redirect()->back()->with('success', 'Item and its batches deleted successfully.');
    }

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Generate a new batch number
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateBatchNumber()
    {
        try {
            $batchNumber = Item::generateBatchNumber();
            return response()->json([
                'success' => true,
                'batch' => $batchNumber
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate batch number',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function subshops(){
        return redirect()->route('subshops.choose', ['intended' => route('items.index')]);
    }


    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('items.index')])
                ->with('error', 'Please select a shop first.');
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        if ($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('items.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }

        $items = Item::where('subshop_id', $subshopId)
            ->with(['category', 'supplier', 'itemBatches'])
            ->orderBy('id', 'DESC');

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $items->where(function($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('sku', 'like', '%' . $request->search . '%')
                      ->orWhere('barcode', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Apply advanced filters
        if ($request->filled('category_id')) {
            $items->where('category_id', $request->category_id);
        }

        if ($request->filled('supplier_id')) {
            $items->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'active') {
                $items->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $items->where('is_active', false);
            }
        }

        if ($request->filled('min_price')) {
            $items->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $items->where('price', '<=', $request->max_price);
        }

        if ($request->filled('min_quantity')) {
            $items->where('quantity', '>=', $request->min_quantity);
        }

        if ($request->filled('max_quantity')) {
            $items->where('quantity', '<=', $request->max_quantity);
        }

        if ($request->filled('created_from')) {
            $items->whereDate('created_at', '>=', $request->created_from);
        }

        if ($request->filled('created_to')) {
            $items->whereDate('created_at', '<=', $request->created_to);
        }

        $items = $items->get();

        if ($format === 'csv') {
            return response()->stream(function () use ($items, $subshop) {
                $handle = fopen('php://output', 'w');

                // CSV headers
                fputcsv($handle, [
                    'ID',
                    'Name',
                    'SKU',
                    'Barcode',
                    'Category',
                    'Supplier',
                    'Min Selling Price',
                    'Max Selling Price',
                    'Avg Cost Price',
                    'Total Quantity',
                    'Number of Batches',
                    'Earliest Expiry',
                    'Margin %',
                    'Min Quantity',
                    'Max Quantity',
                    'Unit',
                    'Status',
                    'Created At'
                ]);

                // Data rows
                foreach ($items as $item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    $batchCount = $item->itemBatches->count();
                    $minPrice = $item->itemBatches->min('selling_price');
                    $maxPrice = $item->itemBatches->max('selling_price');
                    $avgCostPrice = $item->itemBatches->avg('cost_price');
                    $earliestExpiry = $item->itemBatches->whereNotNull('expire_date')->min('expire_date');
                    $marginPercentage = $avgCostPrice && $avgCostPrice > 0 
                        ? (($item->price - $avgCostPrice) / $avgCostPrice) * 100 
                        : ($item->cost_price && $item->cost_price > 0 ? $item->margin_percentage : 0);

                    fputcsv($handle, [ 
                        $item->id,
                        $item->name,
                        $item->sku ?? '',
                        $item->barcode ?? '',
                        $item->category ? $item->category->name : '',
                        $item->supplier ? $item->supplier->name : '',
                        $minPrice ? number_format((float) $minPrice, 2) : number_format((float) $item->price, 2),
                        $maxPrice ? number_format((float) $maxPrice, 2) : number_format((float) $item->price, 2),
                        $avgCostPrice ? number_format((float) $avgCostPrice, 2) : ($item->cost_price ? number_format((float) $item->cost_price, 2) : ''),
                        $totalQuantity,
                        $batchCount,
                        $earliestExpiry ? $earliestExpiry : '',
                        number_format((float) $marginPercentage, 2),
                        $item->min_quantity ?? '',
                        $item->max_quantity ?? '',
                        $item->unit ?? '',
                        $item->is_active ? 'Active' : 'Inactive',
                        $item->created_at->format('Y-m-d H:i:s')
                    ]);
                }

                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $subshop->name . '_items_' . now()->format('Y-m-d_H-i-s') . '.csv"',
            ]);
        }

        if ($format === 'excel') {
            return Excel::download(new ItemsExport($items), $subshop->name . '_items_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
        }

        if ($format === 'pdf') {
            // Calculate stock metrics
            $outOfStockItems = $items->filter(function($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                return $totalQuantity <= 0;
            });
            $lowStockItems = $items->filter(function($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                return $totalQuantity > 0 && $item->min_quantity && $totalQuantity <= $item->min_quantity;
            });
            $overstockedItems = $items->filter(function($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                return $totalQuantity > 0 && $item->max_quantity && $totalQuantity > $item->max_quantity * 1.2;
            });
            $stats = [
                'total_items' => $items->count(),
                'total_value' => $items->sum(function ($item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
                    return $avgSellingPrice * $totalQuantity;
                }),
                'items_in_stock' => $items->filter(function($item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    return $totalQuantity > 0;
                })->count(),
                'items_out_of_stock' => $items->filter(function($item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    return $totalQuantity <= 0;
                })->count(),
                'low_stock_items' => $items->filter(function($item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    return $totalQuantity > 0 && $item->min_quantity && $totalQuantity <= $item->min_quantity;
                })->count(),
                'active_items' => $items->where('is_active', true)->count(),
                'total_categories' => $items->pluck('category_id')->unique()->filter()->count(),
                'total_suppliers' => $items->pluck('supplier_id')->unique()->filter()->count(),
            ];

            // Prepare additional variables for the view
            $outOfStockCount = $outOfStockItems->count();
            $overstockedCount = $overstockedItems->count();

            $pdf = PDF::loadView('exports.items_pdf', compact(
                'items', 
                'stats', 
                'subshop',
                'outOfStockCount',
                'overstockedCount',
                'outOfStockItems',
                'lowStockItems',
                'overstockedItems'
            ));
            return $pdf->download($subshop->name . '_items_' . now()->format('Y-m-d_H-i-s') . '.pdf');
        }

        // For now, redirect back for unsupported formats
        return redirect()->back()->with('error', 'Export format not yet implemented.');
    }
    
    /**
     * Download a sample CSV file for import
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadSample()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=items_import_sample.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for proper encoding in Excel
            fputs($handle, "\xEF\xBB\xBF");
            
            // Headers - MUST MATCH DATABASE COLUMNS
            fputcsv($handle, [
                'name*',
                'description',
                'supplier_name',   // Must match existing supplier name or leave empty
                'sku',
                'barcode',
                'price*',
                'cost_price',
                'quantity*',
                'min_quantity',
                'max_quantity',
                'unit',
                'is_active',

                // Note: subshop_id is automatically set from the current subshop
                // Note: category_id and supplier_id are looked up by name
            ]);
            
            // Sample data - Make sure to use existing category and supplier names
            fputcsv($handle, [
                'Item 1',
                'decription about item one',
                'Tech Supplier', // Must exist in suppliers or leave empty
                'LP-001',
                'LP001123456',
                '120000.00',
                '90000.00',
                '10',
                '2',
                '20',
                'piece',
                '1',
            ]);
            
            // Add another sample row
            fputcsv($handle, [
                'Item 2',
                '',
                '', // Must exist in suppliers or leave empty
                '',
                '',
                '15000.00',
                '8000.00',
                '25',
                '',
                '',
                '',
                '',
            ]);
            
            fclose($handle);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * Import items from CSV file
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        if (!$request->hasFile('import_file')) {
            return response()->json([
                'success' => false,
                'message' => 'No file was uploaded.'
            ], 400);
        }

        try {
            $importService = new ItemImportService();

            // Allow both 'category_id' (preferred) and legacy 'category_name' (which actually holds ID in the view)
            $categoryId = $request->input('category_id');
            if (!$categoryId) {
                $categoryId = $request->input('category_name');
            }

            $results = $importService->processImport(
                $request->file('import_file'),
                (int) $request->input('subshop_id'),
                $categoryId ? (int) $categoryId : null,
                filter_var($request->input('has_headers', true), FILTER_VALIDATE_BOOLEAN)
            );

            $totalRows = $results['imported'] + $results['skipped'];
            
            // Check if import was successful
            if ($results['imported'] === 0 && $totalRows > 0) {
                // All rows failed
                return response()->json([
                    'success' => false,
                    'message' => 'Import failed. All rows were skipped or had errors.',
                    'total_rows' => $totalRows,
                    'imported' => $results['imported'],
                    'skipped' => $results['skipped'],
                    'errors' => $results['errors']
                ], 400);
            }
            
            if ($totalRows === 0) {
                // No data to import
                return response()->json([
                    'success' => false,
                    'message' => 'No data found in the CSV file.',
                    'total_rows' => 0,
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => []
                ], 400);
            }
            
            // Success with some or all rows imported
            $message = "Successfully processed {$totalRows} rows. " .
                      "Imported: {$results['imported']}, Skipped: {$results['skipped']}";

            return response()->json([
                'success' => true,
                'message' => $message,
                'total_rows' => $totalRows,
                'imported' => $results['imported'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors']
            ]);

        } catch (\Exception $e) {
            \Log::error('Import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Import failed: ' . $e->getMessage();
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'errors' => [$e->getMessage()],
                'total_rows' => 0,
                'imported' => 0,
                'skipped' => 0,
            ], 400);
        }
    }
 /* Get summary data for the items dashboard
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function getSummaryData()
{
    try {
        $subshopId = session('subshop_id');
        \Log::info('getSummaryData called with session subshop_id: ' . $subshopId);
        
        if (!$subshopId) {
            \Log::warning('getSummaryData: No subshop_id found in session');
            return response()->json(['error' => 'No active shop selected. Please select a shop first.'], 400);
        }

        $subshop = SubShop::find($subshopId, ['id', 'name']);
        if (!$subshop) {
            \Log::warning('getSummaryData: Subshop not found for ID: ' . $subshopId);
            return response()->json(['error' => 'Subshop not found or you do not have access to it'], 404);
        }

        \Log::info('getSummaryData: Building data for subshop: ' . $subshop->name);
        $data = $this->buildSummaryData($subshopId, $subshop);
        
        \Log::info('getSummaryData: Data built successfully', [
            'recentItems_count' => count($data['recentItems'] ?? []),
            'inStockItems_count' => count($data['inStockItems'] ?? []),
            'outOfStockItems_count' => count($data['outOfStockItems'] ?? []),
            'totalItems' => $data['totalItems'] ?? 0
        ]);
        
        return response()->json($data);

    } catch (\Exception $e) {
        \Log::error('Error in getSummaryData: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'An error occurred while fetching summary data',
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Build summary data for dashboard
 *
 * @param int $subshopId
 * @param \App\Models\SubShop $subshop
 * @return array
 */
private function buildSummaryData(int $subshopId, $subshop): array
{
        $recentItems = $this->getRecentItems($subshopId);
        $inStockItems = $this->getInStockItems($subshopId);
        $outOfStockItems = $this->getOutOfStockItems($subshopId);
        $topValuableItems = $this->getTopValuableItems($subshopId);
        $valueByCategoryArr = $this->getValueByCategory($subshopId);
        $itemCounts = $this->getItemCounts($subshopId);
        $lowStockItems = $this->getLowStockItems($subshopId);
        $activeItems = $this->getActiveItems($subshopId);
        $categories = $this->getCategories($subshopId);
        $suppliers = $this->getSuppliers($subshopId);

    // Work with collections for aggregations, then convert back to arrays
    $valueByCategoryCol = collect($valueByCategoryArr);
    $totalInventoryValue = $valueByCategoryCol->sum('value');
    $valueByCategoryCol = $this->calculateCategoryPercentages($valueByCategoryCol, $totalInventoryValue);

    return [
        'recentItems' => $recentItems,
        'inStockItems' => $inStockItems,
        'outOfStockItems' => $outOfStockItems,
        'topValuableItems' => $topValuableItems,
        'valueByCategory' => $valueByCategoryCol->toArray(),
        'totalInventoryValue' => $totalInventoryValue,
        'formattedTotalInventoryValue' => 'TZS ' . number_format($totalInventoryValue, 2),
        'totalItems' => $itemCounts->total ?? 0,
        'inStockCount' => $itemCounts->in_stock_count ?? 0,
        'outOfStockCount' => $itemCounts->out_of_stock_count ?? 0,
        'lowStockCount' => $itemCounts->low_stock_count ?? 0,
        'activeCount' => $itemCounts->active_count ?? 0,
        'lowStockItems' => $lowStockItems,
        'activeItems' => $activeItems,
        'categories' => $categories,
        'suppliers' => $suppliers,
        'totalCategories' => is_array($categories) ? count($categories) : ($categories->count() ?? 0),
        'totalSuppliers' => is_array($suppliers) ? count($suppliers) : ($suppliers->count() ?? 0),
        'lastUpdated' => now()->toDateTimeString(),
        'currency' => 'TZS',
        'subshop' => $subshop
    ];
}

/**
 * Get recent items
 */
private function getRecentItems(int $subshopId)
{
    return Item::where('subshop_id', $subshopId)
                ->with(['category', 'supplier', 'itemBatches'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function($item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;

                    return [
                        'id' => $item->id,
                        'name' => $item->name ?? 'N/A',
                'price' => $avgSellingPrice,
                'formatted_price' => 'TZS ' . number_format($avgSellingPrice, 2),
                'quantity' => $totalQuantity,
                'status' => $totalQuantity > 0 ? 'In Stock' : 'Out of Stock',
                'status_class' => $totalQuantity > 0 ? 'success' : 'danger',
                        'category' => $item->category ? $item->category->name : 'Uncategorized',
                        'supplier' => $item->supplier ? $item->supplier->name : 'No Supplier',
                'created_at' => ($item->created_at ?? now())->format('Y-m-d H:i:s')
                    ];
                })
                ->toArray();
}

/**
 * Get in-stock items
 */
private function getInStockItems(int $subshopId)
{
    return Item::where('subshop_id', $subshopId)
                ->with(['category', 'supplier', 'itemBatches'])
                ->get()
                ->filter(function($item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    return $totalQuantity > 0;
                })
                ->sortBy(function($item) {
                    return $item->itemBatches->sum('quantity');
                })
                ->take(10)
                ->map(function($item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
            $minQuantity = $item->min_quantity ?? 0;
                    
                    return [
                        'id' => $item->id,
                        'name' => $item->name ?? 'N/A',
                        'quantity' => $totalQuantity,
                        'price' => $avgSellingPrice,
                        'formatted_price' => 'TZS ' . number_format($avgSellingPrice, 2),
                'total_value' => $avgSellingPrice * $totalQuantity,
                'formatted_total_value' => 'TZS ' . number_format($avgSellingPrice * $totalQuantity, 2),
                        'min_quantity' => $minQuantity,
                        'max_quantity' => $item->max_quantity ?? null,
                        'unit' => $item->unit ?? 'pcs',
                        'category' => $item->category ? $item->category->name : 'Uncategorized',
                        'supplier' => $item->supplier ? $item->supplier->name : 'No Supplier',
                        'status' => $minQuantity > 0 && $totalQuantity <= $minQuantity ? 'Low Stock' : 'In Stock',
                        'status_class' => $minQuantity > 0 && $totalQuantity <= $minQuantity ? 'warning' : 'success',
                'last_updated' => ($item->updated_at ?? now())->format('M d, Y H:i')
                    ];
                })
                ->toArray();
}

/**
 * Get out-of-stock items
 */
private function getOutOfStockItems(int $subshopId)
{
    return Item::where('subshop_id', $subshopId)
                ->with(['category', 'supplier', 'itemBatches'])
                ->get()
                ->filter(function($item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    return $totalQuantity <= 0;
                })
                ->sortByDesc(function($item) {
                    return $item->updated_at ?? $item->created_at;
                })
                ->take(10)
                ->map(function($item) {
                    $updatedAt = $item->updated_at ?? now();
                    
                    return [
                        'id' => $item->id,
                        'name' => $item->name ?? 'N/A',
                'price' => $item->price ?? 0,
                'formatted_price' => 'TZS ' . number_format($item->price ?? 0, 2),
                'quantity' => $item->itemBatches->sum('quantity'),
                        'min_quantity' => $item->min_quantity ?? null,
                        'unit' => $item->unit ?? 'pcs',
                        'category' => $item->category ? [
                            'id' => $item->category->id ?? null,
                            'name' => $item->category->name ?? 'Uncategorized'
                        ] : null,
                        'supplier' => $item->supplier ? [
                            'id' => $item->supplier->id ?? null,
                            'name' => $item->supplier->name ?? 'No Supplier',
                            'phone' => $item->supplier->phone ?? null,
                            'email' => $item->supplier->email ?? null
                        ] : null,
                        'last_stocked' => $updatedAt->format('M d, Y'),
                        'days_out_of_stock' => now()->diffInDays($updatedAt),
                        'is_active' => $item->is_active ?? false
                    ];
                })
                ->toArray();
}

/**
 * Get top valuable items
 */
private function getTopValuableItems(int $subshopId)
{
    return Item::where('subshop_id', $subshopId)
                ->with(['category', 'itemBatches'])
                ->get()
                ->filter(function($item) {
                    return $item->itemBatches->sum('quantity') > 0;
                })
                ->map(function($item) {
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
                    $value = $avgSellingPrice * $totalQuantity;
                    
                    return [
                        'id' => $item->id,
                        'name' => $item->name ?? 'N/A',
                        'value' => $value,
                        'formatted_value' => 'TZS ' . number_format($value, 2),
                        'quantity' => $totalQuantity,
                        'formatted_quantity' => number_format($totalQuantity, 0) . ' ' . ($item->unit ?: 'pcs'),
                        'price' => $avgSellingPrice,
                        'formatted_price' => 'TZS ' . number_format($avgSellingPrice, 2),
                        'category' => $item->category ? $item->category->name : 'Uncategorized',
                        'unit' => $item->unit ?? 'pcs',
                'last_updated' => ($item->updated_at ?? now())->format('M d, Y')
                    ];
                })
                ->sortByDesc('value')
                ->take(5)
                ->values()
                ->toArray();
}

/**
 * Get value by category
 */
private function getValueByCategory(int $subshopId)
{
    // Get distinct category_ids from items for this subshop that have quantity > 0
    $categoryIds = Item::where('subshop_id', $subshopId)
        ->with('itemBatches')
        ->get()
        ->filter(function($item) {
            return $item->itemBatches->sum('quantity') > 0;
        })
        ->pluck('category_id')
        ->filter()
        ->unique()
        ->values();
    
    $categories = Category::whereIn('id', $categoryIds)->get();
    
    $valueByCategory = $categories->map(function($category) use ($subshopId) {
        // Get items for this category
        $items = Item::where('subshop_id', $subshopId)
            ->where('category_id', $category->id)
            ->with('itemBatches')
            ->get();
            
        $totalValue = $items->sum(function($item) {
            $totalQuantity = $item->itemBatches->sum('quantity');
            $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
            return $avgSellingPrice * $totalQuantity;
        });
        $totalItems = $items->count();
        $avgValue = $totalItems > 0 ? $totalValue / $totalItems : 0;

        return [
            'id' => $category->id,
            'name' => $category->name ?? 'Uncategorized',
            'description' => $category->description ?? '',
            'value' => $totalValue,
            'formatted_value' => 'TZS ' . number_format($totalValue, 2),
            'item_count' => $totalItems,
            'average_value' => $avgValue,
            'formatted_average_value' => 'TZS ' . number_format($avgValue, 2),
            'percentage' => 0,
            'items' => $items->map(function($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
                return [
                    'id' => $item->id,
                    'name' => $item->name ?? 'N/A',
                    'quantity' => $totalQuantity,
                    'price' => $avgSellingPrice,
                    'value' => $avgSellingPrice * $totalQuantity
                ];
            })
        ];
    });

    // Add uncategorized items
    $uncategorizedItems = Item::where('subshop_id', $subshopId)
        ->whereNull('category_id')
        ->with('itemBatches')
        ->get()
        ->filter(function($item) {
            return $item->itemBatches->sum('quantity') > 0;
        });
        
    $uncategorizedValue = $uncategorizedItems->sum(function($item) {
        $totalQuantity = $item->itemBatches->sum('quantity');
        $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
        return $avgSellingPrice * $totalQuantity;
    });
    $uncategorizedCount = $uncategorizedItems->count();
    
    if ($uncategorizedValue > 0) {
        $valueByCategory->push([
            'id' => null,
            'name' => 'Uncategorized',
            'description' => 'Items without a category',
            'value' => $uncategorizedValue,
            'formatted_value' => 'TZS ' . number_format($uncategorizedValue, 2),
            'item_count' => $uncategorizedCount,
            'average_value' => $uncategorizedCount > 0 ? $uncategorizedValue / $uncategorizedCount : 0,
            'formatted_average_value' => 'TZS ' . number_format($uncategorizedCount > 0 ? $uncategorizedValue / $uncategorizedCount : 0, 2),
            'percentage' => 0,
            'items' => $uncategorizedItems->map(function($item) {
                $totalQuantity = $item->itemBatches->sum('quantity');
                $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
                return [
                    'id' => $item->id,
                    'name' => $item->name ?? 'N/A',
                    'quantity' => $totalQuantity,
                    'price' => $avgSellingPrice,
                    'value' => $avgSellingPrice * $totalQuantity
                ];
            })
        ]);
    }

    // Filter out categories with no value
    return $valueByCategory->filter(function($category) {
        return ($category['value'] ?? 0) > 0;
    })
    ->toArray();
}
private function getItemCounts(int $subshopId)
{
    $items = Item::where('subshop_id', $subshopId)->with('itemBatches')->get();

    $total = $items->count();
    $inStockCount = $items->filter(function($item) {
        return $item->itemBatches->sum('quantity') > 0;
    })->count();
    $outOfStockCount = $items->filter(function($item) {
        return $item->itemBatches->sum('quantity') <= 0;
    })->count();
    $lowStockCount = $items->filter(function($item) {
        $totalQuantity = $item->itemBatches->sum('quantity');
        return $totalQuantity > 0 && $item->min_quantity && $totalQuantity <= $item->min_quantity;
    })->count();
    $activeCount = $items->where('is_active', true)->count();

    return (object) [
        'total' => $total,
        'in_stock_count' => $inStockCount,
        'out_of_stock_count' => $outOfStockCount,
        'low_stock_count' => $lowStockCount,
        'active_count' => $activeCount
    ];
}

/**
 * Calculate category percentages
 */
private function calculateCategoryPercentages($valueByCategory, float $totalInventoryValue)
{
    return $valueByCategory->map(function($category) use ($totalInventoryValue) {
        $category['percentage'] = $totalInventoryValue > 0 ? 
            round(($category['value'] / $totalInventoryValue) * 100, 1) : 0;
        return $category;
    });
}

/**
 * Get low stock items
 */
private function getLowStockItems(int $subshopId)
{
    return Item::where('subshop_id', $subshopId)
        ->with(['category', 'supplier', 'itemBatches'])
        ->get()
        ->filter(function($item) {
            $totalQuantity = $item->itemBatches->sum('quantity');
            return $totalQuantity > 0 && $item->min_quantity && $totalQuantity <= $item->min_quantity;
        })
        ->sortBy(function($item) {
            return $item->itemBatches->sum('quantity');
        })
        ->take(20)
        ->map(function($item) {
            $totalQuantity = $item->itemBatches->sum('quantity');
            $minQuantity = $item->min_quantity ?? 0;
            $maxQuantity = $item->max_quantity ?? null;
            
            return [
                'id' => $item->id,
                'name' => $item->name ?? 'N/A',
                'category' => $item->category ? $item->category->name : 'Uncategorized',
                'supplier' => $item->supplier ? $item->supplier->name : 'No Supplier',
                'quantity' => $totalQuantity,
                'min_quantity' => $minQuantity,
                'max_quantity' => $maxQuantity,
                'unit' => $item->unit ?? 'pcs',
                'price' => $item->price ?? 0,
                'formatted_price' => 'TZS ' . number_format($item->price ?? 0, 2),
                'status' => 'Low Stock',
                'status_class' => 'warning'
            ];
        })
        ->toArray();
}

/**
 * Get active items
 */
private function getActiveItems(int $subshopId)
{
    return Item::where('subshop_id', $subshopId)
        ->where('is_active', true)
        ->with(['category', 'supplier', 'itemBatches'])
        ->orderBy('name', 'asc')
        ->take(20)
        ->get()
        ->map(function($item) {
            $totalQuantity = $item->itemBatches->sum('quantity');
            $price = $item->price ?? 0;
            
            return [
                'id' => $item->id,
                'name' => $item->name ?? 'N/A',
                'category' => $item->category ? $item->category->name : 'Uncategorized',
                'supplier' => $item->supplier ? $item->supplier->name : 'No Supplier',
                'quantity' => $totalQuantity,
                'price' => $price,
                'formatted_price' => 'TZS ' . number_format($price, 2),
                'unit' => $item->unit ?? 'pcs',
                'status' => 'Active',
                'status_class' => 'success'
            ];
        })
        ->toArray();
}

/**
 * Get categories with item counts and values
 */
private function getCategories(int $subshopId)
{
    $categories = Category::where('subshop_id', $subshopId)->get();
    
    return $categories->map(function($category) use ($subshopId) {
        $items = Item::where('subshop_id', $subshopId)
            ->where('category_id', $category->id)
            ->with('itemBatches')
            ->get();
            
        $totalValue = $items->sum(function($item) {
            $totalQuantity = $item->itemBatches->sum('quantity');
            $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
            return $avgSellingPrice * $totalQuantity;
        });
        
        return [
            'id' => $category->id,
            'name' => $category->name ?? 'Uncategorized',
            'description' => $category->description ?? '',
            'item_count' => $items->count(),
            'value' => $totalValue,
            'formatted_value' => 'TZS ' . number_format($totalValue, 2),
            'percentage' => 0 // Will be calculated client-side
        ];
    })
    ->toArray();
}

/**
 * Get suppliers with item counts and values
 */
private function getSuppliers(int $subshopId)
{
    $suppliers = Suppliers::where('subshop_id', $subshopId)->get();
    
    return $suppliers->map(function($supplier) use ($subshopId) {
        $items = Item::where('subshop_id', $subshopId)
            ->where('supplier_id', $supplier->id)
            ->with('itemBatches')
            ->get();
            
        $totalValue = $items->sum(function($item) {
            $totalQuantity = $item->itemBatches->sum('quantity');
            $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
            return $avgSellingPrice * $totalQuantity;
        });
        
        return [
            'id' => $supplier->id,
            'name' => $supplier->name ?? 'N/A',
            'contact_person' => $supplier->contact_person ?? 'N/A',
            'phone' => $supplier->phone ?? 'N/A',
            'email' => $supplier->email ?? 'N/A',
            'item_count' => $items->count(),
            'value' => $totalValue,
            'formatted_value' => 'TZS ' . number_format($totalValue, 2)
        ];
    })
    ->toArray();
}

}