<?php

namespace App\Http\Controllers;

use App\Models\SubShop;
use App\Models\AccountClass;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericArrayExport;

class AccountClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('accounting.account_class.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        // Collect all subshops under the same parent shop
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

        // Fetch account classes across all subshops for this shop
        $accountClasses = AccountClass::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

        return view('accounting.accounting_settings.account_class', compact('subshop', 'accountClasses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:account_classes,code,NULL,id,subshop_id,' . session('subshop_id'),
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            AccountClass::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Account class created successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create account class: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $accountClass = AccountClass::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:account_classes,code,NULL,id,subshop_id,' . session('subshop_id'),
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            $accountClass->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Account class updated successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update account class: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $accountClass = AccountClass::findOrFail($id);
            $accountClass->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Account class deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account class: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export account classes to Excel or PDF
     */
    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('accounting.account_class.index')])
                ->with('error', 'Please select a shop first');
        }

        $subshop = SubShop::with('shop')->findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
        $accountClasses = AccountClass::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

        if ($format === 'excel') {
            $exportRows = $accountClasses->map(function($class){
                return [
                    'Code' => $class->code,
                    'Name' => $class->name,
                    'Description' => $class->description ?? '',
                    'Is Active' => $class->is_active ? 'Yes' : 'No',
                ];
            });
            
            return Excel::download(
                new GenericArrayExport($exportRows->toArray(), 'Account Classes'),
                'account_classes_' . now()->format('Y-m-d_H-i-s') . '.xlsx'
            );
        }

        if ($format === 'pdf') {
            $summary = [
                'total' => $accountClasses->count(),
                'active' => $accountClasses->where('is_active', true)->count(),
                'inactive' => $accountClasses->where('is_active', false)->count(),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting.accounting_settings.pdf.account_class', [
                'accountClasses' => $accountClasses,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->guard()->user())->name ?? 'System',
            ]);

            // Ensure directory exists
            $directory = storage_path('app/public/accounting/accounting_settings/pdf');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = 'account_classes_' . now()->format('Y-m-d_H-i-s') . '.pdf';
            $pdf->save($directory . '/' . $filename);

            return response()->download($directory . '/' . $filename)->deleteFileAfterSend(true);
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    /**
     * Download a sample Excel template for account classes import
     */
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename=account_classes_import_template.xlsx',
        ];

        $sampleData = [
            ['Code', 'Name', 'Description', 'Is Active'],
            ['1', 'Assets', 'All asset accounts', 'Yes'],
            ['2', 'Liabilities', 'All liability accounts', 'Yes'],
            ['3', 'Equity', 'All equity accounts', 'Yes'],
            ['4', 'Revenue', 'All revenue accounts', 'Yes'],
            ['5', 'Expenses', 'All expense accounts', 'Yes'],
        ];

        return Excel::download(
            new GenericArrayExport($sampleData, 'Account Classes Template'),
            'account_classes_import_template.xlsx'
        );
    }

    /**
     * Import account classes from Excel file
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Import failed: Please upload a valid Excel file. Maximum file size is 2MB.'
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        $subshopId = session('subshop_id');
        if (!$subshopId) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import failed: No shop selected. Please select a shop first before importing account classes.'
                ], 400);
            }
            return redirect()->route('subshops.choose', ['intended' => route('accounting.account_class.index')]);
        }

        $file = $request->file('excel_file');
        $imported = 0;
        $errors = [];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (count($rows) < 2) {
                $errorMsg = 'Import failed: No data rows found in the Excel file. Please ensure your Excel file contains a header row and at least one data row.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMsg
                    ], 400);
                }
                return redirect()->back()->with('error', $errorMsg);
            }

            // Get headers from first row
            $headers = array_map(function($h) {
                return strtolower(trim($h));
            }, $rows[0]);

            // Validate headers
            $requiredHeaders = ['code', 'name'];
            $missingHeaders = array_diff($requiredHeaders, $headers);
            
            if (!empty($missingHeaders)) {
                $errorMsg = 'Import failed: Missing required columns in Excel file: ' . implode(', ', $missingHeaders) . '. Please download the template and ensure all required columns are present.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMsg
                    ], 400);
                }
                return redirect()->back()->with('error', $errorMsg);
            }

            // Process rows with transaction
            DB::beginTransaction();

            for ($i = 1; $i < count($rows); $i++) {
                $rowIndex = $i + 1;
                $row = $rows[$i];

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Map row data to headers
                $rowData = [];
                foreach ($headers as $j => $header) {
                    $rowData[$header] = isset($row[$j]) ? trim($row[$j]) : null;
                }

                // Validate required fields
                $rowErrors = [];
                
                if (empty($rowData['code'])) {
                    $rowErrors[] = "code is required and cannot be empty";
                }
                if (empty($rowData['name'])) {
                    $rowErrors[] = "name is required and cannot be empty";
                }

                if (!empty($rowErrors)) {
                    $errors[] = "Row {$rowIndex}: " . implode('; ', $rowErrors);
                    continue;
                }

                try {
                    // Check for duplicate code in same subshop
                    $existing = AccountClass::where('subshop_id', $subshopId)
                        ->where('code', strtoupper($rowData['code']))
                        ->first();
                    
                    if ($existing) {
                        $errors[] = "Row {$rowIndex}: An account class with code '{$rowData['code']}' already exists in this shop. Please use a different code.";
                        continue;
                    }

                    // Prepare data for insertion
                    $data = [
                        'subshop_id' => $subshopId,
                        'code' => strtoupper($rowData['code']),
                        'name' => $rowData['name'],
                        'description' => !empty($rowData['description']) ? $rowData['description'] : null,
                        'is_active' => isset($rowData['is_active']) ?
                            (in_array(strtolower($rowData['is_active']), ['yes', '1', 'true']) ? true : false) : true,
                    ];

                    AccountClass::create($data);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowIndex}: Database error - " . $e->getMessage();
                }
            }

            // If there are any errors, rollback the transaction
            if (!empty($errors)) {
                DB::rollBack();
                
                $errorCount = count($errors);
                $errorMessage = "Import failed due to {$errorCount} error(s). All changes have been rolled back. Please fix the following issues and try again:";
                
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'errors' => $errors,
                        'imported' => 0,
                    ], 400);
                }
                
                return redirect()->back()
                    ->with('error', $errorMessage)
                    ->with('import_errors', $errors);
            }

            // Commit the transaction if all rows are successful
            DB::commit();

            $message = "Successfully imported {$imported} account class(es). All data has been saved to the database.";
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'imported' => $imported,
                ]);
            }

            return redirect()->route('accounting.account_class.index')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $errorMessage = 'Import failed: ' . $e->getMessage();
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 500);
            }
            
            return redirect()->back()->with('error', $errorMessage);
        }
    }
}
