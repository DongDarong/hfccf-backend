<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Assessment\AssessmentSubmissionResource;
use App\Http\Resources\Assessment\AssessmentSubmissionListResource;
use App\Models\AssessmentSubmission;
use App\Models\User;
use App\Services\AssessmentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssessmentSubmissionController extends Controller
{
    public function __construct(private AssessmentSubmissionService $service) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page'               => ['sometimes', 'integer', 'min:1'],
            'per_page'           => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status'             => ['sometimes', 'nullable', 'string', 'max:32'],
            'form_template_id'   => ['sometimes', 'nullable', 'integer'],
            'student_id'         => ['sometimes', 'nullable', 'integer'],
            'sort_by'            => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction'     => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $perPage       = (int) ($validated['per_page'] ?? 20);
        $sortBy        = in_array($validated['sort_by'] ?? '', ['submitted_at', 'created_at', 'status'], true)
            ? $validated['sort_by']
            : 'created_at';
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $user  = $request->user();
        $query = AssessmentSubmission::query()->with(['formTemplate', 'student']);

        if (in_array($user->role_code, ['teacherpreschool'], true)) {
            $query->where('assessor_id', $user->id);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['form_template_id'])) {
            $query->where('form_template_id', $validated['form_template_id']);
        }
        if (! empty($validated['student_id'])) {
            $query->where('student_id', $validated['student_id']);
        }

        $paginator = $query->orderBy($sortBy, $sortDirection)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => AssessmentSubmissionListResource::collection($paginator->items()),
            'meta'    => $this->paginationShape($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'form_template_id' => ['required', 'integer', 'exists:assessment_form_templates,id'],
            'student_id'       => ['required', 'integer', 'exists:preschool_students,id'],
            'answers'          => ['sometimes', 'array'],
            'answers.*.question_id'   => ['required_with:answers', 'integer'],
            'answers.*.answer_value'  => ['sometimes', 'nullable'],
            'answers.*.repeat_index'  => ['sometimes', 'integer'],
        ]);

        $submission = $this->service->createDraft($validated);
        $submission->load(['formTemplate', 'student']);

        return response()->json([
            'success' => true,
            'message' => 'Submission created.',
            'data'    => new AssessmentSubmissionResource($submission),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, AssessmentSubmission $submission): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $submission->load(['formTemplate', 'student', 'scores', 'riskLevel', 'answers', 'history']);

        return response()->json([
            'success' => true,
            'data'    => new AssessmentSubmissionResource($submission),
        ]);
    }

    public function update(Request $request, AssessmentSubmission $submission): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'answers'                    => ['sometimes', 'array'],
            'answers.*.question_id'      => ['required_with:answers', 'integer'],
            'answers.*.answer_value'     => ['sometimes', 'nullable'],
            'answers.*.repeat_index'     => ['sometimes', 'integer'],
        ]);

        if (! empty($validated['answers'])) {
            $this->service->saveAnswers($submission, $validated['answers']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Submission updated.',
            'data'    => new AssessmentSubmissionResource($submission->fresh()->load(['formTemplate', 'student'])),
        ]);
    }

    public function submit(Request $request, AssessmentSubmission $submission): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $submission = $this->service->submitForReview($submission);

        return response()->json([
            'success' => true,
            'message' => 'Submission submitted for review.',
            'data'    => new AssessmentSubmissionResource($submission),
        ]);
    }

    public function review(Request $request, AssessmentSubmission $submission): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
            'note'   => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $submission = $validated['action'] === 'approve'
            ? $this->service->approve($submission, $validated['note'] ?? null)
            : $this->service->reject($submission, $validated['note'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Submission reviewed.',
            'data'    => new AssessmentSubmissionResource($submission),
        ]);
    }

    public function destroy(Request $request, AssessmentSubmission $submission): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $submission->delete();

        return response()->json(['success' => true, 'message' => 'Submission deleted.', 'data' => null]);
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }
        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }
        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }

    private function authorizePreschoolUser(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }
        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacherpreschool'], true)) {
            return null;
        }
        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }

    private function paginationShape($paginator): array
    {
        return [
            'page'       => $paginator->currentPage(),
            'perPage'    => $paginator->perPage(),
            'total'      => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }
}
