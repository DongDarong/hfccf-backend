<?php

namespace App\Http\Controllers\Api\English;

use App\Http\Controllers\Controller;
use App\Http\Requests\English\StoreEnglishSubmissionRequest;
use App\Http\Requests\English\UpdateEnglishSubmissionRequest;
use App\Http\Resources\English\EnglishSubmissionResource;
use App\Models\EnglishTask;
use App\Models\EnglishTaskSubmission;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnglishSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeEnglishAccess($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'task_id' => ['sometimes', 'nullable', 'integer', 'exists:english_tasks,id'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:english_classes,id'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $taskId = (int) ($validated['task_id'] ?? 0);
        $classId = (int) ($validated['class_id'] ?? 0);
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = EnglishTaskSubmission::query()->with(['task.class', 'student', 'reviewedBy']);

        if ($request->user()?->role_code === 'teacher-english') {
            $teacherId = $request->user()?->id;
            $query->whereHas('task.class', function (Builder $builder) use ($teacherId): void {
                $builder->where('teacher_user_id', $teacherId);
            });
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('submission_text', 'like', $like)
                    ->orWhere('feedback', 'like', $like)
                    ->orWhere('submission_status', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('submission_status', $status);
        }

        if ($taskId > 0) {
            $query->where('task_id', $taskId);
        }

        if ($classId > 0) {
            $query->whereHas('task', function (Builder $builder) use ($classId): void {
                $builder->where('class_id', $classId);
            });
        }

        $sortColumn = match ($sortBy) {
            'submitted_at' => 'submitted_at',
            'submission_status' => 'submission_status',
            'score' => 'score',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'English submissions retrieved successfully.',
            $paginator,
            $request,
            EnglishSubmissionResource::class,
        );
    }

    public function store(StoreEnglishSubmissionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $submission = EnglishTaskSubmission::query()->create([
            'task_id' => $data['task_id'],
            'student_id' => $data['student_id'],
            'submission_text' => $data['submission_text'] ?? null,
            'submitted_at' => $data['submitted_at'] ?? now(),
            'submission_status' => $data['submission_status'] ?? 'submitted',
            'score' => $data['score'] ?? null,
            'feedback' => $data['feedback'] ?? null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ]);

        $submission->load(['task.class', 'student', 'reviewedBy']);

        return ApiResponse::successResponse(
            'English submission created successfully.',
            [
                'submission' => EnglishSubmissionResource::make($submission)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateEnglishSubmissionRequest $request, string $id): JsonResponse
    {
        $submission = EnglishTaskSubmission::query()->with(['task.class', 'student', 'reviewedBy'])->find($id);

        if (! $submission) {
            return ApiResponse::errorResponse('Submission not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        foreach (['task_id', 'student_id', 'submission_text', 'submitted_at', 'submission_status', 'score', 'feedback'] as $field) {
            if (array_key_exists($field, $data)) {
                $submission->{$field} = $data[$field];
            }
        }

        if (
            array_key_exists('submission_status', $data)
            && $data['submission_status'] === 'reviewed'
        ) {
            $submission->reviewed_by_user_id = $request->user()?->id;
            $submission->reviewed_at = now();
        }

        if (
            array_key_exists('score', $data)
            || array_key_exists('feedback', $data)
            || array_key_exists('submission_status', $data)
        ) {
            if ($submission->submission_status === 'reviewed') {
                $submission->reviewed_by_user_id = $submission->reviewed_by_user_id ?? $request->user()?->id;
                $submission->reviewed_at = $submission->reviewed_at ?? now();
            }
        }

        $submission->save();
        $submission->load(['task.class', 'student', 'reviewedBy']);

        return ApiResponse::successResponse(
            'English submission updated successfully.',
            [
                'submission' => EnglishSubmissionResource::make($submission)->resolve($request),
            ],
        );
    }

    private function authorizeEnglishAccess(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminenglish', 'teacher-english'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }
}
