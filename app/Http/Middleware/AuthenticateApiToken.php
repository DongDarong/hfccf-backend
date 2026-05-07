<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);

        if (! $accessToken || $accessToken->tokenable_type !== User::class) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();

            return response()->json([
                'success' => false,
                'message' => 'Token has expired.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::query()
            ->with([
                'department',
                'role',
                'permissions' => fn ($query) => $query->orderBy('permissions.code'),
            ])
            ->find($accessToken->tokenable_id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('accessToken', $accessToken);
        $request->setUserResolver(static fn () => $user);
        Auth::setUser($user);

        return $next($request);
    }
}
