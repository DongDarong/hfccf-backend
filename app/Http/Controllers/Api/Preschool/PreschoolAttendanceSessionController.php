<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Resources\Preschool\PreschoolAttendanceSessionResource;
use App\Models\PreschoolAttendanceSession;
use App\Models\User;
use App\Support\PreschoolAttendanceSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAttendanceSessionController extends Controller
{
    public function index(Request $request, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'date' => ['sometimes', 'nullable', 'date'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'status' => ['sometimes', 'nullable', Rule::in(['scheduled', 'open', 'completed', 'locked', 'cancelled', 'closed'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $service->paginateSessions($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance sessions retrieved successfully.',
            'data' => [
                'items' => PreschoolAttendanceSessionResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
                'summary' => $service->statusSummary($request->user(), $validated),
            ],
        ], Response::HTTP_OK);
    }

    public function store(Request $request, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeManager($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:preschool_classes,id'],
            'attendance_date' => ['required', 'date'],
            'schedule_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_schedule_entries,id'],
            'start_time' => ['sometimes', 'nullable', 'string', 'max:20'],
            'end_time' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'nullable', Rule::in(['scheduled', 'open', 'completed', 'locked', 'cancelled', 'closed'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $session = $service->createManualSession($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance session created successfully.',
            'data' => [
                'attendanceSession' => PreschoolAttendanceSessionResource::make($session)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function generate(Request $request, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeManager($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'date' => ['sometimes', 'nullable', 'date'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = $validated['start_date'] ?? $validated['date'] ?? now()->toDateString();
        $endDate = $validated['end_date'] ?? $validated['date'] ?? $startDate;
        $sessions = $service->generateSessionsForDateRange($request->user(), $startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance sessions generated successfully.',
            'data' => [
                'items' => PreschoolAttendanceSessionResource::collection($sessions)->resolve($request),
                'generatedCount' => $sessions->count(),
            ],
        ], Response::HTTP_OK);
    }

    public function today(Request $request, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $sessions = $service->todaySessions($request->user(), now());

        return response()->json([
            'success' => true,
            'message' => 'Today\'s preschool attendance sessions retrieved successfully.',
            'data' => [
                'items' => PreschoolAttendanceSessionResource::collection($sessions)->resolve($request),
                'summary' => $service->statusSummary($request->user(), ['date' => now()->toDateString()]),
            ],
        ], Response::HTTP_OK);
    }

    public function missing(Request $request, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $sessions = $service->missingSessions($request->user(), $validated['start_date'] ?? null, $validated['end_date'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Missing preschool attendance sessions retrieved successfully.',
            'data' => [
                'items' => PreschoolAttendanceSessionResource::collection($sessions)->resolve($request),
                'count' => $sessions->count(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PreschoolAttendanceSession $attendanceSession): JsonResponse
    {
        if ($response = $this->authorizeSessionViewer($request->user(), $attendanceSession)) {
            return $response;
        }

        $attendanceSession->load(['preschoolClass.teacher', 'schedule', 'createdBy', 'closedBy', 'attendanceRecords.student']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance session retrieved successfully.',
            'data' => [
                'attendanceSession' => PreschoolAttendanceSessionResource::make($attendanceSession)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function storeRecords(Request $request, PreschoolAttendanceSession $attendanceSession, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeSessionActor($request->user(), $attendanceSession)) {
            return $response;
        }

        $validated = $request->validate([
            'records' => ['sometimes', 'array', 'min:1'],
            'records.*.student_id' => ['required_with:records', 'integer', 'exists:preschool_students,id'],
            'records.*.status' => ['required_with:records', Rule::in(['present', 'absent', 'late', 'excused'])],
            'records.*.note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'student_id' => ['required_without:records', 'integer', 'exists:preschool_students,id'],
            'status' => ['required_without:records', Rule::in(['present', 'absent', 'late', 'excused'])],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'finalize' => ['sometimes', 'boolean'],
            'complete' => ['sometimes', 'boolean'],
            'submit' => ['sometimes', 'boolean'],
        ]);

        $saved = $service->saveRecordsForSession($request->user(), $attendanceSession, $validated);
        $session = $attendanceSession->refresh()->load(['preschoolClass.teacher', 'schedule', 'createdBy', 'closedBy', 'attendanceRecords.student']);

        return response()->json([
            'success' => true,
            'message' => 'Attendance records saved successfully.',
            'data' => [
                'attendanceSession' => PreschoolAttendanceSessionResource::make($session)->resolve($request),
                'attendanceRecords' => $saved->map(static function ($record): array {
                    return [
                        'id' => $record->id,
                        'attendanceSessionId' => $record->attendance_session_id,
                        'classId' => $record->class_id,
                        'studentId' => $record->student_id,
                        'attendanceDate' => $record->attendance_date?->toDateString(),
                        'status' => $record->status,
                        'note' => $record->note,
                    ];
                })->values()->all(),
            ],
        ], Response::HTTP_OK);
    }

    public function open(Request $request, PreschoolAttendanceSession $attendanceSession, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeSessionActor($request->user(), $attendanceSession)) {
            return $response;
        }

        $session = $service->openSession($request->user(), $attendanceSession);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance session opened successfully.',
            'data' => [
                'attendanceSession' => PreschoolAttendanceSessionResource::make($session)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function complete(Request $request, PreschoolAttendanceSession $attendanceSession, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeSessionActor($request->user(), $attendanceSession)) {
            return $response;
        }

        $session = $service->completeSession($request->user(), $attendanceSession);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance session completed successfully.',
            'data' => [
                'attendanceSession' => PreschoolAttendanceSessionResource::make($session)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function lock(Request $request, PreschoolAttendanceSession $attendanceSession, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeManager($request->user())) {
            return $response;
        }

        $session = $service->lockSession($request->user(), $attendanceSession);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance session locked successfully.',
            'data' => [
                'attendanceSession' => PreschoolAttendanceSessionResource::make($session)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function reopen(Request $request, PreschoolAttendanceSession $attendanceSession, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeManager($request->user())) {
            return $response;
        }

        $session = $service->reopenSession($request->user(), $attendanceSession);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance session reopened successfully.',
            'data' => [
                'attendanceSession' => PreschoolAttendanceSessionResource::make($session)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function cancel(Request $request, PreschoolAttendanceSession $attendanceSession, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeManager($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'cancellation_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $reason = $validated['cancellation_reason'] ?? $validated['reason'] ?? null;
        $session = $service->cancelSession($request->user(), $attendanceSession, $reason);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance session cancelled successfully.',
            'data' => [
                'attendanceSession' => PreschoolAttendanceSessionResource::make($session)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function close(Request $request, PreschoolAttendanceSession $attendanceSession, PreschoolAttendanceSessionService $service): JsonResponse
    {
        if ($response = $this->authorizeSessionActor($request->user(), $attendanceSession)) {
            return $response;
        }

        $session = $service->closeSession($request->user(), $attendanceSession);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance session completed successfully.',
            'data' => [
                'attendanceSession' => PreschoolAttendanceSessionResource::make($session)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeViewer(?User $user): ?JsonResponse
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

    private function authorizeManager(?User $user): ?JsonResponse
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

    private function authorizeSessionViewer(?User $user, PreschoolAttendanceSession $session): ?JsonResponse
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

        if ($user->role_code === 'teacher-preschool' && $session->preschoolClass?->teacher_user_id === $user->id) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function authorizeSessionActor(?User $user, PreschoolAttendanceSession $session): ?JsonResponse
    {
        return $this->authorizeSessionViewer($user, $session);
    }
}
