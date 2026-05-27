<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuardianPortalAccess
{
    /**
     * Legacy compatibility only: the public guardian portal is disabled, so
     * this middleware fails closed instead of granting any login access.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        return response()->json([
            'success' => false,
            'message' => 'Guardian portal access is disabled.',
            'data' => null,
        ], Response::HTTP_GONE);
    }
}
