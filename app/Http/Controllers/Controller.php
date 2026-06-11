<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    // ── Shared response helpers ───────────────────────────────────────────────

    protected function ok(mixed $data, ?string $message = null, array $meta = []): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];
        if ($message !== null) {
            $payload['message'] = $message;
        }
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload);
    }

    protected function created(mixed $data, string $message = 'Created.'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], Response::HTTP_CREATED);
    }

    protected function noContent(string $message = 'Deleted.'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => null]);
    }

    protected function error(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], $status);
    }

    protected function unauthorized(): JsonResponse
    {
        return $this->error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
    }

    protected function forbidden(): JsonResponse
    {
        return $this->error('Forbidden.', Response::HTTP_FORBIDDEN);
    }

    protected function paginationMeta($paginator): array
    {
        return [
            'page'       => $paginator->currentPage(),
            'perPage'    => $paginator->perPage(),
            'total'      => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }

    // ── Role-based guards ─────────────────────────────────────────────────────

    protected function requireAuth(?User $user): ?JsonResponse
    {
        return $user ? null : $this->unauthorized();
    }

    protected function requireRoles(?User $user, array $roles): ?JsonResponse
    {
        if (! $user) {
            return $this->unauthorized();
        }
        if (! in_array($user->role_code, $roles, true)) {
            return $this->forbidden();
        }

        return null;
    }
}
