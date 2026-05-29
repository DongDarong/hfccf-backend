<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolStudentAssessmentRequest;
use App\Http\Requests\Preschool\UpdatePreschoolStudentAssessmentRequest;
use App\Http\Resources\Preschool\PreschoolStudentAssessmentResource;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use App\Support\PreschoolLifecycleAuditService;
use App\Support\PreschoolAssessmentService;
use App\Support\PreschoolLifecycleGuardService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolStudentAssessmentController extends Controller
{
    /**
     * Assessments stay staff-scoped and draft-editable here while the service
     * keeps finalized records immutable.
     */
    public function index(Request $request, PreschoolStudent $student, PreschoolAssessmentService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $service->ensureUserCanAccessStudent($request->user(), $student, $this->nullableInt($request->query('class_id')));

        $paginator = $service->listAssessments($request->user(), $student, $request->query());

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessments retrieved successfully.',
            'data' => [
                'items' => PreschoolStudentAssessmentResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolStudentAssessmentRequest $request, PreschoolStudent $student, PreschoolAssessmentService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $data = $request->validated();
        if ($response = app(PreschoolLifecycleGuardService::class)->assessmentWriteLock($request->user(), $data, null, $student)) {
            return $response;
        }

        $assessment = $service->createAssessment($request->user(), $student, $data);

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment created successfully.',
            'data' => [
                'assessment' => PreschoolStudentAssessmentResource::make($assessment)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePreschoolStudentAssessmentRequest $request, PreschoolStudentAssessment $assessment, PreschoolAssessmentService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $data = $request->validated();
        if ($response = app(PreschoolLifecycleGuardService::class)->assessmentWriteLock($request->user(), $data, $assessment)) {
            return $response;
        }

        $updated = $service->updateAssessment($request->user(), $assessment, $data);

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment updated successfully.',
            'data' => [
                'assessment' => PreschoolStudentAssessmentResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function finalize(Request $request, PreschoolStudentAssessment $assessment, PreschoolAssessmentService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        if ($response = app(PreschoolLifecycleGuardService::class)->assessmentWriteLock($request->user(), [], $assessment)) {
            return $response;
        }

        $previous = $this->assessmentState($assessment);
        $updated = $service->finalizeAssessment($request->user(), $assessment);
        $this->recordAudit(
            request: $request,
            actionType: 'assessment.finalized',
            assessment: $updated,
            previousState: $previous,
            newState: $this->assessmentState($updated),
        );

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment finalized successfully.',
            'data' => [
                'assessment' => PreschoolStudentAssessmentResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, PreschoolStudentAssessment $assessment, PreschoolAssessmentService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        if ($response = app(PreschoolLifecycleGuardService::class)->assessmentWriteLock($request->user(), [], $assessment)) {
            return $response;
        }

        $previous = $this->assessmentState($assessment);
        $updated = $service->archiveAssessment($request->user(), $assessment);
        $this->recordAudit(
            request: $request,
            actionType: 'assessment.archived',
            assessment: $updated,
            previousState: $previous,
            newState: $this->assessmentState($updated),
        );

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment archived successfully.',
            'data' => [
                'assessment' => PreschoolStudentAssessmentResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeAny(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Keep Preschool paging consistent with the rest of the module so the
     * frontend can reuse the same table/pagination components safely.
     */
    private function paginationShape(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }

    private function assessmentState(PreschoolStudentAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'student_id' => $assessment->student_id,
            'class_id' => $assessment->class_id,
            'period_label' => $assessment->period_label,
            'assessment_date' => $assessment->assessment_date?->toDateString(),
            'status' => $assessment->status,
            'score' => $assessment->score,
            'rating' => $assessment->rating,
        ];
    }

    private function recordAudit(Request $request, string $actionType, PreschoolStudentAssessment $assessment, ?array $previousState, ?array $newState): void
    {
        app(PreschoolLifecycleAuditService::class)->recordSafely([
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role_code,
            'action_type' => $actionType,
            'entity_type' => 'assessment',
            'entity_id' => (string) $assessment->id,
            'academic_year_id' => $assessment->academic_year_id,
            'term_id' => $assessment->term_id,
            'report_period_id' => app(\App\Support\PreschoolReportPeriodService::class)->resolveForAssessment([
                'period_label' => $assessment->period_label,
                'assessment_date' => $assessment->assessment_date?->toDateString(),
            ], $assessment->assessment_date?->toDateString())?->id,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'request_context' => app(PreschoolLifecycleAuditService::class)->requestContext($request, [
                'route' => $request->route()?->getName(),
            ]),
        ]);
    }
}
