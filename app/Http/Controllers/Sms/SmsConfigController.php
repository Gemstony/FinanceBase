<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsConfig;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SmsConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        
        $query = SmsConfig::query()->with('shop');
        
        // Filter by current shop only
        $query->where('shop_id', $shopId);
        
        $configs = $query->latest()->paginate(25)->withQueryString();
        
        return view('sms.configs.index', compact('configs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        return view('sms.configs.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $validated = $request->validate([
            'provider' => 'required|string|in:beem,twilio',
            'api_url' => 'required|url',
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
            'sender_id' => 'required|string|max:11',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'rate_limit_per_minute' => 'nullable|integer|min:1',
        ]);

        \DB::beginTransaction();

        try {
            if ($request->boolean('is_default')) {
                SmsConfig::where('shop_id', $shopId)
                    ->update(['is_default' => false]);
            }

            SmsConfig::create([
                'shop_id' => $shopId,
                'provider' => $request->provider,
                'api_url' => $request->api_url,
                'api_key' => $request->api_key,
                'secret_key' => $request->secret_key,
                'sender_id' => $request->sender_id,
                'is_active' => $request->boolean('is_active'),
                'is_default' => $request->boolean('is_default'),
                'rate_limit_per_minute' => $request->input('rate_limit_per_minute', 60),
            ]);

            \DB::commit();

            return redirect()->route('sms.configs.index')
                ->with('success', 'SMS configuration created successfully.');

        } catch (\Exception $e) {
            \DB::rollBack();

            \Log::error('Error creating SMS config: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->except(['api_key', 'secret_key'])
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create SMS configuration.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $smsConfig = SmsConfig::with('shop')
            ->where('shop_id', $shopId)
            ->findOrFail($id);
        
        return view('sms.configs.show', compact('smsConfig'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $smsConfig = SmsConfig::with('shop')
            ->where('shop_id', $shopId)
            ->findOrFail($id);

        return view('sms.configs.edit', compact('smsConfig'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        $smsConfig = SmsConfig::where('shop_id', $shopId)->findOrFail($id);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:beem,twilio'],
            'api_url' => ['required', 'url'],
            'api_key' => ['nullable', 'string'],
            'secret_key' => ['nullable', 'string'],
            'sender_id' => ['required', 'string', 'max:11'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1'],
        ]);

        // Normalize checkbox values (VERY IMPORTANT)
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_default'] = $request->boolean('is_default');

        DB::transaction(function () use ($request, $smsConfig, &$validated, $shopId) {

            // Ensure only one default per shop
            if ($validated['is_default']) {
                SmsConfig::where('shop_id', $shopId)
                    ->where('id', '!=', $smsConfig->id)
                    ->update(['is_default' => false]);
            }

            // Remove empty API fields (keep old values)
            if (!$request->filled('api_key')) {
                unset($validated['api_key']);
            }

            if (!$request->filled('secret_key')) {
                unset($validated['secret_key']);
            }

            $smsConfig->update($validated);
        });

        return redirect()
            ->route('sms.configs.index')
            ->with('success', 'SMS configuration updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        \DB::beginTransaction();

        try {
            $smsConfig = SmsConfig::where('shop_id', $shopId)->findOrFail($id);
            
            // Prevent deleting default config unless handled
            if ($smsConfig->is_default) {
                // Try to assign another default config
                $newDefault = SmsConfig::where('shop_id', $shopId)
                    ->where('id', '!=', $smsConfig->id)
                    ->first();

                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                } else {
                    return redirect()->back()
                        ->with('error', 'Cannot delete the only default SMS configuration.');
                }
            }

            $smsConfig->delete();

            \DB::commit();

            return redirect()->route('sms.configs.index')
                ->with('success', 'SMS configuration deleted successfully.');

        } catch (\Exception $e) {
            \DB::rollBack();

            \Log::error('Error deleting SMS config: ' . $e->getMessage(), [
                'sms_config_id' => $smsConfig->id
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete SMS configuration.');
        }
    }
}
