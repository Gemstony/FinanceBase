<?php

namespace App\Http\Controllers;

use App\Models\LoanProductTypes;
use App\Models\SubShop;
use App\Models\AccountClass;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericArrayExport;

class LoanProductTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('loans.loan_product_types.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
        $loanProductTypes = LoanProductTypes::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

        return view('loans.loans_settings.loan_product_types', compact('subshop', 'loanProductTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:loan_product_types,code,NULL,id,subshop_id,' . session('subshop_id'),
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            LoanProductTypes::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Loan product type created successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create loan product type: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $loanProductType = LoanProductTypes::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:loan_product_types,code,' . $id . ',id,subshop_id,' . session('subshop_id'),
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            
            $loanProductType->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Loan product type updated successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update loan product type: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $loanProductType = LoanProductTypes::findOrFail($id);
            $loanProductType->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Loan product type deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete loan product type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export loan product types to Excel or PDF
     */
    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('loans.loan_product_types.index')])
                ->with('error', 'Please select a shop first');
        }

        $subshop = SubShop::with('shop')->findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
        $loanProductTypes = LoanProductTypes::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

        if ($format === 'excel') {
            $exportRows = $loanProductTypes->map(function($type){
                return [
                    'Code' => $type->code,
                    'Name' => $type->name,
                    'Description' => $type->description ?? '',
                    'Is Active' => $type->is_active ? 'Yes' : 'No',
                ];
            });
            
            return Excel::download(
                new GenericArrayExport($exportRows->toArray(), 'Loan Product Types'),
                'loan_product_types_' . now()->format('Y-m-d_H-i-s') . '.xlsx'
            );
        }

        if ($format === 'pdf') {
            $summary = [
                'total' => $loanProductTypes->count(),
                'active' => $loanProductTypes->where('is_active', true)->count(),
                'inactive' => $loanProductTypes->where('is_active', false)->count(),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('loans.loans_settings.pdf.loan_product_types', [
                'loanProductTypes' => $loanProductTypes,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->guard()->user())->name ?? 'System',
            ]);

            // Ensure directory exists
            $directory = storage_path('app/public/loans/loans_settings/pdf');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = 'loan_product_types_' . now()->format('Y-m-d_H-i-s') . '.pdf';
            $pdf->save($directory . '/' . $filename);

            return response()->download($directory . '/' . $filename)->deleteFileAfterSend(true);
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    /**
     * Download a sample Excel template for loan product types import
     */
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename=loan_product_types_import_template.xlsx',
        ];

        $sampleData = [
            ['Code', 'Name', 'Description', 'Is Active'],
            ['1000', 'Business Loan', 'Loan for business purposes', 'Yes'],
            ['2000', 'Agriculture Loan', 'Loan for agricultural activities', 'Yes'],
            ['3000', 'Education Loan', 'Loan for education expenses', 'No'],
        ];

        return Excel::download(
            new GenericArrayExport($sampleData, 'Loan Product Types Template'),
            'loan_product_types_import_template.xlsx'
        );
    }

    /**
     * Import loan product types from Excel file
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
                    'message' => 'Import failed: No shop selected. Please select a shop first before importing loan product types.'
                ], 400);
            }
            return redirect()->route('subshops.choose', ['intended' => route('loans.loan_product_types.index')]);
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
                    $existing = LoanProductTypes::where('subshop_id', $subshopId)
                        ->where('code', strtoupper($rowData['code']))
                        ->first();
                    
                    if ($existing) {
                        $errors[] = "Row {$rowIndex}: A loan product type with code '{$rowData['code']}' already exists in this shop. Please use a different code.";
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

                    LoanProductTypes::create($data);
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

            $message = "Successfully imported {$imported} loan product type(s). All data has been saved to the database.";
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'imported' => $imported,
                ]);
            }

            return redirect()->route('loans.loan_product_types.index')->with('success', $message);

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
