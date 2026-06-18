<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChangeCompleted
{
    /**
     * Allow only password-change-related routes while the user is forced to
     * update their password.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            $user = $this->resolveBearerTokenUser($request);
        }

        if (! $user || ! (bool) ($user->must_change_password ?? false)) {
            return $next($request);
        }

        $allowed = [
            'GET api/auth/me',
            'POST api/auth/logout',
            'PATCH api/auth/change-password',
        ];

        if (in_array($request->method().' '.$request->path(), $allowed, true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Password change required.',
            'data' => [
                'requires_password_change' => true,
            ],
        ], Response::HTTP_FORBIDDEN);
    }

    private function resolveBearerTokenUser(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken?->tokenable instanceof User ? $accessToken->tokenable : null;
    }
}
