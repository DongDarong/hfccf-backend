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

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Preschool academic lifecycle retrieved successfully.',
        ), Response::HTTP_OK);
    }

    public function showAcademicYear(Request $request, PreschoolAcademicYear $academicYear, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Academic year retrieved successfully.',
            academicYear: $academicYear->loadMissing('terms'),
        ), Response::HTTP_OK);
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

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Academic year created successfully.',
            academicYear: $academicYear->loadMissing('terms'),
            status: Response::HTTP_CREATED,
        ), Response::HTTP_CREATED);
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

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Academic year updated successfully.',
            academicYear: $updated->loadMissing('terms'),
        ), Response::HTTP_OK);
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

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Academic year activated successfully.',
            academicYear: $updated->loadMissing('terms'),
        ), Response::HTTP_OK);
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

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Academic year closed successfully.',
            academicYear: $updated->loadMissing('terms'),
        ), Response::HTTP_OK);
    }

    public function archiveAcademicYear(Request $request, PreschoolAcademicYear $academicYear, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->academicYearSnapshot($academicYear->replicate()->loadMissing('terms'));
        $updated = $service->archiveAcademicYear($academicYear);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_year.archived',
            entityType: 'academic_year',
            entityId: (string) $updated->id,
            previousState: $previous,
            newState: $service->academicYearSnapshot($updated->loadMissing('terms')),
        );

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Academic year archived successfully.',
            academicYear: $updated->loadMissing('terms'),
        ), Response::HTTP_OK);
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

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Term created successfully.',
            term: $term,
            status: Response::HTTP_CREATED,
        ), Response::HTTP_CREATED);
    }

    public function showTerm(Request $request, PreschoolAcademicTerm $term, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Term retrieved successfully.',
            term: $term->loadMissing('academicYear'),
        ), Response::HTTP_OK);
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

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Term updated successfully.',
            term: $updated,
        ), Response::HTTP_OK);
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

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Term activated successfully.',
            term: $updated,
        ), Response::HTTP_OK);
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

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Term closed successfully.',
            term: $updated,
        ), Response::HTTP_OK);
    }

    public function archiveTerm(Request $request, PreschoolAcademicTerm $term, PreschoolAcademicLifecycleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->termSnapshot($term->replicate()->loadMissing('academicYear'));
        $updated = $service->archiveTerm($term);
        $this->recordAudit(
            request: $request,
            actionType: 'academic_term.archived',
            entityType: 'academic_term',
            entityId: (string) $updated->id,
            previousState: $previous,
            newState: $service->termSnapshot($updated),
        );

        return response()->json($this->lifecycleResponse(
            service: $service,
            message: 'Term archived successfully.',
            term: $updated,
        ), Response::HTTP_OK);
    }

    private function academicYearRules(bool $isUpdate = false, ?PreschoolAcademicYear $academicYear = null): array
    {
        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('preschool_academic_years', 'code')->ignore($academicYear?->id),
            ],
            'name' => ['required', 'string', 'max:191'],
            'label' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['sometimes', 'nullable', Rule::in(['draft', 'active', 'closed', 'archived'])],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }

    private function termRules(bool $isUpdate = false, ?PreschoolAcademicTerm $term = null): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:preschool_academic_years,id'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('preschool_terms', 'code')->ignore($term?->id),
            ],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['sometimes', 'nullable', Rule::in(['draft', 'active', 'closed', 'archived'])],
            'is_current' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Keep every lifecycle mutation response consistent so frontend pages can
     * refresh both lists and the active year/term context from one payload.
     *
     * @param  array{service: PreschoolAcademicLifecycleService, message: string, academicYear?: ?PreschoolAcademicYear, term?: ?PreschoolAcademicTerm, status?: int}  $arguments
     */
    private function lifecycleResponse(PreschoolAcademicLifecycleService $service, string $message, ?PreschoolAcademicYear $academicYear = null, ?PreschoolAcademicTerm $term = null, int $status = Response::HTTP_OK): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => array_filter([
                'academicYears' => $service->academicYears()->map(fn (PreschoolAcademicYear $year) => $service->academicYearSnapshot($year->loadMissing('terms')))->values(),
                'terms' => $service->terms()->map(fn (PreschoolAcademicTerm $item) => $service->termSnapshot($item))->values(),
                'academicYear' => $academicYear ? $service->academicYearSnapshot($academicYear->loadMissing('terms')) : null,
                'term' => $term ? $service->termSnapshot($term->loadMissing('academicYear')) : null,
                'currentContext' => $service->currentContext(),
            ], static fn ($value) => $value !== null),
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
