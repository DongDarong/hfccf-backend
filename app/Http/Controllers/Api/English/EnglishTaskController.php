<?php

namespace App\Http\Controllers\Api\English;

use App\Http\Controllers\Controller;
use App\Http\Requests\English\StoreEnglishTaskRequest;
use App\Http\Requests\English\UpdateEnglishTaskRequest;
use App\Http\Resources\English\EnglishTaskResource;
use App\Models\EnglishTask;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnglishTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:english_classes,id'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $classId = (int) ($validated['class_id'] ?? 0);
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = EnglishTask::query()->with(['class', 'assignedBy'])->withCount('submissions');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('task_status', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('task_status', $status);
        }

        if ($classId > 0) {
            $query->where('class_id', $classId);
        }

        $sortColumn = match ($sortBy) {
            'title' => 'title',
            'due_date' => 'due_date',
            'task_status' => 'task_status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'English tasks retrieved successfully.',
            $paginator,
            $request,
            EnglishTaskResource::class,
        );
    }

    public function store(StoreEnglishTaskRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $task = EnglishTask::query()->create([
            'class_id' => $data['class_id'],
            'assigned_by_user_id' => $user?->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'task_status' => $data['task_status'] ?? 'draft',
        ]);

        $task->load(['class', 'assignedBy'])->loadCount('submissions');

        return ApiResponse::successResponse(
            'English task created successfully.',
            [
                'task' => EnglishTaskResource::make($task)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        $task = EnglishTask::query()->with(['class', 'assignedBy'])->withCount('submissions')->find($id);

        if (! $task) {
            return ApiResponse::errorResponse('Task not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'English task retrieved successfully.',
            [
                'task' => EnglishTaskResource::make($task)->resolve($request),
            ],
        );
    }

    public function update(UpdateEnglishTaskRequest $request, string $id): JsonResponse
    {
        $task = EnglishTask::query()->find($id);

        if (! $task) {
            return ApiResponse::errorResponse('Task not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        foreach (['class_id', 'title', 'description', 'due_date', 'task_status'] as $field) {
            if (array_key_exists($field, $data)) {
                $task->{$field} = $data[$field];
            }
        }

        $task->save();
        $task->load(['class', 'assignedBy'])->loadCount('submissions');

        return ApiResponse::successResponse(
            'English task updated successfully.',
            [
                'task' => EnglishTaskResource::make($task)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        $task = EnglishTask::query()->find($id);

        if (! $task) {
            return ApiResponse::errorResponse('Task not found.', null, Response::HTTP_NOT_FOUND);
        }

        $task->delete();

        return ApiResponse::successResponse('English task deleted successfully.', null);
    }

    private function authorizeEnglishAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminenglish'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }
}
