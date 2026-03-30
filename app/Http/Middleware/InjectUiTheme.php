<?php

namespace App\Http\Middleware;

use App\Models\UiSetting;
use App\Models\SubShop;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectUiTheme
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Only process standard HTML responses
        $contentType = $response->headers->get('Content-Type');
        if (!$contentType || stripos($contentType, 'text/html') === false) {
            return $response;
        }

        // Resolve current shop context (shop_id) from subshop session or owner's shop
        $user = $request->user();
        $shopId = null;
        if ($user) {
            $subshopId = (int) $request->session()->get('subshop_id');
            if ($subshopId) {
                $sub = SubShop::find($subshopId);
                if ($sub && $sub->shop_id) {
                    $shopId = (int) $sub->shop_id;
                }
            }
            // If no shop_id yet, check if user owns a shop
            if (!$shopId && method_exists($user, 'shop') && $user->shop) {
                $shopId = (int) $user->shop->id;
            }
            // If still no shop_id, user might be an assigned user (shopkeeper)
            // Try to get shop_id from their assigned subshops
            if (!$shopId && method_exists($user, 'subshops')) {
                $assignedSubshop = $user->subshops()->wherePivot('is_active', 1)->first();
                if ($assignedSubshop && $assignedSubshop->shop_id) {
                    $shopId = (int) $assignedSubshop->shop_id;
                }
            }
        }

        // Retrieve per-shop settings (cached to reduce DB hits)
        $settings = null;
        if ($shopId) {
            $settings = cache()->remember('ui_settings_shop_' . $shopId, 120, function () use ($shopId) {
                return UiSetting::query()->where('shop_id', $shopId)->first(['sidebar_bg','navbar_bg']);
            });
        }
        // Fallback to global default settings (shop_id null)
        if (!$settings) {
            $settings = cache()->remember('ui_settings_global_default', 120, function () {
                return UiSetting::query()->whereNull('shop_id')->first(['sidebar_bg','navbar_bg']);
            });
        }

        // Always inject defaults first
        $defaultSidebar = 'linear-gradient(180deg, #004e92, #000428)';
        $defaultNavbar  = 'linear-gradient(135deg, #FFD700, #FFA500, #FF6347)';
        $cssVars = ':root{--sidebar-bg:' . e($defaultSidebar) . ';--navbar-bg:' . e($defaultNavbar) . ';}';

        // Then, if saved settings exist (per-shop or global), override them
        if ($settings) {
            $overrides = '';
            if (!empty($settings->sidebar_bg)) {
                $overrides .= '--sidebar-bg:' . e($settings->sidebar_bg) . ';';
            }
            if (!empty($settings->navbar_bg)) {
                $overrides .= '--navbar-bg:' . e($settings->navbar_bg) . ';';
            }
            if ($overrides !== '') {
                $cssVars .= ':root{' . $overrides . '}';
            }
        }

        $style = "<style id=\"ui-theme-vars-global\">{$cssVars}</style>";

        // Inject a small script to expose a per-shop key and apply initial mode.
        // Actual click handling and icon/tooltip updates are managed by public/js/theme-toggle.js
        $shopKeySuffix = $shopId ? ('-shop-' . (int)$shopId) : '';
        $script = \sprintf(
            '<script id="per-shop-darkmode">(function(){var shopKey=%s;window.DUKABASE_SHOP_KEY=shopKey;var storageKey="dukabase_theme"+shopKey;var body=document.body;if(!body)return;try{var mode=localStorage.getItem(storageKey)||"light";if(mode==="dark"){body.classList.add("dark-mode");}else{body.classList.remove("dark-mode");}}catch(e){}})();</script>',
            json_encode($shopKeySuffix, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)
        );

        $content = $response->getContent();
        // Insert before </head> if possible; otherwise at start of content
        if (($pos = stripos($content, '</head>')) !== false) {
            $content = substr($content, 0, $pos) . $style . $script . substr($content, $pos);
        } else {
            $content = $style . $script . $content;
        }

        $response->setContent($content);

        return $response;
    }
}
