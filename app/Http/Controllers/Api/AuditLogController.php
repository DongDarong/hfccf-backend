<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLog\AuditLogResource;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role_code, ['superadmin', 'adminsport'], true)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:64'],
            'action' => ['sometimes', 'nullable', 'string', 'max:64'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:191'],
            'entity_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'actor_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $domain = trim((string) ($validated['domain'] ?? ''));
        $action = trim((string) ($validated['action'] ?? ''));
        $entityType = trim((string) ($validated['entity_type'] ?? ''));
        $entityId = trim((string) ($validated['entity_id'] ?? ''));
        $actorUserId = trim((string) ($validated['actor_user_id'] ?? ''));
        $search = trim((string) ($validated['search'] ?? ''));
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $query = AuditLog::query()->with(['actor']);

        if ($domain !== '') {
            $query->where('domain', $domain);
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }

        if ($entityId !== '') {
            $query->where('entity_id', $entityId);
        }

        if ($actorUserId !== '') {
            $query->where('actor_user_id', $actorUserId);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('entity_label', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('entity_type', 'like', $like);
            });
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Audit logs retrieved successfully.',
            $paginator,
            $request,
            AuditLogResource::class,
        );
    }
}
