<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolClassRequest;
use App\Http\Requests\Preschool\UpdatePreschoolClassRequest;
use App\Http\Resources\Preschool\PreschoolClassResource;
use App\Models\PreschoolClass;
use App\Models\PreschoolClassLevel;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolClassTeacherAssignment;
use App\Models\User;
use App\Support\PreschoolClassCodeService;
use App\Support\PreschoolAcademicLifecycleService;
use App\Support\PreschoolLifecycleGuardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolClassController extends Controller
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
            'level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'class_level_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_class_levels,id'],
            'teacher_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $level = trim((string) ($validated['level'] ?? ''));
        $classLevelId = trim((string) ($validated['class_level_id'] ?? ''));
        $teacherUserId = trim((string) ($validated['teacher_user_id'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = PreschoolClass::query()->with(['teacher', 'classLevel', 'students', 'teacherAssignments']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('teacher_display_name', 'like', $like)
                    ->orWhere('level', 'like', $like)
                    ->orWhere('room', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereHas('classLevel', function (Builder $classLevelQuery) use ($like): void {
                        $classLevelQuery->where('name_en', 'like', $like)
                            ->orWhere('name_kh', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($classLevelId !== '') {
            $query->where('class_level_id', $classLevelId);
        } elseif ($level !== '') {
            $query->where('level', $level);
        }

        if ($teacherUserId !== '') {
            $query->where('teacher_user_id', $teacherUserId);
        }

        $sortColumn = match ($sortBy) {
            'code' => 'code',
            'name' => 'name',
            'level' => 'level',
            'class_level_id' => 'class_level_id',
            'status' => 'status',
            'students_count' => 'students_count',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Preschool classes retrieved successfully.',
            'data' => [
                'items' => PreschoolClassResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolClassRequest $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        $classLevel = PreschoolClassLevel::query()->findOrFail($data['class_level_id']);

        if (($data['teacher_user_id'] ?? null) !== null || ! empty($data['student_ids'] ?? [])) {
            if ($response = app(PreschoolLifecycleGuardService::class)->assignmentWriteLock($request->user(), $data)) {
                return $response;
            }
        }

        $class = app(PreschoolClassCodeService::class)->createWithRetry(
            $classLevel,
            function (string $code) use ($classLevel, $data) {
                $class = PreschoolClass::query()->create([
                    'code' => $code,
                    'name' => $data['name'],
                    'teacher_user_id' => $data['teacher_user_id'] ?? null,
                    'teacher_display_name' => $this->resolveTeacherDisplayName($data['teacher_user_id'] ?? null, $data['teacher_display_name'] ?? null),
                    'class_level_id' => $classLevel->id,
                    'level' => $classLevel->name_en,
                    'schedule' => $data['schedule'] ?? null,
                    'students_count' => (int) ($data['students_count'] ?? 0),
                    'status' => $data['status'],
                    'room' => $data['room'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                $this->syncTeacherAssignmentHistory($class, $class->teacher_user_id, $class->teacher_display_name);
                $this->syncClassStudents($class, $data['student_ids'] ?? []);

                return $class;
            }
        );
        $class->load(['teacher', 'classLevel', 'students', 'teacherAssignments']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool class created successfully.',
            'data' => [
                'class' => PreschoolClassResource::make($class)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $class = PreschoolClass::query()->with(['teacher', 'classLevel', 'students', 'teacherAssignments'])->find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool class retrieved successfully.',
            'data' => [
                'class' => PreschoolClassResource::make($class)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolClassRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $class = PreschoolClass::query()->find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        if (array_key_exists('teacher_user_id', $data) || array_key_exists('student_ids', $data)) {
            if ($response = app(PreschoolLifecycleGuardService::class)->assignmentWriteLock($request->user(), $data)) {
                return $response;
            }
        }

        foreach (['code', 'name', 'level', 'schedule', 'status', 'room', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $class->{$field} = $data[$field];
            }
        }

        if (array_key_exists('class_level_id', $data)) {
            $classLevel = PreschoolClassLevel::query()->findOrFail($data['class_level_id']);
            $class->class_level_id = $classLevel->id;
            $class->level = $classLevel->name_en;
        }

        if (array_key_exists('teacher_user_id', $data)) {
            $class->teacher_user_id = $data['teacher_user_id'];
            if (! array_key_exists('teacher_display_name', $data)) {
                $class->teacher_display_name = $this->resolveTeacherDisplayName($data['teacher_user_id'] ?? null, $class->teacher_display_name);
            }
        }

        if (array_key_exists('teacher_display_name', $data)) {
            $class->teacher_display_name = $this->resolveTeacherDisplayName($class->teacher_user_id, $data['teacher_display_name']);
        }

        if (array_key_exists('students_count', $data)) {
            $class->students_count = max(0, (int) $data['students_count']);
        }

        $class->save();
        $this->syncTeacherAssignmentHistory($class, $class->teacher_user_id, $class->teacher_display_name);
        $this->syncClassStudents($class, $data['student_ids'] ?? null);
        $class->load(['teacher', 'classLevel', 'students', 'teacherAssignments']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool class updated successfully.',
            'data' => [
                'class' => PreschoolClassResource::make($class)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $class = PreschoolClass::query()->find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $class->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool class deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    private function academicContext(): array
    {
        return app(PreschoolAcademicLifecycleService::class)->currentContext();
    }

    private function syncClassStudents(PreschoolClass $class, ?array $studentIds): void
    {
        $academicContext = $this->academicContext();

        if ($studentIds === null) {
            $class->students_count = PreschoolClassStudent::query()
                ->where('class_id', $class->id)
                ->where('status', 'active')
                ->count();
            $class->save();

            return;
        }

        $targetStudentIds = collect($studentIds)
            ->filter()
            ->map(static fn ($studentId) => trim((string) $studentId))
            ->filter()
            ->unique()
            ->values();

        foreach ($targetStudentIds as $studentId) {
            $assignment = PreschoolClassStudent::query()->firstOrNew([
                'class_id' => $class->id,
                'student_id' => $studentId,
            ]);

            if (! $assignment->exists || ($assignment->status ?? null) !== 'active') {
                $assignment->enrolled_at = now();
            }

            $assignment->academic_year = $academicContext['academic_year'];
            $assignment->term_label = $academicContext['term_label'];
            $assignment->academic_year_id = $academicContext['academic_year_id'] ?? null;
            $assignment->term_id = $academicContext['term_id'] ?? null;
            $assignment->enrollment_status = 'active';
            $assignment->enrollment_started_at = $assignment->enrollment_started_at ?: now();
            $assignment->enrollment_ended_at = null;
            $assignment->status = 'active';
            $assignment->save();
        }

        PreschoolClassStudent::query()
            ->where('class_id', $class->id)
            ->whereNotIn('student_id', $targetStudentIds->all())
            ->update([
                'status' => 'inactive',
                'enrollment_status' => 'inactive',
                'enrollment_ended_at' => now(),
            ]);

        $class->students_count = PreschoolClassStudent::query()
            ->where('class_id', $class->id)
            ->where('status', 'active')
            ->count();
        $class->save();
    }

    /**
     * Teacher assignment history is intentionally stored separately from the
     * current teacher_user_id field so Preschool admins can review ownership
     * changes without turning teachers into portal users or mutating history.
     */
    private function syncTeacherAssignmentHistory(PreschoolClass $class, ?string $teacherUserId, ?string $teacherDisplayName = null): void
    {
        $academicContext = $this->academicContext();
        $teacherUserId = trim((string) $teacherUserId);
        $teacherDisplayName = $this->resolveTeacherDisplayName($teacherUserId, $teacherDisplayName);

        $activeAssignment = PreschoolClassTeacherAssignment::query()
            ->where('class_id', $class->id)
            ->where('status', 'active')
            ->latest('assigned_at')
            ->first();

        if ($teacherUserId === '') {
            if ($activeAssignment) {
                $activeAssignment->status = 'inactive';
                $activeAssignment->ended_at = now();
                $activeAssignment->academic_year_id = $academicContext['academic_year_id'] ?? $activeAssignment->academic_year_id;
                $activeAssignment->term_id = $academicContext['term_id'] ?? $activeAssignment->term_id;
                $activeAssignment->save();
            }

            return;
        }

        if ($activeAssignment && (string) $activeAssignment->teacher_user_id === $teacherUserId) {
            $activeAssignment->academic_year_id = $academicContext['academic_year_id'] ?? $activeAssignment->academic_year_id;
            $activeAssignment->term_id = $academicContext['term_id'] ?? $activeAssignment->term_id;
            if ($teacherDisplayName !== null && trim((string) $teacherDisplayName) !== trim((string) $activeAssignment->teacher_display_name)) {
                $activeAssignment->teacher_display_name = $teacherDisplayName;
            }

            $activeAssignment->save();

            return;
        }

        if ($activeAssignment) {
            $activeAssignment->status = 'inactive';
            $activeAssignment->ended_at = now();
            $activeAssignment->save();
        }

        PreschoolClassTeacherAssignment::query()->create([
            'class_id' => $class->id,
            'teacher_user_id' => $teacherUserId,
            'teacher_display_name' => $teacherDisplayName,
            'status' => 'active',
            'assigned_at' => now(),
            'academic_year' => $this->academicContext()['academic_year'],
            'term_label' => $this->academicContext()['term_label'],
            'academic_year_id' => $this->academicContext()['academic_year_id'] ?? null,
            'term_id' => $this->academicContext()['term_id'] ?? null,
            'ended_at' => null,
        ]);
    }

    private function resolveTeacherDisplayName($teacherUserId, $teacherDisplayName): ?string
    {
        $name = trim((string) $teacherDisplayName);
        if ($name !== '') {
            return $name;
        }

        if (! $teacherUserId) {
            return null;
        }

        $teacher = User::query()->find($teacherUserId);

        return $teacher ? trim($teacher->first_name.' '.$teacher->last_name) : null;
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
