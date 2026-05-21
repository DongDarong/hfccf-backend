<?php

namespace App\Http\Middleware;

use App\Models\PreschoolGuardianPortalAccount;
use App\Support\PreschoolGuardianPortalStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuardianPortalAccess
{
    /**
     * Guardians can only access the read-only portal after the invitation
     * has been activated and the portal account is explicitly active.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->role_code !== 'guardian') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $account = PreschoolGuardianPortalAccount::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $account || $account->status !== PreschoolGuardianPortalStatus::ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
