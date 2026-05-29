<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\User;
use App\Support\PreschoolLifecycleAuditService;
use App\Support\PreschoolAcademicLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Academic lifecycle records are the admin-only backbone for Preschool year
 * and term operations. They keep reporting, assignments, attendance, and
 * assessment writes aligned without exposing teacher management privileges.
 */
class PreschoolAcademicLifecycleController extends Controller
{
    public function index(Request $request, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool academic lifecycle retrieved successfully.',
            'data' => [
                'academicYears' => $service->academicYears()->map(fn (PreschoolAcademicYear $year) => $service->academicYearSnapshot($year->loadMissing('terms')))->values(),
                'terms' => $service->terms()->map(fn (PreschoolAcademicTerm $term) => $service->termSnapshot($term))->values(),
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_OK);
    }

    public function storeAcademicYear(Request $request, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate($this->academicYearRules());
        $academicYear = $service->createAcademicYear($validated);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_year.created',
            entityType: 'academic_year',
            entityId: (string) $academicYear->id,
            previousState: null,
            newState: $service->academicYearSnapshot($academicYear->loadMissing('terms')),
        );

        return response()->json([
            'success' => true,
            'message' => 'Academic year created successfully.',
            'data' => [
                'academicYear' => $service->academicYearSnapshot($academicYear->loadMissing('terms')),
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateAcademicYear(Request $request, PreschoolAcademicYear $academicYear, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate($this->academicYearRules(true, $academicYear));
        $previous = $service->academicYearSnapshot($academicYear->replicate()->loadMissing('terms'));
        $updated = $service->updateAcademicYear($academicYear, $validated);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_year.updated',
            entityType: 'academic_year',
            entityId: (string) $updated->id,
            previousState: $previous,
            newState: $service->academicYearSnapshot($updated->loadMissing('terms')),
        );

        return response()->json([
            'success' => true,
            'message' => 'Academic year updated successfully.',
            'data' => [
                'academicYear' => $service->academicYearSnapshot($updated->loadMissing('terms')),
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_OK);
    }

    public function activateAcademicYear(Request $request, PreschoolAcademicYear $academicYear, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->academicYearSnapshot($academicYear->replicate()->loadMissing('terms'));
        $updated = $service->activateAcademicYear($academicYear);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_year.activated',
            entityType: 'academic_year',
            entityId: (string) $updated->id,
            previousState: $previous,
            newState: $service->academicYearSnapshot($updated->loadMissing('terms')),
        );

        return response()->json([
            'success' => true,
            'message' => 'Academic year activated successfully.',
            'data' => [
                'academicYear' => $service->academicYearSnapshot($updated->loadMissing('terms')),
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_OK);
    }

    public function closeAcademicYear(Request $request, PreschoolAcademicYear $academicYear, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->academicYearSnapshot($academicYear->replicate()->loadMissing('terms'));
        $updated = $service->closeAcademicYear($academicYear);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_year.closed',
            entityType: 'academic_year',
            entityId: (string) $updated->id,
            previousState: $previous,
            newState: $service->academicYearSnapshot($updated->loadMissing('terms')),
        );

        return response()->json([
            'success' => true,
            'message' => 'Academic year closed successfully.',
            'data' => [
                'academicYear' => $service->academicYearSnapshot($updated->loadMissing('terms')),
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_OK);
    }

    public function storeTerm(Request $request, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate($this->termRules());
        $term = $service->createTerm($validated);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_term.created',
            entityType: 'academic_term',
            entityId: (string) $term->id,
            previousState: null,
            newState: $service->termSnapshot($term),
        );

        return response()->json([
            'success' => true,
            'message' => 'Term created successfully.',
            'data' => [
                'term' => $service->termSnapshot($term),
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateTerm(Request $request, PreschoolAcademicTerm $term, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate($this->termRules(true, $term));
        $previous = $service->termSnapshot($term->replicate()->loadMissing('academicYear'));
        $updated = $service->updateTerm($term, $validated);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_term.updated',
            entityType: 'academic_term',
            entityId: (string) $updated->id,
            previousState: $previous,
            newState: $service->termSnapshot($updated),
        );

        return response()->json([
            'success' => true,
            'message' => 'Term updated successfully.',
            'data' => [
                'term' => $service->termSnapshot($updated),
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_OK);
    }

    public function activateTerm(Request $request, PreschoolAcademicTerm $term, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->termSnapshot($term->replicate()->loadMissing('academicYear'));
        $updated = $service->activateTerm($term);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_term.activated',
            entityType: 'academic_term',
            entityId: (string) $updated->id,
            previousState: $previous,
            newState: $service->termSnapshot($updated),
        );

        return response()->json([
            'success' => true,
            'message' => 'Term activated successfully.',
            'data' => [
                'term' => $service->termSnapshot($updated),
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_OK);
    }

    public function closeTerm(Request $request, PreschoolAcademicTerm $term, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->termSnapshot($term->replicate()->loadMissing('academicYear'));
        $updated = $service->closeTerm($term);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_term.closed',
            entityType: 'academic_term',
            entityId: (string) $updated->id,
            previousState: $previous,
            newState: $service->termSnapshot($updated),
        );

        return response()->json([
            'success' => true,
            'message' => 'Term closed successfully.',
            'data' => [
                'term' => $service->termSnapshot($updated),
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_OK);
    }

    private function academicYearRules(bool $isUpdate = false, ?PreschoolAcademicYear $academicYear = null): array
    {
        return [
            'code' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:50',
                Rule::unique('preschool_academic_years', 'code')->ignore($academicYear?->id),
            ],
            'label' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:191'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'closed', 'archived'])],
            'is_current' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    private function termRules(bool $isUpdate = false, ?PreschoolAcademicTerm $term = null): array
    {
        return [
            'academic_year_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:preschool_academic_years,id'],
            'code' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:50',
                Rule::unique('preschool_terms', 'code')->ignore($term?->id),
            ],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:191'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'closed', 'archived'])],
            'is_current' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
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

    /**
     * Academic lifecycle events are audited separately from CRUD so override
     * history and term closures remain easy to review without mutating data.
     */
    private function recordAudit(
        Request $request,
        string $actionType,
        string $entityType,
        string $entityId,
        ?array $previousState,
        ?array $newState,
    ): void {
        $academicYearId = null;
        if (is_array($newState)) {
            $academicYearId = $newState['academicYearId'] ?? $newState['id'] ?? null;
        }
        if ($academicYearId === null && is_array($previousState)) {
            $academicYearId = $previousState['academicYearId'] ?? $previousState['id'] ?? null;
        }

        $termId = null;
        if ($entityType === 'academic_term') {
            if (is_array($newState)) {
                $termId = $newState['id'] ?? null;
            }
            if ($termId === null && is_array($previousState)) {
                $termId = $previousState['id'] ?? null;
            }
        }

        app(PreschoolLifecycleAuditService::class)->recordSafely([
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role_code,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'academic_year_id' => $academicYearId,
            'term_id' => $termId,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'request_context' => app(PreschoolLifecycleAuditService::class)->requestContext($request, [
                'route' => $request->route()?->getName(),
            ]),
        ]);
    }
}
