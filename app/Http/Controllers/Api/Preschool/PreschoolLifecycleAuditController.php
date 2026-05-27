<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Resources\Preschool\PreschoolLifecycleAuditLogResource;
use App\Models\PreschoolLifecycleAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preschool lifecycle audit logs stay admin-only so overrides, locks, and
 * blocked writes can be reviewed without exposing write access to staff.
 */
class PreschoolLifecycleAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'action_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:191'],
            'entity_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'actor_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
        ]);

        $query = PreschoolLifecycleAuditLog::query()->with(['actor', 'reportPeriod']);

        if (($validated['action_type'] ?? '') !== '') {
            $query->where('action_type', $validated['action_type']);
        }

        if (($validated['entity_type'] ?? '') !== '') {
            $query->where('entity_type', $validated['entity_type']);
        }

        if (($validated['entity_id'] ?? '') !== '') {
            $query->where('entity_id', $validated['entity_id']);
        }

        if (($validated['report_period_id'] ?? null) !== null) {
            $query->where('report_period_id', $validated['report_period_id']);
        }

        if (($validated['term_id'] ?? null) !== null) {
            $query->where('term_id', $validated['term_id']);
        }

        if (($validated['academic_year_id'] ?? null) !== null) {
            $query->where('academic_year_id', $validated['academic_year_id']);
        }

        if (($validated['actor_user_id'] ?? '') !== '') {
            $query->where('actor_user_id', $validated['actor_user_id']);
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                (int) ($validated['per_page'] ?? 20),
                ['*'],
                'page',
                (int) ($validated['page'] ?? 1),
            );

        return response()->json([
            'success' => true,
            'message' => 'Preschool lifecycle audit logs retrieved successfully.',
            'data' => [
                'items' => PreschoolLifecycleAuditLogResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
