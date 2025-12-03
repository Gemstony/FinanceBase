<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelescopeSuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            abort(403, 'Unauthorized access to Telescope');
        }

        // Check if user has Super Admin role
        if (!$request->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied. Super Admin role required for Telescope access.');
        }

        return $next($request);
    }
}
