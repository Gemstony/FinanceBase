<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Category;
use App\Models\Suppliers;
use App\Models\ItemBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItemImportService
{
    /**
     * Process the import of items from a CSV file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $subshopId
     * @param int $categoryId
     * @param bool $hasHeaders
     * @return array
     */
    public function processImport($file, $subshopId, $categoryId, $hasHeaders)
    {
        //$categories = $this->getCategories($subshopId);
        $suppliers = $this->getSuppliers($subshopId);
        
        
        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        // Open file
        $handle = fopen($file->getPathname(), 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open uploaded file');
        }
        
        // Skip headers if present (robust: skip blank lines until first non-empty row)
        if ($hasHeaders) {
            while (($header = fgetcsv($handle)) !== false) {
                if (!empty(array_filter($header))) {
                    break; // consumed the real header row
                }
            }
        }

        $rowNumber = $hasHeaders ? 2 : 1;
        $rowsToImport = [];
        $totalRows = 0;

        // PASS 1: Validate and build payloads only (no DB writes)
        while (($data = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($data))) {
                $rowNumber++;
                continue;
            }

            // Safety: if headers slipped through, skip them
            $firstCell = isset($data[0]) ? strtolower(trim($data[0])) : '';
            if ($hasHeaders && in_array($firstCell, ['name*', 'name'])) {
                $rowNumber++;
                continue;
            }

            $totalRows++;
            try {
                $itemData = $this->prepareItemData($data, $subshopId, $categoryId, $suppliers);
                $rowsToImport[] = $itemData;
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$rowNumber}: " . $e->getMessage();
            }

            $rowNumber++;
        }
        fclose($handle);

        // If any errors were found, abort without saving anything
        if (!empty($results['errors'])) {
            $results['imported'] = 0;
            $results['skipped'] = $totalRows;
            return $results;
        }

        // PASS 2: Persist all rows in a single transaction
        DB::beginTransaction();
        try {
            foreach ($rowsToImport as $itemData) {
                if (empty($itemData['sku'])) {
                    $itemData['sku'] = Item::generateSKU($subshopId);
                }
                if (empty($itemData['barcode'])) {
                    $itemData['barcode'] = Item::generateBarcode();
                }

                $this->saveItem($itemData, $subshopId);
                $results['imported']++;
            }
            DB::commit();
            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get categories mapped by name for the given subshop
     *
     * @param int $subshopId
     * @param int $categoryId
     * @return \Illuminate\Support\Collection
     */
    // protected function getCategories($subshopId)
    // {
    //     return Category::where('subshop_id', $subshopId)
    //         ->get()
    //         ->mapWithKeys(function($category) {
    //             return [strtolower($category->name) => $category->id];
    //         });
    // }

    /**
     * Get suppliers mapped by name for the given subshop
     *
     * @param int $subshopId
     * @param int $categoryId
     * @return \Illuminate\Support\Collection
     */
    protected function getSuppliers($subshopId)
    {
        return Suppliers::where('subshop_id', $subshopId)
            ->get()
            ->mapWithKeys(function($supplier) {
                return [strtolower($supplier->name) => $supplier->id];
            });
    }

    /**
     * Process a single row of CSV data
     *
     * @param array $data
     * @param int $rowNumber
     * @param int $subshopId
     * @param int $categoryId
     * @param \Illuminate\Support\Collection $suppliers
     * @return array
     */
    protected function processRow($data, $rowNumber, $subshopId, $categoryId, $suppliers)
    {
        try {
            // Process and validate row data
            $itemData = $this->prepareItemData($data, $subshopId, $categoryId, $suppliers);
            
            // // Validate required category
            // if (empty($itemData['category_id'])) {
            //     throw new \Exception("Category '{$data[2]}' not found.");
            // }

            if(empty($itemData["sku"])) {
                $itemData["sku"] = Item::generateSKU($subshopId);
            } 

            if(empty($itemData["barcode"])) {
                $itemData["barcode"] = Item::generateBarcode();
            } 

//    $data['sku'] = Item::generateSKU($data['subshop_id']);
//             $data['barcode'] = Item::generateBarcode();


            // Handle item creation/update
            $this->saveItem($itemData, $subshopId);
            
            return ['status' => 'success'];

        } catch (\Exception $e) {
            Log::error("Error processing row $rowNumber: " . $e->getMessage(), [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'error' => "Row $rowNumber: " . $e->getMessage()
            ];
        }
    }

    /**
     * Prepare item data from CSV row
     *
     * @param array $data
     * @param int $subshopId
     * @param int $categoryId
     * @param \Illuminate\Support\Collection $suppliers
     * @return array
     */
    protected function prepareItemData($data, $subshopId, $categoryId, $suppliers)
    {
        // Trim all values
        $data = array_map('trim', $data);
        
        // CSV headers per downloadSample():
        // 0 name, 1 description, 2 supplier_name, 3 sku, 4 barcode, 5 price,
        // 6 cost_price, 7 quantity, 8 min_quantity, 9 max_quantity, 10 unit, 11 is_active

        // Resolve supplier by name (case-insensitive)
        $supplierId = null;
        if (!empty($data[2])) {
            $supplierName = strtolower($data[2]);
            $supplierId = $suppliers[$supplierName] ?? null; // leave null if not found
        }

        // Validations (descriptive, per row)
        if (empty($data[0])) {
            throw new \Exception('Item name is required');
        }
        if (empty($categoryId)) {
            throw new \Exception('Category is required. Please select a category before importing.');
        }
        // Price is required and must be numeric (allow 0)
        $priceRaw = isset($data[5]) ? trim($data[5]) : '';
        if ($priceRaw === '') {
            throw new \Exception('Price is required');
        }
        if (!is_numeric(str_replace(',', '', $priceRaw))) {
            throw new \Exception("Invalid price value '{$data[5]}'");
        }
        // Quantity is required and must be numeric (allow 0)
        $qtyRaw = isset($data[7]) ? trim($data[7]) : '';
        if ($qtyRaw === '') {
            throw new \Exception('Quantity is required');
        }
        if (!is_numeric($qtyRaw)) {
            throw new \Exception("Invalid quantity value '{$data[7]}'");
        }
        if (!empty($data[2]) && $supplierId === null) {
            throw new \Exception("Supplier '{$data[2]}' not found for the selected shop");
        }

        // Build normalized payload using correct indices
        return [
            'subshop_id' => $subshopId,
            'name' => $data[0] ?? null,
            'description' => $data[1] ?? null,
            'category_id' => $categoryId, // from UI selection
            'supplier_id' => $supplierId,
            'sku' => $data[3] ?? null,
            'barcode' => $data[4] ?? null,
            'price' => is_numeric(str_replace(',', '', $data[5] ?? '')) ? (float) str_replace(',', '', $data[5]) : 0,
            'cost_price' => is_numeric(str_replace(',', '', $data[6] ?? '')) ? (float) str_replace(',', '', $data[6]) : 0,
            'quantity' => is_numeric($data[7] ?? '') ? (int) $data[7] : 0,
            'min_quantity' => is_numeric($data[8] ?? '') ? (int) $data[8] : 0,
            'max_quantity' => (!empty($data[9]) && is_numeric($data[9])) ? (int) $data[9] : null,
            'unit' => !empty($data[10]) ? $data[10] : 'piece',
            'is_active' => (!isset($data[11]) || trim((string)$data[11]) === '') ? true : in_array(strtolower(trim((string)$data[11])), ['1', 'true', 'yes', 'y']),
            // Note: batch field removed - we now create ItemBatch records instead
        ];
    }

    /**
     * Save or update an item
     *
     * @param array $itemData
     * @param int $subshopId
     * @param int $categoryId
     * @return void
     * @throws \Exception
     */
    protected function saveItem($itemData, $subshopId)
    {
        // Check if item already exists
        $existingItem = Item::where('name', $itemData['name'])
            ->where('category_id', $itemData['category_id'])
            ->where('subshop_id', $subshopId)
            ->withTrashed()
            ->first();

        // Ensure SKU/barcode uniqueness proactively
        if (!empty($itemData['sku']) && Item::where('sku', $itemData['sku'])->exists()) {
            $itemData['sku'] = Item::generateSKU($subshopId);
        }
        if (!empty($itemData['barcode']) && Item::where('barcode', $itemData['barcode'])->exists()) {
            $itemData['barcode'] = Item::generateBarcode();
        }

        // Retry logic to handle rare race conditions on unique constraints
        $maxAttempts = 5;
        $attempt = 0;
        do {
            try {
                if ($existingItem) {
                    // If item was soft-deleted, restore it first
                    if ($existingItem->trashed()) {
                        $existingItem->restore();
                    }
                    $existingItem->update($itemData);
                    $item = $existingItem;
                } else {
                    $item = Item::create($itemData);
                    if (!$item->exists) {
                        throw new \Exception('Failed to create item');
                    }
                }

                // Create ItemBatch record for the imported quantity
                if ($itemData['quantity'] > 0) {
                    // Generate unique batch number for this import
                    $batchNumber = ItemBatch::generateBatchNumber($item->id);

                    ItemBatch::create([
                        'item_id' => $item->id,
                        'batch_number' => $batchNumber,
                        'quantity' => $itemData['quantity'],
                        'cost_price' => $itemData['cost_price'] ?? 0,
                        'selling_price' => $itemData['price'] ?? 0,
                        'expire_date' => null, // No expiry for bulk imports unless specified
                    ]);

                    // Note: Don't manually update item.quantity - it's computed from batches
                }

                // Success
                return;
            } catch (\Illuminate\Database\QueryException $e) {
                $msg = $e->getMessage();
                if (strpos($msg, 'Duplicate entry') !== false && (strpos($msg, 'items_sku_unique') !== false || strpos($msg, 'sku') !== false)) {
                    // Regenerate SKU and retry
                    $itemData['sku'] = Item::generateSKU($subshopId);
                } elseif (strpos($msg, 'Duplicate entry') !== false && (strpos($msg, 'items_barcode_unique') !== false || strpos($msg, 'barcode') !== false)) {
                    // Regenerate barcode and retry
                    $itemData['barcode'] = Item::generateBarcode();
                } else {
                    throw $e; // unrelated DB error
                }
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw new \Exception('Failed to generate unique identifiers after multiple attempts.');
                }
                // Loop to retry
            }
        } while ($attempt < $maxAttempts);
    }
}
