<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolClassRequest;
use App\Http\Requests\Preschool\UpdatePreschoolClassRequest;
use App\Http\Resources\Preschool\PreschoolClassResource;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolClassTeacherAssignment;
use App\Models\PreschoolClass;
use App\Models\User;
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

        $query = PreschoolClass::query()->with(['teacher', 'students', 'teacherAssignments']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('teacher_display_name', 'like', $like)
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
            'code' => 'code',
            'name' => 'name',
            'level' => 'level',
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
        $class = PreschoolClass::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'teacher_user_id' => $data['teacher_user_id'] ?? null,
            'teacher_display_name' => $this->resolveTeacherDisplayName($data['teacher_user_id'] ?? null, $data['teacher_display_name'] ?? null),
            'level' => $data['level'],
            'schedule' => $data['schedule'] ?? null,
            'students_count' => (int) ($data['students_count'] ?? 0),
            'status' => $data['status'],
            'room' => $data['room'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncTeacherAssignmentHistory($class, $class->teacher_user_id, $class->teacher_display_name);
        $this->syncClassStudents($class, $data['student_ids'] ?? []);
        $class->load(['teacher', 'students', 'teacherAssignments']);

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

        $class = PreschoolClass::query()->with(['teacher', 'students', 'teacherAssignments'])->find($id);

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

        foreach (['code', 'name', 'level', 'schedule', 'status', 'room', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $class->{$field} = $data[$field];
            }
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
        $class->load(['teacher', 'students', 'teacherAssignments']);

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

    private function syncClassStudents(PreschoolClass $class, ?array $studentIds): void
    {
        if ($studentIds === null) {
            // Assignment state is preserved on the pivot table, so we only
            // recalculate the active count when the caller is not changing the
            // student roster. This keeps inactive assignments available for
            // history views without changing current enrollment behavior.
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

            // Re-activating an existing assignment should keep a clean audit
            // trail in the pivot row instead of deleting and recreating links.
            if (! $assignment->exists || ($assignment->status ?? null) !== 'active') {
                $assignment->enrolled_at = now();
            }

            $assignment->status = 'active';
            $assignment->save();
        }

        PreschoolClassStudent::query()
            ->where('class_id', $class->id)
            ->whereNotIn('student_id', $targetStudentIds->all())
            ->update(['status' => 'inactive']);

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
                $activeAssignment->save();
            }

            return;
        }

        if ($activeAssignment && (string) $activeAssignment->teacher_user_id === $teacherUserId) {
            if ($teacherDisplayName !== null && trim((string) $teacherDisplayName) !== trim((string) $activeAssignment->teacher_display_name)) {
                $activeAssignment->teacher_display_name = $teacherDisplayName;
                $activeAssignment->save();
            }

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
