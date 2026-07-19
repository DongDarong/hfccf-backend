<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Exceptions\PreschoolMonthlySubmissionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\FinalizePreschoolMonthlySubmissionRequest;
use App\Http\Requests\Preschool\ReturnPreschoolMonthlySubmissionRequest;
use App\Http\Requests\Preschool\StorePreschoolMonthlySubmissionRequest;
use App\Http\Requests\Preschool\UpsertPreschoolMonthlySubmissionScoreRequest;
use App\Http\Resources\Preschool\PreschoolMonthlySubmissionDetailResource;
use App\Http\Resources\Preschool\PreschoolMonthlySubmissionResource;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolClass;
use App\Models\PreschoolMonthlySubmission;
use App\Models\PreschoolStudent;
use App\Services\PreschoolMonthlySubmissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolMonthlySubmissionController extends Controller
{
    public function __construct(
        private readonly PreschoolMonthlySubmissionService $service,
    ) {}

    // ── List submissions ─────────────────────────────────────────────────────

    /**
     * List monthly submissions with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor) {
            return $this->unauthorized();
        }

        $query = PreschoolMonthlySubmission::query()
            ->with([
                'academicYear',
                'class',
                'category',
                'submittedBy',
                'reviewedBy',
                'returnedBy',
                'finalizedBy',
            ]);

        // Scope to teacher's classes or admin access
        $this->applyListScopes($actor, $query);

        // Apply filters
        $this->applyListFilters($request, $query);

        // Paginate
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $paginator = $query->orderByDesc('updated_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->ok(
            [
                'items' => PreschoolMonthlySubmissionResource::collection($paginator->getCollection()),
                'pagination' => $this->paginationMeta($paginator),
            ],
            'Monthly submissions retrieved.'
        );
    }

    // ── Create draft submission ──────────────────────────────────────────────

    /**
     * Create a new monthly submission draft.
     *
     * Returns 201 for new draft, 200 for existing editable submission.
     */
    public function store(StorePreschoolMonthlySubmissionRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor) {
            return $this->unauthorized();
        }

        $data = $request->validated();

        try {
            $academicYear = PreschoolAcademicYear::findOrFail($data['academic_year_id']);
            $class = PreschoolClass::findOrFail($data['class_id']);
            $category = PreschoolAssessmentCategory::findOrFail($data['assessment_category_id']);

            $submission = $this->service->createDraft(
                $actor,
                $academicYear,
                $class,
                $category,
            );
        } catch (PreschoolMonthlySubmissionException $e) {
            return $this->renderException($e);
        }

        $submission->load([
            'academicYear', 'class', 'category',
            'submittedBy', 'reviewedBy', 'returnedBy', 'finalizedBy',
        ]);

        // Return 201 for new draft, 200 for existing editable
        $status = $submission->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK;

        return response()->json([
            'success' => true,
            'message' => $submission->wasRecentlyCreated
                ? 'Monthly submission draft created.'
                : 'Existing editable submission returned.',
            'data' => ['submission' => PreschoolMonthlySubmissionResource::make($submission)],
        ], $status);
    }

    // ── Show submission detail ───────────────────────────────────────────────

    /**
     * Get a single submission with full detail.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor) {
            return $this->unauthorized();
        }

        $submission = $this->findSubmission($id);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        // Authorization check
        if (!$this->canAccessSubmission($actor, $submission)) {
            return $this->forbidden();
        }

        $submission->load([
            'academicYear', 'class', 'category',
            'studentAssessments.student',
            'submittedBy', 'reviewedBy', 'returnedBy', 'finalizedBy',
        ]);

        return $this->ok(
            ['submission' => PreschoolMonthlySubmissionDetailResource::make($submission)],
            'Submission detail retrieved.'
        );
    }

    // ── Upsert student score ─────────────────────────────────────────────────

    /**
     * Add or update a student's score in the submission.
     */
    public function upsertScore(
        UpsertPreschoolMonthlySubmissionScoreRequest $request,
        string $submissionId,
        string $studentId
    ): JsonResponse {
        $actor = $request->user();
        if (!$actor) {
            return $this->unauthorized();
        }

        $submission = $this->findSubmission($submissionId);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        // Authorization check
        if (!$this->canAccessSubmission($actor, $submission)) {
            return $this->forbidden();
        }

        $student = PreschoolStudent::find($studentId);
        if (!$student) {
            return $this->error('Student not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $assessment = $this->service->addOrUpdateStudentScore(
                $actor,
                $submission,
                $student,
                $request->validated(),
            );
        } catch (PreschoolMonthlySubmissionException $e) {
            return $this->renderException($e);
        }

        $assessment->load(['student']);

        return $this->ok(
            ['assessment' => $assessment],
            'Score updated.'
        );
    }

    // ── Submit submission ────────────────────────────────────────────────────

    /**
     * Submit a draft or returned submission for review.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor) {
            return $this->unauthorized();
        }

        $submission = $this->findSubmission($id);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        if (!$this->canAccessSubmission($actor, $submission)) {
            return $this->forbidden();
        }

        try {
            $submission = $this->service->submit($actor, $submission);
        } catch (PreschoolMonthlySubmissionException $e) {
            return $this->renderException($e);
        }

        $submission->load([
            'academicYear', 'class', 'category',
            'submittedBy', 'reviewedBy', 'returnedBy', 'finalizedBy',
        ]);

        return $this->ok(
            ['submission' => PreschoolMonthlySubmissionResource::make($submission)],
            'Submission submitted for review.'
        );
    }

    // ── Return submission ────────────────────────────────────────────────────

    /**
     * Return a submission for correction (admin only).
     */
    public function return(ReturnPreschoolMonthlySubmissionRequest $request, string $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor || !in_array($actor->role_code, ['adminpreschool', 'superadmin'], true)) {
            return $this->forbidden();
        }

        $submission = $this->findSubmission($id);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $data = $request->validated();

        try {
            $submission = $this->service->returnForCorrection(
                $actor,
                $submission,
                $data['return_reason'],
                $data['review_comment'] ?? null,
            );
        } catch (PreschoolMonthlySubmissionException $e) {
            return $this->renderException($e);
        }

        $submission->load([
            'academicYear', 'class', 'category',
            'submittedBy', 'reviewedBy', 'returnedBy', 'finalizedBy',
        ]);

        return $this->ok(
            ['submission' => PreschoolMonthlySubmissionResource::make($submission)],
            'Submission returned for correction.'
        );
    }

    // ── Finalize submission ──────────────────────────────────────────────────

    /**
     * Finalize a submitted submission (admin only).
     */
    public function finalize(FinalizePreschoolMonthlySubmissionRequest $request, string $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor || !in_array($actor->role_code, ['adminpreschool', 'superadmin'], true)) {
            return $this->forbidden();
        }

        $submission = $this->findSubmission($id);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $data = $request->validated();

        try {
            $submission = $this->service->finalize(
                $actor,
                $submission,
                $data['review_comment'] ?? null,
            );
        } catch (PreschoolMonthlySubmissionException $e) {
            return $this->renderException($e);
        }

        $submission->load([
            'academicYear', 'class', 'category',
            'studentAssessments.student',
            'submittedBy', 'reviewedBy', 'returnedBy', 'finalizedBy',
        ]);

        return $this->ok(
            ['submission' => PreschoolMonthlySubmissionDetailResource::make($submission)],
            'Submission finalized.'
        );
    }

    // ── Archive submission ───────────────────────────────────────────────────

    /**
     * Archive a finalized submission (admin only).
     */
    public function archive(Request $request, string $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor || !in_array($actor->role_code, ['adminpreschool', 'superadmin'], true)) {
            return $this->forbidden();
        }

        $submission = $this->findSubmission($id);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        try {
            $submission = $this->service->archive($actor, $submission);
        } catch (PreschoolMonthlySubmissionException $e) {
            return $this->renderException($e);
        }

        $submission->load([
            'academicYear', 'class', 'category',
            'submittedBy', 'reviewedBy', 'returnedBy', 'finalizedBy',
        ]);

        return $this->ok(
            ['submission' => PreschoolMonthlySubmissionResource::make($submission)],
            'Submission archived.'
        );
    }

    // ── Delete draft ─────────────────────────────────────────────────────────

    /**
     * Delete a draft submission.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor) {
            return $this->unauthorized();
        }

        $submission = $this->findSubmission($id);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        if (!$this->canAccessSubmission($actor, $submission)) {
            return $this->forbidden();
        }

        try {
            $this->service->deleteDraft($actor, $submission);
        } catch (PreschoolMonthlySubmissionException $e) {
            return $this->renderException($e);
        }

        return $this->noContent('Draft submission deleted.');
    }

    // ── Helper methods ───────────────────────────────────────────────────────

    private function findSubmission(string $id): PreschoolMonthlySubmission|JsonResponse
    {
        $submission = PreschoolMonthlySubmission::find($id);
        if (!$submission) {
            return $this->error('Submission not found.', Response::HTTP_NOT_FOUND);
        }
        return $submission;
    }

    private function canAccessSubmission($actor, PreschoolMonthlySubmission $submission): bool
    {
        // Admin has access to all
        if (in_array($actor->role_code, ['adminpreschool', 'superadmin'], true)) {
            return true;
        }

        // Teacher must have access to the class
        return $actor->preschoolClassTeacherAssignments()
            ->where('class_id', $submission->class_id)
            ->where('status', 'active')
            ->exists();
    }

    private function applyListScopes($actor, Builder $query): void
    {
        // Admins see all submissions
        if (in_array($actor->role_code, ['adminpreschool', 'superadmin'], true)) {
            return;
        }

        // Teachers see only their class submissions
        $query->whereIn('class_id',
            $actor->preschoolClassTeacherAssignments()
                ->where('status', 'active')
                ->pluck('class_id')
        );
    }

    private function applyListFilters(Request $request, Builder $query): void
    {
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->has('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        if ($request->has('assessment_category_id')) {
            $query->where('assessment_category_id', $request->query('assessment_category_id'));
        }

        if ($request->has('submission_month')) {
            $query->whereDate('submission_month', $request->query('submission_month'));
        }
    }

    private function renderException(PreschoolMonthlySubmissionException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => ['error_code' => $e->getErrorCode()],
        ], $e->getCode());
    }
}
