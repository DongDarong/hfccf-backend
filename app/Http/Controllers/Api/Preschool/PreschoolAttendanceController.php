<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolAttendanceRequest;
use App\Http\Requests\Preschool\UpdatePreschoolAttendanceRequest;
use App\Http\Resources\Preschool\PreschoolAttendanceResource;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\User;
use App\Support\PreschoolAcademicLifecycleService;
use App\Support\PreschoolLifecycleGuardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $query = $this->attendanceQueryForUser($request->user());
        $this->applyAttendanceFilters($request, $query);

        $paginator = $query
            ->with(['student', 'preschoolClass', 'recordedBy', 'academicYear', 'term'])
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->paginate($this->perPage($request), ['*'], 'page', $this->page($request));

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance retrieved successfully.',
            'data' => [
                'items' => PreschoolAttendanceResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolAttendanceRequest $request): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        if ($response = $this->assertTeacherCanAccessClass($request->user(), (int) $request->validated()['class_id'])) {
            return $response;
        }

        $data = $request->validated();
        if ($response = app(PreschoolLifecycleGuardService::class)->attendanceWriteLock($request->user(), $data)) {
            return $response;
        }
        $academicContext = app(PreschoolAcademicLifecycleService::class)->currentContext();
        $attendance = PreschoolAttendanceRecord::query()->create([
            'class_id' => $data['class_id'],
            'student_id' => $data['student_id'],
            'recorded_by_user_id' => $request->user()->id,
            'attendance_date' => $data['attendance_date'],
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'academic_year_id' => $academicContext['academic_year_id'] ?? null,
            'term_id' => $academicContext['term_id'] ?? null,
        ]);

        $attendance->load(['student', 'preschoolClass', 'recordedBy', 'academicYear', 'term']);

        return response()->json([
            'success' => true,
            'message' => 'Attendance recorded successfully.',
            'data' => [
                'attendance' => PreschoolAttendanceResource::make($attendance)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePreschoolAttendanceRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $attendance = PreschoolAttendanceRecord::query()->find($id);
        if (! $attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        if ($response = $this->assertTeacherCanAccessClass($request->user(), (int) ($request->validated()['class_id'] ?? $attendance->class_id))) {
            return $response;
        }

        $data = $request->validated();
        if ($response = app(PreschoolLifecycleGuardService::class)->attendanceWriteLock($request->user(), $data, $attendance)) {
            return $response;
        }
        foreach (['class_id', 'student_id', 'attendance_date', 'status', 'note'] as $field) {
            if (array_key_exists($field, $data)) {
                $attendance->{$field} = $data[$field];
            }
        }

        $attendance->save();
        $attendance->load(['student', 'preschoolClass', 'recordedBy', 'academicYear', 'term']);

        return response()->json([
            'success' => true,
            'message' => 'Attendance updated successfully.',
            'data' => [
                'attendance' => PreschoolAttendanceResource::make($attendance)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function teacherAttendance(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $query = $this->attendanceQueryForUser($request->user());
        $this->applyAttendanceFilters($request, $query);

        $paginator = $query
            ->with(['student', 'preschoolClass', 'recordedBy', 'academicYear', 'term'])
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->paginate($this->perPage($request), ['*'], 'page', $this->page($request));

        return response()->json([
            'success' => true,
            'message' => 'Teacher attendance retrieved successfully.',
            'data' => [
                'items' => PreschoolAttendanceResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    private function attendanceQueryForUser(User $user): Builder
    {
        $query = PreschoolAttendanceRecord::query();

        if ($user->role_code === 'teacher-preschool') {
            $teacherClassIds = PreschoolClass::query()
                ->where('teacher_user_id', $user->id)
                ->pluck('id')
                ->all();
            $query->whereIn('class_id', $teacherClassIds);
        }

        return $query;
    }

    private function applyAttendanceFilters(Request $request, Builder $query): void
    {
        $search = trim((string) $request->query('search', ''));
        $classId = trim((string) $request->query('class_id', ''));
        $studentId = trim((string) $request->query('student_id', ''));
        $status = trim((string) $request->query('status', ''));
        $date = trim((string) $request->query('attendance_date', ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('status', 'like', $like)
                    ->orWhere('note', 'like', $like)
                    ->orWhereHas('student', static function (Builder $studentQuery) use ($like): void {
                        $studentQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('student_code', 'like', $like);
                    })
                    ->orWhereHas('preschoolClass', static function (Builder $classQuery) use ($like): void {
                        $classQuery->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
            });
        }

        if ($classId !== '') {
            $query->where('class_id', $classId);
        }
        if ($studentId !== '') {
            $query->where('student_id', $studentId);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($date !== '') {
            $query->whereDate('attendance_date', $date);
        }
    }

    private function assertTeacherCanAccessClass(?User $user, int $classId): ?JsonResponse
    {
        if (! $user || in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        if ($user->role_code !== 'teacher-preschool') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $hasAccess = PreschoolClass::query()
            ->where('id', $classId)
            ->where('teacher_user_id', $user->id)
            ->exists();

        if ($hasAccess) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function authorizeAny(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function page(Request $request): int
    {
        return max((int) $request->query('page', 1), 1);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 10), 1), 100);
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
