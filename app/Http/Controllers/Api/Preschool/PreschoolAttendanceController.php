<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolAttendanceRequest;
use App\Http\Requests\Preschool\UpdatePreschoolAttendanceRequest;
use App\Http\Resources\Preschool\PreschoolAttendanceResource;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolClass;
use App\Models\PreschoolClassStudent;
use App\Models\User;
use App\Services\PreschoolGuardianCommunicationService;
use App\Support\PreschoolAcademicLifecycleService;
use App\Support\PreschoolAttendanceSessionService;
use App\Support\PreschoolLifecycleGuardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PreschoolAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $query = PreschoolAttendanceRecord::query();
        if ($response = $this->applyTeacherScope($request->user(), $query)) {
            return $response;
        }
        $this->applyAttendanceFilters($request, $query);

        $paginator = $query
            ->with(['student', 'preschoolClass', 'recordedBy', 'academicYear', 'term', 'attendanceSession.schedule'])
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

        $data = $request->validated();
        $session = $this->resolveAttendanceSession($data);
        $attendance = app(PreschoolAttendanceSessionService::class)->saveLegacyAttendance($request->user(), $data);

        $attendance->load(['student', 'preschoolClass', 'recordedBy', 'academicYear', 'term', 'attendanceSession.schedule']);
        $this->syncAttendanceFollowUpSafely($attendance, $request->user(), 'store');

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

        $data = $request->validated();
        $session = $this->resolveAttendanceSession($data);
        if (! $session || (int) $attendance->attendance_session_id !== (int) $session->id) {
            return response()->json(['success' => false, 'error' => ['code' => 'SESSION_REQUIRED', 'message' => 'The record must be updated through its Attendance Session.'], 'data' => null], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $studentId = (int) ($data['student_id'] ?? $attendance->student_id);
        $saved = app(PreschoolAttendanceSessionService::class)->saveRecordsForSession($request->user(), $session, ['records' => [[
            'student_id' => $studentId,
            'status' => $data['status'] ?? $attendance->status,
            'note' => $data['note'] ?? $attendance->note,
        ]]]);
        $attendance = $saved->first();
        $attendance->load(['student', 'preschoolClass', 'recordedBy', 'academicYear', 'term', 'attendanceSession.schedule']);
        $this->syncAttendanceFollowUpSafely($attendance, $request->user(), 'update');

        return response()->json([
            'success' => true,
            'message' => 'Attendance updated successfully.',
            'data' => [
                'attendance' => PreschoolAttendanceResource::make($attendance)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function summary(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $query = PreschoolAttendanceRecord::query();
        if ($response = $this->applyTeacherScope($request->user(), $query)) {
            return $response;
        }
        $this->applyAttendanceFilters($request, $query);

        $records = $query->get();
        $statusCounts = $records->groupBy('status')->map->count();

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance summary retrieved successfully.',
            'data' => [
                'summary' => [
                    'totalRecords' => $records->count(),
                    'present' => (int) ($statusCounts->get('present', 0)),
                    'absent' => (int) ($statusCounts->get('absent', 0)),
                    'late' => (int) ($statusCounts->get('late', 0)),
                    'excused' => (int) ($statusCounts->get('excused', 0)),
                    'uniqueStudents' => $records->pluck('student_id')->filter()->unique()->count(),
                    'uniqueClasses' => $records->pluck('class_id')->filter()->unique()->count(),
                    'linkedSessions' => $records->whereNotNull('attendance_session_id')->count(),
                    'legacyUnlinkedRecords' => $records->whereNull('attendance_session_id')->count(),
                    'statusCounts' => [
                        'present' => (int) ($statusCounts->get('present', 0)),
                        'absent' => (int) ($statusCounts->get('absent', 0)),
                        'late' => (int) ($statusCounts->get('late', 0)),
                        'excused' => (int) ($statusCounts->get('excused', 0)),
                    ],
                ],
            ],
        ], Response::HTTP_OK);
    }

    public function missing(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $query = PreschoolAttendanceRecord::query()
            ->with(['student', 'preschoolClass', 'recordedBy', 'attendanceSession.schedule']);
        if ($response = $this->applyTeacherScope($request->user(), $query)) {
            return $response;
        }
        $this->applyAttendanceFilters($request, $query);
        $query->whereNull('attendance_session_id');

        $paginator = $query
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->paginate($this->perPage($request), ['*'], 'page', $this->page($request));

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance records without sessions retrieved successfully.',
            'data' => [
                'items' => PreschoolAttendanceResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
                'count' => $paginator->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function classSummary(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $query = PreschoolAttendanceRecord::query()->with(['preschoolClass.teacher']);
        if ($response = $this->applyTeacherScope($request->user(), $query)) {
            return $response;
        }
        $this->applyAttendanceFilters($request, $query);
        $records = $query->get();

        $items = $records
            ->groupBy('class_id')
            ->map(static function ($group, $classId): array {
                $first = $group->first();
                $statusCounts = $group->groupBy('status')->map->count();
                $class = $first?->preschoolClass;

                return [
                    'classId' => (int) $classId,
                    'classCode' => $class?->code,
                    'className' => $class?->name,
                    'teacherName' => trim(($class?->teacher?->first_name ?? '').' '.($class?->teacher?->last_name ?? '')),
                    'totalRecords' => $group->count(),
                    'present' => (int) ($statusCounts->get('present', 0)),
                    'absent' => (int) ($statusCounts->get('absent', 0)),
                    'late' => (int) ($statusCounts->get('late', 0)),
                    'excused' => (int) ($statusCounts->get('excused', 0)),
                    'linkedSessions' => $group->whereNotNull('attendance_session_id')->count(),
                    'legacyUnlinkedRecords' => $group->whereNull('attendance_session_id')->count(),
                ];
            })
            ->values()
            ->all();

        $summary = [
            'totalRecords' => $records->count(),
            'classes' => count($items),
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'linkedSessions' => $records->whereNotNull('attendance_session_id')->count(),
            'legacyUnlinkedRecords' => $records->whereNull('attendance_session_id')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance class summary retrieved successfully.',
            'data' => [
                'summary' => $summary,
                'items' => $items,
            ],
        ], Response::HTTP_OK);
    }

    private function applyAttendanceFilters(Request $request, Builder $query): void
    {
        $search = trim((string) $request->query('search', ''));
        $classId = trim((string) $request->query('class_id', ''));
        $studentId = trim((string) $request->query('student_id', ''));
        $status = trim((string) $request->query('status', ''));
        $date = trim((string) $request->query('attendance_date', ''));
        $sessionId = trim((string) $request->query('attendance_session_id', ''));

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
        if ($sessionId !== '') {
            $query->where('attendance_session_id', $sessionId);
        }
    }

    private function resolveAttendanceSession(array &$data): ?PreschoolAttendanceSession
    {
        if (! array_key_exists('attendance_session_id', $data) || $data['attendance_session_id'] === null || $data['attendance_session_id'] === '') {
            return null;
        }

        return app(PreschoolAttendanceSessionService::class)->findSessionOrFail((int) $data['attendance_session_id']);
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

    private function assertTeacherCanAccessAttendance(?User $user, int $classId, int $studentId): ?JsonResponse
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

        $hasClassAccess = PreschoolClass::query()
            ->where('id', $classId)
            ->where('teacher_user_id', $user->id)
            ->exists();

        if (! $hasClassAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $hasStudentAccess = PreschoolClassStudent::query()
            ->where('class_id', $classId)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->where('enrollment_status', 'active')
            ->exists();

        if (! $hasStudentAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function applyTeacherScope(?User $user, Builder $query): ?JsonResponse
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

        $query->whereIn('class_id', PreschoolClass::query()
            ->where('teacher_user_id', $user->id)
            ->pluck('id')
            ->all());

        return null;
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

    private function syncAttendanceFollowUpSafely(PreschoolAttendanceRecord $attendance, User $actor, string $action): void
    {
        try {
            app(PreschoolGuardianCommunicationService::class)->syncAttendanceFollowUp($attendance, $actor);
        } catch (Throwable $throwable) {
            report($throwable);

            Log::error('Failed to sync preschool attendance follow-up.', [
                'action' => $action,
                'attendance_id' => $attendance->id,
                'student_id' => $attendance->student_id,
                'class_id' => $attendance->class_id,
                'status' => $attendance->status,
                'actor_user_id' => $actor->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
