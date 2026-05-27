<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Assessment\AssessmentSubmissionResource;
use App\Http\Resources\Assessment\AssessmentSubmissionListResource;
use App\Models\AssessmentExportLog;
use App\Models\AssessmentPrintTemplate;
use App\Models\AssessmentSubmission;
use App\Models\User;
use App\Jobs\GenerateAssessmentExportJob;
use App\Services\AssessmentSubmissionService;
use App\Services\AssessmentExportService;
use App\Services\AssessmentLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssessmentSubmissionController extends Controller
{
    public function __construct(
        private AssessmentSubmissionService $service,
        private AssessmentLifecycleService $lifecycle,
        private AssessmentExportService $exportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page'               => ['sometimes', 'integer', 'min:1'],
            'per_page'           => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status'             => ['sometimes', 'nullable', 'string', 'max:32'],
            'template_id'       => ['sometimes', 'nullable', 'integer'],
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
        $query = AssessmentSubmission::query()->with(['template', 'student', 'riskLevel', 'scores']);

        if (in_array($user->role_code, ['teacherpreschool'], true)) {
            $query->where('assessor_id', $user->id);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        $templateFilter = $validated['template_id'] ?? $validated['form_template_id'] ?? null;
        if (! empty($templateFilter)) {
            $query->where('template_id', $templateFilter);
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
            'template_id'      => ['sometimes', 'integer', 'exists:assessment_form_templates,id'],
            'form_template_id' => ['sometimes', 'integer', 'exists:assessment_form_templates,id'],
            'student_id'       => ['required', 'integer', 'exists:preschool_students,id'],
            'answers'          => ['sometimes', 'array'],
            'answers.*.question_id'    => ['required_with:answers', 'integer'],
            'answers.*.answer_value'   => ['sometimes', 'nullable'],
            'answers.*.answer_text'    => ['sometimes', 'nullable'],
            'answers.*.answer_number'  => ['sometimes', 'nullable'],
            'answers.*.answer_options' => ['sometimes', 'nullable'],
            'answers.*.answer_matrix'  => ['sometimes', 'nullable'],
            'answers.*.answer_file'    => ['sometimes', 'nullable'],
            'answers.*.answer_gps'     => ['sometimes', 'nullable'],
            'answers.*.repeat_index'   => ['sometimes', 'integer'],
        ]);

        $submission = $this->service->createDraft($validated);
        $submission->load(['template', 'student', 'riskLevel', 'scores']);

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

        $submission->load(['template', 'student', 'scores', 'riskLevel', 'answers', 'history']);

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
            'answers.*.answer_text'      => ['sometimes', 'nullable'],
            'answers.*.answer_number'    => ['sometimes', 'nullable'],
            'answers.*.answer_options'   => ['sometimes', 'nullable'],
            'answers.*.answer_matrix'    => ['sometimes', 'nullable'],
            'answers.*.answer_file'      => ['sometimes', 'nullable'],
            'answers.*.answer_gps'       => ['sometimes', 'nullable'],
            'answers.*.repeat_index'     => ['sometimes', 'integer'],
        ]);

        if (! empty($validated['answers'])) {
            $this->service->saveAnswers($submission, $validated['answers']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Submission updated.',
            'data'    => new AssessmentSubmissionResource($submission->fresh()->load(['template', 'student', 'riskLevel', 'scores'])),
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

    public function print(Request $request, AssessmentSubmission $submission): BinaryFileResponse|JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:assessment_print_templates,id'],
            'queue'       => ['sometimes', 'boolean'],
        ]);

        $template = AssessmentPrintTemplate::query()
            ->whereKey($validated['template_id'])
            ->where('form_template_id', $submission->template_id)
            ->firstOrFail();

        $storedFormat = $template->format === 'xlsx' ? 'excel' : $template->format;
        $exportLog = $this->lifecycle->startExport([
            'export_type' => $storedFormat,
            'scope' => 'single',
            'submission_ids' => [$submission->id],
            'print_template_id' => $template->id,
            'meta' => [
                'submission_id' => $submission->id,
                'template_id' => $template->id,
                'template_name' => $template->name,
                'format' => $template->format,
                'print_mode' => true,
            ],
        ]);

        if ($request->boolean('queue')) {
            GenerateAssessmentExportJob::dispatch($exportLog->id);

            return response()->json([
                'success' => true,
                'message' => 'Print queued.',
                'data' => [
                    'id' => $exportLog->id,
                    'uuid' => $exportLog->uuid,
                    'status' => $exportLog->status,
                    'export_type' => $template->format,
                    'storage_type' => $exportLog->export_type,
                    'download_url' => route('assessment.exports.download', $exportLog),
                    'status_url' => route('assessment.exports.status', $exportLog),
                ],
            ], Response::HTTP_ACCEPTED);
        }

        $exportLog = $this->exportService->generate($exportLog);

        return $this->downloadPrintExport($exportLog);
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

    private function downloadPrintExport(AssessmentExportLog $exportLog): BinaryFileResponse|JsonResponse
    {
        if ($exportLog->status !== 'completed' || empty($exportLog->file_path) || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($exportLog->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Export file is not ready.',
                'data' => null,
            ], Response::HTTP_CONFLICT);
        }

        $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($exportLog->file_path);
        $filename = basename($exportLog->file_path);
        $mime = match ($exportLog->export_type) {
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
            'html' => 'text/html; charset=UTF-8',
            default => 'application/pdf',
        };

        return response()->download($absolutePath, $filename, [
            'Content-Type' => $mime,
        ]);
    }
}
