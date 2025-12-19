<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantContext
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Ensure we're in a tenant context
        if (! tenancy()->initialized) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context not initialized',
            ], 400);
        }

        return $next($request);
    }
}
