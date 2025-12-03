<?php
// ============================================
// app/Http/Middleware/CheckUserHasShop.php
// ============================================

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserHasShop
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Allow if user owns a shop OR is assigned to at least one subshop
        $ownsShop = $user->hasShop();
        $assigned = method_exists($user, 'subshops') ? $user->subshops()->exists() : false;

        if (!$ownsShop && !$assigned) {
            // No ownership and no assignment: force owner to create a shop
            return redirect()->route('shop.create')
                ->with('info', 'Tafadhali tengeneza duka lako kwanza kabla ya kuendelea');
        }

        // Check if user's shop is suspended
        if ($ownsShop && $user->shop->isSuspended()) {
            return redirect()->route('shop.status')
                ->with('error', 'Duka lako limefungwa kwa sababu ya malipo. Tafadhali wasiliana na msimamizi au ongeza malipo.');
        }

        return $next($request);
    }
}
