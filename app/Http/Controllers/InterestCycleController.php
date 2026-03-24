<?php

namespace App\Http\Controllers;

use App\Models\InterestCycles;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericArrayExport;

class InterestCycleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');
            
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.interest_cycles.index')]);
            }
            
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $interestCycles = InterestCycles::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

            return view('loans.loans_settings.interest_cycles', compact('subshop', 'interestCycles'));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load interest cycles: ' . $e->getMessage());
        }
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
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:10|unique:interest_cycles,code,NULL,id,subshop_id,' . session('subshop_id'),
                'interval_days' => 'nullable|integer|min:1',
                'is_installment_based' => 'boolean',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $interestCycle = InterestCycles::create([
                'subshop_id' => session('subshop_id'),
                'name' => $validated['name'],
                'code' => $validated['code'],
                'interval_days' => $validated['interval_days'],
                'is_installment_based' => $request->has('is_installment_based'),
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Interest cycle created successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create interest cycle: ' . $e->getMessage())->withInput();
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
    public function update(Request $request, string $id)
    {
        try {
            $interestCycle = InterestCycles::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('interest_cycles', 'code')->ignore($interestCycle->id)
                ],
                'interval_days' => 'nullable|integer|min:1',
                'is_installment_based' => 'boolean',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id'
            ]);

            $interestCycle->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'interval_days' => $validated['interval_days'],
                'is_installment_based' => $request->has('is_installment_based'),
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->back()->with('success', 'Interest cycle updated successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update interest cycle: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $interestCycle = InterestCycles::findOrFail($id);
            $interestCycle->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Interest cycle deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete interest cycle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export interest cycles to Excel or PDF
     */
    public function export(Request $request, $format)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('loans.interest_cycles.index')])
                ->with('error', 'Please select a shop first');
        }

        $subshop = SubShop::with('shop')->findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
        $interestCycles = InterestCycles::whereIn('subshop_id', $shopSubshopIds)->latest()->get();

        if ($format === 'excel') {
            $exportRows = $interestCycles->map(function($cycle){
                return [
                    'Code' => $cycle->code,
                    'Name' => $cycle->name,
                    'Interval Days' => $cycle->interval_days ?? '',
                    'Is Installment Based' => $cycle->is_installment_based ? 'Yes' : 'No',
                    'Is Active' => $cycle->is_active ? 'Yes' : 'No',
                ];
            });
            
            return Excel::download(
                new GenericArrayExport($exportRows->toArray(), 'Interest Cycles'),
                'interest_cycles_' . now()->format('Y-m-d_H-i-s') . '.xlsx'
            );
        }

        if ($format === 'pdf') {
            $summary = [
                'total' => $interestCycles->count(),
                'active' => $interestCycles->where('is_active', true)->count(),
                'inactive' => $interestCycles->where('is_active', false)->count(),
                'installment_based' => $interestCycles->where('is_installment_based', true)->count(),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('loans.loans_settings.pdf.interest_cycles', [
                'interestCycles' => $interestCycles,
                'subshop' => $subshop,
                'summary' => $summary,
                'generatedBy' => optional(auth()->guard()->user())->name ?? 'System',
            ]);

            // Ensure directory exists
            $directory = storage_path('app/public/loans/loans_settings/pdf');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = 'interest_cycles_' . now()->format('Y-m-d_H-i-s') . '.pdf';
            $pdf->save($directory . '/' . $filename);

            return response()->download($directory . '/' . $filename)->deleteFileAfterSend(true);
        }

        return redirect()->back()->with('error', 'Unsupported export format');
    }

    /**
     * Download a sample Excel template for interest cycles import
     */
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename=interest_cycles_import_template.xlsx',
        ];

        $sampleData = [
            ['Code', 'Name', 'Interval Days', 'Is Installment Based', 'Is Active'],
            ['DLY', 'Daily', '1', 'No', 'Yes'],
            ['MTH', 'Monthly', '30', 'No', 'Yes'],
            ['INST', 'Per Installment', '', 'Yes', 'Yes'],
            ['WKY', 'Weekly', '7', 'No', 'No'],
        ];

        return Excel::download(
            new GenericArrayExport($sampleData, 'Interest Cycles Template'),
            'interest_cycles_import_template.xlsx'
        );
    }

    /**
     * Import interest cycles from Excel file
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
                    'message' => 'Import failed: No shop selected. Please select a shop first before importing interest cycles.'
                ], 400);
            }
            return redirect()->route('subshops.choose', ['intended' => route('loans.interest_cycles.index')]);
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
                return strtolower(trim(str_replace(' ', '_', $h)));
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
                    $existing = InterestCycles::where('subshop_id', $subshopId)
                        ->where('code', strtoupper($rowData['code']))
                        ->first();
                    
                    if ($existing) {
                        $errors[] = "Row {$rowIndex}: An interest cycle with code '{$rowData['code']}' already exists in this shop. Please use a different code.";
                        continue;
                    }

                    // Prepare data for insertion
                    $data = [
                        'subshop_id' => $subshopId,
                        'code' => strtoupper($rowData['code']),
                        'name' => $rowData['name'],
                        'interval_days' => !empty($rowData['interval_days']) ? (int)$rowData['interval_days'] : null,
                        'is_installment_based' => isset($rowData['is_installment_based']) ?
                            (in_array(strtolower($rowData['is_installment_based']), ['yes', '1', 'true']) ? true : false) : false,
                        'is_active' => isset($rowData['is_active']) ?
                            (in_array(strtolower($rowData['is_active']), ['yes', '1', 'true']) ? true : false) : true,
                    ];

                    InterestCycles::create($data);
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

            $message = "Successfully imported {$imported} interest cycle(s). All data has been saved to the database.";
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'imported' => $imported,
                ]);
            }

            return redirect()->route('loans.interest_cycles.index')->with('success', $message);

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
