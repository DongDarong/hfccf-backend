<?php

namespace App\Http\Controllers\Api\Scholarship;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scholarship\StoreScholarshipStudentRequest;
use App\Http\Requests\Scholarship\UpdateScholarshipStudentRequest;
use App\Http\Resources\Scholarship\ScholarshipStudentResource;
use App\Models\ScholarshipStudent;
use App\Models\User;
use App\Services\ScholarshipService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScholarshipStudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'grade_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:16'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $gradeLevel = trim((string) ($validated['grade_level'] ?? ''));
        $gender = trim((string) ($validated['gender'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = ScholarshipStudent::query()->withCount('applications');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('student_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('guardian_name', 'like', $like)
                    ->orWhere('guardian_phone', 'like', $like)
                    ->orWhere('school_name', 'like', $like)
                    ->orWhere('grade_level', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($gradeLevel !== '') {
            $query->where('grade_level', $gradeLevel);
        }

        if ($gender !== '') {
            $query->where('gender', $gender);
        }

        $sortColumn = match ($sortBy) {
            'student_code' => 'student_code',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'grade_level' => 'grade_level',
            'status' => 'status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Scholarship students retrieved successfully.',
            $paginator,
            $request,
            ScholarshipStudentResource::class,
        );
    }

    public function store(StoreScholarshipStudentRequest $request): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        $student = ScholarshipStudent::query()->create([
            'student_code' => $data['student_code'] ?? $this->generateStudentCode(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'school_name' => $data['school_name'],
            'grade_level' => $data['grade_level'],
            'guardian_name' => $data['guardian_name'],
            'guardian_phone' => $data['guardian_phone'],
            'address' => $data['address'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ])->loadCount('applications');

        return ApiResponse::successResponse(
            'Scholarship student created successfully.',
            [
                'student' => ScholarshipStudentResource::make($student)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $student = ScholarshipStudent::query()->withCount('applications')->find($id);

        if (! $student) {
            return ApiResponse::errorResponse('Scholarship student not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'Scholarship student retrieved successfully.',
            [
                'student' => ScholarshipStudentResource::make($student)->resolve($request),
            ],
        );
    }

    public function update(UpdateScholarshipStudentRequest $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $student = ScholarshipStudent::query()->find($id);
        if (! $student) {
            return ApiResponse::errorResponse('Scholarship student not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        foreach (['student_code', 'first_name', 'last_name', 'gender', 'date_of_birth', 'phone', 'email', 'school_name', 'grade_level', 'guardian_name', 'guardian_phone', 'address', 'status', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $student->{$field} = $data[$field];
            }
        }

        $student->save();
        $student->loadCount('applications');

        return ApiResponse::successResponse(
            'Scholarship student updated successfully.',
            [
                'student' => ScholarshipStudentResource::make($student)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $student = ScholarshipStudent::query()->find($id);
        if (! $student) {
            return ApiResponse::errorResponse('Scholarship student not found.', null, Response::HTTP_NOT_FOUND);
        }

        $student->delete();

        return ApiResponse::successResponse('Scholarship student deleted successfully.', null);
    }

    private function generateStudentCode(): string
    {
        return app(ScholarshipService::class)->generateStudentCode();
    }

    private function authorizeScholarshipAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminscholarship'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }
}
