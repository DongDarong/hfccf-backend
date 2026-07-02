<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolScheduleRequest;
use App\Http\Requests\Preschool\UpdatePreschoolScheduleRequest;
use App\Http\Resources\Preschool\PreschoolAttendanceSessionResource;
use App\Http\Resources\Preschool\PreschoolScheduleEntryResource;
use App\Models\PreschoolScheduleEntry;
use App\Models\User;
use App\Support\PreschoolLifecycleGuardService;
use App\Support\PreschoolScheduleSessionHistoryService;
use App\Support\PreschoolScheduleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PreschoolScheduleController extends Controller
{
    /**
     * Schedule management is admin-only because conflict checks affect every
     * timetable view, while read-only views are exposed through teacher routes.
     */
    public function index(Request $request, PreschoolScheduleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'teacher_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'day_of_week' => ['sometimes', 'nullable', 'integer', 'between:1,7'],
        ]);

        $paginator = $service->paginateSchedules($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedules retrieved successfully.',
            'data' => [
                'items' => PreschoolScheduleEntryResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolScheduleRequest $request, PreschoolScheduleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $payload = $request->validated();
        if ($response = app(PreschoolLifecycleGuardService::class)->scheduleWriteLock($request->user(), $payload)) {
            return $response;
        }
        $conflicts = $service->detectConflicts(null, $payload);

        if ($conflicts !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule conflict detected.',
                'data' => [
                    'conflicts' => $conflicts,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $schedule = $service->createSchedule($request->user(), $payload)->load(['preschoolClass.teacher', 'teacher', 'academicYear', 'term']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedule created successfully.',
            'data' => [
                'schedule' => PreschoolScheduleEntryResource::make($schedule)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, PreschoolScheduleEntry $schedule): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $schedule->load(['preschoolClass.teacher', 'teacher', 'academicYear', 'term']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedule retrieved successfully.',
            'data' => [
                'schedule' => PreschoolScheduleEntryResource::make($schedule)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolScheduleRequest $request, PreschoolScheduleEntry $schedule, PreschoolScheduleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $payload = $request->validated();
        if ($response = app(PreschoolLifecycleGuardService::class)->scheduleWriteLock($request->user(), $payload, $schedule)) {
            return $response;
        }
        $conflicts = $service->detectConflicts($schedule, $payload);

        if ($conflicts !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule conflict detected.',
                'data' => [
                    'conflicts' => $conflicts,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updated = $service->updateSchedule($request->user(), $schedule, $payload);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedule updated successfully.',
            'data' => [
                'schedule' => PreschoolScheduleEntryResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, PreschoolScheduleEntry $schedule, PreschoolScheduleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        if ($response = app(PreschoolLifecycleGuardService::class)->scheduleWriteLock($request->user(), [], $schedule)) {
            return $response;
        }

        $service->archiveSchedule($request->user(), $schedule);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedule archived successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    public function sessions(Request $request, PreschoolScheduleEntry $schedule, PreschoolScheduleSessionHistoryService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['sometimes', 'nullable', Rule::in(array_merge(['closed'], \App\Models\PreschoolAttendanceSession::STATUSES))],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $service->paginateScheduleSessions($request->user(), $schedule, $validated);
        $summary = $service->summary($request->user(), $schedule, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedule sessions retrieved successfully.',
            'data' => [
                'items' => PreschoolAttendanceSessionResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
                'summary' => $summary,
            ],
        ], Response::HTTP_OK);
    }

    public function todaySession(Request $request, PreschoolScheduleEntry $schedule, PreschoolScheduleSessionHistoryService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $session = $service->todaySession($request->user(), $schedule);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedule today session retrieved successfully.',
            'data' => [
                'session' => $session
                    ? PreschoolAttendanceSessionResource::make($session)->resolve($request)
                    : null,
            ],
        ], Response::HTTP_OK);
    }

    public function history(Request $request, PreschoolScheduleEntry $schedule, PreschoolScheduleSessionHistoryService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $history = $service->history($request->user(), $schedule);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedule history retrieved successfully.',
            'data' => [
                'schedule' => $history['schedule'],
                'todaySession' => $history['todaySession']
                    ? PreschoolAttendanceSessionResource::make($history['todaySession'])->resolve($request)
                    : null,
                'recentSessions' => PreschoolAttendanceSessionResource::collection($history['recentSessions'] ?? [])->resolve($request),
                'summary' => $history['summary'],
                'alerts' => $history['alerts'],
                'guardianContacts' => $history['guardianContacts'],
            ],
        ], Response::HTTP_OK);
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

    /**
     * Keep Preschools list pagination consistent so the frontend can reuse the
     * same table and pagination shell already used across the module.
     */
    private function paginationShape(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }
}
