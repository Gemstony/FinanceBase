<?php

namespace App\Http\Controllers;

use App\Models\CollateralDocuments;
use App\Models\CollateralTypes;
use App\Models\CustomerCollaterals;
use App\Models\Customers;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CustomerCollateralsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');

            if (! $subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.customer_collaterals.index')]);
            }

            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
            $customerCollaterals = CustomerCollaterals::whereIn('subshop_id', $shopSubshopIds)
                ->with(['customer', 'collateralType', 'documents' => function ($query) {
                    $query->with(['uploadedBy', 'verifiedBy']);
                }])
                ->latest()
                ->get();

            // Get data for dropdowns
            $customers = Customers::whereIn('subshop_id', $shopSubshopIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $collateralTypes = CollateralTypes::whereIn('subshop_id', $shopSubshopIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return view('loans.loans_settings.customer_collaterals', compact(
                'subshop',
                'customerCollaterals',
                'customers',
                'collateralTypes'
            ));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load customer collaterals: '.$e->getMessage());
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
            // Normalize empty document_types entries so validation can ignore them
            $docTypes = $request->input('document_types', []);
            if (is_array($docTypes)) {
                $request->merge([
                    'document_types' => array_values(array_filter($docTypes, function ($v) {
                        return $v !== null && $v !== '';
                    })),
                ]);
            }
            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'collateral_type_id' => 'required|exists:collateral_types,id',
                'reference_number' => 'nullable|string|max:255',
                'description' => 'required|string|max:255',
                'location' => 'nullable|string|max:500',
                'estimated_value' => 'required|numeric|min:0',
                'valuation_date' => 'nullable|date',
                'valued_by' => 'nullable|string|max:255',
                'is_insured' => 'boolean',
                'insurance_expiry_date' => 'nullable|date|after_or_equal:today',
                'status' => 'required|in:available,pledged,released,seized,disposed',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id',
                // Collateral image
                'collateral_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                // Document uploads
                'documents' => 'nullable|array|max:5',
                'documents.*' => 'file|max:10240',
                'document_types' => 'nullable|array',
                'document_types.*' => 'nullable|in:title_deed,logbook,photo,valuation_report,insurance,ownership_proof,other',
            ]);

            // Validate customer and collateral type belong to the same shop
            $subshopId = session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            $customer = Customers::whereIn('subshop_id', $shopSubshopIds)
                ->where('id', $validated['customer_id'])
                ->firstOrFail();

            $collateralType = CollateralTypes::whereIn('subshop_id', $shopSubshopIds)
                ->where('id', $validated['collateral_type_id'])
                ->firstOrFail();

            $collateralImagePath = null;
            if ($request->hasFile('collateral_image')) {
                $image = $request->file('collateral_image');
                $filename = time().'_'.bin2hex(random_bytes(8)).'.'.$image->getClientOriginalExtension();
                $collateralImagePath = $image->storeAs('collaterals/images', $filename, 'public');
            }

            $customerCollateral = CustomerCollaterals::create([
                'subshop_id' => $subshopId,
                'customer_id' => $validated['customer_id'],
                'collateral_type_id' => $validated['collateral_type_id'],
                'reference_number' => $validated['reference_number'],
                'description' => $validated['description'],
                'collateral_image' => $collateralImagePath,
                'location' => $validated['location'],
                'estimated_value' => $validated['estimated_value'],
                'valuation_date' => $validated['valuation_date'],
                'valued_by' => $validated['valued_by'],
                'is_insured' => $request->has('is_insured'),
                'insurance_expiry_date' => $validated['insurance_expiry_date'],
                'status' => $validated['status'],
                'is_active' => $request->has('is_active'),
            ]);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                $this->handleDocumentUploads($customerCollateral, $request);
            }

            // Return JSON response for AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Customer collateral created successfully!',
                    'collateral' => $customerCollateral->load(['customer', 'collateralType']),
                ]);
            }

            return redirect()->back()->with('success', 'Customer collateral created successfully!');

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create customer collateral: '.$e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', 'Failed to create customer collateral: '.$e->getMessage())->withInput();
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
            $customerCollateral = CustomerCollaterals::findOrFail($id);

            // Normalize empty document_types entries so validation can ignore them
            $docTypes = $request->input('document_types', []);
            if (is_array($docTypes)) {
                $request->merge([
                    'document_types' => array_values(array_filter($docTypes, function ($v) {
                        return $v !== null && $v !== '';
                    })),
                ]);
            }

            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'collateral_type_id' => 'required|exists:collateral_types,id',
                'reference_number' => 'nullable|string|max:255',
                'description' => 'required|string|max:255',
                'location' => 'nullable|string|max:500',
                'estimated_value' => 'required|numeric|min:0',
                'valuation_date' => 'nullable|date',
                'valued_by' => 'nullable|string|max:255',
                'is_insured' => 'boolean',
                'insurance_expiry_date' => 'nullable|date|after_or_equal:today',
                'status' => 'required|in:available,pledged,released,seized,disposed',
                'is_active' => 'boolean',
                'subshop_id' => 'nullable|exists:subshops,id',
                // Collateral image
                'collateral_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                // Document uploads
                'documents' => 'nullable|array|max:5',
                'documents.*' => 'file|max:10240',
                'document_types' => 'nullable|array',
                'document_types.*' => 'nullable|in:title_deed,logbook,photo,valuation_report,insurance,ownership_proof,other',
            ]);

            // Validate customer and collateral type belong to the same shop
            $subshopId = session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            $customer = Customers::whereIn('subshop_id', $shopSubshopIds)
                ->where('id', $validated['customer_id'])
                ->firstOrFail();

            $collateralType = CollateralTypes::whereIn('subshop_id', $shopSubshopIds)
                ->where('id', $validated['collateral_type_id'])
                ->firstOrFail();

            $updateData = [
                'customer_id' => $validated['customer_id'],
                'collateral_type_id' => $validated['collateral_type_id'],
                'reference_number' => $validated['reference_number'],
                'description' => $validated['description'],
                'location' => $validated['location'],
                'estimated_value' => $validated['estimated_value'],
                'valuation_date' => $validated['valuation_date'],
                'valued_by' => $validated['valued_by'],
                'is_insured' => $request->has('is_insured'),
                'insurance_expiry_date' => $validated['insurance_expiry_date'],
                'status' => $validated['status'],
                'is_active' => $request->has('is_active'),
            ];

            if ($request->hasFile('collateral_image')) {
                if ($customerCollateral->collateral_image) {
                    Storage::disk('public')->delete($customerCollateral->collateral_image);
                }
                $image = $request->file('collateral_image');
                $filename = time().'_'.bin2hex(random_bytes(8)).'.'.$image->getClientOriginalExtension();
                $updateData['collateral_image'] = $image->storeAs('collaterals/images', $filename, 'public');
            }

            $customerCollateral->update($updateData);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                $this->handleDocumentUploads($customerCollateral, $request);
            }

            // Return JSON response for AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Customer collateral updated successfully!',
                    'collateral' => $customerCollateral->load(['customer', 'collateralType']),
                ]);
            }

            return redirect()->back()->with('success', 'Customer collateral updated successfully!');

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update customer collateral: '.$e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', 'Failed to update customer collateral: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $customerCollateral = CustomerCollaterals::findOrFail($id);

            // Delete collateral image if exists
            if ($customerCollateral->collateral_image) {
                Storage::disk('public')->delete($customerCollateral->collateral_image);
            }

            // Delete associated documents and files
            foreach ($customerCollateral->documents as $document) {
                if ($document->fileExists()) {
                    Storage::delete($document->file_path);
                }
                $document->delete();
            }

            $customerCollateral->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer collateral and associated documents deleted successfully!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer collateral: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle document uploads for collateral
     */
    private function handleDocumentUploads(CustomerCollaterals $customerCollateral, Request $request)
    {
        $documents = $request->file('documents');
        $documentTypes = $request->input('document_types', []);

        foreach ($documents as $index => $file) {
            if ($file->isValid()) {
                // Generate unique filename
                $filename = time().'_'.$index.'_'.$file->getClientOriginalName();

                // Store file in secure location
                $path = $file->storeAs('collateral_documents', $filename, 'private');

                // Create document record
                CollateralDocuments::create([
                    'customer_collateral_id' => $customerCollateral->id,
                    'document_type' => $documentTypes[$index] ?? 'other',
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'original_filename' => $file->getClientOriginalName(),
                    'uploaded_by' => Auth::id(),
                ]);
            }
        }
    }

    /**
     * Get documents for a specific collateral
     */
    public function getDocuments($customerCollateralId)
    {
        try {
            $customerCollateral = CustomerCollaterals::findOrFail($customerCollateralId);

            // Verify user has access to this collateral
            $subshopId = session('subshop_id');
            if (! $subshopId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subshop selected.',
                ], 403);
            }
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            if (! in_array($customerCollateral->subshop_id, $shopSubshopIds->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this collateral',
                ], 403);
            }

            $documents = $customerCollateral->documents()
                ->with(['uploadedBy', 'verifiedBy'])
                ->get()
                ->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'document_type' => $document->document_type,
                        'document_type_display' => $document->document_type_display,
                        'original_filename' => $document->original_filename,
                        'formatted_file_size' => $document->formatted_file_size,
                        'is_verified' => $document->is_verified,
                        'uploaded_by' => $document->uploadedBy,
                        'verified_by' => $document->verifiedBy,
                        'created_at' => $document->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'success' => true,
                'documents' => $documents,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load documents: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new document for a collateral
     */
    public function storeDocument(Request $request, $customerCollateralId)
    {
        try {
            $customerCollateral = CustomerCollaterals::findOrFail($customerCollateralId);

            // Verify user has access to this collateral
            $subshopId = session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            if (! in_array($customerCollateral->subshop_id, $shopSubshopIds->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this collateral',
                ], 403);
            }

            $validated = $request->validate([
                'document_type' => 'required|in:title_deed,logbook,photo,valuation_report,insurance,ownership_proof,other',
                'document_file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            ]);

            if ($request->hasFile('document_file')) {
                $file = $request->file('document_file');
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs('collateral_documents', $filename, 'private');

                $document = CollateralDocuments::create([
                    'customer_collateral_id' => $customerCollateral->id,
                    'document_type' => $validated['document_type'],
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'original_filename' => $file->getClientOriginalName(),
                    'uploaded_by' => Auth::id(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Document uploaded successfully!',
                    'document' => [
                        'id' => $document->id,
                        'document_type' => $document->document_type,
                        'document_type_display' => $document->getDocumentTypeDisplay(),
                        'original_filename' => $document->original_filename,
                        'formatted_file_size' => $document->getFormattedFileSize(),
                        'is_verified' => $document->is_verified,
                        'created_at' => $document->created_at->format('Y-m-d H:i:s'),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No file provided',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a specific document
     */
    public function downloadDocument($documentId)
    {
        try {
            $document = CollateralDocuments::findOrFail($documentId);

            // Verify user has access to this collateral
            $subshopId = session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            if (! in_array($document->customerCollateral->subshop_id, $shopSubshopIds->toArray())) {
                abort(403, 'Unauthorized access to this document');
            }

            // Determine storage location (private preferred, fallback to local)
            [$disk, $path] = $document->locateStorage();
            if (! $disk || ! $path) {
                abort(404, 'File not found');
            }

            return Storage::disk($disk)->download($path, $document->original_filename);

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to download document: '.$e->getMessage());
        }
    }

    /**
     * Delete a specific document
     */
    public function deleteDocument($documentId)
    {
        try {
            $document = CollateralDocuments::findOrFail($documentId);

            // Verify user has access to this collateral
            $subshopId = session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            if (! in_array($document->customerCollateral->subshop_id, $shopSubshopIds->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this document',
                ], 403);
            }

            // Delete file from storage
            if ($document->fileExists()) {
                Storage::disk('private')->delete($document->file_path);
            }

            // Delete document record
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify a document
     */
    public function verifyDocument($documentId)
    {
        try {
            $document = CollateralDocuments::findOrFail($documentId);

            // Verify user has access to this collateral
            $subshopId = session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            if (! in_array($document->customerCollateral->subshop_id, $shopSubshopIds->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this document',
                ], 403);
            }

            $document->update([
                'is_verified' => true,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document verified successfully!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify document: '.$e->getMessage(),
            ], 500);
        }
    }
}
