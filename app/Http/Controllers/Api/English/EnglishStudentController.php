<?php

namespace App\Http\Controllers\Api\English;

use App\Http\Controllers\Controller;
use App\Http\Requests\English\StoreEnglishStudentRequest;
use App\Http\Requests\English\UpdateEnglishStudentRequest;
use App\Http\Resources\English\EnglishStudentResource;
use App\Models\EnglishStudent;
use App\Models\User;
use App\Services\EnglishService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnglishStudentController extends Controller
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
            'gender' => ['sometimes', 'nullable', 'string', 'max:16'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $gender = trim((string) ($validated['gender'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = EnglishStudent::query()->withCount(['classes', 'submissions']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('student_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('guardian_name', 'like', $like)
                    ->orWhere('guardian_phone', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($gender !== '') {
            $query->where('gender', $gender);
        }

        $sortColumn = match ($sortBy) {
            'student_code' => 'student_code',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'status' => 'status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'English students retrieved successfully.',
            $paginator,
            $request,
            EnglishStudentResource::class,
        );
    }

    public function store(StoreEnglishStudentRequest $request, EnglishService $englishService): JsonResponse
    {
        $data = $request->validated();

        $student = EnglishStudent::query()->create([
            'student_code' => $data['student_code'] ?? $englishService->nextStudentCode(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_phone' => $data['guardian_phone'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        $this->syncClasses($student, $data['class_ids'] ?? []);
        $student->loadCount(['classes', 'submissions']);

        return ApiResponse::successResponse(
            'English student created successfully.',
            [
                'student' => EnglishStudentResource::make($student)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        $student = EnglishStudent::query()->withCount(['classes', 'submissions'])->find($id);

        if (! $student) {
            return ApiResponse::errorResponse('Student not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'English student retrieved successfully.',
            [
                'student' => EnglishStudentResource::make($student)->resolve($request),
            ],
        );
    }

    public function update(UpdateEnglishStudentRequest $request, string $id): JsonResponse
    {
        $student = EnglishStudent::query()->find($id);

        if (! $student) {
            return ApiResponse::errorResponse('Student not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        foreach (['student_code', 'first_name', 'last_name', 'gender', 'date_of_birth', 'guardian_name', 'guardian_phone', 'email', 'phone', 'address', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $student->{$field} = $data[$field];
            }
        }

        $student->save();
        $this->syncClasses($student, array_key_exists('class_ids', $data) ? $data['class_ids'] : null);
        $student->loadCount(['classes', 'submissions']);

        return ApiResponse::successResponse(
            'English student updated successfully.',
            [
                'student' => EnglishStudentResource::make($student)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        $student = EnglishStudent::query()->find($id);

        if (! $student) {
            return ApiResponse::errorResponse('Student not found.', null, Response::HTTP_NOT_FOUND);
        }

        $student->delete();

        return ApiResponse::successResponse('English student deleted successfully.', null);
    }

    private function syncClasses(EnglishStudent $student, ?array $classIds): void
    {
        if ($classIds === null) {
            return;
        }

        $student->classes()->sync(
            collect($classIds)->mapWithKeys(static fn ($classId) => [
                $classId => [
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
