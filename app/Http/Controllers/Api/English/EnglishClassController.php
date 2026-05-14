<?php

namespace App\Http\Controllers\Api\English;

use App\Http\Controllers\Controller;
use App\Http\Requests\English\StoreEnglishClassRequest;
use App\Http\Requests\English\UpdateEnglishClassRequest;
use App\Http\Resources\English\EnglishClassResource;
use App\Models\EnglishClass;
use App\Models\User;
use App\Services\EnglishService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnglishClassController extends Controller
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
            'level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'teacher_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $level = trim((string) ($validated['level'] ?? ''));
        $teacherUserId = trim((string) ($validated['teacher_user_id'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = EnglishClass::query()->with(['teacher', 'students'])->withCount(['students', 'tasks']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('class_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('level', 'like', $like)
                    ->orWhere('room', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($level !== '') {
            $query->where('level', $level);
        }

        if ($teacherUserId !== '') {
            $query->where('teacher_user_id', $teacherUserId);
        }

        $sortColumn = match ($sortBy) {
            'class_code' => 'class_code',
            'name' => 'name',
            'level' => 'level',
            'status' => 'status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'English classes retrieved successfully.',
            $paginator,
            $request,
            EnglishClassResource::class,
        );
    }

    public function store(StoreEnglishClassRequest $request, EnglishService $englishService): JsonResponse
    {
        $data = $request->validated();

        $class = EnglishClass::query()->create([
            'class_code' => $data['class_code'] ?? $englishService->nextClassCode(),
            'name' => $data['name'],
            'level' => $data['level'],
            'teacher_user_id' => $data['teacher_user_id'] ?? null,
            'schedule' => $data['schedule'] ?? null,
            'room' => $data['room'] ?? null,
            'status' => $data['status'] ?? 'active',
            'description' => $data['description'] ?? null,
        ]);

        $this->syncStudents($class, $data['student_ids'] ?? []);
        $class->load(['teacher', 'students'])->loadCount(['students', 'tasks']);

        return ApiResponse::successResponse(
            'English class created successfully.',
            [
                'class' => EnglishClassResource::make($class)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        $class = EnglishClass::query()->with(['teacher', 'students'])->withCount(['students', 'tasks'])->find($id);

        if (! $class) {
            return ApiResponse::errorResponse('Class not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'English class retrieved successfully.',
            [
                'class' => EnglishClassResource::make($class)->resolve($request),
            ],
        );
    }

    public function update(UpdateEnglishClassRequest $request, string $id): JsonResponse
    {
        $class = EnglishClass::query()->find($id);

        if (! $class) {
            return ApiResponse::errorResponse('Class not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        foreach (['class_code', 'name', 'level', 'teacher_user_id', 'schedule', 'room', 'status', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $class->{$field} = $data[$field];
            }
        }

        $class->save();
        $this->syncStudents($class, array_key_exists('student_ids', $data) ? $data['student_ids'] : null);
        $class->load(['teacher', 'students'])->loadCount(['students', 'tasks']);

        return ApiResponse::successResponse(
            'English class updated successfully.',
            [
                'class' => EnglishClassResource::make($class)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        $class = EnglishClass::query()->find($id);

        if (! $class) {
            return ApiResponse::errorResponse('Class not found.', null, Response::HTTP_NOT_FOUND);
        }

        $class->delete();

        return ApiResponse::successResponse('English class deleted successfully.', null);
    }

    private function syncStudents(EnglishClass $class, ?array $studentIds): void
    {
        if ($studentIds === null) {
            return;
        }

        $class->students()->sync(
            collect($studentIds)->mapWithKeys(static fn ($studentId) => [
                $studentId => [
                    'enrolled_at' => now(),
                    'status' => 'active',
                ],
            ])->all(),
        );
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
