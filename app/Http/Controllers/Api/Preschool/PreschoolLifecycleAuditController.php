<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Resources\Preschool\PreschoolLifecycleAuditLogResource;
use App\Models\PreschoolLifecycleAuditLog;
use App\Models\PreschoolReportSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function analytics(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'days' => ['sometimes', 'integer', 'min:7', 'max:90'],
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $baseQuery = PreschoolLifecycleAuditLog::query();

        foreach (['report_period_id', 'term_id', 'academic_year_id'] as $field) {
            if (($validated[$field] ?? null) !== null) {
                $baseQuery->where($field, $validated[$field]);
            }
        }

        $snapshotQuery = PreschoolReportSnapshot::query();
        foreach (['report_period_id', 'term_id', 'academic_year_id'] as $field) {
            if (($validated[$field] ?? null) !== null) {
                $snapshotQuery->where($field, $validated[$field]);
            }
        }

        $overview = [
            'totalEvents' => (clone $baseQuery)->count(),
            'blockedWrites' => (clone $baseQuery)->where('action_type', 'write.blocked')->count(),
            'overrideAttempts' => (clone $baseQuery)->where('action_type', 'override.attempt')->count(),
            'overrideApprovals' => (clone $baseQuery)->where('action_type', 'override.approved')->count(),
            'snapshotEvents' => (clone $baseQuery)->where('action_type', 'report_snapshot.generated')->count(),
            'finalizeEvents' => (clone $baseQuery)->whereIn('action_type', ['report_period.finalized', 'assessment.finalized'])->count(),
            'lockEvents' => (clone $baseQuery)->whereIn('action_type', ['report_period.locked', 'term.closed'])->count(),
            'archiveEvents' => (clone $baseQuery)->whereIn('action_type', ['report_period.archived', 'assessment.archived'])->count(),
            'snapshotCount' => (clone $snapshotQuery)->count(),
            'studentSnapshotCount' => (clone $snapshotQuery)->where('snapshot_type', 'student_report')->count(),
            'classroomSnapshotCount' => (clone $snapshotQuery)->where('snapshot_type', 'classroom_report')->count(),
            'progressSnapshotCount' => (clone $snapshotQuery)->where('snapshot_type', 'progress_summary')->count(),
        ];

        $actionCounts = (clone $baseQuery)
            ->select('action_type', DB::raw('COUNT(*) as total'))
            ->groupBy('action_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'actionType' => $row->action_type,
                'total' => (int) $row->total,
            ])
            ->values();

        $entityCounts = (clone $baseQuery)
            ->select('entity_type', DB::raw('COUNT(*) as total'))
            ->groupBy('entity_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'entityType' => $row->entity_type,
                'total' => (int) $row->total,
            ])
            ->values();

        $actorCounts = (clone $baseQuery)
            ->select('actor_role', DB::raw('COUNT(*) as total'))
            ->groupBy('actor_role')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'actorRole' => $row->actor_role,
                'total' => (int) $row->total,
            ])
            ->values();

        $blockedWriteTrend = (clone $baseQuery)
            ->where('action_type', 'write.blocked')
            ->whereDate('created_at', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'day' => $row->day,
                'total' => (int) $row->total,
            ])
            ->values();

        $lifecycleTimeline = (clone $baseQuery)
            ->whereDate('created_at', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('DATE(created_at) as day, action_type, COUNT(*) as total')
            ->groupBy('day', 'action_type')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'day' => $row->day,
                'actionType' => $row->action_type,
                'total' => (int) $row->total,
            ])
            ->values();

        $overrideReasons = (clone $baseQuery)
            ->whereIn('action_type', ['override.attempt', 'override.approved'])
            ->whereNotNull('override_reason')
            ->select('override_reason', DB::raw('COUNT(*) as total'))
            ->groupBy('override_reason')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'reason' => $row->override_reason,
                'total' => (int) $row->total,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Preschool lifecycle audit analytics retrieved successfully.',
            'data' => [
                'overview' => $overview,
                'actionCounts' => $actionCounts,
                'entityCounts' => $entityCounts,
                'actorCounts' => $actorCounts,
                'blockedWriteTrend' => $blockedWriteTrend,
                'lifecycleTimeline' => $lifecycleTimeline,
                'overrideReasons' => $overrideReasons,
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
