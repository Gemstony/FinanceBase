<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Shop;
use App\Models\SubShop;

class EnsureSubshopAccess
{
    /**
     * Ensure the authenticated user can access the current subshop context and that their shop is active.
     *
     * Rules:
     * - Check if user has a shop and if it's active
     * - If shop is not active, redirect to status page
     * - Read subshop_id from query string, otherwise from session
     * - If provided and user can access, persist in session and proceed
     * - If missing or unauthorized, allow access to subshop selection routes, otherwise redirect to selection
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            \Log::info('EnsureSubshopAccess: No authenticated user, redirecting to login');
            return redirect()->route('login');
        }

        // Check if user has a shop and if it's active
        $shop = $user->shop;
        $mainShopStatus = null;
        $assignedSubshop = null;

        if (!$shop) {
            // User doesn't own a shop, but might be a shopkeeper assigned to subshops
            // Check if they have assigned subshops and get the main shop status
            if (method_exists($user, 'subshops') && $user->subshops()->exists()) {
                $assignedSubshop = $user->subshops()->first();
                if ($assignedSubshop && $assignedSubshop->shop) {
                    $mainShop = $assignedSubshop->shop;
                    $mainShopStatus = $mainShop->status;
                    \Log::info('EnsureSubshopAccess: Shopkeeper assigned to shop', [
                        'user_id' => $user->id,
                        'main_shop_id' => $mainShop->id,
                        'main_shop_name' => $mainShop->name,
                        'main_shop_status' => $mainShopStatus
                    ]);
                }
            }

            if (!$mainShopStatus) {
                \Log::info('EnsureSubshopAccess: User has no shop ownership or assignments, redirecting to shop.create', ['user_id' => $user->id]);
                return redirect()->route('shop.create')
                    ->withErrors(['error' => 'Please create a shop first']);
            }
        } else {
            $mainShopStatus = $shop->status;
        }

        // Check shop status - only allow active shops (for both owners and shopkeepers)
        if ($mainShopStatus !== 'active') {
            $statusShopName = $shop ? $shop->name : ($assignedSubshop && $assignedSubshop->shop ? $assignedSubshop->shop->name : 'Assigned Shop');
            \Log::info('EnsureSubshopAccess: Main shop is not active, redirecting to shop.status', [
                'user_id' => $user->id,
                'shop_status' => $mainShopStatus,
                'shop_name' => $statusShopName
            ]);
            return redirect()->route('shop.status')
                ->with('shop_status', $mainShopStatus)
                ->with('shop_name', $statusShopName);
        }

        $subshopId = $request->query('subshop_id');
        if (!$subshopId) {
            $subshopId = (int) $request->session()->get('subshop_id');
        }

        if ($subshopId && is_numeric($subshopId)) {
            $subshopId = (int) $subshopId;
            if (method_exists($user, 'canAccessSubshop') && $user->canAccessSubshop($subshopId)) {
                // Persist active subshop in session
                $request->session()->put('subshop_id', $subshopId);
                return $next($request);
            }
        }

        // Allow access to unified chooser routes or users management
        $routeName = optional($request->route())->getName();
        if (
            $routeName === 'subshops.choose' ||
            $routeName === 'subshops.choose.store' ||
            $routeName === 'subshops.create-modal' ||
            ($routeName && str_starts_with($routeName, 'users.'))
        ) {
            return $next($request);
        }

        // Try auto-select when there is exactly one accessible subshop
        $accessible = collect();
        // Owner: all subshops of owned shops
        $ownsShop = method_exists($user, 'hasShop') ? $user->hasShop() : false;
        if ($ownsShop) {
            $shopIds = Shop::where('user_id', $user->id)->pluck('id');
            $accessible = SubShop::whereIn('shop_id', $shopIds)->active()->pluck('id');
        } elseif (method_exists($user, 'subshops')) {
            // Shopkeeper: assigned subshops
            $accessible = $user->subshops()->wherePivot('is_active', 1)->active()->pluck('sub_shops.id');
        }

        if ($accessible->count() === 1) {
            $request->session()->put('subshop_id', (int) $accessible->first());
            return $next($request);
        }

        // Redirect to unified chooser with intended URL
        return redirect()->route('subshops.choose', ['intended' => $request->fullUrl()])
            ->with('info', 'Please choose a branch to continue.');
    }
}
