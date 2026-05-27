<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolScheduleRequest;
use App\Http\Requests\Preschool\UpdatePreschoolScheduleRequest;
use App\Http\Resources\Preschool\PreschoolScheduleEntryResource;
use App\Models\PreschoolScheduleEntry;
use App\Models\User;
use App\Support\PreschoolScheduleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $service->archiveSchedule($request->user(), $schedule);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedule archived successfully.',
            'data' => null,
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
