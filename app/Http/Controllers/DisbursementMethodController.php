<?php

namespace App\Http\Controllers;

use App\Models\DisbursementMethods;
use App\Models\SubShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DisbursementMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $subshopId = session('subshop_id');

            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.disbursement_methods.index')]);
            }

            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            $methods = DisbursementMethods::query()
                ->whereIn('subshop_id', $shopSubshopIds)
                ->orderBy('name')
                ->get();

            return view('loans.loans_settings.disbursement_methods', compact('subshop', 'methods'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load disbursement methods: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $subshopId = session('subshop_id');

            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.disbursement_methods.index')]);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:disbursement_methods,code',
                'description' => 'nullable|string',
                'requires_reference' => 'boolean',
                'requires_account_details' => 'boolean',
                'is_active' => 'boolean',
                'is_system_method' => 'boolean',
            ]);

            DisbursementMethods::create([
                'subshop_id' => (int) $subshopId,
                'name' => $validated['name'],
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'requires_reference' => $request->has('requires_reference'),
                'requires_account_details' => $request->has('requires_account_details'),
                'is_active' => $request->has('is_active'),
                'is_system_method' => $request->has('is_system_method'),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return redirect()->back()->with('success', 'Disbursement method created successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create disbursement method: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $method = DisbursementMethods::findOrFail($id);

            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return redirect()->route('subshops.choose', ['intended' => route('loans.disbursement_methods.index')]);
            }

            $subshop = SubShop::findOrFail((int) $subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id')->map(fn ($v) => (int) $v)->all();
            if (!in_array((int) $method->subshop_id, $shopSubshopIds, true)) {
                abort(403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('disbursement_methods', 'code')->ignore($method->id),
                ],
                'description' => 'nullable|string',
                'requires_reference' => 'boolean',
                'requires_account_details' => 'boolean',
                'is_active' => 'boolean',
                'is_system_method' => 'boolean',
            ]);

            $method->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'requires_reference' => $request->has('requires_reference'),
                'requires_account_details' => $request->has('requires_account_details'),
                'is_active' => $request->has('is_active'),
                'is_system_method' => $request->has('is_system_method'),
                'updated_by' => Auth::id(),
            ]);

            return redirect()->back()->with('success', 'Disbursement method updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update disbursement method: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $method = DisbursementMethods::findOrFail($id);

            $subshopId = session('subshop_id');
            if (!$subshopId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a branch first.',
                ], 403);
            }

            $subshop = SubShop::findOrFail((int) $subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id')->map(fn ($v) => (int) $v)->all();
            if (!in_array((int) $method->subshop_id, $shopSubshopIds, true)) {
                abort(403);
            }

            if ($method->is_system_method) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is a system disbursement method and cannot be deleted.',
                ], 422);
            }

            $method->delete();

            return response()->json([
                'success' => true,
                'message' => 'Disbursement method deleted successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete disbursement method: ' . $e->getMessage(),
            ], 500);
        }
    }
}
