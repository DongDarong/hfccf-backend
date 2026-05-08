<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        // `auth.token` loads permissions; keep a safe fallback query in case the relationship is missing.
        $permissionCodes = $user->relationLoaded('permissions')
            ? $user->permissions->pluck('code')->all()
            : $user->permissions()->pluck('permissions.code')->all();

        if (in_array('all:*', $permissionCodes, true) || in_array($permissionCode, $permissionCodes, true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}

