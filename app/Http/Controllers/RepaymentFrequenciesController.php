<?php

namespace App\Http\Controllers;

use App\Models\RepaymentFrequencies;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericArrayExport;

class RepaymentFrequenciesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.repayment_frequencies.index')]);
            }
            
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $repaymentFrequencies = RepaymentFrequencies::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            return view('loans.loans_settings.repayment_frequencies', compact('subshop', 'repaymentFrequencies'));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load repayment frequencies: ' . $e->getMessage());
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:repayment_frequencies,code,NULL,id,subshop_id,' . session('subshop_id'),
                'interval_days' => 'required|integer|min:1',
                'max_installments' => 'nullable|integer|min:1|max:255',
                'min_installments' => 'nullable|integer|min:1|max:255',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $repaymentFrequency = RepaymentFrequencies::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => $validated['code'],
                'interval_days' => $validated['interval_days'],
                'is_month_based' => $request->has('is_month_based'),
                'max_installments' => $validated['max_installments'],
                'min_installments' => $validated['min_installments'],
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Repayment frequency created successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create repayment frequency: ' . $e->getMessage())->withInput();
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $repaymentFrequency = RepaymentFrequencies::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('repayment_frequencies', 'code')->ignore($repaymentFrequency->id)
                ],
                'interval_days' => 'required|integer|min:1',
                'max_installments' => 'nullable|integer|min:1|max:255',
                'min_installments' => 'nullable|integer|min:1|max:255',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $repaymentFrequency->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'interval_days' => $validated['interval_days'],
                'is_month_based' => $request->has('is_month_based'),
                'max_installments' => $validated['max_installments'],
                'min_installments' => $validated['min_installments'],
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Repayment frequency updated successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update repayment frequency: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */


    public function destroy($id)
    {
        try {
            $repaymentFrequency = RepaymentFrequencies::findOrFail($id);
            $repaymentFrequency->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Repayment frequency deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete repayment frequency: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export repayment frequencies to Excel or PDF
     */
    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('loans.repayment_frequencies.index')])
                ->with('error', 'Please select a shop first');
        }

        $subshop = SubShop::with('shop')->findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
        $repaymentFrequencies = RepaymentFrequencies::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

        if ($format === 'excel') {
            $exportRows = $repaymentFrequencies->map(function($frequency){
                return [
                    'Code' => $frequency->code,
                    'Name' => $frequency->name,
                    'Interval Days' => $frequency->interval_days,
                    'Is Month Based' => $frequency->is_month_based ? 'Yes' : 'No',
                    'Min Installments' => $frequency->min_installments ?? '',
                    'Max Installments' => $frequency->max_installments ?? '',
                    'Is Active' => $frequency->is_active ? 'Yes' : 'No',
                ];
            });
            
            return Excel::download(
                new GenericArrayExport($exportRows->toArray(), 'Repayment Frequencies'),
                'repayment_frequencies_' . now()->format('Y-m-d_H-i-s') . '.xlsx'
            );
        }

        if ($format === 'pdf') {
            $summary = [
                'total' => $repaymentFrequencies->count(),
                'active' => $repaymentFrequencies->where('is_active', true)->count(),
                'inactive' => $repaymentFrequencies->where('is_active', false)->count(),
                'month_based' => $repaymentFrequencies->where('is_month_based', true)->count(),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('loans.loans_settings.pdf.repayment_frequencies', [
                'repaymentFrequencies' => $repaymentFrequencies,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->guard()->user())->name ?? 'System',
            ]);

            // Ensure directory exists
            $directory = storage_path('app/public/loans/loans_settings/pdf');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = 'repayment_frequencies_' . now()->format('Y-m-d_H-i-s') . '.pdf';
            $pdf->save($directory . '/' . $filename);

            return response()->download($directory . '/' . $filename)->deleteFileAfterSend(true);
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    /**
     * Download a sample Excel template for repayment frequencies import
     */
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename=repayment_frequencies_import_template.xlsx',
        ];

        $sampleData = [
            ['Code', 'Name', 'Interval Days', 'Is Month Based', 'Min Installments', 'Max Installments', 'Is Active'],
            ['DLY', 'Daily', '1', 'No', '1', '365', 'Yes'],
            ['WKY', 'Weekly', '7', 'No', '1', '52', 'Yes'],
            ['MTH', 'Monthly', '30', 'Yes', '1', '12', 'Yes'],
            ['QTR', 'Quarterly', '90', 'Yes', '1', '4', 'Yes'],
        ];

        return Excel::download(
            new GenericArrayExport($sampleData, 'Repayment Frequencies Template'),
            'repayment_frequencies_import_template.xlsx'
        );
    }

    /**
     * Import repayment frequencies from Excel file
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
                    'message' => 'Import failed: No shop selected. Please select a shop first before importing repayment frequencies.'
                ], 400);
            }
            return redirect()->route('subshops.choose', ['intended' => route('loans.repayment_frequencies.index')]);
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
            $requiredHeaders = ['code', 'name', 'interval days'];
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
                if (empty($rowData['interval days'])) {
                    $rowErrors[] = "interval days is required and cannot be empty";
                } elseif (!is_numeric($rowData['interval days']) || (int)$rowData['interval days'] < 1) {
                    $rowErrors[] = "interval days must be a positive integer";
                }

                if (!empty($rowErrors)) {
                    $errors[] = "Row {$rowIndex}: " . implode('; ', $rowErrors);
                    continue;
                }

                try {
                    // Check for duplicate code in same subshop
                    $existing = RepaymentFrequencies::where('subshop_id', $subshopId)
                        ->where('code', strtoupper($rowData['code']))
                        ->first();
                    
                    if ($existing) {
                        $errors[] = "Row {$rowIndex}: A repayment frequency with code '{$rowData['code']}' already exists in this shop. Please use a different code.";
                        continue;
                    }

                    // Prepare data for insertion
                    $data = [
                        'subshop_id' => $subshopId,
                        'code' => strtoupper($rowData['code']),
                        'name' => $rowData['name'],
                        'interval_days' => (int)$rowData['interval days'],
                        'is_month_based' => isset($rowData['is month based']) ?
                            (in_array(strtolower($rowData['is month based']), ['yes', '1', 'true']) ? true : false) : false,
                        'min_installments' => !empty($rowData['min installments']) ? (int)$rowData['min installments'] : null,
                        'max_installments' => !empty($rowData['max installments']) ? (int)$rowData['max installments'] : null,
                        'is_active' => isset($rowData['is active']) ?
                            (in_array(strtolower($rowData['is active']), ['yes', '1', 'true']) ? true : false) : true,
                    ];

                    RepaymentFrequencies::create($data);
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

            $message = "Successfully imported {$imported} repayment frequency(ies). All data has been saved to the database.";
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'imported' => $imported,
                ]);
            }

            return redirect()->route('loans.repayment_frequencies.index')->with('success', $message);

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
