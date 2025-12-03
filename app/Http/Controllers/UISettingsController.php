<?php

namespace App\Http\Controllers;

use App\Models\UiSetting;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class UISettingsController extends Controller
{
    public function index()
    {
        $shopId = $this->resolveCurrentShopId(request());
        $settings = $shopId ? UiSetting::where('shop_id', $shopId)->first() : null;
        if (!$settings) {
            // Fallback to global default settings (shop_id null)
            $settings = UiSetting::whereNull('shop_id')->first();
        }
        return view('settings.ui-settings', [
            'settings' => $settings,
        ]);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'sidebar_bg' => 'nullable|string|max:255',
            'navbar_bg' => 'nullable|string|max:255',
        ]);

        $shopId = $this->resolveCurrentShopId($request);
        if (!$shopId) {
            return response()->json([
                'success' => false,
                'message' => 'No active shop context found. Please select a shop first.'
            ], 422);
        }

        $settings = UiSetting::updateOrCreate(
            ['shop_id' => $shopId],
            $validated
        );

        // Clear view/config cache so updates are immediately reflected if needed
        try {
            Artisan::call('view:clear');
        } catch (\Throwable $e) {}

        // Bust cached theme so middleware injects new values immediately
        try {
            Cache::forget('ui_settings_shop_' . $shopId);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    private function resolveCurrentShopId(Request $request): ?int
    {
        $user = $request->user();
        if (!$user) return null;

        $subshopId = (int) $request->session()->get('subshop_id');
        if ($subshopId) {
            $sub = SubShop::find($subshopId);
            if ($sub && $sub->shop_id) {
                return (int) $sub->shop_id;
            }
        }
        if (method_exists($user, 'shop') && $user->shop) {
            return (int) $user->shop->id;
        }
        return null;
    }
}
