<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolStudentRequest;
use App\Http\Requests\Preschool\UpdatePreschoolStudentRequest;
use App\Http\Resources\Preschool\PreschoolStudentResource;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Support\ImageStorage;
use App\Support\PreschoolSettingsBackboneService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolStudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:32'],
            'class_id' => ['sometimes', 'nullable', 'integer'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $gender = trim((string) ($validated['gender'] ?? ''));
        $classId = trim((string) ($validated['class_id'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = PreschoolStudent::query()->with(['classes']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('student_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('guardian_name', 'like', $like)
                    ->orWhere('guardian_phone', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($gender !== '') {
            $query->where('gender', $gender);
        }

        if ($classId !== '') {
            $query->whereHas('classes', static function (Builder $builder) use ($classId): void {
                $builder->where('preschool_classes.id', $classId);
            });
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

        return response()->json([
            'success' => true,
            'message' => 'Preschool students retrieved successfully.',
            'data' => [
                'items' => PreschoolStudentResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolStudentRequest $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        $student = PreschoolStudent::query()->create([
            'student_code' => $data['student_code'] ?? $this->nextStudentCode(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_phone' => $data['guardian_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'],
            'avatar' => ImageStorage::store($request->file('avatar'), 'preschool/students'),
        ]);

        $this->syncStudentClasses($student, $data['class_ids'] ?? []);
        $student->load(['classes']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool student created successfully.',
            'data' => [
                'student' => PreschoolStudentResource::make($student)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $student = PreschoolStudent::query()->with(['classes'])->find($id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool student retrieved successfully.',
            'data' => [
                'student' => PreschoolStudentResource::make($student)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolStudentRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $student = PreschoolStudent::query()->find($id);
        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        foreach (['student_code', 'first_name', 'last_name', 'gender', 'date_of_birth', 'guardian_name', 'guardian_phone', 'address', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $student->{$field} = $data[$field];
            }
        }

        $replaceAvatar = $request->hasFile('avatar');
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);

        if ($replaceAvatar) {
            ImageStorage::delete($student->avatar);
            $student->avatar = ImageStorage::store($request->file('avatar'), 'preschool/students');
        } elseif ($removeAvatar) {
            ImageStorage::delete($student->avatar);
            $student->avatar = null;
        }

        $student->save();
        $this->syncStudentClasses($student, $data['class_ids'] ?? null);
        $student->load(['classes']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool student updated successfully.',
            'data' => [
                'student' => PreschoolStudentResource::make($student)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $student = PreschoolStudent::query()->find($id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $student->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool student deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    private function academicContext(): array
    {
        return app(PreschoolSettingsBackboneService::class)->currentAcademicContext();
    }

    private function syncStudentClasses(PreschoolStudent $student, ?array $classIds): void
    {
        $academicContext = $this->academicContext();

        if ($classIds === null) {
            // Keep history rows intact when the caller is not changing class
            // membership. The inactive pivots remain available for assignment
            // and transfer history while the active roster stays unchanged.

            return;
        }

        $targetClassIds = collect($classIds)
            ->filter()
            ->map(static fn ($classId) => trim((string) $classId))
            ->filter()
            ->unique()
            ->values();

        foreach ($targetClassIds as $classId) {
            $assignment = PreschoolClassStudent::query()->firstOrNew([
                'class_id' => $classId,
                'student_id' => $student->id,
            ]);

            if (! $assignment->exists || ($assignment->status ?? null) !== 'active') {
                $assignment->enrolled_at = now();
            }

            $assignment->academic_year = $academicContext['academic_year'];
            $assignment->term_label = $academicContext['term_label'];
            $assignment->status = 'active';
            $assignment->save();
        }

        PreschoolClassStudent::query()
            ->where('student_id', $student->id)
            ->whereNotIn('class_id', $targetClassIds->all())
            ->update(['status' => 'inactive']);

        $student->load('classes');
        foreach ($student->classes as $class) {
            $class->students_count = PreschoolClassStudent::query()
                ->where('class_id', $class->id)
                ->where('status', 'active')
                ->count();
            $class->save();
        }
    }

    private function nextStudentCode(): string
    {
        $maxNumeric = PreschoolStudent::withTrashed()
            ->pluck('student_code')
            ->map(static function (string $code): int {
                return (int) preg_replace('/^PS-STU-/', '', $code);
            })
            ->max() ?? 0;

        $next = $maxNumeric + 1;

        return 'PS-STU-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
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

    private function paginationShape($paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }
}
